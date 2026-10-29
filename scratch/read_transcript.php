<?php
$file = 'C:/Users/EJ Shane/.gemini/antigravity-ide/brain/41e19112-d27c-4443-956c-761aff43c32a/.system_generated/logs/transcript_full.jsonl';
$handle = fopen($file, 'r');
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $data = json_decode($line, true);
        if (isset($data['step_index']) && $data['step_index'] >= 3240 && $data['step_index'] <= 3267) {
            echo "=== Step " . $data['step_index'] . " [" . ($data['source'] ?? '') . "/" . ($data['type'] ?? '') . "] ===\n";
            if (!empty($data['content'])) {
                echo "Content: " . mb_substr($data['content'], 0, 400) . "\n";
            }
        }
    }
    fclose($handle);
}
