<?php
include('database.php');
include('db_check.php');
include('header.php');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = strtolower(trim($_SESSION['role'] ?? 'worker'));

$msg = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;

// Hidden CSRF field for dynamic forms rendered inside PHP echo strings
$csrf_hidden = csrf_field();

// Handle Activity Saving (Admin / Staff / Worker — same Record Activity flow)
$canRecordActivity = in_array($user_role, ['admin', 'staff', 'worker'], true);
if (isset($_POST['save_activity'])) {
    if (!$canRecordActivity) {
        $error = "Access Denied. You do not have permission to record farm activities.";
    } else {
        $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
        $type = trim($_POST['type'] ?? 'General');
        $date = !empty($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? '');
        $qty = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : 0.0;
        $item_name = trim($_POST['item_name'] ?? '');
        $inventory_id = !empty($_POST['inventory_id']) ? (int)$_POST['inventory_id'] : null;
        $logged_in_user_id = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            if (empty($_POST['status']) || !in_array($_POST['status'], ['pending', 'completed'], true)) {
                throw new Exception("Please select an Initial Assignment Status for this task.");
            }
            $status = $_POST['status'];

            $conn->beginTransaction();
            $activity_id = null;

            if ($type === 'Feeding') {
                throw new Exception("Feed consumption must be recorded in Feed & Consumption Monitoring (feed.php). Farm Operations is dedicated to daily poultry flock management activities.");
            } elseif ($type === 'Cleaning') {
                throw new Exception("House cleaning and pen sanitation must be managed through Pen Cleaning Operations in House Management (reservations.php). Farm Operations is dedicated to daily flock management activities.");
            } elseif ($type === 'Health Inspection' || $type === 'Health') {
                throw new Exception("Health records and veterinary inspections must be recorded in Health Records (healthrecords.php). Farm Operations is dedicated to daily poultry flock activities.");
            } elseif ($type === 'Supply') {
                if ($inventory_id) {
                    $stmt = $conn->prepare("SELECT item_name, quantity, unit FROM inventory WHERE inventory_id = ?");
                    $stmt->execute([$inventory_id]);
                    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$inv) {
                        throw new Exception("Selected supply inventory item not found.");
                    }
                    if ($qty > (float)$inv['quantity']) {
                        throw new Exception("Insufficient supply inventory! Available: {$inv['quantity']} {$inv['unit']}, Requested: {$qty} {$inv['unit']}.");
                    }
                    $item_name = $inv['item_name'];
                    $stmt = $conn->prepare("UPDATE inventory SET previous_quantity = quantity, quantity = quantity - ? WHERE inventory_id = ?");
                    $stmt->execute([$qty, $inventory_id]);
                }
                if (empty($item_name)) {
                    $item_name = 'Farm Supply';
                }
                $stmt = $conn->prepare("
                    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at, status)
                    VALUES (?, ?, 'Supply', ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$batch_id, $logged_in_user_id, $item_name, $qty, $date, $remarks, $status]);
                $activity_id = (int)$conn->lastInsertId();
                $msg = "Supply usage recorded successfully!";
            } else {
                // Operational activities: Bedding & Litter, Ventilation, Water Sanitation, Equipment Maintenance, Biosecurity, Flock Uniformity, Lighting, Pest Control, General
                if (empty($item_name)) {
                    $item_name = $type;
                }
                $stmt = $conn->prepare("
                    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$batch_id, $logged_in_user_id, $type, $item_name, $qty, $date, $remarks, $status]);
                $activity_id = (int)$conn->lastInsertId();
                $msg = "$type recorded successfully!";
            }

            // Record the activity in audit_logs
            if (!empty($activity_id)) {
                $stmtAudit = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
                    VALUES (?, 'create', 'activities', ?, ?, ?, NOW())
                ");
                $stmtAudit->execute([
                    $logged_in_user_id,
                    $activity_id,
                    json_encode([
                        'batch_id' => $batch_id,
                        'user_id' => $logged_in_user_id,
                        'type' => $type,
                        'item_name' => $item_name,
                        'quantity' => $qty,
                        'date' => $date,
                        'remarks' => $remarks,
                        'status' => $status
                    ]),
                    $ip
                ]);
            }

            $conn->commit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
