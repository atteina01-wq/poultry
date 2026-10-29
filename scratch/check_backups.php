<?php
$dirs = ['c:/xampp/htdocs/poultry_copy', 'c:/xampp/htdocs/poultry_live_backup_20260913_predeploy'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        if (strpos($path, 'vendor') !== false || pathinfo($path, PATHINFO_EXTENSION) !== 'php') continue;
        $c = file_get_contents($path);
        if (preg_match('/INSERT\s+INTO.*details/i', $c) || preg_match('/UPDATE.*details/i', $c)) {
            echo "Match in $path\n";
        }
    }
}
echo "Done checking backups.\n";
