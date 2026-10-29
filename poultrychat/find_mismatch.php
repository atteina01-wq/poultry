<?php
$html = file_get_contents('scratch_output.html');
$lines = explode("\n", $html);
$stack = [];
foreach ($lines as $i => $line) {
    $opens = substr_count($line, '<div');
    $closes = substr_count($line, '</div');
    for ($j = 0; $j < $opens; $j++) $stack[] = $i + 1;
    for ($j = 0; $j < $closes; $j++) {
        if (!empty($stack)) {
            array_pop($stack);
        } else {
            echo "EXTRA CLOSING DIV AT LINE " . ($i + 1) . ": " . trim($line) . "\n";
        }
    }
}
if (!empty($stack)) {
    echo "UNCLOSED DIVS OPENED AT LINES: " . implode(', ', $stack) . "\n";
}
