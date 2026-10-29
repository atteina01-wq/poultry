<?php
session_start();
$_SESSION['user_id'] = 12;
$_SESSION['role'] = 'integrator';
$_SESSION['username'] = 'magnolia';
$_SESSION['full_name'] = 'Magnolia Chicks';
$_SESSION['customer_id'] = 13;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/poultrychat/index.php';

require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/db_check.php';

$isIntegratorUser = (strtolower($_SESSION['role'] ?? '') === 'integrator');
$currUserId = (int)($_SESSION['user_id'] ?? 0);
$currCustId = (int)($_SESSION['customer_id'] ?? 0);

if ($isIntegratorUser) {
    if ($currCustId <= 0 && $currUserId > 0) {
        $stmtFindC = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
        $stmtFindC->execute([$currUserId]);
        $currCustId = (int)($stmtFindC->fetchColumn() ?: 0);
    }
    if ($currCustId > 0) {
        $kpi_stmt = $conn->prepare("
            SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
            FROM reservations r 
            WHERE r.status='active' AND (r.customer_id = ? OR r.created_by = ?)
        ");
        $kpi_stmt->execute([$currCustId, $currUserId]);
    } else {
        $kpi_stmt = $conn->prepare("
            SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
            FROM reservations r 
            WHERE r.status='active' AND r.created_by = ?
        ");
        $kpi_stmt->execute([$currUserId]);
    }
} else {
    $kpi_stmt = $conn->query("
        SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
        FROM reservations r 
        WHERE r.status='active'
    ");
}
$kpi_data = $kpi_stmt ? $kpi_stmt->fetch(PDO::FETCH_ASSOC) : [];
$kpi_active_count = (int)($kpi_data['active_count'] ?? 0);
$kpi_reserved_birds = (int)($kpi_data['birds'] ?? 0);

echo "KPI Active Count: " . $kpi_active_count . "\n";
echo "KPI Reserved Birds: " . $kpi_reserved_birds . "\n";
echo "Is Integrator User: " . ($isIntegratorUser ? "YES" : "NO") . "\n";
