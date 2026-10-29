<?php include('database.php'); ?>
<?php include('db_check.php'); ?>
<?php
$msg = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;
$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = strtolower(trim($_SESSION['role'] ?? 'worker'));
$isAdmin = ($user_role === 'admin');
$canRecordHealth = in_array($user_role, ['admin', 'staff', 'worker'], true);

// Handle Add Health Record (Admin / Staff / Worker)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!$canRecordHealth) {
        $error = "Access Denied: You do not have permission to create flock health records.";
    } else {
        $batch_id = (int)($_POST['batch_id'] ?? 0);
        $type = 'Treatment';
        $medicine = trim($_POST['medicine'] ?? '');
        $qty = (float)($_POST['qty'] ?? 0);
        $number_of_chickens_treated = (int)($_POST['number_of_chickens_treated'] ?? 0);
        $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? '');
        $illness_observation = trim($_POST['illness_observation'] ?? '');
        $doctor_consultation = trim($_POST['doctor_consultation'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $status = trim($_POST['status'] ?? 'completed');

        if ($batch_id <= 0) {
            $error = "Batch/Flock selection is required.";
        } else {
            // Enforce broiler batch link
            $stmtCheck = $conn->prepare("SELECT batch_code, flock_type FROM batches WHERE batch_id = ?");
            $stmtCheck->execute([$batch_id]);
            $batchInfo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$batchInfo) {
                $error = "Selected batch does not exist.";
            } elseif (!empty($batchInfo['flock_type']) && strtolower($batchInfo['flock_type']) !== 'broiler') {
                $error = "Health records must be linked to an active broiler batch.";
            } elseif (empty($medicine)) {
                $error = "Medicine or treatment name is required.";
            } elseif ($qty <= 0) {
                $error = "Quantity used must be greater than 0.";
            } else {
                if (record_health_activity($conn, $batch_id, $type, $medicine, $qty, $date, $remarks, $illness_observation, $doctor_consultation, $diagnosis, $number_of_chickens_treated, $status)) {
                    $msg = "Health & medication record saved successfully!";
                } else {
                    $error = $_SESSION['last_health_error'] ?? "Error recording health activity. Please try again.";
                    unset($_SESSION['last_health_error']);
                }
            }
        }
    }
}

// Handle Treatment Status Change (Worker / Staff Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $health_id = (int)($_POST['health_id'] ?? 0);
    $new_status = strtolower(trim($_POST['status'] ?? ''));
    $valid_statuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    if ($health_id <= 0 || !in_array($new_status, $valid_statuses, true)) {
        $error = "Invalid health record or status selected.";
    } else {
        $stmtUp = $conn->prepare("UPDATE health_records SET status = ?, recorded_by = ? WHERE health_id = ?");
        if ($stmtUp->execute([$new_status, $user_id, $health_id])) {
            $msg = "Treatment status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . " successfully!";
        } else {
            $error = "Failed to update treatment status.";
        }
    }
}

// PRG Pattern: Redirect to prevent duplicate submission on refresh
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($msg)) {
        echo "<script>window.location.href = 'healthrecords.php?msg=" . urlencode($msg) . "';</script>";
        exit;
    } elseif (!empty($error)) {
        echo "<script>window.location.href = 'healthrecords.php?error=" . urlencode($error) . "';</script>";
        exit;
    }
}

