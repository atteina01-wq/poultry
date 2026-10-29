<?php
require_once __DIR__ . '/../poultrychat/database.php';

$sql = "
    SELECT 
        ret.return_id,
        ret.return_number,
        ret.order_id,
        o.order_number,
        c.name as customer_name,
        ret.return_date,
        ret.reason,
        ret.notes,
        COALESCE(SUM(ri.quantity), 0) as returned_quantity,
        ri.condition_status,
        u.username as processed_by
    FROM order_returns ret
    JOIN orders o ON ret.order_id = o.order_id
    JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN order_return_items ri ON ret.return_id = ri.return_id
    LEFT JOIN users u ON ret.returned_by = u.user_id
    GROUP BY ret.return_id
    ORDER BY ret.return_date DESC
    LIMIT 10
";

$stmt = $conn->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Sample Returns Records:" . PHP_EOL;
print_r($rows);
