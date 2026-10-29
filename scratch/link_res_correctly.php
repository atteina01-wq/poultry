<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "Linking reservations to real customer accounts and houses...\n";

// Res 61: San Miguel Foods Inc. (Integrator) for Batch 40 in House 1
$conn->exec("
    UPDATE reservations SET
        customer_id = 7,
        batch_id = 40,
        house_id = 1,
        quantity_birds = 6000,
        weight_kg = 11280.00,
        target_weight_per_bird = 1.80,
        unit_price = 140.00,
        total_amount = 1579200.00,
        status = 'active',
        notes = 'Integrator contract grow-out final harvest dispatch'
    WHERE reservation_id = 61
");

// Res 62: Bounty Fresh Food Inc. for House 2 (Upcoming Placement)
$conn->exec("
    UPDATE reservations SET
        customer_id = 2,
        batch_id = NULL,
        house_id = 2,
        quantity_birds = 5000,
        weight_kg = 9000.00,
        target_weight_per_bird = 1.80,
        unit_price = 140.00,
        total_amount = 1260000.00,
        status = 'active',
        notes = 'Upcoming grow-out placement booking for House 2'
    WHERE reservation_id = 62
");

// Res 52: Keyvard Allaga for House 1 (Commercial buyer)
$conn->exec("
    UPDATE reservations SET
        customer_id = 4,
        batch_id = 40,
        house_id = 1,
        quantity_birds = 400,
        weight_kg = 752.00,
        target_weight_per_bird = 1.80,
        unit_price = 180.00,
        total_amount = 135360.00,
        status = 'active',
        notes = 'Contract harvest collection'
    WHERE reservation_id = 52
");

$stmt = $conn->query("
    SELECT r.reservation_id, r.reservation_number, c.name as customer, r.batch_id, r.house_id, r.quantity_birds, r.total_amount, r.status
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    WHERE r.status = 'active'
");
echo "\nUPDATED ACTIVE RESERVATIONS:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['reservation_id']} | #{$row['reservation_number']} | Cust: {$row['customer']} | Batch: {$row['batch_id']} | House: {$row['house_id']} | Birds: {$row['quantity_birds']} | Total: ₱" . number_format($row['total_amount'], 2) . "\n";
}
