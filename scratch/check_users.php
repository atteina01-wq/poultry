<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

$stmt = $conn->query("SELECT user_id, username, full_name, role, email, phone FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
