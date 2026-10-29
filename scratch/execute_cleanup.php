<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$conn->exec("ALTER TABLE activities MODIFY type VARCHAR(50) NOT NULL DEFAULT 'General'");
$conn->exec("UPDATE activities SET type = 'Weight Sampling' WHERE activity_id = 170");
$act = $conn->query("SELECT activity_id, batch_id, type, item_name, date, status FROM activities")->fetchAll(PDO::FETCH_ASSOC);
print_r($act);
