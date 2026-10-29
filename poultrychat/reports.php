<?php 
include('database.php'); 
include('header.php'); 

if ($_SESSION['role'] !== 'admin') {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Insufficient permissions.</div></div>";
    include('footer.php');
    exit();
}

$reportService = new \Poultry\Services\ReportService();

// Filter Parameters
$datePreset = $_GET['preset'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;
$batchFilter = !empty($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;
$statusFilter = !empty($_GET['status']) ? $_GET['status'] : null;
$activeTab = $_GET['tab'] ?? 'production';
$exportPdf = (($_GET['export'] ?? '') === 'pdf');
$allowedSections = ['production','mortality','health','feed','inventory','reservations','returns','expenses','financial','audit'];
$pdfSections = [];
if (!empty($_GET['sections'])) {
    foreach (explode(',', (string)$_GET['sections']) as $sec) {
        $sec = strtolower(trim($sec));
        if (in_array($sec, $allowedSections, true)) {
            $pdfSections[] = $sec;
        }
    }
    $pdfSections = array_values(array_unique($pdfSections));
}
if ($exportPdf && empty($pdfSections) && $activeTab !== 'all' && in_array($activeTab, $allowedSections, true)) {
    $pdfSections = [$activeTab];
}

if ($datePreset === 'today') {
    $dateFrom = date('Y-m-d');
    $dateTo = date('Y-m-d');
} elseif ($datePreset === 'this_week') {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = date('Y-m-d', strtotime('sunday this week'));
} elseif ($datePreset === 'this_month') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-t');
}

// Which report datasets to load
$wantSection = function (string $key) use ($activeTab, $exportPdf, $pdfSections): bool {
    if ($exportPdf) {
        return empty($pdfSections) || in_array($key, $pdfSections, true);
    }
    return $activeTab === $key || $activeTab === 'all';
};

// Fetch all batches for filter dropdown
$batchesList = $conn->query("SELECT batch_id, batch_code, status FROM batches ORDER BY arrival_date DESC, batch_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch financial overview for top summary cards
$financialReport = $reportService->getFinancialReport($dateFrom, $dateTo);

// Fetch datasets according to active / selected PDF sections
$productionReport = $wantSection('production')
    ? $reportService->getProductionReport($dateFrom, $dateTo, $batchFilter, $statusFilter) : [];

$mortalityReport = $wantSection('mortality')
    ? $reportService->getMortalityReport($dateFrom, $dateTo, $batchFilter) : [];

// Health: 4th arg is record type (vaccine/meds), 5th is health status — never pass batch status here
$healthReport = $wantSection('health')
    ? $reportService->getHealthRecordReport($dateFrom, $dateTo, $batchFilter, null, null) : [];

$consumptionReport = $wantSection('feed')
    ? $reportService->getConsumptionReport($dateFrom, $dateTo, $batchFilter) : [];

// Inventory: 3rd=category, 4th=stock status (in_stock/low_stock/out_of_stock) — not batch status
$inventoryReport = $wantSection('inventory')
    ? $reportService->getInventoryReport($dateFrom, $dateTo, null, null) : ['items' => []];

$reservationsReport = $wantSection('reservations')
    ? $reportService->getReservationReport($dateFrom, $dateTo, $statusFilter, $batchFilter) : [];

$returnsReport = [];
if ($wantSection('returns')) {
    try {
        $whereRet = ["r.rejected_quantity > 0"];
        $paramsRet = [];
        if ($dateFrom) {
            $whereRet[] = "r.actual_pickup_date >= ?";
            $paramsRet[] = $dateFrom;
        }
        if ($dateTo) {
            $whereRet[] = "r.actual_pickup_date <= ?";
            $paramsRet[] = $dateTo;
        }
        if ($batchFilter) {
            $whereRet[] = "r.batch_id = ?";
            $paramsRet[] = $batchFilter;
        }
        $retSql = "SELECT 
                    r.reservation_id,
                    r.reservation_number as return_number,
                    COALESCE(r.receipt_number, r.reservation_number) as order_number,
                    c.name as customer_name,
                    COALESCE(r.actual_pickup_date, r.pickup_date) as return_date,
                    COALESCE(r.rejected_quantity, 0) as total_returned_qty,
                    'rejected' as condition_status,
                    COALESCE(r.rejection_reason, 'Handling Injury / Rejection') as reason,
                    r.notes,
                    COALESCE(u.username, 'Staff') as returned_by_user
                   FROM reservations r
                   JOIN customers c ON r.customer_id = c.customer_id
                   LEFT JOIN users u ON r.created_by = u.user_id
                   WHERE " . implode(" AND ", $whereRet) . "
                   ORDER BY r.actual_pickup_date DESC";
        $stmtRet = $conn->prepare($retSql);
        $stmtRet->execute($paramsRet);
        $returnsReport = $stmtRet->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $returnsReport = [];
    }
}

// Expenses: 3rd arg is expense category — do not pass batch/reservation status
$expensesReport = ($wantSection('expenses') || $wantSection('financial'))
    ? $reportService->getExpenseReport($dateFrom, $dateTo, null, $batchFilter) : [];

$workerReport = $wantSection('audit')
    ? $reportService->getWorkerActivityReport($dateFrom, $dateTo, $batchFilter, null) : [];

// Human readable tab labels
$tabTitles = [
    'production'   => 'Broiler Batch Production & Performance',
    'mortality'    => 'Flock Mortality Records',
    'health'       => 'Flock Health & Medication',
    'feed'         => 'Feed Consumption & Utilization',
    'inventory'    => 'Stock Inventory & Resource Levels',
    'reservations' => 'Broiler Reservations & Advance Bookings',
    'returns'      => 'Accepted, Returned & Rejected Chickens',
    'expenses'     => 'Operational Expenses Ledger',
    'financial'    => 'Financials, Revenues & Profitability',
    'audit'        => 'Worker Operations & Audit Trail'
];
$pdfSectionIcons = [
    'production'   => 'fa-feather-pointed',
    'mortality'    => 'fa-skull',
    'health'       => 'fa-heart-pulse',
    'feed'         => 'fa-wheat-awn',
    'inventory'    => 'fa-warehouse',
    'reservations' => 'fa-calendar-check',
    'returns'      => 'fa-arrow-rotate-left',
    'expenses'     => 'fa-wallet',
    'financial'    => 'fa-chart-line',
    'audit'        => 'fa-clipboard-check'
];

$pdfBatchLabel = null;
if ($batchFilter) {
    $pdfBatchLabel = 'Batch #' . $batchFilter;
    foreach ($batchesList as $b) {
        if ((int)$b['batch_id'] === $batchFilter) {
            $pdfBatchLabel = $b['batch_code'];
            break;
        }
    }
}
?>

<style>
    .report-tabs .nav-link {
        color: var(--brand-dark, #334155);
        font-weight: 600;
        border-radius: 10px;
        padding: 9px 15px;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .report-tabs .nav-link.active {
        background-color: var(--brand-dark, #334155) !important;
        color: #fff !important;
        box-shadow: 0 4px 10px rgba(12, 56, 46, 0.25);
    }
    .filter-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
    }
    .report-kpi-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .report-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.08) !important;
    }

    /* Print Stylesheet */
    @media print {
        @page { size: A4 portrait; margin: 10mm; }
        .sidebar, .navbar, nav, header, footer, .btn, .nav-pills, .filter-card, .no-print {
            display: none !important;
        }
        body, .main-content, .container-fluid {
            background: #fff !important;
            color: #000 !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .card {
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
            page-break-inside: avoid;
            margin-bottom: 20px !important;
        }
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        .table th, .table td {
            border: 1px solid #dee2e6 !important;
            padding: 6px 8px !important;
            font-size: 10pt !important;
        }
        .badge {
            border: 1px solid #666 !important;
            color: #000 !important;
            background: #fff !important;
        }
        .print-header {
            display: block !important;
            margin-bottom: 20px;
            border-bottom: 2px solid #334155;
            padding-bottom: 10px;
        }
        .tab-content > .tab-pane {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .tab-content > .tab-pane:not(.active) {
            display: none !important;
        }
    }
    @media screen {
        .print-header {
            display: none;
        }
    }

    /* PDF export — DengGuard-style formal letterhead (A4 portrait) */
    #pdfExportDoc {
        display: none;
        background: #ffffff;
        color: #1f2937;
        padding: 12px 14px 16px;
        width: 700px; /* fits A4 portrait printable width after margins */
        max-width: 700px;
        box-sizing: border-box;
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        border: 1px solid #cbd5e1;
        overflow: visible;
    }
    body.pdf-exporting .no-print,
    body.pdf-exporting .sidebar,
    body.pdf-exporting .navbar,
    body.pdf-exporting .top-header,
    body.pdf-exporting .app-layout > nav,
    body.pdf-exporting .app-footer,
    body.pdf-exporting #reportTab,
    body.pdf-exporting .filter-card,
    body.pdf-exporting .report-kpi-card,
    body.pdf-exporting #reportTabContent,
    body.pdf-exporting #printReportModal,
    body.pdf-exporting .modal-backdrop {
        display: none !important;
    }
    body.pdf-exporting #pdfExportDoc {
        display: block !important;
        position: absolute !important;
        left: -10000px !important; /* keep source off-screen; capture uses a clone */
        top: 0 !important;
        margin: 0 !important;
        transform: none !important;
        visibility: visible !important;
        opacity: 1 !important;
        background: #ffffff !important;
        color: #1f2937 !important;
        width: 700px !important;
        max-width: 700px !important;
    }
    body[data-theme="neon-farm"] #pdfCaptureHost,
    #pdfCaptureHost {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 700px !important;
        background: #ffffff !important;
        z-index: 2147483646 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    /* Capture host: do NOT blanket-color * — that nukes white thead text */
    body[data-theme="neon-farm"] #pdfCaptureHost,
    #pdfCaptureHost {
        color: #1f2937 !important;
        text-shadow: none !important;
        box-shadow: none !important;
        background: #ffffff !important;
    }
    body.pdf-exporting #pdfExportDoc .pdf-letterhead,
    body.pdf-exporting #pdfExportDoc .pdf-footer,
    body.pdf-exporting #pdfExportDoc #pdfExportBody,
    body.pdf-exporting #pdfExportDoc #pdfKeyStats {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100% !important;
    }
    body.pdf-exporting #pdfExportDoc * {
        text-shadow: none !important;
        box-shadow: none !important;
    }
    #pdfExportDoc .pdf-letterhead {
        border-bottom: none;
        padding-bottom: 0;
        margin-bottom: 12px;
        width: 100%;
    }
    #pdfExportDoc .pdf-letterhead-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin: 0 0 8px;
    }
    #pdfExportDoc .pdf-letterhead-table td {
        border: 0 !important;
        background: transparent !important;
        vertical-align: middle;
        padding: 0 !important;
    }
    #pdfExportDoc .pdf-letterhead-table .pdf-logo-cell {
        width: 100px;
        text-align: left;
        vertical-align: middle;
    }
    #pdfExportDoc .pdf-logo {
        width: 90px;
        height: 90px;
        object-fit: cover;
        object-position: center;
        border-radius: 50%;
        border: 3px solid #0f766e;
        background: #000;
        display: inline-block;
    }
    #pdfExportDoc .pdf-agency-block {
        text-align: center;
        line-height: 1.3;
        padding: 0 8px;
    }
    #pdfExportDoc .pdf-agency-line {
        margin: 0;
        font-size: 11px;
        color: #334155 !important;
        font-weight: 500;
    }
    #pdfExportDoc .pdf-agency-name {
        margin: 3px 0 0;
        font-size: 16px;
        font-weight: 800;
        color: #0f172a !important;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    #pdfExportDoc .pdf-agency-office {
        margin: 3px 0 0;
        font-size: 13px;
        font-weight: 800;
        color: #0f172a !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #pdfExportDoc .pdf-rule {
        height: 0;
        border: 0;
        border-top: 3px solid #0f766e;
        margin: 8px 0 10px;
        width: 100%;
    }
    #pdfExportDoc .pdf-doc-title {
        margin: 0;
        text-align: center;
        font-size: 18px;
        font-weight: 800;
        color: #0f766e !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        line-height: 1.25;
        width: 100%;
    }
    #pdfExportDoc .pdf-doc-meta {
        margin: 6px 0 0;
        text-align: center;
        font-size: 10px;
        color: #64748b !important;
        line-height: 1.5;
        width: 100%;
    }
    #pdfExportDoc .pdf-doc-meta strong {
        color: #475569 !important;
        font-weight: 700;
    }
    #pdfExportDoc .pdf-section {
        margin-top: 14px;
        margin-bottom: 10px;
        width: 100%;
        page-break-inside: avoid;
    }
    #pdfExportDoc .pdf-section-title {
        margin: 0 0 8px;
        padding: 0 0 5px;
        font-size: 13px;
        font-weight: 800;
        color: #0f766e !important;
        background: transparent !important;
        border-radius: 0;
        border-bottom: 2px solid #14b8a6;
        text-transform: none;
        letter-spacing: 0.01em;
        width: 100%;
    }
    #pdfExportDoc .pdf-stat-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px;
        margin: 4px 0;
        table-layout: fixed;
    }
    #pdfExportDoc .pdf-stat-table > tbody > tr > td {
        width: 25%;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
        vertical-align: stretch;
    }
    #pdfExportDoc .pdf-stat-box {
        border: 2px solid #14b8a6 !important;
        background: #f8fafc !important;
        padding: 14px 8px !important;
        text-align: center;
        box-sizing: border-box;
        min-height: 64px;
    }
    #pdfExportDoc .pdf-stat-value {
        display: block;
        font-size: 15px;
        font-weight: 800;
        color: #0f172a !important;
        line-height: 1.2;
        margin-bottom: 4px;
        word-break: break-word;
    }
    #pdfExportDoc .pdf-stat-label {
        display: block;
        font-size: 8.5px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b !important;
    }
    #pdfExportDoc .pdf-note {
        margin: 6px 0 0;
        font-size: 9px;
        font-style: italic;
        color: #64748b !important;
    }
    #pdfExportDoc h2, #pdfExportDoc h4, #pdfExportDoc h5 {
        color: #0f766e !important;
    }
    #pdfExportDoc #pdfExportBody,
    #pdfExportDoc #pdfKeyStats {
        width: 100%;
    }
    #pdfExportDoc .table {
        width: 100% !important;
        border-collapse: collapse;
        font-size: 8px;
        margin-bottom: 0;
        table-layout: auto;
    }
    #pdfExportDoc .table th,
    #pdfExportDoc .table td {
        border: 1px solid #cbd5e1 !important;
        padding: 5px 4px !important;
        color: #1f2937 !important;
        background: #fff !important;
        vertical-align: middle;
    }
    #pdfExportDoc .table thead th {
        background: #0f766e !important;
        color: #ffffff !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 7.5px;
        letter-spacing: 0.02em;
        border-color: #0f766e !important;
    }
    #pdfExportDoc .table tbody tr:nth-child(even) td {
        background: #f1f5f9 !important;
    }
    #pdfExportDoc .card,
    #pdfExportDoc .card-body,
    #pdfExportDoc .table-responsive,
    #pdfExportDoc .rounded-4,
    #pdfExportDoc .shadow-sm,
    #pdfExportDoc .bg-white,
    #pdfExportDoc .bg-light {
        background: #ffffff !important;
        background-color: #ffffff !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        color: #1f2937 !important;
        overflow: visible !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    #pdfExportDoc .badge,
    #pdfExportDoc .status-pill {
        background: transparent !important;
        color: #0f172a !important;
        border: 0 !important;
        padding: 0 !important;
        font-weight: 700 !important;
    }
    #pdfExportDoc .pdf-footer {
        margin-top: 16px;
        padding-top: 8px;
        border-top: 2px solid #0f766e;
        font-size: 8px;
        color: #64748b !important;
        width: 100%;
    }
    #pdfExportDoc .pdf-footer-table {
        width: 100%;
        border-collapse: collapse;
    }
    #pdfExportDoc .pdf-footer-table td {
        border: 0 !important;
        background: transparent !important;
        color: #64748b !important;
        font-size: 8px;
        padding: 0 !important;
        vertical-align: top;
    }
    .pdf-section-check {
        border: 1px solid rgba(61, 255, 154, 0.18);
        border-radius: 12px;
        padding: 0.75rem 0.9rem;
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
        user-select: none;
    }
    .pdf-section-check:hover {
        border-color: rgba(61, 255, 154, 0.4);
    }
    .pdf-section-check:has(input:checked) {
        border-color: rgba(61, 255, 154, 0.55);
        background: rgba(61, 255, 154, 0.08);
    }
