<?php
require __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("SELECT TRIGGER_SCHEMA, TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT FROM information_schema.TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);
echo "All triggers count: " . count($res) . "\n";
print_r($res);
