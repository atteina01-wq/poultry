<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

$log_query = "
    SELECT 
        a.activity_id,
        a.batch_id,
        a.user_id,
        a.type,
        a.item_name,
        a.quantity,
        a.date,
        a.remarks,
        a.created_at,
        a.status,
        COALESCE(b.batch_code, 'General') AS batch_code,
        b.house_pen,
        COALESCE(u.full_name, u.username, 'System / Unassigned') AS worker_name,
        u.role AS worker_role
    FROM activities a
    LEFT JOIN batches b ON a.batch_id = b.batch_id
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.type IS NOT NULL AND TRIM(a.type) != '' AND a.type != 'None'
    ORDER BY a.created_at DESC, a.activity_id DESC
    LIMIT 20
";
$log = $conn->query($log_query);
$count = 0;
while($row = $log->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    $hp = !empty($row['house_pen']) ? " ({$row['house_pen']})" : "";
    echo "#{$row['activity_id']} | {$row['date']} | {$row['batch_code']}{$hp} | {$row['type']} | {$row['item_name']} (Qty: {$row['quantity']}) | Status: {$row['status']}\n";
}
echo "Total activity rows rendered: {$count}\n";