// Handle Activity Completion (Staff / Worker Action)
if (isset($_POST['complete_activity'])) {
    $act_id = (int)$_POST['activity_id'];
    try {
        $stmt = $conn->prepare("UPDATE activities SET status = 'completed', user_id = ? WHERE activity_id = ?");
        $stmt->execute([$user_id, $act_id]);
        $msg = "Action recorded and marked as completed successfully!";
    } catch (PDOException $e) {
        $error = "Error updating activity: " . $e->getMessage();
    }
}

// PRG Pattern: Redirect to prevent duplicate submission on refresh
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($msg)) {
        echo "<script>window.location.href = 'activities.php?msg=" . urlencode($msg) . "';</script>";
        exit;
    } elseif (!empty($error)) {
        echo "<script>window.location.href = 'activities.php?error=" . urlencode($error) . "';</script>";
        exit;
    }
}
?>

<style>
    .nav-pills .nav-link {
        color: #2c3e50;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .nav-pills .nav-link.active {
        background-color: #2c3e50;
        color: white;
        box-shadow: 0 4px 12px rgba(44, 62, 80, 0.2);
    }
    .nav-pills .nav-link:not(.active):hover {
        background-color: rgba(44, 62, 80, 0.05);
        color: #1a252f;
    }
    body[data-theme="neon-farm"] .nav-pills .nav-link {
        color: #c8e6d4 !important;
        background: transparent !important;
        border: 1px solid rgba(61, 255, 154, 0.18);
    }
    body[data-theme="neon-farm"] .nav-pills .nav-link.active {
        background: rgba(61, 255, 154, 0.16) !important;
        color: #3dff9a !important;
        border-color: rgba(61, 255, 154, 0.45) !important;
        box-shadow: 0 0 18px rgba(61, 255, 154, 0.18);
    }
    body[data-theme="neon-farm"] .nav-pills .nav-link:not(.active):hover {
        background: rgba(61, 255, 154, 0.08) !important;
        color: #eef7f1 !important;
    }
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
    #activitiesTable_wrapper .dataTables_filter input,
    #activitiesTable_wrapper .dataTables_length select {
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
        <div class="alert alert-success shadow-sm py-2 small mb-3"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm py-2 small mb-3"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;">
                        <i class="fas fa-tasks text-success me-2"></i>Farm Operations &amp; Tasks
                    </h5>
                    <?php if ($user_role === 'admin'): ?>
                    <small class="text-muted">Assign operations to workers and monitor completion in real-time.</small>
                    <?php else: ?>
                    <small class="text-muted">Record farm operations, or tap <strong>Complete Action</strong> on assigned pending tasks.</small>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($user_role === 'admin'): ?>
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
                </div>
            </div>
            <div class="table-responsive custom-scrollbar">
                <table id="activitiesTable" class="table table-hover align-middle w-100 m-0 table-mobile-cards">
                    <thead class="table-light" style="border-bottom: 2px solid var(--border, #DDD6C4);">
                        <tr>
                            <th class="text-secondary fw-semibold text-uppercase small">Date / Time</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Worker / Source</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Batch</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Activity Type</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Activity / Item</th>
                            <th class="text-secondary fw-semibold text-uppercase small text-center">Status / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $log_query = "
                            SELECT 
                                a.activity_id,
                                a.batch_id,
                                a.user_id,
                                a.type,
                                a.item_name,
                                a.quantity,
                                a.date,
                                a.remarks,
                                a.created_at,
                                a.status,
                                COALESCE(b.batch_code, 'General') AS batch_code,
                                COALESCE(u.full_name, u.username, 'System / Unassigned') AS worker_name,
                                u.role AS worker_role
                            FROM activities a
                            LEFT JOIN batches b ON a.batch_id = b.batch_id
                            LEFT JOIN users u ON a.user_id = u.user_id
                            WHERE a.type NOT IN ('Cleaning', 'Feeding')
                            ORDER BY a.created_at DESC, a.activity_id DESC
                        ";
                        $log = $conn->query($log_query);
                        while($row = $log->fetch(PDO::FETCH_ASSOC)) {
                                        $timestampFormatted = !empty($row['created_at']) 
                                            ? date('M d, Y h:i A', strtotime($row['created_at'])) 
                                            : date('M d, Y', strtotime($row['date']));
                                        $operationalDate = date('M d, Y', strtotime($row['date']));

                                        $typeColor = match($row['type']) {
                                            'Bedding & Litter Management' => 'bg-warning-subtle text-dark border border-warning',
                                            'Ventilation & Climate Control' => 'bg-info-subtle text-dark border border-info',
                                            'Water Sanitation & Line Flushing' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                            'Equipment Maintenance' => 'bg-secondary-subtle text-dark border border-secondary',
                                            'Biosecurity & Footbath Sanitation' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                            'Flock Uniformity & Weighing' => 'bg-success-subtle text-success border border-success-subtle',
                                            'Lighting & Photoperiod Control' => 'bg-warning-subtle text-dark border border-warning',
                                            'Pest & Rodent Control' => 'bg-dark-subtle text-dark border border-dark-subtle',
                                            'Supply' => 'bg-info-subtle text-info border border-info-subtle',
                                            default => 'bg-light text-secondary border border-secondary-subtle'
                                        };

                                        $icon = match($row['type']) {
                                            'Bedding & Litter Management' => 'fa-feather',
                                            'Ventilation & Climate Control' => 'fa-fan',
                                            'Water Sanitation & Line Flushing' => 'fa-faucet-drip',
                                            'Equipment Maintenance' => 'fa-wrench',
                                            'Biosecurity & Footbath Sanitation' => 'fa-shield-virus',
                                            'Flock Uniformity & Weighing' => 'fa-scale-balanced',
                                            'Lighting & Photoperiod Control' => 'fa-lightbulb',
                                            'Pest & Rodent Control' => 'fa-bug',
                                            'Supply' => 'fa-box',
                                            default => 'fa-clipboard-check'
                                        };

                                        $roleBadge = '';
                                        if (!empty($row['worker_role'])) {
                                            $roleBadgeClass = ($row['worker_role'] === 'admin') ? 'bg-dark text-white' : 'bg-light text-dark border';
                                            $roleBadge = "<span class='badge {$roleBadgeClass} mt-1 d-block w-75 text-truncate' style='font-size: 0.65rem;'>" . ucfirst(e($row['worker_role'])) . "</span>";
                                        }

                                        $detailParts = [];
                                        if (!empty($row['item_name'])) {
                                            $itemStr = "<div class='fw-bold text-dark mb-1'>" . e($row['item_name']) . "</div>";
                                            if ($row['quantity'] !== null && (float)$row['quantity'] > 0) {
                                                $itemStr .= "<div class='small text-muted'><i class='fas fa-balance-scale me-1'></i>Qty: " . number_format((float)$row['quantity'], 2) . "</div>";
                                            }
                                            $detailParts[] = $itemStr;
                                        }
                                        if (!empty($row['remarks'])) {
                                            $detailParts[] = "<div class='small text-muted mt-1 fst-italic' style='white-space: normal;'>\"" . e($row['remarks']) . "\"</div>";
                                        }
                                        $detailsHtml = !empty($detailParts) ? implode("", $detailParts) : "<span class='text-muted fst-italic'>General Activity</span>";

                                        $statusHtml = '';
                                        if ($row['status'] === 'pending') {
                                            if ($user_role !== 'admin') {
                                                $statusHtml = "<form method='POST' class='m-0 d-flex justify-content-center'>
                                                    {$csrf_hidden}
                                                    <input type='hidden' name='activity_id' value='{$row['activity_id']}'>
                                                    <button type='submit' name='complete_activity' class='btn btn-sm btn-warning text-dark rounded-pill fw-bold px-3 py-1 shadow-sm' title='Mark this task as performed in the pen'>
                                                        <i class='fas fa-check-double me-1'></i>Complete Action
                                                    </button>
                                                </form>";
                                            } else {
                                                $statusHtml = "<div class='text-center'>
                                                    <span class='badge bg-warning-subtle text-warning border border-warning px-3 py-1 rounded-pill'>
                                                        <i class='far fa-clock me-1'></i>Pending Action
                                                    </span>
                                                </div>";
                                            }
                                        } else {
                                            $statusHtml = "<div class='text-center'>
                                                <span class='badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill'>
                                                    <i class='fas fa-check-circle me-1'></i>Completed
                                                </span>
                                            </div>";
                                        }

                            echo "<tr style='border-bottom: 1px solid rgba(0,0,0,0.05);'>
                                <td data-label='Date / Time' class='align-top pt-3'>
                                    <div class='fw-bold text-dark small mb-1'>{$timestampFormatted}</div>
                                    <div class='text-muted' style='font-size: 0.75rem;'>Op: {$operationalDate}</div>
                                </td>
                                <td data-label='Worker' class='align-top pt-3'>
                                    <div class='fw-bold text-dark text-truncate' style='max-width: 140px;'>" . e($row['worker_name']) . "</div>
                                    {$roleBadge}
                                </td>
                                <td data-label='Batch' class='align-top pt-3 fw-bold' style='color: var(--primary, #2E5B43);'>" . e($row['batch_code']) . "</td>
                                <td data-label='Activity Type' class='align-top pt-3'>
                                    <span class='badge {$typeColor} px-2 py-1 rounded-pill fw-medium'><i class='fas {$icon} me-1'></i>" . e($row['type']) . "</span>
                                </td>
                                <td data-label='Details' class='align-top pt-3 text-wrap'>
                                    {$detailsHtml}
                                </td>
                                <td data-label='Status / Action' class='align-middle'>
                                    {$statusHtml}
                                </td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($canRecordActivity):
    $activeBatches = $conn->query("SELECT * FROM batches WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) ORDER BY arrival_date DESC, batch_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Sticky FAB: Record Activity -->
<button type="button"
        class="btn btn-primary record-activity-fab shadow-lg rounded-pill fw-bold d-flex align-items-center gap-2"
        data-bs-toggle="modal"
        data-bs-target="#recordActivityModal"
        title="Record Activity">
    <i class="fas fa-plus"></i>
    <span>Record Activity</span>
</button>

<!-- Modal: Record Activity -->
<div class="modal fade" id="recordActivityModal" tabindex="-1" aria-labelledby="recordActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-edit fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="recordActivityModalLabel">Record Activity</h5>
                        <small class="text-muted">
                            <?php echo $user_role === 'admin'
                                ? 'Assign farm operations &amp; tasks to workers'
                                : 'Log farm operations performed in the pens'; ?>
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="recordActivityForm">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Broiler Batch <span class="text-danger">*</span></label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Choose Broiler Batch --</option>
                            <?php foreach ($activeBatches as $b):
                                $hp = !empty($b['house_pen']) ? ' - ' . e($b['house_pen']) : '';
                                $breedStr = !empty($b['breed']) ? ' (Broiler - ' . e($b['breed']) . ')' : ' (Broiler - Cobb 500)';
                            ?>
                                <option value="<?php echo (int)$b['batch_id']; ?>"><?php echo e($b['batch_code']) . $hp . $breedStr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Predefined Task / Activity Type</label>
                        <select name="type" id="activityType" class="form-select" onchange="toggleFields()" required>
                            <option value="">-- Select Task --</option>
                            <option value="Bedding & Litter Management">Bedding & Litter Management</option>
                            <option value="Ventilation & Climate Control">Ventilation & Climate Control</option>
                            <option value="Water Sanitation & Line Flushing">Water Sanitation & Line Flushing</option>
                            <option value="Equipment Maintenance">Equipment Maintenance</option>
                            <option value="Biosecurity & Footbath Sanitation">Biosecurity & Footbath Sanitation</option>
                            <option value="Lighting & Photoperiod Control">Lighting & Photoperiod Control</option>
                            <option value="Pest & Rodent Control">Pest & Rodent Control</option>
                            <option value="Supply">Supply (Inventory Consumables)</option>
                            <option value="General">General Farm Maintenance</option>
                        </select>
                    </div>

                    <div id="itemField" class="mb-3">
                        <label class="form-label fw-bold" id="itemLabel">Item Name</label>
                        <div id="inventorySelectContainer">
                            <input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter name">
                        </div>
                    </div>

                    <div id="qtyField" class="mb-3">
                        <label class="form-label fw-bold" id="qtyLabel">Quantity</label>
                        <input type="number" step="0.01" name="quantity" id="qtyInput" class="form-control" placeholder="e.g. 5" inputmode="decimal">
                        <small id="qtyHelp" class="text-muted d-none">Enter the number of broilers lost. No need for negative sign.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Initial Assignment Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="" selected disabled>-- Select Assignment Status --</option>
                            <?php if ($user_role === 'admin'): ?>
                            <option value="pending">Pending (Requires Staff / Worker Action)</option>
                            <option value="completed">Completed (Direct Admin Log)</option>
                            <?php else: ?>
                            <option value="completed" selected>Completed (Log work done)</option>
                            <option value="pending">Pending (Needs follow-up)</option>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <?php echo $user_role === 'admin'
                                ? 'Select "Pending" to assign this task to farm workers.'
                                : 'Use Completed when logging work you already finished in the pens.'; ?>
                        </small>
                    </div>

                    <div id="remarksField" class="mb-3">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="save_activity" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fas fa-save me-2"></i><?php echo $user_role === 'admin' ? 'Save Activity / Assign Task' : 'Save Activity'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#activitiesTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: {
                search: 'Search activities:',
                lengthMenu: 'Show _MENU_ activities',
                info: 'Showing _START_ to _END_ of _TOTAL_ activities',
                infoEmpty: 'No activities recorded',
                zeroRecords: 'No matching activity records found',
                emptyTable: 'No activity records found.'
            }
        });
    }
});

