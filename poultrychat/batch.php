<?php
ob_start();
include('database.php');
include('db_check.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('auth.php');
include_once('csrf.php');

$user_role = strtolower($_SESSION['role'] ?? 'staff');
$isAdmin = ($user_role === 'admin');

// Enforce Integrator role access restriction: redirect to reservations
if ($user_role === 'integrator') {
    header("Location: reservations.php");
    exit();
}

// Server-side enforcement for batch creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!$isAdmin) {
        $save_error = "Access denied. Only administrators can register or modify flocks.";
    } else {
        try {
            $batch_code = trim($_POST['batch_code'] ?? '');
            $flock_type = 'broiler'; // Enforce broiler-only scope
            $arrival_date = $_POST['arrival_date'] ?? date('Y-m-d');
            $production_date = !empty($_POST['production_date']) ? $_POST['production_date'] : $arrival_date;
            $quantity = intval($_POST['quantity'] ?? 0);
            $breed = trim($_POST['breed'] ?? '');
            $house_pen = trim($_POST['house_pen'] ?? '');
            $supplier = trim($_POST['supplier'] ?? '');
            $delivery_receipt_no = trim($_POST['delivery_receipt_no'] ?? '');
            $chick_unit_cost = floatval($_POST['chick_unit_cost'] ?? 38.00);
            $expected_harvest_date = !empty($_POST['expected_harvest_date']) ? $_POST['expected_harvest_date'] : null;
            $notes = trim($_POST['notes'] ?? '');
            
            // Validation
            if ($quantity <= 0) {
                throw new Exception("Initial quantity must be greater than 0");
            }
            if (empty($batch_code)) {
                throw new Exception("Batch code is required");
            }

            $batchRepo = new \Poultry\Repositories\BatchRepository();
            if ($batchRepo->findByCode($batch_code)) {
                throw new Exception("Batch code '{$batch_code}' already exists.");
            }

            $batchId = $batchRepo->create([
                'batch_code'            => $batch_code,
                'flock_type'            => 'broiler',
                'arrival_date'          => $arrival_date,
                'production_date'       => $production_date,
                'initial_quantity'      => $quantity,
                'breed'                 => $breed ?: null,
                'house_pen'             => $house_pen ?: null,
                'supplier'              => $supplier ?: null,
                'delivery_receipt_no'   => $delivery_receipt_no ?: null,
                'chick_unit_cost'       => $chick_unit_cost > 0 ? $chick_unit_cost : 38.00,
                'target_weight_kg'      => floatval($_POST['target_weight_kg'] ?? 1.80),
                'current_weight_kg'     => floatval($_POST['current_weight_kg'] ?? 0.00),
                'expected_harvest_date' => $expected_harvest_date,
                'notes'                 => $notes ?: null,
                'created_by'            => $_SESSION['user_id'] ?? null,
                'status'                => 'active'
            ]);

            // Sync house status to occupied if assigned
            if (!empty($house_pen)) {
                try {
                    $stmtH = $conn->prepare("UPDATE houses SET status = 'occupied', current_batch_id = ?, vacant_date = ? WHERE house_name = ?");
                    $stmtH->execute([$batchId, $expected_harvest_date, $house_pen]);
                } catch (Exception $ex) {}
            }

            // Sync linked reservation if stocked from a reservation
            $linkedResId = intval($_POST['linked_reservation_id'] ?? 0);
            if ($linkedResId > 0) {
                try {
                    $stmtLink = $conn->prepare("
                        UPDATE reservations 
                        SET batch_id = ?,
                            workflow_stage = 'growing',
                            house_id = COALESCE(house_id, (SELECT house_id FROM houses WHERE house_name = ? LIMIT 1)),
                            updated_at = NOW()
                        WHERE reservation_id = ?
                    ");
                    $stmtLink->execute([$batchId, $house_pen, $linkedResId]);

                    $resNoStmt = $conn->prepare("SELECT reservation_number, customer_id, created_by, quantity_birds FROM reservations WHERE reservation_id = ?");
                    $resNoStmt->execute([$linkedResId]);
                    $resRowData = $resNoStmt->fetch(PDO::FETCH_ASSOC);
                    $linkedResNo = $resRowData['reservation_number'] ?? null;

                    // Notify Integrator that flock is active
                    try {
                        $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
                        $stmtIntUser->execute([(int)($resRowData['customer_id'] ?? 0), (int)($resRowData['created_by'] ?? 0)]);
                        $intUserId = $stmtIntUser->fetchColumn();

                        $houseText = !empty($house_pen) ? " in {$house_pen}" : "";
                        $stockNotifMsg = "Flock Activated (Step 5): Batch {$batch_code} ({$quantity} chicks) is now actively growing{$houseText} under Reservation #{$linkedResNo}. Flock growth monitoring has started.";
                        $insNotifStock = $conn->prepare("
                            INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                            VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())
                        ");
                        if ($intUserId) {
                            $insNotifStock->execute([(int)$intUserId, $stockNotifMsg, $linkedResId]);
                        }

                        $adminStmt = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
                        $adminUserIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: [1];
                        $admStockMsg = "Reservation Accommodated: Batch ({$batch_code}) registered and stocked{$houseText} under Reservation #{$linkedResNo}.";
                        foreach ($adminUserIds as $admId) {
                            $insNotifStock->execute([(int)$admId, $admStockMsg, $linkedResId]);
                        }
                    } catch (\Exception $exN) {}
                } catch (Exception $ex) {}
            }

            $_SESSION['flash_message'] = "Step 5 Activated! Batch " . $batch_code . " for " . ($supplier ?: 'Integrator') . " is now active in " . ($house_pen ?: 'assigned house') . ". The 5-step chick intake workflow is complete!";
            if (!headers_sent()) {
                header("Location: batch.php?activated=" . urlencode($batch_code));
                exit();
            } else {
                echo "<script>window.location.href = 'batch.php?activated=" . urlencode($batch_code) . "';</script>";
                exit();
            }
        } catch (Exception $e) {
            $save_error = "Error: " . $e->getMessage();
        }
    }
}

