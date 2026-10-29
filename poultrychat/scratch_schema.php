<?php
include('database.php');
$stmt = $conn->query("DESCRIBE reservations");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
