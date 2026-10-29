<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

$res = $conn->query("
    SELECT b.batch_id, b.batch_code, b.house_pen, b.status, b.arrival_date, b.flock_type, b.breed,
           h.house_id, h.house_name, h.status as house_status
    FROM batches b
    LEFT JOIN houses h ON h.current_batch_id = b.batch_id
    ORDER BY b.arrival_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($res, JSON_PRETTY_PRINT);
