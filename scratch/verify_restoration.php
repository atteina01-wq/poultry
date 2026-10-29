<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "--- HEALTH RECORDS COUNT ---\n";
echo $conn->query("SELECT COUNT(*) FROM health_records")->fetchColumn() . " records in health_records\n";

echo "\n--- FEED CONSUMPTION COUNT ---\n";
echo $conn->query("SELECT COUNT(*) FROM feed_consumption")->fetchColumn() . " records in feed_consumption\n";

echo "\n--- BATCHES IN USER PICTURE ---\n";
$picBatches = [
    'BR-20261012-319' => 'House 5',
    'B2026-P3-03'     => 'Pen 3',
    'B2026-P5-01'     => 'Pen 5',
    'B2026-H4-08'     => 'House 4',
    'B2026-P1-04'     => 'Pen 1',
    'B2026-P6-06'     => 'Pen 6'
];
foreach ($picBatches as $code => $hp) {
    $b = $conn->query("SELECT batch_id, batch_code, house_pen FROM batches WHERE batch_code = '$code'")->fetch(PDO::FETCH_ASSOC);
    $hrCount = $conn->query("SELECT COUNT(*) FROM health_records WHERE batch_id = {$b['batch_id']}")->fetchColumn();
    $fcCount = $conn->query("SELECT COUNT(*) FROM feed_consumption WHERE batch_id = {$b['batch_id']}")->fetchColumn();
    echo "Batch: {$code} ({$hp}) -> ID: {$b['batch_id']} | Health Records: {$hrCount} | Feed Records: {$fcCount}\n";
}

echo "\n--- ANY DATA FROM BATCHES OUTSIDE PICTURE? ---\n";
$bids = implode(',', array_map(function($c) use ($conn) {
    return $conn->query("SELECT batch_id FROM batches WHERE batch_code = '$c'")->fetchColumn();
}, array_keys($picBatches)));

$outsideHr = $conn->query("SELECT COUNT(*) FROM health_records WHERE batch_id NOT IN ($bids)")->fetchColumn();
$outsideFc = $conn->query("SELECT COUNT(*) FROM feed_consumption WHERE batch_id NOT IN ($bids)")->fetchColumn();
echo "Health records outside picture batches: {$outsideHr}\n";
echo "Feed consumption outside picture batches: {$outsideFc}\n";
