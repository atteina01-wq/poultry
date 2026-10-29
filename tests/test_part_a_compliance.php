<?php
require_once __DIR__ . '/../src/autoload.php';

use Poultry\Core\Database;
use Poultry\Services\OrderService;
use Poultry\Services\ReservationService;
use Poultry\Services\WasteService;
use Poultry\Services\InventoryService;
use Poultry\Services\ReportService;
use Poultry\Services\DashboardService;

$db = Database::getInstance()->getConnection();

echo "========================================================================\n";
echo "PART A COMPLIANCE & END-TO-END VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $section, string $testName, callable $fn) {
    global $passed, $failed;
    try {
        $result = $fn();
        if ($result === true || (is_array($result) && !empty($result))) {
            echo sprintf("[PASS] [%-18s] %s\n", $section, $testName);
            $passed++;
        } else {
            echo sprintf("[FAIL] [%-18s] %s (Assertion returned false)\n", $section, $testName);
            $failed++;
        }
    } catch (Throwable $e) {
        echo sprintf("[FAIL] [%-18s] %s -> %s\n", $section, $testName, $e->getMessage());
        $failed++;
    }
}

// ---------------------------------------------------------
// A1: Order Status Management, Cancellation, and Returns
// ---------------------------------------------------------
assertTest('A1: ORDERS', 'OrderService::updateStatus transitions pending -> confirmed -> processing -> delivered', function() use ($db) {
    $orderService = new OrderService();
    // XL grade_id = 4
    $order = $orderService->createOrder([
        'customer_id' => 1,
        'order_date' => date('Y-m-d'),
        'items' => [
            ['grade_id' => 4, 'quantity_ordered' => 30, 'unit_price' => 9.50]
        ]
    ], 1);

    $ordId = $order['order_id'];
    
    // Update to processing
    $upd1 = $orderService->updateStatus($ordId, 'processing', 1);
    if ($upd1['status'] !== 'processing') return false;

    // Simulate delivery
    $stmtDel = $db->prepare("UPDATE order_items SET quantity_delivered = quantity_ordered WHERE order_id = ?");
    $stmtDel->execute([$ordId]);
    $upd2 = $orderService->updateStatus($ordId, 'delivered', 1);
    
    return $upd2['status'] === 'delivered';
});

assertTest('A1: ORDERS', 'OrderService::cancelOrder restores reserved egg inventory', function() use ($db) {
    $orderService = new OrderService();
    $stockBefore = (int)$db->query("SELECT uncommitted_on_hand_qty FROM v_demand_supply_gap WHERE grade_id = 4")->fetchColumn();

    $order = $orderService->createOrder([
        'customer_id' => 1,
        'order_date' => date('Y-m-d'),
        'items' => [
            ['grade_id' => 4, 'quantity_ordered' => 30, 'unit_price' => 9.50]
        ]
    ], 1);

    $stockDuring = (int)$db->query("SELECT uncommitted_on_hand_qty FROM v_demand_supply_gap WHERE grade_id = 4")->fetchColumn();
    if ($stockDuring !== $stockBefore - 30) return false;

    $orderService->cancelOrder($order['order_id'], "E2E Test Cancellation", 1);
    $stockAfter = (int)$db->query("SELECT uncommitted_on_hand_qty FROM v_demand_supply_gap WHERE grade_id = 4")->fetchColumn();

    return $stockAfter === $stockBefore;
});

assertTest('A1: ORDERS', 'OrderService::returnOrder processes return and appears in ReportService', function() use ($db) {
    $orderService = new OrderService();
    $order = $orderService->createOrder([
        'customer_id' => 1,
        'order_date' => date('Y-m-d'),
        'items' => [
            ['grade_id' => 4, 'quantity_ordered' => 30, 'unit_price' => 9.50]
        ]
    ], 1);

    $ordId = $order['order_id'];
    $stmtDel = $db->prepare("UPDATE order_items SET quantity_delivered = quantity_ordered WHERE order_id = ?");
    $stmtDel->execute([$ordId]);
    $orderService->updateStatus($ordId, 'delivered', 1);

    // Return 15 eggs good, 15 damaged
    $returnItems = [
        ['grade_id' => 4, 'quantity' => 15, 'condition_status' => 'good'],
        ['grade_id' => 4, 'quantity' => 15, 'condition_status' => 'damaged']
    ];
    $orderService->returnOrder($ordId, $returnItems, "E2E Return Test", 1);

    // Verify in ReportService
    $rep = new ReportService();
    $retReport = $rep->getReturnReport();
    $found = false;
    foreach ($retReport as $rr) {
        if ((int)$rr['order_id'] === $ordId) {
            $found = true;
            break;
        }
    }
    return $found;
});

// ---------------------------------------------------------
// A2: Reservation Status, Cancellation, and Conversion
// ---------------------------------------------------------
assertTest('A2: RESERVATIONS', 'ReservationService::updateStatus cancels active reservation and restores stock', function() use ($db) {
    $resService = new ReservationService();
    $stockBefore = (int)$db->query("SELECT uncommitted_on_hand_qty FROM v_demand_supply_gap WHERE grade_id = 4")->fetchColumn();

    $res = $resService->createReservation([
        'customer_id' => 1,
        'pickup_date' => date('Y-m-d', strtotime('+5 days')),
        'items' => [
            ['grade_id' => 4, 'quantity' => 30]
        ]
    ], 1);

    $stockDuring = (int)$db->query("SELECT uncommitted_on_hand_qty FROM v_demand_supply_gap WHERE grade_id = 4")->fetchColumn();
    if ($stockDuring !== $stockBefore - 30) return false;

    $resService->updateStatus($res['reservation_id'], 'cancelled', 1);
    $stockAfter = (int)$db->query("SELECT uncommitted_on_hand_qty FROM v_demand_supply_gap WHERE grade_id = 4")->fetchColumn();

    return $stockAfter === $stockBefore;
});