</style>

<!-- Official Print Header (Only visible on paper / print preview) -->
<div class="print-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-0 fw-bold" style="color: #334155;">ALQUIZAR POULTRY FARM</h2>
            <h5 class="mb-1 text-secondary"><?php echo htmlspecialchars($tabTitles[$activeTab] ?? 'Centralized Operations Report'); ?></h5>
            <small class="text-muted">Chapter 1 Broiler Production, Flock Health, Feed, Inventory, Reservations & Financials</small>
        </div>
        <div class="text-end">
            <div><strong>Generated:</strong> <?php echo date('M d, Y h:i A'); ?></div>
            <div><strong>Period:</strong> <?php echo $dateFrom ? date('M d, Y', strtotime($dateFrom)) : 'Start'; ?> &mdash; <?php echo $dateTo ? date('M d, Y', strtotime($dateTo)) : 'Present'; ?></div>
            <?php if ($batchFilter): 
                $bCode = 'Batch #' . $batchFilter;
                foreach ($batchesList as $b) { if ($b['batch_id'] == $batchFilter) { $bCode = $b['batch_code']; break; } }
            ?>
                <div><strong>Filtered Batch:</strong> <?php echo htmlspecialchars($bCode); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Screen Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div>
        <h3 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2 text-success"></i>Centralized Farm Reports & Auditing</h3>
        <small class="text-muted">Alquizar Poultry Farm &mdash; Broiler Batches, Health, Feed, Inventory, Reservations, Returns, Expenses & Profitability</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#printReportModal">
            <i class="fas fa-file-pdf me-1 text-danger"></i>Print Report
        </button>
    </div>
