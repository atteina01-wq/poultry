<?php
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'worker';
$_SESSION['username'] = 'worker';

ob_start();
include "c:/xampp/htdocs/poultry/poultrychat/healthrecords.php";
$htmlWorker = ob_get_clean();

echo "=== HEALTHRECORDS WORKER ===\n";
echo "Has 'New Health Record' form: " . (strpos($htmlWorker, 'New Health Record') !== false ? "YES" : "NO") . "\n";
echo "Has 'Worker Action Mode' badge: " . (strpos($htmlWorker, 'Worker Action Mode') !== false ? "YES" : "NO") . "\n";
echo "Has Action/Update button in table: " . (strpos($htmlWorker, 'openStatusModal') !== false ? "YES" : "NO") . "\n";
echo "Has page-grid: " . (strpos($htmlWorker, 'class="page-grid"') !== false ? "YES" : "NO") . "\n";
