<?php
include('database.php');
include('database.php');

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Hidden CSRF field for dynamic forms rendered inside PHP echo strings
$csrf_hidden = csrf_field();

// Handle Activity Saving
if (isset($_POST['save_activity'])) {
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
        $conn->beginTransaction();
        $activity_id = null;

        if ($type === 'Feeding') {
            if ($inventory_id) {
                $stmt = $conn->prepare("SELECT item_name, quantity, unit FROM inventory WHERE inventory_id = ?");
                $stmt->execute([$inventory_id]);
                $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$inv) {
                    throw new Exception("Selected feed inventory item not found.");
                }
                if ($qty > (float)$inv['quantity']) {
                    throw new Exception("Insufficient feed inventory! Available: {$inv['quantity']} {$inv['unit']}, Requested: {$qty} {$inv['unit']}.");
                }
                $item_name = $inv['item_name'];

                // 1. Record in activities
                $stmt = $conn->prepare("
                    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
                    VALUES (?, ?, 'Feeding', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$batch_id, $logged_in_user_id, $item_name, $qty, $date, $remarks]);
                $activity_id = (int)$conn->lastInsertId();

                // 2. Safely deduct inventory
                $stmt = $conn->prepare("UPDATE inventory SET previous_quantity = quantity, quantity = quantity - ? WHERE inventory_id = ?");
                $stmt->execute([$qty, $inventory_id]);

                // 3. Record in feed_consumption for unified reports
                $stmt = $conn->prepare("
                    INSERT INTO feed_consumption (batch_id, inventory_id, quantity_used, date, recorded_by, remarks, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$batch_id, $inventory_id, $qty, $date, $logged_in_user_id, $remarks]);
            } else {
                if (empty($item_name)) {
                    $item_name = 'Broiler Feed';
                }
                $stmt = $conn->prepare("
                    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
                    VALUES (?, ?, 'Feeding', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$batch_id, $logged_in_user_id, $item_name, $qty, $date, $remarks]);
                $activity_id = (int)$conn->lastInsertId();
            }
            $msg = "Feeding task recorded successfully!";
        } elseif ($type === 'Cleaning') {
            if (empty($item_name)) {
                $item_name = 'Facility Cleaning';
            }
            // 1. Record in activities
            $stmt = $conn->prepare("
                INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
                VALUES (?, ?, 'Cleaning', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$batch_id, $logged_in_user_id, $item_name, $qty, $date, $remarks]);
            $activity_id = (int)$conn->lastInsertId();

            // 2. Insert into cleaning_records for legacy compatibility
            $stmt = $conn->prepare("INSERT INTO cleaning_records (batch_id, date, remarks) VALUES (?, ?, ?)");
            $stmt->execute([$batch_id, $date, $remarks]);

            $msg = "Cleaning task recorded successfully!";
        } elseif ($type === 'Health Inspection') {
            if ($inventory_id) {
                $stmt = $conn->prepare("SELECT item_name, quantity, unit FROM inventory WHERE inventory_id = ?");
                $stmt->execute([$inventory_id]);
                $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($inv) {
                    if ($qty > 0 && $qty > (float)$inv['quantity']) {
                        throw new Exception("Insufficient inventory stock! Available: {$inv['quantity']} {$inv['unit']}, Requested: {$qty} {$inv['unit']}.");
                    }
                    $item_name = $inv['item_name'];
                    if ($qty > 0) {
                        $stmt = $conn->prepare("UPDATE inventory SET previous_quantity = quantity, quantity = quantity - ? WHERE inventory_id = ?");
                        $stmt->execute([$qty, $inventory_id]);
                    }
                }
            }
            if (empty($item_name)) {
                $item_name = 'Health Inspection';
            }
            $stmt = $conn->prepare("
                INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
                VALUES (?, ?, 'Health Inspection', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$batch_id, $logged_in_user_id, $item_name, $qty, $date, $remarks]);
            $activity_id = (int)$conn->lastInsertId();
            $msg = "Health inspection recorded successfully!";
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
                INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
                VALUES (?, ?, 'Supply', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$batch_id, $logged_in_user_id, $item_name, $qty, $date, $remarks]);
            $activity_id = (int)$conn->lastInsertId();
            $msg = "Supply usage recorded successfully!";
        } elseif ($type === 'General') {
            if (empty($item_name)) {
                $item_name = 'General Farm Task';
            }
            $stmt = $conn->prepare("
                INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
                VALUES (?, ?, 'General', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$batch_id, $logged_in_user_id, $item_name, $qty, $date, $remarks]);
            $activity_id = (int)$conn->lastInsertId();
            $msg = "General farm operation recorded successfully!";
        } else {
            // Fallback for legacy types (e.g. Health, Mortality)
            if ($type === 'Mortality') {
                $negQty = abs($qty) * -1;
                $reason = !empty($item_name) ? $item_name : 'Flock Loss';
                record_mortality($conn, $batch_id, $negQty, $reason, $date, $logged_in_user_id);
            }
            $stmt = $conn->prepare("
                INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$batch_id, $logged_in_user_id, $type, $item_name, $qty, $date, $remarks]);
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
                    'remarks' => $remarks
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


