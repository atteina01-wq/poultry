<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== RESTORING FARM OPERATIONS (ACTIVITIES) OLD RECORDS ===\n\n";

$activitiesToRestore = [
    [
        'activity_id' => 121,
        'batch_id'    => 6,
        'user_id'     => 1,
        'type'        => 'Feeding',
        'item_name'   => 'BroilerFeeds',
        'quantity'    => 50.00,
        'date'        => '2026-09-24',
        'remarks'     => 'Morning flock ration delivery',
        'created_at'  => '2026-09-24 10:34:47',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 122,
        'batch_id'    => 31,
        'user_id'     => 1,
        'type'        => 'Health Inspection',
        'item_name'   => 'Vitamins',
        'quantity'    => 6.00,
        'date'        => '2026-09-25',
        'remarks'     => 'Careful flock health audit',
        'created_at'  => '2026-09-24 22:42:08',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 123,
        'batch_id'    => 31,
        'user_id'     => 1,
        'type'        => 'Health',
        'item_name'   => 'Anti-plema',
        'quantity'    => 6.00,
        'date'        => '2026-09-25',
        'remarks'     => 'Good recovery response',
        'created_at'  => '2026-09-24 22:44:13',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 125,
        'batch_id'    => 37,
        'user_id'     => 2,
        'type'        => 'Cleaning',
        'item_name'   => 'Disinfectant Spray',
        'quantity'    => 2.00,
        'date'        => '2026-09-25',
        'remarks'     => 'Mid-cycle pen biosecurity sanitation',
        'created_at'  => '2026-09-25 08:11:22',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 138,
        'batch_id'    => 40,
        'user_id'     => 2,
        'type'        => 'Feeding',
        'item_name'   => 'ChicksFeeds',
        'quantity'    => 10.00,
        'date'        => '2026-09-28',
        'remarks'     => 'Fast feed intake verified',
        'created_at'  => '2026-09-25 09:01:33',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 139,
        'batch_id'    => 37,
        'user_id'     => 2,
        'type'        => 'Cleaning',
        'item_name'   => 'Facility Cleaning',
        'quantity'    => 0.00,
        'date'        => '2026-09-25',
        'remarks'     => 'House 4 walkway and drinker line sanitation',
        'created_at'  => '2026-09-25 18:09:02',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 148,
        'batch_id'    => 31,
        'user_id'     => 2,
        'type'        => 'Feeding',
        'item_name'   => 'BroilerFeeds',
        'quantity'    => 33.70,
        'date'        => '2026-09-26',
        'remarks'     => 'Fast grower appetite observed',
        'created_at'  => '2026-09-26 06:36:59',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 184,
        'batch_id'    => 6,
        'user_id'     => 1,
        'type'        => 'Feeding',
        'item_name'   => 'ChicksFeeds',
        'quantity'    => 5.00,
        'date'        => '2026-10-13',
        'remarks'     => 'Routine pen feeding',
        'created_at'  => '2026-10-12 14:10:00',
        'status'      => 'completed'
    ],
    [
        'activity_id' => 187,
        'batch_id'    => 46,
        'user_id'     => 1,
        'type'        => 'Health',
        'item_name'   => 'Vitamin C (Ascorbic Acid)',
        'quantity'    => 30.00,
        'date'        => '2026-10-12',
        'remarks'     => 'Good brooding vitamin intake',
        'created_at'  => '2026-10-12 22:20:32',
        'status'      => 'completed'
    ]
];

$stmtCheck = $conn->prepare("SELECT COUNT(*) FROM activities WHERE activity_id = ?");
$stmtIns = $conn->prepare("
    INSERT INTO activities (
        activity_id, batch_id, user_id, type, item_name, quantity, date, remarks, created_at, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$restoredCount = 0;
foreach ($activitiesToRestore as $act) {
    $stmtCheck->execute([$act['activity_id']]);
    if ($stmtCheck->fetchColumn() == 0) {
        $stmtIns->execute([
            $act['activity_id'],
            $act['batch_id'],
            $act['user_id'],
            $act['type'],
            $act['item_name'],
            $act['quantity'],
            $act['date'],
            $act['remarks'],
            $act['created_at'],
            $act['status']
        ]);
        echo "Restored Activity #{$act['activity_id']} [{$act['type']}: {$act['item_name']}] for Batch #{$act['batch_id']}\n";
        $restoredCount++;
    } else {
        echo "Activity #{$act['activity_id']} already exists in database.\n";
    }
}

echo "\nTotal restored: {$restoredCount}\n";

echo "\n=== ALL ACTIVITIES NOW IN DATABASE ===\n";
$all = $conn->query("
    SELECT a.activity_id, a.batch_id, b.batch_code, b.house_pen, a.type, a.item_name, a.quantity, a.date, a.status
    FROM activities a
    LEFT JOIN batches b ON a.batch_id = b.batch_id
    ORDER BY a.created_at DESC, a.activity_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($all as $row) {
    $hp = !empty($row['house_pen']) ? " ({$row['house_pen']})" : "";
    echo "ID: {$row['activity_id']} | Batch: {$row['batch_code']}{$hp} | Type: {$row['type']} | Item: {$row['item_name']} | Qty: {$row['quantity']} | Date: {$row['date']} | Status: {$row['status']}\n";
}
