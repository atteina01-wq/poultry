<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = ['user_id' => 2, 'username' => 'worker', 'full_name' => 'Farm Worker', 'role' => 'worker'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/poultrychat/reservations.php';
ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$html = ob_get_clean();
$lines = explode("\n", $html);
foreach ($lines as $i => $line) {
    if (stripos($line, 'Settle Balance') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