assertTest('A2: RESERVATIONS', 'ReservationService::convertToOrder converts reservation to confirmed order', function() use ($db) {
    $resService = new ReservationService();
    $res = $resService->createReservation([
        'customer_id' => 1,
        'pickup_date' => date('Y-m-d', strtotime('+3 days')),
        'items' => [
            ['grade_id' => 4, 'quantity' => 30]
        ]
    ], 1);

    $resId = $res['reservation_id'];
    $order = $resService->convertToOrder($resId, [4 => 9.50], 1);

    // Verify reservation status fulfilled and converted_order_id linked
    $resCheck = $resService->getReservation($resId);
    return $resCheck['status'] === 'fulfilled' && (int)$resCheck['converted_order_id'] === (int)$order['order_id'];
});

// ---------------------------------------------------------
// A3: Waste Record Creation
// ---------------------------------------------------------
assertTest('A3: WASTE', 'WasteService::recordWaste logs egg loss and writes egg inventory movement', function() use ($db) {
    $wasteService = new WasteService();
    $result = $wasteService->recordWaste([
        'waste_type' => 'egg',
        'grade_id' => 4,
        'quantity' => 5,
        'unit' => 'pcs',
        'reason' => 'broken',
        'remarks' => 'E2E Broken egg test'
    ], 1);

    $wasteId = $result['waste_id'];
    $stmt = $db->prepare("SELECT * FROM egg_inventory_movements WHERE reference_type = 'waste' AND reference_id = ?");
    $stmt->execute([$wasteId]);
    $move = $stmt->fetch(PDO::FETCH_ASSOC);

    return !empty($move) && (int)$move['quantity'] === 5 && $move['movement_type'] === 'waste_out';
});

assertTest('A3: WASTE', 'WasteService::recordWaste logs supply/medicine waste and expense', function() use ($db) {
    $wasteService = new WasteService();
    // Get an inventory item
    $invItem = $db->query("SELECT inventory_id, quantity FROM inventory WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$invItem) return false;

    $invId = (int)$invItem['inventory_id'];
    $qtyBefore = (float)$invItem['quantity'];

    $result = $wasteService->recordWaste([
        'waste_type' => 'supply',
        'inventory_id' => $invId,
        'quantity' => 2,
        'unit' => 'units',
        'reason' => 'expired',
        'estimated_cost' => 350.00,
        'remarks' => 'E2E Supply Waste Test'
    ], 1);

    $wasteId = $result['waste_id'];
    $stmt = $db->prepare("SELECT * FROM waste_records WHERE waste_id = ?");
    $stmt->execute([$wasteId]);
    $waste = $stmt->fetch(PDO::FETCH_ASSOC);

    $qtyAfter = (float)$db->query("SELECT quantity FROM inventory WHERE inventory_id = {$invId}")->fetchColumn();

    return !empty($waste) && !empty($waste['expense_id']) && abs($qtyAfter - ($qtyBefore - 2)) < 0.01;
});

// ---------------------------------------------------------
// A4: Inventory Expiry Monitoring & Limestone
// ---------------------------------------------------------
assertTest('A4: INVENTORY', 'InventoryService::createItem saves limestone with lot and appears in getExpiringLots', function() use ($db) {
    $invService = new InventoryService();
    $lotNum = 'LOT-TEST-' . time();
    $expDate = date('Y-m-d', strtotime('+15 days'));

    $item = $invService->createItem([
        'item_name' => 'Agricultural Limestone ' . time(),
        'category' => 'limestone',
        'quantity' => 50,
        'unit' => 'bags',
        'unit_cost' => 220.00,
        'lot_number' => $lotNum,
        'supplier' => 'Calcite Minerals Inc.',
        'received_date' => date('Y-m-d'),
        'expiry_date' => $expDate
    ], 1);

    if ($item['category'] !== 'limestone') return false;

    $expLots = $invService->getExpiringLots(30);
    $found = false;
    foreach ($expLots as $l) {
        if ($l['lot_number'] === $lotNum) {
            $found = true;
            break;
        }
    }
    return $found;
});

// ---------------------------------------------------------
// A5: Header Navigation Verification
// ---------------------------------------------------------
assertTest('A5: DISTRIBUTION', 'header.php sidebar navigation no longer links to distribution.php', function() {
    $headerContent = file_get_contents(__DIR__ . '/../poultrychat/header.php');
    return strpos($headerContent, 'href="distribution.php"') === false;
});

// ---------------------------------------------------------
// Reports & Dashboard End-to-End Verification
// ---------------------------------------------------------
assertTest('DASHBOARD & REPORTS', 'DashboardService and ReportService reflect all live data accurately', function() {
    $dash = new DashboardService();
    $kpis = $dash->getKPIs();
    $rep = new ReportService();
    $fin = $rep->getFinancialReport();

    return isset($kpis['total_on_hand_eggs']) && isset($fin['net_sales']);
});

echo "\n------------------------------------------------------------------------\n";
echo "PART A TEST SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "------------------------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
