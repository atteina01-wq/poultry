<?php
// Simulate HTTP request to index.php with integrator session
$ch = curl_init('http://localhost/poultry/poultrychat/integrator_login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie_mags.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie_mags.txt');
$loginPage = curl_exec($ch);

// Extract CSRF token
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $m);
$token = $m[1] ?? '';

// Perform login as Mags (Magnolia)
curl_setopt($ch, CURLOPT_URL, 'http://localhost/poultry/poultrychat/integrator_login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $token,
    'username' => 'Mags',
    'password' => 'password',
    'login' => 1
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$resHtml = curl_exec($ch);

echo "=== HTTP TEST AS LOGGED-IN INTEGRATOR (Mags) ===\n";

// 1. Check index.php
curl_setopt($ch, CURLOPT_URL, 'http://localhost/poultry/poultrychat/index.php');
curl_setopt($ch, CURLOPT_POST, false);
$indexHtml = curl_exec($ch);

$hasBatchList = (strpos($indexHtml, 'id="batchesSection"') !== false);
echo "1. Batch List on index.php: " . ($hasBatchList ? "VISIBLE (FAIL)" : "REMOVED (PASS)") . "\n";

$hasWorkflow = (strpos($indexHtml, 'Commercial Broiler Reservation &amp; Intake Process') !== false);
echo "2. Reservation Process on index.php: " . ($hasWorkflow ? "PRESENT (PASS)" : "MISSING (FAIL)") . "\n";

$hasStep1 = (strpos($indexHtml, 'Step 1 — Company submits a reservation') !== false);
$hasStep2 = (strpos($indexHtml, 'Step 2 — Farm Admin approves') !== false);
$hasStep3 = (strpos($indexHtml, 'Step 3 — Company delivers the chicks') !== false);
$hasStep4 = (strpos($indexHtml, 'Step 4 — Farm verifies receipt') !== false);
$hasStep5 = (strpos($indexHtml, 'Step 5 — Growing batch is activated') !== false);
echo "3. Steps 1 to 5 Present: " . ($hasStep1 && $hasStep2 && $hasStep3 && $hasStep4 && $hasStep5 ? "ALL 5 PRESENT (PASS)" : "FAIL") . "\n";

if (preg_match('/<div class="stat-val"[^>]*>([\d,]+)<\/div>\s*<div class="stat-lbl">\s*Your Reserved Birds\s*<\/div>/i', $indexHtml, $m)) {
    echo "4. Your Reserved Birds KPI: " . $m[1] . " birds (PASS)\n";
} else {
    echo "4. Your Reserved Birds KPI pattern check...\n";
}

// Check reservations.php
curl_setopt($ch, CURLOPT_URL, 'http://localhost/poultry/poultrychat/reservations.php');
$resPage = curl_exec($ch);

$hasResWorkflow = (strpos($resPage, 'Commercial Broiler Reservation &amp; Intake Process') !== false);
echo "5. Reservation Process on reservations.php: " . ($hasResWorkflow ? "PRESENT (PASS)" : "MISSING (FAIL)") . "\n";

if (preg_match('/<div class="fs-4 fw-bold text-primary mt-1">([\d,]+)/i', $resPage, $m2)) {
    echo "6. Reservations.php KPI Reserved Birds: " . $m2[1] . " birds (PASS)\n";
}

curl_close($ch);
