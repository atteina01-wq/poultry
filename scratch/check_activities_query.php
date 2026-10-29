<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
try {
    $conn->exec("ALTER TABLE activities MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'General'");
    echo "ALTER successful!\n";
} catch(Exception $e) {
    echo "ALTER error: " . $e->getMessage() . "\n";
}
$conn->exec("UPDATE activities SET type = 'Weight Sampling' WHERE activity_id = 170");
$stmt = $conn->query("SHOW COLUMNS FROM activities WHERE Field = 'type'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
$row = $conn->query("SELECT * FROM activities WHERE activity_id = 170")->fetch(PDO::FETCH_ASSOC);
print_r($row);