const operationalPresets = {
    'Bedding & Litter Management': {
        label: 'Litter Operation',
        qtyLabel: 'Bags / Depth / Value (Optional)',
        placeholder: 'e.g., Rice hull addition, Raking, Conditioning',
        options: [
            'Fresh rice hull addition / top-dressing',
            'Litter raking & fluffing (crust breaking)',
            'Wet cake & caked litter spot removal',
            'Hydrated lime floor conditioning',
            'Litter moisture & depth measurement'
        ]
    },
    'Ventilation & Climate Control': {
        label: 'Climate & Airflow Routine',
        qtyLabel: 'Airspeed (m/s) or Temp (°C) (Optional)',
        placeholder: 'e.g., Curtain adjustment, Fan inspection',
        options: [
            'Curtain drop & natural airflow adjustment',
            'Tunnel exhaust fan inspection & belt check',
            'Evaporative cooling pad flushing & cleaning',
            'Temperature & humidity sensor calibration',
            'Air speed & static pressure check'
        ]
    },
    'Water Sanitation & Line Flushing': {
        label: 'Drinker Line Operation',
        qtyLabel: 'Dosage / Liters (Optional)',
        placeholder: 'e.g., Line flush, Nipple drinker test',
        options: [
            'High-pressure drinker line flushing',
            'Nipple drinker flow rate test & unblocking',
            'Water sanitizer / chlorine acidifier dosing',
            'Sediment water filter replacement',
            'Header tank inspection & cleaning'
        ]
    },
    'Equipment Maintenance': {
        label: 'Equipment Routine',
        qtyLabel: 'Count / Units (Optional)',
        placeholder: 'e.g., Feeder motor servicing, Generator run',
        options: [
            'Feeder motor & pan auger lubrication',
            'Brooder gas heater inspection & ignition test',
            'Backup diesel generator weekly test run',
            'Emergency power / alarm siren test',
            'Bulk feed silo hopper & boot inspection'
        ]
    },
    'Biosecurity & Footbath Sanitation': {
        label: 'Biosecurity Routine',
        qtyLabel: 'Disinfectant (Liters) (Optional)',
        placeholder: 'e.g., Footbath change, Perimeter check',
        options: [
            'Pen entrance footbath disinfectant renewal',
            'Perimeter bird netting & mesh fence inspection',
            'Vehicle wheel spray / dip tank chemical replenishment',
            'Farm anteroom PPE & boot wash sanitation'
        ]
    },
    'Lighting & Photoperiod Control': {
        label: 'Lighting Routine',
        qtyLabel: 'Lux / Hours (Optional)',
        placeholder: 'e.g., Timer schedule adjustment, Lux check',
        options: [
            'Photoperiod timer schedule adjustment',
            'Light intensity lux meter calibration',
            'LED bulb wiping & dust removal',
            'Dark period observation & bird rest check'
        ]
    },
    'Pest & Rodent Control': {
        label: 'Pest Control Routine',
        qtyLabel: 'Traps / Stations Checked (Optional)',
        placeholder: 'e.g., Bait station check, Fly trap replacement',
        options: [
            'Perimeter tamper-resistant rodent bait station check',
            'Fly ribbon & sticky trap replacement',
            'Exterior wall insecticide barrier spraying',
            'Wild bird entry deterrent inspection'
        ]
    }
};

