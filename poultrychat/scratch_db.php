<?php
include 'database.php';
try {
    $conn->exec("ALTER TABLE activities ADD COLUMN status ENUM('pending', 'completed') DEFAULT 'pending'");
    echo "Added status column.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
