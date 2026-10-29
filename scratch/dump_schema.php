<?php
$conn = new mysqli("localhost", "root", "", "poultry_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    $tables[] = $row[0];
}

$schema = "";
foreach ($tables as $table) {
    if (strpos($table, 'v_') === 0) continue; // Skip views
    $res = $conn->query("SHOW CREATE TABLE `$table`");
    if ($row = $res->fetch_assoc()) {
        $schema .= $row['Create Table'] . ";\n\n";
    }
}
file_put_contents('scratch/schema_utf8.sql', $schema);
echo "Schema dumped to scratch/schema_utf8.sql";
