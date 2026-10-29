<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
foreach ($files as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (strpos($path, 'vendor') !== false || strpos($path, '.gemini') !== false) continue;
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') continue;
    $content = file_get_contents($path);
    if (stripos($content, 'audit_logs') !== false) {
        $lines = explode("\n", $content);
        foreach ($lines as $i => $l) {
            if (stripos($l, 'INSERT INTO') !== false && stripos($l, 'audit_logs') !== false) {
                echo $path . ":" . ($i+1) . " -> " . trim($l) . "\n";
                for ($j = $i+1; $j <= min(count($lines)-1, $i+5); $j++) {
                    echo "   " . trim($lines[$j]) . "\n";
                    if (strpos($lines[$j], ')') !== false) break;
                }
            }
        }
    }
}