// Server-side enforcement for batch editing (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_batch'])) {
    if (!$isAdmin) {
        $save_error = "Access denied. Only administrators can modify flocks.";
    } else {
        try {
            $batch_id = intval($_POST['batch_id'] ?? 0);
            $batch_code = trim($_POST['batch_code'] ?? '');
            $arrival_date = $_POST['arrival_date'] ?? date('Y-m-d');
            $production_date = !empty($_POST['production_date']) ? $_POST['production_date'] : $arrival_date;
            $initial_quantity = intval($_POST['initial_quantity'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            $breed = trim($_POST['breed'] ?? '');
            $house_pen = trim($_POST['house_pen'] ?? '');
            $supplier = trim($_POST['supplier'] ?? '');
            $delivery_receipt_no = trim($_POST['delivery_receipt_no'] ?? '');
            $chick_unit_cost = floatval($_POST['chick_unit_cost'] ?? 38.00);
            $target_weight_kg = floatval($_POST['target_weight_kg'] ?? 1.80);
            $current_weight_kg = floatval($_POST['current_weight_kg'] ?? 0.00);
            $expected_harvest_date = !empty($_POST['expected_harvest_date']) ? $_POST['expected_harvest_date'] : null;
            $notes = trim($_POST['notes'] ?? '');

            if ($batch_id <= 0) {
                throw new Exception("Invalid batch identifier.");
            }
            if ($initial_quantity <= 0) {
                throw new Exception("Initial quantity must be greater than 0.");
            }
            if (empty($batch_code)) {
                throw new Exception("Batch code is required.");
            }
            if (!in_array($status, ['active', 'harvested', 'closed'])) {
                throw new Exception("Invalid batch status selected.");
            }

            // Check duplicate code collision
            $chkStmt = $conn->prepare("SELECT batch_id FROM batches WHERE batch_code = ? AND batch_id != ?");
            $chkStmt->execute([$batch_code, $batch_id]);
            if ($chkStmt->fetch()) {
                throw new Exception("Batch code '{$batch_code}' is already used by another flock.");
            }

            $batchRepo = new \Poultry\Repositories\BatchRepository();
            $batchRepo->update($batch_id, [
                'batch_code'            => $batch_code,
                'flock_type'            => 'broiler',
                'arrival_date'          => $arrival_date,
                'production_date'       => $production_date,
                'initial_quantity'      => $initial_quantity,
                'status'                => $status,
                'breed'                 => $breed ?: null,
                'house_pen'             => $house_pen ?: null,
                'supplier'              => $supplier ?: null,
                'delivery_receipt_no'   => $delivery_receipt_no ?: null,
                'chick_unit_cost'       => $chick_unit_cost > 0 ? $chick_unit_cost : 38.00,
                'target_weight_kg'      => $target_weight_kg,
                'current_weight_kg'     => $current_weight_kg,
                'expected_harvest_date' => $expected_harvest_date,
                'notes'                 => $notes ?: null
            ]);

            // If harvested or closed, mark house as cleaning / available
            if (in_array($status, ['harvested', 'closed']) && !empty($house_pen)) {
                try {
                    $stmtH = $conn->prepare("UPDATE houses SET status = 'cleaning', current_batch_id = NULL, vacant_date = CURDATE() WHERE house_name = ?");
                    $stmtH->execute([$house_pen]);
                } catch (Exception $ex) {}
            }

            $save_msg = "Broiler Batch '{$batch_code}' updated successfully!";
        } catch (Exception $e) {
            $save_error = "Error updating batch: " . $e->getMessage();
        }
    }
}

// Handler for Recording Flock Weight Sample (Release Readiness Verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_batch_weight'])) {
    try {
        $batch_id = intval($_POST['batch_id'] ?? 0);
        $weight_kg = floatval($_POST['weight_kg'] ?? 0);
        $sample_date = !empty($_POST['sample_date']) ? $_POST['sample_date'] : date('Y-m-d');
        $birds_sampled = intval($_POST['birds_sampled'] ?? 50);
        $notes = trim($_POST['notes'] ?? '');

        if ($batch_id <= 0 || $weight_kg <= 0) {
            throw new Exception("Please enter a valid weight sample greater than 0 kg.");
        }

        $stmtW = $conn->prepare("UPDATE batches SET current_weight_kg = ?, updated_at = NOW() WHERE batch_id = ?");
        $stmtW->execute([$weight_kg, $batch_id]);

        // Weight sampling updates batch current weight directly and is not logged into farm operations (activities table)
        $save_msg = "Flock weight sample of {$weight_kg} kg recorded successfully! Release readiness updated.";
    } catch (Exception $e) {
        $save_error = "Failed to record weight sample: " . $e->getMessage();
    }
}

