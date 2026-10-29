<?php include('database.php'); ?>
<?php include('db_check.php'); ?>

<?php
// Handle Mortality Recording
if(isset($_POST['save_mortality'])) {
    $batch_id = (int)$_POST['batch_id'];
    $qty = abs((int)$_POST['quantity']) * -1; // Automatically convert to negative for loss
    $reason = trim($_POST['reason']);
    $date = $_POST['date'];
    $financial_value = (float)($_POST['financial_value'] ?? 0);
    $userId = $_SESSION['user_id'] ?? null;

    if (record_mortality($conn, $batch_id, $qty, $reason, $date, $financial_value, $userId)) {
        $_SESSION['flash_msg'] = "Mortality record saved successfully!";
        $m = (int)date('m', strtotime($date));
        $y = (int)date('Y', strtotime($date));
        header("Location: mortality.php?batch_filter={$batch_id}&month={$m}&year={$y}");
        exit();
    } else {
        $error = $_SESSION['last_mortality_error'] ?? "Error recording mortality. Please check the quantity and try again.";
        unset($_SESSION['last_mortality_error']);
    }
}

if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// Get current month and year for calendar
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$batch_filter = isset($_GET['batch_filter']) ? $_GET['batch_filter'] : '';

// Check count of records in current view
$viewWhere = "WHERE quantity_change < 0 AND DATE_FORMAT(date, '%Y-%m') = ?";
$viewParams = [sprintf('%04d-%02d', $year, $month)];
if ($batch_filter) {
    $viewWhere .= " AND batch_id = ?";
    $viewParams[] = $batch_filter;
}
$stmtCountView = $conn->prepare("SELECT COUNT(*) FROM mortality_records $viewWhere");
$stmtCountView->execute($viewParams);
$viewCount = (int)$stmtCountView->fetchColumn();

// Find the latest month with records in database for helpful fallback link
$latestRow = $conn->query("SELECT DATE_FORMAT(date, '%Y') as y, DATE_FORMAT(date, '%m') as m FROM mortality_records WHERE quantity_change < 0 ORDER BY date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$latestM = $latestRow ? (int)$latestRow['m'] : null;
$latestY = $latestRow ? (int)$latestRow['y'] : null;

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day = date('w', strtotime("$year-$month-01"));

// Get summary period filter
$summary_period = isset($_GET['summary_period']) ? $_GET['summary_period'] : 'daily';

$activeBatches = $conn->query("SELECT batch_id, batch_code, current_quantity, arrival_date, house_pen, COALESCE(chick_unit_cost, 38.00) as chick_cost FROM batches WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);

include('header.php');
?>

<style>
.calendar-container .col {
    flex: 0 0 14.28%;
    max-width: 14.28%;
}
.calendar-container .card {
    min-height: 120px;
    border: 1px solid #eee;
    transition: all 0.2s ease;
}
.calendar-container .card:hover {
    background-color: #f8f9fa;
}
.calendar-container .overflow-auto::-webkit-scrollbar {
    width: 3px;
}
.calendar-container .overflow-auto::-webkit-scrollbar-track {
    background: transparent;
}
.calendar-container .overflow-auto::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
}

/* Professional Mortality Modal Styles */
.modal-loss-icon-wrap {
    width: 62px;
    height: 62px;
    background-color: #FEE2E2;
    color: #DC2626;
    border: 1px solid #FECACA;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.modal-warn-icon-wrap {
    width: 62px;
    height: 62px;
    background-color: #FEF3C7;
    color: #D97706;
    border: 1px solid #FDE68A;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.mortality-summary-table td {
    padding: 9px 0;
    vertical-align: middle;
}
.record-mortality-fab {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    z-index: 1040;
    padding: 0.85rem 1.35rem;
    font-size: 0.95rem;
    box-shadow: 0 10px 28px rgba(20, 83, 45, 0.35) !important;
}
.record-mortality-fab:hover {
    transform: translateY(-2px);
}
#mortalityLogsTable_wrapper .dataTables_filter input,
#mortalityLogsTable_wrapper .dataTables_length select,
#mortalitySummaryTable_wrapper .dataTables_filter input,
#mortalitySummaryTable_wrapper .dataTables_length select {
    border-radius: 0.5rem;
    border: 1px solid #dee2e6;
    padding: 0.35rem 0.65rem;
}
@media (max-width: 576px) {
    .record-mortality-fab span { display: none; }
    .record-mortality-fab {
        width: 3.25rem;
        height: 3.25rem;
        padding: 0;
        border-radius: 50% !important;
        justify-content: center;
    }
}
</style>

