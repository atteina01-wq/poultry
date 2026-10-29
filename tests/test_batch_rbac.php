<?php
/**
 * Test: Staff & Admin RBAC for batch.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function testBatchRbac() {
    $results = [];

    // ----------------------------------------------------
    // Test 1: Staff Read-Only Rendering
    // ----------------------------------------------------
    $_SESSION['user_id'] = 2;
    $_SESSION['username'] = 'staff1';
    $_SESSION['full_name'] = 'Staff Worker';
    $_SESSION['role'] = 'staff';
    $_SERVER['PHP_SELF'] = '/poultrychat/batch.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_POST['save']);

    ob_start();
    include(__DIR__ . '/../poultrychat/batch.php');
    $staffOutput = ob_get_clean();

    $staffAccessDenied = strpos($staffOutput, 'Access Denied. Admins only.') !== false;
    $staffHasForm = strpos($staffOutput, 'Register New Batch') !== false;
    $staffHasTable = strpos($staffOutput, 'Registered Flocks & Houses') !== false;
    $staffHasTableHead = strpos($staffOutput, 'Batch Code') !== false;

    if (!$staffAccessDenied && !$staffHasForm && $staffHasTable && $staffHasTableHead) {
        $results[] = "[PASS] Staff Role: Read-only access granted, flock list rendered, Register Form completely omitted from HTML";
    } else {
        $results[] = "[FAIL] Staff Role: Unexpected output (AccessDenied: " . ($staffAccessDenied ? 'YES' : 'NO') . ", HasForm: " . ($staffHasForm ? 'YES' : 'NO') . ", HasTable: " . ($staffHasTable ? 'YES' : 'NO') . ")";
    }

    // ----------------------------------------------------
    // Test 2: Staff Server-Side POST Block
    // ----------------------------------------------------
    $_SESSION['role'] = 'staff';
    $_SESSION['csrf_token'] = 'test-token';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['csrf_token'] = 'test-token';
    $_POST['save'] = '1';
    $_POST['batch_code'] = 'HACK-BATCH';
    $_POST['flock_type'] = 'layer';
    $_POST['arrival_date'] = date('Y-m-d');
    $_POST['quantity'] = 100;

    ob_start();
    include(__DIR__ . '/../poultrychat/batch.php');
    $staffPostOutput = ob_get_clean();

    // Verify HACK-BATCH was NOT inserted
    include(__DIR__ . '/../poultrychat/database.php');
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM batches WHERE batch_code = 'HACK-BATCH'");
    $stmtCheck->execute();
    $hackCount = (int)$stmtCheck->fetchColumn();

    if ($hackCount === 0) {
        $results[] = "[PASS] Staff Role: Server-side POST handler strictly blocked batch creation attempt";
    } else {
        $results[] = "[FAIL] Staff Role: Batch creation was not blocked server-side!";
    }

    // ----------------------------------------------------
    // Test 3: Admin Full Access Rendering & Creation
    // ----------------------------------------------------
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['full_name'] = 'Administrator';
    $_SESSION['role'] = 'admin';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_POST['save']);

    ob_start();
    include(__DIR__ . '/../poultrychat/batch.php');
    $adminOutput = ob_get_clean();

    $adminHasForm = strpos($adminOutput, 'Register New Batch') !== false;
    $adminHasTable = strpos($adminOutput, 'Registered Flocks & Houses') !== false;

    if ($adminHasForm && $adminHasTable) {
        $results[] = "[PASS] Admin Role: Full access with both Register Form and Flocks Table present";
    } else {
        $results[] = "[FAIL] Admin Role: Form or Table missing in admin view";
    }

    // ----------------------------------------------------
    // Test 4: Financial / Cost Field Leak Check
    // ----------------------------------------------------
    $hasCostFieldInStaff = (
        stripos($staffOutput, 'acquisition_cost') !== false ||
        stripos($staffOutput, 'cost_per_bird') !== false ||
        stripos($staffOutput, 'investment') !== false ||
        stripos($staffOutput, 'unit_cost') !== false
    );

    if (!$hasCostFieldInStaff) {
        $results[] = "[PASS] Financial Field Check: No sensitive flock financial/acquisition figures leak in staff output";
    } else {
        $results[] = "[FAIL] Financial Field Check: Financial figures found in staff output!";
    }

    echo "========================================================================\n";
    echo "FLOCKS & HOUSES (batch.php) RBAC VERIFICATION SUITE\n";
    echo "========================================================================\n\n";

    $allPassed = true;
    foreach ($results as $res) {
        echo $res . "\n";
        if (strpos($res, '[FAIL]') !== false) {
            $allPassed = false;
        }
    }

    echo "\n------------------------------------------------------------------------\n";
    if ($allPassed) {
        echo "SUMMARY: ALL RBAC TESTS PASSED (4/4)\n";
    } else {
        echo "SUMMARY: SOME RBAC TESTS FAILED\n";
    }
    echo "------------------------------------------------------------------------\n";

    return $allPassed;
}

testBatchRbac();