// Fetch active reservations available to be stocked into a new batch
$pendingReservations = [];
try {
    $pendingResStmt = $conn->query("
        SELECT r.reservation_id, r.reservation_number, r.customer_id, c.name as customer_name,
               r.house_id, h.house_name, r.quantity_birds, r.accepted_quantity, r.receipt_number,
               r.workflow_stage, r.rejection_reason,
               r.target_weight_per_bird, r.breed,
               r.unit_price, r.chick_cost, r.total_amount, r.advance_payment, r.pickup_date, r.notes, r.created_at
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        LEFT JOIN houses h ON r.house_id = h.house_id
        WHERE r.status = 'active'
          AND (r.batch_id IS NULL OR r.batch_id = 0)
          AND r.workflow_stage NOT IN ('growing', 'completed')
        ORDER BY 
            CASE 
                WHEN r.workflow_stage = 'receipt_confirmed' THEN 1 
                WHEN r.workflow_stage = 'receiving' THEN 2 
                WHEN r.workflow_stage = 'approved' THEN 3
                WHEN r.workflow_stage = 'pending_approval' THEN 4
                ELSE 5 
            END, 
            r.reservation_id DESC
    ");
    $pendingReservations = $pendingResStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Check if a specific reservation was requested in query string (e.g. ?reserve_id=62 or ?from_res=108 or ?house=House 1)
$selectedResId = intval($_GET['reserve_id'] ?? $_GET['from_res'] ?? 0);
$selectedRes = null;
$openRegisterModal = ($selectedResId > 0 || !empty($_GET['house']));

if ($selectedResId > 0) {
    foreach ($pendingReservations as $pr) {
        if ((int)$pr['reservation_id'] === $selectedResId) {
            $selectedRes = $pr;
            break;
        }
    }
    if (!$selectedRes) {
        try {
            $sStmt = $conn->prepare("
                SELECT r.reservation_id, r.reservation_number, r.customer_id, c.name as customer_name,
                       r.house_id, h.house_name, r.quantity_birds, r.accepted_quantity, r.receipt_number,
                       r.workflow_stage, r.rejection_reason, r.batch_id,
                       r.target_weight_per_bird, r.breed,
                       r.unit_price, r.chick_cost, r.total_amount, r.advance_payment, r.pickup_date, r.notes, r.created_at
                FROM reservations r
                JOIN customers c ON r.customer_id = c.customer_id
                LEFT JOIN houses h ON r.house_id = h.house_id
                WHERE r.reservation_id = ?
            ");
            $sStmt->execute([$selectedResId]);
            $candidateRes = $sStmt->fetch(PDO::FETCH_ASSOC);
            if ($candidateRes && (empty($candidateRes['batch_id']) && $candidateRes['workflow_stage'] !== 'growing')) {
                $selectedRes = $candidateRes;
            } elseif ($candidateRes) {
                $_SESSION['step5_done_notice'] = "Reservation #" . $candidateRes['reservation_number'] . " has already finished Step 5 (Batch code registered and flock is active in " . ($candidateRes['house_name'] ?? 'pen') . "). It cannot be registered again.";
            }
        } catch (Exception $e) {}
    }
} else {
    // If no specific reservation ID passed, check if a house parameter was passed (e.g. ?house=House 1)
    if (!empty($_GET['house'])) {
        $qHouse = trim($_GET['house']);
        foreach ($pendingReservations as $pr) {
            if (!empty($pr['house_name']) && strcasecmp(trim($pr['house_name']), $qHouse) === 0) {
                $selectedRes = $pr;
                $selectedResId = (int)$pr['reservation_id'];
                break;
            }
        }
    }
    // If still no reservation selected, automatically select the first/latest active reservation from integrator
    if (!$selectedRes && !empty($pendingReservations)) {
        $selectedRes = $pendingReservations[0];
        $selectedResId = (int)$selectedRes['reservation_id'];
    }
}

// Compute auto-fill values - Batch Code is explicitly kept BLANK as requested
$autoBatchCode = '';
$defaultQty = '';
$defaultBreed = 'Cobb 500';
$defaultSupplier = '';
$defaultHouse = '';
$defaultTargetWeight = '1.80';
$defaultChickCost = '38.00';
$defaultHarvestDate = date('Y-m-d', strtotime('+35 days'));
$defaultReceiptNo = '';
$defaultNotes = '';

if ($selectedRes) {
    $defaultQty = (!empty($selectedRes['accepted_quantity']) && (int)$selectedRes['accepted_quantity'] > 0) 
        ? (int)$selectedRes['accepted_quantity'] 
        : (int)$selectedRes['quantity_birds'];
    if (!empty($selectedRes['breed'])) $defaultBreed = $selectedRes['breed'];
    if (!empty($selectedRes['customer_name'])) $defaultSupplier = $selectedRes['customer_name'];
    if (!empty($selectedRes['house_name'])) $defaultHouse = $selectedRes['house_name'];
    if (!empty($selectedRes['target_weight_per_bird'])) $defaultTargetWeight = number_format((float)$selectedRes['target_weight_per_bird'], 2, '.', '');
    if (!empty($selectedRes['chick_cost'])) $defaultChickCost = number_format((float)$selectedRes['chick_cost'], 2, '.', '');
    if (!empty($selectedRes['pickup_date'])) $defaultHarvestDate = $selectedRes['pickup_date'];
    if (!empty($selectedRes['receipt_number'])) $defaultReceiptNo = $selectedRes['receipt_number'];

    $defaultNotes = "Stocked from Reservation #" . $selectedRes['reservation_number'] . " (" . $selectedRes['customer_name'] . ").";
    if ($defaultQty > 0) {
        $defaultNotes .= " Headcount: " . number_format($defaultQty) . " chicks.";
    }
    if (!empty($selectedRes['receipt_number'])) {
        $defaultNotes .= " DR: " . $selectedRes['receipt_number'] . ".";
    }
    if (!empty($selectedRes['notes'])) {
        $defaultNotes .= " Integrator Remarks: " . $selectedRes['notes'];
    }
}
include('header.php');
?>

<?php if (!empty($_SESSION['step5_notice'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4 border-success-subtle p-3" role="alert" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-success text-white rounded-circle p-2" style="width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fas fa-layer-group"></i>
            </span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-success mb-1">
                        <i class="fas fa-check-circle me-1"></i>Step 5: Broiler Batch Activation &amp; Placement
                    </h6>
                    <span class="badge bg-success text-white rounded-pill px-2">Integrator Input Auto-Filled</span>
                </div>
                <div class="text-dark small"><?php echo e($_SESSION['step5_notice']); ?></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['step5_notice']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['step5_done_notice'])): ?>
    <div class="alert alert-info alert-dismissible fade show rounded-4 shadow-sm mb-4 border-info-subtle p-3" role="alert" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary text-white rounded-circle p-2" style="width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fas fa-check-double"></i>
            </span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-primary mb-1">
                        <i class="fas fa-info-circle me-1"></i>Step 5 Already Completed
                    </h6>
                    <span class="badge bg-primary text-white rounded-pill px-2">Active Flock in Production</span>
                </div>
                <div class="text-dark small"><?php echo e($_SESSION['step5_done_notice']); ?></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['step5_done_notice']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4 border-success-subtle p-3" role="alert" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-success text-white rounded-circle p-2" style="width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fas fa-check"></i>
            </span>
            <div>
                <h6 class="fw-bold text-success mb-1">
                    <i class="fas fa-layer-group me-1"></i>Step 5: Active Batch Production
                </h6>
                <div class="text-dark small"><?php echo e($_SESSION['flash_message']); ?></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<?php if (isset($save_msg)): ?>
    <div class="alert alert-success shadow-sm mb-3 py-2"><i class="fas fa-check-circle me-2"></i><?php echo e($save_msg); ?></div>
<?php endif; ?>
<?php if (isset($save_error)): ?>
    <div class="alert alert-danger shadow-sm mb-3 py-2"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($save_error); ?></div>
<?php endif; ?>

<!-- Registered Batches Table -->
<div>
    <div class="card h-100 shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <div>
                    <h3 class="section-title mb-1"><i class="fas fa-layer-group me-2 text-primary"></i>Registered Flocks & Houses</h3>
                    <p class="text-muted small mb-0">Active and historical broiler flocks across all houses.</p>
                </div>
                <?php if (!$isAdmin): ?>
                    <span class="badge bg-light text-muted border px-3 py-2"><i class="fas fa-eye me-1 text-primary"></i>Operational Directory (Staff View)</span>
                <?php endif; ?>
            </div>

            <div class="table-responsive custom-scrollbar">
                <table id="batchesTable" class="table table-hover align-middle table-mobile-cards w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Batch Code</th>
                            <th>Dates</th>
                            <th>Chicks Stocked</th>
                            <th>Current Headcount</th>
                            <th>House / Breed</th>
                            <th>Target & Live Weight</th>
                            <th>Status</th>
                            <th class="text-center" style="min-width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $batchSql = "
                            SELECT 
                                b.*,
                                COALESCE(m.total_mortality, 0) as total_mortality,
                                COALESCE(m.total_financial_loss, 0) as total_financial_loss,
                                ROUND((COALESCE(m.total_mortality, 0) / NULLIF(b.initial_quantity, 0)) * 100, 2) as mortality_rate_pct,
                                ROUND(((b.initial_quantity - COALESCE(m.total_mortality, 0)) / NULLIF(b.initial_quantity, 0)) * 100, 2) as survival_rate_pct,
                                COALESCE(fc.total_feed, 0) as total_feed_used,
                                COALESCE(hr.health_count, 0) as health_events_count
                            FROM batches b
                            LEFT JOIN (
                                SELECT batch_id, ABS(SUM(quantity_change)) as total_mortality, COALESCE(SUM(financial_value), 0) as total_financial_loss
                                FROM mortality_records WHERE quantity_change < 0 GROUP BY batch_id
                            ) m ON b.batch_id = m.batch_id
                            LEFT JOIN (
                                SELECT batch_id, SUM(quantity_used) as total_feed
                                FROM feed_consumption GROUP BY batch_id
                            ) fc ON b.batch_id = fc.batch_id
                            LEFT JOIN (
                                SELECT batch_id, COUNT(*) as health_count
                                FROM health_records GROUP BY batch_id
                            ) hr ON b.batch_id = hr.batch_id
                            ORDER BY b.arrival_date DESC, b.batch_id DESC
                        ";
                        $stmt = $conn->query($batchSql);
                        $batches_found = false;
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $batches_found = true;
                            $isActive = ($row['status'] == 'active');
                            $isHarvested = ($row['status'] == 'harvested');
                            $statusBadge = $isActive ? 'bg-success text-white' : ($isHarvested ? 'bg-info text-white' : 'bg-secondary text-white');
                            $housePen = !empty($row['house_pen']) ? e($row['house_pen']) : '<span class="text-muted small">General</span>';
                            $breedText = !empty($row['breed']) ? e($row['breed']) : 'Broiler (Cobb 500)';
                            $stageInfo = get_broiler_stage_info($row['arrival_date'], $row['status'] ?? null, $row['expected_harvest_date'] ?? null);
                            $readiness = check_release_readiness($conn, $row, null, $row['expected_harvest_date'] ?? null);
                            $targetW = (float)($row['target_weight_kg'] ?? 1.80);
                            $curW = (float)($row['current_weight_kg'] ?? 0.00);
                            $row['stage_info'] = $stageInfo;
                            $row['readiness'] = $readiness;
                            $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            
                            echo "<tr>
                                <td data-label='Batch Code'>
                                    <span class='fw-bold text-primary fs-6'>" . e($row['batch_code']) . "</span>
                                    <div class='small text-dark mt-1'><strong>Age:</strong> {$stageInfo['age_days']} days (Wk {$stageInfo['age_weeks']})</div>
                                    <span class='badge {$stageInfo['badge_class']} px-2 py-1 mt-1'>{$stageInfo['status_label']}</span>";
                                    if (($stageInfo['is_ready_pickup'] || ($readiness && $readiness['ready'])) && $isActive) {
                                        echo "<div class='mt-1'><span class='badge bg-success text-white py-1 px-2'><i class='fas fa-truck-loading me-1'></i>Ready for Pickup</span></div>";
                                    }
                            echo "</td>
                                <td data-label='Dates'>
                                    <div class='small'><strong>Intake:</strong> " . date('M d, Y', strtotime($row['arrival_date'])) . "</div>
                                    " . (!empty($row['expected_harvest_date']) ? "<div class='small text-muted'><strong>Agreed Harvest:</strong> " . date('M d, Y', strtotime($row['expected_harvest_date'])) . "</div>" : "") . "
                                </td>
                                <td data-label='Chicks Stocked'>
                                    <span class='fw-bold fs-6'>" . number_format($row['initial_quantity']) . "</span>
                                    <small class='text-muted d-block'>Chicks Stocked</small>
                                </td>
                                <td data-label='Live Headcount'>
                                    <span class='fw-bold fs-6 text-success'>" . number_format($row['current_quantity']) . " heads</span>
                                    <small class='text-danger d-block'>(-" . number_format($row['total_mortality']) . " loss / ₱" . number_format($row['total_financial_loss'], 2) . ")</small>
                                    <div class='small text-muted'>" . $row['survival_rate_pct'] . "% survival</div>
                                </td>
                                <td data-label='House / Breed'>
                                    <div><strong>{$housePen}</strong></div>
                                    <small class='text-muted d-block'>{$breedText}</small>
                                    <small class='text-primary d-block'><i class='fas fa-utensils me-1'></i>Feed: {$stageInfo['feed_type']}</small>
                                    " . (!empty($row['supplier']) ? "<small class='text-secondary d-block'><i class='fas fa-building me-1'></i>" . e($row['supplier']) . "</small>" : "") . "
                                </td>
                                <td data-label='Target & Weight'>
                                    <div><strong class='text-primary font-monospace'>" . number_format($curW, 2) . " kg</strong> <span class='text-muted small'>/ " . number_format($targetW, 2) . " kg</span></div>
                                    <div class='mt-1'>";
                                    if ($readiness['ready']) {
                                        echo "<span class='badge bg-success-subtle text-success border border-success-subtle px-2 py-1'><i class='fas fa-check-circle me-1'></i>Weight Met</span>";
                                    } else {
                                        echo "<span class='badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1'><i class='fas fa-hourglass-half me-1'></i>Growing ({$readiness['percent']}%)</span>";
                                    }
                            echo "  </div>
                                </td>
                                <td data-label='Status'>
                                    <span class='badge rounded-pill {$statusBadge} px-3 py-1 text-uppercase'>
                                        " . ucfirst($row['status']) . "
                                    </span>
                                </td>
                                <td data-label='Actions' class='text-center'>
                                    <div class='btn-group btn-group-sm w-100 justify-content-center' role='group'>
                                        <button type='button' class='btn btn-outline-primary px-3' onclick='openViewBatchModal({$rowJson})' title='View Batch Details'>
                                            <i class='fas fa-eye me-1'></i><span class='d-md-none'>Details</span>
                                        </button>
                                        <button type='button' class='btn btn-outline-success px-3' onclick='openBatchWeighModal({$rowJson})' title='Record Weight Sample'>
                                            <i class='fas fa-weight-scale me-1'></i><span class='d-md-none'>Weigh</span>
                                        </button>";
                                        if ($isAdmin) {
                                            echo "<button type='button' class='btn btn-outline-secondary px-3' onclick='openEditBatchModal({$rowJson})' title='Edit Batch'>
                                                <i class='fas fa-edit me-1'></i><span class='d-md-none'>Edit</span>
                                            </button>";
                                        }
                            echo "      </div>
                                </td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$houseList = $conn->query("SELECT * FROM houses ORDER BY house_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($isAdmin): ?>
<!-- Sticky FAB: Register New Flock -->
<button type="button"
        class="btn btn-primary register-flock-fab shadow-lg rounded-pill fw-bold d-flex align-items-center gap-2"
        data-bs-toggle="modal"
        data-bs-target="#registerFlockModal"
        title="Register New Flock">
    <i class="fas fa-plus"></i>
    <span>Register New Flock</span>
</button>

<!-- Modal: Register New Flock -->
<div class="modal fade" id="registerFlockModal" tabindex="-1" aria-labelledby="registerFlockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-layer-group fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="registerFlockModalLabel">Register New Flock</h5>
                        <small class="text-muted">Broiler contract intake &amp; housing placement</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill d-none d-sm-inline-flex">
                        <i class="fas fa-check-circle me-1"></i>Broiler Intake
                    </span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="newBatchForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="flock_type" value="broiler">
                    <input type="hidden" name="linked_reservation_id" id="linked_reservation_id" value="<?php echo $selectedRes ? (int)$selectedRes['reservation_id'] : ''; ?>">
                    <input type="hidden" name="delivery_receipt_no" id="new_delivery_receipt_no" value="<?php echo e($defaultReceiptNo); ?>">

                    <?php if (!empty($pendingReservations) || $selectedRes): ?>
                    <div class="p-3 mb-3 rounded-3 border" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border-color: #bbf7d0 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-success mb-0">
                                <i class="fas fa-bookmark me-1"></i>Stock from Reservation (Auto-Fill)
                            </label>
                            <?php if (!empty($pendingReservations)): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size: 0.72rem;">
                                    <?php echo count($pendingReservations); ?> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                        <select id="reservationSelector" class="form-select form-select-sm border-success fw-semibold" onchange="applyReservation(this.value)">
                            <option value="">-- Choose a pending reservation to auto-fill (Optional) --</option>
                            <?php foreach ($pendingReservations as $pr):
                                $hNameTxt = !empty($pr['house_name']) ? " • " . e($pr['house_name']) : " • Pen unassigned";
                                $isCurSelected = ($selectedRes && $selectedRes['reservation_id'] == $pr['reservation_id']) ? "selected" : "";
                                $prBirds = (!empty($pr['accepted_quantity']) && (int)$pr['accepted_quantity'] > 0) ? (int)$pr['accepted_quantity'] : (int)$pr['quantity_birds'];
                            ?>
                                <option value="<?php echo $pr['reservation_id']; ?>" <?php echo $isCurSelected; ?>>
                                    <?php echo e($pr['reservation_number']); ?> — <?php echo e($pr['customer_name']); ?> (<?php echo number_format($prBirds); ?> birds<?php echo $hNameTxt; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="res_loaded_alert" class="<?php echo $selectedRes ? '' : 'd-none'; ?> mt-2 small text-success fw-semibold d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-circle-check me-1"></i><span id="res_loaded_text"><?php echo $selectedRes ? "Auto-filled from <strong>" . e($selectedRes['reservation_number']) . "</strong> (" . e($selectedRes['customer_name']) . ")" : ""; ?></span>
                            </span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none small" onclick="clearReservationAutoFill()"><i class="fas fa-times me-1"></i>Clear</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-dark"><i class="fas fa-id-card text-primary me-1"></i>Flock Identification</span>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size: 0.72rem;" onclick="generateBatchCode()" title="Auto-generate standard batch code">
                                <i class="fas fa-magic me-1"></i>Auto-Code
                            </button>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted mb-1">Batch Code <span class="text-danger">*</span></label>
                            <input type="text" name="batch_code" id="new_batch_code" class="form-control font-monospace fw-bold text-primary" placeholder="Click 'Auto-Code' button above to generate code" value="" required>
                            <div class="d-flex justify-content-between align-items-center mt-1 flex-wrap gap-1">
                                <small class="text-primary fw-semibold"><i class="fas fa-magic me-1"></i>Click <strong>Auto-Code</strong> to generate a code.</small>
                                <small class="text-muted font-monospace">Format: BR-YYYYMMDD-XXX</small>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Chicks Stocked <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="quantity" id="new_quantity" class="form-control fw-bold" min="1" placeholder="e.g. 5000" value="<?php echo $defaultQty ?: ''; ?>" oninput="checkPenCapacity()" required>
                                    <span class="input-group-text bg-white text-muted">birds</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Breed / Line</label>
                                <input type="text" name="breed" id="new_breed" class="form-control form-control-sm" placeholder="e.g. Cobb 500, Ross 308" value="<?php echo e($defaultBreed); ?>" list="breedOptions">
                                <datalist id="breedOptions">
                                    <option value="Cobb 500">
                                    <option value="Ross 308">
                                    <option value="Hubbard Classic">
                                </datalist>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="fw-bold small text-dark mb-2"><i class="fas fa-warehouse text-primary me-1"></i>Housing &amp; Integrator Partner</div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-muted mb-0">House / Pen Location <span class="text-danger">*</span></label>
                                <span class="badge bg-white text-muted border py-0 px-2 small" id="pen_capacity_badge">No pen selected</span>
                            </div>
                            <select name="house_pen" id="new_house_pen" class="form-select form-select-sm" onchange="checkPenCapacity()" required>
                                <option value="">-- Choose Poultry House --</option>
                                <?php foreach ($houseList as $hl):
                                    $hBadge = ($hl['status'] === 'available') ? '🟢 Available' : (($hl['status'] === 'occupied') ? '🔴 Occupied' : ($hl['status'] === 'reserved' ? '🔵 Reserved' : '🟡 Cleaning/Resting'));
                                    $isHSelected = (!empty($defaultHouse) && strcasecmp($defaultHouse, $hl['house_name']) === 0) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo e($hl['house_name']); ?>" <?php echo $isHSelected; ?> data-capacity="<?php echo (int)$hl['capacity']; ?>" data-status="<?php echo e($hl['status']); ?>">
                                        <?php echo e($hl['house_name']); ?> (Cap: <?php echo number_format($hl['capacity']); ?> birds - <?php echo $hBadge; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="pen_warning" class="text-danger small mt-1 d-none fw-semibold"><i class="fas fa-triangle-exclamation me-1"></i>Warning: Chicks count exceeds pen capacity!</div>
                        </div>
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">Integrator / Company <span class="text-danger">*</span></label>
                            <input type="text" name="supplier" id="new_supplier" class="form-control form-control-sm" placeholder="e.g. Bounty Fresh, San Miguel Foods" value="<?php echo e($defaultSupplier); ?>" list="integratorOptions" required>
                            <datalist id="integratorOptions">
                                <option value="Bounty Fresh">
                                <option value="San Miguel Foods Inc. (B-MEG)">
                                <option value="Universal Robina Corp">
                                <option value="Independent Commercial">
                            </datalist>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="fw-bold small text-dark mb-2"><i class="fas fa-calendar-check text-primary me-1"></i>Cycle Timeline &amp; Standards</div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Arrival Date <span class="text-danger">*</span></label>
                                <input type="date" name="arrival_date" id="new_arrival_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" onchange="onArrivalDateChange(this.value)" required>
                                <input type="hidden" name="production_date" id="new_production_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Expected Harvest</label>
                                <input type="date" name="expected_harvest_date" id="new_expected_harvest_date" class="form-control form-control-sm" value="<?php echo e($defaultHarvestDate); ?>">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Agreed Target Weight <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.05" name="target_weight_kg" id="new_target_weight_kg" class="form-control fw-bold font-monospace" value="<?php echo e($defaultTargetWeight); ?>" required>
                                    <span class="input-group-text bg-white text-muted">kg</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Chick Cost (₱) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white text-muted">₱</span>
                                    <input type="number" step="0.01" name="chick_unit_cost" id="new_chick_unit_cost" class="form-control font-monospace" value="<?php echo e($defaultChickCost); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Field / Intake Notes</label>
                        <textarea name="notes" id="new_notes" class="form-control form-control-sm" rows="2" placeholder="Flock uniformity, initial health, or intake remarks..."><?php echo e($defaultNotes); ?></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="save" class="btn btn-primary py-2 fw-bold shadow-sm">
                            <i class="fas fa-check-circle me-1"></i>Save Batch Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal 1: View Batch Details -->
<div class="modal fade" id="viewBatchModal" tabindex="-1" aria-labelledby="viewBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-layer-group fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="viewBatchModalLabel">Batch Details: <span id="v_batch_code" class="text-primary"></span></h5>
                        <small class="text-muted">Comprehensive flock performance, dates, headcount and housing</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Status and KPI Highlights -->
                <div class="row g-2 mb-4">
                    <div class="col-sm-3 col-6">
                        <div class="p-3 bg-light rounded-4 text-center border">
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Chicks Stocked</small>
                            <h5 class="mb-0 fw-bold text-dark" id="v_initial_qty">-</h5>
                            <small class="text-muted" style="font-size: 0.75rem;">Initial intake</small>
                        </div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <div class="p-3 bg-light rounded-4 text-center border border-success">
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Live Headcount</small>
                            <h5 class="mb-0 fw-bold text-success" id="v_current_qty">-</h5>
                            <small class="text-muted" style="font-size: 0.75rem;" id="v_survival_rate">-</small>
                        </div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <div class="p-3 bg-light rounded-4 text-center border border-danger">
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Mortality Loss</small>
                            <h5 class="mb-0 fw-bold text-danger" id="v_mortality">-</h5>
                            <small class="text-muted" style="font-size: 0.75rem;" id="v_mortality_rate">-</small>
                        </div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <div class="p-3 bg-light rounded-4 text-center border border-warning">
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Financial Loss</small>
                            <h5 class="mb-0 fw-bold text-danger" id="v_financial_loss">-</h5>
                            <small class="text-muted" style="font-size: 0.75rem;">Cost incurred</small>
                        </div>
                    </div>
                </div>

                <!-- Detail Grid -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <ul class="list-group list-group-flush border rounded-4 p-2">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Current Age & Stage:</span>
                                <strong id="v_stage_age" class="text-primary">-</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Feeding Transition:</span>
                                <strong id="v_feed_transition">-</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Target Body Weight:</span>
                                <strong id="v_target_weight">-</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Harvest Readiness:</span>
                                <span id="v_harvest_readiness" class="badge rounded-pill px-3 py-1">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Status:</span>
                                <span id="v_status_badge" class="badge rounded-pill px-3 py-1">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">House / Pen:</span>
                                <strong id="v_house_pen">-</strong>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-group list-group-flush border rounded-4 p-2">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Integrator / Partner:</span>
                                <strong id="v_supplier">-</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Production Scope:</span>
                                <strong class="text-success"><i class="fas fa-drumstick-bite me-1"></i>Commercial Broiler</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Chick Unit Cost:</span>
                                <strong id="v_chick_cost">-</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Arrival Date:</span>
                                <strong id="v_arrival_date">-</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Expected Harvest Date:</span>
                                <strong id="v_expected_harvest_date">-</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 border-0">
                                <span class="text-muted">Total Feed Consumed:</span>
                                <strong id="v_feed_used">-</strong>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-3">
                    <label class="fw-bold small text-muted text-uppercase mb-1">Notes / Remarks</label>
                    <div class="p-3 bg-light rounded-3 text-secondary" id="v_notes">None recorded.</div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Edit Batch (Admin Only) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="editBatchModal" tabindex="-1" aria-labelledby="editBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="batch_id" id="e_batch_id">
                <input type="hidden" name="edit_batch" value="1">

                <div class="modal-header bg-light border-0 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-warning-subtle text-warning rounded-circle">
                            <i class="fas fa-edit fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="editBatchModalLabel">Edit Batch: <span id="e_title_code" class="text-primary"></span></h5>
                            <small class="text-muted">Update broiler batch information, headcount, dates, and status</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Batch Code</label>
                            <input type="text" name="batch_code" id="e_batch_code" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" id="e_status" class="form-select" required>
                                <option value="active">Active (In Production)</option>
                                <option value="harvested">Harvested (Sold / Delivered)</option>
                                <option value="closed">Closed (Archived)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Arrival Date</label>
                            <input type="date" name="arrival_date" id="e_arrival_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Production Date</label>
                            <input type="date" name="production_date" id="e_production_date" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Initial Chicks Received</label>
                            <input type="number" name="initial_quantity" id="e_initial_quantity" class="form-control" min="1" required>
                            <small class="text-muted">Current headcount will automatically synchronize with mortality records.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Breed</label>
                            <input type="text" name="breed" id="e_breed" class="form-control" placeholder="e.g. Cobb 500, Ross 308">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">House / Pen Location</label>
                            <select name="house_pen" id="e_house_pen" class="form-select" required>
                                <option value="">-- Select House/Pen --</option>
                                <?php foreach($houseList as $hl): ?>
                                    <option value="<?php echo e($hl['house_name']); ?>"><?php echo e($hl['house_name']); ?> (Cap: <?php echo number_format($hl['capacity']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Integrator / Company</label>
                            <input type="text" name="supplier" id="e_supplier" class="form-control" placeholder="Supplier name">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Chick Unit Cost (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₱</span>
                                <input type="number" step="0.01" name="chick_unit_cost" id="e_chick_unit_cost" class="form-control" value="38.00">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Agreed Target Weight (kg)</label>
                            <div class="input-group">
                                <input type="number" step="0.05" name="target_weight_kg" id="e_target_weight_kg" class="form-control" value="1.80" required>
                                <span class="input-group-text bg-light text-muted">kg</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">Latest Sample Weight (kg)</label>
                                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" style="font-size: 0.72rem;" onclick="autoFillEditBatchWeight()" title="Auto-fill Cobb 500 estimated weight for flock age">
                                    <i class="fas fa-wand-magic-sparkles text-warning me-1"></i>Auto-Fill by Age
                                </button>
                            </div>
                            <div class="input-group">
                                <input type="number" step="0.01" name="current_weight_kg" id="e_current_weight_kg" class="form-control" value="0.00">
                                <span class="input-group-text bg-light text-muted">kg</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Expected Harvest Date</label>
                            <input type="date" name="expected_harvest_date" id="e_expected_harvest_date" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Notes / Remarks</label>
                            <textarea name="notes" id="e_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal 3: Record Flock Weight Sample (Broiler Release Gatekeeping) -->
<div class="modal fade" id="batchWeighModal" tabindex="-1" aria-labelledby="batchWeighModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="record_batch_weight" value="1">
                <input type="hidden" name="batch_id" id="bw_batch_id">

                <div class="modal-header bg-success-subtle border-0 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-success text-white rounded-circle">
                            <i class="fas fa-weight-scale fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-success" id="batchWeighModalLabel">Record Weight Sample</h5>
                            <small class="text-muted">Verify if flock is ready for release back to company</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Batch Code:</span>
                            <strong id="bw_batch_code" class="text-primary font-monospace">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">House / Location:</span>
                            <strong id="bw_house_pen">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Agreed Target Contract Weight:</span>
                            <span class="badge bg-primary rounded-pill font-monospace" id="bw_target_weight">1.80 kg</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Previous Sample Weight:</span>
                            <strong id="bw_prev_weight" class="text-dark font-monospace">0.00 kg</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold mb-0">Average Sample Body Weight (ABW) *</label>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 shadow-sm" id="btn_autofill_batch_weight" onclick="autoFillBatchWeighModal()" title="Auto-fill standard Cobb 500 growth weight based on flock age">
                                <i class="fas fa-wand-magic-sparkles text-warning me-1"></i>Auto-Fill from Age (<span id="bw_est_label">1.80 kg</span>)
                            </button>
                        </div>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0.05" max="10.00" name="weight_kg" id="bw_weight_kg" class="form-control form-control-lg fw-bold text-success text-center" placeholder="e.g. 1.85" inputmode="decimal" required>
                            <span class="input-group-text bg-success text-white fw-bold">kg / bird</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Take scale weight or click "Auto-Fill" for standard.</small>
                            <small class="text-primary fw-semibold" id="bw_age_display"><i class="fas fa-calendar-day me-1"></i>Flock Age: -</small>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Sampling Date</label>
                            <input type="date" name="sample_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Birds Sampled</label>
                            <input type="number" name="birds_sampled" id="bw_birds_sampled" class="form-control form-control-sm" value="50" min="1">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">Field / Weighing Notes</label>
                        <input type="text" name="notes" id="bw_notes" class="form-control form-control-sm" placeholder="e.g. Uniformity looks high, birds active and healthy">
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">
                        <i class="fas fa-check-circle me-1"></i>Save Weight Sample
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
.register-flock-fab {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    z-index: 1040;
    padding: 0.85rem 1.35rem;
    font-size: 0.95rem;
    box-shadow: 0 10px 28px rgba(20, 83, 45, 0.35) !important;
}
.register-flock-fab:hover {
    transform: translateY(-2px);
}
#batchesTable_wrapper .dataTables_filter input,
#batchesTable_wrapper .dataTables_length select {
    border-radius: 0.5rem;
    border: 1px solid #dee2e6;
    padding: 0.35rem 0.65rem;
}
@media (max-width: 576px) {
    .register-flock-fab span { display: none; }
    .register-flock-fab {
        width: 3.25rem;
        height: 3.25rem;
        padding: 0;
        border-radius: 50% !important;
        justify-content: center;
    }
}
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
function openViewBatchModal(batch) {
    document.getElementById('v_batch_code').innerText = batch.batch_code || '';
    document.getElementById('v_initial_qty').innerText = Number(batch.initial_quantity || 0).toLocaleString();
    document.getElementById('v_current_qty').innerText = Number(batch.current_quantity || 0).toLocaleString();
    document.getElementById('v_survival_rate').innerText = (batch.survival_rate_pct || 0) + '% Survival Rate';
    document.getElementById('v_mortality').innerText = Number(batch.total_mortality || 0).toLocaleString() + ' bird(s)';
    document.getElementById('v_mortality_rate').innerText = (batch.mortality_rate_pct || 0) + '% Mortality Rate';
    document.getElementById('v_financial_loss').innerText = '₱' + Number(batch.total_financial_loss || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

    const statusBadge = document.getElementById('v_status_badge');
    const status = (batch.status || 'active').toUpperCase();
    statusBadge.innerText = status;
    statusBadge.className = 'badge rounded-pill px-3 py-1 ' + (status === 'ACTIVE' ? 'bg-success' : (status === 'HARVESTED' ? 'bg-info' : 'bg-secondary'));

    // Growth Stage & Transition Info
    if (batch.stage_info) {
        document.getElementById('v_stage_age').innerText = batch.stage_info.age_days + ' days (Wk ' + batch.stage_info.age_weeks + ') — ' + batch.stage_info.stage;
        document.getElementById('v_feed_transition').innerText = batch.stage_info.feed_type;
        const curW = Number(batch.current_weight_kg || 0).toFixed(2);
        const tgtW = Number(batch.target_weight_kg || 1.80).toFixed(2);
        document.getElementById('v_target_weight').innerText = curW + ' kg (Target: ' + tgtW + ' kg)';
        
        const rBadge = document.getElementById('v_harvest_readiness');
        if (Number(curW) >= Number(tgtW) && batch.stage_info.age_days >= 28) {
            rBadge.className = 'badge bg-success rounded-pill px-3 py-1';
            rBadge.innerText = '🟢 Ready for Release to Company';
        } else {
            rBadge.className = 'badge bg-warning text-dark rounded-pill px-3 py-1';
            rBadge.innerText = '⏳ Below Target (' + Math.round((Number(curW)/Number(tgtW))*100) + '%)';
        }
    }

    document.getElementById('v_house_pen').innerText = batch.house_pen || 'General Pen';
    document.getElementById('v_supplier').innerText = batch.supplier || 'Not specified';
    if (document.getElementById('v_delivery_receipt')) document.getElementById('v_delivery_receipt').innerText = batch.delivery_receipt_no || 'None';
    document.getElementById('v_chick_cost').innerText = '₱' + Number(batch.chick_unit_cost || 38.00).toFixed(2);
    document.getElementById('v_arrival_date').innerText = batch.arrival_date || '-';
    document.getElementById('v_expected_harvest_date').innerText = batch.expected_harvest_date || 'Not set';
    document.getElementById('v_feed_used').innerText = Number(batch.total_feed_used || 0).toLocaleString() + ' kg';
    document.getElementById('v_notes').innerText = batch.notes || 'None recorded.';

    const modal = new bootstrap.Modal(document.getElementById('viewBatchModal'));
    modal.show();
}

// Data store for pending reservations
const pendingReservationsData = <?php echo json_encode($pendingReservations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function highlightField(el) {
    if (!el) return;
    el.classList.add('bg-success-subtle');
    setTimeout(() => el.classList.remove('bg-success-subtle'), 1000);
}

function applyReservation(resId) {
    if (!resId) {
        clearReservationAutoFill();
        return;
    }
    const res = pendingReservationsData.find(r => String(r.reservation_id) === String(resId));
    if (!res) return;

    // 1. Linked reservation hidden field
    const linkedInput = document.getElementById('linked_reservation_id');
    if (linkedInput) linkedInput.value = res.reservation_id;

    // 2. Headcount (Chicks Stocked)
    const qtyInput = document.getElementById('new_quantity');
    if (qtyInput) {
        const count = (res.accepted_quantity && parseInt(res.accepted_quantity) > 0) ? parseInt(res.accepted_quantity) : parseInt(res.quantity_birds || 0);
        qtyInput.value = count > 0 ? count : '';
        highlightField(qtyInput);
    }

    // 2b. Chick Breed / Line
    const breedInput = document.getElementById('new_breed');
    if (breedInput) {
        breedInput.value = res.breed || 'Cobb 500';
        highlightField(breedInput);
    }

    // 2c. Keep Batch Code blank so Admin can click Auto-Code
    const batchCodeInput = document.getElementById('new_batch_code');
    if (batchCodeInput) {
        batchCodeInput.value = '';
        batchCodeInput.placeholder = "Click 'Auto-Code' button above to generate code";
    }

    // 3. House / Pen Selection
    const penSelect = document.getElementById('new_house_pen');
    if (penSelect && res.house_name) {
        for (let i = 0; i < penSelect.options.length; i++) {
            if (penSelect.options[i].value.toLowerCase() === res.house_name.toLowerCase()) {
                penSelect.selectedIndex = i;
                highlightField(penSelect);
                break;
            }
        }
        checkPenCapacity();
    }

    // 4. Integrator / Partner Company
    const supplierInput = document.getElementById('new_supplier');
    if (supplierInput && res.customer_name) {
        supplierInput.value = res.customer_name;
        highlightField(supplierInput);
    }

    // 5. Target Weight Standard
    const targetWInput = document.getElementById('new_target_weight_kg');
    if (targetWInput && res.target_weight_per_bird) {
        targetWInput.value = Number(res.target_weight_per_bird).toFixed(2);
        highlightField(targetWInput);
    }

    // 5b. Chick Unit Cost (DOC rate) from reservation
    const chickCostInput = document.getElementById('new_chick_unit_cost');
    if (chickCostInput && res.chick_cost) {
        chickCostInput.value = Number(res.chick_cost).toFixed(2);
        highlightField(chickCostInput);
    }

    // 5c. Delivery Receipt No from intake
    const drInput = document.getElementById('new_delivery_receipt_no');
    if (drInput && res.receipt_number) {
        drInput.value = res.receipt_number;
    }

    // 6. Expected Harvest Date
    const harvInput = document.getElementById('new_expected_harvest_date');
    if (harvInput && res.pickup_date) {
        harvInput.value = res.pickup_date;
        highlightField(harvInput);
    }

    // 7. Notes / Remarks
    const notesInput = document.getElementById('new_notes');
    if (notesInput) {
        let noteTxt = `Intake verified from Reservation #${res.reservation_number} (${res.customer_name}).`;
        if (res.accepted_quantity && parseInt(res.accepted_quantity) > 0) {
            noteTxt += ` Accepted: ${Number(res.accepted_quantity).toLocaleString()} chicks.`;
        }
        if (res.receipt_number) {
            noteTxt += ` DR: ${res.receipt_number}.`;
        }
        if (res.notes && res.notes.trim()) {
            noteTxt += ` Integrator Remarks: ${res.notes.trim()}`;
        }
        notesInput.value = noteTxt;
        highlightField(notesInput);
    }

    // 8. Update info alert badge
    const alertBox = document.getElementById('res_loaded_alert');
    const alertTxt = document.getElementById('res_loaded_text');
    if (alertBox && alertTxt) {
        alertTxt.innerHTML = `<i class="fas fa-circle-check me-1"></i>Auto-filled from <strong>${escapeHtml(res.reservation_number)}</strong> (${escapeHtml(res.customer_name)})`;
        alertBox.classList.remove('d-none');
    }
}

function clearReservationAutoFill() {
    const sel = document.getElementById('reservationSelector');
    if (sel) sel.value = '';
    const linkedInput = document.getElementById('linked_reservation_id');
    if (linkedInput) linkedInput.value = '';
    const alertBox = document.getElementById('res_loaded_alert');
    if (alertBox) alertBox.classList.add('d-none');
}

function generateBatchCode() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const rand = Math.floor(100 + Math.random() * 900);
    const code = `BR-${year}${month}${day}-${rand}`;
    const input = document.getElementById('new_batch_code');
    if (input) {
        input.value = code;
        input.classList.add('bg-warning-subtle');
        setTimeout(() => input.classList.remove('bg-warning-subtle'), 800);
    }
}

function onArrivalDateChange(val) {
    if (!val) return;
    const prodInput = document.getElementById('new_production_date');
    if (prodInput) prodInput.value = val;
    
    const d = new Date(val);
    d.setDate(d.getDate() + 35);
    const harvYear = d.getFullYear();
    const harvMonth = String(d.getMonth() + 1).padStart(2, '0');
    const harvDay = String(d.getDate()).padStart(2, '0');
    const harvInput = document.getElementById('new_expected_harvest_date');
    if (harvInput) {
        harvInput.value = `${harvYear}-${harvMonth}-${harvDay}`;
    }
}

function checkPenCapacity() {
    const penSelect = document.getElementById('new_house_pen');
    const qtyInput = document.getElementById('new_quantity');
    const badge = document.getElementById('pen_capacity_badge');
    const warning = document.getElementById('pen_warning');
    if (!penSelect || !badge) return;

    const opt = penSelect.options[penSelect.selectedIndex];
    if (!opt || !opt.value) {
        badge.innerText = 'No pen selected';
        badge.className = 'badge bg-white text-muted border py-0 px-2 small';
        if (warning) warning.classList.add('d-none');
        return;
    }

    const cap = parseInt(opt.getAttribute('data-capacity') || '0');
    const qty = parseInt(qtyInput ? qtyInput.value : '0') || 0;

    badge.innerText = `Cap: ${cap.toLocaleString()} birds`;
    badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle py-0 px-2 small';

    if (warning) {
        if (qty > 0 && cap > 0 && qty > cap) {
            warning.classList.remove('d-none');
            warning.innerHTML = `<i class="fas fa-triangle-exclamation me-1"></i>Warning: Chicks (${qty.toLocaleString()}) exceed pen capacity (${cap.toLocaleString()})!`;
        } else {
            warning.classList.add('d-none');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#batchesTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: {
                search: 'Search flocks:',
                lengthMenu: 'Show _MENU_ flocks',
                info: 'Showing _START_ to _END_ of _TOTAL_ flocks',
                infoEmpty: 'No flocks registered',
                zeroRecords: 'No matching flock records found',
                emptyTable: 'No batch records registered yet.'
            }
        });
    }

    <?php if ($isAdmin && $selectedRes): ?>
    applyReservation(<?php echo (int)$selectedRes['reservation_id']; ?>);
    <?php elseif ($isAdmin): ?>
    checkPenCapacity();
    <?php endif; ?>

    <?php if ($isAdmin && !empty($openRegisterModal)): ?>
    var regModalEl = document.getElementById('registerFlockModal');
    if (regModalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(regModalEl).show();
    }
    <?php endif; ?>
});

let currentEstimatedWeight = 1.80;
let currentFlockAgeDays = 0;
let editBatchArrivalDate = '';

function estimateBroilerWeightByAge(ageDays) {
    let days = Math.max(1, parseInt(ageDays) || 1);
    let est = 0.04;
    if (days <= 7) {
        est = 0.04 + (days * (0.18 - 0.04) / 7);
    } else if (days <= 14) {
        est = 0.18 + ((days - 7) * (0.45 - 0.18) / 7);
    } else if (days <= 21) {
        est = 0.45 + ((days - 14) * (0.92 - 0.45) / 7);
    } else if (days <= 28) {
        est = 0.92 + ((days - 21) * (1.55 - 0.92) / 7);
    } else if (days <= 32) {
        est = 1.55 + ((days - 28) * (1.85 - 1.55) / 4);
    } else {
        est = 1.85 + ((days - 32) * 0.06);
    }
    return Math.round(est * 100) / 100;
}

function autoFillBatchWeighModal() {
    const input = document.getElementById('bw_weight_kg');
    if (!input) return;
    input.value = currentEstimatedWeight.toFixed(2);
    input.classList.add('bg-warning-subtle');
    setTimeout(() => input.classList.remove('bg-warning-subtle'), 800);

    const notes = document.getElementById('bw_notes');
    if (notes && !notes.value) {
        notes.value = `Auto-estimated from Cobb 500 standard growth curve at Day ${currentFlockAgeDays}`;
    }
}

function autoFillEditBatchWeight() {
    const arrDateStr = document.getElementById('e_arrival_date').value || editBatchArrivalDate;
    let days = 1;
    if (arrDateStr) {
        const arr = new Date(arrDateStr);
        const now = new Date();
        const diff = Math.max(0, now - arr);
        days = Math.max(1, Math.floor(diff / (1000 * 60 * 60 * 24)));
    }
    const est = estimateBroilerWeightByAge(days);
    const input = document.getElementById('e_current_weight_kg');
    if (input) {
        input.value = est.toFixed(2);
        input.classList.add('bg-warning-subtle');
        setTimeout(() => input.classList.remove('bg-warning-subtle'), 800);
    }
}

function openEditBatchModal(batch) {
    editBatchArrivalDate = batch.arrival_date || '';
    document.getElementById('e_batch_id').value = batch.batch_id || '';
    document.getElementById('e_title_code').innerText = batch.batch_code || '';
    document.getElementById('e_batch_code').value = batch.batch_code || '';
    document.getElementById('e_status').value = batch.status || 'active';
    document.getElementById('e_arrival_date').value = batch.arrival_date || '';
    document.getElementById('e_production_date').value = batch.production_date || batch.arrival_date || '';
    document.getElementById('e_initial_quantity').value = batch.initial_quantity || '';
    document.getElementById('e_breed').value = batch.breed || '';
    document.getElementById('e_house_pen').value = batch.house_pen || '';
    document.getElementById('e_supplier').value = batch.supplier || '';
    if (document.getElementById('e_delivery_receipt_no')) document.getElementById('e_delivery_receipt_no').value = batch.delivery_receipt_no || '';
    document.getElementById('e_chick_unit_cost').value = batch.chick_unit_cost || '38.00';
    document.getElementById('e_target_weight_kg').value = batch.target_weight_kg || '1.80';
    document.getElementById('e_current_weight_kg').value = batch.current_weight_kg || '0.00';
    document.getElementById('e_expected_harvest_date').value = batch.expected_harvest_date || '';
    document.getElementById('e_notes').value = batch.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('editBatchModal'));
    modal.show();
}

function openBatchWeighModal(batch) {
    document.getElementById('bw_batch_id').value = batch.batch_id || '';
    document.getElementById('bw_batch_code').innerText = batch.batch_code || '-';
    document.getElementById('bw_house_pen').innerText = batch.house_pen || 'Unassigned';
    const targetW = Number(batch.target_weight_kg || 1.80).toFixed(2);
    const curW = Number(batch.current_weight_kg || 0.00).toFixed(2);
    document.getElementById('bw_target_weight').innerText = targetW + ' kg';
    document.getElementById('bw_prev_weight').innerText = curW + ' kg';
    document.getElementById('bw_weight_kg').value = curW > 0 ? curW : '';

    const birdsInput = document.getElementById('bw_birds_sampled');
    if (birdsInput) {
        const aliveCount = parseInt(batch.current_quantity || batch.initial_quantity || 0);
        birdsInput.value = aliveCount > 0 ? aliveCount : 50;
    }

    // Calculate flock age and standard estimated weight
    let ageDays = 1;
    if (batch.stage_info && batch.stage_info.age_days !== undefined) {
        ageDays = parseInt(batch.stage_info.age_days);
    } else if (batch.arrival_date) {
        const arrDate = new Date(batch.arrival_date);
        const now = new Date();
        const diffTime = Math.max(0, now - arrDate);
        ageDays = Math.max(1, Math.floor(diffTime / (1000 * 60 * 60 * 24)));
    }
    currentFlockAgeDays = ageDays;
    currentEstimatedWeight = estimateBroilerWeightByAge(ageDays);

    const estLabel = document.getElementById('bw_est_label');
    if (estLabel) {
        estLabel.innerText = currentEstimatedWeight.toFixed(2) + ' kg';
    }
    const ageDisplay = document.getElementById('bw_age_display');
    if (ageDisplay) {
        ageDisplay.innerHTML = `<i class="fas fa-calendar-day me-1"></i>Flock Age: <strong>Day ${ageDays}</strong>`;
    }

    const modal = new bootstrap.Modal(document.getElementById('batchWeighModal'));
    modal.show();
}
</script>

<?php include('footer.php'); ?>