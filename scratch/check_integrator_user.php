<?php
require __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT user_id, username, full_name, role, is_active FROM users WHERE username = 'integrator'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