// Filter parameters
$filterBatch = !empty($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$filterStatus = !empty($_GET['status']) ? trim($_GET['status']) : '';
$filterType = !empty($_GET['type']) ? trim($_GET['type']) : '';
$filterDateFrom = !empty($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filterDateTo = !empty($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = [];
$params = [];

if ($filterBatch > 0) {
    $where[] = "hr.batch_id = ?";
    $params[] = $filterBatch;
}
if (!empty($filterStatus)) {
    $where[] = "hr.status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterType)) {
    $where[] = "hr.type = ?";
    $params[] = strtolower($filterType);
}
if (!empty($filterDateFrom)) {
    $where[] = "hr.date >= ?";
    $params[] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $where[] = "hr.date <= ?";
    $params[] = $filterDateTo;
}

$sql = "SELECT hr.*, b.batch_code, b.flock_type 
        FROM health_records hr 
        JOIN batches b ON hr.batch_id = b.batch_id";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY hr.date DESC, hr.health_id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$healthRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalRecordsCount = count($healthRecords);
?>
<?php include('header.php'); ?>

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
    #healthRecordsTable_wrapper .dataTables_filter input,
    #healthRecordsTable_wrapper .dataTables_length select {
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

<div class="container-fluid px-2 px-md-3">
    <?php if (!empty($msg)): ?>
        <div class="alert alert-success shadow-sm py-2 small mb-3"><i class="fas fa-check-circle me-1"></i><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm py-2 small mb-3"><i class="fas fa-exclamation-circle me-1"></i><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;">
                        <i class="fas fa-notes-medical text-primary me-2"></i>Flock Health &amp; Treatment History
                    </h5>
                        <?php if ($isAdmin): ?>
                            <small class="text-muted">Review medication and disease observations recorded for active broiler batches.</small>
                        <?php else: ?>
                        <small class="text-muted">Add health records, or update treatment schedules assigned to your shift.</small>
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
                            <i class="fas fa-hand-pointer me-1"></i>Worker Action Mode
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-primary px-3 py-2 rounded-pill">
                        <?php echo number_format($totalRecordsCount); ?> Records
                    </span>
                </div>
                </div>

            <!-- Filter Toolbar -->
            <form method="GET" class="p-3 bg-light rounded-4 mb-4 border">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1">Batch</label>
                        <select name="batch_id" class="form-select form-select-sm">
                            <option value="">All Batches</option>
                            <?php
                            $filterBatches = $conn->query("SELECT batch_id, batch_code, house_pen FROM batches WHERE flock_type='broiler' OR flock_type IS NULL ORDER BY arrival_date DESC");
                            foreach($filterBatches as $fb) {
                                $sel = ($filterBatch === (int)$fb['batch_id']) ? 'selected' : '';
                                $hp = !empty($fb['house_pen']) ? ' - ' . e($fb['house_pen']) : '';
                                echo "<option value='{$fb['batch_id']}' {$sel}>" . e($fb['batch_code']) . $hp . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="scheduled" <?php echo ($filterStatus === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="in_progress" <?php echo ($filterStatus === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo ($filterStatus === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($filterStatus === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="treatment" <?php echo ($filterType === 'treatment') ? 'selected' : ''; ?>>Treatment</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1">From Date</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo e($filterDateFrom); ?>">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-bold text-muted mb-1">To Date</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo e($filterDateTo); ?>">
                    </div>
                    <div class="col-md-1 col-sm-12 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="healthrecords.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive custom-scrollbar">
                <table id="healthRecordsTable" class="table table-hover align-middle w-100 m-0 table-mobile-cards">
                    <thead class="table-light" style="border-bottom: 2px solid var(--border, #DDD6C4);">
                        <tr>
                            <th class="text-secondary fw-semibold text-uppercase small">Date / Batch</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Type</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Medicine / Qty</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Chickens Treated</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Illness &amp; Diagnosis</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Doctor Consultation</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Status</th>
                            <th class="text-secondary fw-semibold text-uppercase small text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php foreach($healthRecords as $row): ?>
                                <?php
                                $typeBadge = 'bg-danger-subtle text-danger border-danger-subtle';
                                $typeName = 'Treatment';

                                $rawStatus = strtolower($row['status'] ?? 'completed');
                                $statusBadge = 'bg-secondary text-white';
                                if ($rawStatus === 'completed') $statusBadge = 'bg-success text-white';
                                elseif ($rawStatus === 'in_progress') $statusBadge = 'bg-info text-dark';
                                elseif ($rawStatus === 'scheduled') $statusBadge = 'bg-warning text-dark';
                                elseif ($rawStatus === 'cancelled') $statusBadge = 'bg-danger text-white';

                                $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td data-label="Date / Batch">
                                        <div class="fw-bold"><?php echo date('M d, Y', strtotime($row['date'])); ?></div>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-0 small">
                                             <i class="fas fa-drumstick-bite me-1"></i><?php echo e($row['batch_code']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Type">
                                        <span class="badge rounded-pill <?php echo $typeBadge; ?> border px-2 py-1"><?php echo $typeName; ?></span>
                                    </td>
                                    <td data-label="Medicine / Qty">
                                        <div class="fw-bold text-dark"><?php echo e($row['medicine_name']); ?></div>
                                        <small class="text-muted"><i class="fas fa-prescription-bottle me-1 text-primary"></i><?php echo (float)$row['quantity_used']; ?> unit(s)</small>
                                    </td>
                                    <td data-label="Chickens Treated">
                                        <div class="fw-bold text-dark"><?php echo (int)$row['number_of_chickens_treated'] ?: '—'; ?></div>
                                    </td>
                                    <td data-label="Illness / Diagnosis">
                                        <?php if (!empty($row['diagnosis'])): ?>
                                            <div class="fw-semibold text-danger small"><i class="fas fa-stethoscope me-1"></i><?php echo e($row['diagnosis']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['illness_observation'])): ?>
                                            <div class="small text-muted"><i class="fas fa-eye me-1"></i><?php echo e($row['illness_observation']); ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($row['diagnosis']) && empty($row['illness_observation'])): ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Doctor">
                                        <?php if (!empty($row['doctor_consultation'])): ?>
                                            <div class="small fw-semibold text-primary"><i class="fas fa-user-md me-1"></i><?php echo e($row['doctor_consultation']); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge rounded-pill <?php echo $statusBadge; ?> px-2 py-1 text-uppercase small">
                                            <?php echo ucfirst(str_replace('_', ' ', $rawStatus)); ?>
                                        </span>
                                    </td>
                                    <td data-label="Action" class="text-center">
                                        <?php if (!$isAdmin): ?>
                                            <?php if ($rawStatus !== 'completed' && $rawStatus !== 'cancelled'): ?>
                                                <button type="button" class="btn btn-sm btn-warning text-dark rounded-pill fw-bold px-3 py-1 shadow-sm" onclick="openStatusModal(<?php echo $rowJson; ?>)" title="Perform action / update treatment status">
                                                    <i class="fas fa-check-double me-1"></i>Action
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" onclick="openStatusModal(<?php echo $rowJson; ?>)" title="Update treatment status">
                                                    <i class="fas fa-sync-alt me-1"></i>Update
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($canRecordHealth):
    $activeBatches = $conn->query("SELECT * FROM batches WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) ORDER BY arrival_date DESC, batch_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $inv_items = $conn->query("SELECT item_name, quantity, unit FROM inventory WHERE category IN ('medicine', 'vitamins', 'supply') AND is_active = 1 ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Sticky FAB: New Health Record -->
<button type="button"
        class="btn btn-primary record-activity-fab shadow-lg rounded-pill fw-bold d-flex align-items-center gap-2"
        data-bs-toggle="modal"
        data-bs-target="#newHealthRecordModal"
        title="New Health Record">
    <i class="fas fa-plus"></i>
    <span>New Health Record</span>
</button>

<!-- Modal: New Health Record -->
<div class="modal fade" id="newHealthRecordModal" tabindex="-1" aria-labelledby="newHealthRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-heartbeat fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="newHealthRecordModalLabel">New Health Record</h5>
                        <small class="text-muted">Record disease observation, consultation, medicine dosage and schedule</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="newHealthRecordForm">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Broiler Batch <span class="text-danger">*</span></label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Select Broiler Batch --</option>
                            <?php foreach ($activeBatches as $b):
                                $selected = ($filterBatch === (int)$b['batch_id']) ? 'selected' : '';
                                $hp = !empty($b['house_pen']) ? ' - ' . e($b['house_pen']) : '';
                                $breedStr = !empty($b['breed']) ? ' (Broiler - ' . e($b['breed']) . ')' : ' (Broiler - Cobb 500)';
                            ?>
                                <option value="<?php echo (int)$b['batch_id']; ?>" <?php echo $selected; ?>><?php echo e($b['batch_code']) . $hp . $breedStr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Record Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required readonly>
                                <option value="Treatment" selected>Treatment / Medication</option>
                            </select>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Treatment Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <?php if ($isAdmin): ?>
                                <option value="scheduled" selected>Scheduled (For Worker Action)</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed Directly</option>
                                <option value="cancelled">Cancelled</option>
                                <?php else: ?>
                                <option value="completed" selected>Completed (Log treatment done)</option>
                                <option value="in_progress">In Progress</option>
                                <option value="scheduled">Scheduled (Needs follow-up)</option>
                                <option value="cancelled">Cancelled</option>
                                <?php endif; ?>
                            </select>
            </div>
            </div>
            
                    <div class="mb-3">
                        <label class="form-label fw-bold">Medicine / Treatment / Vitamin <span class="text-danger">*</span></label>
                        <select name="medicine" class="form-select" required>
                            <option value="">-- Select from Inventory --</option>
                            <?php foreach ($inv_items as $inv):
                                $disabled = ($inv['quantity'] <= 0) ? 'disabled' : '';
                                $stockMsg = ($inv['quantity'] <= 0) ? ' [OUT OF STOCK]' : ' (Available: ' . number_format($inv['quantity'], 2) . ' ' . e($inv['unit']) . ')';
                            ?>
                                <option value="<?php echo e($inv['item_name']); ?>" <?php echo $disabled; ?>><?php echo e($inv['item_name']) . $stockMsg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Illness Observation</label>
                            <input type="text" name="illness_observation" class="form-control" placeholder="e.g. Sneezing, lethargy">
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control" placeholder="e.g. CRD / Mild Enteritis">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Company-Doctor Consultation</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-md text-primary"></i></span>
                            <input type="text" name="doctor_consultation" class="form-control" placeholder="e.g. Dr. A. Santos (Farm Vet)">
                        </div>
                        <small class="text-muted">Attending veterinary doctor or company medical consultant.</small>
                    </div>

                    <div class="row">
                        <div class="col-12 col-sm-4 mb-3">
                            <label class="form-label fw-bold">Quantity Used <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" inputmode="decimal" name="qty" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-12 col-sm-4 mb-3">
                            <label class="form-label fw-bold">Chickens Treated</label>
                            <input type="number" inputmode="numeric" name="number_of_chickens_treated" class="form-control" placeholder="e.g. 100">
                        </div>
                        <div class="col-12 col-sm-4 mb-3">
                            <label class="form-label fw-bold">Treatment Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Remarks / Notes</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Administration route, dosage remarks, flock response..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="save" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fas fa-save me-2"></i>Add Health Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Update Treatment Status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="health_id" id="m_health_id">

                <div class="modal-header bg-light border-0 py-3">
                    <h6 class="modal-title fw-bold mb-0" id="statusModalLabel">
                        <i class="fas fa-sync-alt me-1 text-primary"></i>Update Status
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="small text-muted mb-2">Item: <strong id="m_medicine_name" class="text-dark"></strong></p>
                    <label class="form-label fw-bold small">Treatment Status</label>
                    <select name="status" id="m_status_select" class="form-select" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#healthRecordsTable').DataTable({
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
                infoEmpty: 'No health records',
                zeroRecords: 'No matching health records found',
                emptyTable: 'No health records found.'
            }
        });
    }
});

function openStatusModal(record) {
    document.getElementById('m_health_id').value = record.health_id || '';
    document.getElementById('m_medicine_name').innerText = record.medicine_name || '';
    document.getElementById('m_status_select').value = (record.status || 'completed').toLowerCase();

    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}
</script>

<?php include('footer.php'); ?>