// Handle Task Creation (Admin Only)
if (isset($_POST['create_task']) && $user_role == 'admin') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $assigned_to = $_POST['assigned_to'];
    $batch_id = !empty($_POST['task_batch_id']) ? $_POST['task_batch_id'] : NULL;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
    $task_type = $_POST['task_type'];
    $created_by = $user_id;

    try {
        $stmt = $conn->prepare("INSERT INTO tasks (title, description, assigned_to, created_by, batch_id, due_date, task_type, last_reset_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        // If daily, set last_reset_date to today to start it fresh
        $last_reset = ($task_type == 'daily') ? date('Y-m-d') : NULL;
        $stmt->execute([$title, $description, $assigned_to, $created_by, $batch_id, $due_date, $task_type, $last_reset]);
        $task_msg = "Task created and assigned successfully!";
    } catch (PDOException $e) {
        $task_error = "Error creating task: " . $e->getMessage();
    }
}

// Handle Task Status Update
if (isset($_POST['update_task_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];

    try {
        // If worker is updating, check if task is already completed
        if ($user_role == 'worker') {
            $stmt = $conn->prepare("SELECT status FROM tasks WHERE task_id = ?");
            $stmt->execute([$task_id]);
            $current_status = $stmt->fetchColumn();
            
            if ($current_status == 'completed') {
                $task_error = "Completed tasks cannot be changed.";
            } else {
                $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE task_id = ?");
                $stmt->execute([$new_status, $task_id]);
                $task_msg = "Task marked as completed!";
            }
        } else {
            // Admin can change status freely
            $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE task_id = ?");
            $stmt->execute([$new_status, $task_id]);
            $task_msg = "Task status updated!";
        }
    } catch (PDOException $e) {
        $task_error = "Error updating status: " . $e->getMessage();
    }
}

// Handle Task Deletion (Admin Only, POST with CSRF verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task']) && $user_role == 'admin') {
    try {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $stmt = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $task_msg = "Task deleted successfully!";
        }
    } catch (PDOException $e) {
        $task_error = "Error deleting task: " . $e->getMessage();
    }
}
?>

<style>
    /* Tab Styling Improvements */
    .nav-pills .nav-link {
        color: #2c3e50 !important;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .nav-pills .nav-link.active {
        background-color: #2c3e50 !important;
        color: white !important;
        box-shadow: 0 4px 12px rgba(44, 62, 80, 0.2);
    }
    .nav-pills .nav-link:not(.active):hover {
        background-color: rgba(44, 62, 80, 0.05);
        color: #1a252f !important;
    }
</style>

