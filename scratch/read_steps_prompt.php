<?php
$appData = getenv('LOCALAPPDATA') ?: 'C:/Users/EJ Shane/AppData/Local';
$transcriptPath = 'C:/Users/EJ Shane/.gemini/antigravity-ide/brain/c4a189fa-b6d4-4427-81be-68dc02e392e9/.system_generated/logs/transcript.jsonl';
if (!file_exists($transcriptPath)) {
    echo "No transcript found at $transcriptPath\n";
    exit;
}
$lines = file($transcriptPath);
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (!empty($data['content']) && strpos($data['content'], 'chick reservation-to-batch workflow') !== false) {
        echo substr($data['content'], 0, 3000) . "\n";
        break;
    }
}
