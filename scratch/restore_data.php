<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== RESTORING HEALTH RECORDS & FEED CONSUMPTION FOR PICTURE BATCHES ===\n\n";

// 1. Health Records Restoration
// In the picture: BR-20261012-319 (46), B2026-P3-03 (38), B2026-P5-01 (6), B2026-H4-08 (37), B2026-P1-04 (35), B2026-P6-06 (31)
// Record 58 belongs to batch 31 (B2026-P6-06)
$checkHr = $conn->query("SELECT COUNT(*) FROM health_records WHERE health_id = 58")->fetchColumn();
if ($checkHr == 0) {
    $stmtHr = $conn->prepare("
        INSERT INTO health_records (
            health_id, batch_id, number_of_chickens_treated, inventory_id, type, medicine_name,
            illness_observation, doctor_consultation, diagnosis, quantity_used, date, notes,
            status, recorded_by, created_at
        ) VALUES (
            58, 31, 6, 4, 'treatment', 'Anti-plema',
            'none', 'Dr. Shane Campilana', 'none', 6.00, '2026-09-25', 'Good',
            'completed', 1, '2026-09-24 22:44:13'
        )
    ");
    $stmtHr->execute();
    echo "1. Health Record #58 (Batch B2026-P6-06 / Anti-plema) restored successfully.\n";
} else {
    echo "1. Health Record #58 already present.\n";
}

// 2. Feed Consumption Restoration
// Records 69, 70, 85, 86 belong to Batch 6 (B2026-P5-01) and Batch 31 (B2026-P6-06)
$feedRows = [
    [
        'id' => 69,
        'batch_id' => 6,
        'inventory_id' => 1,
        'feed_stage' => 'Starter',
        'qty' => 50.00,
        'date' => '2026-09-24',
        'recorded_by' => 1,
        'remarks' => '',
        'cost' => null,
        'created_at' => '2026-09-24 10:34:47'
    ],
    [
        'id' => 70,
        'batch_id' => 31,
        'inventory_id' => 2,
        'feed_stage' => 'Pre-Starter',
        'qty' => 16.00,
        'date' => '2026-09-25',
        'recorded_by' => 1,
        'remarks' => null,
        'cost' => 2300.00,
        'created_at' => '2026-09-24 22:52:16'
    ],
    [
        'id' => 85,
        'batch_id' => 31,
        'inventory_id' => 1,
        'feed_stage' => 'Starter',
        'qty' => 33.70,
        'date' => '2026-09-26',
        'recorded_by' => 1,
        'remarks' => 'fast',
        'cost' => null,
        'created_at' => '2026-09-26 06:36:59'
    ],
    [
        'id' => 86,
        'batch_id' => 6,
        'inventory_id' => 2,
        'feed_stage' => 'Pre-Starter',
        'qty' => 5.00,
        'date' => '2026-10-13',
        'recorded_by' => 1,
        'remarks' => 'ChicksFeeds',
        'cost' => null,
        'created_at' => '2026-10-12 14:00:00'
    ]
];

foreach ($feedRows as $fr) {
    $checkFc = $conn->prepare("SELECT COUNT(*) FROM feed_consumption WHERE consumption_id = ?");
    $checkFc->execute([$fr['id']]);
    if ($checkFc->fetchColumn() == 0) {
        $stmtIns = $conn->prepare("
            INSERT INTO feed_consumption (
                consumption_id, batch_id, inventory_id, feed_stage, quantity_used, date,
                recorded_by, remarks, unit_cost_snapshot, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([
            $fr['id'], $fr['batch_id'], $fr['inventory_id'], $fr['feed_stage'],
            $fr['qty'], $fr['date'], $fr['recorded_by'], $fr['remarks'],
            $fr['cost'], $fr['created_at']
        ]);
        echo "2. Feed consumption #{$fr['id']} (Batch #{$fr['batch_id']}, Date: {$fr['date']}, Qty: {$fr['qty']}) restored.\n";
    } else {
        echo "2. Feed consumption #{$fr['id']} already present.\n";
    }
}

echo "\n=== VERIFICATION: CURRENT HEALTH RECORDS ===\n";
$stmtH = $conn->query("
    SELECT hr.health_id, hr.batch_id, b.batch_code, b.house_pen, hr.date, hr.medicine_name, hr.quantity_used, hr.number_of_chickens_treated, hr.status
    FROM health_records hr
    JOIN batches b ON hr.batch_id = b.batch_id
    ORDER BY hr.date DESC, hr.health_id DESC
");
while ($r = $stmtH->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['health_id']} | Batch: {$r['batch_code']} ({$r['house_pen']}) | Date: {$r['date']} | Med: {$r['medicine_name']} | Chickens: {$r['number_of_chickens_treated']} | Status: {$r['status']}\n";
}

echo "\n=== VERIFICATION: CURRENT FEED CONSUMPTION ===\n";
$stmtF = $conn->query("
    SELECT fc.consumption_id, fc.batch_id, b.batch_code, b.house_pen, i.item_name, fc.feed_stage, fc.quantity_used, fc.date
    FROM feed_consumption fc
    JOIN batches b ON fc.batch_id = b.batch_id
    JOIN inventory i ON fc.inventory_id = i.inventory_id
    ORDER BY fc.date DESC, fc.consumption_id DESC
");
while ($r = $stmtF->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['consumption_id']} | Batch: {$r['batch_code']} ({$r['house_pen']}) | Feed: {$r['item_name']} | Stage: {$r['feed_stage']} | Qty: {$r['quantity_used']} | Date: {$r['date']}\n";
}