function toggleFields() {
    var actTypeSelect = document.getElementById('activityType');
    if (!actTypeSelect) return;
    var type = actTypeSelect.value;
    var itemField = document.getElementById('itemField');
    var qtyField = document.getElementById('qtyField');
    var itemLabel = document.getElementById('itemLabel');
    var qtyLabel = document.getElementById('qtyLabel');
    var qtyHelp = document.getElementById('qtyHelp');
    var remarksField = document.getElementById('remarksField');
    var inventorySelectContainer = document.getElementById('inventorySelectContainer');
    var qtyInput = document.getElementById('qtyInput');

    itemField.style.display = 'block';
    qtyField.style.display = 'block';
    remarksField.style.display = 'block';
    if (qtyHelp) qtyHelp.classList.add('d-none');

    if (operationalPresets[type]) {
        var preset = operationalPresets[type];
        if (itemLabel) itemLabel.innerHTML = preset.label;
        if (qtyLabel) qtyLabel.innerHTML = preset.qtyLabel;

        let html = '<select name="item_name" id="itemNameInput" class="form-select" required>';
        html += '<option value="">-- Select Specific ' + type + ' Task --</option>';
        preset.options.forEach(opt => {
            html += '<option value="' + opt + '">' + opt + '</option>';
        });
        html += '</select>';
        inventorySelectContainer.innerHTML = html;
        qtyInput.removeAttribute('required');
        qtyInput.value = '';
    } else if (type === 'Supply') {
        qtyInput.setAttribute('required', '');
        if (itemLabel) itemLabel.innerHTML = 'Supply Item (Inventory Consumable)';
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity Used <span class="text-danger">*</span>';

        fetch('get_inventory_items.php?type=' + encodeURIComponent(type))
            .then(response => response.json())
            .then(data => {
                let html = '<select name="inventory_id" id="itemNameInput" class="form-select" required>';
                html += '<option value="">-- Select Consumable Item --</option>';
                data.forEach(item => {
                    html += `<option value="${item.inventory_id}">${item.item_name} (${item.quantity} ${item.unit} available)</option>`;
                });
                html += '</select>';
                inventorySelectContainer.innerHTML = html;
            })
            .catch(error => {
                inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter supply item name" required>';
            });
    } else {
        // General or default
        if (itemLabel) itemLabel.innerHTML = 'Task / Operation Name';
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity / Value (Optional)';
        inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="e.g., General perimeter inspection, Facility maintenance" required>';
        qtyInput.removeAttribute('required');
        qtyInput.value = '';
    }
}
</script>

<?php include('footer.php'); ?>