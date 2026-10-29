<?php 
include('database.php'); 
include('db_check.php'); 

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = strtolower(trim($_SESSION['role'] ?? 'worker'));
$isAdmin = ($user_role === 'admin');
$canRecordFeed = in_array($user_role, ['admin', 'staff', 'worker'], true);

$cat = isset($_GET['cat']) ? strtolower(trim($_GET['cat'])) : 'feed';
$is_limestone = ($cat === 'limestone');
$target_category = $is_limestone ? 'limestone' : 'feed';

$section_icon = $is_limestone ? 'fa-mountain text-success' : 'fa-wheat-awn text-warning';
$form_title = $is_limestone ? 'Schedule / Record Limestone Usage' : 'Schedule / Record Feed Consumption';
$item_label = $is_limestone ? 'Limestone Item' : 'Feed Item';
$item_placeholder = $is_limestone ? '-- Choose Limestone Item --' : '-- Choose Feed Item --';
$button_text = $is_limestone ? 'Schedule / Record Limestone' : 'Schedule / Record Feeding';
$history_title = $is_limestone ? 'Limestone Usage & Distribution' : 'Feed Consumption & Distribution';
$history_col_header = $is_limestone ? 'Limestone Item' : 'Feed Item';
$no_records_msg = $is_limestone ? 'No limestone usage records found.' : 'No feeding records found.';
$success_msg = $is_limestone ? 'Limestone record updated and inventory deducted!' : 'Feed record updated and inventory deducted!';

