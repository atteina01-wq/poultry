<?php
/**
 * helpers.php - Shared business-logic helpers for San Isidro Poultry Farm Management System.
 *
 * These functions centralize the core recording operations so that
 * duplicate SQL across pages stays consistent. They do NOT change the
 * database schema and do NOT delete data.
 *
 * All functions assume $conn (PDO) is already available.
 */

if (!function_exists('record_mortality')) {
    /**
     * Record a mortality/adjustment entry and update the batch's current quantity.
     *
     * @param PDO    $conn     Database connection
     * @param int    $batch_id Batch ID
     * @param int    $qty      Signed quantity: negative = loss, positive = adjustment/re-add
     * @param string $reason   Reason/remarks
     * @param string $date     Date (Y-m-d)
     * @param float  $financial_value Financial value/loss
     * @return bool True on success, false on failure
     */
    function record_mortality($conn, $batch_id, $qty, $reason, $date, $arg6 = 0.00, $arg7 = null) {
        $ownsTx = false;
        try {
            if (!$conn->inTransaction()) {
                $conn->beginTransaction();
                $ownsTx = true;
            }

            // Support both signatures:
            // 1. record_mortality($conn, $batch_id, $qty, $reason, $date, $userId) [6 args, legacy]
            // 2. record_mortality($conn, $batch_id, $qty, $reason, $date, $financial_value, $userId) [7 args]
            if (func_num_args() <= 6 && !is_null($arg6) && is_int($arg6)) {
                $financial_value = 0.00;
                $recorded_by = (int)$arg6;
            } else {
                $financial_value = (float)($arg6 ?? 0.00);
                $recorded_by = $arg7 !== null ? (int)$arg7 : null;
            }

            // Guard: Ensure mortality loss cannot exceed current batch headcount
            if ($qty < 0) {
                $stmtC = $conn->prepare("SELECT current_quantity FROM batches WHERE batch_id = ? FOR UPDATE");
                $stmtC->execute([$batch_id]);
                $current = $stmtC->fetchColumn();
                if ($current === false) {
                    throw new Exception("Batch #{$batch_id} not found.");
                }
                if (abs($qty) > (int)$current) {
                    throw new Exception("Mortality loss count (" . abs($qty) . ") cannot exceed current batch headcount (" . (int)$current . ").");
                }
            }

            // Auto-calculate financial loss if not explicitly provided or is 0
            if ($financial_value <= 0 && $qty < 0) {
                $lossCalc = calculate_mortality_financial_loss($conn, $batch_id, abs($qty), $date);
                $financial_value = $lossCalc['total_loss'];
            }

            // 1. Record in mortality_records
            $userId = $recorded_by ?? ($_SESSION['user_id'] ?? null);
            $stmt = $conn->prepare("INSERT INTO mortality_records (batch_id, date, quantity_change, reason, financial_value, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$batch_id, $date, $qty, $reason, $financial_value, $userId]);
            $mortality_id = $conn->lastInsertId();

            if ($financial_value > 0) {
                $stmtExp = $conn->prepare("INSERT INTO expenses (batch_id, category, amount, description, date, source_type, source_id, recorded_by, created_at) VALUES (?, 'other', ?, ?, ?, 'mortality', ?, ?, NOW())");
                $desc = "Mortality Loss: " . abs($qty) . " birds. Reason: " . $reason;
                $stmtExp->execute([$batch_id, $financial_value, $desc, $date, $mortality_id, $userId]);
            }

            // 2. Update batches current_quantity safely
            $stmt = $conn->prepare("UPDATE batches SET current_quantity = GREATEST(0, current_quantity + ?) WHERE batch_id = ?");
            $stmt->execute([$qty, $batch_id]);

            // 3. Record Audit Log for Gatekeeping
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $auditDetails = json_encode([
                'batch_id' => $batch_id,
                'quantity_loss' => abs($qty),
                'reason' => $reason,
                'date' => $date,
                'financial_loss' => $financial_value,
                'action_type' => 'mortality_deduction'
            ]);
            $stmtAudit = $conn->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at) VALUES (?, 'create', 'mortality_records', ?, ?, ?, NOW())");
            $stmtAudit->execute([$userId, $mortality_id, $auditDetails, $ip]);

            if ($ownsTx && $conn->inTransaction()) {
                $conn->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($ownsTx && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['last_mortality_error'] = $e->getMessage();
            return false;
        }
    }
}

if (!function_exists('record_feed_consumption')) {
    /**
     * Record a feed consumption entry and deduct from inventory.
     * Prevents consumption greater than available inventory.
     * Enforces broiler batch linkage.
     *
     * @param PDO         $conn          Database connection
     * @param int         $batch_id      Batch ID
     * @param int         $inventory_id  Inventory item ID
     * @param float       $qty           Quantity used
     * @param string      $date          Date (Y-m-d)
     * @param string|null $feed_stage    Pre-Starter, Starter, Grower, Finisher
     * @return bool True on success, false on failure
     */
    function record_feed_consumption($conn, $batch_id, $inventory_id, $qty, $date, $feed_stage = null, $status = 'completed', $remarks = null) {
        try {
            $conn->beginTransaction();
            $batch_id = (int)$batch_id;
            $inventory_id = (int)$inventory_id;
            $qty = (float)$qty;
            $user_id = $_SESSION['user_id'] ?? null;
            $status = in_array(strtolower($status ?? ''), ['pending', 'completed']) ? strtolower($status) : 'completed';

            // 1. Verify broiler batch linkage
            $stmtB = $conn->prepare("SELECT batch_id, flock_type FROM batches WHERE batch_id = ?");
            $stmtB->execute([$batch_id]);
            $batch = $stmtB->fetch(PDO::FETCH_ASSOC);
            if (!$batch) {
                throw new Exception("Batch #{$batch_id} not found.");
            }
            if (!empty($batch['flock_type']) && strtolower($batch['flock_type']) !== 'broiler') {
                throw new Exception("Feed consumption must be linked to an active broiler batch.");
            }

            // 2. Lock inventory row and verify sufficient stock
            $stmtInv = $conn->prepare("SELECT quantity, unit_cost, item_name, unit FROM inventory WHERE inventory_id = ? FOR UPDATE");
            $stmtInv->execute([$inventory_id]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            if (!$inv) {
                throw new Exception("Feed inventory item not found.");
            }

            $currentStock = (float)$inv['quantity'];
            if ($qty > $currentStock) {
                throw new Exception("Insufficient stock for {$inv['item_name']}! Available: " . number_format($currentStock, 2) . " {$inv['unit']}, Requested: " . number_format($qty, 2) . " {$inv['unit']}.");
            }

            // 3. Validate feed_stage if provided
            $validStages = ['Pre-Starter', 'Starter', 'Grower', 'Finisher'];
            $stageVal = null;
            if ($feed_stage !== null && trim($feed_stage) !== '') {
                $trimmedStage = trim($feed_stage);
                foreach ($validStages as $vs) {
                    if (strcasecmp($vs, $trimmedStage) === 0) {
                        $stageVal = $vs;
                        break;
                    }
                }
                if ($stageVal === null) {
                    throw new Exception("Invalid feed stage: {$feed_stage}. Must be Pre-Starter, Starter, Grower, or Finisher.");
                }
            }

            // 4. Insert consumption record with status
            $completedBy = ($status === 'completed') ? $user_id : null;
            $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

            $stmt = $conn->prepare("
                INSERT INTO feed_consumption (
                    batch_id, inventory_id, feed_stage, status, quantity_used, date,
                    recorded_by, completed_by, completed_at, remarks, unit_cost_snapshot, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $batch_id,
                $inventory_id,
                $stageVal,
                $status,
                $qty,
                $date,
                $user_id,
                $completedBy,
                $completedAt,
                $remarks,
                $inv['unit_cost'] ?? 0.00
            ]);
            $consumptionId = (int)$conn->lastInsertId();

            // 5. Deduct inventory
            $stmtDeduct = $conn->prepare("UPDATE inventory SET previous_quantity = quantity, quantity = quantity - ? WHERE inventory_id = ?");
            $stmtDeduct->execute([$qty, $inventory_id]);

            // 6. Log audit trail
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmtAudit = $conn->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'create', 'feed_consumption', ?, ?, ?, NOW())
            ");
            $stmtAudit->execute([
                $user_id,
                $consumptionId,
                json_encode([
                    'batch_id' => $batch_id,
                    'inventory_id' => $inventory_id,
                    'feed_stage' => $stageVal,
                    'status' => $status,
                    'quantity_used' => $qty,
                    'date' => $date
                ]),
                $ip
            ]);

            $conn->commit();
            return true;
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $_SESSION['last_feed_error'] = $e->getMessage();
            return false;
        }
    }
}

if (!function_exists('record_health_activity')) {
    /**
     * Record a health/supply activity, write to health_records (if Health),
     * log audit trail, and deduct from inventory when the item maps to an inventory record.
     *
     * @param PDO    $conn          Database connection
     * @param int    $batch_id      Batch ID
     * @param string $type          'Health' or 'Supply'
     * @param string $item_name     Item/medicine name
     * @param float  $qty                  Quantity used
     * @param string $date                 Date (Y-m-d)
     * @param string $remarks              Optional remarks
     * @param string|null $illness_observation Optional illness observation
     * @param string|null $doctor_consultation Optional doctor consultation
     * @param string|null $diagnosis      Optional diagnosis
     * @param int|null    $number_of_chickens_treated Number of chickens treated
     * @param string $status               Status: scheduled, in_progress, completed, cancelled
     * @return bool True on success, false on failure
     */
    function record_health_activity($conn, $batch_id, $type, $item_name, $qty, $date, $remarks = '', $illness_observation = null, $doctor_consultation = null, $diagnosis = null, $number_of_chickens_treated = null, $status = 'completed') {
        try {
            $conn->beginTransaction();
            $batch_id = (int)$batch_id;
            $qty = (float)$qty;
            $user_id = $_SESSION['user_id'] ?? null;
            $recordType = 'treatment';
            $validStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
            $healthStatus = in_array(strtolower($status), $validStatuses, true) ? strtolower($status) : 'completed';

            // Ensure batch exists and is broiler
            $stmtBatch = $conn->prepare("SELECT flock_type FROM batches WHERE batch_id = ?");
            $stmtBatch->execute([$batch_id]);
            $flockType = $stmtBatch->fetchColumn();
            if (!$flockType) {
                throw new Exception("Batch #{$batch_id} not found.");
            }
            if (!empty($flockType) && strtolower($flockType) !== 'broiler') {
                throw new Exception("Health records must be linked to a broiler batch.");
            }

            // 1. Resolve inventory_id if medicine/supply exists in inventory
            $inventory_id = null;
            $stmt = $conn->prepare("SELECT inventory_id, quantity, unit, item_name FROM inventory WHERE item_name = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$item_name]);
            $found_inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found_inv) {
                $inventory_id = (int)$found_inv['inventory_id'];
                $available = (float)$found_inv['quantity'];
                if ($qty > $available) {
                    throw new Exception("Insufficient stock for {$found_inv['item_name']}! Available: " . number_format($available, 2) . " {$found_inv['unit']}, Requested: " . number_format($qty, 2) . " {$found_inv['unit']}.");
                }
                $stmt = $conn->prepare("UPDATE inventory SET previous_quantity = quantity, quantity = quantity - ? WHERE inventory_id = ?");
                $stmt->execute([$qty, $inventory_id]);
            }

            // 2. If Health/Treatment, record in health_records table
            $health_id = null;
            if (in_array(strtolower($type), ['health', 'treatment'])) {
                $stmtHr = $conn->prepare("
                    INSERT INTO health_records (
                        batch_id, number_of_chickens_treated, inventory_id, type, medicine_name, illness_observation,
                        doctor_consultation, diagnosis, quantity_used, date, notes,
                        status, recorded_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtHr->execute([
                    $batch_id,
                    $number_of_chickens_treated,
                    $inventory_id,
                    $recordType,
                    $item_name,
                    $illness_observation ?: null,
                    $doctor_consultation ?: null,
                    $diagnosis ?: null,
                    $qty,
                    $date,
                    $remarks ?: null,
                    $healthStatus,
                    $user_id
                ]);
                $health_id = (int)$conn->lastInsertId();

                // Log audit trail
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $stmtAudit = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
                    VALUES (?, 'create', 'health_records', ?, ?, ?, NOW())
                ");
                $stmtAudit->execute([
                    $user_id,
                    $health_id,
                    json_encode([
                        'batch_id' => $batch_id,
                        'medicine_name' => $item_name,
                        'quantity_used' => $qty,
                        'type' => $recordType,
                        'date' => $date,
                        'status' => $healthStatus,
                        'doctor_consultation' => $doctor_consultation,
                        'illness_observation' => $illness_observation,
                        'diagnosis' => $diagnosis,
                        'number_of_chickens_treated' => $number_of_chickens_treated,
                        'notes' => $remarks
                    ]),
                    $ip
                ]);
            }

            // 3. Health activities are managed exclusively in Flock Health Records (healthrecords.php)
            // and no longer inserted into farm operations (activities table) to prevent unwanted pending actions.

            $conn->commit();
            return true;
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $_SESSION['last_health_error'] = $e->getMessage();
            return false;
        }
    }
}

if (!function_exists('get_broiler_stage_info')) {
    /**
     * Determine age, feeding stage, target weight, and harvest readiness for a broiler batch.
     */
    function get_broiler_stage_info($arrival_date, $status = null, $expected_harvest_date = null) {
        $arrTime = strtotime($arrival_date ?? date('Y-m-d'));
        $todayTime = strtotime(date('Y-m-d'));
        $age_days = max(0, (int)floor(($todayTime - $arrTime) / 86400));
        $age_weeks = (int)floor($age_days / 7) + 1;

        $hasFutureHarvest = false;
        $daysLeft = 0;
        if (!empty($expected_harvest_date)) {
            $harvTime = strtotime($expected_harvest_date);
            $daysLeft = (int)round(($harvTime - $todayTime) / 86400);
            if ($daysLeft > 0) {
                $hasFutureHarvest = true;
            }
        }

        if ($status && in_array(strtolower($status), ['harvested', 'closed'])) {
            $stage = "Market-Ready Broilers";
            $feed_type = "Finisher / Feed Withdrawal";
            $target_weight = "1.6kg – 2.2kg";
            $badge_class = "badge bg-success";
            $status_label = "Ready for Transfer / Pickup";
            $is_ready_pickup = true;
            $next_milestone = "Harvest Completed";
        } elseif ($age_days <= 10) {
            $stage = "Brooding Stage";
            $feed_type = "Pre-Starter Crumble";
            $target_weight = "40g – 300g";
            $badge_class = "badge bg-info text-dark";
            $status_label = "Brooding (Pre-Starter)";
            $is_ready_pickup = false;
            $next_milestone = "Switch to Starter at Day 11";
        } elseif ($age_days <= 21) {
            $stage = "Growing Stage";
            $feed_type = "Starter Crumble/Pellet";
            $target_weight = "350g – 900g";
            $badge_class = "badge bg-primary";
            $status_label = "Growing (Starter)";
            $is_ready_pickup = false;
            $next_milestone = "Switch to Finisher at Day 22";
        } elseif ($age_days <= 27) {
            $stage = "Finisher Stage";
            $feed_type = "Finisher Pellet";
            $target_weight = "1.2kg – 1.6kg";
            $badge_class = "badge bg-warning text-dark";
            $status_label = "Finisher Stage";
            $is_ready_pickup = false;
            $next_milestone = "Ready for Harvest at Day 28+";
        } elseif ($hasFutureHarvest || $age_days <= 34) {
            $stage = "Brooding Stage";
            $feed_type = "Finisher Pellet";
            $target_weight = "1.6kg – 2.4kg";
            $badge_class = "badge bg-info text-dark";
            $status_label = "Brooding Stage";
            $is_ready_pickup = false;
            $next_milestone = $daysLeft > 0 ? "Harvest in {$daysLeft} days" : "Ready for Harvest";
        } else {
            $stage = "Market-Ready Broilers";
            $feed_type = "Finisher / Feed Withdrawal";
            $target_weight = "1.6kg – 2.2kg";
            $badge_class = "badge bg-success";
            $status_label = "Ready for Transfer / Pickup";
            $is_ready_pickup = true;
            $next_milestone = "Optimal harvest window (Day 28–35)";
        }

        return [
            'age_days' => $age_days,
            'age_weeks' => $age_weeks,
            'stage' => $stage,
            'feed_type' => $feed_type,
            'target_weight' => $target_weight,
            'badge_class' => $badge_class,
            'status_label' => $status_label,
            'is_ready_pickup' => $is_ready_pickup,
            'next_milestone' => $next_milestone
        ];
    }
}

if (!function_exists('calculate_mortality_financial_loss')) {
    /**
     * Compute economic loss for mortalities:
     * Loss = Initial DOC Cost + Accumulated Feed & Meds cost up to the mortality date.
     */
    function calculate_mortality_financial_loss($conn, $batch_id, $dead_qty, $record_date = null) {
        $dead_qty = abs((int)$dead_qty);
        if ($dead_qty === 0) {
            return ['cost_per_bird' => 0.00, 'total_loss' => 0.00, 'age_days' => 0, 'doc_cost' => 0.00, 'feed_med_cost' => 0.00];
        }

        $stmt = $conn->prepare("SELECT arrival_date, chick_unit_cost FROM batches WHERE batch_id = ?");
        $stmt->execute([$batch_id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        $arrival_date = $batch['arrival_date'] ?? date('Y-m-d');
        $recDate = !empty($record_date) ? $record_date : date('Y-m-d');
        $age_days = max(1, (int)floor((strtotime($recDate) - strtotime($arrival_date)) / 86400));
        
        $doc_cost = !empty($batch['chick_unit_cost']) ? (float)$batch['chick_unit_cost'] : 38.00;

        // Progressive feed & medication cost accumulation model based on standard Philippine Cobb 500 consumption:
        // Day 1-10: ~25g/day avg
        // Day 11-21: ~75g/day avg
        // Day 22-35: ~135g/day avg
        // Daily linear rate ~ ₱2.85 per day
        $feed_med_cost = min(35, $age_days) * 2.85;
        $cost_per_bird = round($doc_cost + $feed_med_cost, 2);
        $total_loss = round($dead_qty * $cost_per_bird, 2);

        return [
            'cost_per_bird' => $cost_per_bird,
            'total_loss' => $total_loss,
            'age_days' => $age_days,
            'doc_cost' => $doc_cost,
            'feed_med_cost' => round($feed_med_cost, 2)
        ];
    }
}

if (!function_exists('check_release_readiness')) {
    /**
     * Determine if an occupied house / batch meets the agreed contract weight and agreed date for release / return to company.
     *
     * In commercial broiler contract growing (Bounty Fresh, San Miguel, etc.):
     * - The grower and integrator establish an agreed average target weight (ABW, e.g. 1.80kg) and agreed harvest/pickup date.
     * - Release Readiness:
     *   1) When the flock reaches the agreed target weight (current_weight_kg >= target_weight_kg), it is ready for release/pickup.
     *      (Even if ahead of the agreed date, early harvest is permitted to avoid feed waste and mortality).
     *   2) OR when the agreed harvest/pickup date has arrived (today >= agreed_date) and harvest window is open.
     *
     * @param PDO $conn Database connection
     * @param int|array $batch Batch ID or batch associative array
     * @param float|null $override_target_weight Optional agreed target weight from a reservation
     * @param string|null $override_target_date Optional agreed pickup/harvest date from a reservation
     * @return array Evaluation with readiness boolean, current/target weights, agreed date, diff, age, badge class, and status message
     */
    function check_release_readiness($conn, $batch, $override_target_weight = null, $override_target_date = null) {
        if (!is_array($batch)) {
            $stmt = $conn->prepare("SELECT batch_id, batch_code, arrival_date, expected_harvest_date, current_quantity, target_weight_kg, current_weight_kg, status FROM batches WHERE batch_id = ?");
            $stmt->execute([(int)$batch]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$batch) {
            return [
                'ready' => false,
                'is_weight_met' => false,
                'is_date_met' => false,
                'is_age_met' => false,
                'current_weight' => 0.0,
                'target_weight' => 1.80,
                'weight_diff' => 1.80,
                'agreed_date' => null,
                'days_to_agreed_date' => 0,
                'age_days' => 0,
                'percent' => 0.0,
                'status_label' => 'Empty / No Batch',
                'badge_class' => 'badge bg-secondary',
                'alert_class' => 'alert-secondary',
                'message' => 'No active flock assigned to this house.'
            ];
        }

        $arrival_date = $batch['arrival_date'] ?? date('Y-m-d');
        $age_days = max(0, (int)floor((strtotime(date('Y-m-d')) - strtotime($arrival_date)) / 86400));
        
        $target_weight = !empty($override_target_weight) && (float)$override_target_weight > 0 
            ? (float)$override_target_weight 
            : (!empty($batch['target_weight_kg']) ? (float)$batch['target_weight_kg'] : 1.80);

        // Determine agreed date
        $agreed_date = !empty($override_target_date) 
            ? $override_target_date 
            : (!empty($batch['expected_harvest_date']) ? $batch['expected_harvest_date'] : null);

        if (empty($agreed_date) && !empty($arrival_date)) {
            $agreed_date = date('Y-m-d', strtotime($arrival_date . ' + 35 days'));
        }

        $today = date('Y-m-d');
        $days_to_agreed_date = !empty($agreed_date) ? (int)round((strtotime($agreed_date) - strtotime($today)) / 86400) : 0;
        $is_date_met = !empty($agreed_date) ? ($today >= $agreed_date) : ($age_days >= 28);
            
        $current_weight = (float)($batch['current_weight_kg'] ?? 0.0);
        
        // If current weight is unrecorded (0.00), calculate an estimated weight based on standard Cobb 500 growth curve
        if ($current_weight <= 0.0) {
            if ($age_days == 0) {
                $current_weight = 0.04;
            } elseif ($age_days <= 7) {
                $current_weight = round(0.04 + ($age_days * (0.18 - 0.04) / 7), 2);
            } elseif ($age_days <= 14) {
                $current_weight = round(0.18 + (($age_days - 7) * (0.45 - 0.18) / 7), 2);
            } elseif ($age_days <= 21) {
                $current_weight = round(0.45 + (($age_days - 14) * (0.92 - 0.45) / 7), 2);
            } elseif ($age_days <= 28) {
                $current_weight = round(0.92 + (($age_days - 21) * (1.55 - 0.92) / 7), 2);
            } elseif ($age_days <= 32) {
                $current_weight = round(1.55 + (($age_days - 28) * (1.85 - 1.55) / 4), 2);
            } else {
                $current_weight = round(1.85 + (($age_days - 32) * 0.06), 2);
            }
        }

        $percent = $target_weight > 0 ? round(($current_weight / $target_weight) * 100, 1) : 0;
        $is_weight_met = $current_weight >= $target_weight;
        $is_age_met = $age_days >= 28;

        $isHarvested = !empty($batch['status']) && in_array(strtolower($batch['status']), ['harvested', 'closed']);
        if ($isHarvested) {
            $is_weight_met = true;
            $is_ready = true;
            $percent = max($percent, 100.0);
        } else {
            // Strict Gate ("Mahigpit"): BOTH agreed target weight AND agreed harvest/pickup date must be completed
            $is_ready = ($is_weight_met && $is_date_met);
        }

        $formattedAgreedDate = !empty($agreed_date) ? date('M d, Y', strtotime($agreed_date)) : 'N/A';

        if ($isHarvested) {
            $status_label = "Harvest Completed (Weight Met & Transferred)";
            $badge_class = "badge bg-success";
            $alert_class = "alert-success";
            $message = "Target weight achieved (" . number_format($current_weight, 2) . " kg >= " . number_format($target_weight, 2) . " kg). Flock harvested and cleared for partner transfer / pickup!";
        } elseif ($is_weight_met && $is_date_met) {
            $status_label = "Agreement Met (Ready for Release)";
            $badge_class = "badge bg-success";
            $alert_class = "alert-success";
            $message = "Target weight met (" . number_format($current_weight, 2) . " kg >= " . number_format($target_weight, 2) . " kg) and agreed date reached (" . $formattedAgreedDate . "). Cleared for admin dispatch & partner pickup!";
        } elseif ($is_weight_met && !$is_date_met) {
            $daysLeft = max(1, $days_to_agreed_date);
            $status_label = "Weight Met (Awaiting Agreed Date - {$daysLeft}d left)";
            $badge_class = "badge bg-warning text-dark";
            $alert_class = "alert-warning";
            $message = "Target weight achieved (" . number_format($current_weight, 2) . " kg >= " . number_format($target_weight, 2) . " kg), but agreed pickup date (" . $formattedAgreedDate . ") has not arrived yet. Release locked until agreed date.";
        } elseif (!$is_weight_met && $is_date_met) {
            $diff = max(0, round($target_weight - $current_weight, 2));
            $status_label = "Date Reached (Underweight by {$diff} kg)";
            $badge_class = "badge bg-warning text-dark";
            $alert_class = "alert-warning";
            $message = "Agreed pickup date has arrived (" . $formattedAgreedDate . "), but flock is underweight (" . number_format($current_weight, 2) . " kg vs agreed " . number_format($target_weight, 2) . " kg). Release locked until flock reaches agreed target weight.";
        } else {
            $diff = max(0, round($target_weight - $current_weight, 2));
            $daysText = $days_to_agreed_date > 0 ? " ({$days_to_agreed_date} days until {$formattedAgreedDate})" : "";
            $status_label = "Below Agreed Weight (" . number_format($current_weight, 2) . " / " . number_format($target_weight, 2) . " kg)";
            $badge_class = "badge bg-secondary";
            $alert_class = "alert-secondary";
            $message = "Current flock weight (" . number_format($current_weight, 2) . " kg) has not met the agreed contract weight (" . number_format($target_weight, 2) . " kg). Underweight by {$diff} kg{$daysText}. Both date and weight must be completed.";
        }

        return [
            'ready' => $is_ready,
            'is_weight_met' => $is_weight_met,
            'is_date_met' => $is_date_met,
            'is_age_met' => $is_age_met,
            'current_weight' => $current_weight,
            'target_weight' => $target_weight,
            'weight_diff' => max(0, round($target_weight - $current_weight, 2)),
            'agreed_date' => $agreed_date,
            'days_to_agreed_date' => $days_to_agreed_date,
            'age_days' => $age_days,
            'percent' => $percent,
            'status_label' => $status_label,
            'badge_class' => $badge_class,
            'alert_class' => $alert_class,
            'message' => $message
        ];
    }
}