<div class="container-fluid px-2 px-md-3">
            <?php if(isset($msg)): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 shadow-sm rounded-3 py-2 px-3 mb-3 border border-success-subtle">
                    <i class="fas fa-check-circle text-success fs-5"></i>
                    <div>
                        <div class="fw-semibold text-success-emphasis">Mortality Entry Recorded</div>
                        <div class="small text-muted"><?php echo htmlspecialchars($msg); ?></div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 shadow-sm rounded-3 py-2 px-3 mb-3 border border-danger-subtle">
                    <i class="fas fa-circle-exclamation text-danger fs-5"></i>
                    <div>
                        <div class="fw-semibold text-danger-emphasis">Unable to Record Mortality</div>
                        <div class="small text-muted"><?php echo htmlspecialchars($error); ?></div>
                    </div>
                </div>
            <?php endif; ?>

        <!-- Mortality Summary Card -->
    <div class="card p-4 shadow-sm border-0 rounded-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="text-primary fw-bold mb-0"><i class="fas fa-chart-pie me-2"></i>Mortality Summary</h5>
                <select id="summaryPeriodSelect" class="form-select form-select-sm rounded-pill" style="width: auto;" onchange="updateSummaryPeriod(this.value)">
                    <option value="daily" <?php echo ($summary_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                    <option value="weekly" <?php echo ($summary_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo ($summary_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                    <option value="yearly" <?php echo ($summary_period == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                </select>
            </div>
        <div class="table-responsive custom-scrollbar">
            <table id="mortalitySummaryTable" class="table table-sm table-hover align-middle w-100 m-0">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th class="text-center">Loss (Heads)</th>
                            <th class="text-end">Financial Loss</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $where_clause = " WHERE quantity_change < 0 ";
                            $params = [];
                            if ($batch_filter) {
                                $where_clause .= " AND batch_id = ? ";
                                $params = [$batch_filter];
                            }

                            $group_by = "date";
                            $sql_group = "date";
                            
                            switch($summary_period) {
                                case 'weekly':
                                    $sql_group = "YEARWEEK(date, 1)";
                                    $group_by = "YEARWEEK(date, 1)";
                                    break;
                                case 'monthly':
                                    $sql_group = "DATE_FORMAT(date, '%Y-%m')";
                                    $group_by = "DATE_FORMAT(date, '%Y-%m')";
                                    break;
                                case 'yearly':
                                    $sql_group = "YEAR(date)";
                                    $group_by = "YEAR(date)";
                                    break;
                                default: // daily
                                    $sql_group = "date";
                                    $group_by = "date";
                                    break;
                            }

                            $summary_stmt = $conn->prepare("
                                SELECT 
                                    MIN(date) as period_start,
                                    $sql_group as period_label,
                                    ABS(SUM(quantity_change)) as total_loss,
                                    COALESCE(SUM(financial_value), 0) as total_financial_loss
                                FROM mortality_records 
                                $where_clause
                                GROUP BY $group_by
                            ORDER BY period_start DESC
                            ");
                            $summary_stmt->execute($params);
                            
                            while($d = $summary_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $display_period = "";
                                switch($summary_period) {
                                    case 'weekly':
                                        $week_start = date('M d', strtotime($d['period_start']));
                                        $week_end = date('M d, Y', strtotime($d['period_start'] . ' +6 days'));
                                        $display_period = "$week_start - $week_end";
                                        break;
                                    case 'monthly':
                                        $display_period = date('F Y', strtotime($d['period_start']));
                                        break;
                                    case 'yearly':
                                        $display_period = date('Y', strtotime($d['period_start']));
                                        break;
                                    default:
                                        $display_period = date('M d, Y', strtotime($d['period_start']));
                                        break;
                                }
                                
                                echo "<tr>
                                    <td><span class='small fw-bold text-muted'>$display_period</span></td>
                                    <td class='fw-bold text-danger text-center'>-{$d['total_loss']} <small>heads</small></td>
                                    <td class='fw-bold text-dark text-end'>₱" . number_format($d['total_financial_loss'], 2) . "</td>
                                </tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='3' class='text-center text-muted'>Error: ".$e->getMessage()."</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
        </div>
    </div>

    <!-- Calendar / Monthly View -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <h3 class="section-title mb-0"><i class="fas fa-calendar-alt text-primary"></i>Daily Mortality Logs</h3>
                <div class="d-flex gap-2">
                    <form action="mortality.php" method="GET" class="d-flex flex-wrap gap-2">
                        <select name="batch_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- All Batches --</option>
                            <?php
                            $all_batches = $conn->query("SELECT batch_id, batch_code FROM batches ORDER BY arrival_date DESC");
                            foreach($all_batches as $ab) {
                                $selected = ($batch_filter == $ab['batch_id']) ? 'selected' : '';
                                echo "<option value='{$ab['batch_id']}' $selected>" . e($ab['batch_code']) . "</option>";
                            }
                            ?>
                        </select>
                        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php if($m == $month) echo 'selected'; ?>>
                                    <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php for($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php if($y == $year) echo 'selected'; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if ($viewCount === 0): ?>
                <div class="alert alert-light border shadow-sm rounded-3 py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="fas fa-circle-info text-primary fs-5"></i>
                        <span>No mortality records found for <strong><?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?></strong><?php echo $batch_filter ? ' under this batch filter' : ''; ?>.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($batch_filter): ?>
                            <a href="mortality.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                <i class="fas fa-filter-circle-xmark me-1"></i>View All Batches
                            </a>
                        <?php endif; ?>
                        <?php if ($latestM && ($latestM != $month || $latestY != $year)): ?>
                            <a href="mortality.php?month=<?php echo $latestM; ?>&year=<?php echo $latestY; ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                <i class="fas fa-calendar-check me-1"></i>View Latest Records (<?php echo date('F Y', mktime(0,0,0,$latestM,1,$latestY)); ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Calendar Grid -->
            <div class="table-responsive custom-scrollbar pb-2">
                <div class="calendar-container" style="min-width: 580px;">
                    <div class="row g-2 text-center fw-bold mb-2">
                        <div class="col" style="width: 14.28%;">Sun</div>
                        <div class="col" style="width: 14.28%;">Mon</div>
                        <div class="col" style="width: 14.28%;">Tue</div>
                        <div class="col" style="width: 14.28%;">Wed</div>
                        <div class="col" style="width: 14.28%;">Thu</div>
                        <div class="col" style="width: 14.28%;">Fri</div>
                        <div class="col" style="width: 14.28%;">Sat</div>
                    </div>
                
                <div class="row g-2">
                    <?php
                    // Empty slots before first day
                    for($i=0; $i<$first_day; $i++) {
                        echo '<div class="col" style="width: 14.28%; min-height: 120px;"><div class="card h-100 border-0 bg-light opacity-25"></div></div>';
                    }

                    // Days of the month
                    for($day=1; $day<=$days_in_month; $day++) {
                        $current_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                        $today_class = ($current_date == date('Y-m-d')) ? 'border-primary shadow-sm' : 'border-light';
                        
                        // Fetch mortality records for this day
                        $query = "
                            SELECT m.quantity_change, m.reason, m.financial_value, b.batch_code 
                            FROM mortality_records m 
                            JOIN batches b ON m.batch_id = b.batch_id 
                            WHERE m.date = ? AND m.quantity_change < 0
                        ";
                        $params = [$current_date];
                        if ($batch_filter) {
                            $query .= " AND m.batch_id = ? ";
                            $params[] = $batch_filter;
                        }

                        $stmt = $conn->prepare($query);
                        $stmt->execute($params);
                        $day_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        echo '<div class="col" style="width: 14.28%; min-height: 120px;">';
                        $records_json = htmlspecialchars(json_encode($day_records), ENT_QUOTES, 'UTF-8');
                        $cursor_style = !empty($day_records) ? 'cursor: pointer;' : '';
                        $onclick = !empty($day_records) ? "onclick='showMortalityDetails(\"$current_date\", `$records_json`)'" : "";
                        
                        echo "  <div class='card h-100 $today_class p-1 position-relative' style='$cursor_style' $onclick>";
                        echo '    <span class="small fw-bold opacity-50">'.$day.'</span>';
                        if(!empty($day_records)) {
                            echo '    <div class="mt-1 overflow-auto" style="max-height: 90px; font-size: 0.65rem;">';
                            foreach($day_records as $rec) {
                        echo '      <div class="bg-danger text-white rounded px-1 mb-1 shadow-sm" style="line-height: 1.1;">';
                        echo '        <strong>'.e($rec['batch_code']).'</strong>: '.$rec['quantity_change'].'<br>';
                        echo '        <span style="font-size: 0.6rem; opacity: 0.9;">'.e($rec['reason']).'</span>';
                                echo '      </div>';
                            }
                            echo '    </div>';
                        }
                        echo '  </div>';
                        echo '</div>';

                        // New row every 7 days
                        if(($day + $first_day) % 7 == 0) echo '</div><div class="row g-2 mt-2">';
                    }
                    ?>
                </div>
                </div>
            </div>
            </div>
        </div>

    <!-- Mortality Log History Table -->
    <div class="card shadow-sm border rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;">
                        <i class="fas fa-clipboard-list text-danger me-2"></i>Recent Mortality Log Records
                    </h5>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill shadow-sm">
                        <i class="fas fa-shield-alt me-1"></i>Flock Deductions Ledger
                    </span>
                </div>
            <div class="table-responsive custom-scrollbar">
                <table id="mortalityLogsTable" class="table table-hover align-middle w-100 m-0 table-mobile-cards">
                    <thead class="table-light">
                            <tr>
                                <th style="width: 18%;">Date</th>
                                <th style="width: 22%;">Batch</th>
                                <th style="width: 16%;" class="text-center">Loss (Heads)</th>
                                <th style="width: 22%;">Reason / Cause</th>
                                <th style="width: 12%;" class="text-end">Loss (₱)</th>
                                <th style="width: 10%;">Worker</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recentMort = $conn->query("
                                SELECT m.*, b.batch_code, b.house_pen, u.full_name, u.username
                                FROM mortality_records m
                                JOIN batches b ON m.batch_id = b.batch_id
                                LEFT JOIN users u ON m.recorded_by = u.user_id
                                WHERE m.quantity_change < 0
                                ORDER BY m.date DESC, m.mortality_id DESC
                            ")->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($recentMort as $rm):
                                    $recName = !empty($rm['full_name']) ? e($rm['full_name']) : (!empty($rm['username']) ? e($rm['username']) : 'Admin');
                                    $pen = !empty($rm['house_pen']) ? ' <span class="badge bg-light text-muted border py-0 px-2 small">' . e($rm['house_pen']) . '</span>' : '';
                            ?>
                                <tr>
                                    <td data-label="Date">
                                        <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($rm['date'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($rm['created_at'])); ?></small>
                                    </td>
                                    <td data-label="Batch">
                                        <span class="fw-bold text-primary"><?php echo e($rm['batch_code']); ?></span>
                                        <?php echo $pen; ?>
                                    </td>
                                    <td data-label="Loss (Heads)" class="text-center">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 fs-6 fw-bold">
                                            <?php echo (int)$rm['quantity_change']; ?> heads
                                        </span>
                                    </td>
                                    <td data-label="Reason / Cause">
                                        <div class="fw-semibold text-dark"><?php echo e($rm['reason']); ?></div>
                                    </td>
                                    <td data-label="Loss (₱)" class="text-end">
                                        <span class="fw-bold text-dark">₱<?php echo number_format((float)$rm['financial_value'], 2); ?></span>
                                    </td>
                                    <td data-label="Worker">
                                        <small class="text-secondary fw-medium"><?php echo $recName; ?></small>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </div>
</div>

<!-- Sticky FAB: Record Mortality -->
<button type="button"
        class="btn btn-danger record-mortality-fab shadow-lg rounded-pill fw-bold d-flex align-items-center gap-2"
        data-bs-toggle="modal"
        data-bs-target="#recordMortalityModal"
        title="Record Mortality">
    <i class="fas fa-plus"></i>
    <span>Record Mortality</span>
</button>

<!-- Modal: Record Mortality -->
<div class="modal fade" id="recordMortalityModal" tabindex="-1" aria-labelledby="recordMortalityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-danger-subtle text-danger rounded-circle">
                        <i class="fas fa-clipboard-list fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="recordMortalityModalLabel">Record Mortality</h5>
                        <small class="text-muted">Log flock headcount loss &amp; financial impact</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" action="mortality.php" id="mortalityForm" onsubmit="event.preventDefault(); initiateMortalityConfirmation();">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="save_mortality" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Broiler Batch</label>
                        <select name="batch_id" id="batchSelect" class="form-select" required onchange="calculateAutoLoss()">
                            <option value="">-- Choose Batch --</option>
                            <?php
                            foreach($activeBatches as $b) {
                                $hp = !empty($b['house_pen']) ? ' - ' . e($b['house_pen']) : '';
                                echo "<option value='{$b['batch_id']}' data-qty='{$b['current_quantity']}' data-arrival='{$b['arrival_date']}' data-cost='{$b['chick_cost']}'>" . e($b['batch_code']) . $hp . " ({$b['current_quantity']} heads left)</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Number of Mortality (Heads)</label>
                        <input type="number" name="quantity" id="mortQty" class="form-control" placeholder="e.g., 5" min="1" inputmode="numeric" required oninput="calculateAutoLoss()">
                        <div id="qtyHeadcountFeedback" class="small mt-1"></div>
                        <small class="text-muted">Enter the number of broilers lost. Headcount will be safely deducted from flock inventory.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason / Cause</label>
                        <input type="text" name="reason" id="mortReason" class="form-control" placeholder="e.g., Heat stress, Smothering, Respiratory" required>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label fw-bold mb-0">Financial Loss Impact (₱)</label>
                            <span class="badge bg-light text-primary border" id="autoCalcBadge">Auto-Calculated</span>
                        </div>
                        <input type="number" step="0.01" name="financial_value" id="financialValueInput" class="form-control mt-1" placeholder="0.00" inputmode="decimal">
                        <div id="lossBreakdownText" class="small text-muted mt-1">Estimated loss based on Day-Old Chick cost + accumulated feed & meds.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Date of Occurrence</label>
                        <input type="date" name="date" id="mortDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required onchange="calculateAutoLoss()">
                    </div>

                    <div class="d-grid">
                        <button type="submit" id="saveMortalityBtn" class="btn btn-danger w-100 py-2 fw-semibold shadow-sm">
                            <i class="fas fa-clipboard-check me-2"></i>Review & Record Mortality
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Professional Confirmation Warning Modal -->
<div class="modal fade" id="confirmMortalityModal" tabindex="-1" aria-labelledby="confirmMortalityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down" style="max-width: 490px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <!-- Modal Header / Alert Crest -->
            <div class="modal-body p-4 text-center pb-2">
                <div class="mx-auto mb-3 modal-loss-icon-wrap shadow-sm">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h5 class="fw-bold mb-1 text-dark" id="confirmMortalityModalLabel" style="font-size: 19px;">
                    Confirm Mortality Record
                </h5>
                <p class="text-muted small mb-3">
                    Please verify the flock headcount deduction and financial assessment before applying this record.
                </p>

                <!-- Detailed Audit Summary Box -->
                <div class="text-start p-3 rounded-3 mb-3 bg-light border">
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Flock Batch</span>
                        <span class="fw-bold text-dark text-end" id="confModalBatch">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Mortality Count</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold fs-6 px-3 py-1" id="confModalQty">-0 Heads</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Live Flock Impact</span>
                        <span class="fw-semibold text-dark text-end" id="confModalFlock">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Cause / Observation</span>
                        <span class="fw-semibold text-dark text-end text-truncate" style="max-width: 240px;" id="confModalReason">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                        <span class="text-muted small fw-medium">Estimated Financial Loss</span>
                        <span class="fw-bold text-danger text-end fs-6" id="confModalLoss">₱0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-medium">Record Date</span>
                        <span class="text-secondary text-end fw-medium" id="confModalDate">--</span>
                    </div>
                </div>

                <!-- Professional Operational Advisory Notice -->
                <div class="alert alert-warning-subtle border border-warning-subtle rounded-3 py-2 px-3 small d-flex align-items-start gap-2 text-warning-emphasis text-start mb-0">
                    <i class="fas fa-circle-info mt-1 flex-shrink-0 text-warning"></i>
                    <div style="font-size: 12.5px; line-height: 1.45;">
                        <strong>System Notice:</strong> Confirming will immediately decrement the live batch headcount, record the loss in the financial ledger, and log an audit entry.
                    </div>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="modal-footer border-0 p-3 pt-0 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary flex-fill py-2 rounded-3 fw-medium" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-1"></i> Edit Details
                </button>
                <button type="button" class="btn btn-danger flex-fill py-2 rounded-3 fw-semibold shadow-sm" id="btnConfirmMortalityFinal" onclick="executeMortalitySubmission()">
                    <i class="fas fa-check-circle me-1"></i> Confirm & Record
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Professional Validation Warning Modal -->
<div class="modal fade" id="mortalityValidationModal" tabindex="-1" aria-labelledby="mortalityValidationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down" style="max-width: 420px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="mx-auto mb-3 modal-warn-icon-wrap shadow-sm">
                    <i class="fas fa-circle-exclamation"></i>
                </div>
                <h5 class="fw-bold mb-2 text-dark" id="mortalityValidationModalLabel" style="font-size: 18px;">Validation Notice</h5>
                <p class="text-muted small mb-4" id="mortalityValidationMessage" style="line-height: 1.6; font-size: 13px;"></p>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-3 w-100 fw-semibold" data-bs-dismiss="modal">
                    Acknowledge & Adjust
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mortality Detail Modal -->
<div class="modal fade" id="mortalityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary"><i class="fas fa-clipboard-list me-2"></i>Daily Mortality Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalDate" class="text-muted mb-3 small fw-bold text-uppercase"></div>
                <div id="modalContent"></div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
function showMortalityDetails(date, recordsJson) {
    const records = JSON.parse(recordsJson);
    const modalDate = document.getElementById('modalDate');
    const modalContent = document.getElementById('modalContent');
    
    // Format date nicely
    const dateObj = new Date(date);
    modalDate.innerText = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // SAFE rendering: build DOM nodes with textContent so no HTML escaping is needed.
    modalContent.innerHTML = '';
    records.forEach(rec => {
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center p-3 mb-2 bg-light rounded-3 border-start border-4 border-danger';

        const qtyCol = document.createElement('div');
        qtyCol.className = 'me-3 text-center';
        qtyCol.style.minWidth = '50px';
        const qtyBadge = document.createElement('span');
        qtyBadge.className = 'badge bg-danger rounded-pill px-3 py-2';
        qtyBadge.textContent = String(rec.quantity_change);
        qtyCol.appendChild(qtyBadge);

        const infoCol = document.createElement('div');
        infoCol.className = 'flex-grow-1';
        const title = document.createElement('div');
        title.className = 'fw-bold text-dark mb-0';
        title.textContent = 'Batch ' + rec.batch_code;
        const sub = document.createElement('div');
        sub.className = 'small text-muted';
        let subText = rec.reason;
        if (parseFloat(rec.financial_value) > 0) {
            subText += ' (Loss: ₱' + parseFloat(rec.financial_value).toFixed(2) + ')';
        }
        sub.textContent = subText;
        infoCol.appendChild(title);
        infoCol.appendChild(sub);

        row.appendChild(qtyCol);
        row.appendChild(infoCol);
        modalContent.appendChild(row);
    });
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('mortalityModal'));
    modal.show();
}

function updateSummaryPeriod(period) {
    const url = new URL(window.location);
    url.searchParams.set('summary_period', period);
    window.location.href = url.href;
}

function calculateAutoLoss() {
    const batchSelect = document.getElementById('batchSelect');
    if (!batchSelect) return;
    const selectedOption = batchSelect.options[batchSelect.selectedIndex];
    const qtyInput = document.getElementById('mortQty');
    const dateInput = document.getElementById('mortDate');
    const lossInput = document.getElementById('financialValueInput');
    const feedback = document.getElementById('qtyHeadcountFeedback');
    const breakdown = document.getElementById('lossBreakdownText');

    if (!selectedOption || !selectedOption.value) {
        if (feedback) feedback.innerHTML = '';
        return;
    }

    const currentQty = parseInt(selectedOption.getAttribute('data-qty') || 0);
    const arrivalDateStr = selectedOption.getAttribute('data-arrival');
    const chickCost = parseFloat(selectedOption.getAttribute('data-cost') || 38.00);
    const deadQty = parseInt(qtyInput.value || 0);

    // Real-time live feedback with professional phrasing
    if (deadQty > currentQty) {
        feedback.innerHTML = `<span class="text-danger fw-semibold"><i class="fas fa-circle-exclamation me-1"></i>Headcount limit exceeded: Batch currently has ${currentQty.toLocaleString()} live heads.</span>`;
        qtyInput.classList.add('is-invalid');
    } else if (deadQty >= 50 || (currentQty > 0 && deadQty >= currentQty * 0.1)) {
        feedback.innerHTML = `<span class="text-warning-emphasis fw-semibold"><i class="fas fa-triangle-exclamation me-1"></i>Elevated Mortality Rate: ${deadQty} heads flagged. Please verify cause and farm protocol.</span>`;
        qtyInput.classList.remove('is-invalid');
    } else if (deadQty > 0) {
        feedback.innerHTML = `<span class="text-success fw-medium"><i class="fas fa-circle-check me-1"></i>Projected post-deduction population: ${Math.max(0, currentQty - deadQty).toLocaleString()} heads remaining.</span>`;
        qtyInput.classList.remove('is-invalid');
    } else {
        feedback.innerHTML = '';
        qtyInput.classList.remove('is-invalid');
    }

    // Auto calculate financial loss based on age and feed
    if (arrivalDateStr && deadQty > 0) {
        const arrDate = new Date(arrivalDateStr);
        const recDate = new Date(dateInput.value || new Date());
        const ageDays = Math.max(1, Math.floor((recDate - arrDate) / (1000 * 60 * 60 * 24)));
        const feedMeds = Math.min(35, ageDays) * 2.85;
        const costPerBird = chickCost + feedMeds;
        const totalLoss = (deadQty * costPerBird).toFixed(2);

        lossInput.value = totalLoss;
        breakdown.innerHTML = `<span class="text-primary fw-bold">₱${parseFloat(totalLoss).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span> (${deadQty} birds @ ~₱${costPerBird.toFixed(2)}/bird at Day ${ageDays}: ₱${chickCost.toFixed(2)} DOC + ₱${feedMeds.toFixed(2)} feed/meds)`;
    }
}

function showValidationNotice(title, message, focusElementId) {
    document.getElementById('mortalityValidationModalLabel').textContent = title;
    document.getElementById('mortalityValidationMessage').textContent = message;
    const modalEl = document.getElementById('mortalityValidationModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    if (focusElementId) {
        modalEl.addEventListener('hidden.bs.modal', function onHidden() {
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            const el = document.getElementById(focusElementId);
            if (el) el.focus();
        });
    }
    modal.show();
}

function initiateMortalityConfirmation() {
    const batchSelect = document.getElementById('batchSelect');
    const selectedOption = batchSelect ? batchSelect.options[batchSelect.selectedIndex] : null;
    const qtyInput = document.getElementById('mortQty');
    const reasonInput = document.getElementById('mortReason');
    const dateInput = document.getElementById('mortDate');
    const lossInput = document.getElementById('financialValueInput');

    if (!selectedOption || !selectedOption.value) {
        showValidationNotice(
            'Batch Selection Required',
            'Please select an active broiler flock batch before recording mortality.',
            'batchSelect'
        );
        return;
    }

    const currentQty = parseInt(selectedOption.getAttribute('data-qty') || 0);
    const deadQty = parseInt(qtyInput ? qtyInput.value : 0);

    if (isNaN(deadQty) || deadQty <= 0) {
        showValidationNotice(
            'Invalid Headcount',
            'Please enter a valid mortality count (minimum 1 head).',
            'mortQty'
        );
        return;
    }

    if (deadQty > currentQty) {
        showValidationNotice(
            'Flock Population Limit Exceeded',
            `The requested mortality count of ${deadQty} heads exceeds the currently available live population (${currentQty} heads) for this batch.`,
            'mortQty'
        );
        return;
    }

    const reason = reasonInput ? reasonInput.value.trim() : '';
    if (!reason) {
        showValidationNotice(
            'Observed Cause Required',
            'Please specify the cause or observed symptoms for this mortality entry (e.g., Heat stress, Smothering).',
            'mortReason'
        );
        return;
    }

    // Populate confirmation modal fields
    document.getElementById('confModalBatch').textContent = selectedOption.text.split('(')[0].trim();
    document.getElementById('confModalQty').textContent = `-${deadQty} Heads`;
    
    const remainingHeads = Math.max(0, currentQty - deadQty);
    document.getElementById('confModalFlock').innerHTML = `${currentQty.toLocaleString()} &rarr; <span class="text-success fw-bold">${remainingHeads.toLocaleString()} live heads left</span>`;
    
    document.getElementById('confModalReason').textContent = reason;
    
    const lossVal = parseFloat(lossInput ? lossInput.value : 0) || 0;
    document.getElementById('confModalLoss').textContent = '₱' + lossVal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    let formattedDate = dateInput && dateInput.value ? dateInput.value : 'Today';
    try {
        const d = new Date(dateInput.value);
        if (!isNaN(d.getTime())) {
            formattedDate = d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
    } catch(e) {}
    document.getElementById('confModalDate').textContent = formattedDate;

    // Show Confirmation Warning Modal
    const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmMortalityModal'));
    confirmModal.show();
}

function executeMortalitySubmission() {
    const btn = document.getElementById('btnConfirmMortalityFinal');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving Record...';
    }
    document.getElementById('mortalityForm').submit();
}

// Backward compatibility fallback
function validateMortalitySubmission() {
    initiateMortalityConfirmation();
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    calculateAutoLoss();

    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#mortalitySummaryTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            language: {
                search: 'Search summary:',
                lengthMenu: 'Show _MENU_ periods',
                info: 'Showing _START_ to _END_ of _TOTAL_ periods',
                infoEmpty: 'No mortality summary',
                zeroRecords: 'No matching summary periods found',
                emptyTable: 'No mortality records found.'
            }
        });

        jQuery('#mortalityLogsTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            language: {
                search: 'Search mortality logs:',
                lengthMenu: 'Show _MENU_ records',
                info: 'Showing _START_ to _END_ of _TOTAL_ records',
                infoEmpty: 'No mortality logs',
                zeroRecords: 'No matching mortality records found',
                emptyTable: 'No mortality entries recorded yet.'
            }
        });
    }

    <?php if (isset($error)): ?>
    var recordModalEl = document.getElementById('recordMortalityModal');
    if (recordModalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(recordModalEl).show();
    }
    <?php endif; ?>
});
</script>

<?php include('footer.php'); ?>
