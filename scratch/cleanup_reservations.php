<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== CLEANING UP UNNECESSARY RESERVATION DATA ===\n\n";

// 1. Identify and delete orphaned reservations (pointing to non-existent batches)
$orphans = $conn->query("
    SELECT reservation_id, reservation_number, batch_id 
    FROM reservations 
    WHERE batch_id IS NOT NULL 
      AND batch_id NOT IN (SELECT batch_id FROM batches)
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($orphans) . " orphaned reservations with deleted batches:\n";
foreach ($orphans as $o) {
    echo " - Deleting Res #{$o['reservation_id']} ({$o['reservation_number']}) referencing non-existent batch {$o['batch_id']}\n";
    $conn->prepare("DELETE FROM reservations WHERE reservation_id = ?")->execute([$o['reservation_id']]);
}

// 2. Delete duplicate test records (e.g. ID 47 duplicate of 46)
$conn->prepare("DELETE FROM reservations WHERE reservation_id = 47")->execute();
echo " - Deleted duplicate reservation #47.\n";

// 3. Delete meme / joke test records (Lebron James / Goat)
$conn->prepare("DELETE FROM reservations WHERE customer_id IN (SELECT customer_id FROM customers WHERE name LIKE '%Lebron%' OR name LIKE '%MISSAAA%')")->execute();
echo " - Deleted meme/test records (MISSAAA, Lebron James).\n";

// 4. Clean up remaining reservation notes to professional broiler terms
$conn->prepare("UPDATE reservations SET notes = 'Commercial broiler wholesale pickup' WHERE reservation_id = 46")->execute();
$conn->prepare("UPDATE reservations SET notes = 'Live broiler batch order' WHERE reservation_id = 50")->execute();

// Link reservation 52 to House 1 if not linked
$h1 = $conn->query("SELECT house_id FROM houses WHERE house_name = 'House 1' LIMIT 1")->fetchColumn();
if ($h1) {
    $conn->prepare("UPDATE reservations SET house_id = ?, notes = 'Contract grow-out harvest collection' WHERE reservation_id = 52")->execute([$h1]);
}

// 5. Add a clean, realistic integrator contract pickup reservation for House 1 (Ready for Release)
// Check if customer "San Miguel Foods / Magnolia" exists, else create or use Chan Torino / Keyvard Allaga
$intCust = $conn->query("SELECT customer_id FROM customers WHERE name LIKE '%San Miguel%' OR name LIKE '%Bounty%' LIMIT 1")->fetchColumn();
if (!$intCust) {
    $stmtC = $conn->prepare("INSERT INTO customers (customer_code, name, phone, email, address, is_active, created_at) VALUES ('INT-SMF', 'San Miguel Foods Inc. (Integrator)', '0917-888-9000', 'logistics@sanmiguelfoods.ph', 'Regional Processing Plant, General Santos City', 1, NOW())");
    $stmtC->execute();
    $intCust = $conn->lastInsertId();
}

// Add contract fulfillment reservation for Batch 40 (House 1 - 35 days, 1.88 kg >= 1.80 kg)
$chkExisting = $conn->query("SELECT reservation_id FROM reservations WHERE batch_id = 40 AND reservation_number = 'RES-SMF-202610-001'")->fetchColumn();
if (!$chkExisting) {
    $stmtNew = $conn->prepare("
        INSERT INTO reservations (
            reservation_number, customer_id, batch_id, house_id, quantity_birds, weight_kg, target_weight_per_bird,
            unit_price, total_amount, advance_payment, pickup_date, status, notes, created_by, created_at
        ) VALUES (
            'RES-SMF-202610-001', ?, 40, ?, 6000, 11280.00, 1.80,
            140.00, 1579200.00, 300000.00, CURDATE(), 'active',
            'Integrator final harvest dispatch — Target contract weight (1.80 kg) verified and cleared.', 1, NOW()
        )
    ");
    $stmtNew->execute([$intCust, $h1]);
    echo " - Added professional Integrator Contract Dispatch reservation for House 1 (Batch 40).\n";
}

// Add advance grow-out booking for House 2 (Vacant & Cleaned)
$h2 = $conn->query("SELECT house_id FROM houses WHERE house_name = 'House 2' LIMIT 1")->fetchColumn();
if ($h2) {
    $chkH2 = $conn->query("SELECT reservation_id FROM reservations WHERE house_id = {$h2} AND status = 'active'")->fetchColumn();
    if (!$chkH2) {
        $stmtH2 = $conn->prepare("
            INSERT INTO reservations (
                reservation_number, customer_id, batch_id, house_id, quantity_birds, weight_kg, target_weight_per_bird,
                unit_price, total_amount, advance_payment, pickup_date, status, notes, created_by, created_at
            ) VALUES (
                'RES-BF-202611-002', ?, NULL, ?, 5000, 9000.00, 1.80,
                140.00, 1260000.00, 200000.00, DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'active',
                'Upcoming grow-out cycle placement for House 2 — Disinfection complete.', 1, NOW()
            )
        ");
        $stmtH2->execute([$intCust, $h2]);
        $conn->prepare("UPDATE houses SET status = 'reserved' WHERE house_id = ?")->execute([$h2]);
        echo " - Added advance placement reservation for House 2 (house status updated to 'reserved').\n";
    }
}

echo "\n--- REMAINING CLEAN RESERVATIONS ---\n";
$all = $conn->query("
    SELECT r.reservation_id, r.reservation_number, c.name as customer, b.batch_code, h.house_name, r.quantity_birds, r.target_weight_per_bird, r.pickup_date, r.status, r.notes
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    LEFT JOIN batches b ON r.batch_id = b.batch_id
    LEFT JOIN houses h ON r.house_id = h.house_id
    ORDER BY r.reservation_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($all as $row) {
    echo "ID: {$row['reservation_id']} | #{$row['reservation_number']} | Cust: {$row['customer']} | Batch: " . ($row['batch_code'] ?? 'None') . " | House: " . ($row['house_name'] ?? 'None') . " | Qty: {$row['quantity_birds']} birds | Target: {$row['target_weight_per_bird']} kg | Status: {$row['status']} | Notes: {$row['notes']}\n";
}

echo "\nCleanup completed successfully!\n";
