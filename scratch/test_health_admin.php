<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

ob_start();
include "c:/xampp/htdocs/poultry/poultrychat/healthrecords.php";
$htmlAdmin = ob_get_clean();

echo "=== HEALTHRECORDS ADMIN ===\n";
echo "Has 'New Health Record' form: " . (strpos($htmlAdmin, 'New Health Record') !== false ? "YES" : "NO") . "\n";
echo "Has '<span class=\"text-muted small\">—</span>' (No action for admin): " . (strpos($htmlAdmin, '—</span>') !== false ? "YES" : "NO") . "\n";
echo "Has Action button: " . (strpos($htmlAdmin, 'btn-warning') !== false ? "YES" : "NO") . "\n";
echo "Has page-grid: " . (strpos($htmlAdmin, 'class="page-grid"') !== false ? "YES" : "NO") . "\n";
