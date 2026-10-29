<?php
require __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->prepare("UPDATE users SET role = 'worker' WHERE user_id = 2");
$stmt->execute();
echo "Worker role updated. Verification:\n";
$u = $conn->query("SELECT user_id, username, full_name, role FROM users WHERE user_id = 2")->fetch(PDO::FETCH_ASSOC);
print_r($u);
