<?php
include('database.php');
$stmt = $conn->query("SELECT * FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
