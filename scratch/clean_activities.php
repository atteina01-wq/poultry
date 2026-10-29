<?php
require_once __DIR__ . '/../poultrychat/database.php';
$conn->query("DELETE FROM activities WHERE status = 'pending' AND type = 'Cleaning' AND item_name = 'Facility Cleaning'");
echo "Duplicates removed successfully.\n";
