<?php
require __DIR__ . '/../poultrychat/database.php';

$conn->exec("SET FOREIGN_KEY_CHECKS = 0");

$conn->exec("DELETE FROM reservations WHERE reservation_id IN (
    SELECT id FROM (
        SELECT reservation_id AS id FROM reservations ORDER BY reservation_id ASC LIMIT 13
    ) AS tmp
)");

$count = $conn->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
echo "Remaining reservations count: $count\n";

$conn->exec("SET FOREIGN_KEY_CHECKS = 1");
