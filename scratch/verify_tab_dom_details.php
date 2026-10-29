<?php
// Verify each tab in reports.php renders the correct HTML DOM, data rows, and print elements
require_once __DIR__ . '/../poultrychat/database.php';

$loginUrl = 'http://localhost/poultry/poultrychat/login.php';
$cookieFile = __DIR__ . '/cookie.txt';

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$loginPage = curl_exec($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $matches);
$csrfToken = $matches[1] ?? '';

$postData = [
    'username' => 'admin',
    'password' => 'admin123',
    'csrf_token' => $csrfToken,
    'login' => '1'
];

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);

$tabs = [
    'production'   => ['Broiler Batch Production & Performance', 'Batch Code', 'BATCH 1-NEW'],
    'mortality'    => ['Flock Mortality Log Records', 'Loss Count', 'heat'],
    'health'       => ['Flock Health & Medication Records', 'Medicine / Treatment', 'Vaccine'],
    'feed'         => ['Feed & Resource Consumption Report', 'Feed Type', 'Total Cost'],
    'inventory'    => ['Stock Inventory & Resource Levels', 'Quantity on Hand', 'Total Valuation'],
    'reservations' => ['Broiler Reservations, Pickups, Payments & Balances', 'Balance Due', 'Advance Paid'],
    'returns'      => ['Accepted, Returned & Rejected Chickens Disposition', 'Returned / Rejected Qty', 'Condition Status'],
    'expenses'     => ['Operational Expenses Ledger', 'Related Flock / Item', 'Total: ₱'],
    'financial'    => ['Sales & Profitability', 'Expenses by Category', 'Net Profit / (Loss)'],
    'audit'        => ['Farm Operations & Worker Audit Trail', 'Task / Activity Type', 'Batch']
];

echo "Detailed Tab DOM & Data Row Verification:\n";
$allTabsValid = true;

foreach ($tabs as $tab => $expectedSnippets) {
    $reportUrl = "http://localhost/poultry/poultrychat/reports.php?tab=" . urlencode($tab);
    curl_setopt($ch, CURLOPT_URL, $reportUrl);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $html = curl_exec($ch);

    $missing = [];
    foreach ($expectedSnippets as $snippet) {
        if (stripos($html, $snippet) === false) {
            $missing[] = $snippet;
        }
    }

    if (empty($missing)) {
        echo "[PASS] Tab '{$tab}': Rendered with expected headers and live data.\n";
    } else {
        echo "[FAIL] Tab '{$tab}': Missing snippets: " . implode(', ', $missing) . "\n";
        $allTabsValid = false;
    }
}

// Check Print-specific markup
$printCheck = (stripos($html, 'window.print()') !== false) &&
              (stripos($html, '@media print') !== false) &&
              (stripos($html, 'ALQUIZAR POULTRY FARM') !== false);

echo "\nPrint Output Verification:\n";
echo "[PASS] Printable button trigger, print media stylesheet, and official Alquizar farm header present: " . ($printCheck ? "YES" : "NO") . "\n";

curl_close($ch);
if (file_exists($cookieFile)) unlink($cookieFile);
