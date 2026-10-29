<?php 
include('database.php'); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('auth.php'); 
include_once('csrf.php'); 

if (!in_array($_SESSION['role'] ?? '', ['admin', 'staff', 'worker', 'integrator'])) {
    include('header.php');
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Insufficient permissions.</div></div>";
    include('footer.php');
    exit();
}

$resService = new \Poultry\Services\ReservationService();
$message = '';
$error = '';

if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$isIntegrator = (strtolower($user_role) === 'integrator');

// Resolve authenticated integrator's partner company for strict data isolation
$integratorCustomerId = 0;
$integratorCustomer = null;
if ($isIntegrator) {
    if (!empty($_SESSION['customer_id'])) {
        $integratorCustomerId = (int)$_SESSION['customer_id'];
    } else {
        $stmtU = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
        $stmtU->execute([$user_id]);
        $integratorCustomerId = (int)$stmtU->fetchColumn();
        
        if ($integratorCustomerId <= 0) {
            $stmtC = $conn->prepare("SELECT customer_id FROM reservations WHERE created_by = ? ORDER BY reservation_id DESC LIMIT 1");
            $stmtC->execute([$user_id]);
            $integratorCustomerId = (int)$stmtC->fetchColumn();
        }
        
        if ($integratorCustomerId <= 0) {
            // Strictly resolve matching customer by username / full_name, never default to competitor company
            $uName = trim($_SESSION['username'] ?? '');
            $fName = trim($_SESSION['full_name'] ?? '');
            if (!empty($uName) || !empty($fName)) {
                $stmtMatch = $conn->prepare("SELECT customer_id FROM customers WHERE name LIKE ? OR name LIKE ? LIMIT 1");
                $stmtMatch->execute(['%' . $uName . '%', '%' . $fName . '%']);
                $matchedId = (int)$stmtMatch->fetchColumn();
                if ($matchedId > 0) {
                    $integratorCustomerId = $matchedId;
                    try {
                        $conn->prepare("UPDATE users SET customer_id = ? WHERE user_id = ?")->execute([$matchedId, $user_id]);
                    } catch (Exception $ign) {}
                }
            }
        }
        
        $_SESSION['customer_id'] = $integratorCustomerId;
    }

    if ($integratorCustomerId > 0) {
        $stmtCust = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmtCust->execute([$integratorCustomerId]);
        $integratorCustomer = $stmtCust->fetch(PDO::FETCH_ASSOC);
    }
}

