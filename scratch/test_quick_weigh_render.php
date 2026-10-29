<?php
session_start();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

ob_start();
require_once __DIR__ . '/../poultrychat/database.php';
include __DIR__ . '/../poultrychat/reservations.php';
$html = ob_get_clean();

echo "=== CHECKING RESERVATIONS.PHP RENDER ===\n";

if (preg_match_all('/openQuickWeighModal\([^)]+\)/', $html, $matches)) {
    echo "Found " . count($matches[0]) . " openQuickWeighModal calls:\n";
    foreach ($matches[0] as $call) {
        echo "  " . $call . "\n";
    }
} else {
    echo "[FAIL] openQuickWeighModal call not found in HTML.\n";
}

echo "Modal has #qwAliveBirds: " . (strpos($html, 'id="qwAliveBirds"') !== false ? '[PASS]' : '[FAIL]') . "\n";
echo "Modal has #qwBirdsSampled: " . (strpos($html, 'id="qwBirdsSampled"') !== false ? '[PASS]' : '[FAIL]') . "\n";
echo "Modal has #qwBirdsSampledHint: " . (strpos($html, 'id="qwBirdsSampledHint"') !== false ? '[PASS]' : '[FAIL]') . "\n";
