<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
foreach ($files as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (strpos($path, 'vendor') !== false || strpos($path, '.gemini') !== false || strpos($path, 'composer.phar') !== false) continue;
    $content = file_get_contents($path);
    if (preg_match('/\bdetails\b/i', $content)) {
        $lines = explode("\n", $content);
        foreach ($lines as $i => $l) {
            if (preg_match('/\bdetails\b/i', $l)) {
                // Ignore plain HTML tags or comments without quotes/variables
                echo $path . ":" . ($i+1) . " -> " . trim($l) . "\n";
            }
        }
    }
}