// 1. Create New Broiler Reservation (Step 1: Exclusively submitted by contract Integrator)
if (isset($_POST['create_broiler_reservation'])) {
    if (!$isIntegrator) {
        $error = "Access Denied: Step 1 (Reservation Submission) must be submitted by the contract Integrator partner. Farm Admin and Staff view the reservation once submitted and proceed with Step 2 Approval.";
    } else {
        try {
            $batch_id = !empty($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
            $house_id = !empty($_POST['house_id']) ? intval($_POST['house_id']) : null;
            if ($batch_id <= 0 && empty($house_id)) {
                throw new Exception("Please select a valid broiler batch or reserve a vacant house.");
            }

            if ($house_id > 0) {
                // Verify house exists and calculate occupied/reserved birds
                $chkH = $conn->prepare("SELECT house_id, house_name, capacity, status, current_batch_id FROM houses WHERE house_id = ?");
                $chkH->execute([$house_id]);
                $hInfo = $chkH->fetch(PDO::FETCH_ASSOC);
                if (!$hInfo) {
                    throw new Exception("Selected poultry house/pen does not exist.");
                }

                if ($hInfo['status'] === 'occupied' || !empty($hInfo['current_batch_id'])) {
                    throw new Exception("House " . htmlspecialchars($hInfo['house_name']) . " is currently occupied with an active growing batch (Step 5). Reservation workflow is locked for this pen until the flock is harvested and sanitized.");
                }

                // Strict 1 Integrator per House Exclusivity Rule
                $chkActiveRes = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE house_id = ? AND status = 'active'");
                $chkActiveRes->execute([$house_id]);
                $hasActiveRes = ((int)$chkActiveRes->fetchColumn() > 0);

                if ($hasActiveRes || $hInfo['status'] === 'reserved') {
                    throw new Exception("House " . htmlspecialchars($hInfo['house_name']) . " is already reserved. Each house is strictly dedicated to 1 integrator partner only, even if birds do not reach 5,000 capacity.");
                }

                $houseCap = (int)($hInfo['capacity'] > 0 ? $hInfo['capacity'] : 5000);
                $reqBirds = intval($_POST['quantity_birds'] ?? 0);
                if ($reqBirds <= 0) {
                    throw new Exception("Please specify a valid chick quantity to reserve.");
                }
                if ($reqBirds > $houseCap) {
                    throw new Exception("Exceeds maximum house capacity: " . htmlspecialchars($hInfo['house_name']) . " capacity is " . number_format($houseCap) . " birds (requested " . number_format($reqBirds) . " birds).");
                }
            }

            if ($batch_id > 0) {
                // Verify batch exists in database
                $chkBatch = $conn->prepare("SELECT batch_id FROM batches WHERE batch_id = ?");
                $chkBatch->execute([$batch_id]);
                if (!$chkBatch->fetchColumn()) {
                    throw new Exception("Selected broiler batch does not exist.");
                }
            } else {
                if ($house_id > 0) {
                    $stmtHBatch = $conn->prepare("SELECT current_batch_id FROM houses WHERE house_id = ? AND status = 'occupied'");
                    $stmtHBatch->execute([$house_id]);
                    $hCurBatch = (int)$stmtHBatch->fetchColumn();
                    $batch_id = $hCurBatch > 0 ? $hCurBatch : null;
                } else {
                    $batch_id = null;
                }
            }

            $targetCustomerId = intval($_POST['customer_id'] ?? 0);
            if ($isIntegrator) {
                if ($integratorCustomerId > 0) {
                    $targetCustomerId = $integratorCustomerId;
                } else {
                    $stmtU = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
                    $stmtU->execute([$user_id]);
                    $targetCustomerId = (int)$stmtU->fetchColumn();
                }
            }

            // Rapid duplicate submission check (within 30 seconds)
            $chkRapidDup = $conn->prepare("
                SELECT reservation_id, reservation_number 
                FROM reservations 
                WHERE customer_id = ? 
                  AND (house_id = ? OR (? IS NULL AND house_id IS NULL))
                  AND quantity_birds = ? 
                  AND created_at >= (NOW() - INTERVAL 30 SECOND)
                LIMIT 1
            ");
            $chkRapidDup->execute([$targetCustomerId, $house_id, $house_id, intval($_POST['quantity_birds'] ?? 0)]);
            $rapidDup = $chkRapidDup->fetch(PDO::FETCH_ASSOC);
            if ($rapidDup) {
                throw new Exception("Duplicate reservation submission detected. Reservation #" . htmlspecialchars($rapidDup['reservation_number']) . " was already created a few seconds ago.");
            }

            $targetWeight = (isset($_POST['target_weight_per_bird']) && $_POST['target_weight_per_bird'] !== '') ? floatval($_POST['target_weight_per_bird']) : 2.20;
            if ($targetWeight < 2.20) {
                throw new Exception("Strict Standard: Agreed target weight cannot be below 2.20 kg (received " . number_format($targetWeight, 2) . " kg).");
            }
            $birds = intval($_POST['quantity_birds'] ?? 0);
            if ($birds <= 0) {
                throw new Exception("Reserved birds quantity must be greater than 0.");
            }
            if ($birds > 5000) {
                throw new Exception("Reserved birds cannot exceed 5,000 birds (house capacity limit).");
            }
            $arrivalDate = trim($_POST['arrival_date'] ?? date('Y-m-d'));
            if ($arrivalDate < date('Y-m-d')) {
                throw new Exception("Chicks arrival date cannot be in the past (" . htmlspecialchars($arrivalDate) . "). Date must be today or in the future.");
            }
            $pickupDate = trim($_POST['pickup_date'] ?? '');
            if (empty($pickupDate) || $pickupDate < date('Y-m-d')) {
                throw new Exception("Expected pickup date cannot be in the past (" . htmlspecialchars($pickupDate) . "). Date must be today or in the future.");
            }
            $diffDays = (strtotime($pickupDate) - strtotime($arrivalDate)) / 86400;
            if ($diffDays < 35) {
                throw new Exception("Strict Standard: Expected harvest date must be at least 35 days from arrival date (" . htmlspecialchars($arrivalDate) . " to " . htmlspecialchars($pickupDate) . " is only " . round($diffDays) . " days). Harvest cycle cannot be below 35 days.");
            }

            $data = [
                'customer_id' => $targetCustomerId,
                'batch_id' => $batch_id,
                'house_id' => $house_id,
                'quantity_birds' => $birds,
                'weight_kg' => (isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '') ? floatval($_POST['weight_kg']) : null,
                'target_weight_per_bird' => $targetWeight,
                'breed' => trim($_POST['breed'] ?? 'Cobb 500'),
                'arrival_date' => $arrivalDate,
                'chick_cost' => (isset($_POST['chick_cost']) && $_POST['chick_cost'] !== '') ? floatval($_POST['chick_cost']) : 38.00,
                'unit_price' => (isset($_POST['unit_price']) && $_POST['unit_price'] !== '') ? floatval($_POST['unit_price']) : null,
                'total_amount' => floatval($_POST['total_amount'] ?? 0),
                'advance_payment' => floatval($_POST['advance_payment'] ?? 0),
                'pickup_date' => $pickupDate,
                'notes' => trim($_POST['notes'] ?? '')
            ];

            $newRes = $resService->createBroilerReservation($data, $user_id);
            if ($house_id > 0) {
                $conn->prepare("UPDATE houses SET status = 'reserved' WHERE house_id = ?")->execute([$house_id]);
            }

            // Notify Farm Admin of incoming reservation
            try {
                $hNameQuery = $conn->prepare("SELECT house_name FROM houses WHERE house_id = ?");
                $hNameQuery->execute([$house_id]);
                $hNameVal = $hNameQuery->fetchColumn() ?: "House";
                
                $cNameQuery = $conn->prepare("SELECT name FROM customers WHERE customer_id = ?");
                $cNameQuery->execute([$targetCustomerId]);
                $cNameVal = $cNameQuery->fetchColumn() ?: "Integrator Partner";

                $adminStmt = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
                $adminUserIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                if (empty($adminUserIds)) {
                    $adminUserIds = [1];
                }

                $notifMsg = "New Reservation Received: {$cNameVal} reserved " . number_format($birds) . " chicks in {$hNameVal} (Ref: #" . $newRes['reservation_number'] . ").";
                $insNotif = $conn->prepare("
                    INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                    VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())
                ");
                foreach ($adminUserIds as $admId) {
                    $insNotif->execute([(int)$admId, $notifMsg, (int)$newRes['reservation_id']]);
                }

                // Notify Integrator Partner of submitted reservation
                $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
                $stmtIntUser->execute([(int)$targetCustomerId, (int)$user_id]);
                $intUserId = $stmtIntUser->fetchColumn();
                if ($intUserId) {
                    $intResNotif = "Reservation Submitted: Your reservation #" . $newRes['reservation_number'] . " for " . number_format($birds) . " birds in {$hNameVal} has been recorded successfully. Status: Pending batch allocation.";
                    $insNotif->execute([(int)$intUserId, $intResNotif, (int)$newRes['reservation_id']]);
                }
            } catch (\Exception $exN) {}

            $_SESSION['flash_message'] = "Broiler Reservation #" . htmlspecialchars($newRes['reservation_number']) . " created successfully!";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Creation failed: " . $e->getMessage();
        }
    }
}

// 1b. Workflow Step 2: Farm Admin Approves Reservation
if (isset($_POST['approve_reservation'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff'])) {
        $error = "Access Denied. Only Farm Admin can approve reservations.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            $stmtR = $conn->prepare("SELECT r.*, c.name as customer_name, h.house_name FROM reservations r JOIN customers c ON r.customer_id = c.customer_id LEFT JOIN houses h ON r.house_id = h.house_id WHERE r.reservation_id = ?");
            $stmtR->execute([$resId]);
            $resRow = $stmtR->fetch(PDO::FETCH_ASSOC);
            if (!$resRow) throw new Exception("Reservation not found.");

            // Strict Prerequisite Enforcement: Step 1 submission must be pending approval
            if (($resRow['workflow_stage'] ?? 'pending_approval') !== 'pending_approval') {
                throw new Exception("Prerequisite Not Met: Step 1 (Reservation Submission) must be in 'pending_approval' before Step 2 Review & Approval can be executed. (Current Stage: " . ($resRow['workflow_stage'] ?? 'unknown') . ")");
            }

            $conn->prepare("UPDATE reservations SET workflow_stage = 'approved', updated_at = NOW() WHERE reservation_id = ?")->execute([$resId]);

            // Notify Integrator
            $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ?");
            $stmtIntUser->execute([(int)$resRow['customer_id'], (int)$resRow['created_by']]);
            $insNotif = $conn->prepare("INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at) VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())");
            foreach ($stmtIntUser->fetchAll(PDO::FETCH_COLUMN) as $iUid) {
                $insNotif->execute([(int)$iUid, "Reservation Approved: Farm Admin approved #" . $resRow['reservation_number'] . " for " . ($resRow['house_name'] ?: 'Pen') . ". Status: Step 2 Approved — Awaiting Delivery. Please schedule chick delivery to the farm.", $resId]);
            }

            // Notify Farm Workers
            $workerStmt = $conn->query("SELECT user_id FROM users WHERE role = 'worker'");
            foreach ($workerStmt->fetchAll(PDO::FETCH_COLUMN) as $wId) {
                $insNotif->execute([(int)$wId, "Flock Intake Prep: House " . ($resRow['house_name'] ?: 'Pen') . " approved for " . number_format($resRow['quantity_birds']) . " chicks from " . $resRow['customer_name'] . ". Please wash, sanitize, and prepare pen.", $resId]);
            }

            $_SESSION['flash_message'] = "Reservation #" . htmlspecialchars($resRow['reservation_number']) . " approved successfully! Integrator notified.";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Approval failed: " . $e->getMessage();
        }
    }
}

// 1b-2. Workflow Step 2: Farm Admin Rejects Reservation with mandatory reason
if (isset($_POST['reject_reservation'])) {
    if (strtolower($user_role) !== 'admin') {
        $error = "Access Denied. Only Farm Admin can reject reservations.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        try {
            if ($resId <= 0) throw new Exception("Invalid reservation ID.");
            if (empty($reason)) throw new Exception("Rejection reason is mandatory.");

            $stmtR = $conn->prepare("SELECT r.*, c.name as customer_name, h.house_name FROM reservations r JOIN customers c ON r.customer_id = c.customer_id LEFT JOIN houses h ON r.house_id = h.house_id WHERE r.reservation_id = ?");
            $stmtR->execute([$resId]);
            $resRow = $stmtR->fetch(PDO::FETCH_ASSOC);
            if (!$resRow) throw new Exception("Reservation not found.");

            $conn->prepare("UPDATE reservations SET status = 'cancelled', rejection_reason = ?, notes = CONCAT(COALESCE(notes, ''), ' [Admin Rejected: ', ?, ']'), updated_at = NOW() WHERE reservation_id = ?")->execute([$reason, $reason, $resId]);

            // Release house if reserved
            if (!empty($resRow['house_id'])) {
                $conn->prepare("UPDATE houses SET status = 'available' WHERE house_id = ? AND status = 'reserved'")->execute([(int)$resRow['house_id']]);
            }

            // Notify Integrator
            $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ?");
            $stmtIntUser->execute([(int)$resRow['customer_id'], (int)$resRow['created_by']]);
            $insNotif = $conn->prepare("INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at) VALUES (?, ?, 'reservation_due', 'reservations', ?, 'critical', 'unread', NOW())");
            foreach ($stmtIntUser->fetchAll(PDO::FETCH_COLUMN) as $iUid) {
                $insNotif->execute([(int)$iUid, "Reservation Rejected: Farm Admin rejected #" . $resRow['reservation_number'] . ". Reason: " . $reason, $resId]);
            }

            $_SESSION['flash_message'] = "Reservation #" . htmlspecialchars($resRow['reservation_number']) . " was rejected. Reason logged and partner notified.";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Rejection failed: " . $e->getMessage();
        }
    }
}

// 1c. Workflow Step 3: Company Delivers the Chicks (Intake Arrival)
if (isset($_POST['record_delivery_intake'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff', 'worker'])) {
        $error = "Access Denied. Insufficient permissions.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            $stmtR = $conn->prepare("SELECT r.*, c.name as customer_name, h.house_name FROM reservations r JOIN customers c ON r.customer_id = c.customer_id LEFT JOIN houses h ON r.house_id = h.house_id WHERE r.reservation_id = ?");
            $stmtR->execute([$resId]);
            $resRow = $stmtR->fetch(PDO::FETCH_ASSOC);
            if (!$resRow) throw new Exception("Reservation not found.");

            // Strict Prerequisite Enforcement: Step 2 (Admin Approval) must be completed before Step 3 intake
            if (($resRow['workflow_stage'] ?? '') !== 'approved') {
                throw new Exception("Prerequisite Not Met: Chicks cannot be recorded as arrived until Step 2 (Farm Admin Feasibility Review & Approval) is completed. (Current Stage: " . ($resRow['workflow_stage'] ?? 'unknown') . ")");
            }

            $conn->prepare("UPDATE reservations SET workflow_stage = 'receiving', updated_at = NOW() WHERE reservation_id = ?")->execute([$resId]);

            // Notify Integrator
            $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ?");
            $stmtIntUser->execute([(int)$resRow['customer_id'], (int)$resRow['created_by']]);
            $insNotif = $conn->prepare("INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at) VALUES (?, ?, 'reservation_due', 'reservations', ?, 'warning', 'unread', NOW())");
            foreach ($stmtIntUser->fetchAll(PDO::FETCH_COLUMN) as $iUid) {
                $insNotif->execute([(int)$iUid, "Chicks Delivery Recorded: Shipment for " . ($resRow['house_name'] ?: 'Pen') . " (Ref: #" . $resRow['reservation_number'] . ") has arrived at the farm. Staff is inspecting shipment condition.", $resId]);
            }

            $_SESSION['flash_message'] = "Delivery intake recorded for #" . htmlspecialchars($resRow['reservation_number']) . "! Status updated to Step 3: Receiving in Progress.";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Recording delivery intake failed: " . $e->getMessage();
        }
    }
}

// 1d. Workflow Step 4: Farm Verifies Receipt & Transitions to Step 5 (Batch Management)
if (isset($_POST['verify_receipt_and_activate'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff'])) {
        $error = "Access Denied. Only Farm Admin or Staff can verify chick receipts.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        $acceptedQty = intval($_POST['accepted_quantity'] ?? 0);
        $rejectedQty = intval($_POST['rejected_quantity'] ?? 0);
        $receiptNo = trim($_POST['receipt_number'] ?? '');
        $rejectionReason = trim($_POST['rejection_reason'] ?? $_POST['remarks'] ?? '');

        try {
            if ($resId <= 0 || $acceptedQty <= 0) {
                throw new Exception("Accepted chick quantity must be greater than 0.");
            }
            $stmtR = $conn->prepare("SELECT r.*, c.name as customer_name, h.house_name, h.house_id FROM reservations r JOIN customers c ON r.customer_id = c.customer_id LEFT JOIN houses h ON r.house_id = h.house_id WHERE r.reservation_id = ?");
            $stmtR->execute([$resId]);
            $resRow = $stmtR->fetch(PDO::FETCH_ASSOC);
            if (!$resRow) throw new Exception("Reservation not found.");

            // Strict Prerequisite Enforcement: Step 3 (Delivery Arrival Intake) must be recorded before Step 4 verification
            if (($resRow['workflow_stage'] ?? '') !== 'receiving') {
                throw new Exception("Prerequisite Not Met: Chick receipt verification cannot proceed until Step 3 (Delivery Intake Arrival) is recorded by Farm Staff. (Current Stage: " . ($resRow['workflow_stage'] ?? 'unknown') . ")");
            }

            $houseName = $resRow['house_name'] ?: 'House 1';
            $houseId = (int)$resRow['house_id'];

            // Save verified intake parameters onto the reservation and transition to 'receipt_confirmed'
            $conn->prepare("
                UPDATE reservations SET 
                    workflow_stage = 'receipt_confirmed',
                    accepted_quantity = ?,
                    rejected_quantity = ?,
                    receipt_number = ?,
                    rejection_reason = ?,
                    updated_at = NOW() 
                WHERE reservation_id = ?
            ")->execute([$acceptedQty, $rejectedQty, $receiptNo, $rejectionReason, $resId]);

            // Notify Integrator that receipt is verified and Step 5 batch code / flock placement is starting
            $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ?");
            $stmtIntUser->execute([(int)$resRow['customer_id'], (int)$resRow['created_by']]);
            $insNotif = $conn->prepare("INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at) VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())");
            foreach ($stmtIntUser->fetchAll(PDO::FETCH_COLUMN) as $iUid) {
                $insNotif->execute([(int)$iUid, "Receipt Confirmed: Farm verified " . number_format($acceptedQty) . " accepted chicks (DR: " . ($receiptNo ?: "DR-{$resId}") . ") in " . $houseName . ". Final Step 5 (Batch Activation) in progress.", $resId]);
            }

            $_SESSION['step5_notice'] = "Step 4 Verified! " . number_format($acceptedQty) . " chicks verified for " . ($resRow['customer_name'] ?? 'Integrator') . ". All integrator and intake details are auto-filled below. Admin only needs to review the Batch Code and Note, then click 'Save Batch Record' to activate Step 5!";
            header("Location: batch.php?from_res=" . $resId);
            exit();
        } catch (Exception $e) {
            $error = "Intake verification failed: " . $e->getMessage();
        }
    }
}

// 2. Record Fulfillment / Pickup / Dispatch
if (isset($_POST['record_fulfillment'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff'])) {
        $error = "Access Denied. Insufficient permissions to fulfill reservations.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            if ($resId <= 0) {
                throw new Exception("Invalid reservation ID.");
            }

            // Retrieve current reservation & partner details
            $stmtResInfo = $conn->prepare("
                SELECT r.reservation_number, r.house_id, r.batch_id, c.name as customer_name, c.customer_code
                FROM reservations r
                LEFT JOIN customers c ON r.customer_id = c.customer_id
                WHERE r.reservation_id = ?
            ");
            $stmtResInfo->execute([$resId]);
            $resInfo = $stmtResInfo->fetch(PDO::FETCH_ASSOC);
            if (!$resInfo) {
                throw new Exception("Reservation not found.");
            }

            $fulfillmentData = [
                'actual_pickup_date' => !empty($_POST['actual_pickup_date']) ? trim($_POST['actual_pickup_date']) : date('Y-m-d'),
                'accepted_quantity' => intval($_POST['accepted_quantity'] ?? 0),
                'actual_weight_kg' => (isset($_POST['actual_weight_kg']) && $_POST['actual_weight_kg'] !== '') ? floatval($_POST['actual_weight_kg']) : null,
                'actual_price' => (isset($_POST['actual_price']) && $_POST['actual_price'] !== '') ? floatval($_POST['actual_price']) : null,
                'receipt_number' => trim($_POST['receipt_number'] ?? ''),
                'rejected_quantity' => intval($_POST['rejected_quantity'] ?? 0),
                'rejection_reason' => trim($_POST['rejection_reason'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
                'status' => 'fulfilled'
            ];

            // Security Gate: Full payment must be manually settled prior to dispatch and pickup
            $stmtBal = $conn->prepare("SELECT total_amount, advance_payment, reservation_number FROM reservations WHERE reservation_id = ?");
            $stmtBal->execute([$resId]);
            $resBalRow = $stmtBal->fetch(PDO::FETCH_ASSOC);
            if ($resBalRow) {
                $unpaidBal = round((float)$resBalRow['total_amount'] - (float)$resBalRow['advance_payment'], 2);
                if ($unpaidBal > 0.01) {
                    throw new Exception("Security Gate: Cannot dispatch or pick up flock! An outstanding balance of ₱" . number_format($unpaidBal, 2) . " remains on #" . $resBalRow['reservation_number'] . ". Full payment must be manually settled first before pickup.");
                }
            }

            $resService->recordFulfillment($resId, $fulfillmentData, $user_id);

            // Automatically check and update house status if batch is completely fulfilled / harvested
            $targetHouseId = !empty($_POST['house_id']) ? intval($_POST['house_id']) : (int)($resInfo['house_id'] ?? 0);
            if ($targetHouseId > 0) {
                $stmtH = $conn->prepare("SELECT current_batch_id FROM houses WHERE house_id = ?");
                $stmtH->execute([$targetHouseId]);
                $curBatchId = $stmtH->fetchColumn();
                if ($curBatchId) {
                    $stmtB = $conn->prepare("SELECT current_quantity, status FROM batches WHERE batch_id = ?");
                    $stmtB->execute([$curBatchId]);
                    $bData = $stmtB->fetch(PDO::FETCH_ASSOC);
                    if ($bData && ($bData['current_quantity'] <= 0 || in_array($bData['status'], ['harvested', 'closed']))) {
                        $conn->prepare("UPDATE houses SET status = 'cleaning', current_batch_id = NULL WHERE house_id = ?")->execute([$targetHouseId]);
                    }
                }
            }

            // Notify Integrator that their reservation has been fulfilled / accepted by Admin
            try {
                $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = (SELECT created_by FROM reservations WHERE reservation_id = ?) LIMIT 1");
                $stmtIntUser->execute([(int)($resInfo['customer_id'] ?? 0), $resId]);
                $intUserId = $stmtIntUser->fetchColumn();

                $notifAccMsg = "Flock Fulfilled & Dispatched: Reservation #" . ($resInfo['reservation_number'] ?? $resId) . " has been officially fulfilled and released by Farm Admin (" . number_format($fulfillmentData['accepted_quantity']) . " birds). Delivery Receipt: " . (!empty($fulfillmentData['receipt_number']) ? $fulfillmentData['receipt_number'] : "DR-{$resId}") . ".";
                $insNotifAcc = $conn->prepare("
                    INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                    VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())
                ");
                $insNotifAcc->execute([$intUserId ?: null, $notifAccMsg, $resId]);
            } catch (\Exception $exN) {}

            $custName = $resInfo['customer_name'] ?: 'Partner';
            $drNo = !empty($fulfillmentData['receipt_number']) ? $fulfillmentData['receipt_number'] : "DR-{$resId}";
            $message = "Flock successfully dispatched and delivered to " . $custName . " (" . $resInfo['reservation_number'] . ")! Delivered: " . number_format($fulfillmentData['accepted_quantity']) . " birds (" . number_format((float)$fulfillmentData['actual_weight_kg'], 2) . " kg). Gate Pass / DR: " . $drNo . ".";
            $_SESSION['flash_message'] = $message;
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Fulfillment failed: " . $e->getMessage();
        }
    }
}

// 3. Update Broiler Reservation Details
if (isset($_POST['update_reservation'])) {
    if (strtolower($user_role) !== 'admin') {
        $error = "Access Denied. Only admin can update reservations.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            if ($resId <= 0) {
                throw new Exception("Invalid reservation ID.");
            }
            $custId = intval($_POST['customer_id'] ?? 0);
            if ($custId <= 0) {
                $chkCur = $conn->prepare("SELECT customer_id FROM reservations WHERE reservation_id = ?");
                $chkCur->execute([$resId]);
                $custId = (int)$chkCur->fetchColumn();
            }
            // Ensure customer exists
            $chkC = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
            $chkC->execute([$custId]);
            if (!$chkC->fetchColumn()) {
                $custId = (int)$conn->query("SELECT customer_id FROM customers WHERE is_active = 1 ORDER BY customer_id ASC LIMIT 1")->fetchColumn();
            }

            $updateBatchId = !empty($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
            if ($updateBatchId <= 0) {
                throw new Exception("Please select a valid broiler batch. Every reservation must belong to a batch.");
            }

            $updateData = [
                'customer_id' => $custId,
                'batch_id' => $updateBatchId,
                'house_id' => !empty($_POST['house_id']) ? intval($_POST['house_id']) : null,
                'quantity_birds' => intval($_POST['quantity_birds'] ?? 0),
                'weight_kg' => (isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '') ? floatval($_POST['weight_kg']) : null,
                'target_weight_per_bird' => (isset($_POST['target_weight_per_bird']) && $_POST['target_weight_per_bird'] !== '') ? floatval($_POST['target_weight_per_bird']) : 1.80,
                'breed' => trim($_POST['breed'] ?? 'Cobb 500'),
                'chick_cost' => (isset($_POST['chick_cost']) && $_POST['chick_cost'] !== '') ? floatval($_POST['chick_cost']) : 38.00,
                'unit_price' => (isset($_POST['unit_price']) && $_POST['unit_price'] !== '') ? floatval($_POST['unit_price']) : null,
                'total_amount' => floatval($_POST['total_amount'] ?? 0),
                'advance_payment' => floatval($_POST['advance_payment'] ?? 0),
                'pickup_date' => trim($_POST['pickup_date'] ?? ''),
                'notes' => trim($_POST['notes'] ?? '')
            ];
            $resService->updateReservation($resId, $updateData, $user_id);
            if (!empty($_POST['status'])) {
                $resService->updateStatus($resId, $_POST['status'], $user_id);
            }
            $_SESSION['flash_message'] = "Reservation #{$resId} updated successfully!";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// 4. Cancel Reservation
if (isset($_POST['cancel_reservation'])) {
    if (strtolower($user_role) !== 'admin') {
        $error = "Access Denied. Only admin can cancel reservations.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            if ($resId <= 0) {
                throw new Exception("Invalid reservation ID.");
            }
            $cancelledRes = $resService->updateStatus($resId, 'cancelled', $user_id);
            // Notify Integrator of reservation cancellation
            try {
                $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = (SELECT created_by FROM reservations WHERE reservation_id = ?) LIMIT 1");
                $stmtIntUser->execute([(int)($cancelledRes['customer_id'] ?? 0), $resId]);
                $intUserId = $stmtIntUser->fetchColumn();
                if ($intUserId) {
                    $insN = $conn->prepare("INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at) VALUES (?, ?, 'reservation_due', 'reservations', ?, 'warning', 'unread', NOW())");
                    $insN->execute([$intUserId, "Reservation Cancelled: Reservation #" . ($cancelledRes['reservation_number'] ?? $resId) . " has been cancelled by Farm Administration.", $resId]);
                }
            } catch (\Exception $exN) {}
            $_SESSION['flash_message'] = "Reservation #{$resId} cancelled successfully!";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Cancellation failed: " . $e->getMessage();
        }
    }
}

// 5. Restore / Uncancel Reservation
if (isset($_POST['restore_reservation'])) {
    if (strtolower($user_role) !== 'admin') {
        $error = "Access Denied. Only admin can restore reservations.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            if ($resId <= 0) {
                throw new Exception("Invalid reservation ID.");
            }
            $resService->updateStatus($resId, 'active', $user_id);
            $_SESSION['flash_message'] = "Reservation #{$resId} was restored to Active successfully!";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Restore failed: " . $e->getMessage();
        }
    }
}

// 6. Delete Reservation (Permanent - Admin Only)
if (isset($_POST['delete_reservation'])) {
    if (strtolower($user_role) !== 'admin') {
        $error = "Access Denied. Only admin can permanently delete reservations.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            if ($resId <= 0) {
                throw new Exception("Invalid reservation ID.");
            }
            $resService->deleteReservation($resId, $user_id);
            $_SESSION['flash_message'] = "Reservation #{$resId} was permanently deleted from the system.";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Deletion failed: " . $e->getMessage();
        }
    }
}

// 6. Record Quick Sample Weighing (for Release Verification)
if (isset($_POST['record_quick_weight'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff', 'worker'])) {
        $error = "Access Denied. Only farm operators and workers can record weight samples.";
    } else {
        try {
            $batch_id = intval($_POST['batch_id'] ?? 0);
            $weight_kg = floatval($_POST['weight_kg'] ?? 0);
            $sample_date = !empty($_POST['sample_date']) ? $_POST['sample_date'] : date('Y-m-d');
            $birds_sampled = intval($_POST['birds_sampled'] ?? 50);
            $notes = trim($_POST['notes'] ?? '');

            if ($batch_id <= 0 || $weight_kg <= 0) {
                throw new Exception("Please specify a valid batch and measured weight greater than 0 kg.");
            }

            // Update batch current weight
            $stmtW = $conn->prepare("UPDATE batches SET current_weight_kg = ?, updated_at = NOW() WHERE batch_id = ?");
            $stmtW->execute([$weight_kg, $batch_id]);

            // Check linked active reservations for harvest/dispatch target weight readiness
            try {
                $stmtRel = $conn->prepare("
                    SELECT r.reservation_id, r.reservation_number, r.target_weight_per_bird, r.total_amount, r.advance_payment, r.customer_id, r.created_by, c.name as customer_name
                    FROM reservations r
                    LEFT JOIN customers c ON r.customer_id = c.customer_id
                    WHERE r.batch_id = ? AND r.status = 'active'
                ");
                $stmtRel->execute([$batch_id]);
                $linkedList = $stmtRel->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($linkedList)) {
                    $adminStmt = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
                    $adminUserIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: [1];

                    $insN = $conn->prepare("
                        INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                        VALUES (?, ?, 'reservation_due', 'reservations', ?, ?, 'unread', NOW())
                    ");

                    foreach ($linkedList as $lr) {
                        $targetW = !empty($lr['target_weight_per_bird']) ? (float)$lr['target_weight_per_bird'] : 2.20;
                        if ($weight_kg >= $targetW) {
                            $tot = (float)$lr['total_amount'];
                            $adv = (float)$lr['advance_payment'];
                            $bal = max(0, round($tot - $adv, 2));

                            $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
                            $stmtIntUser->execute([(int)$lr['customer_id'], (int)$lr['created_by']]);
                            $intUserId = $stmtIntUser->fetchColumn();

                            if ($bal > 0.01) {
                                // Balance reminder to Integrator and notification to Admin
                                $intMsg = "Flock Ready for Dispatch: Flock #" . $lr['reservation_number'] . " has reached target weight (" . number_format($weight_kg, 2) . " kg vs " . number_format($targetW, 2) . " kg target). Please settle your outstanding balance of ₱" . number_format($bal, 2) . " so Admin can dispatch your chicks!";
                                if ($intUserId) {
                                    $insN->execute([$intUserId, $intMsg, (int)$lr['reservation_id'], 'warning']);
                                }
                                $admMsg = "Flock Target Reached: Flock #" . $lr['reservation_number'] . " (" . ($lr['customer_name'] ?? 'Partner') . ") reached " . number_format($weight_kg, 2) . " kg. Outstanding balance: ₱" . number_format($bal, 2) . " (Awaiting payment settlement before dispatch).";
                                foreach ($adminUserIds as $admId) {
                                    $insN->execute([(int)$admId, $admMsg, (int)$lr['reservation_id'], 'info']);
                                }
                            } else {
                                // Fully paid flock ready for dispatch clearance
                                $intMsg = "Flock Ready for Dispatch: Flock #" . $lr['reservation_number'] . " has reached target weight (" . number_format($weight_kg, 2) . " kg) and is 100% fully paid! Awaiting Admin dispatch clearance.";
                                if ($intUserId) {
                                    $insN->execute([$intUserId, $intMsg, (int)$lr['reservation_id'], 'info']);
                                }
                                $admMsg = "Flock Ready for Dispatch: Flock #" . $lr['reservation_number'] . " (" . ($lr['customer_name'] ?? 'Partner') . ") reached " . number_format($weight_kg, 2) . " kg and is 100% paid. Ready for Admin inspection and dispatch clearance.";
                                foreach ($adminUserIds as $admId) {
                                    $insN->execute([(int)$admId, $admMsg, (int)$lr['reservation_id'], 'info']);
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $exN) {}

            // Weight sampling updates batch current weight directly and is not logged into farm operations (activities table)
            $_SESSION['flash_message'] = "Weight sample recorded ({$weight_kg} kg avg)! Flock release readiness recalculated.";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to record weight sample: " . $e->getMessage();
        }
    }
}

// 7. Settle Reservation Balance (Integrator Only - Admin will dispatch once fully paid)
if (isset($_POST['settle_reservation_balance'])) {
    if (!$isIntegrator) {
        $error = "Access Denied. Manual payment settlement is for integrator / partner only. Admin will dispatch the chicks once the integrator has fully paid the price.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        $payAmount = floatval($_POST['payment_amount'] ?? 0);
        $payMethod = trim($_POST['payment_method'] ?? 'Bank Transfer (Online)');
        $payRef = trim($_POST['payment_reference'] ?? '');
        $payNotes = trim($_POST['payment_notes'] ?? '');

        try {
            if ($resId <= 0 || $payAmount <= 0) {
                throw new Exception("Please specify a valid payment amount greater than ₱0.00.");
            }

            $stmt = $conn->prepare("SELECT reservation_id, reservation_number, total_amount, advance_payment, customer_id, created_by FROM reservations WHERE reservation_id = ?");
            $stmt->execute([$resId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$res) {
                throw new Exception("Reservation record not found.");
            }

            if ($integratorCustomerId > 0 && (int)$res['customer_id'] !== $integratorCustomerId && (int)($res['created_by'] ?? 0) !== $user_id) {
                throw new Exception("Access Denied. You are only authorized to settle payments for your own company reservations.");
            }

            $curAdv = (float)$res['advance_payment'];
            $totAmt = (float)$res['total_amount'];
            $newAdv = round(min($totAmt, $curAdv + $payAmount), 2);
            $remBal = max(0, round($totAmt - $newAdv, 2));

            $stmtU = $conn->prepare("UPDATE reservations SET advance_payment = ?, updated_at = NOW() WHERE reservation_id = ?");
            $stmtU->execute([$newAdv, $resId]);

            // Audit log payment settlement
            $stmtAudit = $conn->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'payment_settle', 'reservations', ?, ?, ?, NOW())
            ");
            $stmtAudit->execute([
                $user_id,
                $resId,
                json_encode([
                    'amount_paid' => $payAmount,
                    'method' => $payMethod,
                    'reference' => $payRef,
                    'notes' => $payNotes,
                    'previous_advance' => $curAdv,
                    'new_advance_payment' => $newAdv,
                    'remaining_balance' => $remBal
                ]),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            if ($remBal <= 0.001) {
                $msg = "Payment of ₱" . number_format($payAmount, 2) . " received via " . $payMethod . " (Ref: " . $payRef . ")! Reservation #" . $res['reservation_number'] . " is now FULLY PAID and unlocked for pick up!";
                // Notify Farm Admin that balance is 100% settled
                try {
                    $adminStmt = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
                    $adminUserIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: [1];
                    $insNotif = $conn->prepare("
                        INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                        VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())
                    ");
                    $admMsg = "Payment Settled: #" . $res['reservation_number'] . " (" . ($res['customer_name'] ?? 'Partner') . ") is 100% FULLY PAID (₱" . number_format($payAmount, 2) . " via " . $payMethod . "). Ready for Admin flock inspection & dispatch!";
                    foreach ($adminUserIds as $admId) {
                        $insNotif->execute([(int)$admId, $admMsg, $resId]);
                    }

                    // Also notify Integrator that full payment is confirmed
                    $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
                    $stmtIntUser->execute([(int)$res['customer_id'], (int)$res['created_by']]);
                    $intUserId = $stmtIntUser->fetchColumn();
                    if ($intUserId) {
                        $intMsg = "Payment Confirmed: Payment of ₱" . number_format($payAmount, 2) . " (Ref: " . $payRef . ") received for Reservation #" . $res['reservation_number'] . ". Your reservation is 100% FULLY PAID and queued for dispatch inspection.";
                        $insNotif->execute([(int)$intUserId, $intMsg, $resId]);
                    }
                } catch (\Exception $exN) {}
            } else {
                $msg = "Payment of ₱" . number_format($payAmount, 2) . " credited to #" . $res['reservation_number'] . ". Outstanding balance is now ₱" . number_format($remBal, 2) . ".";
                // Notify Admin and Integrator of partial payment
                try {
                    $adminStmt = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
                    $adminUserIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: [1];
                    $insNotif = $conn->prepare("
                        INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                        VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())
                    ");
                    $admMsg = "Payment Received: #" . $res['reservation_number'] . " (" . ($res['customer_name'] ?? 'Partner') . ") paid ₱" . number_format($payAmount, 2) . " via " . $payMethod . ". Outstanding balance: ₱" . number_format($remBal, 2) . ".";
                    foreach ($adminUserIds as $admId) {
                        $insNotif->execute([(int)$admId, $admMsg, $resId]);
                    }

                    $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
                    $stmtIntUser->execute([(int)$res['customer_id'], (int)$res['created_by']]);
                    $intUserId = $stmtIntUser->fetchColumn();
                    if ($intUserId) {
                        $intMsg = "Payment Received: ₱" . number_format($payAmount, 2) . " credited to Reservation #" . $res['reservation_number'] . ". Outstanding balance: ₱" . number_format($remBal, 2) . ".";
                        $insNotif->execute([(int)$intUserId, $intMsg, $resId]);
                    }
                } catch (\Exception $exN) {}
            }
            $_SESSION['flash_message'] = $msg;
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Payment settlement failed: " . $e->getMessage();
        }
    }
}

// 7b. Admin Dispatch Chicks to Integrator (Enforcing Agreement & Full Payment)
if (isset($_POST['admin_dispatch_chicks'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff'])) {
        $error = "Access Denied. Only farm administrators can inspect and dispatch chicks to integrators.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        $dispatchNotes = trim($_POST['dispatch_notes'] ?? '');
        try {
            $dispatchedRes = $resService->dispatchFlock($resId, $dispatchNotes, $user_id);
            
            // Notify Integrator Partner that flock has been dispatched and cleared for pickup
            try {
                $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
                $stmtIntUser->execute([(int)($dispatchedRes['customer_id'] ?? 0), (int)($dispatchedRes['created_by'] ?? 0)]);
                $intUserId = $stmtIntUser->fetchColumn();

                $stmtIntNotif = $conn->prepare("
                    INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                    VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())
                ");
                $stmtIntNotif->execute([
                    $intUserId ?: null,
                    "Flock Dispatched: Farm Admin has officially inspected and dispatched chicks for Reservation #" . ($dispatchedRes['reservation_number'] ?? $resId) . ". Hauling trucks are now cleared for pickup!",
                    $resId
                ]);
            } catch (\Exception $exN) {}

            $_SESSION['flash_message'] = "Chicks successfully inspected and dispatched to integrator! Integrator is now cleared to authorize hauling truck pick up.";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Admin dispatch failed: " . $e->getMessage();
        }
    }
}

// 7c. Admin Send Balance Reminder to Integrator
if (isset($_POST['send_balance_reminder'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff'])) {
        $error = "Access Denied. Only farm administrators can send payment reminders.";
    } else {
        $resId = intval($_POST['reservation_id'] ?? 0);
        try {
            $stmtRes = $conn->prepare("
                SELECT r.*, c.name as customer_name 
                FROM reservations r 
                LEFT JOIN customers c ON r.customer_id = c.customer_id 
                WHERE r.reservation_id = ?
            ");
            $stmtRes->execute([$resId]);
            $res = $stmtRes->fetch(PDO::FETCH_ASSOC);
            if (!$res) {
                throw new Exception("Reservation not found.");
            }
            $tot = (float)$res['total_amount'];
            $adv = (float)$res['advance_payment'];
            $rem = max(0, round($tot - $adv, 2));
            if ($rem <= 0.001) {
                throw new Exception("This reservation is already fully paid. No reminder needed.");
            }

            // Find integrator user ID
            $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
            $stmtIntUser->execute([(int)$res['customer_id'], (int)$res['created_by']]);
            $intUserId = $stmtIntUser->fetchColumn();

            $targetW = (float)($res['target_weight_per_bird'] ?? 2.20);
            $reminderMsg = "Payment Reminder: Flock #" . $res['reservation_number'] . " meets agreed standards (Target: " . number_format($targetW, 2) . " kg). Please settle outstanding balance of ₱" . number_format($rem, 2) . " so Admin can dispatch your chicks!";

            $stmtNotif = $conn->prepare("
                INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                VALUES (?, ?, 'reservation_due', 'reservations', ?, 'warning', 'unread', NOW())
            ");
            $stmtNotif->execute([$intUserId ?: null, $reminderMsg, $resId]);

            $_SESSION['flash_message'] = "Payment reminder successfully sent to integrator for Reservation #" . htmlspecialchars($res['reservation_number']) . "! (Outstanding: ₱" . number_format($rem, 2) . ")";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "Reminder failed: " . $e->getMessage();
        }
    }
}

// 8. Integrator Confirm Pick Up & Hauling Clearance (Full Payment & Admin Dispatch Enforced)
if (isset($_POST['integrator_confirm_pickup'])) {
    $resId = intval($_POST['reservation_id'] ?? 0);
    try {
        if ($resId <= 0) {
            throw new Exception("Invalid reservation ID.");
        }

        $stmt = $conn->prepare("
            SELECT r.*, c.name as customer_name, c.customer_code 
            FROM reservations r 
            LEFT JOIN customers c ON r.customer_id = c.customer_id 
            WHERE r.reservation_id = ?
        ");
        $stmt->execute([$resId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) {
            throw new Exception("Reservation record not found.");
        }

        $totAmt = (float)$res['total_amount'];
        $advAmt = (float)$res['advance_payment'];
        $unpaidBal = round($totAmt - $advAmt, 2);

        // Security Gate 1: Strict check that reservation is 100% fully paid!
        if ($unpaidBal > 0.01) {
            throw new Exception("Security Gate: Cannot pick up flock! An outstanding balance of ₱" . number_format($unpaidBal, 2) . " remains. Full payment must be settled before trucks can be dispatched.");
        }

        // Security Gate 2: Integrator cannot pick up dayon! Must wait until Admin officially dispatches the flock!
        if (empty($res['is_dispatched'])) {
            throw new Exception("Security Gate: Flock has not been dispatched by the Farm Admin yet. Even when fully paid, the admin must inspect the flock and officially dispatch the chicks before truck hauling and pick up can proceed.");
        }

        // Security Gate 3: Agreed pickup date reached!
        $today = date('Y-m-d');
        if (!empty($res['pickup_date']) && $today < $res['pickup_date']) {
            throw new Exception("Security Gate: Agreed pickup date (" . date('M d, Y', strtotime($res['pickup_date'])) . ") has not arrived yet. Flock hauling is scheduled for " . date('F d, Y', strtotime($res['pickup_date'])) . ".");
        }

        $actualDate = !empty($_POST['actual_pickup_date']) ? trim($_POST['actual_pickup_date']) : date('Y-m-d');
        if ($actualDate < $today) {
            throw new Exception("Security Gate: Hauling pick up date cannot be in the past (" . htmlspecialchars($actualDate) . "). Only today or future dates can be selected.");
        }
        $haulerInfo = trim($_POST['hauler_info'] ?? '');
        $receiptNumber = trim($_POST['receipt_number'] ?? ("DR-" . date('Ymd') . "-" . str_pad($resId, 4, '0', STR_PAD_LEFT)));
        $notes = trim($_POST['notes'] ?? '');
        $combinedNotes = !empty($haulerInfo) ? "Hauler: " . $haulerInfo . ". " . $notes : $notes;

        $acceptedQty = (int)($res['quantity_birds'] ?? 0);
        $actualWeight = (float)($res['weight_kg'] ?? 0);
        if ($actualWeight <= 0 && (float)($res['target_weight_per_bird'] ?? 0) > 0) {
            $actualWeight = round($acceptedQty * (float)$res['target_weight_per_bird'], 2);
        }

        $fulfillmentData = [
            'actual_pickup_date' => $actualDate,
            'accepted_quantity'  => $acceptedQty,
            'actual_weight_kg'   => $actualWeight,
            'actual_price'       => $totAmt,
            'receipt_number'     => $receiptNumber,
            'rejected_quantity'  => 0,
            'rejection_reason'   => '',
            'notes'              => $combinedNotes,
            'status'             => 'fulfilled'
        ];

        $resService->recordFulfillment($resId, $fulfillmentData, $user_id);

        // Notify Farm Admin and Integrator of completed flock pickup
        try {
            $adminStmt = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
            $adminUserIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: [1];

            $insNotif = $conn->prepare("
                INSERT INTO notifications (user_id, message, type, entity_type, entity_id, severity, status, created_at)
                VALUES (?, ?, 'reservation_due', 'reservations', ?, 'info', 'unread', NOW())
            ");

            $admMsg = "Flock Picked Up: Integrator " . ($res['customer_name'] ?? 'Partner') . " has confirmed hauling and pickup for Reservation #" . $res['reservation_number'] . " (" . number_format($acceptedQty) . " birds). Delivery Receipt / Gate Pass: " . $receiptNumber . ".";
            foreach ($adminUserIds as $admId) {
                $insNotif->execute([(int)$admId, $admMsg, $resId]);
            }

            $stmtIntUser = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? OR user_id = ? LIMIT 1");
            $stmtIntUser->execute([(int)$res['customer_id'], (int)$res['created_by']]);
            $intUserId = $stmtIntUser->fetchColumn();
            if ($intUserId) {
                $intMsg = "Pick Up Completed: Flock hauling confirmed for Reservation #" . $res['reservation_number'] . " (" . number_format($acceptedQty) . " birds). Delivery Receipt: " . $receiptNumber . ". Thank you for your partnership!";
                $insNotif->execute([(int)$intUserId, $intMsg, $resId]);
            }
        } catch (\Exception $exN) {}

        // Clean house if batch is depleted / harvested
        $targetHouseId = (int)($res['house_id'] ?? 0);
        if ($targetHouseId > 0) {
            $stmtH = $conn->prepare("SELECT current_batch_id FROM houses WHERE house_id = ?");
            $stmtH->execute([$targetHouseId]);
            $curBatchId = $stmtH->fetchColumn();
            if ($curBatchId) {
                $stmtB = $conn->prepare("SELECT current_quantity, status FROM batches WHERE batch_id = ?");
                $stmtB->execute([$curBatchId]);
                $bData = $stmtB->fetch(PDO::FETCH_ASSOC);
                if ($bData && ($bData['current_quantity'] <= 0 || in_array($bData['status'], ['harvested', 'closed']))) {
                    $conn->prepare("UPDATE houses SET status = 'cleaning', current_batch_id = NULL WHERE house_id = ?")->execute([$targetHouseId]);
                }
            }
        }

        $_SESSION['flash_message'] = "Flock pick up successfully confirmed for #" . $res['reservation_number'] . "! Delivery Receipt / Gate Pass " . $receiptNumber . " generated. Hauling authorized!";
        header("Location: reservations.php");
        exit();
    } catch (Exception $e) {
        $error = "Pick up authorization failed: " . $e->getMessage();
    }
}

// 9. Mark House Cleaning Complete & Available (Farm Worker Only)
if (isset($_POST['mark_house_available'])) {
    if (strtolower($user_role) !== 'worker') {
        $error = "Access Denied. Farm admin cannot mark house cleaning complete. Only farm workers are authorized to inspect, certify, and complete house sanitation.";
    } else {
        $houseId = intval($_POST['house_id'] ?? 0);
        try {
            if ($houseId <= 0) {
                throw new Exception("Invalid house ID.");
            }
            $stmtH = $conn->prepare("SELECT house_id, house_name, status, capacity FROM houses WHERE house_id = ?");
            $stmtH->execute([$houseId]);
            $hRow = $stmtH->fetch(PDO::FETCH_ASSOC);
            if (!$hRow) {
                throw new Exception("Poultry house not found.");
            }

            // Verify house is not occupied by an active batch
            $stmtOcc = $conn->prepare("
                SELECT b.batch_code, b.current_quantity 
                FROM batches b 
                WHERE (b.house_pen = ? OR b.batch_id = (SELECT current_batch_id FROM houses WHERE house_id = ?))
                  AND b.status = 'active' 
                  AND b.current_quantity > 0
                LIMIT 1
            ");
            $stmtOcc->execute([$hRow['house_name'], $houseId]);
            $occB = $stmtOcc->fetch(PDO::FETCH_ASSOC);
            if ($occB) {
                throw new Exception("Cannot mark " . htmlspecialchars($hRow['house_name']) . " as available: It is currently occupied by active flock " . htmlspecialchars($occB['batch_code']) . " (" . number_format($occB['current_quantity']) . " birds).");
            }

            // Check if there is an active reservation
            $stmtActiveRes = $conn->prepare("
                SELECT reservation_id, reservation_number 
                FROM reservations 
                WHERE house_id = ? AND status = 'active'
                LIMIT 1
            ");
            $stmtActiveRes->execute([$houseId]);
            $actRes = $stmtActiveRes->fetch(PDO::FETCH_ASSOC);

            $targetStatus = $actRes ? 'reserved' : 'available';
            $performerName = htmlspecialchars($_SESSION['full_name'] ?? 'Farm Worker');

            $updH = $conn->prepare("
                UPDATE houses 
                SET status = ?, 
                    vacant_date = CURDATE(), 
                    current_batch_id = NULL,
                    notes = CONCAT(COALESCE(notes, ''), ' [Sanitation completed by ', ?, ' (', ?, ') at ', NOW(), ']') 
                WHERE house_id = ?
            ");
            $updH->execute([$targetStatus, $performerName, $user_role, $houseId]);

            // Audit log
            try {
                $auditRepo = new \Poultry\Repositories\AuditLogRepository();
                $auditRepo->log($user_id, 'update', 'houses', $houseId, ['status' => $hRow['status']], ['status' => $targetStatus, 'action' => 'cleaning_completed', 'performer' => $performerName]);
            } catch (Exception $audEx) {}

            // Worker operations & activity log
            try {
                $stmtAct = $conn->prepare("
                    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at, status)
                    VALUES (NULL, ?, 'Cleaning', 'Facility Cleaning', 0, CURDATE(), ?, NOW(), 'completed')
                ");
                $stmtAct->execute([$user_id, "Completed deep washing & biosecurity disinfection of " . $hRow['house_name'] . ". Pen ready for intake."]);

                $stmtClean = $conn->prepare("INSERT INTO cleaning_records (batch_id, date, remarks) VALUES (NULL, CURDATE(), ?)");
                $stmtClean->execute(["Pen washing & disinfection completed for " . $hRow['house_name'] . " by " . $performerName]);
            } catch (Exception $actEx) {}

            $_SESSION['flash_message'] = "House '{$hRow['house_name']}' sanitation protocol complete! Pen is marked " . ($actRes ? "Reserved for upcoming flock (#" . htmlspecialchars($actRes['reservation_number']) . ")" : "Vacant & Available for new flock intake") . ".";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "House update failed: " . $e->getMessage();
        }
    }
}

// 10. Set House to Cleaning Mode (Admin/Staff Action Only)
if (isset($_POST['mark_house_cleaning'])) {
    if (!in_array(strtolower($user_role), ['admin', 'staff'])) {
        $error = "Access Denied. Only Farm Admin and Staff can set a poultry house to cleaning mode. Farm workers perform the cleaning and certify completion.";
    } else {
        $houseId = intval($_POST['house_id'] ?? 0);
        try {
            if ($houseId <= 0) {
                throw new Exception("Invalid house ID.");
            }
            $stmtH = $conn->prepare("SELECT house_id, house_name, status FROM houses WHERE house_id = ?");
            $stmtH->execute([$houseId]);
            $hRow = $stmtH->fetch(PDO::FETCH_ASSOC);
            if (!$hRow) {
                throw new Exception("Poultry house not found.");
            }

            // Verify house is not occupied
            $stmtOcc = $conn->prepare("
                SELECT b.batch_code 
                FROM batches b 
                WHERE (b.house_pen = ? OR b.batch_id = (SELECT current_batch_id FROM houses WHERE house_id = ?))
                  AND b.status = 'active' 
                  AND b.current_quantity > 0
                LIMIT 1
            ");
            $stmtOcc->execute([$hRow['house_name'], $houseId]);
            if ($stmtOcc->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Cannot set " . htmlspecialchars($hRow['house_name']) . " to cleaning while an active flock is in the pen.");
            }

            $performerName = htmlspecialchars($_SESSION['full_name'] ?? 'Farm Worker');

            $updH = $conn->prepare("UPDATE houses SET status = 'cleaning', vacant_date = CURDATE(), notes = CONCAT(COALESCE(notes, ''), ' [Cleaning started by ', ?, ' (', ?, ') at ', NOW(), ']') WHERE house_id = ?");
            $updH->execute([$performerName, $user_role, $houseId]);

            // Audit log
            try {
                $auditRepo = new \Poultry\Repositories\AuditLogRepository();
                $auditRepo->log($user_id, 'update', 'houses', $houseId, ['status' => $hRow['status']], ['status' => 'cleaning', 'performer' => $performerName]);
            } catch (Exception $audEx) {}

            // Worker operations & activity log
            try {
                $stmtAct = $conn->prepare("
                    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at, status)
                    VALUES (NULL, ?, 'Cleaning', 'Pen Cleaning & Sanitation', 0, CURDATE(), ?, NOW(), 'pending')
                ");
                $stmtAct->execute([$user_id, "Started pen washing & disinfection protocol for " . $hRow['house_name']]);
            } catch (Exception $actEx) {}

            $_SESSION['flash_message'] = "House '{$hRow['house_name']}' is now set to Cleaning mode for washing and biosecurity sanitation.";
            header("Location: reservations.php");
            exit();
        } catch (Exception $e) {
            $error = "House update failed: " . $e->getMessage();
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$batchFilter = !empty($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$searchQuery = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if ($isIntegrator) {
    if ($integratorCustomerId > 0) {
        $where[] = "(r.customer_id = ? OR r.created_by = ?)";
        $params[] = $integratorCustomerId;
        $params[] = $user_id;
    } else {
        $where[] = "r.created_by = ?";
        $params[] = $user_id;
    }
}

if ($statusFilter !== 'all') {
    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}
if ($batchFilter > 0) {
    $where[] = "r.batch_id = ?";
    $params[] = $batchFilter;
}
if (!empty($searchQuery)) {
    if ($isIntegrator) {
        $where[] = "(r.reservation_number LIKE ? OR b.batch_code LIKE ?)";
        $like = "%$searchQuery%";
        $params[] = $like;
        $params[] = $like;
    } else {
        $where[] = "(r.reservation_number LIKE ? OR c.name LIKE ? OR c.customer_code LIKE ? OR b.batch_code LIKE ?)";
        $like = "%$searchQuery%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
}

// Pagination Logic
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$count_stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    LEFT JOIN batches b ON r.batch_id = b.batch_id
    WHERE " . implode(" AND ", $where) . "
");
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch reservations directly with broiler attributes (Paginated)
$stmt = $conn->prepare("
    SELECT r.*, 
           c.name as customer_name, c.customer_code, c.phone as customer_phone, c.address as customer_address,
           COALESCE(r.batch_id, h.current_batch_id) as effective_batch_id,
           COALESCE(b.batch_code, hb.batch_code) as batch_code,
           COALESCE(b.current_weight_kg, hb.current_weight_kg, 0.04) as batch_current_weight,
           COALESCE(b.target_weight_kg, hb.target_weight_kg, 2.20) as batch_target_weight,
           COALESCE(b.arrival_date, hb.arrival_date) as batch_arrival_date,
           h.house_name, h.capacity as house_capacity,
           COALESCE(r.quantity_birds, 0) AS display_quantity,
           COALESCE(r.total_amount, 0.00) AS total_amount,
           COALESCE(r.advance_payment, 0.00) AS advance_payment,
           u.full_name as created_by_name
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    LEFT JOIN houses h ON r.house_id = h.house_id
    LEFT JOIN batches b ON r.batch_id = b.batch_id
    LEFT JOIN batches hb ON h.current_batch_id = hb.batch_id
    LEFT JOIN users u ON r.created_by = u.user_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY r.reservation_id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active Batches for selection (with current, target weight and house pen)
if ($isIntegrator) {
    $compName = trim($integratorCustomer['name'] ?? '');
    if (!empty($compName)) {
        $compNameLike = '%' . $compName . '%';
        $batchStmt = $conn->prepare("
            SELECT DISTINCT b.batch_id, b.batch_code, b.current_quantity, b.initial_quantity, b.target_weight_kg, b.current_weight_kg, b.arrival_date, b.house_pen, COALESCE(b.chick_unit_cost, 38.00) as chick_unit_cost 
            FROM batches b 
            LEFT JOIN (
                SELECT res.batch_id, res.customer_id, res.created_by, h.house_name
                FROM reservations res
                LEFT JOIN houses h ON res.house_id = h.house_id
            ) r ON (b.batch_id = r.batch_id OR (b.house_pen IS NOT NULL AND b.house_pen = r.house_name))
            WHERE b.status = 'active'
              AND ((? > 0 AND r.customer_id = ?) OR r.created_by = ? OR b.supplier LIKE ?)
            ORDER BY b.batch_code ASC
        ");
        $batchStmt->execute([$integratorCustomerId, $integratorCustomerId, $user_id, $compNameLike]);
    } else {
        $batchStmt = $conn->prepare("
            SELECT DISTINCT b.batch_id, b.batch_code, b.current_quantity, b.initial_quantity, b.target_weight_kg, b.current_weight_kg, b.arrival_date, b.house_pen, COALESCE(b.chick_unit_cost, 38.00) as chick_unit_cost 
            FROM batches b 
            LEFT JOIN (
                SELECT res.batch_id, res.customer_id, res.created_by, h.house_name
                FROM reservations res
                LEFT JOIN houses h ON res.house_id = h.house_id
            ) r ON (b.batch_id = r.batch_id OR (b.house_pen IS NOT NULL AND b.house_pen = r.house_name))
            WHERE b.status = 'active'
              AND ((? > 0 AND r.customer_id = ?) OR r.created_by = ?)
            ORDER BY b.batch_code ASC
        ");
        $batchStmt->execute([$integratorCustomerId, $integratorCustomerId, $user_id]);
    }
    $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $batches = $conn->query("SELECT batch_id, batch_code, current_quantity, initial_quantity, target_weight_kg, current_weight_kg, arrival_date, house_pen, COALESCE(chick_unit_cost, 38.00) as chick_unit_cost FROM batches WHERE status = 'active' ORDER BY batch_code ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// All Houses for reservation selection (with real-time active reservation status & remaining capacity)
$allHouses = $conn->query("
    SELECT h.*, 
           (SELECT COUNT(*) FROM reservations r WHERE r.house_id = h.house_id AND r.status = 'active') as active_res_count,
           (SELECT COALESCE(SUM(r.quantity_birds), 0) FROM reservations r WHERE r.house_id = h.house_id AND r.status = 'active') as active_res_birds,
           (SELECT COALESCE(SUM(b.current_quantity), 0) FROM batches b WHERE (b.house_pen = h.house_name OR b.batch_id = h.current_batch_id) AND b.status = 'active' AND b.current_quantity > 0) as active_flock_birds,
           (SELECT r.reservation_number FROM reservations r WHERE r.house_id = h.house_id AND r.status = 'active' ORDER BY r.reservation_id DESC LIMIT 1) as active_res_number,
           (SELECT c.name FROM reservations r JOIN customers c ON r.customer_id = c.customer_id WHERE r.house_id = h.house_id AND r.status = 'active' ORDER BY r.reservation_id DESC LIMIT 1) as active_res_customer
    FROM houses h
    ORDER BY h.house_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($allHouses as &$hItem) {
    $hCap = (int)($hItem['capacity'] > 0 ? $hItem['capacity'] : 5000);
    $isOccupied = ($hItem['status'] === 'occupied' || !empty($hItem['current_batch_id']));
    $hasActiveRes = ((int)($hItem['active_res_count'] ?? 0) > 0);
    $isReserved = ($hasActiveRes || $hItem['status'] === 'reserved');
    
    // Strict 1 Integrator per House rule: if reserved or occupied, 0 remaining capacity
    $hItem['committed_birds'] = ($isReserved || $isOccupied) ? $hCap : 0;
    $hItem['remaining_capacity'] = ($isReserved || $isOccupied) ? 0 : $hCap;
}
unset($hItem);

// Active Customers for selection
$customers = $conn->query("SELECT customer_id, customer_code, name, phone, address FROM customers WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// KPI aggregates
if ($isIntegrator) {
    if ($integratorCustomerId > 0) {
        $kpi_stmt = $conn->prepare("
            SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
            FROM reservations r 
            LEFT JOIN batches b ON r.batch_id = b.batch_id 
            WHERE r.status='active' AND (r.customer_id = ? OR r.created_by = ?)
        ");
        $kpi_stmt->execute([$integratorCustomerId, $user_id]);
    } else {
        $kpi_stmt = $conn->prepare("
            SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
            FROM reservations r 
            LEFT JOIN batches b ON r.batch_id = b.batch_id 
            WHERE r.status='active' AND r.created_by = ?
        ");
        $kpi_stmt->execute([$user_id]);
    }
} else {
    $kpi_stmt = $conn->query("
        SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
        FROM reservations r 
        LEFT JOIN batches b ON r.batch_id = b.batch_id 
        WHERE r.status='active'
    ");
}
$kpi_data = $kpi_stmt->fetch(PDO::FETCH_ASSOC);
$kpi_active_count = (int)($kpi_data['active_count'] ?? 0);
$kpi_reserved_birds = (int)($kpi_data['birds'] ?? 0);

include('header.php');
?>

<style>
/* --- Custom Reservation Voucher Styles --- */
.voucher-sheet {
    background-color: #ffffff !important;
    color: #1e293b !important;
    border: 1px solid #e2e8f0;
    border-top: 5px solid #2E5B43;
    border-radius: 16px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.06), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
    padding: 2.25rem 2.5rem;
    position: relative;
}
.voucher-brand-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #2E5B43 0%, #163023 100%) !important;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(46, 91, 67, 0.25);
    flex-shrink: 0;
}
.voucher-badge-number {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 1.2rem;
    font-weight: 700;
    color: #163023;
    letter-spacing: 0.5px;
}
.voucher-info-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
    height: 100%;
}
.voucher-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}
.voucher-table thead th {
    background-color: #244A37 !important;
    color: #ffffff !important;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.75px;
    padding: 12px 16px;
    border: none;
}
.voucher-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    background-color: #ffffff !important;
    font-size: 0.88rem;
    vertical-align: middle;
}
.voucher-table tbody tr:last-child td {
    border-bottom: none;
}
.voucher-summary-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
}
.voucher-sig-line {
    border-bottom: 1.5px solid #334155;
    width: 220px;
    margin: 35px auto 8px auto;
}

/* Compact View Reservation modal */
.view-res-modal .modal-body {
    overflow-x: hidden;
}
.view-res-block {
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    background: rgba(15, 40, 28, 0.45);
    border: 1px solid rgba(61, 255, 154, 0.12);
}
.view-res-k {
    display: block;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #8fa89a;
    margin-bottom: 0.15rem;
}
.view-res-v {
    font-size: 1.05rem;
    line-height: 1.25;
    word-break: break-word;
}
.view-res-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.85rem;
    margin-top: 0.25rem;
    font-size: 0.78rem;
    color: #8fa89a;
}
.view-res-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.35rem 0.65rem;
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    background: rgba(15, 40, 28, 0.45);
    border: 1px solid rgba(61, 255, 154, 0.12);
}
.view-res-row {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.view-res-row > span {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #8fa89a;
}
.view-res-row > strong {
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1.25;
    word-break: break-word;
}
.view-res-money {
    grid-template-columns: repeat(3, 1fr);
}
.view-res-notes {
    padding: 0.65rem 0.85rem;
    border-radius: 12px;
    border: 1px solid rgba(61, 255, 154, 0.14);
    font-size: 0.8rem;
    line-height: 1.4;
    color: #c5d8cc;
}
.view-res-notes p {
    margin-top: 0.2rem;
    word-break: break-word;
}
@media (max-width: 420px) {
    .view-res-grid,
    .view-res-money {
        grid-template-columns: 1fr;
    }
}

/* Compact summary reservations list */
.reservations-summary-table {
    --bs-table-cell-padding-y: 0.55rem;
    --bs-table-cell-padding-x: 0.75rem;
    margin-bottom: 0;
}
.reservations-summary-table thead th {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
    vertical-align: middle;
    padding-top: 0.65rem;
    padding-bottom: 0.65rem;
}
.reservations-summary-table tbody td {
    vertical-align: middle;
    font-size: 0.875rem;
}
.reservations-summary-table .mobile-cell-val {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    line-height: 1.25;
}
.reservations-summary-table .badge {
    font-weight: 600;
}

/* Signature Alquizar Theme Alerts & Messages */
.alert-success {
    background: linear-gradient(135deg, #F0F7F2 0%, #E3EFE7 100%) !important;
    border: 1px solid #A3D4B6 !important;
    border-left: 5px solid #2E5B43 !important;
    color: #163023 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 12px rgba(46, 91, 67, 0.06);
}
.alert-warning {
    background: linear-gradient(135deg, #FCF9EE 0%, #F8EED4 100%) !important;
    border: 1px solid #E6CE94 !important;
    border-left: 5px solid #C29A4B !important;
    color: #4A3305 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 12px rgba(194, 154, 75, 0.08);
}
.alert-danger {
    background: linear-gradient(135deg, #FDF4F2 0%, #F9E4E0 100%) !important;
    border: 1px solid #E8ADA5 !important;
    border-left: 5px solid #B03A2E !important;
    color: #4D1610 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 12px rgba(176, 58, 46, 0.06);
}
.alert-info {
    background: linear-gradient(135deg, #F4F6F4 0%, #E9ECE9 100%) !important;
    border: 1px solid #CAD5CA !important;
    border-left: 5px solid #2E5B43 !important;
    color: #1E2B23 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 12px rgba(46, 91, 67, 0.05);
}

/* Responsive Mobile Table Cards Transformation for Reservations */
@media (max-width: 768px) {
    .table-mobile-cards {
        display: block !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        background: transparent !important;
        border: none !important;
    }
    .table-mobile-cards thead {
        display: none !important;
    }
    .table-mobile-cards tbody,
    .table-mobile-cards tbody tr {
        display: block !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .table-mobile-cards tbody tr {
        background: #ffffff !important;
        border: 1px solid #E2E8F0 !important;
        border-radius: 16px !important;
        padding: 14px 16px !important;
        margin-bottom: 16px !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04) !important;
    }
    .table-mobile-cards tbody tr:hover,
    .table-mobile-cards tbody tr:active,
    .table-mobile-cards tbody tr:focus {
        background: #ffffff !important;
    }
    .table-mobile-cards tbody tr:hover td,
    .table-mobile-cards tbody tr:active td,
    .table-mobile-cards tbody tr:focus td,
    .table-mobile-cards tbody tr td {
        background: transparent !important;
        background-color: transparent !important;
        --bs-table-bg-hover: transparent !important;
        --bs-table-accent-bg: transparent !important;
        --bs-table-bg: transparent !important;
    }
    .table-mobile-cards tbody tr td {
        display: flex !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        padding: 8px 0 !important;
        border: none !important;
        border-bottom: 1px solid #F1F5F9 !important;
        font-size: 0.86rem !important;
        text-align: right !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .table-mobile-cards tbody tr td:first-child {
        border-bottom: 1px dashed #CBD5E1 !important;
        padding-bottom: 10px !important;
        margin-bottom: 6px !important;
    }
    .table-mobile-cards tbody tr td:last-child {
        border-bottom: none !important;
        border-top: 1px solid #E2E8F0 !important;
        padding-top: 12px !important;
        margin-top: 8px !important;
        justify-content: center !important;
    }
    .table-mobile-cards tbody tr td::before {
        content: attr(data-label);
        font-weight: 700 !important;
        color: #475569 !important;
        font-size: 0.74rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.4px !important;
        margin-right: 12px !important;
        text-align: left !important;
        flex-shrink: 0 !important;
        max-width: 44% !important;
        padding-top: 2px !important;
    }
    .table-mobile-cards tbody tr td:last-child::before {
        display: none !important;
    }
    .table-mobile-cards tbody tr td .mobile-cell-val {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-end !important;
        text-align: right !important;
        gap: 3px !important;
        max-width: 56% !important;
        flex-grow: 1 !important;
        word-break: break-word !important;
    }
    .table-mobile-cards-wrap {
        overflow-x: hidden !important;
        overflow-y: visible !important;
        max-height: none !important;
        width: 100% !important;
    }
}
@media (min-width: 769px) {
    .table-mobile-cards {
        min-width: 1040px;
    }
    .table-mobile-cards tbody tr td .mobile-cell-val {
        display: contents !important;
    }
}
/* Mobile-responsive Sequential Pipeline & Prerequisite Rule Banner */
.pipeline-rule-banner {
    white-space: normal !important;
    word-break: break-word !important;
    line-height: 1.35 !important;
    max-width: 100% !important;
    display: inline-flex !important;
    align-items: center !important;
}
.house-card-mobile {
    overflow: hidden !important;
    word-break: break-word !important;
}
/* Ensure alert text inside modals and cards wraps cleanly and never overflows */
.alert {
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
    max-width: 100% !important;
}
.alert strong, .alert span, .alert div {
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
}
.pipeline-board {
    background: rgba(0, 0, 0, 0.28);
    border-color: rgba(61, 255, 154, 0.16) !important;
}
.pipeline-rule-banner {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(61, 255, 154, 0.08);
    border: 1px solid rgba(61, 255, 154, 0.22);
    color: #c5d8cc;
}
.pipeline-step-card {
    background: rgba(8, 22, 16, 0.72);
    border-color: rgba(61, 255, 154, 0.18) !important;
}
.pipeline-step-title { color: #eef7f1; }
.pipeline-step-sub { color: #8fa89a; }
.pipeline-role-badge {
    background: rgba(61, 255, 154, 0.1);
    border: 1px solid rgba(61, 255, 154, 0.22);
    color: #3dff9a;
}
@media (max-width: 575.98px) {
    .pipeline-rule-banner {
        font-size: 0.68rem !important;
        padding: 4px 10px !important;
        width: 100% !important;
        justify-content: center !important;
    }
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-3 p-md-4">
                <!-- Page Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <?php if ($isIntegrator): ?>
                        <h4 class="fw-bold mb-1 fs-5 fs-md-4"><i class="fas fa-warehouse me-2 text-success"></i>Poultry House Availability & Pen Reservation</h4>
                        <p class="text-muted small mb-0">Check available house capacity in real-time and reserve vacant pens for incoming contract broiler flocks.</p>
                        <?php elseif (strtolower($user_role) === 'worker'): ?>
                        <h4 class="fw-bold mb-1 fs-5 fs-md-4"><i class="fas fa-broom me-2 text-warning"></i>House Cleaning & Pen Sanitation Operations</h4>
                        <p class="text-muted small mb-0">Worker Operations: Review house sanitation status, initiate washing, and mark cleaned pens ready for chick intake.</p>
                        <?php else: ?>
                        <h4 class="fw-bold mb-1 fs-5 fs-md-4"><i class="fas fa-calendar-check me-2 text-success"></i>Broiler Chicken Reservation Management</h4>
                        <p class="text-muted small mb-0">Manage commercial customer bookings, track advance payments & balances, and record pickup fulfillment.</p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                        <?php if (strtolower($user_role) === 'admin'): ?>
                        <a href="#housesAvailabilityBoard" class="btn btn-outline-success rounded-pill px-3 fw-semibold w-100 w-sm-auto text-center">
                            <i class="fas fa-warehouse me-1"></i>View Pen Availability Board
                        </a>
                        <?php elseif ($isIntegrator): ?>
                        <button type="button" class="btn btn-success rounded-pill px-4 fw-semibold shadow-sm w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#newBroilerReservationModal">
                            <i class="fas fa-calendar-plus me-1"></i>Reserve a Vacant House (Step 1)
                        </button>
                        <a href="#housesAvailabilityBoard" class="btn btn-outline-success rounded-pill px-3 fw-semibold w-100 w-sm-auto text-center">
                            <i class="fas fa-warehouse me-1"></i>Check Vacant Houses Below
                        </a>
                        <?php elseif (strtolower($user_role) === 'worker'): ?>
                        <a href="#housesAvailabilityBoard" class="btn btn-warning text-dark rounded-pill px-3 fw-semibold shadow-sm w-100 w-sm-auto text-center">
                            <i class="fas fa-broom me-1"></i>Go to House Cleaning Board
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Global Flash / Alert Notifications (Top of page) -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4 rounded-3 shadow-sm" role="alert">
                        <i class="fas fa-check-circle me-2 fs-5 align-middle"></i><strong>Success:</strong> <?php echo e($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4 rounded-3 shadow-sm" role="alert">
                        <i class="fas fa-exclamation-triangle me-2 fs-5 align-middle"></i><strong>Notice:</strong> <?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- KPI Cards (Mobile-friendly side-by-side grid) -->
                <div class="row g-2 g-md-3 mb-4">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-4 border h-100 kpi-mobile-card">
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;"><?php echo $isIntegrator ? 'Your Active Bookings' : 'Active Bookings'; ?></div>
                            <div class="fs-4 fw-bold text-success mt-1"><?php echo number_format($kpi_active_count); ?></div>
                            <small class="text-muted d-none d-sm-block"><?php echo $isIntegrator ? 'Your company bookings' : 'Awaiting pickup'; ?></small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-4 border h-100 kpi-mobile-card">
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;"><?php echo $isIntegrator ? 'Your Reserved Birds' : 'Reserved Birds'; ?></div>
                            <div class="fs-4 fw-bold text-primary mt-1"><?php echo number_format($kpi_reserved_birds); ?> <span class="fs-6 fw-normal text-muted">birds</span></div>
                            <small class="text-muted d-none d-sm-block"><?php echo $isIntegrator ? 'Allocated to contract' : 'Live broilers held'; ?></small>
                        </div>
                    </div>
                </div>

                <!-- Poultry House & Pen Availability Board (Customer & Integrator Portal) -->
                <?php
                $renderedIntegratorModals = [];

                if (!function_exists('renderIntegratorPickupModalHTML')) {
                /**
                 * Render Integrator Pick Up & Full Payment Enforcement Modal
                 */
                function renderIntegratorPickupModalHTML($res, $readiness = null, $houseName = '') {
                    $resId = (int)$res['reservation_id'];
                    $totAmt = (float)($res['total_amount'] ?? 0);
                    $advAmt = (float)($res['advance_payment'] ?? 0);
                    $balance = max(0, round($totAmt - $advAmt, 2));
                    $isFullyPaid = ($balance <= 0.001);
                    $targetWeight = !empty($res['target_weight_per_bird']) ? (float)$res['target_weight_per_bird'] : 1.80;
                    $defaultDr = "DR-" . date('Ymd') . "-" . str_pad($resId, 4, '0', STR_PAD_LEFT);
                    $isReady = ($readiness && !empty($readiness['ready']));

                    ob_start();
                    ?>
                    <!-- MODAL: Integrator Pick Up & Payment Verification Gate (#<?php echo $resId; ?>) -->
                    <div class="modal fade text-start" id="integratorPickupModal<?php echo $resId; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down modal-lg">
                            <div class="modal-content border-0 rounded-4 shadow bg-white">
                                <div class="modal-header border-0 rounded-top-4 text-white" style="background: linear-gradient(135deg, #163023 0%, #2E5B43 100%);">
                                    <h5 class="modal-title fw-bold">
                                        <i class="fas fa-truck-fast me-2 text-warning" style="color: #C29A4B !important;"></i>Flock Pick Up & Hauling Clearance
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-3 p-md-4 bg-white">
                                    <!-- Reservation & Flock Overview -->
                                    <div class="p-3 bg-light rounded-3 mb-3 border">
                                        <div class="row g-2">
                                            <div class="col-6 col-md-3">
                                                <span class="text-muted small d-block">Reservation #:</span>
                                                <span class="fw-bold font-monospace text-primary"><?php echo e($res['reservation_number']); ?></span>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <span class="text-muted small d-block">Pen / Location:</span>
                                                <span class="fw-bold text-dark"><i class="fas fa-warehouse text-success me-1"></i><?php echo e($houseName ?: ($res['house_name'] ?? 'Pen')); ?></span>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <span class="text-muted small d-block">Chick Breed:</span>
                                                <span class="fw-bold text-dark font-monospace"><i class="fas fa-feather text-primary me-1"></i><?php echo e($res['breed'] ?? 'Cobb 500'); ?></span>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <span class="text-muted small d-block">Booked Birds:</span>
                                                <span class="fw-bold text-dark"><?php echo number_format((int)($res['quantity_birds'] ?? $res['display_quantity'] ?? 0)); ?> heads</span>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <span class="text-muted small d-block">Target Weight:</span>
                                                <span class="fw-bold text-success font-monospace"><?php echo number_format($targetWeight, 2); ?> kg/bird</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Agreement Timeline & Flock Weight Standards Card -->
                                    <div class="card border rounded-3 mb-3 shadow-none bg-white">
                                        <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small text-dark"><i class="fas fa-clipboard-check me-1 text-success"></i>Agreement Timeline & Standards</span>
                                            <?php if ($readiness && !empty($readiness['ready'])): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-1"><i class="fas fa-check-double me-1"></i>Standards Complete</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1"><i class="fas fa-hourglass-half me-1"></i>In Growing Cycle</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="row g-2 text-center">
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Agreed Pickup Date</span>
                                                    <div class="fw-bold font-monospace fs-6 text-dark"><?php echo !empty($res['pickup_date']) ? date('M d, Y', strtotime($res['pickup_date'])) : 'Pending'; ?></div>
                                                    <small class="d-block text-muted">
                                                        <?php 
                                                        $todayStr = date('Y-m-d');
                                                        if (!empty($res['pickup_date'])) {
                                                            if ($todayStr >= $res['pickup_date']) {
                                                                echo '<span class="text-success fw-semibold"><i class="fas fa-check me-1"></i>Date Reached</span>';
                                                            } else {
                                                                $daysLeft = (int)round((strtotime($res['pickup_date']) - strtotime($todayStr)) / 86400);
                                                                echo '<span class="text-warning fw-semibold"><i class="fas fa-clock me-1"></i>' . $daysLeft . ' days left</span>';
                                                            }
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Agreed Target Weight</span>
                                                    <div class="fw-bold font-monospace fs-6 text-dark"><?php echo number_format($targetWeight, 2); ?> kg/bird</div>
                                                    <small class="d-block text-muted">Contract Standard</small>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Live Sample Weight</span>
                                                    <div class="fw-bold font-monospace fs-6 <?php echo ($readiness && !empty($readiness['is_weight_met'])) ? 'text-success' : 'text-primary'; ?>">
                                                        <?php echo $readiness ? number_format($readiness['current_weight'], 2) : '2.20'; ?> kg/bird
                                                    </div>
                                                    <small class="d-block text-muted">
                                                        <?php if ($readiness && !empty($readiness['is_weight_met'])): ?>
                                                            <span class="text-success fw-semibold"><i class="fas fa-check me-1"></i>Weight Met</span>
                                                        <?php else: ?>
                                                            <span class="text-warning fw-semibold"><i class="fas fa-arrow-up me-1"></i>Under Target</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Admin Dispatch</span>
                                                    <div class="fw-bold fs-6 <?php echo !empty($res['is_dispatched']) ? 'text-success' : 'text-muted'; ?>">
                                                        <?php echo !empty($res['is_dispatched']) ? 'Authorized' : 'Pending'; ?>
                                                    </div>
                                                    <small class="d-block text-muted">
                                                        <?php if (!empty($res['is_dispatched'])): ?>
                                                            <span class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>Dispatched</span>
                                                        <?php else: ?>
                                                            <span class="text-secondary"><i class="fas fa-lock me-1"></i>Awaiting Admin</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php if ($readiness && empty($readiness['ready'])): ?>
                                                <div class="mt-2 p-2 bg-light rounded-2 small text-muted text-center border">
                                                    <i class="fas fa-info-circle text-warning me-1"></i><?php echo htmlspecialchars($readiness['message']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Payment Status & Balance Breakdown -->
                                    <div class="card border rounded-3 mb-3 shadow-none bg-white">
                                        <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small text-dark"><i class="fas fa-file-invoice-dollar me-1 text-primary"></i>Contract Payment Status</span>
                                            <?php if ($isFullyPaid): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-1"><i class="fas fa-check-circle me-1"></i>100% Fully Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fas fa-lock me-1"></i>Balance Due: ₱<?php echo number_format($balance, 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="row g-2 text-center">
                                                <div class="col-4">
                                                    <span class="text-muted small d-block">Total Contract</span>
                                                    <div class="fw-bold font-monospace fs-6 text-dark">₱<?php echo number_format($totAmt, 2); ?></div>
                                                </div>
                                                <div class="col-4">
                                                    <span class="text-muted small d-block">Advance Paid</span>
                                                    <div class="fw-bold font-monospace fs-6 text-success">₱<?php echo number_format($advAmt, 2); ?></div>
                                                </div>
                                                <div class="col-4">
                                                    <span class="text-muted small d-block">Remaining Balance</span>
                                                    <div class="fw-bold font-monospace fs-6 <?php echo $isFullyPaid ? 'text-success' : 'text-danger'; ?>">
                                                        ₱<?php echo number_format($balance, 2); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!$isFullyPaid): ?>
                                        <!-- PAYMENT GATE: LOCKED -->
                                        <div class="alert alert-danger rounded-3 p-3 mb-3 border-0">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fas fa-shield-halved fs-3 text-danger flex-shrink-0 mt-1"></i>
                                                <div>
                                                    <h6 class="fw-bold mb-1 text-danger">Pick Up Gate Locked: Full Payment Required</h6>
                                                    <p class="small mb-0">
                                                        Chickens cannot be released or picked up until the contract is <strong>100% fully paid</strong>. An outstanding balance of <strong>₱<?php echo number_format($balance, 2); ?></strong> must be settled before gate clearance pass can be issued.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Settle Payment Form -->
                                        <div class="p-3 bg-light rounded-3 border mb-3">
                                            <h6 class="fw-bold text-dark mb-2">
                                                <i class="fas fa-credit-card text-primary me-2"></i>Settle Remaining Balance to Unlock Pick Up
                                            </h6>
                                            <form method="POST" class="bg-transparent">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                                <div class="row g-2 mb-3">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Payment Amount (₱) <span class="text-danger">*</span></label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-white fw-bold">₱</span>
                                                            <input type="number" step="0.01" min="1" max="<?php echo $balance; ?>" name="payment_amount" class="form-control fw-bold font-monospace text-success" value="<?php echo number_format($balance, 2, '.', ''); ?>" required>
                                                        </div>
                                                        <small class="text-muted">Remaining balance: ₱<?php echo number_format($balance, 2); ?></small>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Payment Method <span class="text-danger">*</span></label>
                                                        <select name="payment_method" class="form-select" required>
                                                            <option value="Bank Transfer (Online)">Bank Transfer (Online BDO / BPI / Metrobank)</option>
                                                            <option value="GCash / Maya">GCash / Maya (E-Wallet)</option>
                                                            <option value="Company Cheque">Company Cheque</option>
                                                            <option value="Cash Deposit / Cashier">Cash Deposit / Farm Cashier</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Reference / Transaction / Deposit # <span class="text-danger">*</span></label>
                                                        <input type="text" name="payment_reference" class="form-control font-monospace" placeholder="e.g., TRX-928471 or Cheque #10294" required>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Payment Notes / Remarks</label>
                                                        <input type="text" name="payment_notes" class="form-control" placeholder="e.g., Final contract settlement">
                                                    </div>
                                                </div>
                                                <button type="submit" name="settle_reservation_balance" class="btn btn-primary fw-bold rounded-pill w-100 py-2 shadow-sm">
                                                    <i class="fas fa-money-bill-wave me-1"></i>Pay & Settle Remaining Balance (₱<?php echo number_format($balance, 2); ?>)
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Locked Hauling Clearance Notice -->
                                        <div class="p-3 bg-white rounded-3 border text-center text-muted">
                                            <i class="fas fa-lock fs-4 text-secondary mb-1 d-block"></i>
                                            <span class="fw-semibold small">Hauler & Truck Clearance Form is Locked</span>
                                            <p class="small mb-0 text-muted">Submitting full payment above unlocks hauler truck dispatch and security gate clearance.</p>
                                        </div>

                                    <?php elseif (empty($res['is_dispatched'])): ?>
                                        <!-- PAYMENT CLEARED BUT AWAITING ADMIN DISPATCH: CANNOT PICK UP DAYON -->
                                        <div class="alert alert-warning rounded-3 p-3 mb-3 border-0">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fas fa-hourglass-half fs-3 text-warning flex-shrink-0 mt-1"></i>
                                                <div>
                                                    <h6 class="fw-bold mb-1 text-dark">Payment Verified (₱0.00 Balance) &bull; Awaiting Farm Admin Dispatch Clearance</h6>
                                                    <p class="small mb-1 text-muted">
                                                        Your contract balance is 100% paid. However, according to farm agreement protocol, you <strong>cannot pick up immediately ("cannot pick up dayon")</strong>. The <strong>Farm Admin</strong> must first inspect the flock, verify full payment, and officially dispatch the chicks.
                                                    </p>
                                                    <div class="small fw-semibold text-dark">
                                                        <i class="fas fa-info-circle text-primary me-1"></i>Once the Farm Admin executes the flock dispatch, this hauler pick up and gate clearance form will unlock automatically.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Locked Hauling Clearance Notice -->
                                        <div class="p-3 bg-light rounded-3 border text-center text-muted">
                                            <i class="fas fa-truck-clock fs-3 text-secondary mb-2 d-block"></i>
                                            <span class="fw-semibold small d-block text-dark">Hauler Truck Dispatch Form on Hold</span>
                                            <p class="small mb-0 text-muted">Awaiting Admin Official Flock Dispatch Confirmation</p>
                                        </div>

                                    <?php else: ?>
                                        <!-- PAYMENT GATE: 100% FULLY PAID & OFFICIALLY DISPATCHED BY ADMIN -->
                                        <div class="alert alert-success rounded-3 p-3 mb-3 border-0">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fas fa-circle-check fs-3 text-success flex-shrink-0 mt-1"></i>
                                                <div>
                                                    <h6 class="fw-bold mb-1 text-success">Flock Officially Dispatched by Farm Admin &bull; Cleared for Pick Up!</h6>
                                                    <p class="small mb-0">
                                                        Dispatched on <strong><?php echo date('M d, Y h:i A', strtotime($res['dispatched_at'])); ?></strong>. Contract payments are 100% verified (₱0.00 balance). Enter your hauler truck and driver dispatch details below to authorize pickup and generate your gate clearance pass.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Pickup Authorization Form -->
                                        <form method="POST" class="bg-transparent">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                            <div class="p-3 bg-light rounded-3 border mb-3">
                                                <h6 class="fw-bold text-dark mb-2">
                                                    <i class="fas fa-truck-pickup text-success me-2"></i>Hauling Dispatch & Gate Pass Authorization
                                                </h6>
                                                <div class="row g-2 mb-2">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Pick Up / Hauling Date <span class="text-danger">*</span></label>
                                                        <input type="date" name="actual_pickup_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Delivery Receipt / Gate Pass #</label>
                                                        <input type="text" name="receipt_number" class="form-control font-monospace bg-light" value="<?php echo $defaultDr; ?>" readonly>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label fw-bold small">Hauler Truck Plate & Driver Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="hauler_info" class="form-control" placeholder="e.g., Truck Plate ABC-1234 / Driver: Juan Dela Cruz (0917-xxx-xxxx)" required>
                                                        <small class="text-muted">Security gate pass will verify truck plate and driver ID upon arrival at the farm</small>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label fw-bold small">Logistics & Crate Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g., 200 plastic poultry crates, weighing supervised by company inspector..."></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="integrator_confirm_pickup" class="btn btn-success fw-bold rounded-pill flex-fill py-2 shadow-sm">
                                                    <i class="fas fa-truck-fast me-2"></i>Confirm & Authorize Pick Up
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer border-0 bg-white">
                                    <button type="button" class="btn btn-secondary rounded-pill px-4 w-100 w-sm-auto" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    return ob_get_clean();
                }
                }

                $renderedSettleModals = [];

                if (!function_exists('renderManualSettlementModalHTML')) {
                /**
                 * Render Manual Payment Settlement Modal (Integrator Only)
                 */
                function renderManualSettlementModalHTML($res) {
                    global $isIntegrator;
                    if (!$isIntegrator) {
                        return '';
                    }
                    $resId = (int)$res['reservation_id'];
                    $totAmt = (float)($res['total_amount'] ?? 0);
                    $advAmt = (float)($res['advance_payment'] ?? 0);
                    $balance = max(0, round($totAmt - $advAmt, 2));
                    $isFullyPaid = ($balance <= 0.001);
                    $custName = !empty($res['customer_name']) ? $res['customer_name'] : 'Customer/Partner';
                    $resNum = !empty($res['reservation_number']) ? $res['reservation_number'] : ('RES-' . $resId);
                    $qty = (int)($res['quantity_birds'] ?? $res['display_quantity'] ?? 0);

                    ob_start();
                    ?>
                    <!-- MODAL: Manual Payment Settlement (#<?php echo $resId; ?>) -->
                    <div class="modal fade text-start" id="settleResModal<?php echo $resId; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content border-0 rounded-4 shadow bg-white">
                                <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                                    <h5 class="modal-title fw-bold">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>Manual Payment Settlement
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" class="bg-transparent">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                    <div class="modal-body p-3 p-md-4">
                                        <!-- Summary -->
                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                            <div class="row g-2">
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Reservation #:</span>
                                                    <span class="fw-bold font-monospace text-primary"><?php echo e($resNum); ?></span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Customer / Partner:</span>
                                                    <span class="fw-bold text-dark"><?php echo e($custName); ?></span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Reserved Birds:</span>
                                                    <span class="fw-bold text-dark"><?php echo number_format($qty); ?> heads</span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Total Contract:</span>
                                                    <span class="fw-bold font-monospace text-dark">₱<?php echo number_format($totAmt, 2); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Balance breakdown -->
                                        <div class="card border rounded-3 mb-3 shadow-none bg-white">
                                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                                <span class="fw-bold small text-dark"><i class="fas fa-coins me-1 text-primary"></i>Contract Balance Breakdown</span>
                                                <?php if ($isFullyPaid): ?>
                                                    <span class="badge bg-success rounded-pill px-3 py-1"><i class="fas fa-check-circle me-1"></i>100% Fully Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fas fa-lock me-1"></i>Remaining Balance: ₱<?php echo number_format($balance, 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="row g-2 text-center">
                                                    <div class="col-4">
                                                        <span class="text-muted small d-block">Total Contract</span>
                                                        <div class="fw-bold font-monospace fs-6 text-dark">₱<?php echo number_format($totAmt, 2); ?></div>
                                                    </div>
                                                    <div class="col-4">
                                                        <span class="text-muted small d-block">Advance Paid</span>
                                                        <div class="fw-bold font-monospace fs-6 text-success">₱<?php echo number_format($advAmt, 2); ?></div>
                                                    </div>
                                                    <div class="col-4">
                                                        <span class="text-muted small d-block">Remaining Balance</span>
                                                        <div class="fw-bold font-monospace fs-6 <?php echo $isFullyPaid ? 'text-success' : 'text-danger'; ?>">
                                                            ₱<?php echo number_format($balance, 2); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($isFullyPaid): ?>
                                            <div class="alert alert-success rounded-3 p-3 mb-0 border-0">
                                                <i class="fas fa-check-circle me-2 fs-5"></i>This contract is already <strong>100% fully paid</strong> (₱0.00 remaining balance). Ready for pickup and dispatch.
                                            </div>
                                        <?php else: ?>
                                            <div class="p-3 bg-light rounded-3 border mb-0">
                                                <h6 class="fw-bold text-dark mb-2">
                                                    <i class="fas fa-credit-card text-primary me-2"></i>Record Manual Payment Settlement
                                                </h6>
                                                <p class="text-muted small mb-3">Record final or partial payment details provided by integrator / customer. Full payment is required before pickup clearance.</p>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Payment Amount (₱) <span class="text-danger">*</span></label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-white fw-bold">₱</span>
                                                            <input type="number" step="0.01" min="1" max="<?php echo $balance; ?>" name="payment_amount" class="form-control fw-bold font-monospace text-success" value="<?php echo number_format($balance, 2, '.', ''); ?>" required>
                                                        </div>
                                                        <small class="text-muted">Remaining balance: ₱<?php echo number_format($balance, 2); ?></small>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Payment Method <span class="text-danger">*</span></label>
                                                        <select name="payment_method" class="form-select" required>
                                                            <option value="Bank Transfer (Online)">Bank Transfer (Online BDO / BPI / Metrobank)</option>
                                                            <option value="GCash / Maya">GCash / Maya (E-Wallet)</option>
                                                            <option value="Company Cheque">Company Cheque</option>
                                                            <option value="Cash Deposit / Cashier">Cash Deposit / Farm Cashier</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Reference / Cheque / Dep # <span class="text-danger">*</span></label>
                                                        <input type="text" name="payment_reference" class="form-control font-monospace" placeholder="e.g., TRX-928471 or Cheque #10294" required>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-bold small">Payment Notes / Remarks</label>
                                                        <input type="text" name="payment_notes" class="form-control" placeholder="e.g., Settlement prior to truck pickup">
                                                    </div>
                                                </div>
                                                <button type="submit" name="settle_reservation_balance" class="btn btn-primary fw-bold rounded-pill w-100 py-2 shadow-sm">
                                                    <i class="fas fa-money-bill-wave me-1"></i>Confirm & Settle Balance (₱<?php echo number_format($balance, 2); ?>)
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                    return ob_get_clean();
                }
                }

                $renderedReviewModals = [];
                $renderedVerifyModals = [];

                if (!function_exists('renderStep2ReviewModalHTML')) {
                /**
                 * Render Step 2 Admin Feasibility Verification & Approval Modal
                 */
                function renderStep2ReviewModalHTML($r, $conn) {
                    $resId = (int)$r['reservation_id'];
                    $reqBirds = (int)(!empty($r['display_quantity']) ? $r['display_quantity'] : ($r['quantity_birds'] ?? 0));
                    $houseId = (int)($r['house_id'] ?? 0);
                    $houseName = !empty($r['house_name']) ? $r['house_name'] : ('House ' . $houseId);
                    $houseCap = (int)(!empty($r['house_capacity']) && $r['house_capacity'] > 0 ? $r['house_capacity'] : 5000);
                    $occupiedBirds = 0;
                    if ($houseId > 0) {
                        try {
                            $stmtOcc = $conn->prepare("
                                SELECT 
                                    COALESCE((SELECT SUM(quantity_birds) FROM reservations WHERE house_id = ? AND status = 'active' AND reservation_id != ?), 0) as reserved_birds,
                                    COALESCE((SELECT SUM(current_quantity) FROM batches WHERE (house_pen = ? OR batch_id = (SELECT current_batch_id FROM houses WHERE house_id = ?)) AND status = 'active' AND current_quantity > 0), 0) as flock_birds
                            ");
                            $stmtOcc->execute([$houseId, $resId, $houseName, $houseId]);
                            $occRow = $stmtOcc->fetch(PDO::FETCH_ASSOC);
                            $occupiedBirds = max((int)($occRow['reserved_birds'] ?? 0), (int)($occRow['flock_birds'] ?? 0));
                        } catch (\Exception $exOcc) {}
                    }
                    $availCap = max(0, $houseCap - $occupiedBirds);
                    $capacityPass = ($reqBirds <= $availCap);

                    $starterFeedNeededKg = round($reqBirds * 0.15);
                    $feedStockKg = 0;
                    $feedItemDetail = '';
                    try {
                        // Check dedicated Starter Crumble first
                        $starterRows = $conn->query("
                            SELECT item_name, quantity, unit,
                            (CASE WHEN LOWER(unit) IN ('bags', 'sacks') THEN quantity * 50 ELSE quantity END) as in_kg
                            FROM inventory 
                            WHERE (LOWER(category) LIKE '%feed%' OR LOWER(item_name) LIKE '%feed%')
                              AND (LOWER(item_name) LIKE '%starter%' OR LOWER(item_name) LIKE '%crumble%')
                        ")->fetchAll(PDO::FETCH_ASSOC);

                        $starterSum = 0;
                        foreach ($starterRows as $srow) {
                            $starterSum += (float)$srow['in_kg'];
                        }

                        if ($starterSum > 0) {
                            $feedStockKg = $starterSum;
                            $feedItemDetail = $starterRows[0]['item_name'] ?? 'Broiler Starter Crumble';
                        } else {
                            $feedRows = $conn->query("
                                SELECT item_name, quantity, unit,
                                (CASE WHEN LOWER(unit) IN ('bags', 'sacks') THEN quantity * 50 ELSE quantity END) as in_kg
                                FROM inventory 
                                WHERE (LOWER(category) LIKE '%feed%' OR LOWER(item_name) LIKE '%feed%')
                                  AND (LOWER(item_name) LIKE '%chick%')
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            
                            $chickSum = 0;
                            foreach ($feedRows as $frow) {
                                $chickSum += (float)$frow['in_kg'];
                            }
                            if ($chickSum > 0) {
                                $feedStockKg = $chickSum;
                                $feedItemDetail = $feedRows[0]['item_name'] ?? 'Chick Feed';
                            }
                        }
                    } catch (\Exception $exF) {}
                    $feedPass = ($feedStockKg >= $starterFeedNeededKg && $feedStockKg > 0);

                    $activeWorkersCount = 0;
                    try {
                        $activeWorkersCount = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role IN ('worker', 'staff') AND is_active = 1")->fetchColumn();
                    } catch (\Exception $exW) {}
                    $staffPass = ($activeWorkersCount > 0);

                    $deliveryDate = !empty($r['pickup_date']) ? $r['pickup_date'] : date('Y-m-d');
                    $schedulePass = ($deliveryDate >= date('Y-m-d'));

                    ob_start();
                    ?>
                    <!-- MODAL: Step 2 Admin Reservation Review & Verification Checks (#<?php echo $resId; ?>) -->
                    <div class="modal fade text-start" id="reviewReservationModal<?php echo $resId; ?>" tabindex="-1" aria-labelledby="reviewResLabel<?php echo $resId; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down modal-lg">
                            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden bg-white">
                                <div class="modal-header border-0 rounded-top-4 text-white p-3 p-md-4" style="background: linear-gradient(135deg, #123524 0%, #1c4a33 100%);">
                                    <div class="d-flex align-items-center gap-3">
                                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(242, 182, 50, 0.2); color: #f2b632; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                            <i class="fas fa-clipboard-check"></i>
                                        </div>
                                        <div>
                                            <h5 class="modal-title fw-bold mb-0 text-white" id="reviewResLabel<?php echo $resId; ?>" style="font-family: 'Bricolage Grotesque', sans-serif;">
                                                Step 2: Admin Reservation Review &amp; Verification
                                            </h5>
                                            <small class="text-white-50">Reservation #<?php echo e($r['reservation_number']); ?> &bull; <?php echo e($r['customer_name'] ?? 'Integrator'); ?></small>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body p-3 p-md-4">
                                    <!-- Step-by-Step Prerequisite Flow Banner -->
                                    <div class="p-2 p-md-3 mb-3 rounded-3 border" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);">
                                        <div class="row g-2 align-items-stretch text-start">
                                            <!-- Step 1: Completed -->
                                            <div class="col-12 col-md-4">
                                                <div class="p-2 rounded-3 bg-white border border-success-subtle h-100 d-flex align-items-center gap-2 shadow-sm">
                                                    <span class="badge bg-success text-white rounded-circle p-1 flex-shrink-0" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">
                                                        <i class="fas fa-check"></i>
                                                    </span>
                                                    <div class="min-w-0 flex-grow-1 overflow-hidden">
                                                        <div class="fw-bold text-success text-truncate" style="font-size: 0.76rem;">
                                                            <i class="fas fa-check-circle me-1"></i>Step 1: Submitted
                                                        </div>
                                                        <div class="text-muted text-truncate" style="font-size: 0.69rem;" title="<?php echo e($r['customer_name'] ?? 'Integrator'); ?> (Booked)">
                                                            By <strong><?php echo e($r['customer_name'] ?? 'Integrator'); ?></strong> (Booked)
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Step 2: Active (Current Action) -->
                                            <div class="col-12 col-md-4">
                                                <div class="p-2 rounded-3 bg-warning-subtle border border-warning h-100 d-flex align-items-center gap-2 shadow-sm">
                                                    <span class="badge bg-warning text-dark rounded-circle p-1 flex-shrink-0" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">
                                                        <i class="fas fa-clipboard-check"></i>
                                                    </span>
                                                    <div class="min-w-0 flex-grow-1 overflow-hidden">
                                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.76rem;">
                                                            <i class="fas fa-hourglass-half text-warning me-1"></i>Step 2: Admin Review
                                                        </div>
                                                        <div class="text-dark-emphasis text-truncate" style="font-size: 0.69rem;" title="Active: Feasibility verification">
                                                            <strong>Active:</strong> Feasibility verification
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Step 3: Locked -->
                                            <div class="col-12 col-md-4">
                                                <div class="p-2 rounded-3 bg-light border border-secondary-subtle h-100 d-flex align-items-center gap-2 opacity-75">
                                                    <span class="badge bg-secondary text-white rounded-circle p-1 flex-shrink-0" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                    <div class="min-w-0 flex-grow-1 overflow-hidden">
                                                        <div class="fw-bold text-muted text-truncate" style="font-size: 0.76rem;">
                                                            <i class="fas fa-truck me-1"></i>Step 3: Staff Intake
                                                        </div>
                                                        <div class="text-muted text-truncate" style="font-size: 0.69rem;" title="Locked until Farm Admin approves Step 2">
                                                            Locked until Step 2 approved
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summary Card -->
                                    <div class="p-3 rounded-3 mb-3 border" style="background: #f8fafc;">
                                        <div class="row g-2">
                                            <div class="col-6 col-md-3">
                                                <span class="text-muted small d-block">Reservation Ref:</span>
                                                <span class="fw-bold font-monospace text-primary">#<?php echo e($r['reservation_number']); ?></span>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <span class="text-muted small d-block">Partner / Company:</span>
                                                <span class="fw-bold text-dark"><?php echo e($r['customer_name'] ?? 'Integrator'); ?></span>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <span class="text-muted small d-block">Designated Pen:</span>
                                                <span class="fw-bold text-success"><i class="fas fa-warehouse me-1"></i><?php echo e($houseName); ?></span>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <span class="text-muted small d-block">Requested Chicks:</span>
                                                <span class="fw-bold text-dark"><?php echo number_format($reqBirds); ?> birds (<?php echo e($r['breed'] ?? 'Cobb 500'); ?>)</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 4 Real-Data Verification Checks Panel -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark small text-uppercase mb-2" style="letter-spacing: 0.5px;">
                                            <i class="fas fa-shield-alt text-success me-1"></i>Feasibility Verification Checks Panel
                                        </label>
                                        <div class="row g-2">
                                            <!-- 1. Housing Capacity Check -->
                                            <div class="col-12 col-md-6">
                                                <div class="p-3 rounded-3 border h-100 <?php echo $capacityPass ? 'bg-success-subtle border-success-subtle' : 'bg-warning-subtle border-warning-subtle'; ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <span class="fw-bold small text-dark"><i class="fas fa-warehouse text-success me-1"></i>1. Housing Capacity</span>
                                                        <?php if ($capacityPass): ?>
                                                            <span class="badge bg-success text-white rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-check me-1"></i>PASS</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-exclamation-triangle me-1"></i>CAPACITY ALERT</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted" style="font-size: 0.8rem;">
                                                        Pen Capacity: <strong><?php echo number_format($houseCap); ?></strong> birds &bull; Occupied: <strong><?php echo number_format($occupiedBirds); ?></strong>
                                                    </div>
                                                    <div class="small fw-semibold mt-1 <?php echo $capacityPass ? 'text-success' : 'text-danger'; ?>" style="font-size: 0.8rem;">
                                                        Available Space: <?php echo number_format($availCap); ?> birds (Requested <?php echo number_format($reqBirds); ?>)
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 2. Feed Availability Check -->
                                            <div class="col-12 col-md-6">
                                                <div class="p-3 rounded-3 border h-100 <?php echo $feedPass ? 'bg-success-subtle border-success-subtle' : 'bg-warning-subtle border-warning-subtle'; ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <span class="fw-bold small text-dark"><i class="fas fa-wheat-awn text-warning me-1"></i>2. Feed Stock Availability</span>
                                                        <?php if ($feedPass): ?>
                                                            <span class="badge bg-success text-white rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-check me-1"></i>PASS</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-clock me-1"></i>RE-ORDER DUE</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted" style="font-size: 0.8rem;">
                                                        Starter Need (Day 0-7): <strong>~<?php echo number_format($starterFeedNeededKg); ?> kg</strong>
                                                    </div>
                                                    <div class="small fw-semibold mt-1 <?php echo $feedPass ? 'text-success' : 'text-warning'; ?>" style="font-size: 0.8rem;">
                                                        Inventory In Stock: <strong><?php echo number_format($feedStockKg, 1); ?> kg</strong> on hand
                                                    </div>
                                                    <div class="small mt-1 <?php echo $feedPass ? 'text-success' : 'text-warning'; ?>" style="font-size: 0.74rem; word-break: break-word;">
                                                        <?php if ($feedPass): ?>
                                                            <i class="fas fa-check-circle me-1"></i>Sufficient stock (<?php echo e($feedItemDetail); ?>)
                                                        <?php else: ?>
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Re-order required before delivery
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 3. Staffing Check -->
                                            <div class="col-12 col-md-6">
                                                <div class="p-3 rounded-3 border h-100 <?php echo $staffPass ? 'bg-success-subtle border-success-subtle' : 'bg-danger-subtle border-danger-subtle'; ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <span class="fw-bold small text-dark"><i class="fas fa-users text-primary me-1"></i>3. Staffing &amp; Brooding Hands</span>
                                                        <?php if ($staffPass): ?>
                                                            <span class="badge bg-success text-white rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-check me-1"></i>PASS</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger text-white rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-times me-1"></i>NO WORKERS</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted" style="font-size: 0.8rem;">
                                                        Active Farm Workers: <strong><?php echo $activeWorkersCount; ?> worker(s)</strong> on active duty
                                                    </div>
                                                    <div class="small text-success fw-semibold mt-1" style="font-size: 0.8rem;">
                                                        Pen Sanitization &amp; Heating preparation assigned
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 4. Schedule Check -->
                                            <div class="col-12 col-md-6">
                                                <div class="p-3 rounded-3 border h-100 <?php echo $schedulePass ? 'bg-success-subtle border-success-subtle' : 'bg-warning-subtle border-warning-subtle'; ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <span class="fw-bold small text-dark"><i class="far fa-calendar-check text-info me-1"></i>4. Requested Schedule</span>
                                                        <?php if ($schedulePass): ?>
                                                            <span class="badge bg-success text-white rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-check me-1"></i>PASS</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark rounded-pill px-2" style="font-size: 0.68rem;"><i class="fas fa-clock me-1"></i>DATE PAST</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted" style="font-size: 0.8rem;">
                                                        Target Arrival: <strong><?php echo date('M d, Y', strtotime($deliveryDate)); ?></strong>
                                                    </div>
                                                    <div class="small text-success fw-semibold mt-1" style="font-size: 0.8rem;">
                                                        Standard 35-day grow-out cycle feasible
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Collapsible Rejection Form -->
                                    <div class="collapse mb-2" id="rejectCollapse<?php echo $resId; ?>">
                                        <form method="POST" action="reservations.php" class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="reject_reservation" value="1">
                                            <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                            <label class="form-label fw-bold text-danger small">
                                                <i class="fas fa-exclamation-circle me-1"></i>Mandatory Rejection Reason <span class="text-danger">*</span>
                                            </label>
                                            <textarea name="rejection_reason" class="form-control mb-2" rows="2" placeholder="Specify why this reservation cannot be accepted (e.g. Pen 5 scheduled for bio-security cleaning; schedule conflict)..." required></textarea>
                                            <div class="d-flex justify-content-end gap-2">
                                                <button type="button" class="btn btn-sm btn-light rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#rejectCollapse<?php echo $resId; ?>">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold shadow-sm">
                                                    <i class="fas fa-ban me-1"></i>Confirm Rejection
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="modal-footer border-0 bg-light rounded-bottom-4 d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-danger rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#rejectCollapse<?php echo $resId; ?>" title="Reject with required reason">
                                        <i class="fas fa-times-circle me-1"></i>Reject Reservation
                                    </button>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                        <form method="POST" action="reservations.php" class="d-inline m-0">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="approve_reservation" value="1">
                                            <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                                <i class="fas fa-circle-check me-1"></i>Confirm &amp; Approve
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    return ob_get_clean();
                }
                }

                if (!function_exists('renderStep4VerifyModalHTML')) {
                /**
                 * Render Step 4 & 5 Admin Verify Chick Receipt & Activate Batch Modal
                 */
                function renderStep4VerifyModalHTML($r) {
                    $resId = (int)$r['reservation_id'];
                    $dispQty = (int)(!empty($r['display_quantity']) ? $r['display_quantity'] : ($r['quantity_birds'] ?? 0));
                    $houseName = !empty($r['house_name']) ? $r['house_name'] : 'House 1';
                    
                    ob_start();
                    ?>
                    <!-- MODAL: Verify Chick Receipt & Activate Batch (Step 4 & 5) -->
                    <div class="modal fade text-start" id="verifyReceiptModal<?php echo $resId; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down modal-lg">
                            <div class="modal-content border-0 rounded-4 shadow">
                                <form method="POST" action="reservations.php">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="verify_receipt_and_activate" value="1">
                                    <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                    
                                    <div class="modal-header border-0 rounded-top-4 text-white" style="background: linear-gradient(135deg, #123524 0%, #1c4a33 100%);">
                                        <div>
                                            <h5 class="modal-title fw-bold mb-0">
                                                <i class="fas fa-clipboard-check text-warning me-2"></i>Step 4: Verify Chick Receipt &amp; Advance to Step 5 (Batch Activation)
                                            </h5>
                                            <small class="text-white-50">Reservation #<?php echo e($r['reservation_number']); ?> &bull; <?php echo e($r['customer_name'] ?? 'Integrator'); ?></small>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    
                                    <div class="modal-body p-3 p-md-4">
                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                            <div class="row g-2">
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Reservation Ref:</span>
                                                    <span class="fw-bold font-monospace text-primary">#<?php echo e($r['reservation_number']); ?></span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Partner / Integrator:</span>
                                                    <span class="fw-bold text-dark"><?php echo e($r['customer_name'] ?? 'Integrator'); ?></span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Designated Pen:</span>
                                                    <span class="fw-bold text-success"><i class="fas fa-warehouse me-1"></i><?php echo e($houseName); ?></span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <span class="text-muted small d-block">Contract Reserved:</span>
                                                    <span class="fw-bold text-dark"><?php echo number_format($dispQty); ?> chicks</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold">Accepted Chick Quantity <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="number" name="accepted_quantity" class="form-control" value="<?php echo $dispQty; ?>" min="1" max="<?php echo ($dispQty * 2); ?>" required>
                                                    <span class="input-group-text">birds</span>
                                                </div>
                                                <small class="text-muted">Live chicks healthy and placed into pen.</small>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold">Shortage / Rejected Chicks</label>
                                                <div class="input-group">
                                                    <input type="number" name="rejected_quantity" class="form-control" value="0" min="0">
                                                    <span class="input-group-text">birds</span>
                                                </div>
                                                <small class="text-muted">DOA (Dead on Arrival) or rejected chicks.</small>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold">Delivery Receipt (DR) / Waybill No.</label>
                                                <input type="text" name="receipt_number" class="form-control font-monospace" value="DR-<?php echo date('Ymd'); ?>-<?php echo str_pad($resId, 3, '0', STR_PAD_LEFT); ?>" placeholder="e.g. DR-20261017-001">
                                                <small class="text-muted">Physical delivery receipt number from company delivery driver.</small>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold">Initial Chick Weight</label>
                                                <div class="input-group">
                                                    <input type="number" step="0.001" name="initial_weight" class="form-control" value="0.042" min="0.01" max="0.50">
                                                    <span class="input-group-text">kg / bird</span>
                                                </div>
                                                <small class="text-muted">Average Day-Old Chick intake weight (~40-45g).</small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Intake Inspection Remarks / Chick Condition</label>
                                            <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Chicks active and alert, navels well-healed, brooder pre-heated to 32°C. Placed directly into Pen."></textarea>
                                        </div>

                                        <div class="alert alert-info rounded-3 p-2 px-3 small border-0 mb-0" style="white-space: normal !important; word-break: break-word !important; overflow-wrap: break-word !important; max-width: 100% !important;">
                                            <div class="d-flex align-items-start gap-2" style="white-space: normal !important; word-break: break-word !important;">
                                                <i class="fas fa-info-circle text-success mt-1 flex-shrink-0"></i>
                                                <div class="flex-grow-1" style="white-space: normal !important; word-break: break-word !important; line-height: 1.45; min-width: 0;">
                                                    <strong>Step 5 Transition:</strong> Confirming chick receipt records physical intake parameters and takes you directly to <strong>Step 5 (Batch Registration on batch.php)</strong> where all integrator contract details are automatically populated. As Admin, you will only need to verify the auto-generated Batch Code and note before activating!
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="modal-footer border-0 bg-light rounded-bottom-4">
                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                            <i class="fas fa-arrow-right me-1"></i>Verify Intake &amp; Proceed to Step 5 (Batch Auto-Fill)
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                    return ob_get_clean();
                }
                }

                $renderedViewModals = [];
                $viewResModalBuffer = [];
                if (!function_exists('renderViewReservationModalHTML')) {
                /**
                 * Render View Reservation Details Modal
                 */
                function renderViewReservationModalHTML($r, $user_role = 'admin') {
                    global $conn;
                    $resId = (int)$r['reservation_id'];
                    $dispQty = (int)(!empty($r['display_quantity']) ? $r['display_quantity'] : ($r['quantity_birds'] ?? 0));
                    $targetWeightBird = !empty($r['target_weight_per_bird']) ? (float)$r['target_weight_per_bird'] : 2.20;
                    $effectiveBId = !empty($r['effective_batch_id']) ? (int)$r['effective_batch_id'] : (!empty($r['batch_id']) ? (int)$r['batch_id'] : 0);
                    $resReadiness = (!empty($effectiveBId) && $conn instanceof PDO)
                        ? check_release_readiness($conn, $effectiveBId, $targetWeightBird, $r['pickup_date'] ?? null)
                        : null;
                    $rWf = $r['workflow_stage'] ?? (!empty($r['batch_id']) ? 'growing' : 'pending_approval');
                    $rWfLabel = match($rWf) {
                        'pending_approval' => 'Step 2 · Pending Approval',
                        'approved' => 'Step 3 · Awaiting Delivery',
                        'receiving' => 'Step 4 · Pending Verification',
                        'receipt_confirmed' => 'Step 5 · Activated',
                        'growing' => 'Step 5 · Growing',
                        default => 'Step 5 · Growing'
                    };
                    $balAmt = max(0, round((float)($r['total_amount'] ?? 0) - (float)($r['advance_payment'] ?? 0), 2));
                    $agreedWt = !empty($r['target_weight_per_bird'])
                        ? number_format((float)$r['target_weight_per_bird'], 2) . ' kg'
                        : (!empty($r['weight_kg']) ? number_format((float)$r['weight_kg'], 2) . ' kg' : '—');
                    $readyLabel = 'Pen reserved';
                    $readyClass = 'text-primary';
                    if ($resReadiness) {
                        if (!empty($resReadiness['ready'])) {
                            $readyLabel = ($resReadiness['is_weight_met'] ? 'Weight met' : 'Date met')
                                . ' · ' . number_format((float)$resReadiness['current_weight'], 2) . ' kg';
                            $readyClass = 'text-success';
                        } else {
                            $readyLabel = number_format((float)$resReadiness['current_weight'], 2)
                                . ' / ' . number_format((float)$resReadiness['target_weight'], 2) . ' kg';
                            $readyClass = 'text-warning';
                        }
                    } elseif (!empty($r['batch_id'])) {
                        $readyLabel = 'Batch linked';
                        $readyClass = 'text-secondary';
                    }
                    ob_start();
                    ?>
                    <!-- MODAL: View Reservation Details (#<?php echo $resId; ?>) -->
                    <div class="modal fade text-start" id="viewResModal<?php echo $resId; ?>" tabindex="-1" aria-labelledby="viewResTitle<?php echo $resId; ?>">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down" style="max-width: 560px;">
                            <div class="modal-content border-0 rounded-4 shadow view-res-modal">
                                <div class="modal-header border-0 py-3 px-3 px-md-4">
                                    <div class="min-w-0 pe-2">
                                        <div class="text-muted text-uppercase small fw-semibold mb-1" style="letter-spacing: .04em; font-size: .68rem;">Reservation</div>
                                        <h5 class="modal-title fw-bold mb-0 text-truncate" id="viewResTitle<?php echo $resId; ?>">
                                            #<?php echo e($r['reservation_number']); ?>
                                        </h5>
                                    </div>
                                    <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body pt-0 px-3 px-md-4 pb-3">
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.72rem;"><?php echo e($rWfLabel); ?></span>
                                        <span class="badge rounded-pill bg-secondary text-white px-2 py-1 text-uppercase" style="font-size:.68rem;"><?php echo e($r['status'] ?? 'n/a'); ?></span>
                                        <?php if ($balAmt <= 0.001): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.68rem;"><i class="fas fa-check me-1"></i>Paid</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size:.68rem;">Bal ₱<?php echo number_format($balAmt, 0); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['is_dispatched'])): ?>
                                            <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle px-2 py-1" style="font-size:.68rem;">Dispatched</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="view-res-block mb-2">
                                        <div class="view-res-k">Integrator</div>
                                        <div class="view-res-v fw-bold"><?php echo e($r['customer_name'] ?? 'N/A'); ?></div>
                                        <div class="view-res-meta">
                                            <?php if (!empty($r['customer_code'])): ?><span><?php echo e($r['customer_code']); ?></span><?php endif; ?>
                                            <?php if (!empty($r['customer_phone'])): ?><span><i class="fas fa-phone me-1"></i><?php echo e($r['customer_phone']); ?></span><?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="view-res-grid mb-2">
                                        <div class="view-res-row"><span>House</span><strong><?php echo e($r['house_name'] ?? 'Unassigned'); ?></strong></div>
                                        <div class="view-res-row"><span>Batch</span><strong class="font-monospace"><?php echo e($r['batch_code'] ?? '—'); ?></strong></div>
                                        <div class="view-res-row"><span>Breed</span><strong><?php echo e($r['breed'] ?? 'Cobb 500'); ?></strong></div>
                                        <div class="view-res-row"><span>Birds</span><strong><?php echo number_format($dispQty); ?></strong></div>
                                        <div class="view-res-row"><span>Target wt</span><strong><?php echo e($agreedWt); ?></strong></div>
                                        <div class="view-res-row"><span>DOC price</span><strong>₱<?php echo number_format((float)($r['chick_cost'] ?? 38.00), 2); ?></strong></div>
                                        <div class="view-res-row"><span>Pickup</span><strong><?php echo !empty($r['pickup_date']) ? date('M d, Y', strtotime($r['pickup_date'])) : '—'; ?></strong></div>
                                        <div class="view-res-row"><span>Readiness</span><strong class="<?php echo e($readyClass); ?>"><?php echo e($readyLabel); ?></strong></div>
                                    </div>

                                    <div class="view-res-grid view-res-money mb-2">
                                        <div class="view-res-row"><span>Total</span><strong>₱<?php echo number_format((float)($r['total_amount'] ?? 0), 2); ?></strong></div>
                                        <div class="view-res-row"><span>Advance</span><strong class="text-success">₱<?php echo number_format((float)($r['advance_payment'] ?? 0), 2); ?></strong></div>
                                        <div class="view-res-row"><span>Balance</span><strong class="<?php echo $balAmt > 0.001 ? 'text-danger' : 'text-success'; ?>">₱<?php echo number_format($balAmt, 2); ?></strong></div>
                                    </div>

                                    <?php if (!empty($r['accepted_quantity']) || !empty($r['rejected_quantity'])): ?>
                                    <div class="view-res-grid mb-2">
                                        <div class="view-res-row"><span>Accepted</span><strong class="text-success"><?php echo number_format((int)$r['accepted_quantity']); ?></strong></div>
                                        <div class="view-res-row"><span>Rejected</span><strong class="text-danger"><?php echo number_format((int)$r['rejected_quantity']); ?></strong></div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($r['notes'])): ?>
                                        <div class="view-res-notes">
                                            <span class="view-res-k">Notes</span>
                                            <p class="mb-0"><?php echo e($r['notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer border-0 py-2 px-3 px-md-4 gap-2">
                                    <?php if ($r['status'] === 'active' && empty($r['batch_id']) && ($r['workflow_stage'] ?? '') !== 'growing' && strtolower($user_role) === 'admin'): ?>
                                        <a href="batch.php?reserve_id=<?php echo $resId; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                                            <i class="fas fa-layer-group me-1"></i>Stock Batch
                                        </a>
                                    <?php elseif (!empty($r['batch_id']) || ($r['workflow_stage'] ?? '') === 'growing'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-2">
                                            <i class="fas fa-check-circle me-1"></i>Growing
                                        </span>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3 ms-auto" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    return ob_get_clean();
                }
                }

                $housesQuery = "
                SELECT 
                    h.*,
                    b.batch_id as active_batch_id,
                    b.batch_code as active_batch_code,
                    b.current_quantity as active_headcount,
                    b.arrival_date as active_arrival_date,
                    b.expected_harvest_date as active_harvest_date,
                    b.target_weight_kg as active_target_weight,
                    b.current_weight_kg as active_current_weight,
                    b.supplier as active_integrator
                FROM houses h
                LEFT JOIN batches b ON (
                    (h.current_batch_id IS NOT NULL AND b.batch_id = h.current_batch_id)
                    OR (h.current_batch_id IS NULL AND b.status = 'active' AND b.house_pen = h.house_name)
                )
                ORDER BY h.house_id ASC
                ";
                $housesList = $conn->query($housesQuery)->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="card bg-white border rounded-4 shadow-sm mb-4" id="housesAvailabilityBoard">
                    <div class="card-header bg-light border-0 py-3 d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-warehouse text-success me-2"></i>Poultry House & Pen Availability Board</h5>
                            <small class="text-muted">Real-time pen capacity, agreed body weight verification, release readiness to integrator, and vacant house reservations.</small>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                            <i class="fas fa-scale-balanced me-1"></i>Weight Verification Active
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <!-- 5-Stage Role-Owned Sequential Workflow Pipeline -->
                        <div class="mb-3 p-2 p-md-3 rounded-4 border pipeline-board">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-2 pb-2 border-bottom border-secondary-subtle">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-circle p-1 flex-shrink-0" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">
                                        <i class="fas fa-network-wired"></i>
                                    </span>
                                    <span class="fw-bold small pipeline-step-title text-truncate">
                                        Chick Reservation Sequential Workflow Pipeline
                                    </span>
                                </div>
                                <div class="rounded-pill pipeline-rule-banner px-3 py-1" style="font-size: 0.72rem; line-height: 1.35; max-width: 100%; white-space: normal !important; word-break: break-word;">
                                    <i class="fas fa-lock me-1 text-warning flex-shrink-0"></i><span><strong>Sequential Rule:</strong> Steps advance strictly 1 &rarr; 2 &rarr; 3 &rarr; 4 &rarr; 5</span>
                                </div>
                            </div>
                            <div class="row g-2 text-center">
                                <!-- Step 1 -->
                                <div class="col-6 col-sm-4 col-lg">
                                    <div class="p-2 rounded-3 pipeline-step-card border h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1 gap-1">
                                            <span class="badge bg-primary text-white rounded-pill px-2" style="font-size: 0.65rem;">Step 1</span>
                                            <span class="badge pipeline-role-badge rounded-pill px-2" style="font-size: 0.65rem;"><i class="fas fa-handshake me-1"></i>Integrator</span>
                                        </div>
                                        <div class="fw-bold pipeline-step-title text-truncate" style="font-size: 0.78rem;">Reservation</div>
                                        <div class="pipeline-step-sub" style="font-size: 0.68rem;">Integrator books pen</div>
                                    </div>
                                </div>
                                <!-- Step 2 -->
                                <div class="col-6 col-sm-4 col-lg">
                                    <div class="p-2 rounded-3 pipeline-step-card border h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1 gap-1">
                                            <span class="badge bg-warning text-dark rounded-pill px-2" style="font-size: 0.65rem;">Step 2</span>
                                            <span class="badge pipeline-role-badge rounded-pill px-2" style="font-size: 0.65rem;"><i class="fas fa-user-shield me-1"></i>Admin</span>
                                        </div>
                                        <div class="fw-bold pipeline-step-title text-truncate" style="font-size: 0.78rem;">Feasibility Review</div>
                                        <div class="pipeline-step-sub" style="font-size: 0.68rem;">Capacity &amp; feeds check</div>
                                    </div>
                                </div>
                                <!-- Step 3 -->
                                <div class="col-6 col-sm-4 col-lg">
                                    <div class="p-2 rounded-3 pipeline-step-card border h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1 gap-1">
                                            <span class="badge bg-info text-dark rounded-pill px-2" style="font-size: 0.65rem;">Step 3</span>
                                            <span class="badge pipeline-role-badge rounded-pill px-2" style="font-size: 0.65rem;"><i class="fas fa-people-carry-box me-1"></i>Staff</span>
                                        </div>
                                        <div class="fw-bold pipeline-step-title text-truncate" style="font-size: 0.78rem;">Delivery Intake</div>
                                        <div class="pipeline-step-sub" style="font-size: 0.68rem;">Staff records chick arrival</div>
                                    </div>
                                </div>
                                <!-- Step 4 -->
                                <div class="col-6 col-sm-4 col-lg">
                                    <div class="p-2 rounded-3 pipeline-step-card border h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1 gap-1">
                                            <span class="badge bg-secondary text-white rounded-pill px-2" style="font-size: 0.65rem;">Step 4</span>
                                            <span class="badge pipeline-role-badge rounded-pill px-2" style="font-size: 0.65rem;"><i class="fas fa-user-shield me-1"></i>Admin</span>
                                        </div>
                                        <div class="fw-bold pipeline-step-title text-truncate" style="font-size: 0.78rem;">Maker-Checker</div>
                                        <div class="pipeline-step-sub" style="font-size: 0.68rem;">DR# &amp; headcount check</div>
                                    </div>
                                </div>
                                <!-- Step 5 -->
                                <div class="col-12 col-sm-4 col-lg">
                                    <div class="p-2 rounded-3 pipeline-step-card border h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1 gap-1">
                                            <span class="badge bg-success text-white rounded-pill px-2" style="font-size: 0.65rem;">Step 5</span>
                                            <span class="badge pipeline-role-badge rounded-pill px-2" style="font-size: 0.65rem;"><i class="fas fa-layer-group me-1"></i>Production</span>
                                        </div>
                                        <div class="fw-bold pipeline-step-title text-truncate" style="font-size: 0.78rem;">Active Batch Growth</div>
                                        <div class="pipeline-step-sub" style="font-size: 0.68rem;">Flock monitoring active</div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0 mt-1 d-inline-flex align-items-center" style="font-size: 0.65rem;">
                                            <i class="fas fa-check-circle me-1"></i>Flock Active
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (strtolower($user_role) === 'worker'): ?>
                        <div class="alert alert-warning border-warning-subtle rounded-3 py-2 px-3 mb-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 shadow-sm">
                            <div class="small text-dark">
                                <i class="fas fa-broom me-2 text-warning fs-6"></i>
                                <strong>Worker Sanitation Taskboard:</strong> Review houses scheduled for cleaning by Farm Admin. Wash, sanitize, and disinfect pens, then click <strong>Mark Cleaning Complete</strong> once sanitized and certified ready for chick intake.
                            </div>
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill text-uppercase font-monospace align-self-start align-self-sm-auto" style="font-size: 0.70rem;">Worker Cleaning Mode</span>
                        </div>
                        <?php endif; ?>
                        <div class="row g-3">
                            <?php foreach($housesList as $hl): 
                                $isOccupied = !empty($hl['active_batch_id']) && (int)$hl['active_headcount'] > 0;
                                $stage = $isOccupied ? get_broiler_stage_info($hl['active_arrival_date'], null, $hl['active_harvest_date'] ?? null) : null;

                                // Fetch active reservations linked to this house or its active batch
                                $activeBId = (int)($hl['active_batch_id'] ?? 0);
                                $hId = (int)($hl['house_id'] ?? 0);
                                $houseReservations = [];
                                $stmtHouseRes = $conn->prepare("
                                    SELECT DISTINCT r.*, c.name as customer_name, c.customer_code, c.phone as customer_phone
                                    FROM reservations r
                                    JOIN customers c ON r.customer_id = c.customer_id
                                    WHERE r.status = 'active'
                                      AND (
                                          (r.house_id = ?)
                                          OR (? > 0 AND r.batch_id = ?)
                                      )
                                    ORDER BY r.reservation_id DESC
                                ");
                                $stmtHouseRes->execute([$hId, $activeBId, $activeBId]);
                                $houseReservations = $stmtHouseRes->fetchAll(PDO::FETCH_ASSOC);

                                $isReserved = ($hl['status'] === 'reserved' || (!empty($houseReservations) && !$isOccupied));

                                // 1 Integrator Exclusive: If house is reserved or occupied, entire house is committed
                                $resBirdsSum = 0;
                                foreach ($houseReservations as $hResItem) {
                                    $resBirdsSum += (int)$hResItem['quantity_birds'];
                                }
                                $activeHeadcount = (int)($hl['active_headcount'] ?? 0);
                                $committedBirds = max($resBirdsSum, $activeHeadcount);
                                $houseCap = (int)($hl['capacity'] > 0 ? $hl['capacity'] : 5000);
                                $remainingCapacity = ($isReserved || $isOccupied) ? 0 : $houseCap;
                                $isPartiallyOccupied = false;
                                $isPartiallyReserved = false;
                                $isFullyCommitted = ($isReserved || $isOccupied);

                                // Check if this flock or house belongs to the logged-in integrator
                                $isMyFlock = false;
                                if (!empty($houseReservations)) {
                                    $firstRes = $houseReservations[0];
                                    $isMyFlock = ($isIntegrator && ((int)$firstRes['customer_id'] === $integratorCustomerId || (int)($firstRes['created_by'] ?? 0) === $user_id));
                                } elseif (!empty($hl['active_integrator']) && $isIntegrator && !empty($integratorCustomer)) {
                                    $isMyFlock = (stripos($hl['active_integrator'], $integratorCustomer['name']) !== false || stripos($integratorCustomer['name'], $hl['active_integrator']) !== false);
                                }

                                // Resolve agreed target weight & agreed target date (from reservation or batch expected harvest)
                                $agreedTargetWeight = !empty($houseReservations[0]['target_weight_per_bird']) 
                                    ? (float)$houseReservations[0]['target_weight_per_bird'] 
                                    : (!empty($hl['active_target_weight']) ? (float)$hl['active_target_weight'] : 1.80);

                                $agreedTargetDate = !empty($houseReservations[0]['pickup_date']) 
                                    ? $houseReservations[0]['pickup_date'] 
                                    : (!empty($hl['active_harvest_date']) ? $hl['active_harvest_date'] : null);

                                $readiness = $isOccupied ? check_release_readiness($conn, $hl['active_batch_id'], $agreedTargetWeight, $agreedTargetDate) : null;
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 rounded-4 border h-100 house-card-mobile <?php 
                                    if ($isOccupied) {
                                        if ($readiness && $readiness['ready']) {
                                            echo 'bg-success-subtle border-success';
                                        } elseif ($remainingCapacity > 0) {
                                            echo 'bg-white border-warning-subtle shadow-sm';
                                        } else {
                                            echo 'bg-light border-warning-subtle';
                                        }
                                    } elseif ($isReserved) {
                                        echo $remainingCapacity > 0 ? 'bg-white border-primary-subtle shadow-sm' : 'bg-primary-subtle border-primary-subtle';
                                    } else {
                                        echo 'bg-white border-success-subtle';
                                    }
                                ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0"><?php echo e($hl['house_name']); ?></h6>
                                            <small class="text-muted"><i class="fas fa-arrows-left-right-to-line me-1"></i>Capacity: <strong><?php echo number_format($houseCap); ?> birds</strong></small>
                                        </div>
                                        <?php if ($isOccupied): ?>
                                            <?php if ($readiness && $readiness['ready']): ?>
                                                <span class="badge bg-success rounded-pill px-2 py-1"><i class="fas fa-check-circle me-1"></i>Step 5: Ready for Release</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1"><i class="fas fa-check-circle me-1"></i>Step 5: Active Flock Growing</span>
                                            <?php endif; ?>
                                        <?php elseif ($isReserved): ?>
                                            <?php 
                                            $firstWf = !empty($houseReservations) ? ($houseReservations[0]['workflow_stage'] ?? 'pending_approval') : 'pending_approval';
                                            if ($firstWf === 'pending_approval'): ?>
                                                <span class="badge rounded-pill px-2 py-1" style="background: rgba(245, 158, 11, 0.15); color: #b45309; border: 1px solid rgba(251, 191, 36, 0.4);"><i class="fas fa-hourglass-half me-1"></i>Step 2: Under Review</span>
                                            <?php elseif ($firstWf === 'approved'): ?>
                                                <span class="badge rounded-pill px-2 py-1" style="background: rgba(14, 165, 233, 0.15); color: #0284c7; border: 1px solid rgba(56, 189, 248, 0.4);"><i class="fas fa-truck me-1"></i>Step 3: Awaiting Delivery</span>
                                            <?php elseif ($firstWf === 'receiving'): ?>
                                                <span class="badge rounded-pill px-2 py-1" style="background: rgba(147, 51, 234, 0.15); color: #7e22ce; border: 1px solid rgba(192, 132, 252, 0.4);"><i class="fas fa-clipboard-check me-1"></i>Step 4: Pending Verification</span>
                                            <?php elseif ($remainingCapacity > 0): ?>
                                                <span class="badge bg-info text-dark rounded-pill px-2 py-1"><i class="fas fa-chart-pie me-1"></i>Partially Booked</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary rounded-pill px-2 py-1"><i class="fas fa-bookmark me-1"></i>Reserved</span>
                                            <?php endif; ?>
                                        <?php elseif ($hl['status'] === 'cleaning'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-2 py-1"><i class="fas fa-broom me-1"></i>Cleaning</span>
                                        <?php else: ?>
                                            <?php if ($isIntegrator): ?>
                                                <span class="badge bg-success rounded-pill px-2 py-1"><i class="fas fa-door-open me-1"></i>Step 1: Open to Reserve</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-2 py-1"><i class="fas fa-hourglass-start me-1"></i>Step 1: Waiting for Submission</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($isOccupied): ?>
                                        <div class="small bg-white p-2 rounded-3 border mb-2">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">Active Flock:</span>
                                                <strong class="text-primary">
                                                    <?php 
                                                    if ($isIntegrator && !$isMyFlock) {
                                                        echo '<span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;"><i class="fas fa-lock me-1"></i>Growing Flock (Confidential)</span>';
                                                    } else {
                                                        echo e($hl['active_batch_code']);
                                                    }
                                                    ?>
                                                </strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">Occupied Birds:</span>
                                                <strong class="text-dark"><?php echo number_format($hl['active_headcount']); ?> heads</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">Age / Stage:</span>
                                                <span class="<?php echo $stage['badge_class']; ?> py-0 px-2 rounded-pill"><?php echo $stage['age_days']; ?>d (<?php echo $stage['stage']; ?>)</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted"><i class="far fa-calendar-check text-success me-1"></i>Agreed Date:</span>
                                                <strong class="text-dark">
                                                    <?php 
                                                    if ($isIntegrator && !$isMyFlock) {
                                                        echo '<span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;"><i class="fas fa-lock me-1"></i>Confidential</span>';
                                                    } else {
                                                        $displayAgreedDate = !empty($readiness['agreed_date']) ? $readiness['agreed_date'] : $agreedTargetDate;
                                                        if (!empty($displayAgreedDate)) {
                                                            echo date('M d, Y', strtotime($displayAgreedDate));
                                                            $daysLeft = (int)round((strtotime($displayAgreedDate) - strtotime(date('Y-m-d'))) / 86400);
                                                            if ($daysLeft > 0) {
                                                                echo " <small class='text-muted'>({$daysLeft}d left)</small>";
                                                            } elseif ($daysLeft === 0) {
                                                                echo " <small class='text-success fw-bold'>(Today)</small>";
                                                            } else {
                                                                echo " <small class='text-danger fw-bold'>(" . abs($daysLeft) . "d overdue)</small>";
                                                            }
                                                        } else {
                                                            echo 'In ~' . max(0, 35 - ($stage['age_days'] ?? 0)) . ' days';
                                                        }
                                                    }
                                                    ?>
                                                </strong>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center pt-1 border-top mt-1">
                                                <span class="text-muted small"><i class="fas fa-handshake text-success me-1"></i>Reserved By:</span>
                                                <?php if (!empty($houseReservations)): ?>
                                                    <?php if ($isIntegrator && !$isMyFlock): ?>
                                                        <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">
                                                            <i class="fas fa-lock me-1"></i>Booked (Confidential)
                                                        </span>
                                                    <?php else: ?>
                                                        <strong class="text-primary text-truncate small" style="max-width: 175px;" title="<?php echo $isIntegrator ? 'Your Company' : e($houseReservations[0]['customer_name']); ?>">
                                                            <?php echo $isIntegrator ? 'Your Company' : e($houseReservations[0]['customer_name']); ?>
                                                            <?php if (!$isIntegrator && count($houseReservations) > 1): ?>
                                                                <span class="badge bg-secondary-subtle text-secondary py-0 px-1">+<?php echo count($houseReservations) - 1; ?></span>
                                                            <?php endif; ?>
                                                        </strong>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if (!empty($hl['active_integrator'])): ?>
                                                        <strong class="text-primary text-truncate small" style="max-width: 175px;" title="<?php echo $isIntegrator ? 'Your Company' : e($hl['active_integrator']); ?>">
                                                            <?php echo ($isIntegrator && !$isMyFlock) ? 'Booked (Confidential)' : ($isIntegrator ? 'Your Company' : e($hl['active_integrator'])); ?>
                                                        </strong>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">Open / No Reserve</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Weight Verification & Release Readiness Gate -->
                                        <?php if ($isIntegrator && !$isMyFlock): ?>
                                            <div class="p-2 rounded-3 bg-light border mb-2 text-center text-muted small">
                                                <i class="fas fa-lock me-1 text-secondary"></i>House Occupied &bull; External Partner Flock
                                            </div>
                                            <div class="p-2 text-center bg-white rounded-3 border text-muted small">
                                                <i class="fas fa-shield-alt me-1 text-secondary"></i>Growing under external partner contract
                                            </div>
                                        <?php elseif ($readiness && $readiness['ready']): ?>
                                            <div class="p-2 rounded-3 bg-success text-white mb-2 shadow-sm">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold small">
                                                        <i class="fas fa-check-double me-1"></i><?php echo $readiness['is_weight_met'] ? 'AGREED WEIGHT MET' : 'AGREED DATE REACHED'; ?>
                                                    </span>
                                                    <span class="badge bg-white text-success fw-bold font-monospace"><?php echo number_format($readiness['current_weight'], 2); ?> kg</span>
                                                </div>
                                                <div style="font-size: 0.78rem; opacity: 0.95;">
                                                    <div>Agreed Target: <strong><?php echo number_format($readiness['target_weight'], 2); ?> kg</strong> &bull; Agreed Date: <strong><?php echo !empty($readiness['agreed_date']) ? date('M d, Y', strtotime($readiness['agreed_date'])) : 'N/A'; ?></strong></div>
                                                    <div class="mt-1"><?php echo e($readiness['message']); ?></div>
                                                </div>
                                            </div>
                                            <?php if (!$isIntegrator): ?>
                                                <div class="d-flex gap-1">
                                                    <?php if (!empty($houseReservations)): ?>
                                                        <?php 
                                                            $topHRes = $houseReservations[0];
                                                            $hBal = max(0, round((float)$topHRes['total_amount'] - (float)$topHRes['advance_payment'], 2));
                                                            $hPaid = ($hBal <= 0.001);
                                                            $cNameParts = explode(' ', trim($topHRes['customer_name']));
                                                            $shortCust = $cNameParts[0];
                                                            if (in_array(strtolower($shortCust), ['san', 'santa', 'saint', 'bounty', 'general']) && isset($cNameParts[1])) {
                                                                $shortCust .= ' ' . $cNameParts[1];
                                                            }
                                                            if (strlen($shortCust) > 16) $shortCust = substr($shortCust, 0, 14) . '...';
                                                        ?>
                                                        <?php if (in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                                            <?php if ($hPaid): ?>
                                                                <?php if (!empty($topHRes['is_dispatched'])): ?>
                                                                    <span class="btn btn-sm btn-success-subtle text-success border border-success-subtle flex-grow-1 rounded-pill fw-bold shadow-sm" style="pointer-events:none;">
                                                                        <i class="fas fa-check-double me-1"></i>Dispatched &bull; Awaiting Pick Up
                                                                    </span>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn btn-sm btn-success flex-grow-1 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#dispatchHouseModal<?php echo (int)$hl['house_id']; ?>">
                                                                        <i class="fas fa-paper-plane me-1"></i>Dispatch to <?php echo e($shortCust); ?>
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-sm btn-warning text-dark flex-grow-1 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#dispatchHouseModal<?php echo (int)$hl['house_id']; ?>" title="Contract Balance Unpaid: ₱<?php echo number_format($hBal, 2); ?>">
                                                                    <i class="fas fa-lock text-danger me-1"></i>Dispatch (Bal: ₱<?php echo number_format($hBal, 2); ?>)
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="btn btn-sm btn-light border text-muted flex-grow-1 rounded-pill text-truncate" style="font-size:0.75rem;">
                                                                <i class="fas fa-check-circle text-success me-1"></i>Ready for Dispatch
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if (in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                                        <button type="button" class="btn btn-sm btn-success flex-grow-1 rounded-pill fw-bold" onclick="prefillReserveBatch(<?php echo (int)$hl['active_batch_id']; ?>, '<?php echo e($hl['active_batch_code']); ?>', <?php echo (int)$hl['active_headcount']; ?>, <?php echo (int)$hl['house_id']; ?>, <?php echo (float)$readiness['target_weight']; ?>)">
                                                            <i class="fas fa-truck-loading me-1"></i>Dispatch / Release Flock
                                                        </button>
                                                        <?php else: ?>
                                                        <span class="btn btn-sm btn-light border text-muted flex-grow-1 rounded-pill text-truncate" style="font-size:0.75rem;">
                                                            <i class="fas fa-check-circle text-success me-1"></i>Flock Target Reached
                                                        </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" title="Update Weight Sample" onclick="openQuickWeighModal(<?php echo (int)$hl['active_batch_id']; ?>, '<?php echo e($hl['active_batch_code']); ?>', '<?php echo e($hl['house_name']); ?>', <?php echo (float)$readiness['current_weight']; ?>, <?php echo (float)$readiness['target_weight']; ?>, <?php echo (int)($stage['age_days'] ?? 0); ?>, '<?php echo e($hl['active_arrival_date'] ?? date('Y-m-d')); ?>', <?php echo (int)$hl['active_headcount']; ?>)">
                                                        <i class="fas fa-weight-scale"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($isMyFlock && !empty($houseReservations)): 
                                                    $fRes = $houseReservations[0];
                                                    $fTot = (float)$fRes['total_amount'];
                                                    $fAdv = (float)$fRes['advance_payment'];
                                                    $fBal = max(0, round($fTot - $fAdv, 2));
                                                    $fPaid = ($fBal <= 0.001);
                                                    $fDispatched = !empty($fRes['is_dispatched']);
                                                ?>
                                                    <?php if (!$fPaid): ?>
                                                        <button type="button" class="btn btn-sm btn-warning text-dark w-100 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo (int)$fRes['reservation_id']; ?>">
                                                            <i class="fas fa-lock text-danger me-1"></i>Pick Up Flock (Bal: ₱<?php echo number_format($fBal, 2); ?>)
                                                        </button>
                                                    <?php elseif (!$fDispatched): ?>
                                                        <button type="button" class="btn btn-sm btn-warning text-dark w-100 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo (int)$fRes['reservation_id']; ?>" title="Contract fully paid. Awaiting Farm Admin flock inspection and dispatch">
                                                            <i class="fas fa-clock me-1"></i>Awaiting Admin Dispatch
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-success w-100 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo (int)$fRes['reservation_id']; ?>">
                                                            <i class="fas fa-truck-fast me-1"></i>Pick Up Flock (Dispatched)
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php 
                                                    if (!isset($renderedIntegratorModals[(int)$fRes['reservation_id']])) {
                                                        echo renderIntegratorPickupModalHTML($fRes, $readiness, $hl['house_name']);
                                                        $renderedIntegratorModals[(int)$fRes['reservation_id']] = true;
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    <div class="p-2 text-center bg-white rounded-3 border text-success fw-semibold small">
                                                        <i class="fas fa-check-circle me-1"></i>Flock Ready for Harvest &bull; Awaiting Farm Dispatch
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php
                                             $curW = (float)($readiness['current_weight'] ?? 0);
                                             $tgtW = (float)($readiness['target_weight'] ?? 1.80);
                                             $diff = max(0, round($tgtW - $curW, 2));
                                             $pct = $tgtW > 0 ? round(($curW / $tgtW) * 100) : 0;
                                             $agreeDtText = !empty($readiness['agreed_date']) ? date('M d, Y', strtotime($readiness['agreed_date'])) : 'N/A';
                                             ?>
                                            <div class="p-2 rounded-3 bg-warning-subtle border border-warning mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold small text-dark"><i class="fas fa-hourglass-half me-1"></i>GROWING (BELOW TARGET WEIGHT)</span>
                                                    <span class="badge bg-warning text-dark font-monospace"><?php echo number_format($curW, 2); ?> / <?php echo number_format($tgtW, 2); ?> kg</span>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.78rem;">
                                                    <div>Agreed Target: <strong><?php echo number_format($tgtW, 2); ?> kg</strong> &bull; Agreed Date: <strong><?php echo $agreeDtText; ?></strong></div>
                                                    <div class="mt-1">Current sample: <strong><?php echo number_format($curW, 2); ?> kg</strong> (<?php echo $pct; ?>%). Needs <strong><?php echo number_format($diff, 2); ?> kg</strong> more before release.</div>
                                                </div>
                                            </div>
                                            <?php if (!$isIntegrator): ?>
                                                <div class="d-flex gap-1">
                                                    <?php 
                                                    $targetResId = !empty($houseReservations[0]['reservation_id']) ? (int)$houseReservations[0]['reservation_id'] : 0;
                                                    ?>
                                                    <?php if ($targetResId > 0): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#viewResModal<?php echo $targetResId; ?>" title="Step 5 Finished: View Details">
                                                            <i class="fas fa-eye me-1"></i>View Details
                                                        </button>
                                                        <?php 
                                                        if (!isset($renderedViewModals[$targetResId]) && !empty($houseReservations[0])) {
                                                            $viewRow = $houseReservations[0];
                                                            if (empty($viewRow['house_name']) && !empty($hl['house_name'])) {
                                                                $viewRow['house_name'] = $hl['house_name'];
                                                            }
                                                            if (empty($viewRow['batch_code']) && !empty($hl['active_batch_code'])) {
                                                                $viewRow['batch_code'] = $hl['active_batch_code'];
                                                            }
                                                            $viewResModalBuffer[$targetResId] = renderViewReservationModalHTML($viewRow, $user_role);
                                                            $renderedViewModals[$targetResId] = true;
                                                        }
                                                        ?>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 rounded-pill fw-bold shadow-sm" disabled title="Step 5 Finished">
                                                            <i class="fas fa-check-circle me-1"></i>Step 5 Finished
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" title="Update Weight Sample" onclick="openQuickWeighModal(<?php echo (int)$hl['active_batch_id']; ?>, '<?php echo e($hl['active_batch_code']); ?>', '<?php echo e($hl['house_name']); ?>', <?php echo (float)$curW; ?>, <?php echo (float)$tgtW; ?>, <?php echo (int)($stage['age_days'] ?? 0); ?>, '<?php echo e($hl['active_arrival_date'] ?? date('Y-m-d')); ?>', <?php echo (int)$hl['active_headcount']; ?>)">
                                                        <i class="fas fa-weight-scale"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($isMyFlock && !empty($houseReservations)): 
                                                    $fRes = $houseReservations[0];
                                                    $fTot = (float)$fRes['total_amount'];
                                                    $fAdv = (float)$fRes['advance_payment'];
                                                    $fBal = max(0, round($fTot - $fAdv, 2));
                                                ?>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary w-100 rounded-pill py-1" style="font-size:0.75rem;" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo (int)$fRes['reservation_id']; ?>">
                                                        <i class="fas fa-clipboard-check me-1"></i>Check Pick Up & Payment Gate (Bal: ₱<?php echo number_format($fBal, 2); ?>)
                                                    </button>
                                                    <?php 
                                                    if (!isset($renderedIntegratorModals[(int)$fRes['reservation_id']])) {
                                                        echo renderIntegratorPickupModalHTML($fRes, $readiness, $hl['house_name']);
                                                        $renderedIntegratorModals[(int)$fRes['reservation_id']] = true;
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    <div class="p-2 text-center bg-white rounded-3 border text-muted small mb-2">
                                                        <i class="fas fa-lock me-1"></i>House Occupied &bull; Flock Growing to Target Weight
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div class="p-2 mb-2 rounded-3 bg-light border text-center text-muted" style="font-size: 0.75rem;">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 mb-1">
                                                <i class="fas fa-check-circle me-1"></i>Step 5: Batch Production Active
                                            </span>
                                            <div>Flock is growing in this pen. Reservation workflow is locked until harvest &amp; sanitation.</div>
                                        </div>

                                    <?php elseif ($isReserved): ?>
                                        <?php 
                                            $firstRes = !empty($houseReservations) ? $houseReservations[0] : null;
                                            $firstResWf = $firstRes ? ($firstRes['workflow_stage'] ?? 'pending_approval') : 'pending_approval';
                                            $firstResId = $firstRes ? (int)$firstRes['reservation_id'] : 0;
                                            $isMyHouseRes = ($firstRes && $isIntegrator && ((int)$firstRes['customer_id'] === $integratorCustomerId || (int)($firstRes['created_by'] ?? 0) === $user_id));
                                        ?>
                                        <?php if ($isIntegrator && !$isMyHouseRes): ?>
                                            <div class="mt-2 small bg-white p-3 rounded-3 border text-center">
                                                <span class="badge bg-secondary-subtle text-muted border rounded-pill px-3 py-1 mb-2 font-monospace text-wrap text-center" style="max-width: 100%; word-break: break-word; line-height: 1.4; display: inline-block;">
                                                    <i class="fas fa-lock me-1"></i>Reserved &bull; Contract Partner (Confidential)
                                                </span>
                                                <div class="p-2 bg-light rounded-3 text-muted" style="font-size: 0.78rem;">
                                                    This poultry house is currently reserved by an external contract partner. Intake schedule, capacity, and agreement terms are strictly confidential.
                                                </div>
                                            </div>
                                        <?php else: ?>
                                        <div class="mt-2 small bg-white p-3 rounded-3 border text-center">
                                            <?php if ($firstRes): ?>
                                                <?php if ($firstResWf === 'pending_approval'): ?>
                                                    <!-- Clear Step 1 -> Step 2 Prerequisite Flow Header -->
                                                    <div class="mb-2 p-2 rounded-3 border text-start" style="background: #f8fafc;">
                                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size:0.68rem;" title="Integrator submitted Step 1">
                                                                <i class="fas fa-check-circle me-1"></i>Step 1: Submitted
                                                            </span>
                                                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-2 py-0 fw-bold" style="font-size:0.68rem;" title="Awaiting Farm Admin Feasibility Review">
                                                                <i class="fas fa-hourglass-half me-1"></i>Step 2: Admin Review
                                                            </span>
                                                        </div>
                                                        <div class="progress my-1" style="height: 5px;">
                                                            <div class="progress-bar bg-success" role="progressbar" style="width: 25%;" title="Step 1 Completed"></div>
                                                            <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" role="progressbar" style="width: 25%;" title="Step 2 Active (Awaiting Review)"></div>
                                                        </div>
                                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.68rem;">
                                                            <span><i class="fas fa-check text-success me-1"></i>Step 1 Complete</span>
                                                            <span class="fw-semibold text-warning-emphasis">Awaiting Admin Step 2</span>
                                                        </div>
                                                    </div>
                                                <?php elseif ($firstResWf === 'approved'): ?>
                                                    <div class="text-success fw-bold mb-1">
                                                        <i class="far fa-circle-check me-1"></i>Step 2: Approved &bull; Awaiting Delivery
                                                    </div>
                                                <?php elseif ($firstResWf === 'receiving'): ?>
                                                    <div class="text-warning fw-bold mb-1">
                                                        <i class="fas fa-truck-fast me-1"></i>Step 3: Chicks Arrived (Receiving)
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-primary fw-bold mb-1">
                                                        <i class="fas fa-bookmark me-1"></i>House Space Reserved
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($isIntegrator && !$isMyHouseRes): ?>
                                                    <div class="p-2 bg-light rounded-3 mb-2 text-start small">
                                                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                                                            <span class="text-muted">Reserved By:</span>
                                                            <span class="badge bg-secondary-subtle text-muted border text-wrap text-end" style="max-width: 100%; word-break: break-word; line-height: 1.3;"><i class="fas fa-lock me-1"></i>Contract Partner (Confidential)</span>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.78rem;">
                                                            Space contracted for upcoming Day-Old Chicks intake.
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="p-2 bg-light rounded-3 mb-2 text-start small">
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span class="text-muted">Reserved By:</span>
                                                            <strong class="text-primary"><?php echo $isIntegrator ? 'Your Company (' . e($firstRes['customer_name']) . ')' : e($firstRes['customer_name']); ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span class="text-muted">Contract #:</span>
                                                            <span class="font-monospace text-muted"><?php echo e($firstRes['reservation_number']); ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-muted">Agreed Capacity:</span>
                                                            <strong><?php echo number_format($resBirdsSum); ?> birds</strong>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="text-primary fw-bold mb-1"><i class="fas fa-bookmark me-1"></i>House Space Reserved</div>
                                                <div class="text-muted mb-2">Reserved for upcoming flock intake (Capacity: <strong><?php echo number_format($hl['capacity']); ?> birds</strong>).</div>
                                            <?php endif; ?>

                                            <div class="p-2 mb-2 rounded-3 bg-light border text-start" style="font-size:0.75rem;">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-semibold text-dark"><i class="fas fa-lock text-primary me-1"></i>Dedicated Pen:</span>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">1 Integrator Only</span>
                                                </div>
                                                <div class="text-muted">
                                                    House is fully reserved for <strong><?php echo $isIntegrator ? 'your company' : e($firstRes['customer_name'] ?? 'Integrator'); ?></strong> (Capacity: <?php echo number_format($houseCap); ?> birds).
                                                </div>
                                            </div>

                                            <!-- Role-Specific Action for NEXT Step Only -->
                                            <?php if ($firstRes): ?>
                                                <?php if ($firstResWf === 'pending_approval'): ?>
                                                    <?php if (strtolower($user_role) === 'admin'): ?>
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-success w-100 rounded-pill fw-bold shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#reviewReservationModal<?php echo $firstResId; ?>" title="Conduct Feasibility Review & Approve Reservation (Step 2)">
                                                                <i class="fas fa-clipboard-check me-1"></i>Review &amp; Approve (Step 2)
                                                            </button>
                                                        </div>
                                                    <?php elseif (in_array(strtolower($user_role), ['worker', 'staff'])): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-clock text-info me-1"></i><strong>Pending Step 2:</strong> Awaiting Farm Admin review &amp; approval.</span>
                                                        </div>
                                                    <?php elseif ($isIntegrator): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-clock text-primary me-1"></i><strong>Step 1 Submitted:</strong> Awaiting Farm Admin review &amp; confirmation.</span>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php elseif ($firstResWf === 'approved'): ?>
                                                    <?php if (in_array(strtolower($user_role), ['worker', 'staff'])): ?>
                                                        <div class="mt-2">
                                                            <form method="POST" action="reservations.php" class="w-100 m-0">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="record_delivery_intake" value="1">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $firstResId; ?>">
                                                                <button type="button" class="btn btn-sm btn-warning text-dark w-100 rounded-pill fw-bold shadow-sm py-2" data-farm-confirm="Record chick delivery arrival at the farm for reservation #<?php echo e($firstRes['reservation_number']); ?>? This advances status to Step 3 (Receiving in Progress)." data-farm-title="Record Chick Delivery Intake" data-farm-type="warning" data-farm-btn="Confirm Arrival">
                                                                    <i class="fas fa-truck-fast me-1"></i>Record Chicks Arrived (Step 3)
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php elseif (strtolower($user_role) === 'admin'): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-info-circle text-success me-1"></i><strong>Step 2 Approved:</strong> Awaiting Farm Staff to record physical arrival on delivery day.</span>
                                                        </div>
                                                    <?php elseif ($isIntegrator): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-circle-check text-success me-1"></i><strong>Reservation Confirmed:</strong> Farm prepared for delivery on <?php echo !empty($firstRes['pickup_date']) ? date('M d, Y', strtotime($firstRes['pickup_date'])) : 'target date'; ?>.</span>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php elseif ($firstResWf === 'receiving'): ?>
                                                    <?php if (strtolower($user_role) === 'admin'): ?>
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-primary w-100 rounded-pill fw-bold shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#verifyReceiptModal<?php echo $firstResId; ?>" title="Verify Receipt & Activate Batch (Step 4 & 5)">
                                                                <i class="fas fa-clipboard-check me-1"></i>Verify Chick Receipt (Step 4)
                                                            </button>
                                                        </div>
                                                    <?php elseif (in_array(strtolower($user_role), ['worker', 'staff'])): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-spinner fa-spin text-warning me-1"></i><strong>Step 3 Recorded:</strong> Awaiting Farm Admin verification &amp; activation.</span>
                                                        </div>
                                                    <?php elseif ($isIntegrator): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-truck-ramp-box text-primary me-1"></i><strong>Chicks at Farm:</strong> Intake recorded, under admin verification.</span>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php elseif ($firstResWf === 'receipt_confirmed'): ?>
                                                    <?php if (strtolower($user_role) === 'admin'): ?>
                                                        <div class="mt-2">
                                                            <a href="batch.php?from_res=<?php echo $firstResId; ?>" class="btn btn-sm btn-success w-100 rounded-pill fw-bold shadow-sm py-2" title="Advance to Step 5: Activate Batch in Batch Management">
                                                                <i class="fas fa-layer-group me-1"></i>Advance to Step 5: Batch Registration
                                                            </a>
                                                        </div>
                                                    <?php elseif (in_array(strtolower($user_role), ['worker', 'staff'])): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-check-circle text-success me-1"></i><strong>Receipt Confirmed:</strong> Awaiting Farm Admin flock code &amp; Step 5 activation.</span>
                                                        </div>
                                                    <?php elseif ($isIntegrator): ?>
                                                        <div class="p-2 rounded-3 bg-light border text-start mt-2" style="font-size:0.78rem;">
                                                            <span class="text-muted"><i class="fas fa-check-double text-success me-1"></i><strong>Intake Verified:</strong> Admin is generating batch code and activating production flock.</span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                            <?php else: ?>
                                                <?php if (strtolower($user_role) === 'admin'): ?>
                                                    <div class="d-flex gap-1 mt-2">
                                                        <a href="batch.php?house=<?php echo urlencode($hl['house_name']); ?>" class="btn btn-sm btn-outline-primary flex-grow-1 rounded-pill fw-bold shadow-sm">
                                                            <i class="fas fa-plus me-1"></i>Stock House
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                    <?php elseif ($hl['status'] === 'cleaning'): ?>
                                        <div class="mt-2 small bg-white p-3 rounded-3 border">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="text-warning fw-bold"><i class="fas fa-broom me-1"></i>Cleaning & Sanitizing</div>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">In Progress</span>
                                            </div>
                                            <div class="text-muted mb-2">Pen is undergoing disinfection protocol (Capacity: <strong><?php echo number_format($hl['capacity']); ?> birds</strong>).</div>
                                            
                                            <?php if (strtolower($user_role) === 'worker'): ?>
                                                <form method="POST" class="mb-2" id="markAvailableForm_<?php echo (int)$hl['house_id']; ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="mark_house_available" value="1">
                                                    <input type="hidden" name="house_id" value="<?php echo (int)$hl['house_id']; ?>">
                                                    <button type="button" class="btn btn-sm btn-success w-100 rounded-pill fw-bold shadow-sm" onclick="promptHouseCleaning('markAvailableForm_<?php echo (int)$hl['house_id']; ?>', 'mark_available', '<?php echo addslashes(htmlspecialchars($hl['house_name'])); ?>')">
                                                        <i class="fas fa-check-double me-1"></i>Mark Cleaning Complete (Available)
                                                    </button>
                                                </form>
                                            <?php elseif (in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                                <div class="small text-muted fst-italic mb-2 text-center bg-light p-2 rounded-2 border">
                                                    <i class="fas fa-user-shield me-1 text-warning"></i>Cleaning in progress • Worker completion required
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($isIntegrator): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-pill fw-bold" onclick="prefillReserveHouse(<?php echo (int)$hl['house_id']; ?>, '<?php echo e($hl['house_name']); ?>', <?php echo (int)$hl['capacity']; ?>)">
                                                <i class="fas fa-calendar-plus me-1"></i>Reserve Upcoming Intake (Step 1)
                                            </button>
                                            <?php else: ?>
                                            <div class="p-2 text-center bg-light rounded-3 border text-muted small mb-2" style="font-size:0.75rem;">
                                                <div class="fw-bold text-dark mb-1" style="font-size: 0.78rem;">
                                                    <i class="fas fa-clock text-warning me-1"></i>Waiting for Integrator Submission (Step 1)
                                                </div>
                                                <div class="text-secondary" style="font-size: 0.72rem;">
                                                    Pen is in sanitation. Contract Integrator can reserve for upcoming intake.
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: ?>
                                        <div class="mt-2 small bg-white p-3 rounded-3 border">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <?php if ($isIntegrator): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size:0.70rem;">
                                                    <i class="fas fa-door-open me-1"></i>Step 1: Open to Reserve
                                                </span>
                                                <?php else: ?>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill font-monospace" style="font-size:0.70rem;">
                                                    <i class="fas fa-hourglass-start me-1"></i>Waiting for Submission (Step 1)
                                                </span>
                                                <?php endif; ?>
                                                <span class="badge bg-secondary-subtle text-muted border rounded-pill" style="font-size:0.68rem;">
                                                    <i class="fas fa-lock me-1"></i>Step 2 Locked
                                                </span>
                                            </div>
                                            <div class="text-success fw-bold mb-1"><i class="fas fa-check-double me-1"></i>Vacant, Cleaned &amp; Ready</div>
                                            <div class="text-muted mb-2" style="font-size:0.78rem;">
                                                Can accommodate up to <strong><?php echo number_format($hl['capacity']); ?> Day-Old Chicks</strong>.
                                                <div class="mt-1 text-secondary" style="font-size:0.72rem; line-height: 1.35; word-break: break-word;">
                                                    <i class="fas fa-arrow-down-short-wide me-1"></i><strong>Sequential Rule:</strong> Step 1 (Reservation) must be submitted by Integrator before Admin Feasibility Review (Step 2) or Intake (Step 3).
                                                </div>
                                            </div>
                                            <?php if ($isIntegrator): ?>
                                            <button type="button" class="btn btn-sm btn-success w-100 rounded-pill fw-bold mb-2 shadow-sm" onclick="prefillReserveHouse(<?php echo (int)$hl['house_id']; ?>, '<?php echo e($hl['house_name']); ?>', <?php echo (int)$hl['capacity']; ?>)">
                                                <i class="fas fa-calendar-plus me-1"></i>Submit Reservation (Step 1)
                                            </button>
                                            <?php else: ?>
                                            <div class="p-2 text-center bg-light rounded-3 border text-muted small mb-2" style="font-size:0.75rem;">
                                                <div class="fw-bold text-dark mb-1" style="font-size: 0.78rem;">
                                                    <i class="fas fa-clock text-warning me-1"></i>Waiting for Integrator Submission (Step 1)
                                                </div>
                                                <div class="text-secondary" style="font-size: 0.72rem; line-height: 1.35; word-break: break-word;">
                                                    The contract Integrator partner must submit pen reservation. (View Only for <?php echo ucfirst($user_role); ?> &bull; Step 2 Review unlocks after Step 1)
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!$isIntegrator): ?>
                                                <?php if (in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                                    <form method="POST" class="m-0" id="markCleaningForm_<?php echo (int)$hl['house_id']; ?>">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="mark_house_cleaning" value="1">
                                                        <input type="hidden" name="house_id" value="<?php echo (int)$hl['house_id']; ?>">
                                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark w-100 rounded-pill fw-semibold shadow-sm mb-1" onclick="promptHouseCleaning('markCleaningForm_<?php echo (int)$hl['house_id']; ?>', 'set_cleaning', '<?php echo addslashes(htmlspecialchars($hl['house_name'])); ?>')">
                                                            <i class="fas fa-broom me-1"></i>Set House to Cleaning Mode
                                                        </button>
                                                    </form>
                                                <?php elseif (strtolower($user_role) === 'worker'): ?>
                                                    <div class="small text-muted text-center bg-light p-2 rounded-2 border">
                                                        <i class="fas fa-check-circle text-success me-1"></i>Pen Clean & Disinfected
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!$isIntegrator && $isOccupied && !empty($houseReservations)): 
                                $firstRes = $houseReservations[0];
                                $hasMultiple = count($houseReservations) > 1;
                                $curLiveW = (float)($readiness['current_weight'] ?? 1.80);
                                $defaultBirds = min((int)$firstRes['quantity_birds'], (int)$hl['active_headcount']);
                                $defaultUnitP = (float)($firstRes['unit_price'] ?: 140.00);
                                $defaultLiveNetW = round($defaultBirds * $curLiveW, 2);
                                $defaultTotalP = (float)$firstRes['total_amount'] > 0 ? (float)$firstRes['total_amount'] : round($defaultLiveNetW * $defaultUnitP, 2);
                            ?>
                            <!-- MODAL: Admin Inspect & Dispatch Chicks for <?php echo e($hl['house_name']); ?> -->
                            <div class="modal fade text-start" id="dispatchHouseModal<?php echo (int)$hl['house_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down modal-lg">
                                    <div class="modal-content border-0 rounded-4 shadow">
                                        <form method="POST">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="house_id" value="<?php echo (int)$hl['house_id']; ?>">
                                            <input type="hidden" name="reservation_id" id="dispatchSelectRes_<?php echo $hl['house_id']; ?>" value="<?php echo (int)$firstRes['reservation_id']; ?>">
                                            <div class="modal-header bg-success text-white border-0 rounded-top-4">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="fas fa-paper-plane me-2"></i>Inspect & Dispatch Chicks &mdash; <?php echo e($hl['house_name']); ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-3 p-md-4">
                                                <!-- Release Clearance Banner -->
                                                <div class="alert alert-success d-flex align-items-center py-2 px-3 rounded-3 mb-3 border-0">
                                                    <i class="fas fa-check-double fs-3 me-3 text-success"></i>
                                                    <div>
                                                        <strong>Agreement Met &bull; Ready for Admin Dispatch!</strong>
                                                        <div class="small">
                                                            Batch <strong><?php echo e($hl['active_batch_code']); ?></strong> live body weight average is <strong><?php echo number_format($curLiveW, 2); ?> kg</strong> (&ge; agreed weight <?php echo number_format($readiness['target_weight'], 2); ?> kg) &bull; Agreed Date: <strong><?php echo !empty($readiness['agreed_date']) ? date('M d, Y', strtotime($readiness['agreed_date'])) : 'N/A'; ?></strong>.
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Partner / Reserving Company Section -->
                                                <div class="p-3 bg-light rounded-3 mb-3 border">
                                                    <div class="row g-2 align-items-center">
                                                        <div class="col-12 col-md-7">
                                                            <label class="form-label fw-bold text-muted small text-uppercase mb-1">
                                                                <i class="fas fa-building text-primary me-1"></i>Reserving Partner / Integrator
                                                            </label>
                                                            <div class="fs-5 fw-bold text-primary">
                                                                <?php echo e($firstRes['customer_name']); ?>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2 fs-6 font-monospace"><?php echo e($firstRes['reservation_number']); ?></span>
                                                            </div>
                                                            <div class="text-muted small mt-1">
                                                                <i class="fas fa-phone me-1"></i>Contact: <?php echo e($firstRes['customer_phone'] ?? 'N/A'); ?> &bull; Booked: <strong><?php echo number_format($firstRes['quantity_birds']); ?> birds</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-5 text-md-end">
                                                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill font-monospace">
                                                                <i class="fas fa-feather text-primary me-1"></i>Breed: <strong><?php echo e($firstRes['breed'] ?? 'Cobb 500'); ?></strong>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php 
                                                $dispTot = (float)$firstRes['total_amount'];
                                                $dispAdv = (float)$firstRes['advance_payment'];
                                                $dispBal = max(0, round($dispTot - $dispAdv, 2));
                                                $dispPaid = ($dispBal <= 0.001);
                                                $dispAlreadyDone = !empty($firstRes['is_dispatched']);
                                                ?>
                                                <div class="card border rounded-3 mb-3 shadow-none bg-white">
                                                    <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                                        <span class="small fw-bold text-dark"><i class="fas fa-file-invoice-dollar me-1 text-primary"></i>Contract Payment Status:</span>
                                                        <span class="badge <?php echo $dispPaid ? 'bg-success' : 'bg-danger'; ?> rounded-pill px-3 py-1 font-monospace">
                                                            <?php echo $dispPaid ? '100% Fully Paid' : 'Balance Due: ₱' . number_format($dispBal, 2); ?>
                                                        </span>
                                                    </div>
                                                    <div class="card-body p-2 px-3">
                                                        <div class="row g-2 text-center small">
                                                            <div class="col-4">
                                                                <span class="text-muted d-block" style="font-size:0.75rem;">Total Contract</span>
                                                                <span class="fw-bold font-monospace">₱<?php echo number_format($dispTot, 2); ?></span>
                                                            </div>
                                                            <div class="col-4">
                                                                <span class="text-muted d-block" style="font-size:0.75rem;">Advance Paid</span>
                                                                <span class="fw-bold font-monospace text-success">₱<?php echo number_format($dispAdv, 2); ?></span>
                                                            </div>
                                                            <div class="col-4">
                                                                <span class="text-muted d-block" style="font-size:0.75rem;">Remaining Balance</span>
                                                                <span class="fw-bold font-monospace <?php echo $dispPaid ? 'text-success' : 'text-danger'; ?>">
                                                                    ₱<?php echo number_format($dispBal, 2); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if (!$dispPaid): ?>
                                                    <!-- PAYMENT GATE LOCKED ALERT -->
                                                    <div class="alert alert-danger rounded-3 p-3 mb-3 border-0">
                                                        <div class="d-flex align-items-start gap-2">
                                                            <i class="fas fa-shield-halved fs-3 text-danger flex-shrink-0 mt-1"></i>
                                                            <div class="flex-grow-1">
                                                                <h6 class="fw-bold mb-1 text-danger">Dispatch Gate Locked: Awaiting Full Payment</h6>
                                                                <p class="small mb-0 text-danger-emphasis">
                                                                    The chicks cannot be dispatched until the integrator has settled the full contract balance. Outstanding balance: <strong class="text-danger">₱<?php echo number_format($dispBal, 2); ?></strong>. Once the integrator pays in full, the dispatch button will unlock.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php elseif ($dispAlreadyDone): ?>
                                                    <!-- ALREADY DISPATCHED BY ADMIN -->
                                                    <div class="alert alert-success rounded-3 p-3 mb-3 border-0">
                                                        <div class="d-flex align-items-start gap-2">
                                                            <i class="fas fa-check-circle fs-3 text-success flex-shrink-0 mt-1"></i>
                                                            <div class="flex-grow-1">
                                                                <h6 class="fw-bold mb-1 text-success">Flock Officially Dispatched by Farm Admin</h6>
                                                                <p class="small mb-0 text-muted">
                                                                    Dispatched on <strong><?php echo date('M d, Y h:i A', strtotime($firstRes['dispatched_at'])); ?></strong>. The integrator has been authorized to send hauler trucks to pick up the chicks.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- PAYMENT GATE CLEARED: READY FOR ADMIN DISPATCH -->
                                                    <div class="alert alert-success rounded-3 p-2 px-3 mb-3 border-0">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <i class="fas fa-circle-check fs-4 text-success flex-shrink-0"></i>
                                                            <div>
                                                                <span class="fw-bold text-success d-block small">Contract 100% Fully Paid (₱0.00 Balance) &bull; Verified</span>
                                                                <span class="small text-muted" style="font-size:0.78rem;">Flock inspection complete. Admin can now officially dispatch the chicks to enable integrator truck pick up.</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Admin Dispatch Notes / Inspection Clearance</label>
                                                        <input type="text" name="dispatch_notes" class="form-control" placeholder="e.g., Biosecurity inspection passed, flock health verified, cleared for hauling">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                                <?php if (!$dispPaid): ?>
                                                    <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" disabled title="Outstanding balance must be settled by integrator first">
                                                        <i class="fas fa-lock me-1"></i>Dispatch Locked (Unpaid Balance)
                                                    </button>
                                                <?php elseif ($dispAlreadyDone): ?>
                                                    <button type="button" class="btn btn-success rounded-pill px-4 fw-bold" disabled>
                                                        <i class="fas fa-check-double me-1"></i>Already Dispatched
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="admin_dispatch_chicks" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                                        <i class="fas fa-paper-plane me-1"></i>Dispatch Chicks to Integrator
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php 
                            if (!empty($firstRes)) {
                                $hResId = (int)$firstRes['reservation_id'];
                                $hResWf = $firstRes['workflow_stage'] ?? 'pending_approval';

                                if ($isIntegrator && !isset($renderedSettleModals[$hResId])) {
                                    echo renderManualSettlementModalHTML($firstRes);
                                    $renderedSettleModals[$hResId] = true;
                                }
                                if ($hResWf === 'pending_approval' && in_array(strtolower($user_role), ['admin', 'staff']) && !isset($renderedReviewModals[$hResId])) {
                                    echo renderStep2ReviewModalHTML($firstRes, $conn);
                                    $renderedReviewModals[$hResId] = true;
                                }
                                if ($hResWf === 'receiving' && in_array(strtolower($user_role), ['admin', 'staff']) && !isset($renderedVerifyModals[$hResId])) {
                                    echo renderStep4VerifyModalHTML($firstRes);
                                    $renderedVerifyModals[$hResId] = true;
                                }
                            }
                            ?>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4 rounded-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo e($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4 rounded-3" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="p-3 bg-light rounded-4 mb-4 border">
                    <?php if ($isIntegrator): ?>
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-bold text-wrap text-start" style="font-size: 0.78rem; max-width: 100%; word-break: break-word; line-height: 1.4; display: inline-block;">
                                <i class="fas fa-shield-halved me-1"></i>Confidential Portal: Viewing <strong><?php echo e($integratorCustomer['name'] ?? 'Your Company'); ?></strong> Records Only
                            </span>
                            <span class="small text-muted text-nowrap"><i class="fas fa-lock text-success me-1"></i>Protected Data Isolation</span>
                        </div>
                    <?php endif; ?>
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-12 col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="<?php echo $isIntegrator ? 'Search reservation #, batch...' : 'Search customer, reservation #, batch...'; ?>" value="<?php echo e($searchQuery); ?>">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="fulfilled" <?php echo $statusFilter === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <select name="batch_id" class="form-select">
                                <option value="0">All Broiler Batches</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?php echo $b['batch_id']; ?>" <?php echo $batchFilter === (int)$b['batch_id'] ? 'selected' : ''; ?>>
                                        <?php echo e($b['batch_code']); ?> (<?php echo number_format($b['current_quantity']); ?> birds)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm" style="background: linear-gradient(135deg, #163023 0%, #2E5B43 100%); border: none;"><i class="fas fa-filter me-1"></i>Filter</button>
                            <a href="reservations.php" class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 38px; flex-shrink: 0;" title="Reset"><i class="fas fa-rotate-left"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Reservations Table (summary only — full details via View modal) -->
                <div class="table-responsive custom-scrollbar table-mobile-cards-wrap reservations-summary-wrap" style="max-height: 650px; overflow: auto;">
                    <table class="table table-hover align-middle table-mobile-cards reservations-summary-table">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="min-width: 120px;">Reservation #</th>
                                <th style="min-width: 140px;"><?php echo $isIntegrator ? 'Company' : 'Integrator'; ?></th>
                                <th style="min-width: 120px;">House</th>
                                <th class="text-end" style="min-width: 90px;">Birds</th>
                                <th style="min-width: 100px;">Pickup</th>
                                <th class="text-center" style="min-width: 130px;">Stage</th>
                                <th class="text-center" style="min-width: 160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block text-muted"></i>
                                    <?php echo $isIntegrator ? 'No reservations found for your company account.' : 'No broiler reservations found matching current criteria.'; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($reservations as $r): ?>
                            <?php 
                            $resId = (int)$r['reservation_id'];
                            $targetWeightBird = !empty($r['target_weight_per_bird']) ? (float)$r['target_weight_per_bird'] : 2.20;
                            $effectiveBId = !empty($r['effective_batch_id']) ? (int)$r['effective_batch_id'] : (!empty($r['batch_id']) ? (int)$r['batch_id'] : 0);
                            $resReadiness = !empty($effectiveBId) ? check_release_readiness($conn, $effectiveBId, $targetWeightBird, $r['pickup_date'] ?? null) : null;
                            $tBal = max(0, round((float)($r['total_amount'] ?? 0) - (float)($r['advance_payment'] ?? 0), 2));
                            $rWf = $r['workflow_stage'] ?? (!empty($r['batch_id']) ? 'growing' : 'pending_approval');
                            $rWfSummary = match($rWf) {
                                'pending_approval' => ['label' => 'Step 2 · Pending', 'class' => 'bg-warning-subtle text-dark border border-warning-subtle'],
                                'approved' => ['label' => 'Step 3 · Delivery', 'class' => 'bg-info-subtle text-info border border-info-subtle'],
                                'receiving' => ['label' => 'Step 4 · Verify', 'class' => 'bg-primary-subtle text-primary border border-primary-subtle'],
                                'receipt_confirmed' => ['label' => 'Step 5 · Activated', 'class' => 'bg-success-subtle text-success border border-success-subtle'],
                                'growing' => ['label' => 'Step 5 · Growing', 'class' => 'bg-success-subtle text-success border border-success-subtle'],
                                default => ['label' => 'Step 5 · Growing', 'class' => 'bg-light text-muted border']
                            };
                            ?>
                            <tr>
                                <td data-label="Reservation #">
                                    <div class="mobile-cell-val">
                                        <span class="fw-bold text-primary font-monospace">
                                            <?php echo e($r['reservation_number']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="<?php echo $isIntegrator ? 'Company' : 'Integrator'; ?>">
                                    <div class="mobile-cell-val">
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 180px;" title="<?php echo e($r['customer_name']); ?>">
                                            <?php echo e($r['customer_name']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="House">
                                    <div class="mobile-cell-val">
                                        <div class="fw-semibold text-dark small">
                                            <?php echo !empty($r['house_name']) ? e($r['house_name']) : '<span class="text-muted">Unassigned</span>'; ?>
                                        </div>
                                        <?php if (!empty($r['batch_code'])): ?>
                                            <small class="text-primary font-monospace"><?php echo e($r['batch_code']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">No batch yet</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Birds" class="text-end">
                                    <div class="mobile-cell-val">
                                        <span class="fw-bold text-dark"><?php echo number_format((int)$r['display_quantity']); ?></span>
                                    </div>
                                </td>
                                <td data-label="Pickup">
                                    <div class="mobile-cell-val">
                                        <span class="text-dark small"><?php echo date('M d, Y', strtotime($r['pickup_date'])); ?></span>
                                    </div>
                                </td>
                                <td data-label="Stage" class="text-center">
                                    <div class="mobile-cell-val align-items-center">
                                        <span class="badge rounded-pill <?php echo $rWfSummary['class']; ?> px-2 py-1 text-nowrap" style="font-size: 0.72rem;" title="<?php echo e(ucfirst((string)$r['status'])); ?>">
                                            <?php echo e($rWfSummary['label']); ?>
                                        </span>
                                        <?php if ($tBal <= 0.001): ?>
                                            <small class="text-success"><i class="fas fa-check-circle me-1"></i>Paid</small>
                                        <?php else: ?>
                                            <small class="text-danger">Bal ₱<?php echo number_format($tBal, 0); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Actions" class="text-center text-nowrap" style="min-width: 160px;">
                                    <?php if ($isIntegrator): ?>
                                        <div class="d-flex justify-content-center align-items-center gap-1 flex-nowrap">
                                            <?php if ($r['status'] === 'active'): ?>
                                                <?php if ($resReadiness && $resReadiness['ready']): ?>
                                                    <?php if ($tBal <= 0.001): ?>
                                                        <?php if (!empty($r['is_dispatched'])): ?>
                                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo $resId; ?>" title="Flock officially dispatched! Click to confirm pick up and truck hauling">
                                                                <i class="fas fa-truck-fast me-1"></i>Pick Up
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo $resId; ?>" title="Fully paid! Awaiting Admin flock inspection & dispatch clearance before pickup">
                                                                <i class="fas fa-clock text-dark me-1"></i>Awaiting Dispatch
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo $resId; ?>" title="Outstanding balance must be settled before pick up">
                                                            <i class="fas fa-lock text-danger me-1"></i>Pick Up (Pay Bal)
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#integratorPickupModal<?php echo $resId; ?>" title="Check contract balance & pick up readiness">
                                                        <i class="fas fa-truck-pickup me-1"></i>Pick Up Gate
                                                    </button>
                                                <?php endif; ?>
                                                <?php 
                                                if (!isset($renderedIntegratorModals[$resId])) {
                                                    echo renderIntegratorPickupModalHTML($r, $resReadiness, $r['house_name'] ?? '');
                                                    $renderedIntegratorModals[$resId] = true;
                                                }
                                                ?>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-1 text-muted" data-bs-toggle="modal" data-bs-target="#viewResModal<?php echo $resId; ?>" title="View reservation particulars">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                    <div class="d-flex justify-content-center align-items-center gap-1 flex-nowrap">
                                        <?php 
                                        $rowTot = (float)$r['total_amount'];
                                        $rowAdv = (float)$r['advance_payment'];
                                        $rowBal = max(0, round($rowTot - $rowAdv, 2));
                                        $rowPaid = ($rowBal <= 0.001);
                                        ?>
                                        <?php if ($rWf === 'pending_approval' && in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-2 py-1 fw-bold shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#reviewReservationModal<?php echo $resId; ?>" title="Conduct Feasibility Review &amp; Approve Reservation (Step 2)">
                                                <i class="fas fa-clipboard-check me-1"></i>Approve
                                            </button>
                                        <?php elseif ($rWf === 'approved' && in_array(strtolower($user_role), ['admin', 'staff', 'worker'])): ?>
                                            <form method="POST" action="reservations.php" class="d-inline m-0">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="record_delivery_intake" value="1">
                                                <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                                <button type="button" class="btn btn-sm btn-warning text-dark rounded-pill px-2 py-1 fw-bold shadow-sm text-nowrap" data-farm-confirm="Record chick delivery arrival at the farm for reservation #<?php echo e($r['reservation_number']); ?>? This advances status to Step 3 (Receiving in Progress)." data-farm-title="Record Chick Delivery Intake" data-farm-type="warning" data-farm-btn="Confirm Arrival" title="Record Chick Delivery (Step 3)">
                                                    <i class="fas fa-truck-fast me-1"></i>Arrived
                                                </button>
                                            </form>
                                        <?php elseif ($rWf === 'receiving' && in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-2 py-1 fw-bold shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#verifyReceiptModal<?php echo $resId; ?>" title="Verify Receipt &amp; Activate Batch (Step 4 &amp; 5)">
                                                <i class="fas fa-clipboard-check me-1"></i>Verify
                                            </button>
                                        <?php elseif ($rWf === 'growing' && in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 fw-semibold text-nowrap" data-bs-toggle="modal" data-bs-target="#viewResModal<?php echo $resId; ?>" title="Step 5 Completed: View Details">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                        <?php elseif ($rWf === 'receipt_confirmed' && in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                            <?php 
                                            $tblBatchLink = "batch.php?from_res=" . (int)$resId . (!empty($r['house_name']) ? "&house=" . urlencode($r['house_name']) : "");
                                            ?>
                                            <a href="<?php echo $tblBatchLink; ?>" class="btn btn-sm btn-success rounded-pill px-2 py-1 fw-bold text-nowrap" title="Advance to Step 5: Activate Batch in Batch Management">
                                                <i class="fas fa-layer-group me-1"></i>Activate
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($r['status'] === 'active' && $resReadiness && $resReadiness['ready'] && in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                            <?php if ($rowPaid): ?>
                                                <?php if (!empty($r['is_dispatched'])): ?>
                                                    <span class="badge bg-info-subtle text-dark border border-info-subtle rounded-pill px-2 py-1" title="Chicks officially dispatched. Waiting for partner truck pick up.">
                                                        <i class="fas fa-check-double text-primary me-1"></i>Dispatched
                                                    </span>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success rounded-pill px-2 py-1 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#adminDispatchModal<?php echo $resId; ?>" title="Weight target verified & balance fully paid! Click to dispatch chicks to partner">
                                                        <i class="fas fa-paper-plane me-1"></i>Dispatch
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-warning text-dark rounded-pill px-2 py-1 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#adminDispatchModal<?php echo $resId; ?>" title="Awaiting integrator full payment before dispatch (Balance: ₱<?php echo number_format($rowBal, 2); ?>)">
                                                    <i class="fas fa-lock text-danger me-1"></i>Pay &amp; Dispatch
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (strtolower($user_role) === 'worker'): ?>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-1 text-muted" data-bs-toggle="modal" data-bs-target="#viewResModal<?php echo $resId; ?>" title="View reservation particulars">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                        <?php else: ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-2 text-nowrap" type="button" data-bs-toggle="dropdown" title="More actions">
                                                More
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#viewResModal<?php echo $resId; ?>">
                                                        <i class="fas fa-eye text-primary me-2"></i>View Details
                                                    </a>
                                                </li>

                                                <?php if ($r['status'] === 'active' && !$rowPaid && $isIntegrator): ?>
                                                <li>
                                                    <a class="dropdown-item text-primary fw-semibold" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#settleResModal<?php echo $resId; ?>">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Settle Balance (₱<?php echo number_format($rowBal, 2); ?>)
                                                    </a>
                                                </li>
                                                <?php endif; ?>

                                                <?php if (in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($rWf === 'receiving'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-primary fw-bold" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#verifyReceiptModal<?php echo $resId; ?>">
                                                            <i class="fas fa-clipboard-check me-2"></i>Verify Receipt &amp; Activate Batch
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <?php if ($r['status'] === 'active'): ?>
                                                        <?php if (empty($r['is_dispatched'])): ?>
                                                        <li>
                                                            <a class="dropdown-item text-primary fw-semibold" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#adminDispatchModal<?php echo $resId; ?>">
                                                                <i class="fas fa-paper-plane me-2"></i>Dispatch Chicks to Partner
                                                            </a>
                                                        </li>
                                                        <?php else: ?>
                                                        <li>
                                                            <span class="dropdown-item text-success fw-semibold">
                                                                <i class="fas fa-check-circle me-2"></i>Dispatched to Partner
                                                            </span>
                                                        </li>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <!-- Edit Details (Admin can always edit) -->
                                                <li>
                                                    <a class="dropdown-item text-dark" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editResModal<?php echo $resId; ?>">
                                                        <i class="fas fa-edit text-muted me-2"></i>Edit Reservation
                                                    </a>
                                                </li>
                                                <?php if ($r['status'] !== 'cancelled'): ?>
                                                <!-- Cancel -->
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                                        <button type="button" name="cancel_reservation" value="1" class="dropdown-item text-danger" data-farm-confirm="Cancel reservation #<?php echo e($r['reservation_number']); ?>? This will release reserved capacity." data-farm-title="Cancel Reservation" data-farm-type="danger" data-farm-btn="Yes, Cancel">
                                                            <i class="fas fa-ban me-2"></i>Cancel Reservation
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>

                                                <?php if ($r['status'] === 'cancelled'): ?>
                                                <!-- Restore / Uncancel -->
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                                        <button type="button" name="restore_reservation" value="1" class="dropdown-item text-success fw-bold" data-farm-confirm="Restore this cancelled reservation back to Active? This will allow it to be processed." data-farm-title="Restore Reservation" data-farm-type="info" data-farm-btn="Yes, Restore">
                                                            <i class="fas fa-undo me-2"></i>Restore (Uncancel)
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>

                                                <!-- Delete Permanently -->
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                                        <button type="button" name="delete_reservation" value="1" class="dropdown-item text-danger" data-farm-confirm="Permanently delete reservation #<?php echo e($r['reservation_number']); ?>? This removes it completely from the database." data-farm-title="Delete Reservation" data-farm-type="danger" data-farm-btn="Delete Permanently">
                                                            <i class="fas fa-trash-alt me-2"></i>Delete Permanently
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- View modal buffered to page end (avoid overflow:hidden parents) -->
                                    <?php 
                                    if (!isset($renderedViewModals[$resId])) {
                                        $viewResModalBuffer[$resId] = renderViewReservationModalHTML($r, $user_role);
                                        $renderedViewModals[$resId] = true;
                                    }
                                    ?>

                                    <!-- MODAL: Admin Inspection & Flock Dispatch Clearance (#<?php echo $resId; ?>) -->
                                    <?php if (!$isIntegrator && $r['status'] === 'active' && in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                    <div class="modal fade text-start" id="adminDispatchModal<?php echo $resId; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                                            <div class="modal-content border-0 rounded-4 shadow">
                                                <form method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                                    <div class="modal-header border-0 rounded-top-4 text-white" style="background: linear-gradient(135deg, #163023 0%, #2E5B43 100%);">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="fas fa-paper-plane me-2" style="color: #C29A4B !important;"></i>Admin Flock Dispatch Clearance
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-3 p-md-4">
                                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted small">Reservation #:</span>
                                                                <span class="fw-bold font-monospace text-primary"><?php echo e($r['reservation_number']); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted small">Customer / Partner:</span>
                                                                <span class="fw-bold"><?php echo e($r['customer_name']); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted small">Pen / House:</span>
                                                                <span class="fw-bold text-dark"><i class="fas fa-warehouse text-success me-1"></i><?php echo e($r['house_name'] ?? 'Pen'); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted small">Chick Breed:</span>
                                                                <span class="fw-bold font-monospace text-dark"><i class="fas fa-feather-alt text-primary me-1"></i><?php echo e($r['breed'] ?? 'Cobb 500'); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted small">Agreed Birds:</span>
                                                                <span class="fw-bold text-primary"><?php echo number_format((int)$r['display_quantity']); ?> birds</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted small">Agreed Target Weight:</span>
                                                                <span class="fw-bold text-dark font-monospace"><?php echo number_format($targetWeightBird, 2); ?> kg/bird</span>
                                                            </div>
                                                            <?php if ($resReadiness): ?>
                                                            <div class="d-flex justify-content-between">
                                                                <span class="text-muted small">Live Flock Sample:</span>
                                                                <span class="fw-bold font-monospace <?php echo $resReadiness['ready'] ? 'text-success' : 'text-danger'; ?>">
                                                                    <?php echo number_format($resReadiness['current_weight'], 2); ?> kg (<?php echo $resReadiness['ready'] ? 'Agreement Met' : 'Underweight'; ?>)
                                                                </span>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Release Weight Gate Banner -->
                                                        <?php if ($resReadiness && $resReadiness['ready']): ?>
                                                            <div class="alert alert-success d-flex align-items-center mb-3 py-2 px-3 rounded-3 border-0">
                                                                <i class="fas fa-check-circle fs-4 me-2 text-success"></i>
                                                                <div>
                                                                    <strong>Flock Weight & Agreement Met!</strong>
                                                                    <div class="small">Live sample average (<strong><?php echo number_format($resReadiness['current_weight'], 2); ?> kg</strong>) meets or exceeds target (<strong><?php echo number_format($resReadiness['target_weight'], 2); ?> kg</strong>). Check full payment balance below to dispatch.</div>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($resReadiness && !$resReadiness['ready']): ?>
                                                            <div class="alert alert-warning d-flex align-items-center mb-3 py-2 px-3 rounded-3 border-0">
                                                                <i class="fas fa-exclamation-triangle fs-4 me-2 text-warning"></i>
                                                                <div>
                                                                    <strong>Notice: Flock Below Target Weight</strong>
                                                                    <div class="small">Live sample is <strong><?php echo number_format($resReadiness['current_weight'], 2); ?> kg</strong> vs agreed target <strong><?php echo number_format($resReadiness['target_weight'], 2); ?> kg</strong>.</div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php 
                                                        $dispTot = (float)$r['total_amount'];
                                                        $dispAdv = (float)$r['advance_payment'];
                                                        $dispBal = max(0, round($dispTot - $dispAdv, 2));
                                                        $dispPaid = ($dispBal <= 0.001);
                                                        ?>
                                                        <div class="card border rounded-3 mb-3 shadow-none bg-white">
                                                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                                                <span class="small fw-bold text-dark"><i class="fas fa-file-invoice-dollar me-1 text-primary"></i>Contract Payment Status:</span>
                                                                <span class="badge <?php echo $dispPaid ? 'bg-success' : 'bg-danger'; ?> rounded-pill px-3 py-1 font-monospace">
                                                                    <?php echo $dispPaid ? '100% Fully Paid' : 'Balance Due: ₱' . number_format($dispBal, 2); ?>
                                                                </span>
                                                            </div>
                                                            <div class="card-body p-2 px-3">
                                                                <div class="row g-2 text-center small">
                                                                    <div class="col-4">
                                                                        <span class="text-muted d-block" style="font-size:0.75rem;">Total Contract</span>
                                                                        <span class="fw-bold font-monospace">₱<?php echo number_format($dispTot, 2); ?></span>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <span class="text-muted d-block" style="font-size:0.75rem;">Advance Paid</span>
                                                                        <span class="fw-bold font-monospace text-success">₱<?php echo number_format($dispAdv, 2); ?></span>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <span class="text-muted d-block" style="font-size:0.75rem;">Remaining Balance</span>
                                                                        <span class="fw-bold font-monospace <?php echo $dispPaid ? 'text-success' : 'text-danger'; ?>">
                                                                            ₱<?php echo number_format($dispBal, 2); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <?php if (!$dispPaid): ?>
                                                            <!-- PAYMENT GATE LOCKED ALERT -->
                                                            <div class="alert alert-danger rounded-3 p-3 mb-2 border-0">
                                                                <div class="d-flex align-items-start gap-2">
                                                                    <i class="fas fa-lock fs-4 text-danger flex-shrink-0 mt-1"></i>
                                                                    <div class="flex-grow-1">
                                                                        <h6 class="fw-bold mb-1 text-danger">Dispatch Locked: Integrator Balance Unpaid</h6>
                                                                        <p class="small mb-2 text-danger-emphasis">
                                                                            Before admin can dispatch the chicks, you must check if the integrator has 100% fully paid the balance. Outstanding balance: <strong class="text-danger">₱<?php echo number_format($dispBal, 2); ?></strong>. Integrator cannot pick up dayon until fully paid and officially dispatched.
                                                                        </p>
                                                                        <div class="text-end">
                                                                            <button type="submit" name="send_balance_reminder" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold shadow-sm">
                                                                                <i class="fas fa-bell me-1"></i>Send Payment Reminder to Integrator
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($resReadiness && !$resReadiness['ready']): ?>
                                                            <div class="alert alert-warning rounded-3 p-3 mb-2 border-0">
                                                                <div class="d-flex align-items-start gap-2">
                                                                    <i class="fas fa-lock fs-4 text-warning flex-shrink-0 mt-1"></i>
                                                                    <div>
                                                                        <h6 class="fw-bold mb-1 text-dark">Dispatch Locked: Flock Growing to Agreement Standards</h6>
                                                                        <p class="small mb-0 text-muted">
                                                                            Both agreed pickup date and target weight must be completed before chicks can be dispatched to the partner. Live weight: <strong><?php echo number_format($resReadiness['current_weight'], 2); ?> kg</strong> (Target: <strong><?php echo number_format($resReadiness['target_weight'], 2); ?> kg</strong>).
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php elseif (!empty($r['is_dispatched'])): ?>
                                                            <div class="alert alert-info rounded-3 p-3 mb-2 border-0">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <i class="fas fa-check-double fs-4 text-primary flex-shrink-0"></i>
                                                                    <div>
                                                                        <strong class="text-dark">Already Officially Dispatched!</strong>
                                                                        <div class="small text-muted">Dispatched on <?php echo date('M d, Y h:i A', strtotime($r['dispatched_at'])); ?>. The integrator is cleared to authorize truck hauling and pick up the chicks.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="alert alert-success rounded-3 p-2 px-3 mb-2 border-0">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <i class="fas fa-circle-check fs-4 text-success flex-shrink-0"></i>
                                                                    <div>
                                                                        <span class="fw-bold text-success d-block small">Agreement &amp; Payment Verified &bull; Ready for Release</span>
                                                                        <span class="small text-muted" style="font-size:0.78rem;">Target weight met &amp; 100% fully paid (₱0.00 balance). Proceed to officially dispatch the flock so the integrator can pick up the chicks.</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Admin Dispatch Notes / Clearance Remarks</label>
                                                                <textarea name="dispatch_notes" class="form-control" rows="2" placeholder="Flock inspection passed, health clearance certified, authorized for partner truck hauling..."></textarea>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                                        <?php if (!$dispPaid): ?>
                                                            <button type="submit" name="send_balance_reminder" class="btn btn-warning text-dark rounded-pill px-3 fw-bold shadow-sm">
                                                                <i class="fas fa-bell me-1"></i>Send Reminder
                                                            </button>
                                                            <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" disabled title="Outstanding balance must be settled first">
                                                                <i class="fas fa-lock me-1"></i>Dispatch Locked (Unpaid Balance)
                                                            </button>
                                                        <?php elseif ($resReadiness && !$resReadiness['ready']): ?>
                                                            <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" disabled title="Flock has not met agreed standards">
                                                                <i class="fas fa-lock me-1"></i>Dispatch Locked (Flock Not Ready)
                                                            </button>
                                                        <?php elseif (empty($r['is_dispatched'])): ?>
                                                            <button type="submit" name="admin_dispatch_chicks" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                                                <i class="fas fa-paper-plane me-1"></i>Authorize & Dispatch Chicks to Partner
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($rWf === 'pending_approval' && in_array(strtolower($user_role), ['admin', 'staff'])): ?>
                                        <?php 
                                        if (!isset($renderedReviewModals[$resId])) {
                                            echo renderStep2ReviewModalHTML($r, $conn);
                                            $renderedReviewModals[$resId] = true;
                                        }
                                        ?>
                                    <?php endif; ?>

                                    <?php if (in_array(strtolower($user_role), ['admin', 'staff']) && $rWf === 'receiving'): ?>
                                        <?php 
                                        if (!isset($renderedVerifyModals[$resId])) {
                                            echo renderStep4VerifyModalHTML($r);
                                            $renderedVerifyModals[$resId] = true;
                                        }
                                        ?>
                                    <?php endif; ?>

                                    <!-- MODAL: Edit Reservation -->
                                    <div class="modal fade text-start" id="editResModal<?php echo $resId; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down modal-lg">
                                            <div class="modal-content border-0 rounded-4 shadow">
                                                <form method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
                                                    <div class="modal-header bg-light border-0 rounded-top-4">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="fas fa-edit text-primary me-2"></i>Edit Reservation #<?php echo e($r['reservation_number']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-3 p-md-4">
                                                        <div class="row g-2 g-md-3 mb-3">
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label fw-bold">Retailer</label>
                                                                <select name="customer_id" class="form-select" required>
                                                                    <?php foreach ($customers as $c): ?>
                                                                        <option value="<?php echo $c['customer_id']; ?>" <?php echo $c['customer_id'] == $r['customer_id'] ? 'selected' : ''; ?>>
                                                                            <?php echo e($c['name']); ?> (<?php echo e($c['customer_code']); ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label fw-bold">Broiler Batch <span class="text-danger">*</span></label>
                                                                <select name="batch_id" class="form-select border-primary" required>
                                                                    <option value="" disabled>-- Select Broiler Batch * --</option>
                                                                    <?php foreach ($batches as $b): ?>
                                                                        <option value="<?php echo $b['batch_id']; ?>" <?php echo $b['batch_id'] == $r['batch_id'] ? 'selected' : ''; ?>>
                                                                            <?php echo e($b['batch_code']); ?> (<?php echo number_format($b['current_quantity']); ?> birds)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <small class="text-muted">Must belong to an active broiler batch</small>
                                                            </div>
                                                        </div>

                                                        <div class="row g-2 g-md-3 mb-3">
                                                            <div class="col-6 col-md-2">
                                                                <label class="form-label fw-bold">Reserved Birds</label>
                                                                <input type="number" min="1" inputmode="numeric" name="quantity_birds" class="form-control edit-res-birds" value="<?php echo (int)$r['display_quantity']; ?>" required>
                                                            </div>
                                                            <div class="col-6 col-md-2">
                                                                <label class="form-label fw-bold">Chick Breed</label>
                                                                <input type="text" list="breedListOptions" name="breed" class="form-control fw-bold" value="<?php echo e($r['breed'] ?? 'Cobb 500'); ?>" placeholder="Select or type breed">
                                                            </div>
                                                            <div class="col-6 col-md-2">
                                                                <label class="form-label fw-bold text-primary">Chick Price (₱)</label>
                                                                <input type="number" step="0.01" min="0" inputmode="decimal" name="chick_cost" class="form-control font-monospace fw-bold text-primary" value="<?php echo !empty($r['chick_cost']) ? (float)$r['chick_cost'] : 38.00; ?>" placeholder="38.00" title="Day-Old Chick (DOC) rate per head">
                                                            </div>
                                                            <div class="col-6 col-md-2">
                                                                <label class="form-label fw-bold">Total Wt (kg)</label>
                                                                <input type="number" step="0.01" min="0" inputmode="decimal" name="weight_kg" class="form-control" value="<?php echo !empty($r['weight_kg']) ? (float)$r['weight_kg'] : ''; ?>">
                                                            </div>
                                                            <div class="col-6 col-md-2">
                                                                <label class="form-label fw-bold">Unit Price (₱)</label>
                                                                <input type="number" step="0.01" min="0" inputmode="decimal" name="unit_price" class="form-control" value="<?php echo !empty($r['unit_price']) ? (float)$r['unit_price'] : ''; ?>">
                                                            </div>
                                                            <div class="col-12 col-md-2">
                                                                <label class="form-label fw-bold">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="active" <?php echo $r['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                                    <option value="fulfilled" <?php echo $r['status'] == 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                                                                    <option value="cancelled" <?php echo $r['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="row g-2 g-md-3 mb-3">
                                                            <div class="col-12 col-md-4">
                                                                <label class="form-label fw-bold">Total Amount (₱)</label>
                                                                <input type="number" step="0.01" min="0" inputmode="decimal" name="total_amount" class="form-control" value="<?php echo (float)($r['total_amount'] ?? 0); ?>" required>
                                                            </div>
                                                            <div class="col-12 col-md-4">
                                                                <label class="form-label fw-bold">Advance Payment (₱)</label>
                                                                <input type="number" step="0.01" min="0" inputmode="decimal" name="advance_payment" class="form-control" value="<?php echo (float)($r['advance_payment'] ?? 0); ?>">
                                                            </div>
                                                            <div class="col-12 col-md-4">
                                                                <label class="form-label fw-bold">Expected Pickup Date <span class="text-danger">*</span></label>
                                                                <input type="date" name="pickup_date" class="form-control" value="<?php echo $r['pickup_date']; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                                                <small class="text-muted" style="font-size:0.72rem;">Min: Today. Past dates blocked.</small>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Notes</label>
                                                            <textarea name="notes" class="form-control" rows="2"><?php echo e($r['notes'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_reservation" class="btn btn-primary rounded-pill px-4 fw-bold">
                                                            <i class="fas fa-save me-1"></i>Save Changes
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <?php 
                                    if ($isIntegrator && !isset($renderedSettleModals[$resId])) {
                                        echo renderManualSettlementModalHTML($r);
                                        $renderedSettleModals[$resId] = true;
                                    }
                                    ?>

                                    <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Reservations pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                            $queryParams = $_GET;
                        ?>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <?php $queryParams['page'] = $page - 1; ?>
                            <a class="page-link" href="?<?php echo http_build_query($queryParams); ?>">Previous</a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php $queryParams['page'] = $i; ?>
                                <a class="page-link" href="?<?php echo http_build_query($queryParams); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <?php $queryParams['page'] = $page + 1; ?>
                            <a class="page-link" href="?<?php echo http_build_query($queryParams); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (strtolower($user_role) !== 'worker'): ?>
<!-- MODAL: New Broiler Reservation -->
<div class="modal fade text-start" id="newBroilerReservationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down modal-lg">
        <div class="modal-content border-0 rounded-4 shadow bg-white">
            <form method="POST" id="newBroilerReservationForm" class="d-flex flex-column h-100 bg-white" onsubmit="return handleNewResSubmit(this);">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="create_broiler_reservation" value="1">
                <div class="modal-header border-0 rounded-top-4 flex-shrink-0 text-white" style="background: linear-gradient(135deg, #163023 0%, #2E5B43 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-calendar-plus me-2" style="color: #C29A4B !important;"></i>Step 1: Submit Chick &amp; Pen Reservation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 p-md-4 bg-white flex-grow-1">
                    <div class="alert alert-info py-2 px-3 rounded-3 small mb-3">
                        <i class="fas fa-circle-info me-1"></i><strong>Step 1 in Workflow:</strong> Submitting this reservation assigns the pen space and creates the contract request. Once submitted, the reservation advances to <strong>Step 2 (Farm Admin Feasibility Review &amp; Approval)</strong>. Farm staff cannot receive chicks (Step 3) until both Step 1 and Step 2 are completed.
                    </div>

                    <div class="row g-2 g-md-3 mb-3">
                        <div class="col-12 col-md-3">
                            <?php if ($isIntegrator && !empty($integratorCustomer)): ?>
                                <label class="form-label fw-bold">Reserving Partner / Integrator</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-primary border"><i class="fas fa-building"></i></span>
                                    <input type="text" class="form-control bg-light fw-bold text-dark border" value="<?php echo e($integratorCustomer['name']); ?> (<?php echo e($integratorCustomer['customer_code']); ?>)" readonly disabled>
                                </div>
                                <input type="hidden" name="customer_id" id="newResCustomerId" value="<?php echo (int)$integratorCustomerId; ?>">
                                <small class="text-success"><i class="fas fa-shield-alt me-1"></i>Locked to your company account</small>
                            <?php else: ?>
                                <label class="form-label fw-bold">Partner / Integrator <span class="text-danger">*</span></label>
                                <select name="customer_id" id="newResCustomerId" class="form-select" required>
                                    <option value="">-- Choose Partner / Retailer --</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?php echo $c['customer_id']; ?>">
                                            <?php echo e($c['name']); ?> (<?php echo e($c['customer_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">Reserve Pen / House <span class="text-danger">*</span></label>
                            <select name="house_id" id="newResHouseId" class="form-select" onchange="onHouseSelectChanged(this)" required>
                                <option value="">-- Select Vacant House / Pen --</option>
                                <?php foreach ($allHouses as $h): 
                                    $hCap = (int)($h['capacity'] > 0 ? $h['capacity'] : 5000);
                                    $isOccupied = ($h['status'] === 'occupied' || !empty($h['current_batch_id']));
                                    $hasActiveRes = ((int)($h['active_res_count'] ?? 0) > 0);
                                    $isReserved = ($hasActiveRes || $h['status'] === 'reserved');
                                    $isDisabled = ($isOccupied || $isReserved);
                                    
                                    $hStatusLabel = match(true) {
                                        $isOccupied => '🔒 Step 5: Active Batch Growth (Pen Occupied)',
                                        $isReserved => '🔒 Reserved (1 Integrator Exclusive Pen)',
                                        $h['status'] === 'cleaning' => '🟡 Cleaning / Ready for Intake (Cap: ' . number_format($hCap) . ' birds)',
                                        default => '🟢 Vacant & Available (Cap: ' . number_format($hCap) . ' birds)'
                                    };
                                    $resNoSafe = ($isIntegrator) ? 'Booked' : ($h['active_res_number'] ?? '');
                                ?>
                                    <option value="<?php echo $h['house_id']; ?>" 
                                        data-capacity="<?php echo $hCap; ?>" 
                                        data-total-capacity="<?php echo $hCap; ?>"
                                        data-committed="<?php echo $isReserved ? $hCap : 0; ?>"
                                        data-remaining="<?php echo $isDisabled ? 0 : $hCap; ?>"
                                        data-status="<?php echo $h['status']; ?>" 
                                        data-reserved="<?php echo $isReserved ? '1' : '0'; ?>"
                                        data-occupied="<?php echo $isOccupied ? '1' : '0'; ?>"
                                        data-resno="<?php echo e($resNoSafe); ?>"
                                        data-name="<?php echo e($h['house_name']); ?>" 
                                        <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                        <?php echo e($h['house_name']); ?> (Cap: <?php echo number_format($hCap); ?> birds) - <?php echo $hStatusLabel; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><?php echo $isIntegrator ? 'Select a house with available capacity' : 'Reserves available pen capacity for incoming flock'; ?></small>
                        </div>
                        <div class="col-12 col-md-3">
                            <?php if ($isIntegrator): ?>
                                <label class="form-label fw-bold">Flock Intake Type</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-success border"><i class="fas fa-egg"></i></span>
                                    <select name="batch_id" id="newResBatchId" class="form-select bg-light fw-bold text-dark border">
                                        <option value="0" selected>Incoming Flock / Pre-Intake (Vacant Pen)</option>
                                    </select>
                                </div>
                                <small class="text-muted">Direct pen reservation for company chicks</small>
                            <?php else: ?>
                                <label class="form-label fw-bold">Active Flock / Batch</label>
                                <select name="batch_id" id="newResBatchId" class="form-select border-primary fw-semibold" onchange="onBatchSelectChanged(this)">
                                    <option value="0">-- Incoming Flock / Pre-Intake (Vacant Pen) --</option>
                                    <?php foreach ($batches as $b): 
                                        $w = (float)($b['current_weight_kg'] ?? 0);
                                        $tw = (float)($b['target_weight_kg'] ?? 1.80);
                                        $wStatus = $w >= $tw ? "🟢 {$w}kg (Ready)" : "⏳ {$w}kg / {$tw}kg";
                                        $hPen = !empty($b['house_pen']) ? " [{$b['house_pen']}]" : "";
                                    ?>
                                        <option value="<?php echo $b['batch_id']; ?>" data-quantity="<?php echo $b['current_quantity']; ?>" data-weight="<?php echo $w; ?>" data-target="<?php echo $tw; ?>" data-house="<?php echo e($b['house_pen'] ?? ''); ?>" data-chick-cost="<?php echo (float)($b['chick_unit_cost'] ?? 38.00); ?>">
                                            <?php echo e($b['batch_code']); ?><?php echo $hPen; ?> (<?php echo number_format($b['current_quantity']); ?> birds) - <?php echo $wStatus; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Link to active batch or book pen for incoming flock intake</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">Chick Breed <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary border"><i class="fas fa-feather-alt"></i></span>
                                <input type="text" list="breedListOptions" name="breed" id="newResBreed" class="form-control fw-bold" placeholder="Select or type breed" value="Cobb 500" required>
                            </div>
                            <datalist id="breedListOptions">
                                <option value="Cobb 500">
                                <option value="Ross 308">
                                <option value="Hubbard Classic">
                                <option value="Arbor Acres Plus">
                                <option value="SASSO">
                                <option value="Kabir">
                                <option value="Heritage Red Broiler">
                                <option value="Dekalb White">
                            </datalist>
                            <small class="text-muted">Select from list or type custom breed</small>
                        </div>
                    </div>

                    <!-- Cycle Timeline & Standards Section -->
                    <div class="card border border-success-subtle rounded-4 p-3 mb-3 bg-light-subtle shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <span class="badge bg-success rounded-circle p-2"><i class="fas fa-calendar-week text-white"></i></span>
                                <span>Cycle Timeline &amp; Standards</span>
                            </h6>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 font-monospace small">
                                <i class="fas fa-calculator me-1"></i>Standard Automated
                            </span>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 0.80rem;">
                            Select cycle duration standard. Arrival date is locked to today or future (no past dates like 10/12/2026). Expected harvest date and contract target weight auto-compute based on growth curves.
                        </p>

                        <!-- Cycle Selector Pills / Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-4">
                                <div class="card border border-success h-100 p-3 rounded-3 cursor-pointer cycle-choice-card shadow-sm" id="cycleCard35" onclick="selectCycleOption(35)" style="cursor: pointer; background-color: #ecfdf5; transition: all 0.2s;">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><i class="fas fa-clock text-success me-1"></i>Day 35 Standard</div>
                                            <small class="text-muted">5 Weeks &bull; Standard Broiler</small>
                                        </div>
                                        <span class="badge bg-success rounded-pill px-2 py-1 small" id="badge35">Selected</span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <div><i class="fas fa-weight-scale text-success me-1"></i>Target Wt: <strong>2.20 kg</strong></div>
                                        <div><i class="fas fa-drumstick-bite text-primary me-1"></i>DOC Price: <strong class="docBenchmarkPrice">₱38.00</strong></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="card border h-100 p-3 rounded-3 cursor-pointer cycle-choice-card shadow-sm" id="cycleCard45" onclick="selectCycleOption(45)" style="cursor: pointer; background-color: #ffffff; transition: all 0.2s;">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><i class="fas fa-clock text-primary me-1"></i>Day 45 Heavy</div>
                                            <small class="text-muted">~6.5 Weeks &bull; Heavy Roaster</small>
                                        </div>
                                        <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small" id="badge45">Select</span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <div><i class="fas fa-weight-scale text-success me-1"></i>Target Wt: <strong>2.90 kg</strong></div>
                                        <div><i class="fas fa-drumstick-bite text-primary me-1"></i>DOC Price: <strong class="docBenchmarkPrice">₱38.00</strong></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="card border h-100 p-3 rounded-3 cursor-pointer cycle-choice-card shadow-sm" id="cycleCardCustom" onclick="selectCycleOption('custom')" style="cursor: pointer; background-color: #ffffff; transition: all 0.2s;">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><i class="fas fa-sliders-h text-warning me-1"></i>Manual Custom Standard</div>
                                            <small class="text-muted">35+ Days &bull; Custom Target Wt</small>
                                        </div>
                                        <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small" id="badgeCustom">Select</span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <div><i class="fas fa-weight-scale text-success me-1"></i>Target Wt: <strong>Manual (≥ 2.20 kg)</strong></div>
                                        <div><i class="fas fa-calendar-alt text-primary me-1"></i>Harvest: <strong>35+ Days (Strict)</strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="cycle_days" id="selectedCycleDays" value="35">

                        <!-- Interactive Timeline & Date Controls -->
                        <div class="row g-2 g-md-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small text-dark">
                                    <i class="fas fa-plane-arrival text-success me-1"></i>Arrival Date *
                                </label>
                                <input type="date" name="arrival_date" id="newResArrivalDate" class="form-control fw-bold font-monospace" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required onchange="onArrivalDateChanged()">
                                <small class="text-muted" style="font-size: 0.72rem;">Intake date (Min: <?php echo date('m/d/Y'); ?>)</small>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small text-dark">
                                    <i class="fas fa-calendar-check text-primary me-1"></i>Expected Harvest *
                                </label>
                                <input type="date" name="pickup_date" id="newResPickupDate" class="form-control fw-bold font-monospace bg-light text-dark" value="<?php echo date('Y-m-d', strtotime('+35 days')); ?>" min="<?php echo date('Y-m-d', strtotime('+35 days')); ?>" required onchange="onPickupDateChanged()">
                                <small class="text-success fw-semibold" id="harvestDurationHint" style="font-size: 0.72rem;">Day 35: +35 days from arrival</small>
                                <div id="harvestDateError" class="text-danger fw-bold small mt-1" style="display: none; font-size: 0.75rem;"></div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-bold small text-dark mb-0">
                                        <i class="fas fa-coins text-warning me-1"></i>Chick Price (₱) *
                                    </label>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0" style="font-size: 0.68rem;"><i class="fas fa-pen me-1"></i>Editable</span>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">₱</span>
                                    <input type="number" step="0.01" min="0" name="chick_cost" id="newResChickCost" class="form-control font-monospace fw-bold text-primary" value="38.00" required oninput="onChickCostChanged()">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted" style="font-size: 0.72rem;">Day-Old Chick (DOC) rate</small>
                                    <small class="text-muted" style="font-size: 0.72rem;">DOC Total: <span class="fw-bold text-dark font-monospace" id="docTotalInvestmentDisplay">₱190,000.00</span></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 g-md-3 mb-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-bold">Reserved Birds <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="5000" inputmode="numeric" name="quantity_birds" id="newResBirds" class="form-control fw-bold" placeholder="e.g., 5000" value="5000" required oninput="calcNewResBalance(); onChickCostChanged();">
                            <small class="text-muted d-block">Max 5,000 birds per pen</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-bold text-success">Agreed Target Wt (kg) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="2.20" max="5.0" inputmode="decimal" name="target_weight_per_bird" id="newResTargetWeight" class="form-control fw-bold text-success bg-light" value="2.20" readonly required oninput="onTargetWeightChanged()">
                            <small class="text-muted d-block" id="targetWeightHint">Min 2.20 kg (Strict standard)</small>
                            <div id="targetWeightError" class="text-danger fw-bold small mt-1" style="display: none; font-size: 0.75rem;"></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-bold">Estimated Live Wt (kg)</label>
                            <input type="number" step="0.01" min="0" inputmode="decimal" name="weight_kg" id="newResWeight" class="form-control font-monospace" placeholder="e.g., 11000.00" oninput="calcNewResBalance()">
                            <small class="text-muted d-block">Total live kilograms</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-bold">Agreed Unit Price (₱)</label>
                            <input type="number" step="0.01" min="0" inputmode="decimal" name="unit_price" id="newResUnitPrice" class="form-control font-monospace" placeholder="e.g., 180.00" value="180.00" oninput="calcNewResBalance()">
                            <small class="text-muted d-block">Rate per live kilogram</small>
                        </div>
                    </div>

                    <div class="row g-2 g-md-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Total Contract Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" inputmode="decimal" name="total_amount" id="newResTotal" class="form-control font-monospace fw-bold" placeholder="0.00" required oninput="calcNewResBalance(true)">
                            <small class="text-muted d-block">Gross contract value</small>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Advance Payment (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" inputmode="decimal" name="advance_payment" id="newResAdvance" class="form-control font-monospace" value="0.00" required oninput="calcNewResBalance(true)">
                            <small class="text-muted d-block">Booking deposit / downpayment</small>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold text-danger">Remaining Balance (₱)</label>
                            <div class="p-2 bg-light rounded border text-danger fw-bold fs-5 text-center font-monospace" id="newResBalanceDisplay">₱0.00</div>
                            <small class="text-danger d-block text-center mt-1"><i class="fas fa-lock me-1"></i>Must be fully settled before release</small>
                        </div>
                    </div>

                    <div class="row g-2 g-md-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Contract Notes / Logistics &amp; Crate Protocol</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="e.g., 2.20kg target body weight standard agreement, plastic poultry crates, biosecurity disinfection..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white flex-shrink-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_broiler_reservation" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-paper-plane me-1"></i>Submit Chick Reservation (Complete Step 1)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php else: ?>
<!-- #newBroilerReservationModal omitted for worker role (Workers cannot create reservations; authorized for house cleaning actions only) -->
<?php endif; ?>

<!-- MODAL: Quick Weight Sampling (for Release Verification) -->
<?php if (!$isIntegrator): ?>
<div class="modal fade text-start" id="quickWeighModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 rounded-4 shadow">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="batch_id" id="qwBatchId">
                <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-weight-scale me-2"></i>Record Flock Weight Sampling</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">House / Pen Location:</span>
                            <span class="fw-bold" id="qwHouseName">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Active Flock / Batch:</span>
                            <span class="fw-bold text-primary" id="qwBatchCode">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Current Birds (Alive):</span>
                            <span class="fw-bold text-dark" id="qwAliveBirds">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Agreed Contract Target:</span>
                            <span class="fw-bold text-success" id="qwTargetWeight">1.80 kg</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Sampling Date <span class="text-danger">*</span></label>
                        <input type="date" name="sample_date" id="qwSampleDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" onchange="updateAgeFromSampleDate(this.value)" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">Average Weight <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" style="font-size:0.72rem;" onclick="autoFillQuickWeighModal()" title="Auto-fill Cobb 500 estimated weight based on flock age">
                                    <i class="fas fa-wand-magic-sparkles text-warning me-1"></i>Auto-Fill
                                </button>
                            </div>
                            <input type="number" step="0.01" min="0.01" max="5.0" inputmode="decimal" name="weight_kg" id="qwWeight" class="form-control font-monospace fs-5 fw-bold text-primary" placeholder="e.g. 1.85" required>
                            <small class="text-muted" id="qwEstHint">Live average bird weight</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Sample Count (Birds)</label>
                            <input type="number" min="1" inputmode="numeric" name="birds_sampled" id="qwBirdsSampled" class="form-control" value="50">
                            <small class="text-muted" id="qwBirdsSampledHint">All alive birds inside house</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Weighing Notes / Observations</label>
                        <textarea name="notes" id="qwNotes" class="form-control" rows="2" placeholder="e.g., Flock weight uniform, healthy activity, no ascites..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="record_quick_weight" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i>Save Weight Sample
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: House Cleaning & Sanitation Confirmation (Signature Alquizar Aesthetic) -->
<div class="modal fade text-start" id="houseCleaningConfirmModal" tabindex="-1" aria-labelledby="cleaningModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden bg-white">
            <div class="modal-header border-0 text-white p-3 p-md-4 position-relative" style="background: linear-gradient(135deg, #163023 0%, #2E5B43 100%);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 46px; height: 46px; background: rgba(194, 154, 75, 0.25); border: 2px solid #C29A4B;">
                        <i class="fas fa-broom fs-5" id="cleaningModalIcon" style="color: #F8EED4;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="cleaningModalTitle">House Cleaning Action</h5>
                        <small class="text-white-50" id="cleaningModalSubtitle" style="font-size: 0.78rem;">Biosecurity &amp; Pen Sanitation</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-white text-center">
                <div class="p-3 rounded-3 mb-3 border text-center" style="background-color: #F8FAF9;" id="cleaningModalAlertBox">
                    <div class="fw-bold text-dark fs-5 mb-1" id="cleaningModalHouseName">House Location</div>
                    <div class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-3 py-1 font-monospace mb-2" id="cleaningModalBadge">
                        Cleaning Action
                    </div>
                    <p class="text-muted small mb-0" id="cleaningModalDesc">
                        Set this poultry house to Cleaning mode for washing and biosecurity sanitation?
                    </p>
                </div>
                <div class="alert alert-info py-2 px-3 rounded-3 text-start small mb-0 d-flex align-items-start gap-2 border">
                    <i class="fas fa-shield-virus mt-1 text-success"></i>
                    <div>
                        <strong class="text-dark">Biosecurity Standard:</strong>
                        <span class="text-muted" id="cleaningModalTip">Sanitizing vacant pens prepares the environment for incoming day-old chicks and eliminates pathogens.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 pt-0 bg-white d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light rounded-pill px-4 text-muted fw-semibold" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm" id="cleaningModalConfirmBtn">
                    <i class="fas fa-broom me-1"></i>Set to Cleaning Mode
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let totalEditedManually = false;
let targetCleaningFormId = null;

function promptHouseCleaning(formId, actionType, houseName) {
    targetCleaningFormId = formId;
    const modalEl = document.getElementById('houseCleaningConfirmModal');
    if (!modalEl) {
        const form = document.getElementById(formId);
        if (form) form.submit();
        return;
    }

    const titleEl = document.getElementById('cleaningModalTitle');
    const subTitleEl = document.getElementById('cleaningModalSubtitle');
    const iconEl = document.getElementById('cleaningModalIcon');
    const houseNameEl = document.getElementById('cleaningModalHouseName');
    const badgeEl = document.getElementById('cleaningModalBadge');
    const descEl = document.getElementById('cleaningModalDesc');
    const tipEl = document.getElementById('cleaningModalTip');
    const btnEl = document.getElementById('cleaningModalConfirmBtn');

    if (houseNameEl) houseNameEl.innerText = houseName;

    if (actionType === 'set_cleaning') {
        if (titleEl) titleEl.innerText = 'House Sanitation & Cleaning';
        if (subTitleEl) subTitleEl.innerText = 'Biosecurity Washing Protocol';
        if (iconEl) iconEl.className = 'fas fa-broom fs-5';
        if (badgeEl) {
            badgeEl.className = 'badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-3 py-1 font-monospace mb-2';
            badgeEl.innerText = 'Set to Cleaning Mode';
        }
        if (descEl) descEl.innerHTML = 'Set <strong>' + houseName + '</strong> to Cleaning mode for washing and biosecurity sanitation?';
        if (tipEl) tipEl.innerText = 'Setting to cleaning mode alerts farm staff that disinfection is in progress before chick intake.';
        if (btnEl) {
            btnEl.className = 'btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm';
            btnEl.innerHTML = '<i class="fas fa-broom me-1"></i>Set to Cleaning Mode';
            btnEl.disabled = false;
        }
    } else { // mark_available
        if (titleEl) titleEl.innerText = 'House Readiness Verification';
        if (subTitleEl) subTitleEl.innerText = 'Washing & Disinfection Complete';
        if (iconEl) iconEl.className = 'fas fa-check-double fs-5';
        if (badgeEl) {
            badgeEl.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 font-monospace mb-2';
            badgeEl.innerText = 'Ready for Chick Intake';
        }
        if (descEl) descEl.innerHTML = 'Confirm that <strong>' + houseName + '</strong> has completed washing & disinfection, and is ready for Day-Old Chicks intake?';
        if (tipEl) tipEl.innerText = 'Marking as available updates house status in real-time, allowing new incoming flocks or pre-reserved batches to be housed.';
        if (btnEl) {
            btnEl.className = 'btn btn-success rounded-pill px-4 fw-bold shadow-sm';
            btnEl.innerHTML = '<i class="fas fa-check-double me-1"></i>Mark Ready & Available';
            btnEl.disabled = false;
        }
    }

    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('cleaningModalConfirmBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (targetCleaningFormId) {
                const targetForm = document.getElementById(targetCleaningFormId);
                if (targetForm) {
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                    targetForm.submit();
                }
            }
        });
    }

    const newResModalEl = document.getElementById('newBroilerReservationModal');
    if (newResModalEl) {
        newResModalEl.addEventListener('show.bs.modal', function() {
            onChickCostChanged();
        });
    }
});

function selectCycleOption(days) {
    const cycleInput = document.getElementById('selectedCycleDays');
    if (cycleInput) cycleInput.value = days;

    const card35 = document.getElementById('cycleCard35');
    const card45 = document.getElementById('cycleCard45');
    const cardCustom = document.getElementById('cycleCardCustom');
    const badge35 = document.getElementById('badge35');
    const badge45 = document.getElementById('badge45');
    const badgeCustom = document.getElementById('badgeCustom');
    const targetW = document.getElementById('newResTargetWeight');
    const pickInput = document.getElementById('newResPickupDate');
    const targetHint = document.getElementById('targetWeightHint');

    // Reset all cards
    [card35, card45, cardCustom].forEach(c => {
        if (c) {
            c.style.borderColor = '#e2e8f0';
            c.style.backgroundColor = '#ffffff';
        }
    });
    if (badge35) { badge35.className = 'badge bg-light text-muted border rounded-pill px-2 py-1 small'; badge35.innerText = 'Select'; }
    if (badge45) { badge45.className = 'badge bg-light text-muted border rounded-pill px-2 py-1 small'; badge45.innerText = 'Select'; }
    if (badgeCustom) { badgeCustom.className = 'badge bg-light text-muted border rounded-pill px-2 py-1 small'; badgeCustom.innerText = 'Select'; }

    if (days === 35 || days === '35') {
        if (card35) {
            card35.style.borderColor = '#10b981';
            card35.style.backgroundColor = '#ecfdf5';
        }
        if (badge35) {
            badge35.className = 'badge bg-success rounded-pill px-2 py-1 small';
            badge35.innerText = 'Selected';
        }
        if (targetW) {
            targetW.value = '2.20';
            targetW.min = '2.20';
            targetW.readOnly = true;
            targetW.classList.add('bg-light');
            targetW.classList.remove('is-invalid');
        }
        if (pickInput) {
            pickInput.readOnly = true;
            pickInput.classList.add('bg-light');
            pickInput.classList.remove('is-invalid');
        }
        if (targetHint) targetHint.innerHTML = 'Day 35 Standard: <strong>2.20 kg</strong> (Locked)';
        const dateErr = document.getElementById('harvestDateError');
        if (dateErr) dateErr.style.display = 'none';
        const wtErr = document.getElementById('targetWeightError');
        if (wtErr) wtErr.style.display = 'none';
        calcHarvestTimeline();
    } else if (days === 45 || days === '45') {
        if (card45) {
            card45.style.borderColor = '#2563eb';
            card45.style.backgroundColor = '#eff6ff';
        }
        if (badge45) {
            badge45.className = 'badge bg-primary rounded-pill px-2 py-1 small';
            badge45.innerText = 'Selected';
        }
        if (targetW) {
            targetW.value = '2.90';
            targetW.min = '2.20';
            targetW.readOnly = true;
            targetW.classList.add('bg-light');
            targetW.classList.remove('is-invalid');
        }
        if (pickInput) {
            pickInput.readOnly = true;
            pickInput.classList.add('bg-light');
            pickInput.classList.remove('is-invalid');
        }
        if (targetHint) targetHint.innerHTML = 'Day 45 Heavy: <strong>2.90 kg</strong> (Locked)';
        const dateErr = document.getElementById('harvestDateError');
        if (dateErr) dateErr.style.display = 'none';
        const wtErr = document.getElementById('targetWeightError');
        if (wtErr) wtErr.style.display = 'none';
        calcHarvestTimeline();
    } else { // 'custom'
        if (cardCustom) {
            cardCustom.style.borderColor = '#C29A4B';
            cardCustom.style.backgroundColor = '#FCF9EE';
        }
        if (badgeCustom) {
            badgeCustom.className = 'badge bg-warning text-dark border rounded-pill px-2 py-1 small fw-bold';
            badgeCustom.innerText = 'Selected';
        }
        if (targetW) {
            targetW.readOnly = false;
            targetW.classList.remove('bg-light');
            targetW.min = '2.20';
            if (parseFloat(targetW.value) < 2.20) {
                targetW.value = '2.20';
            }
        }
        if (pickInput) {
            pickInput.readOnly = false;
            pickInput.classList.remove('bg-light');
        }
        if (targetHint) targetHint.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-edit me-1"></i>Manual Entry: Strictly ≥ 2.20 kg</span>';
        validateCustomTimeline();
    }
}

function onArrivalDateChanged() {
    const cycleInput = document.getElementById('selectedCycleDays');
    if (cycleInput && cycleInput.value === 'custom') {
        validateCustomTimeline();
    } else {
        calcHarvestTimeline();
    }
}

function onPickupDateChanged() {
    const cycleInput = document.getElementById('selectedCycleDays');
    if (cycleInput && cycleInput.value === 'custom') {
        validateCustomTimeline();
    } else {
        calcNewResBalance();
    }
}

function onTargetWeightChanged() {
    const targetW = document.getElementById('newResTargetWeight');
    const weightErrBox = document.getElementById('targetWeightError');
    if (targetW) {
        const val = parseFloat(targetW.value) || 0;
        if (val < 2.20) {
            if (weightErrBox) {
                weightErrBox.style.display = 'block';
                weightErrBox.innerText = `⚠️ Strict Standard: Target weight cannot be below 2.20 kg (entered: ${val.toFixed(2)} kg).`;
            }
            targetW.classList.add('is-invalid');
        } else {
            if (weightErrBox) weightErrBox.style.display = 'none';
            targetW.classList.remove('is-invalid');
        }
    }
    calcNewResBalance();
}

function validateCustomTimeline() {
    const arrInput = document.getElementById('newResArrivalDate');
    const pickInput = document.getElementById('newResPickupDate');
    const hint = document.getElementById('harvestDurationHint');
    const errBox = document.getElementById('harvestDateError');
    const targetW = document.getElementById('newResTargetWeight');
    const weightErrBox = document.getElementById('targetWeightError');

    let arrDate = arrInput ? arrInput.value : '';
    if (!arrDate) {
        arrDate = new Date().toISOString().split('T')[0];
        if (arrInput) arrInput.value = arrDate;
    }

    // Earliest allowed harvest date is arrival + 35 days
    const minHarvestDate = new Date(arrDate + 'T00:00:00');
    minHarvestDate.setDate(minHarvestDate.getDate() + 35);
    const minHarvestStr = minHarvestDate.toISOString().split('T')[0];

    if (pickInput) {
        pickInput.min = minHarvestStr;
        if (!pickInput.value || pickInput.value < minHarvestStr) {
            pickInput.value = minHarvestStr;
        }
    }

    let pickDate = pickInput ? pickInput.value : minHarvestStr;
    const curPick = new Date(pickDate + 'T00:00:00');
    const curArr = new Date(arrDate + 'T00:00:00');
    const diffDays = Math.round((curPick - curArr) / (1000 * 60 * 60 * 24));

    if (diffDays < 35) {
        if (errBox) {
            errBox.style.display = 'block';
            errBox.innerText = `⚠️ Strict Standard: Harvest date must be 35+ days from arrival (currently ${diffDays} days). Cannot be below 35 days.`;
        }
        if (pickInput) pickInput.classList.add('is-invalid');
    } else {
        if (errBox) errBox.style.display = 'none';
        if (pickInput) pickInput.classList.remove('is-invalid');
    }

    if (hint) {
        hint.innerHTML = `<span class="text-primary fw-bold"><i class="fas fa-sliders-h me-1"></i>Custom: Day ${diffDays} (+${diffDays} days from intake)</span>`;
    }

    if (targetW) {
        const val = parseFloat(targetW.value) || 0;
        if (val < 2.20) {
            if (weightErrBox) {
                weightErrBox.style.display = 'block';
                weightErrBox.innerText = `⚠️ Strict Standard: Target weight cannot be below 2.20 kg (entered: ${val.toFixed(2)} kg).`;
            }
            targetW.classList.add('is-invalid');
        } else {
            if (weightErrBox) weightErrBox.style.display = 'none';
            targetW.classList.remove('is-invalid');
        }
    }

    calcNewResBalance();
}

function calcHarvestTimeline() {
    const arrInput = document.getElementById('newResArrivalDate');
    const pickInput = document.getElementById('newResPickupDate');
    const cycleInput = document.getElementById('selectedCycleDays');
    const days = cycleInput ? (parseInt(cycleInput.value) || 35) : 35;

    let arrDate = arrInput ? arrInput.value : '';
    if (!arrDate) {
        arrDate = new Date().toISOString().split('T')[0];
        if (arrInput) arrInput.value = arrDate;
    }

    const dt = new Date(arrDate + 'T00:00:00');
    dt.setDate(dt.getDate() + days);

    const yyyy = dt.getFullYear();
    const mm = String(dt.getMonth() + 1).padStart(2, '0');
    const dd = String(dt.getDate()).padStart(2, '0');
    const harvestStr = `${yyyy}-${mm}-${dd}`;

    if (pickInput) {
        pickInput.value = harvestStr;
        pickInput.min = harvestStr;
    }

    const hint = document.getElementById('harvestDurationHint');
    if (hint) {
        hint.innerText = `Day ${days}: +${days} days from intake (${harvestStr})`;
    }

    calcNewResBalance();
}

function calcNewResBalance(isManualTotal = false) {
    if (isManualTotal) {
        totalEditedManually = true;
    }
    
    const birds = parseFloat(document.getElementById('newResBirds')?.value) || 0;
    const targetW = parseFloat(document.getElementById('newResTargetWeight')?.value) || 2.20;
    let weightField = document.getElementById('newResWeight');
    
    // Auto-suggest estimated live weight if empty or based on target weight
    if (birds > 0 && (!weightField.value || weightField.value == '0' || !totalEditedManually)) {
        weightField.value = (birds * targetW).toFixed(2);
    }
    
    const weight = parseFloat(weightField.value) || 0;
    const unitPrice = parseFloat(document.getElementById('newResUnitPrice').value) || 0;
    let totalField = document.getElementById('newResTotal');
    
    // If not manually edited, calculate total from birds * unitPrice
    if (!totalEditedManually && unitPrice > 0) {
        let suggestedTotal = 0;
        if (birds > 0) {
            suggestedTotal = birds * unitPrice;
        } else if (weight > 0) {
            suggestedTotal = weight * unitPrice;
        }
        if (suggestedTotal > 0) {
            totalField.value = suggestedTotal.toFixed(2);
        }
    }
    
    const total = parseFloat(totalField.value) || 0;
    const advance = parseFloat(document.getElementById('newResAdvance').value) || 0;
    const balance = Math.max(0, total - advance);
    
    document.getElementById('newResBalanceDisplay').innerText = '₱' + balance.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function handleNewResSubmit(form) {
    const arrInput = document.getElementById('newResArrivalDate');
    const pickInput = document.getElementById('newResPickupDate');
    const targetW = document.getElementById('newResTargetWeight');

    const arrDate = arrInput ? arrInput.value : '';
    const pickDate = pickInput ? pickInput.value : '';
    const wVal = targetW ? (parseFloat(targetW.value) || 0) : 2.20;

    const todayStr = new Date().toISOString().split('T')[0];
    if (arrDate && arrDate < todayStr) {
        alert("Strict Reservation Rule: Chicks arrival date cannot be in the past (" + arrDate + "). Must be today or future date.");
        if (arrInput) arrInput.focus();
        return false;
    }

    if (arrDate && pickDate) {
        const dArr = new Date(arrDate + 'T00:00:00');
        const dPick = new Date(pickDate + 'T00:00:00');
        const diffDays = Math.round((dPick - dArr) / (1000 * 60 * 60 * 24));
        if (diffDays < 35) {
            alert("Strict Reservation Rule: Expected harvest date must be 35+ days from arrival date. Currently: " + diffDays + " days. Cannot be below 35 days.");
            if (pickInput) pickInput.focus();
            return false;
        }
    }

    if (wVal < 2.20) {
        alert("Strict Reservation Rule: Agreed target weight strictly cannot be below 2.20 kg (entered: " + wVal.toFixed(2) + " kg). Minimum allowed is 2.20 kg.");
        if (targetW) targetW.focus();
        return false;
    }

    const houseSelect = document.getElementById('newResHouseId');
    const birdsInput = document.getElementById('newResBirds');
    if (houseSelect && houseSelect.value && birdsInput) {
        const opt = houseSelect.options[houseSelect.selectedIndex];
        const remCap = opt ? parseInt(opt.getAttribute('data-remaining') || opt.getAttribute('data-capacity') || '0') : 0;
        const enteredBirds = parseInt(birdsInput.value) || 0;
        if (remCap > 0 && enteredBirds > remCap) {
            alert("Capacity Warning: The selected house only has " + remCap.toLocaleString() + " available spaces. You cannot reserve " + enteredBirds.toLocaleString() + " birds for this house.");
            birdsInput.focus();
            return false;
        }
    }

    if (form.getAttribute('data-submitting') === '1') {
        return false;
    }
    form.setAttribute('data-submitting', '1');
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        setTimeout(() => {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving Reservation...';
        }, 20);
    }
    return true;
}

function prefillReserveHouse(houseId, houseName, capacity) {
    <?php if (strtolower($user_role) === 'worker'): ?>
    alert('Access Denied: Farm workers cannot create or book reservations. Farm workers are authorized for pen cleaning and house sanitation operations.');
    return;
    <?php endif; ?>
    const modalEl = document.getElementById('newBroilerReservationModal');
    if (!modalEl) {
        console.error('Modal #newBroilerReservationModal not found');
        return;
    }
    
    const houseSelect = document.getElementById('newResHouseId');
    if (houseSelect) {
        const targetOpt = houseSelect.querySelector(`option[value="${houseId}"]`);
        const remCap = targetOpt ? parseInt(targetOpt.getAttribute('data-remaining') || targetOpt.getAttribute('data-capacity') || capacity || '0') : capacity;
        if (targetOpt && (targetOpt.disabled || remCap <= 0)) {
            alert('Cannot reserve ' + houseName + ': This pen has no vacant capacity left (' + (targetOpt.getAttribute('data-occupied') === '1' ? 'fully occupied' : 'fully booked under active contract') + ').');
            return;
        }
        houseSelect.value = String(houseId);
        onHouseSelectChanged(houseSelect);
    }
    
    // Auto-match active batch that is housed in this house, or set to incoming flock
    const batchSelect = document.getElementById('newResBatchId');
    if (batchSelect && batchSelect.options && batchSelect.options.length > 0) {
        let matched = false;
        for (let i = 0; i < batchSelect.options.length; i++) {
            const bOpt = batchSelect.options[i];
            const bHouse = bOpt.getAttribute('data-house');
            if (bHouse && houseName && bHouse.toLowerCase() === houseName.toLowerCase()) {
                batchSelect.selectedIndex = i;
                onBatchSelectChanged(batchSelect);
                matched = true;
                break;
            }
        }
        if (!matched) {
            batchSelect.value = "0"; // Incoming Flock / Pre-Intake (Vacant Pen)
            onBatchSelectChanged(batchSelect);
        }
    } else if (batchSelect) {
        batchSelect.value = "0";
    }
    
    const birdsInput = document.getElementById('newResBirds');
    if (birdsInput && capacity > 0) {
        birdsInput.value = capacity;
    }
    
    totalEditedManually = false;
    calcNewResBalance();
    onChickCostChanged();
    
    // Re-enable submit button in case modal is re-opened
    const submitBtn = document.querySelector('#newBroilerReservationForm button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Create Reservation';
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function prefillReserveBatch(batchId, batchCode, headcount, houseId, targetWeight) {
    <?php if (strtolower($user_role) === 'worker'): ?>
    alert('Access Denied: Farm workers cannot create or book reservations.');
    return;
    <?php endif; ?>
    const modalEl = document.getElementById('newBroilerReservationModal');
    if (!modalEl) {
        console.error('Modal #newBroilerReservationModal not found');
        return;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    const batchSelect = document.getElementById('newResBatchId');
    if (batchSelect) {
        batchSelect.value = batchId;
        onBatchSelectChanged(batchSelect);
    }
    
    const houseSelect = document.getElementById('newResHouseId');
    if (houseSelect && houseId) houseSelect.value = houseId;
    
    const birdsInput = document.getElementById('newResBirds');
    if (birdsInput) birdsInput.value = headcount;
    
    const targetW = document.getElementById('newResTargetWeight');
    if (targetW) targetW.value = targetWeight ? targetWeight.toFixed(2) : '1.80';
    
    const weightField = document.getElementById('newResWeight');
    if (weightField && headcount > 0) weightField.value = (headcount * (targetWeight || 1.80)).toFixed(2);
    
    totalEditedManually = false;
    calcNewResBalance();
    onChickCostChanged();
    modal.show();
}

let qwAgeDaysGlobal = 0;
let qwArrivalDateGlobal = '';
let qwBatchCodeGlobal = '';

function estimateBroilerWeightByAge(ageDays) {
    let days = parseInt(ageDays);
    if (isNaN(days) || days < 0) days = 0;
    if (days === 0) return 0.04; // Standard Day-Old Chick (DOC) weight ~40-42g
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

function updateAgeFromSampleDate(dateVal) {
    if (!qwArrivalDateGlobal || !dateVal) return;
    try {
        const arrDate = new Date(qwArrivalDateGlobal + 'T00:00:00');
        const selDate = new Date(dateVal + 'T00:00:00');
        const diffMs = selDate - arrDate;
        const diffDays = Math.max(0, Math.floor(diffMs / (1000 * 60 * 60 * 24)));
        
        qwAgeDaysGlobal = diffDays;
        const estWeight = estimateBroilerWeightByAge(qwAgeDaysGlobal);
        
        const batchCodeEl = document.getElementById('qwBatchCode');
        if (batchCodeEl && qwBatchCodeGlobal) {
            batchCodeEl.innerText = `${qwBatchCodeGlobal} (Day ${qwAgeDaysGlobal})`;
        }
        
        const hint = document.getElementById('qwEstHint');
        if (hint) {
            hint.innerHTML = `<span class="text-primary fw-semibold"><i class="fas fa-calendar-day me-1"></i>Day ${qwAgeDaysGlobal} Std: ~${estWeight.toFixed(2)} kg</span>`;
        }
    } catch (e) {
        console.error(e);
    }
}

function autoFillQuickWeighModal() {
    const sampleDateInput = document.getElementById('qwSampleDate');
    if (sampleDateInput && sampleDateInput.value && qwArrivalDateGlobal) {
        updateAgeFromSampleDate(sampleDateInput.value);
    }
    
    const est = estimateBroilerWeightByAge(qwAgeDaysGlobal);
    const input = document.getElementById('qwWeight');
    if (input) {
        input.value = est.toFixed(2);
        input.classList.add('bg-warning-subtle');
        setTimeout(() => input.classList.remove('bg-warning-subtle'), 800);
    }
    const notes = document.getElementById('qwNotes');
    if (notes) {
        notes.value = `Auto-estimated from Cobb 500 standard growth curve at Day ${qwAgeDaysGlobal}`;
    }
}

function openQuickWeighModal(batchId, batchCode, houseName, currentWeight, targetWeight, ageDays = 0, arrivalDate = '', aliveBirds = 0) {
    const modalEl = document.getElementById('quickWeighModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    const parsedAge = parseInt(ageDays);
    qwAgeDaysGlobal = (!isNaN(parsedAge) && parsedAge >= 0) ? parsedAge : 0;
    qwArrivalDateGlobal = arrivalDate || '';
    qwBatchCodeGlobal = batchCode || '';
    
    const parsedAlive = parseInt(aliveBirds);
    const aliveCount = (!isNaN(parsedAlive) && parsedAlive > 0) ? parsedAlive : 0;
    
    const aliveEl = document.getElementById('qwAliveBirds');
    if (aliveEl) {
        aliveEl.innerText = aliveCount > 0 ? `${aliveCount.toLocaleString()} heads` : '-';
    }
    
    const birdsInput = document.getElementById('qwBirdsSampled');
    const birdsHint = document.getElementById('qwBirdsSampledHint');
    if (birdsInput) {
        if (aliveCount > 0) {
            birdsInput.value = aliveCount;
            if (birdsHint) birdsHint.innerHTML = `<span class="text-success"><i class="fas fa-check-circle me-1"></i>All ${aliveCount.toLocaleString()} alive birds in ${houseName}</span>`;
        } else {
            birdsInput.value = 50;
            if (birdsHint) birdsHint.innerText = 'Number of birds weighed';
        }
    }
    
    // Set sample date input to today
    const sampleDateInput = document.getElementById('qwSampleDate');
    if (sampleDateInput) {
        const todayStr = new Date().toISOString().split('T')[0];
        sampleDateInput.value = todayStr;
        if (qwArrivalDateGlobal) {
            updateAgeFromSampleDate(sampleDateInput.value);
        }
    }
    
    const estWeight = estimateBroilerWeightByAge(qwAgeDaysGlobal);
    
    document.getElementById('qwBatchId').value = batchId;
    document.getElementById('qwHouseName').innerText = houseName;
    document.getElementById('qwBatchCode').innerText = `${batchCode} (Day ${qwAgeDaysGlobal})`;
    document.getElementById('qwTargetWeight').innerText = (targetWeight || 1.80).toFixed(2) + ' kg/bird';
    document.getElementById('qwWeight').value = currentWeight > 0 ? Number(currentWeight).toFixed(2) : estWeight.toFixed(2);
    
    const hint = document.getElementById('qwEstHint');
    if (hint) {
        hint.innerHTML = `<span class="text-primary fw-semibold"><i class="fas fa-calendar-day me-1"></i>Day ${qwAgeDaysGlobal} Std: ~${estWeight.toFixed(2)} kg</span>`;
    }
    
    const notes = document.getElementById('qwNotes');
    if (notes) {
        notes.value = '';
    }
    
    modal.show();
}

function onHouseSelectChanged(select) {
    if (!select || !select.options || select.selectedIndex < 0) return;
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        const remCap = parseInt(opt.getAttribute('data-remaining') || opt.getAttribute('data-capacity') || '5000');
        const totalCap = parseInt(opt.getAttribute('data-total-capacity') || '5000');
        const committed = parseInt(opt.getAttribute('data-committed') || '0');
        const birdsInput = document.getElementById('newResBirds');
        
        if (birdsInput) {
            birdsInput.max = remCap > 0 ? remCap : totalCap;
            birdsInput.placeholder = 'Max ' + (remCap > 0 ? remCap : totalCap) + ' birds';
            
            const currentVal = parseInt(birdsInput.value) || 0;
            if (remCap > 0 && (currentVal === 0 || currentVal > remCap || !birdsInput.value)) {
                birdsInput.value = remCap;
            }
            calcNewResBalance();
        }
    }
}

function onBatchSelectChanged(select) {
    if (!select || !select.options || select.selectedIndex < 0) return;
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value && opt.value !== "0") {
        const qty = opt.getAttribute('data-quantity');
        const tw = opt.getAttribute('data-target');
        const hp = opt.getAttribute('data-house');
        if (qty) {
            const birdsEl = document.getElementById('newResBirds');
            if (birdsEl) birdsEl.value = qty;
        }
        if (tw) {
            const twEl = document.getElementById('newResTargetWeight');
            if (twEl) twEl.value = tw;
        }
        if (hp) {
            const houseSelect = document.getElementById('newResHouseId');
            if (houseSelect && houseSelect.options) {
                for (let i = 0; i < houseSelect.options.length; i++) {
                    const hOpt = houseSelect.options[i];
                    const hName = hOpt.getAttribute('data-name');
                    if (hName && hName.toLowerCase() === hp.toLowerCase()) {
                        houseSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }
        const chickCost = opt.getAttribute('data-chick-cost');
        if (chickCost) {
            const chickCostEl = document.getElementById('newResChickCost');
            if (chickCostEl) {
                chickCostEl.value = parseFloat(chickCost).toFixed(2);
                onChickCostChanged();
            }
        }
    }
    calcNewResBalance();
}

function onChickCostChanged() {
    const chickCostEl = document.getElementById('newResChickCost');
    const val = parseFloat(chickCostEl?.value) || 0;
    
    // Update live benchmark labels on cycle pills/cards
    document.querySelectorAll('.docBenchmarkPrice').forEach(el => {
        el.innerText = '₱' + (val > 0 ? val.toFixed(2) : '38.00');
    });
    
    // Update total estimated DOC investment
    const birds = parseFloat(document.getElementById('newResBirds')?.value) || 0;
    const docTotalDisplay = document.getElementById('docTotalInvestmentDisplay');
    if (docTotalDisplay) {
        docTotalDisplay.innerText = '₱' + (birds * val).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function onDispatchResChanged(houseId, birdWeight) {
    const select = document.getElementById('dispatchSelectRes_' + houseId);
    if (!select) return;
    const opt = select.options[select.selectedIndex];
    if (!opt) return;
    
    const resId = opt.value;
    const birds = parseInt(opt.getAttribute('data-birds')) || 0;
    const unitPrice = parseFloat(opt.getAttribute('data-unitprice')) || 140.00;
    const contractTotal = parseFloat(opt.getAttribute('data-total')) || 0;
    
    const rateEl = document.getElementById('dispUnitRate_' + houseId);
    if (rateEl) rateEl.value = unitPrice;
    
    const acceptedInput = document.getElementById('dispAccepted_' + houseId);
    if (acceptedInput) {
        acceptedInput.value = birds;
    }
    
    const bw = birdWeight || 1.80;
    const netWeight = (birds * bw).toFixed(2);
    const weightInput = document.getElementById('dispWeight_' + houseId);
    if (weightInput) {
        weightInput.value = netWeight;
    }
    
    const priceInput = document.getElementById('dispPrice_' + houseId);
    if (priceInput) {
        if (contractTotal > 0) {
            priceInput.value = contractTotal.toFixed(2);
        } else {
            priceInput.value = (netWeight * unitPrice).toFixed(2);
        }
    }
    
    const receiptInput = document.getElementById('dispReceipt_' + houseId);
    if (receiptInput) {
        const d = new Date();
        const ymd = d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0');
        receiptInput.value = 'DR-' + ymd + '-' + String(resId).padStart(4, '0');
    }
}

function calcDispatchModalTotals(houseId, birdWeight) {
    const accepted = parseFloat(document.getElementById('dispAccepted_' + houseId).value) || 0;
    const bw = birdWeight || 1.80;
    const netWeight = (accepted * bw).toFixed(2);
    
    const weightInput = document.getElementById('dispWeight_' + houseId);
    if (weightInput) {
        weightInput.value = netWeight;
    }
    
    const unitRate = parseFloat(document.getElementById('dispUnitRate_' + houseId).value) || 140.00;
    const priceInput = document.getElementById('dispPrice_' + houseId);
    if (priceInput) {
        priceInput.value = (netWeight * unitRate).toFixed(2);
    }
}

function calcDispatchModalPrice(houseId) {
    const weight = parseFloat(document.getElementById('dispWeight_' + houseId).value) || 0;
    const unitRate = parseFloat(document.getElementById('dispUnitRate_' + houseId).value) || 140.00;
    const priceInput = document.getElementById('dispPrice_' + houseId);
    if (priceInput) {
        priceInput.value = (weight * unitRate).toFixed(2);
    }
}

// Relocate nested Bootstrap modals to <body> so overflow:hidden ancestors
// (house cards, table-responsive) cannot clip or offset them off-screen.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modalEl) {
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    });
});
</script>

<?php
// View Details modals rendered outside overflow-clipped layouts
if (!empty($viewResModalBuffer)) {
    echo "\n<!-- Buffered View Reservation Modals -->\n";
    foreach ($viewResModalBuffer as $modalHtml) {
        echo $modalHtml;
    }
}
?>

<?php include('footer.php'); ?>