</div>

<!-- Global Date & Batch Filter Bar -->
<div class="card p-3 rounded-4 shadow-sm mb-4 filter-card no-print">
    <form method="GET" action="reports.php" class="row g-2 align-items-center">
        <input type="hidden" name="tab" id="activeTabInput" value="<?php echo htmlspecialchars($activeTab); ?>">
        
        <?php
        $filterQs = 'tab=' . urlencode($activeTab);
        if ($batchFilter) $filterQs .= '&batch_id=' . (int)$batchFilter;
        if ($statusFilter) $filterQs .= '&status=' . urlencode($statusFilter);
        ?>
        <!-- Preset Date Buttons -->
        <div class="col-12 col-xl-4 col-lg-5">
            <div class="btn-group btn-group-sm w-100" role="group">
                <a href="reports.php?preset=all&<?php echo $filterQs; ?>" class="btn <?php echo $datePreset === 'all' ? 'btn-success' : 'btn-outline-success'; ?>">All Time</a>
                <a href="reports.php?preset=today&<?php echo $filterQs; ?>" class="btn <?php echo $datePreset === 'today' ? 'btn-success' : 'btn-outline-success'; ?>">Today</a>
                <a href="reports.php?preset=this_week&<?php echo $filterQs; ?>" class="btn <?php echo $datePreset === 'this_week' ? 'btn-success' : 'btn-outline-success'; ?>">This Week</a>
                <a href="reports.php?preset=this_month&<?php echo $filterQs; ?>" class="btn <?php echo $datePreset === 'this_month' ? 'btn-success' : 'btn-outline-success'; ?>">This Month</a>
            </div>
        </div>

        <!-- Custom Date Range -->
        <div class="col-6 col-xl-2 col-lg-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text">From</span>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom ?? ''); ?>">
            </div>
        </div>

        <div class="col-6 col-xl-2 col-lg-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text">To</span>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo ?? ''); ?>">
            </div>
        </div>

        <!-- Batch Filter Dropdown -->
        <div class="col-6 col-xl-2 col-lg-2">
            <select name="batch_id" class="form-select form-select-sm">
                <option value="">-- All Batches --</option>
                <?php foreach ($batchesList as $b): ?>
                    <option value="<?php echo $b['batch_id']; ?>" <?php echo $batchFilter == $b['batch_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['batch_code']); ?> (<?php echo ucfirst($b['status']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status Filter Dropdown -->
        <div class="col-6 col-xl-1 col-lg-1">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- Status --</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="harvested" <?php echo $statusFilter === 'harvested' ? 'selected' : ''; ?>>Harvested</option>
                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                <option value="fulfilled" <?php echo $statusFilter === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>

        <!-- Filter Submit Button -->
        <div class="col-12 col-xl-1 col-lg-12 text-end">
            <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
        </div>
    </form>
</div>

<!-- High-Level Financial KPI Bar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-success h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Total Contract Value</small>
            <h4 class="mb-0 fw-bold text-success">₱<?php echo number_format($financialReport['net_sales'], 2); ?></h4>
            <small class="text-muted">Total Active/Fulfilled</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-info h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Advance Payments</small>
            <h4 class="mb-0 fw-bold text-info">₱<?php echo number_format($financialReport['advance_payments'] ?? 0, 2); ?></h4>
            <small class="text-muted">Deposits on bookings</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-danger h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Total Expenses</small>
            <h4 class="mb-0 fw-bold text-danger">₱<?php echo number_format($financialReport['total_expenses'], 2); ?></h4>
            <small class="text-muted">Feed, Meds, Waste, Operations</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 bg-white rounded-4 shadow-sm border-start border-4 border-primary h-100 report-kpi-card">
            <small class="text-muted text-uppercase fw-bold">Net Operating Profit</small>
            <h4 class="mb-0 fw-bold <?php echo $financialReport['net_profit'] >= 0 ? 'text-primary' : 'text-danger'; ?>">
                ₱<?php echo number_format($financialReport['net_profit'], 2); ?>
            </h4>
            <small class="text-muted">Net Revenue &minus; Expenses</small>
        </div>
    </div>
</div>

<!-- Report Category Tabs (Chapter 1 Scope) -->
<ul class="nav nav-pills report-tabs bg-white p-2 rounded-4 shadow-sm mb-4 flex-wrap gap-1 no-print" id="reportTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'production' ? 'active' : ''; ?>" onclick="setTab('production')" type="button">
            <i class="fas fa-feather-pointed me-1"></i>Broiler Batches
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'mortality' ? 'active' : ''; ?>" onclick="setTab('mortality')" type="button">
            <i class="fas fa-skull me-1"></i>Mortality
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'health' ? 'active' : ''; ?>" onclick="setTab('health')" type="button">
            <i class="fas fa-heart-pulse me-1"></i>Health & Meds
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'feed' ? 'active' : ''; ?>" onclick="setTab('feed')" type="button">
            <i class="fas fa-wheat-awn me-1"></i>Feed Consumption
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'inventory' ? 'active' : ''; ?>" onclick="setTab('inventory')" type="button">
            <i class="fas fa-warehouse me-1"></i>Stock Inventory
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'reservations' ? 'active' : ''; ?>" onclick="setTab('reservations')" type="button">
            <i class="fas fa-calendar-check me-1"></i>Reservations & Pickups
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'returns' ? 'active' : ''; ?>" onclick="setTab('returns')" type="button">
            <i class="fas fa-arrow-rotate-left me-1"></i>Returns & Rejections
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'expenses' ? 'active' : ''; ?>" onclick="setTab('expenses')" type="button">
            <i class="fas fa-wallet me-1"></i>Expenses Ledger
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'financial' ? 'active' : ''; ?>" onclick="setTab('financial')" type="button">
            <i class="fas fa-chart-line me-1"></i>Financial Analytics
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'audit' ? 'active' : ''; ?>" onclick="setTab('audit')" type="button">
            <i class="fas fa-clipboard-check me-1"></i>Worker Audit Trail
        </button>
    </li>
</ul>

<div class="tab-content" id="reportTabContent">

    <?php
    $paneActive = function (string $key) use ($activeTab, $exportPdf, $wantSection): string {
        if ($exportPdf) {
            return $wantSection($key) ? 'show active' : '';
        }
        return $activeTab === $key ? 'show active' : '';
    };
    ?>

    <!-- 1. Broiler Batches Production Report -->
    <div class="tab-pane fade <?php echo $paneActive('production'); ?>" id="production-tab" role="tabpanel" data-report-section="production">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-feather-pointed me-2 text-success"></i>Broiler Batch Production & Performance</h5>
                    <span class="badge bg-light text-dark border"><?php echo count($productionReport); ?> Batches</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Batch Code</th>
                                <th>Arrival Date</th>
                                <th>Breed / House</th>
                                <th>Expected Harvest</th>
                                <th class="text-end">Stocked</th>
                                <th class="text-end">Current Count</th>
                                <th class="text-end">Mortality</th>
                                <th class="text-end">Mortality %</th>
                                <th class="text-end">Survival %</th>
                                <th class="text-end">Feed Used</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productionReport)): ?>
                                <tr><td colspan="11" class="text-center py-4 text-muted">No broiler batch production data found for the selected filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($productionReport as $p): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($p['batch_code']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($p['arrival_date'])); ?></td>
                                    <td>
                                        <div><small class="fw-semibold"><?php echo htmlspecialchars($p['breed'] ?: 'Cobb 500'); ?></small></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($p['house_pen'] ?: 'Pen 1'); ?></small>
                                    </td>
                                    <td>
                                        <?php echo !empty($p['expected_harvest_date']) ? date('M d, Y', strtotime($p['expected_harvest_date'])) : '<span class="text-muted">-</span>'; ?>
                                    </td>
                                    <td class="text-end"><?php echo number_format($p['initial_quantity']); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo number_format($p['current_quantity']); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($p['total_mortality']); ?></td>
                                    <td class="text-end <?php echo $p['mortality_rate_pct'] > 5 ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo $p['mortality_rate_pct']; ?>%
                                    </td>
                                    <td class="text-end fw-semibold"><?php echo $p['survival_rate_pct']; ?>%</td>
                                    <td class="text-end"><?php echo number_format($p['total_feed_used'], 1); ?> kg</td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $p['status'] === 'active' ? 'success' : 'secondary'; ?> rounded-pill px-3">
                                            <?php echo strtoupper($p['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Mortality Report -->
    <div class="tab-pane fade <?php echo $paneActive('mortality'); ?>" id="mortality-tab" role="tabpanel" data-report-section="mortality">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-skull me-2 text-danger"></i>Flock Mortality Log Records</h5>
                    <?php 
                        $totalLossCount = array_sum(array_column($mortalityReport, 'loss_count'));
                    ?>
                    <span class="badge bg-danger text-white rounded-pill px-3"><?php echo number_format($totalLossCount); ?> Total Loss</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Batch</th>
                                <th class="text-end">Loss Count</th>
                                <th>Reason / Observation</th>
                                <th>Logged By</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mortalityReport)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No mortality records found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mortalityReport as $m): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($m['date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['batch_code']); ?></strong></td>
                                    <td class="text-end fw-bold text-danger"><?php echo number_format($m['loss_count']); ?> bird(s)</td>
                                    <td><?php echo htmlspecialchars($m['reason'] ?: 'Routine loss / Unspecified'); ?></td>
                                    <td><small><?php echo htmlspecialchars($m['recorded_by_user'] ?? 'Staff'); ?></small></td>
                                    <td><small class="text-muted"><?php echo date('M d, Y H:i', strtotime($m['created_at'])); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Flock Health & Medication Report -->
    <div class="tab-pane fade <?php echo $paneActive('health'); ?>" id="health-tab" role="tabpanel" data-report-section="health">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-heart-pulse me-2 text-danger"></i>Flock Health & Medication Records</h5>
                    <span class="badge bg-light text-dark border"><?php echo count($healthReport); ?> Health Events</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Batch</th>
                                <th>Medicine / Treatment</th>
                                <th>Type</th>
                                <th class="text-end">Quantity Used</th>
                                <th>Doctor Consultation</th>
                                <th>Diagnosis / Observation</th>
                                <th class="text-center">Status</th>
                                <th>Next Due Date</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($healthReport)): ?>
                                <tr><td colspan="10" class="text-center py-4 text-muted">No health records found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($healthReport as $h): 
                                    $hBadge = 'success';
                                    if (($h['status'] ?? '') === 'scheduled') $hBadge = 'warning text-dark';
                                    elseif (($h['status'] ?? '') === 'in_progress') $hBadge = 'info text-dark';
                                    elseif (($h['status'] ?? '') === 'cancelled') $hBadge = 'danger';
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($h['date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($h['batch_code']); ?></strong></td>
                                    <td class="fw-semibold text-primary"><?php echo htmlspecialchars($h['medicine_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $h['type'] === 'vaccination' ? 'info text-dark' : 'primary'; ?> rounded-pill">
                                            <?php echo ucfirst($h['type'] ?? 'treatment'); ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?php echo htmlspecialchars($h['quantity_used'] ?? '-'); ?></td>
                                    <td><small><?php echo htmlspecialchars($h['doctor_consultation'] ?: '-'); ?></small></td>
                                    <td>
                                        <?php if (!empty($h['diagnosis'])): ?><strong><?php echo htmlspecialchars($h['diagnosis']); ?></strong><br><?php endif; ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($h['illness_observation'] ?: ($h['notes'] ?? 'None')); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $hBadge; ?> rounded-pill px-2"><?php echo ucfirst(str_replace('_', ' ', $h['status'] ?? 'completed')); ?></span>
                                    </td>
                                    <td><small><?php echo !empty($h['next_due_date']) ? date('M d, Y', strtotime($h['next_due_date'])) : '-'; ?></small></td>
                                    <td><small><?php echo htmlspecialchars($h['recorded_by_user'] ?? 'Staff'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Feed Consumption Report -->
    <div class="tab-pane fade <?php echo $paneActive('feed'); ?>" id="feed-tab" role="tabpanel" data-report-section="feed">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-wheat-awn me-2 text-warning"></i>Feed & Resource Consumption Report</h5>
                    <?php 
                        $totalFeedKg = array_sum(array_column($consumptionReport, 'quantity_used'));
                        $totalFeedCost = array_sum(array_column($consumptionReport, 'total_cost'));
                    ?>
                    <div class="d-flex gap-2">
                        <span class="badge bg-warning text-dark rounded-pill px-3"><?php echo number_format($totalFeedKg, 1); ?> kg Total</span>
                        <span class="badge bg-light text-dark border rounded-pill px-3">₱<?php echo number_format($totalFeedCost, 2); ?> Total Cost</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Batch</th>
                                <th>Feed Type</th>
                                <th>Stage</th>
                                <th class="text-end">Quantity Used</th>
                                <th>Unit</th>
                                <th class="text-end">Cost Snapshot</th>
                                <th class="text-end">Total Cost</th>
                                <th>Recorded By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($consumptionReport)): ?>
                                <tr><td colspan="10" class="text-center py-4 text-muted">No consumption records found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($consumptionReport as $c): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($c['date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($c['batch_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($c['feed_type']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($c['feed_stage'] ?: 'Standard'); ?></span></td>
                                    <td class="text-end fw-bold text-success"><?php echo number_format($c['quantity_used'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($c['unit']); ?></td>
                                    <td class="text-end">₱<?php echo number_format($c['unit_cost_snapshot'], 2); ?></td>
                                    <td class="text-end fw-bold">₱<?php echo number_format($c['total_cost'], 2); ?></td>
                                    <td><small><?php echo htmlspecialchars($c['recorded_by_user'] ?? 'Staff'); ?></small></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($c['notes'] ?? 'None'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Stock Inventory Report -->
    <div class="tab-pane fade <?php echo $paneActive('inventory'); ?>" id="inventory-tab" role="tabpanel" data-report-section="inventory">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-warehouse me-2 text-primary"></i>Stock Inventory & Resource Levels</h5>
                    <?php
                        $totalInvVal = array_sum(array_column($inventoryReport['items'], 'total_value'));
                    ?>
                    <span class="badge bg-primary text-white rounded-pill px-3">Valuation: ₱<?php echo number_format($totalInvVal, 2); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th class="text-end">Quantity on Hand</th>
                                <th>Unit</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Valuation</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventoryReport['items'])): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No inventory items found for the selected filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($inventoryReport['items'] as $item): 
                                    $badge = 'success';
                                    if ($item['stock_status'] === 'Out of Stock') $badge = 'danger';
                                    elseif ($item['stock_status'] === 'Low Stock') $badge = 'warning text-dark';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo ucfirst($item['category']); ?></span></td>
                                    <td class="text-end fw-bold"><?php echo number_format($item['quantity'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td class="text-end">₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                    <td class="text-end fw-bold text-success">₱<?php echo number_format($item['total_value'], 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $badge; ?> rounded-pill px-3"><?php echo $item['stock_status']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Broiler Reservations & Advance Bookings Report -->
    <div class="tab-pane fade <?php echo $paneActive('reservations'); ?>" id="reservations-tab" role="tabpanel" data-report-section="reservations">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-calendar-check me-2 text-success"></i>Broiler Reservations, Pickups, Payments & Balances</h5>
                    <?php 
                        $totalReservedBirds = array_sum(array_column($reservationsReport, 'reserved_quantity'));
                        $totalAgreedAmt = array_sum(array_column($reservationsReport, 'total_amount'));
                        $totalAdvancePaid = array_sum(array_column($reservationsReport, 'advance_payment'));
                        $totalBalanceDue = array_sum(array_column($reservationsReport, 'remaining_balance'));
                    ?>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success text-white rounded-pill px-3"><?php echo number_format($totalReservedBirds); ?> Birds</span>
                        <span class="badge bg-light text-dark border rounded-pill px-3">Total: ₱<?php echo number_format($totalAgreedAmt, 2); ?></span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3"><i class="fas fa-hand-holding-dollar me-1"></i>Advance Paid: ₱<?php echo number_format($totalAdvancePaid, 2); ?></span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3"><i class="fas fa-receipt me-1"></i>Balance Due: ₱<?php echo number_format($totalBalanceDue, 2); ?></span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Reservation #</th>
                                <th>Customer</th>
                                <th>Batch</th>
                                <th class="text-end">Reserved Birds</th>
                                <th>Pickup Date</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Advance Paid</th>
                                <th class="text-end">Balance Due</th>
                                <th class="text-end">Accepted</th>
                                <th class="text-end">Rejected</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservationsReport)): ?>
                                <tr><td colspan="11" class="text-center py-4 text-muted">No reservations found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reservationsReport as $res): 
                                    $resBadge = 'secondary';
                                    if ($res['status'] === 'active') $resBadge = 'primary';
                                    elseif ($res['status'] === 'fulfilled') $resBadge = 'success';
                                    elseif ($res['status'] === 'cancelled') $resBadge = 'danger';
                                    elseif ($res['status'] === 'expired') $resBadge = 'warning text-dark';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($res['reservation_number']); ?></strong></td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($res['customer_name']); ?></strong></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($res['customer_phone'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($res['batch_code'] ?? 'All / Unassigned'); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($res['reserved_quantity']); ?></td>
                                    <td><strong><?php echo date('M d, Y', strtotime($res['pickup_date'])); ?></strong></td>
                                    <td class="text-end fw-bold">₱<?php echo number_format($res['total_amount'], 2); ?></td>
                                    <td class="text-end text-success fw-semibold">₱<?php echo number_format($res['advance_payment'], 2); ?></td>
                                    <td class="text-end text-danger fw-bold">₱<?php echo number_format($res['remaining_balance'], 2); ?></td>
                                    <td class="text-end text-success fw-semibold"><?php echo number_format($res['accepted_quantity']); ?></td>
                                    <td class="text-end text-danger fw-semibold"><?php echo number_format($res['rejected_quantity']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $resBadge; ?> rounded-pill px-3"><?php echo strtoupper($res['status']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Accepted, Returned & Rejected Chickens Report -->
    <div class="tab-pane fade <?php echo $paneActive('returns'); ?>" id="returns-tab" role="tabpanel" data-report-section="returns">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-arrow-rotate-left me-2 text-danger"></i>Accepted, Returned & Rejected Chickens Disposition</h5>
                    <?php 
                        $totalReturnedQty = !empty($returnsReport) ? (int)array_sum(array_column($returnsReport, 'total_returned_qty')) : 0;
                    ?>
                    <span class="badge bg-danger text-white rounded-pill px-3"><?php echo number_format($totalReturnedQty); ?> Total Returned/Rejected</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Return #</th>
                                <th>Order Ref</th>
                                <th>Customer Name</th>
                                <th>Return / Disposition Date</th>
                                <th class="text-end">Returned / Rejected Qty</th>
                                <th class="text-center">Condition Status</th>
                                <th>Reason / Notes</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($returnsReport)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No return or rejection records found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($returnsReport as $ret): 
                                    $condBadge = 'success';
                                    if ($ret['condition_status'] === 'damaged') $condBadge = 'danger';
                                    elseif ($ret['condition_status'] === 'expired') $condBadge = 'warning text-dark';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ret['return_number']); ?></strong></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ret['order_number']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($ret['customer_name']); ?></strong></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($ret['return_date'])); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo number_format($ret['total_returned_qty']); ?> bird(s)</td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $condBadge; ?> rounded-pill px-3"><?php echo strtoupper($ret['condition_status']); ?></span>
                                    </td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($ret['reason'] ?: 'None stated'); ?></strong></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($ret['notes'] ?? ''); ?></small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($ret['returned_by_user'] ?? 'Staff'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Detailed Operational Expenses Ledger -->
    <div class="tab-pane fade <?php echo $paneActive('expenses'); ?>" id="expenses-tab" role="tabpanel" data-report-section="expenses">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-wallet me-2 text-danger"></i>Operational Expenses Ledger</h5>
                    <?php 
                        $totalExp = !empty($expensesReport) ? (float)array_sum(array_column($expensesReport, 'amount')) : 0.0;
                    ?>
                    <span class="badge bg-danger text-white rounded-pill px-3">Total: ₱<?php echo number_format($totalExp, 2); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Related Flock / Item</th>
                                <th class="text-end">Amount</th>
                                <th>Source</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expensesReport)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No expense records found for the selected period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($expensesReport as $exp): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($exp['date'])); ?></td>
                                    <td><span class="badge bg-secondary text-uppercase px-2 py-1"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                                    <td><?php echo htmlspecialchars($exp['description'] ?: '-'); ?></td>
                                    <td>
                                        <?php if (!empty($exp['batch_code'])): ?>
                                            <span class="badge bg-light text-dark border"><i class="fas fa-feather-pointed me-1"></i><?php echo htmlspecialchars($exp['batch_code']); ?></span>
                                        <?php elseif (!empty($exp['inventory_item_name'])): ?>
                                            <span class="badge bg-light text-dark border"><i class="fas fa-box me-1"></i><?php echo htmlspecialchars($exp['inventory_item_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-danger">₱<?php echo number_format($exp['amount'], 2); ?></td>
                                    <td><small class="badge bg-light text-secondary border"><?php echo htmlspecialchars($exp['source_type'] ?? 'manual'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($exp['recorded_by_user'] ?? 'Staff'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 9. Financial Analytics Report -->
    <div class="tab-pane fade <?php echo $paneActive('financial'); ?>" id="financial-tab" role="tabpanel" data-report-section="financial">
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-wallet me-2 text-danger"></i>Expenses by Category</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Expense Category</th>
                                        <th class="text-end">Total (₱)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($financialReport['expenses_by_category'])): ?>
                                        <tr><td colspan="2" class="text-center py-3 text-muted">No expense records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($financialReport['expenses_by_category'] as $ec): ?>
                                        <tr>
                                            <td><strong><?php echo ucfirst($ec['category']); ?></strong></td>
                                            <td class="text-end fw-bold text-danger">₱<?php echo number_format($ec['total'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light">
                                            <td><strong>Total Operating Expenses</strong></td>
                                            <td class="text-end fw-bold text-danger">₱<?php echo number_format($financialReport['total_expenses'], 2); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-coins me-2 text-success"></i>Financial Summary</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Total Contract Value:</span>
                                <strong class="text-success fs-5">₱<?php echo number_format($financialReport['net_sales'], 2); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Advance Payments Collected:</span>
                                <strong class="text-info fs-5">₱<?php echo number_format($financialReport['advance_payments'] ?? 0, 2); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Completed / Fulfilled Value:</span>
                                <strong class="text-primary fs-5">₱<?php echo number_format($financialReport['completed_sales'], 2); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span>Total Operating Costs:</span>
                                <strong class="text-danger fs-5">&minus;₱<?php echo number_format($financialReport['total_expenses'], 2); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-light rounded-3 mt-2">
                                <span class="fw-bold">Net Profit / (Loss):</span>
                                <strong class="fs-4 <?php echo $financialReport['net_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    ₱<?php echo number_format($financialReport['net_profit'], 2); ?>
                                </strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 10. Worker Audit Trail Tab -->
    <div class="tab-pane fade <?php echo $paneActive('audit'); ?>" id="audit-tab" role="tabpanel" data-report-section="audit">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-clipboard-check me-2 text-primary"></i>Farm Operations & Worker Audit Trail</h5>
                    <span class="badge bg-light text-dark border"><?php echo count($workerReport); ?> Actions Logged</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Worker</th>
                                <th>Batch</th>
                                <th>Task / Activity Type</th>
                                <th>Item / Resource</th>
                                <th class="text-end">Quantity</th>
                                <th>Remarks / Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($workerReport)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No worker activity logs recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($workerReport as $act): ?>
                                <tr>
                                    <td>
                                        <div><small class="fw-bold"><?php echo date('M d, Y', strtotime($act['date'])); ?></small></div>
                                        <small class="text-muted"><?php echo !empty($act['created_at']) ? date('h:i A', strtotime($act['created_at'])) : ''; ?></small>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($act['worker_name'] ?? 'Staff'); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($act['batch_code']); ?></strong></td>
                                    <td><span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($act['activity_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($act['item_name'] ?: '-'); ?></td>
                                    <td class="text-end fw-semibold"><?php echo $act['quantity'] > 0 ? number_format($act['quantity'], 2) : '-'; ?></td>
                                    <td><small><?php echo htmlspecialchars($act['description'] ?: 'None'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Print / PDF Report Selection Modal -->
<div class="modal fade no-print" id="printReportModal" tabindex="-1" aria-labelledby="printReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold" id="printReportModalLabel">
                        <i class="fas fa-file-pdf text-danger me-2"></i>Export Report as PDF
                    </h5>
                    <small class="text-muted">Select which report section(s) to include. Current filters (date / batch / status) will apply.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="toggleAllPdfSections(true)">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="toggleAllPdfSections(false)">Clear</button>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="selectCurrentPdfSectionOnly()">Current Tab Only</button>
                </div>
                <div class="row g-2" id="pdfSectionChoices">
                    <?php foreach ($tabTitles as $secKey => $secLabel): ?>
                    <div class="col-12 col-md-6">
                        <label class="pdf-section-check d-flex align-items-start gap-2 h-100 mb-0">
                            <input class="form-check-input mt-1 pdf-section-input" type="checkbox" value="<?php echo e($secKey); ?>" <?php echo ($activeTab === $secKey || $activeTab === 'all') ? 'checked' : ''; ?>>
                            <span>
                                <span class="d-block fw-semibold"><i class="fas <?php echo e($pdfSectionIcons[$secKey] ?? 'fa-file'); ?> me-1 text-success"></i><?php echo e($secLabel); ?></span>
                                <small class="text-muted"><?php echo e($secKey); ?></small>
                            </span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-info border-0 rounded-3 small mt-3 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    PDF uses your current filter settings
                    (<?php echo $dateFrom ? e(date('M d, Y', strtotime($dateFrom))) : 'All time start'; ?>
                    &ndash;
                    <?php echo $dateTo ? e(date('M d, Y', strtotime($dateTo))) : 'Present'; ?>
                    <?php if ($batchFilter): ?>
                        · Batch: <?php echo e($pdfBatchLabel); ?>
                    <?php endif; ?>
                    <?php if ($statusFilter): ?> · Status: <?php echo e(ucfirst($statusFilter)); ?><?php endif; ?>).
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success rounded-pill px-4 fw-bold" id="btnGeneratePdf" onclick="generateSelectedPdf()">
                    <i class="fas fa-file-pdf me-1"></i>Generate PDF
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$pdfPeriodFrom = $dateFrom ? date('F d, Y', strtotime($dateFrom)) : 'All records';
$pdfPeriodTo = $dateTo ? date('F d, Y', strtotime($dateTo)) : 'Present';
$pdfReportYear = $dateTo ? date('Y', strtotime($dateTo)) : date('Y');
$pdfPreparedBy = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'System Admin');
?>
<!-- Hidden printable / PDF document shell (DengGuard-style letterhead) -->
<div id="pdfExportDoc" aria-hidden="true">
    <div class="pdf-letterhead" id="pdfLetterhead">
        <table class="pdf-letterhead-table" role="presentation" style="width:100%;border-collapse:collapse;table-layout:fixed;">
            <tr>
                <td class="pdf-logo-cell" style="width:100px;text-align:left;vertical-align:middle;padding:0;border:0;">
                    <img class="pdf-logo pdf-logo-left" src="uploads/logos/alquizar_chicken_logo.png" alt="Alquizar Poultry Farm Official Logo" width="90" height="90">
                </td>
                <td style="vertical-align:middle;padding:0 8px 0 0;border:0;">
                    <div class="pdf-agency-block" style="text-align:center;line-height:1.3;width:100%;">
                        <p class="pdf-agency-line" style="margin:0;font-size:11px;color:#334155;font-weight:500;text-align:center;">Republic of the Philippines</p>
                        <p class="pdf-agency-line" style="margin:0;font-size:11px;color:#334155;font-weight:500;text-align:center;">Province of South Cotabato</p>
                        <p class="pdf-agency-name" style="margin:3px 0 0;font-size:16px;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:0.02em;text-align:center;">Alquizar Poultry Farm</p>
                        <p class="pdf-agency-office" style="margin:3px 0 0;font-size:13px;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:0.03em;text-align:center;">Poultry Farm Management System</p>
                        <p class="pdf-agency-line" style="margin:3px 0 0;font-size:11px;color:#334155;font-weight:500;text-align:center;">Tampakan, South Cotabato</p>
                    </div>
                </td>
                <td style="width:100px;border:0;padding:0;"></td>
            </tr>
        </table>
        <hr class="pdf-rule" style="border:0;border-top:3px solid #0f766e;margin:8px 0 10px;width:100%;">
        <h1 class="pdf-doc-title" id="pdfDocSubtitle" style="margin:0;text-align:center;font-size:18px;font-weight:800;color:#0f766e;text-transform:uppercase;letter-spacing:0.04em;line-height:1.25;">Farm Operations Surveillance Report</h1>
        <div class="pdf-doc-meta" id="pdfDocMetaLine" style="margin:6px 0 0;text-align:center;font-size:10px;color:#64748b;line-height:1.5;">
            <strong style="color:#475569;">Reporting Year:</strong> <?php echo e($pdfReportYear); ?>
            &nbsp;|&nbsp;
            <strong style="color:#475569;">Period:</strong> <?php echo e($pdfPeriodFrom); ?> to <?php echo e($pdfPeriodTo); ?>
            &nbsp;|&nbsp;
            <strong style="color:#475569;">Report Date:</strong> <?php echo date('F d, Y'); ?>
            <?php if ($batchFilter): ?>
            &nbsp;|&nbsp; <strong style="color:#475569;">Batch:</strong> <?php echo e($pdfBatchLabel ?? ('#' . $batchFilter)); ?>
            <?php endif; ?>
            <?php if ($statusFilter): ?>
            &nbsp;|&nbsp; <strong style="color:#475569;">Status:</strong> <?php echo e(ucfirst($statusFilter)); ?>
            <?php endif; ?>
            &nbsp;|&nbsp; <strong style="color:#475569;">Prepared By:</strong> <?php echo e($pdfPreparedBy); ?>
        </div>
    </div>

    <div class="pdf-section" id="pdfKeyStats">
        <h3 class="pdf-section-title" style="margin:0 0 10px;padding:0 0 6px;font-size:13px;font-weight:800;color:#0f766e;border-bottom:2px solid #14b8a6;background:transparent;">1. Key Farm Statistics</h3>
        <table class="pdf-stat-table" role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:separate;border-spacing:8px;table-layout:fixed;">
            <tr>
                <td style="width:25%;padding:0;border:0;background:transparent;">
                    <div class="pdf-stat-box" style="border:2px solid #14b8a6;background:#f8fafc;padding:14px 8px;text-align:center;">
                        <div class="pdf-stat-value" style="font-size:15px;font-weight:800;color:#0f172a;line-height:1.2;margin-bottom:4px;">₱<?php echo number_format($financialReport['net_sales'], 2); ?></div>
                        <div class="pdf-stat-label" style="font-size:8.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#64748b;">Total Contract Value</div>
                    </div>
                </td>
                <td style="width:25%;padding:0;border:0;background:transparent;">
                    <div class="pdf-stat-box" style="border:2px solid #14b8a6;background:#f8fafc;padding:14px 8px;text-align:center;">
                        <div class="pdf-stat-value" style="font-size:15px;font-weight:800;color:#0f172a;line-height:1.2;margin-bottom:4px;">₱<?php echo number_format($financialReport['advance_payments'] ?? 0, 2); ?></div>
                        <div class="pdf-stat-label" style="font-size:8.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#64748b;">Advance Payments</div>
                    </div>
                </td>
                <td style="width:25%;padding:0;border:0;background:transparent;">
                    <div class="pdf-stat-box" style="border:2px solid #14b8a6;background:#f8fafc;padding:14px 8px;text-align:center;">
                        <div class="pdf-stat-value" style="font-size:15px;font-weight:800;color:#0f172a;line-height:1.2;margin-bottom:4px;">₱<?php echo number_format($financialReport['total_expenses'], 2); ?></div>
                        <div class="pdf-stat-label" style="font-size:8.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#64748b;">Total Expenses</div>
                    </div>
                </td>
                <td style="width:25%;padding:0;border:0;background:transparent;">
                    <div class="pdf-stat-box" style="border:2px solid #14b8a6;background:#f8fafc;padding:14px 8px;text-align:center;">
                        <div class="pdf-stat-value" style="font-size:15px;font-weight:800;color:#0f172a;line-height:1.2;margin-bottom:4px;">₱<?php echo number_format($financialReport['net_profit'], 2); ?></div>
                        <div class="pdf-stat-label" style="font-size:8.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#64748b;">Net Operating Profit</div>
                    </div>
                </td>
            </tr>
        </table>
        <p class="pdf-note" style="margin:6px 0 0;font-size:9px;font-style:italic;color:#64748b;">Values are limited to the selected reporting period and active filters.</p>
    </div>

    <div id="pdfExportBody"></div>
    <div class="pdf-footer">
        <table class="pdf-footer-table" role="presentation">
            <tr>
                <td style="text-align:left;">Confidential &mdash; For internal farm use only</td>
                <td style="text-align:center;">Alquizar Poultry Farm &middot; Poultry Farm Management System</td>
                <td style="text-align:right;">Generated <?php echo date('Y-m-d H:i'); ?></td>
            </tr>
        </table>
    </div>
</div>

<div id="pdfGeneratingOverlay" class="no-print" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(6,20,15,0.72);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
    <div class="text-center text-white px-4">
        <div class="spinner-border text-success mb-3" role="status"></div>
        <div class="fw-bold">Generating PDF…</div>
        <small class="text-white-50">Please wait while the selected report is prepared.</small>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const REPORT_CURRENT_TAB = <?php echo json_encode($activeTab); ?>;
const REPORT_EXPORT_PDF = <?php echo $exportPdf ? 'true' : 'false'; ?>;
const REPORT_PDF_SECTIONS = <?php echo json_encode($pdfSections); ?>;
const REPORT_TAB_TITLES = <?php echo json_encode($tabTitles); ?>;

function setTab(tabName) {
    // Full reload so the selected report tab actually fetches its dataset.
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabName);
    url.searchParams.delete('export');
    url.searchParams.delete('sections');

    const form = document.querySelector('.filter-card form');
    if (form) {
        const fd = new FormData(form);
        ['date_from', 'date_to', 'batch_id', 'status'].forEach(function (key) {
            const val = (fd.get(key) || '').toString().trim();
            if (val) url.searchParams.set(key, val);
            else url.searchParams.delete(key);
        });
    }

    const preset = new URLSearchParams(window.location.search).get('preset');
    if (preset) url.searchParams.set('preset', preset);

    window.location.href = url.toString();
}

function toggleAllPdfSections(checked) {
    document.querySelectorAll('.pdf-section-input').forEach(function (el) {
        el.checked = !!checked;
    });
}

function selectCurrentPdfSectionOnly() {
    document.querySelectorAll('.pdf-section-input').forEach(function (el) {
        el.checked = el.value === REPORT_CURRENT_TAB;
    });
}

function getSelectedPdfSections() {
    return Array.from(document.querySelectorAll('.pdf-section-input:checked')).map(function (el) {
        return el.value;
    });
}

function buildReportsFilterUrl(extraParams) {
    const url = new URL(window.location.href);
    const form = document.querySelector('.filter-card form');
    if (form) {
        const fd = new FormData(form);
        ['date_from', 'date_to', 'batch_id', 'status', 'tab'].forEach(function (key) {
            const val = (fd.get(key) || '').toString().trim();
            if (val) url.searchParams.set(key, val);
            else if (key !== 'tab') url.searchParams.delete(key);
        });
    }
    const preset = new URLSearchParams(window.location.search).get('preset');
    if (preset) url.searchParams.set('preset', preset);
    Object.keys(extraParams || {}).forEach(function (k) {
        if (extraParams[k] === null || extraParams[k] === undefined || extraParams[k] === '') {
            url.searchParams.delete(k);
        } else {
            url.searchParams.set(k, extraParams[k]);
        }
    });
    return url;
}

function generateSelectedPdf() {
    const sections = getSelectedPdfSections();
    if (!sections.length) {
        alert('Please select at least one report section to export.');
        return;
    }

    const btn = document.getElementById('btnGeneratePdf');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing…';
    }

    const url = buildReportsFilterUrl({
        tab: 'all',
        export: 'pdf',
        sections: sections.join(',')
    });
    window.location.href = url.toString();
}

function setImportant(el, prop, value) {
    if (!el || !el.style) return;
    el.style.setProperty(prop, value, 'important');
}

function stripPdfNoise(root) {
    root.querySelectorAll('.btn, .dropdown, .no-print, script, style, .nav, .spinner-border').forEach(function (n) { n.remove(); });
    root.querySelectorAll('.card, .card-body, .rounded-4, .shadow-sm, .border-0, .bg-white, .bg-light, .table-responsive').forEach(function (el) {
        setImportant(el, 'background', '#ffffff');
        setImportant(el, 'background-color', '#ffffff');
        setImportant(el, 'box-shadow', 'none');
        setImportant(el, 'border', 'none');
        setImportant(el, 'border-radius', '0');
        setImportant(el, 'color', '#1f2937');
        setImportant(el, 'padding', '0');
        setImportant(el, 'margin', '0');
        setImportant(el, 'overflow', 'visible');
    });
    root.querySelectorAll('.badge').forEach(function (el) {
        setImportant(el, 'background', 'transparent');
        setImportant(el, 'background-color', 'transparent');
        setImportant(el, 'color', '#0f172a');
        setImportant(el, 'border', '0');
        setImportant(el, 'border-radius', '0');
        setImportant(el, 'box-shadow', 'none');
        setImportant(el, 'padding', '0');
        setImportant(el, 'font-weight', '700');
    });
    root.querySelectorAll('i.fas, i.far, i.fab').forEach(function (icon) {
        icon.remove();
    });
}

/** DengGuard-style polish — bake colors/borders as inline !important for html2canvas */
function polishPdfCapture(root) {
    setImportant(root, 'background', '#ffffff');
    setImportant(root, 'color', '#1f2937');
    setImportant(root, 'font-family', 'Arial, Helvetica, sans-serif');

    root.querySelectorAll('.pdf-doc-title, .pdf-section-title').forEach(function (el) {
        setImportant(el, 'color', '#0f766e');
        setImportant(el, 'font-weight', '800');
        setImportant(el, 'background', 'transparent');
        setImportant(el, 'background-color', 'transparent');
    });
    root.querySelectorAll('.pdf-section-title').forEach(function (el) {
        setImportant(el, 'border-bottom', '2px solid #14b8a6');
        setImportant(el, 'padding-bottom', '6px');
        setImportant(el, 'margin', '0 0 10px 0');
        setImportant(el, 'font-size', '13px');
        setImportant(el, 'display', 'block');
        setImportant(el, 'width', '100%');
    });
    root.querySelectorAll('.pdf-doc-title').forEach(function (el) {
        setImportant(el, 'text-align', 'center');
        setImportant(el, 'font-size', '18px');
        setImportant(el, 'text-transform', 'uppercase');
        setImportant(el, 'letter-spacing', '0.04em');
        setImportant(el, 'margin', '0');
    });
    root.querySelectorAll('.pdf-agency-name, .pdf-agency-office').forEach(function (el) {
        setImportant(el, 'color', '#0f172a');
        setImportant(el, 'font-weight', '800');
        setImportant(el, 'text-transform', 'uppercase');
    });
    root.querySelectorAll('.pdf-agency-line, .pdf-doc-meta, .pdf-note').forEach(function (el) {
        setImportant(el, 'color', '#64748b');
    });
    root.querySelectorAll('.pdf-rule').forEach(function (el) {
        setImportant(el, 'border', '0');
        setImportant(el, 'border-top', '3px solid #0f766e');
        setImportant(el, 'margin', '8px 0 10px');
        setImportant(el, 'width', '100%');
    });
    root.querySelectorAll('.pdf-stat-box').forEach(function (el) {
        setImportant(el, 'border', '2px solid #14b8a6');
        setImportant(el, 'background', '#f8fafc');
        setImportant(el, 'background-color', '#f8fafc');
        setImportant(el, 'padding', '14px 8px');
        setImportant(el, 'text-align', 'center');
        setImportant(el, 'box-sizing', 'border-box');
    });
    root.querySelectorAll('.pdf-stat-value').forEach(function (el) {
        setImportant(el, 'color', '#0f172a');
        setImportant(el, 'font-weight', '800');
        setImportant(el, 'font-size', '15px');
    });
    root.querySelectorAll('.pdf-stat-label').forEach(function (el) {
        setImportant(el, 'color', '#64748b');
        setImportant(el, 'font-weight', '700');
        setImportant(el, 'font-size', '8.5px');
        setImportant(el, 'text-transform', 'uppercase');
    });
    root.querySelectorAll('.pdf-logo').forEach(function (el) {
        setImportant(el, 'width', '90px');
        setImportant(el, 'height', '90px');
        setImportant(el, 'border-radius', '50%');
        setImportant(el, 'border', '3px solid #0f766e');
        setImportant(el, 'object-fit', 'cover');
        setImportant(el, 'background', '#000');
        setImportant(el, 'display', 'inline-block');
    });

    // Data tables — solid teal header, white text, zebra body (DengGuard)
    root.querySelectorAll('table').forEach(function (tbl) {
        if (tbl.classList.contains('pdf-stat-table') || tbl.classList.contains('pdf-letterhead-table') || tbl.classList.contains('pdf-footer-table')) {
            return;
        }
        tbl.classList.add('table');
        setImportant(tbl, 'width', '100%');
        setImportant(tbl, 'border-collapse', 'collapse');
        setImportant(tbl, 'background', '#ffffff');
        setImportant(tbl, 'color', '#1f2937');
        setImportant(tbl, 'font-size', '8px');
        setImportant(tbl, 'margin', '0');

        Array.from(tbl.querySelectorAll('thead th')).forEach(function (th) {
            setImportant(th, 'background', '#0f766e');
            setImportant(th, 'background-color', '#0f766e');
            setImportant(th, 'color', '#ffffff');
            setImportant(th, 'border', '1px solid #0f766e');
            setImportant(th, 'padding', '6px 4px');
            setImportant(th, 'font-weight', '700');
            setImportant(th, 'text-transform', 'uppercase');
            setImportant(th, 'font-size', '7.5px');
            setImportant(th, 'letter-spacing', '0.02em');
            setImportant(th, 'text-shadow', 'none');
            setImportant(th, 'vertical-align', 'middle');
        });

        Array.from(tbl.querySelectorAll('tbody tr')).forEach(function (tr, idx) {
            Array.from(tr.children).forEach(function (td) {
                setImportant(td, 'background', idx % 2 === 0 ? '#ffffff' : '#f1f5f9');
                setImportant(td, 'background-color', idx % 2 === 0 ? '#ffffff' : '#f1f5f9');
                setImportant(td, 'color', '#1f2937');
                setImportant(td, 'border', '1px solid #cbd5e1');
                setImportant(td, 'padding', '5px 4px');
                setImportant(td, 'vertical-align', 'middle');
                setImportant(td, 'text-shadow', 'none');
            });
        });
    });

    root.querySelectorAll('.text-muted, .text-success, .text-danger, .text-primary, .text-info, .text-white, small, span, p, div').forEach(function (el) {
        if (el.closest('thead')) return;
        if (el.classList.contains('pdf-doc-title') || el.classList.contains('pdf-section-title')) return;
        if (el.classList.contains('pdf-stat-value') || el.classList.contains('pdf-stat-label')) return;
        if (el.classList.contains('pdf-agency-name') || el.classList.contains('pdf-agency-office')) return;
        if (el.classList.contains('pdf-stat-box')) return;
        setImportant(el, 'color', '#1f2937');
        setImportant(el, 'text-shadow', 'none');
    });
}

async function runPdfExportFromPage() {
    const overlay = document.getElementById('pdfGeneratingOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
    }

    const sections = (REPORT_PDF_SECTIONS && REPORT_PDF_SECTIONS.length)
        ? REPORT_PDF_SECTIONS
        : Object.keys(REPORT_TAB_TITLES);

    const sourceDoc = document.getElementById('pdfExportDoc');
    const body = document.getElementById('pdfExportBody');
    if (!sourceDoc || !body) {
        alert('PDF template missing. Please refresh and try again.');
        return;
    }

    const subtitle = document.getElementById('pdfDocSubtitle');
    if (subtitle) {
        subtitle.textContent = sections.length === 1
            ? ((REPORT_TAB_TITLES[sections[0]] || 'Farm Report').toUpperCase() + ' REPORT')
            : 'FARM OPERATIONS SURVEILLANCE REPORT';
    }

    // Resolve logo URLs while source is still in the live DOM
    sourceDoc.querySelectorAll('.pdf-logo').forEach(function (logo) {
        const abs = new URL(logo.getAttribute('src'), window.location.href).href;
        logo.setAttribute('src', abs);
        logo.style.display = 'inline-block';
        logo.style.width = '90px';
        logo.style.height = '90px';
        logo.style.borderRadius = '50%';
        logo.style.objectFit = 'cover';
        logo.style.background = '#000';
    });

    // Clone report panes BEFORE hiding the page chrome (display:none can confuse layout)
    body.innerHTML = '';
    let sectionNo = 1;
    sections.forEach(function (sec) {
        const pane = document.querySelector('[data-report-section="' + sec + '"]');
        if (!pane) return;
        sectionNo += 1;
        const wrap = document.createElement('div');
        wrap.className = 'pdf-section';
        const title = document.createElement('h3');
        title.className = 'pdf-section-title';
        title.textContent = sectionNo + '. ' + (REPORT_TAB_TITLES[sec] || sec);
        title.setAttribute('style', 'margin:0 0 10px;padding:0 0 6px;font-size:13px;font-weight:800;color:#0f766e;border-bottom:2px solid #14b8a6;background:transparent;display:block;width:100%;');
        wrap.appendChild(title);

        const paneClone = pane.cloneNode(true);
        paneClone.classList.remove('fade', 'tab-pane', 'show', 'active');
        paneClone.style.display = 'block';
        paneClone.style.opacity = '1';
        paneClone.style.visibility = 'visible';
        const firstHeading = paneClone.querySelector('h5');
        if (firstHeading) {
            const headingRow = firstHeading.closest('.d-flex') || firstHeading;
            headingRow.remove();
        }
        stripPdfNoise(paneClone);
        polishPdfCapture(paneClone);
        wrap.appendChild(paneClone);
        const note = document.createElement('p');
        note.className = 'pdf-note';
        note.textContent = 'Sorted by report defaults. Values are limited to the selected date range.';
        wrap.appendChild(note);
        body.appendChild(wrap);
    });

    const fileStamp = new Date().toISOString().slice(0, 10);
    const fileName = 'Alquizar_Report_' + sections.join('-') + '_' + fileStamp + '.pdf';
    let host = null;

    try {
        if (typeof html2pdf === 'undefined') {
            throw new Error('PDF library failed to load.');
        }

        // Capture clone at top-left (html2canvas blanks on fixed/off-screen nodes)
        window.scrollTo(0, 0);
        host = document.createElement('div');
        host.id = 'pdfCaptureHost';
        host.style.cssText = 'position:absolute;left:0;top:0;width:700px;margin:0;padding:0;background:#ffffff;z-index:2147483646;';

        const capture = sourceDoc.cloneNode(true);
        capture.removeAttribute('id');
        capture.removeAttribute('aria-hidden');
        capture.style.cssText = [
            'display:block',
            'position:relative',
            'left:0',
            'top:0',
            'width:700px',
            'max-width:700px',
            'margin:0',
            'padding:12px 14px 16px',
            'background:#ffffff',
            'color:#1f2937',
            'visibility:visible',
            'opacity:1',
            'border:1px solid #cbd5e1',
            'box-sizing:border-box',
            'font-family:Arial,Helvetica,sans-serif'
        ].join(';');

        polishPdfCapture(capture);
        host.appendChild(capture);
        document.body.appendChild(host);

        // Hide overlay so it cannot cover the capture node
        if (overlay) overlay.style.display = 'none';

        const logoWait = Array.from(host.querySelectorAll('img')).map(function (img) {
            if (img.complete && img.naturalWidth > 0) return Promise.resolve();
            return new Promise(function (resolve) {
                img.onload = resolve;
                img.onerror = resolve;
                setTimeout(resolve, 2000);
            });
        });
        await Promise.all(logoWait);
        await new Promise(function (r) { setTimeout(r, 250); });

        if (!capture.offsetHeight || capture.offsetHeight < 40) {
            throw new Error('PDF content height is empty (' + capture.offsetHeight + 'px).');
        }

        await html2pdf().set({
            margin: [10, 10, 10, 10],
            filename: fileName,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                logging: false,
                imageTimeout: 3000,
                scrollX: 0,
                scrollY: -window.scrollY,
                onclone: function (clonedDoc) {
                    const node = clonedDoc.getElementById('pdfCaptureHost');
                    if (node) polishPdfCapture(node);
                }
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'], avoid: ['.pdf-letterhead', '.pdf-section-title', '.pdf-stat-box', '.pdf-stat-table'] }
        }).from(capture).save();
    } catch (err) {
        console.error(err);
        alert('Could not generate PDF: ' + (err && err.message ? err.message : 'unknown error'));
    } finally {
        if (host && host.parentNode) host.parentNode.removeChild(host);
        document.body.classList.remove('pdf-exporting');
        const back = buildReportsFilterUrl({
            tab: sections[0] || 'production',
            export: null,
            sections: null
        });
        window.location.replace(back.toString());
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Keep modal outside overflow/transform ancestors so Bootstrap can center it
    const printModal = document.getElementById('printReportModal');
    if (printModal && printModal.parentElement !== document.body) {
        document.body.appendChild(printModal);
    }
    const pdfDoc = document.getElementById('pdfExportDoc');
    if (pdfDoc && pdfDoc.parentElement !== document.body) {
        document.body.appendChild(pdfDoc);
    }

    // Pre-check current tab in modal
    document.querySelectorAll('.pdf-section-input').forEach(function (el) {
        if (REPORT_CURRENT_TAB === 'all') {
            el.checked = true;
        } else {
            el.checked = el.value === REPORT_CURRENT_TAB;
        }
    });

    if (REPORT_EXPORT_PDF) {
        // Slight delay so fonts/layout settle before capture
        setTimeout(runPdfExportFromPage, 350);
    }
});
</script>

<?php include('footer.php'); ?>