$message = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle Feed Consumption / Scheduling (Admin / Staff / Worker)
if (isset($_POST['consume'])) {
    if (!$canRecordFeed) {
        $error = "Access Denied. You do not have permission to schedule feeding records.";
    } else {
        $batch_id = intval($_POST['batch_id'] ?? 0);
        $inventory_id = intval($_POST['inventory_id'] ?? 0);
        $qty = floatval($_POST['qty'] ?? 0);
        $date = !empty($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
        $feed_stage = (!$is_limestone && !empty($_POST['feed_stage'])) ? trim($_POST['feed_stage']) : null;
        $status = (!empty($_POST['status']) && in_array($_POST['status'], ['pending', 'completed'])) ? $_POST['status'] : 'pending';
        $remarks = trim($_POST['remarks'] ?? '');

        if ($batch_id <= 0 || $inventory_id <= 0 || $qty <= 0) {
            $error = "Please provide a valid broiler batch, item, and quantity greater than zero.";
        } elseif (!$is_limestone && empty($feed_stage)) {
            $error = "Please select a valid feed stage (Pre-Starter, Starter, Grower, or Finisher).";
        } else {
            if (record_feed_consumption($conn, $batch_id, $inventory_id, $qty, $date, $feed_stage, $status, $remarks)) {
                $message = ($status === 'pending')
                    ? ucfirst($target_category) . " distribution task scheduled as Pending Action for farm workers!"
                    : $success_msg;
            } else {
                $error = $_SESSION['last_feed_error'] ?? "Error recording {$target_category} consumption. Please check available stock.";
                unset($_SESSION['last_feed_error']);
            }
        }
    }
}

// Handle Feed Action Completion (Worker Action / Admin Override)
if (isset($_POST['complete_feeding'])) {
    $consumption_id = intval($_POST['consumption_id'] ?? 0);
    if ($consumption_id <= 0) {
        $error = "Invalid consumption record selected.";
    } else {
        try {
            $conn->beginTransaction();
            $stmtFC = $conn->prepare("SELECT * FROM feed_consumption WHERE consumption_id = ?");
            $stmtFC->execute([$consumption_id]);
            $fcRecord = $stmtFC->fetch(PDO::FETCH_ASSOC);

            if (!$fcRecord) {
                throw new Exception("Feeding record not found.");
            }

            if ($fcRecord['status'] === 'completed') {
                $message = "This feeding task has already been completed.";
            } else {
                // 1. Update feed_consumption
                $stmtUp = $conn->prepare("
                    UPDATE feed_consumption 
                    SET status = 'completed', completed_by = ?, completed_at = NOW() 
                    WHERE consumption_id = ?
                ");
                $stmtUp->execute([$user_id, $consumption_id]);

                // 2. Log audit trail
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $stmtAudit = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
                    VALUES (?, 'update', 'feed_consumption', ?, ?, ?, NOW())
                ");
                $stmtAudit->execute([
                    $user_id,
                    $consumption_id,
                    json_encode([
                        'action' => 'complete_feeding',
                        'status' => 'completed',
                        'completed_by' => $user_id,
                        'completed_at' => date('Y-m-d H:i:s')
                    ]),
                    $ip
                ]);

                $conn->commit();
                $message = "Feeding distribution action performed and marked as completed successfully!";
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = "Error completing feeding action: " . $e->getMessage();
        }
    }
}

// PRG Pattern to prevent duplicate form submission on refresh
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catParam = ($cat === 'limestone') ? '&cat=limestone' : '';
    if (!empty($message)) {
        echo "<script>window.location.href = 'feed.php?msg=" . urlencode($message) . $catParam . "';</script>";
        exit;
    } elseif (!empty($error)) {
        echo "<script>window.location.href = 'feed.php?error=" . urlencode($error) . $catParam . "';</script>";
        exit;
    }
}

include('header.php'); 

// Fetch active broiler batches
$stmtBatches = $conn->query("
    SELECT batch_id, batch_code, breed, house_pen 
    FROM batches 
    WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) 
    ORDER BY arrival_date DESC, batch_id DESC
");
$batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);

// Fetch items filtered strictly by category (limestone vs feed)
$stmtItems = $conn->prepare("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE category = ? ORDER BY item_name");
$stmtItems->execute([$target_category]);
$inventory_items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Fetch history records with creator and completer info
$stmtHistory = $conn->prepare("
    SELECT 
        fc.*, 
        b.batch_code, 
        b.flock_type, 
        b.house_pen,
        i.item_name, 
        i.unit, 
        i.category,
        COALESCE(rec.full_name, rec.username, 'System Admin') AS recorded_by_name,
        rec.role AS recorded_by_role,
        COALESCE(comp.full_name, comp.username) AS completed_by_name,
        comp.role AS completed_by_role
    FROM feed_consumption fc 
    JOIN batches b ON fc.batch_id = b.batch_id 
    JOIN inventory i ON fc.inventory_id = i.inventory_id 
    LEFT JOIN users rec ON fc.recorded_by = rec.user_id
    LEFT JOIN users comp ON fc.completed_by = comp.user_id
    WHERE i.category = ?
    ORDER BY fc.date DESC, fc.consumption_id DESC
");
$stmtHistory->execute([$target_category]);
$historyRecords = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .record-activity-fab {
        position: fixed;
        right: 1.5rem;
        bottom: 1.5rem;
        z-index: 1040;
        padding: 0.85rem 1.35rem;
        font-size: 0.95rem;
        box-shadow: 0 10px 28px rgba(20, 83, 45, 0.35) !important;
    }
    .record-activity-fab:hover {
        transform: translateY(-2px);
    }
    #feedHistoryTable_wrapper .dataTables_filter input,
    #feedHistoryTable_wrapper .dataTables_length select {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        padding: 0.35rem 0.65rem;
    }
    @media (max-width: 576px) {
        .record-activity-fab span { display: none; }
        .record-activity-fab {
            width: 3.25rem;
            height: 3.25rem;
            padding: 0;
            border-radius: 50% !important;
            justify-content: center;
        }
    }
</style>

<!-- Category Navigation Pills -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo !$is_limestone ? 'active fw-bold' : ''; ?>" href="feed.php">
            <i class="fas fa-wheat-awn me-2"></i>Feed Consumption
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $is_limestone ? 'active fw-bold' : ''; ?>" href="feed.php?cat=limestone">
            <i class="fas fa-mountain me-2"></i>Limestone Usage
        </a>
    </li>
</ul>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo e($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h3 class="section-title mb-0"><i class="fas fa-history me-2"></i><?php echo $history_title; ?></h3>
                <?php if ($isAdmin): ?>
                    <small class="text-muted">Fill up records and monitor distribution completion across broiler houses.</small>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-dark border border-warning px-3 py-1 rounded-pill mt-1">
                        <i class="fas fa-hand-pointer me-1"></i>Record feeding, or click <strong>Complete Action</strong> on pending tasks
                    </span>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($isAdmin): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill shadow-sm">
                        <i class="fas fa-shield-alt me-1"></i>Admin Manager
                    </span>
                <?php elseif ($user_role === 'staff'): ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 rounded-pill shadow-sm">
                        <i class="fas fa-user-check me-1"></i>Staff Operator
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-dark border border-warning px-3 py-2 rounded-pill shadow-sm">
                        <i class="fas fa-user-clock me-1"></i>Worker Action Mode
                    </span>
                <?php endif; ?>
                <span class="badge bg-primary px-3 py-2 rounded-pill fw-bold">
                    <?php echo count($historyRecords); ?> Entries
                </span>
            </div>
        </div>

        <div class="table-responsive custom-scrollbar">
            <table id="feedHistoryTable" class="table table-hover align-middle w-100 m-0 table-mobile-cards">
                <thead class="table-light" style="border-bottom: 2px solid var(--border, #DDD6C4);">
                    <tr>
                        <th>Date</th>
                        <th>Broiler Batch</th>
                        <?php if (!$is_limestone): ?>
                            <th>Stage</th>
                        <?php endif; ?>
                        <th><?php echo $history_col_header; ?></th>
                        <th class="text-end">Qty Used</th>
                        <th>Recorded By</th>
                        <th>Performed By (Worker)</th>
                        <th class="text-center">Status / Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($historyRecords as $row): ?>
                        <?php
                        $stage = $row['feed_stage'] ?? '';
                        $stageBadge = 'bg-secondary text-white';
                        if ($stage === 'Pre-Starter') $stageBadge = 'bg-primary text-white';
                        elseif ($stage === 'Starter') $stageBadge = 'bg-success text-white';
                        elseif ($stage === 'Grower') $stageBadge = 'bg-warning text-dark';
                        elseif ($stage === 'Finisher') $stageBadge = 'bg-danger text-white';

                        $hpBadge = !empty($row['house_pen']) ? ' &bull; ' . e($row['house_pen']) : '';
                        $isPending = ($row['status'] === 'pending');
                        ?>
                        <tr>
                            <td data-label="Date">
                                <div class="fw-semibold text-dark"><?php echo date('M d, Y', strtotime($row['date'])); ?></div>
                                <?php if (!empty($row['created_at'])): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?php echo date('h:i A', strtotime($row['created_at'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Broiler Batch">
                                <span class="badge bg-light text-primary border">
                                    <i class="fas fa-drumstick-bite me-1 text-success"></i><?php echo e($row['batch_code']); ?><?php echo $hpBadge; ?>
                                </span>
                            </td>
                            <?php if (!$is_limestone): ?>
                                <td data-label="Stage">
                                    <?php if (!empty($stage)): ?>
                                        <span class="badge rounded-pill <?php echo $stageBadge; ?> px-2 py-1">
                                            <?php echo e($stage); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td data-label="Item">
                                <div class="fw-bold text-dark"><?php echo e($row['item_name']); ?></div>
                                <?php if (!empty($row['remarks'])): ?>
                                    <div class="text-muted small fst-italic mt-1" style="max-width: 220px; white-space: normal;">
                                        <i class="fas fa-comment-dots me-1 text-secondary"></i>"<?php echo e($row['remarks']); ?>"
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Qty Used" class="text-end fw-bold text-danger">
                                -<?php echo number_format($row['quantity_used'], 2); ?> <?php echo e($row['unit']); ?>
                            </td>
                            <td data-label="Filled Up By">
                                <div class="fw-semibold text-dark small"><?php echo e($row['recorded_by_name']); ?></div>
                                <span class="badge bg-dark text-white px-2 py-0" style="font-size: 0.65rem;">
                                    <?php echo ucfirst(e($row['recorded_by_role'] ?? 'admin')); ?>
                                </span>
                            </td>
                            <td data-label="Performed By">
                                <?php if (!empty($row['completed_by_name'])): ?>
                                    <div class="fw-semibold text-success small"><?php echo e($row['completed_by_name']); ?></div>
                                    <span class="badge bg-light text-secondary border px-2 py-0" style="font-size: 0.65rem;">
                                        <?php echo ucfirst(e($row['completed_by_role'] ?? 'worker')); ?>
                                    </span>
                                    <?php if (!empty($row['completed_at'])): ?>
                                        <div class="text-muted" style="font-size: 0.70rem;"><?php echo date('M d, h:i A', strtotime($row['completed_at'])); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Pending Distribution</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status / Action" class="text-center">
                                <?php if ($isPending): ?>
                                    <?php if (!$isAdmin): ?>
                                        <!-- Worker Action Button -->
                                        <form method="POST" class="m-0 d-inline-block">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="consumption_id" value="<?php echo (int)$row['consumption_id']; ?>">
                                            <button type="submit" name="complete_feeding" class="btn btn-sm btn-warning text-dark rounded-pill fw-bold px-3 py-1 shadow-sm" title="Confirm feed was distributed in the pen">
                                                <i class="fas fa-check-double me-1"></i>Complete Action
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Admin Status Badge & Quick Complete -->
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill">
                                                <i class="far fa-clock me-1"></i>Pending Action
                                            </span>
                                            <form method="POST" class="m-0 d-inline-block">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="consumption_id" value="<?php echo (int)$row['consumption_id']; ?>">
                                                <button type="submit" name="complete_feeding" class="btn btn-xs btn-outline-success rounded-pill px-2 py-0" title="Admin override: mark completed">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                                        <i class="fas fa-check-circle me-1"></i>Completed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canRecordFeed): ?>
<!-- Sticky FAB: Schedule / Record Feed -->
<button type="button"
        class="btn btn-primary record-activity-fab shadow-lg rounded-pill fw-bold d-flex align-items-center gap-2"
        data-bs-toggle="modal"
        data-bs-target="#feedRecordModal"
        title="<?php echo e($form_title); ?>">
    <i class="fas fa-plus"></i>
    <span><?php echo e($form_title); ?></span>
</button>

<!-- Modal: Schedule / Record Feed -->
<div class="modal fade" id="feedRecordModal" tabindex="-1" aria-labelledby="feedRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas <?php echo $section_icon; ?> fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="feedRecordModalLabel"><?php echo e($form_title); ?></h5>
                        <small class="text-muted">
                            <?php if ($isAdmin): ?>
                                <?php if ($is_limestone): ?>
                                    Fill up limestone supplementation requirements. Farm workers will execute the physical distribution.
                                <?php else: ?>
                                    Fill up daily flock feeding requirements. Farm workers will execute the physical distribution in the pens.
                                <?php endif; ?>
                            <?php else: ?>
                                Record <?php echo $is_limestone ? 'limestone' : 'feed'; ?> usage for active broiler batches.
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Broiler Batch <span class="text-danger">*</span></label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Choose Broiler Batch --</option>
                            <?php foreach($batches as $b): ?>
                                <?php $hp = !empty($b['house_pen']) ? ' - ' . e($b['house_pen']) : ''; ?>
                                <option value="<?php echo (int)$b['batch_id']; ?>">
                                    <?php echo e($b['batch_code']) . $hp; ?> (Broiler<?php echo !empty($b['breed']) ? ' - ' . e($b['breed']) : ''; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Consumption Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <?php if (!$is_limestone): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Feed Stage <span class="text-danger">*</span></label>
                        <select name="feed_stage" class="form-select" required>
                            <option value="">-- Select Feed Stage --</option>
                            <option value="Pre-Starter">Pre-Starter</option>
                            <option value="Starter">Starter</option>
                            <option value="Grower">Grower</option>
                            <option value="Finisher">Finisher</option>
                        </select>
                        <small class="text-muted">Pre-Starter (1-10d), Starter (11-20d), Grower (21-30d), Finisher (31d+)</small>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo $item_label; ?> <span class="text-danger">*</span></label>
                        <select name="inventory_id" id="feedItemSelect" class="form-select" required>
                            <option value=""><?php echo $item_placeholder; ?></option>
                            <?php if (empty($inventory_items)): ?>
                                <option value="" disabled>No active <?php echo $target_category; ?> items in inventory</option>
                            <?php else: ?>
                                <?php foreach($inventory_items as $f): ?>
                                    <?php $isOutOfStock = ((float)$f['quantity'] <= 0); ?>
                                    <option value="<?php echo (int)$f['inventory_id']; ?>" 
                                            data-unit="<?php echo e($f['unit']); ?>"
                                            data-stock="<?php echo (float)$f['quantity']; ?>"
                                            <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                        <?php echo e($f['item_name']); ?> (Stock: <?php echo number_format($f['quantity'], 2); ?> <?php echo e($f['unit']); ?>)
                                        <?php echo $isOutOfStock ? ' [OUT OF STOCK]' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($inventory_items)): ?>
                            <small class="text-warning mt-1 d-block">
                                <i class="fas fa-exclamation-triangle me-1"></i>Add <?php echo $target_category; ?> in <a href="inventory.php">Central Inventory</a> first.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantity Consumed <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0.01" name="qty" class="form-control" placeholder="0.00" inputmode="decimal" required>
                            <span class="input-group-text" id="unitDisplay">Units</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Execution Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <?php if ($isAdmin): ?>
                            <option value="pending" selected>⏳ Pending Action (Worker will distribute in pens)</option>
                            <option value="completed">✓ Completed (Already distributed)</option>
                            <?php else: ?>
                            <option value="completed" selected>✓ Completed (Already distributed)</option>
                            <option value="pending">⏳ Pending Action (Needs follow-up)</option>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">
                            <?php echo $isAdmin
                                ? 'Set as "Pending Action" so farm workers can confirm physical distribution in pens.'
                                : 'Use Completed when logging feed/limestone you already distributed.'; ?>
                        </small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Remarks / Instructions <span class="text-muted small fw-normal">(Optional)</span></label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="e.g., Distribute 15 bags across north and south feeder lines..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="consume" class="btn btn-primary w-100 shadow-sm py-2 fw-bold">
                            <i class="fas fa-clipboard-check me-2"></i><?php echo $button_text; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include('footer.php'); ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#feedHistoryTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: {
                search: 'Search records:',
                lengthMenu: 'Show _MENU_ records',
                info: 'Showing _START_ to _END_ of _TOTAL_ records',
                infoEmpty: 'No records found',
                zeroRecords: 'No matching records found',
                emptyTable: <?php echo json_encode($no_records_msg); ?>
            }
        });
    }

    document.getElementById('feedItemSelect')?.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const unit = selected.getAttribute('data-unit');
        if (unit) {
            document.getElementById('unitDisplay').textContent = unit;
        }
    });
});
</script>
