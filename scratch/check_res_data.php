<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT * FROM reservations ORDER BY reservation_id DESC LIMIT 10");
echo "Reservations count: " . $conn->query("SELECT COUNT(*) FROM reservations")->fetchColumn() . PHP_EOL;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}

$stmt2 = $conn->query("SELECT * FROM reservation_items LIMIT 10");
echo "Reservation items count: " . $conn->query("SELECT COUNT(*) FROM reservation_items")->fetchColumn() . PHP_EOL;
while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
