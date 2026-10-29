<?php
$dir = realpath(__DIR__ . '/..');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$matches = [];
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (strpos($path, 'vendor') !== false || strpos($path, '.gemini') !== false) continue;
    $content = file_get_contents($path);
    if (stripos($content, 'details') !== false) {
        $lines = explode("\n", $content);
        foreach ($lines as $num => $line) {
            if (stripos($line, 'details') !== false) {
                // Check if it looks like SQL (INSERT, UPDATE, SELECT)
                if (preg_match('/(INSERT|UPDATE|SELECT|INTO|SET|FROM|WHERE)/i', $line)) {
                    $matches[] = $path . ":" . ($num + 1) . ": " . trim($line);
                }
            }
        }
    }
}
echo "Found " . count($matches) . " SQL-like occurrences of 'details':\n";
foreach ($matches as $m) {
    echo $m . "\n";
}
