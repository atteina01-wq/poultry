<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("UPDATE reservations SET unit_price = 180.00, total_amount = (quantity_birds * 180.00) WHERE total_amount = 0 OR total_amount IS NULL");
echo "Updated " . $stmt->rowCount() . " reservations.";
