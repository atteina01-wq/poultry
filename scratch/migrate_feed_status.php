<?php
require_once __DIR__ . '/../poultrychat/database.php';

header('Content-Type: text/plain');

try {
    // 1. Add status
    try {
        $conn->query("SELECT status FROM feed_consumption LIMIT 1");
        echo "status column already exists.\n";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE feed_consumption ADD COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'completed' AFTER feed_stage");
        echo "status column added successfully.\n";
    }

    // 2. Add completed_by and completed_at
    try {
        $conn->query("SELECT completed_by FROM feed_consumption LIMIT 1");
        echo "completed_by column already exists.\n";
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE feed_consumption ADD COLUMN completed_by INT NULL AFTER recorded_by, ADD COLUMN completed_at DATETIME NULL AFTER completed_by, ADD INDEX idx_fc_completed_by (completed_by)");
        echo "completed_by and completed_at columns added successfully.\n";
    }

    // Backfill existing records: if recorded_by is set and completed_by is null, set completed_by = recorded_by and completed_at = created_at
    $conn->exec("UPDATE feed_consumption SET status = 'completed', completed_by = recorded_by, completed_at = created_at WHERE status IS NULL OR status = 'completed' AND completed_by IS NULL");
    echo "Backfilled existing feed consumption records.\n";

    echo "\nNew structure:\n";
    foreach ($conn->query("DESCRIBE feed_consumption") as $r) {
        echo sprintf("%-25s %-25s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
