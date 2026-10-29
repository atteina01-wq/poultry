<?php
$content = file_get_contents(__DIR__ . '/../poultrychat/reservations.php');
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (stripos($line, '<?php if (!empty($message))') !== false || stripos($line, '<?php if ($message') !== false || stripos($line, 'alert-success') !== false) {
        echo ($num + 1) . ": " . $line . "\n";
    }
}
