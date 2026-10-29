<?php
require_once __DIR__ . '/../poultrychat/database.php';
$_SESSION['db_schema_verified'] = false;
require_once __DIR__ . '/../poultrychat/db_check.php';

$res = $conn->query("SELECT user_id, username, role, customer_id FROM users WHERE username = 'integrator'")->fetch(PDO::FETCH_ASSOC);
print_r($res);