<div class="container-fluid">
    <!-- Tab Navigation -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-4 shadow-sm d-inline-flex flex-wrap gap-1" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-3 px-sm-4" id="pills-activities-tab" data-bs-toggle="pill" data-bs-target="#pills-activities" type="button" role="tab"><i class="fas fa-tasks me-2"></i>Activities</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-3 px-sm-4" id="pills-tasks-tab" data-bs-toggle="pill" data-bs-target="#pills-tasks" type="button" role="tab"><i class="fas fa-clipboard-list me-2"></i>Tasks</button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <!-- Activities Tab -->
        <div class="tab-pane fade show active" id="pills-activities" role="tabpanel">
            <div class="row g-4 align-items-stretch">
                <div class="col-md-4">
                    <div class="card form-container h-100">
                        <div class="card-body d-flex flex-column">
                            <h3 class="section-title"><i class="fas fa-edit text-primary"></i>Record Activity</h3>
                        
                        <?php if(isset($msg)) echo "<div class='alert alert-success shadow-sm'>$msg</div>"; ?>
                        <?php if(isset($error)) echo "<div class='alert alert-danger shadow-sm'>$error</div>"; ?>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Batch</label>
                                <select name="batch_id" class="form-select" required>
                                    <option value="">-- Choose Batch --</option>
                                    <?php
                                    $batches = $conn->query("SELECT * FROM batches WHERE status='active'");
                                    foreach($batches as $b) {
                                        echo "<option value='{$b['batch_id']}'>{$b['batch_code']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Predefined Task / Activity Type</label>
                                <select name="type" id="activityType" class="form-select" onchange="toggleFields()" required>
                                    <option value="">-- Select Task --</option>
                                    <option value="Feeding">Feeding</option>
                                    <option value="Cleaning">Cleaning</option>
                                    <option value="Health Inspection">Health Inspection</option>
                                    <option value="Supply">Supply</option>
                                    <option value="General">General</option>
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
                                <input type="number" step="0.01" name="quantity" id="qtyInput" class="form-control" placeholder="e.g. 5">
                                <small id="qtyHelp" class="text-muted d-none">Enter the number of broilers lost. No need for negative sign.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div id="remarksField" class="mb-3">
                                <label class="form-label fw-bold">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>

                            <div class="form-actions-sticky mt-auto d-grid">
                                <button type="submit" name="save_activity" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Save Activity
                                </button>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card h-100 shadow-sm border-0 rounded-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                                <div>
                                    <h3 class="section-title mb-0"><i class="fas fa-history"></i>Farm Operations & Activity History</h3>
                                    <p class="text-muted small mb-0">Worker audit trail with recorded timestamp, identity, and task details.</p>
                                </div>
                                <span class="badge bg-light text-dark border px-3 py-2">
                                    <i class="fas fa-shield-alt text-success me-1"></i>Audit Trail Active
                                </span>
                            </div>
                            <div class="table-responsive custom-scrollbar">
                                <table class="table table-hover align-middle">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Worker</th>
                                        <th>Batch</th>
                                        <th>Task Type</th>
                                        <th>Details & Notes</th>
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
                                            COALESCE(b.batch_code, 'General') AS batch_code,
                                            COALESCE(u.full_name, u.username, 'System / Unassigned') AS worker_name,
                                            u.role AS worker_role
                                        FROM activities a
                                        LEFT JOIN batches b ON a.batch_id = b.batch_id
                                        LEFT JOIN users u ON a.user_id = u.user_id
                                        ORDER BY a.created_at DESC, a.activity_id DESC
                                        LIMIT 50
                                    ";
                                    $log = $conn->query($log_query);
                                    $hasRows = false;
                                    while($row = $log->fetch(PDO::FETCH_ASSOC)) {
                                        $hasRows = true;
                                        $timestampFormatted = !empty($row['created_at']) 
                                            ? date('M d, Y h:i A', strtotime($row['created_at'])) 
                                            : date('M d, Y', strtotime($row['date']));
                                        $operationalDate = date('M d, Y', strtotime($row['date']));

                                        $typeColor = 'bg-secondary text-white';
                                        $icon = 'fa-tasks';
                                        if ($row['type'] == 'Feeding') {
                                            $typeColor = 'bg-warning text-dark';
                                            $icon = 'fa-utensils';
                                        } elseif ($row['type'] == 'Cleaning') {
                                            $typeColor = 'bg-info text-dark';
                                            $icon = 'fa-broom';
                                        } elseif ($row['type'] == 'Health Inspection' || $row['type'] == 'Health') {
                                            $typeColor = 'bg-danger text-white';
                                            $icon = 'fa-stethoscope';
                                        } elseif ($row['type'] == 'Supply') {
                                            $typeColor = 'bg-success text-white';
                                            $icon = 'fa-box';
                                        } elseif ($row['type'] == 'General') {
                                            $typeColor = 'bg-primary text-white';
                                            $icon = 'fa-clipboard-check';
                                        }

                                        $roleBadge = '';
                                        if (!empty($row['worker_role'])) {
                                            $roleBadgeClass = ($row['worker_role'] === 'admin') ? 'bg-dark text-white' : 'bg-light text-dark border';
                                            $roleBadge = "<span class='badge {$roleBadgeClass} ms-1' style='font-size: 0.72rem;'>" . ucfirst(e($row['worker_role'])) . "</span>";
                                        }

                                        $detailParts = [];
                                        if (!empty($row['item_name'])) {
                                            $itemStr = "<strong>" . e($row['item_name']) . "</strong>";
                                            if ($row['quantity'] !== null && (float)$row['quantity'] > 0) {
                                                $itemStr .= " (" . number_format((float)$row['quantity'], 2) . ")";
                                            }
                                            $detailParts[] = $itemStr;
                                        }
                                        if (!empty($row['remarks'])) {
                                            $detailParts[] = "<span class='text-muted small'>" . e($row['remarks']) . "</span>";
                                        }
                                        $detailsHtml = !empty($detailParts) ? implode(" — ", $detailParts) : "<span class='text-muted fst-italic'>Completed</span>";

                                        echo "<tr>
                                            <td>
                                                <div class='fw-semibold text-dark small'><i class='far fa-clock text-muted me-1'></i>{$timestampFormatted}</div>
                                                <div class='text-muted' style='font-size: 0.75rem;'>Op Date: {$operationalDate}</div>
                                            </td>
                                            <td>
                                                <div class='fw-semibold d-inline-block'>" . e($row['worker_name']) . "</div>
                                                {$roleBadge}
                                            </td>
                                            <td class='fw-bold text-primary'>" . e($row['batch_code']) . "</td>
                                            <td><span class='badge {$typeColor}'><i class='fas {$icon} me-1'></i>" . e($row['type']) . "</span></td>
                                            <td>{$detailsHtml}</td>
                                        </tr>";
                                    }
                                    if (!$hasRows) {
                                        echo "<tr><td colspan='5' class='text-center text-muted py-4'>No activity records found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks Tab -->
        <div class="tab-pane fade" id="pills-tasks" role="tabpanel">
            <div class="row g-4 align-items-stretch">
                <!-- Task Creation Form (Admins) OR Worker Summary (Staff) -->
                <div class="col-md-4">
                    <?php if ($user_role == 'admin'): ?>
                    <div class="card p-4 shadow-sm border-0 rounded-4 h-100">
                        <h3 class="section-title mb-4"><i class="fas fa-plus-circle me-2"></i>Create New Task</h3>
                        
                        <?php if(isset($task_msg)) echo "<div class='alert alert-success shadow-sm'>$task_msg</div>"; ?>
                        <?php if(isset($task_error)) echo "<div class='alert alert-danger shadow-sm'>$task_error</div>"; ?>

                        <form method="POST" action="activities.php?tab=tasks">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                    <label class="form-label fw-bold">Task Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Clean Batch A Coop" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Describe the task..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Batch (Optional)</label>
                    <select name="task_batch_id" class="form-select">
                        <option value="">-- No Specific Batch --</option>
                        <?php
                        $batches_task = $conn->query("SELECT * FROM batches WHERE status='active'");
                        foreach($batches_task as $bt) {
                            echo "<option value='{$bt['batch_id']}'>{$bt['batch_code']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Task Type</label>
                    <select name="task_type" class="form-select" id="taskTypeSelect" onchange="toggleDueDate()" required>
                        <option value="daily">Daily Task (Refreshes Daily)</option>
                        <option value="one_time">One-time Task (Specific Day)</option>
                    </select>
                </div>
                <div class="mb-3" id="dueDateField">
                    <label class="form-label fw-bold">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Assign To</label>
                    <select name="assigned_to" class="form-select" required>
                        <option value="">-- Select Worker --</option>
                        <?php
                        $workers = $conn->query("SELECT user_id, full_name, role FROM users WHERE role IN ('staff', 'worker')");
                        foreach($workers as $w) {
                            echo "<option value='{$w['user_id']}'>{$w['full_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="d-grid">
                                <button type="submit" name="create_task" class="btn btn-primary btn-lg rounded-pill">
                                    <i class="fas fa-paper-plane me-2"></i>Assign Task
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <!-- Worker Premium Summary Card -->
                    <div class="card p-4 shadow-sm border-0 rounded-4 h-100 text-center position-relative overflow-hidden" style="background: linear-gradient(135deg, var(--primary), #1a252f); color: white;">
                        <div class="position-absolute" style="top: -20px; right: -20px; font-size: 150px; opacity: 0.05; pointer-events: none;">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <div class="mb-4 mt-4 position-relative" style="z-index: 1;">
                            <div class="d-inline-flex justify-content-center align-items-center bg-white rounded-circle shadow" style="width: 90px; height: 90px;">
                                <i class="fas fa-user-tie" style="font-size: 40px; color: var(--primary);"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-1 position-relative" style="z-index: 1;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff Member'); ?></h4>
                        <p class="text-white-50 small mb-4 position-relative" style="z-index: 1;"><?php echo strtoupper($user_role); ?> ACCOUNT</p>
                        
                        <?php
                            $p_count = $conn->query("SELECT COUNT(*) FROM tasks WHERE status != 'completed'")->fetchColumn();
                            $c_count = $conn->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn();
                        ?>
                        <div class="d-flex justify-content-around text-center mt-auto border-top border-secondary pt-4 position-relative" style="z-index: 1;">
                            <div>
                                <h2 class="fw-bold mb-0 text-warning"><?php echo $p_count; ?></h2>
                                <span class="small text-white-50">Pending Tasks</span>
                            </div>
                            <div>
                                <h2 class="fw-bold mb-0 text-success"><?php echo $c_count; ?></h2>
                                <span class="small text-white-50">Completed</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Task List -->
                <div class="col-md-8">
                    <div class="card p-4 shadow-sm border-0 rounded-4 h-100 position-relative">
                        <!-- Background Watermark -->
                        <div class="position-absolute d-flex justify-content-center align-items-center w-100 h-100" style="top: 0; left: 0; z-index: 0; opacity: 0.03; pointer-events: none;">
                            <i class="fas fa-clipboard-check" style="font-size: 180px;"></i>
                        </div>
                        <div class="position-relative" style="z-index: 1;">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                            <h3 class="section-title mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>
                                <?php echo $user_role == 'admin' ? 'Farm Tasks' : 'My Tasks'; ?>
                            </h3>
                            
                                <!-- Batch Filter -->
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <label class="small fw-bold text-muted text-nowrap">Filter by Batch:</label>
                                    <select id="taskBatchFilter" class="form-select form-select-sm rounded-pill" style="width: auto;" onchange="filterTasks(this.value)">
                                        <?php
                                        $all_batches = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status='active' ORDER BY arrival_date DESC")->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Default to 'all' tasks if no filter set, so workers see everything by default
                                        $selected_filter = isset($_GET['batch_filter']) ? $_GET['batch_filter'] : 'all';
                                        
                                        echo "<option value='all' " . ($selected_filter == 'all' ? 'selected' : '') . ">-- All Tasks --</option>";
                                        echo "<option value='general' " . ($selected_filter == 'general' ? 'selected' : '') . ">General Tasks</option>";
                                        foreach($all_batches as $ab) {
                                            echo "<option value='{$ab['batch_id']}' " . ($selected_filter == $ab['batch_id'] ? 'selected' : '') . ">{$ab['batch_code']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3">
                                <?php
                                try {
                                    // Auto-refresh Daily Tasks
                                    $today = date('Y-m-d');
                                    $conn->exec("UPDATE tasks SET status = 'pending', last_reset_date = '$today' WHERE task_type = 'daily' AND (last_reset_date IS NULL OR last_reset_date != '$today')");

                                    $where_clauses = [];
                                    $params = [];

                                    // Batch filter
                                    if ($selected_filter == 'general') {
                                        $where_clauses[] = "t.batch_id IS NULL";
                                    } elseif ($selected_filter != 'all') {
                                        $where_clauses[] = "t.batch_id = ?";
                                        $params[] = $selected_filter;
                                    }

                                    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

                                $q = "SELECT t.*, u.full_name as assigned_worker, b.batch_code 
                                      FROM tasks t 
                                      LEFT JOIN users u ON t.assigned_to = u.user_id 
                                      LEFT JOIN batches b ON t.batch_id = b.batch_id 
                                      $where_sql
                                      ORDER BY t.task_type DESC, t.due_date ASC, t.status DESC";
                                
                                $stmt = $conn->prepare($q);
                                $stmt->execute($params);
                                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (empty($tasks)) {
                                    echo "<div class='text-center py-5 text-muted h-100 d-flex flex-column justify-content-center align-items-center'>
                                            <div class='mb-3'><i class='fas fa-check-double fa-4x opacity-25 text-success'></i></div>
                                            <h5 class='fw-bold mb-1'>All clear!</h5>
                                            <p class='small mb-0'>No tasks found. Everything is up to date.</p>
                                          </div>";
                                }

                                foreach($tasks as $task) {
                                    $status_class = 'bg-warning text-dark';
                                    $icon = 'fa-clock';
                                    if($task['status'] == 'in_progress') { $status_class = 'bg-info text-dark'; $icon = 'fa-spinner fa-spin'; }
                                    elseif($task['status'] == 'completed') { $status_class = 'bg-success'; $icon = 'fa-check-circle'; }

                                    $escaped_title = e($task['title']);
                                    $escaped_desc = e($task['description']);
                                    $escaped_worker = e($task['assigned_worker'] ?? 'Unassigned');
                                    $escaped_batch_code = e($task['batch_code']);

                                    echo "
                                    <div class='col-12'>
                                        <div class='card border-start border-4 border-" . ($task['status'] == 'completed' ? 'success' : ($task['status'] == 'in_progress' ? 'info' : 'warning')) . " shadow-sm mb-2'>
                                            <div class='card-body p-3'>
                                                <div class='d-flex flex-column flex-sm-row justify-content-between align-items-start gap-2'>
                                                    <div>
                                                        <h5 class='fw-bold mb-1'>{$escaped_title}</h5>
                                                        <p class='small text-muted mb-2'>{$escaped_desc}</p>
                                                        <div class='d-flex flex-wrap gap-2 mb-2'>
                                                            " . ($task['task_type'] == 'daily' ? "<span class='badge bg-primary'><i class='fas fa-sync-alt me-1'></i>Daily</span>" : "<span class='badge bg-light text-dark border'><i class='fas fa-calendar-day me-1'></i>Due: " . date('M d', strtotime($task['due_date'])) . "</span>") . "
                                                            <span class='badge bg-light text-dark border'><i class='fas fa-user me-1'></i>For: {$escaped_worker}</span>
                                                            " . ($task['batch_code'] ? "<span class='badge bg-light text-primary border'><i class='fas fa-layer-group me-1'></i>Batch: {$escaped_batch_code}</span>" : "<span class='badge bg-light text-secondary border'><i class='fas fa-globe me-1'></i>General Task</span>") . "
                                                            <span class='badge {$status_class}'><i class='fas {$icon} me-1'></i>" . ucfirst(str_replace('_', ' ', $task['status'])) . "</span>
                                                        </div>
                                                    </div>
                                                    <div class='d-flex gap-2'>";
                                    
                                    if ($user_role == 'admin') {
                                        echo "
                                                        <form method='POST' action='activities.php?tab=tasks' class='d-flex gap-1'>
                                                            {$csrf_hidden}
                                                            <input type='hidden' name='task_id' value='{$task['task_id']}'>
                                                            <select name='status' class='form-select form-select-sm' style='width: auto;' onchange='this.form.submit()'>
                                                                <option value='pending' " . ($task['status'] == 'pending' ? 'selected' : '') . ">Pending</option>
                                                                <option value='in_progress' " . ($task['status'] == 'in_progress' ? 'selected' : '') . ">In Progress</option>
                                                                <option value='completed' " . ($task['status'] == 'completed' ? 'selected' : '') . ">Completed</option>
                                                            </select>
                                                            <input type='hidden' name='update_task_status' value='1'>
                                                        </form>";
                                    } else {
                                        // Worker UI: Checkbox for completion
                                        $checked = ($task['status'] == 'completed') ? 'checked' : '';
                                        $disabled = ($task['status'] == 'completed') ? 'disabled' : '';
                                        $label = ($task['status'] == 'completed') ? 'Completed' : 'Mark as Complete';
                                        
                                        echo "
                                                        <form method='POST' action='activities.php?tab=tasks' class='d-flex align-items-center gap-2'>
                                                            {$csrf_hidden}
                                                            <input type='hidden' name='task_id' value='{$task['task_id']}'>
                                                            <input type='hidden' name='status' value='completed'>
                                                            <div class='form-check'>
                                                                <input class='form-check-input' type='checkbox' id='taskCheck{$task['task_id']}' 
                                                                       {$checked} {$disabled} onchange='if(this.checked) this.form.submit()'>
                                                                <label class='form-check-label small fw-bold' for='taskCheck{$task['task_id']}'>
                                                                    {$label}
                                                                </label>
                                                            </div>
                                                            <input type='hidden' name='update_task_status' value='1'>
                                                        </form>";
                                    }

                                    if ($user_role == 'admin') {
                                        echo "
                                        <form method='POST' action='activities.php?tab=tasks' class='d-inline' onsubmit='return confirm(\"Are you sure you want to delete this task?\");'>
                                            {$csrf_hidden}
                                            <input type='hidden' name='task_id' value='{$task['task_id']}'>
                                            <input type='hidden' name='delete_task' value='1'>
                                            <button type='submit' class='btn btn-sm btn-outline-danger' title='Delete Task'>
                                                <i class='fas fa-trash'></i>
                                            </button>
                                        </form>";
                                    }

                                    echo "          </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>";
                                }
                            } catch (PDOException $e) { echo "Error loading tasks."; }
                            ?>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDueDate() {
    var type = document.getElementById('taskTypeSelect').value;
    var field = document.getElementById('dueDateField');
    if (type === 'daily') {
        field.style.display = 'none';
    } else {
        field.style.display = 'block';
    }
}

function filterTasks(batchId) {
    const url = new URL(window.location);
    url.searchParams.set('tab', 'tasks');
    url.searchParams.set('batch_filter', batchId);
    window.location.href = url.href;
}

// Run on load if form exists
document.addEventListener('DOMContentLoaded', function() {
    // Restore tab from URL
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab === 'tasks') {
        const tasksTab = document.getElementById('pills-tasks-tab');
        if (tasksTab) tasksTab.click();
    }

    if (document.getElementById('taskTypeSelect')) {
        toggleDueDate();
    }
});

function toggleFields() {
    var type = document.getElementById('activityType').value;
    var itemField = document.getElementById('itemField');
    var qtyField = document.getElementById('qtyField');
    var itemLabel = document.getElementById('itemLabel');
    var qtyLabel = document.getElementById('qtyLabel');
    var qtyHelp = document.getElementById('qtyHelp');
    var remarksField = document.getElementById('remarksField');
    var inventorySelectContainer = document.getElementById('inventorySelectContainer');
    var qtyInput = document.getElementById('qtyInput');

    if (type === 'Cleaning') {
        itemField.style.display = 'none';
        qtyField.style.display = 'none';
        remarksField.style.display = 'block';
        inventorySelectContainer.innerHTML = '<input type="hidden" name="item_name" value="Facility Cleaning">'; 
        qtyInput.removeAttribute('required');
        qtyInput.value = '0';
    } else if (type === 'General') {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity / Value (Optional)';
        if (itemLabel) itemLabel.innerHTML = 'Task / Operation Name';
        inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="e.g., Ventilation Check, Coop Maintenance, Weighing" required>';
        qtyInput.removeAttribute('required');
        qtyInput.value = '';
    } else if (type === 'Health Inspection') {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity / Dosage (Optional)';
        if (itemLabel) itemLabel.innerHTML = 'Medicine / Supplement (Optional)';
        qtyInput.removeAttribute('required');
        
        fetch('get_inventory_items.php?type=' + encodeURIComponent(type))
            .then(response => response.json())
            .then(data => {
                let html = '<select name="inventory_id" id="itemNameInput" class="form-select">';
                html += '<option value="">-- None / Visual Inspection Only --</option>';
                data.forEach(item => {
                    html += `<option value="${item.inventory_id}">${item.item_name} (${item.quantity} ${item.unit} available)</option>`;
                });
                html += '</select>';
                inventorySelectContainer.innerHTML = html;
            })
            .catch(error => {
                inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter inspection details">';
            });
    } else if (type === 'Feeding' || type === 'Supply') {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        qtyInput.setAttribute('required', '');
        
        if (type === 'Feeding') {
            if (itemLabel) itemLabel.innerHTML = 'Feed Item (Inventory)';
            if (qtyLabel) qtyLabel.innerHTML = 'Quantity Used (kg / bags)';
        } else {
            if (itemLabel) itemLabel.innerHTML = 'Supply Item (Inventory)';
            if (qtyLabel) qtyLabel.innerHTML = 'Quantity Used';
        }

        fetch('get_inventory_items.php?type=' + encodeURIComponent(type))
            .then(response => response.json())
            .then(data => {
                let html = '<select name="inventory_id" id="itemNameInput" class="form-select" required>';
                html += '<option value="">-- Select Item --</option>';
                data.forEach(item => {
                    html += `<option value="${item.inventory_id}">${item.item_name} (${item.quantity} ${item.unit} available)</option>`;
                });
                html += '</select>';
                inventorySelectContainer.innerHTML = html;
            })
            .catch(error => {
                inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter name" required>';
            });
    } else {
        itemField.style.display = 'block';
        qtyField.style.display = 'block';
        remarksField.style.display = 'block';
        if (qtyHelp) qtyHelp.classList.add('d-none');
        if (itemLabel) itemLabel.innerHTML = 'Item Name';
        if (qtyLabel) qtyLabel.innerHTML = 'Quantity';
        inventorySelectContainer.innerHTML = '<input type="text" name="item_name" id="itemNameInput" class="form-control" placeholder="Enter name">';
        qtyInput.removeAttribute('required');
    }
}
</script>

<?php include('footer.php'); ?>