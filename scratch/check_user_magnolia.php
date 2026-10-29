<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("DESCRIBE users");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
$wheres = [];
foreach ($cols as $c) {
    $wheres[] = "`$c` LIKE '%Magnolia%'";
}
$stmt = $conn->query("SELECT * FROM users WHERE " . implode(' OR ', $wheres));
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
