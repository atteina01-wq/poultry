<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
require_once 'c:/xampp/htdocs/poultry/poultrychat/db_check.php';

$conn->exec("ALTER TABLE activities MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'General'");
$conn->exec("UPDATE activities SET type = 'Weight Sampling' WHERE activity_id = 170");

$stmt = $conn->query("SELECT * FROM activities WHERE activity_id = 170");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
