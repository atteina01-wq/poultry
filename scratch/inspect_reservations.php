<?php
$content = file_get_contents(__DIR__ . '/../poultrychat/reservations.php');
preg_match_all('/isset\(\$_POST\[[\'"](\w+)[\'"]\]\)/', $content, $matches);
echo "Found " . count($matches[1]) . " POST actions:\n";
print_r(array_count_values($matches[1]));

preg_match_all('/(workflow_stage|RECEIVING|DISCREPANCY|APPROVED|GROWING|RECEIPT)/i', $content, $matches2);
echo "\nWorkflow mentions: " . count($matches2[0]) . "\n";
print_r(array_count_values(array_map('strtolower', $matches2[0])));
