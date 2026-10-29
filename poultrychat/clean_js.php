<?php
$file = 'c:/xampp/htdocs/poultry/poultrychat/activities.php';
$content = file_get_contents($file);

$content = preg_replace('/function toggleDueDate\(\) \{.*?\}/s', '', $content);
$content = preg_replace('/function filterTasks\(batchId\) \{.*?\}/s', '', $content);
$content = preg_replace('/\/\/ Run on load if form exists\s*document\.addEventListener\(\'DOMContentLoaded\', function\(\) \{.*?\n\}\);/s', '', $content);
$content = str_replace("\n\n\n", "\n", $content);

file_put_contents($file, $content);
echo "JS cleaned.\n";
