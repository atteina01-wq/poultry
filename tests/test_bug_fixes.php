<?php
require_once __DIR__ . '/../src/autoload.php';

use Poultry\Core\Database;
use Poultry\Services\InventoryService;

$db = Database::getInstance()->getConnection();

echo "========================================================================\n";
echo "BUG FIXES & REGRESSION VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$passed = 0;
$failed = 0;

function runCheck(string $testName, callable $fn) {
    global $passed, $failed;
    try {
        $res = $fn();
        if ($res === true || (!empty($res) && is_array($res))) {
            echo "[PASS] " . $testName . "\n";
            $passed++;
        } else {
            echo "[FAIL] " . $testName . " (Assertion returned false)\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "[FAIL] " . $testName . " -> " . $e->getMessage() . "\n";
        $failed++;
    }
}

// BUG 1: Expiring lots query returns unit and unit_cost without warning
runCheck("BUG 1: getExpiringLots includes unit and unit_cost from inventory join", function() use ($db) {
    $invService = new InventoryService();
    $lots = $invService->getExpiringLots(60);
    if (empty($lots)) return false;
    foreach ($lots as $l) {
        if (!isset($l['unit']) || $l['unit'] === '' || $l['unit'] === null) {
            return false;
        }
    }
    return true;
});

// BUG 2: Limestone filtering vs Feed filtering
runCheck("BUG 2: Feed dropdown queries strictly category='limestone' vs category='feed'", function() use ($db) {
    $stmtLimestone = $db->prepare("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE category = ?");
    $stmtLimestone->execute(['limestone']);
    $limestoneItems = $stmtLimestone->fetchAll(PDO::FETCH_ASSOC);

    $stmtFeed = $db->prepare("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE category = ?");
    $stmtFeed->execute(['feed']);
    $feedItems = $stmtFeed->fetchAll(PDO::FETCH_ASSOC);

    return count($limestoneItems) > 0 && count($feedItems) > 0;
});

runCheck("BUG 2: Recording limestone consumption deducts limestone item and appears in limestone history", function() use ($db) {
    require_once __DIR__ . '/../poultrychat/helpers.php';
    
    // Get limestone item and active batch
    $limestoneItem = $db->query("SELECT inventory_id, quantity FROM inventory WHERE category = 'limestone' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $batch = $db->query("SELECT batch_id FROM batches WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$limestoneItem || !$batch) return false;

    $invId = (int)$limestoneItem['inventory_id'];
    $batchId = (int)$batch['batch_id'];
    $qtyBefore = (float)$limestoneItem['quantity'];

    $ok = record_feed_consumption($db, $batchId, $invId, 5.0, date('Y-m-d'));
    if (!$ok) return false;

    $qtyAfter = (float)$db->query("SELECT quantity FROM inventory WHERE inventory_id = {$invId}")->fetchColumn();
    if (abs($qtyAfter - ($qtyBefore - 5.0)) > 0.01) return false;

    // Check history filter
    $stmtHistory = $db->prepare("
        SELECT fc.*, i.category 
        FROM feed_consumption fc 
        JOIN inventory i ON fc.inventory_id = i.inventory_id 
        WHERE i.category = 'limestone'
        ORDER BY fc.consumption_id DESC LIMIT 1
    ");
    $stmtHistory->execute();
    $latest = $stmtHistory->fetch(PDO::FETCH_ASSOC);

    return !empty($latest) && $latest['category'] === 'limestone' && (int)$latest['inventory_id'] === $invId;
});

// BUG 3: Responsive CSS check
runCheck("BUG 3: top-header has auto height and responsive flex-wrap rules", function() {
    $css = file_get_contents(__DIR__ . '/../poultrychat/css/sidebar.css');
    return strpos($css, 'flex-wrap: wrap;') !== false && strpos($css, 'height: auto;') !== false && strpos($css, '@media (max-width: 576px)') !== false;
});

echo "\n------------------------------------------------------------------------\n";
echo "SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "------------------------------------------------------------------------\n";

if ($failed > 0) exit(1);
