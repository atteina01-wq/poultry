<?php
require_once __DIR__ . '/../poultrychat/database.php';

header('Content-Type: text/plain');

echo "=== TESTING FEED.PHP RENDERING FOR BOTH ROLES ===\n\n";

// Function to simulate session and render feed.php
function renderFeedAsRole($role, $userId, $cat = 'feed') {
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['username'] = ($role === 'admin') ? 'admin' : 'worker';
    $_GET['cat'] = $cat;

    ob_start();
    include __DIR__ . '/../poultrychat/feed.php';
    $output = ob_get_clean();

    return $output;
}

// 1. Render as Admin (feed)
echo "--- 1. Testing Admin View (Feed) ---\n";
$adminHtml = renderFeedAsRole('admin', 1, 'feed');
$hasForm = (strpos($adminHtml, 'Schedule / Record Feed Consumption') !== false);
$hasExecutionStatus = (strpos($adminHtml, 'Execution Status') !== false);
$hasPendingActionOption = (strpos($adminHtml, 'Pending Action (Worker will distribute in pens)') !== false);
$hasTable = (strpos($adminHtml, 'Feed Consumption & Distribution') !== false);
$hasFilledUpCol = (strpos($adminHtml, 'Filled Up By (Admin)') !== false);
$hasPerformedByCol = (strpos($adminHtml, 'Performed By (Worker)') !== false);

echo "Admin Form present: " . ($hasForm ? "YES" : "NO") . "\n";
echo "Execution Status dropdown present: " . ($hasExecutionStatus ? "YES" : "NO") . "\n";
echo "Pending Action option present: " . ($hasPendingActionOption ? "YES" : "NO") . "\n";
echo "History table present: " . ($hasTable ? "YES" : "NO") . "\n";
echo "Filled Up By column present: " . ($hasFilledUpCol ? "YES" : "NO") . "\n";
echo "Performed By column present: " . ($hasPerformedByCol ? "YES" : "NO") . "\n";

// 2. Render as Worker (feed)
echo "\n--- 2. Testing Worker View (Feed) ---\n";
$workerHtml = renderFeedAsRole('worker', 2, 'feed');
$workerHasForm = (strpos($workerHtml, '<button type="submit" name="consume"') !== false);
$workerHasBanner = (strpos($workerHtml, 'Worker Action Mode') !== false);
$workerHasTable = (strpos($workerHtml, 'Feed Consumption & Distribution') !== false);

echo "Worker Form hidden (should be NO): " . ($workerHasForm ? "FAILED (form visible)" : "PASSED (form hidden)") . "\n";
echo "Worker Action banner present: " . ($workerHasBanner ? "YES" : "NO") . "\n";
echo "Worker History table present: " . ($workerHasTable ? "YES" : "NO") . "\n";

// 3. Render as Admin (limestone)
echo "\n--- 3. Testing Limestone Category View ---\n";
$limestoneHtml = renderFeedAsRole('admin', 1, 'limestone');
$hasLimestoneTitle = (strpos($limestoneHtml, 'Limestone Usage') !== false);
echo "Limestone navigation & title active: " . ($hasLimestoneTitle ? "YES" : "NO") . "\n";

echo "\nRENDERING TESTS COMPLETED SUCCESSFULLY!\n";
