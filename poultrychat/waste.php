<?php 
include('database.php'); 
include('header.php'); 

if (!in_array($_SESSION['role'], ['admin', 'staff', 'worker'])) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Insufficient permissions.</div></div>";
    include('footer.php');
    exit();
}

$wasteService = new \Poultry\Services\WasteService();
$message = '';
$error = '';

// Handle Record Waste Submission
if (isset($_POST['save_waste'])) {
    try {
        $wasteType = $_POST['waste_type'] ?? 'supply';
        if (!in_array($wasteType, ['supply', 'medicine', 'other'])) {
            $wasteType = 'supply';
        }
        $wasteDate = $_POST['waste_date'] ?? date('Y-m-d');
        $quantity = floatval($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'units');
        $reason = $_POST['reason'] ?? 'spoiled';

        $inventoryId = !empty($_POST['inventory_id']) ? intval($_POST['inventory_id']) : null;
        $batchId = !empty($_POST['batch_id']) ? intval($_POST['batch_id']) : null;
        $estimatedCost = !empty($_POST['estimated_cost']) ? floatval($_POST['estimated_cost']) : null;
        $remarks = trim($_POST['remarks'] ?? '');

        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than zero.");
        }

        if (in_array($wasteType, ['supply', 'medicine']) && empty($inventoryId)) {
            throw new Exception("Please select an inventory item for {$wasteType} waste.");
        }

        $payload = [
            'waste_type' => $wasteType,
            'waste_date' => $wasteDate,
            'quantity' => $quantity,
            'unit' => $unit,
            'reason' => $reason,
            'inventory_id' => $inventoryId,
            'batch_id' => $batchId,
            'estimated_cost' => $estimatedCost,
            'remarks' => $remarks
        ];

        $res = $wasteService->recordWaste($payload, (int)$_SESSION['user_id']);
        $message = "Waste record logged successfully! Farm inventory has been updated.";
    } catch (Exception $e) {
        $error = "Failed to record waste: " . $e->getMessage();
    }
}

// Fetch form options

$inventoryItems = $conn->query("SELECT inventory_id, item_name, category, unit FROM inventory WHERE is_active = 1 ORDER BY category, item_name")->fetchAll(PDO::FETCH_ASSOC);
$batches = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status = 'active' ORDER BY batch_code")->fetchAll(PDO::FETCH_ASSOC);

// Fetch waste records via WasteService
$wasteRecords = $wasteService->listWaste();
?>

<div class="page-grid">
    <!-- Record Waste Form -->
    <div>
        <div class="card form-container h-100">
            <div class="card-body d-flex flex-column">
                <h3 class="section-title"><i class="fas fa-trash-can text-primary"></i>Record Waste / Damage</h3>
            <p class="text-muted small mb-4">Log damaged, broken, or expired farm inventory and biological losses.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo e($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label fw-bold">Waste Type</label>
                    <select name="waste_type" id="wasteTypeSelect" class="form-select" required onchange="toggleWasteTypeFields()">
                        <option value="supply" selected>Feed Spoilage or Supply Damage</option>
                        <option value="medicine">Expired Medicine or Biologics</option>
                        <option value="other">Other Farm Waste</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Date of Loss</label>
                    <input type="date" name="waste_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- Inventory Item Selector (Feed / Medicine / Supplies) -->
                <div class="mb-3" id="inventoryItemGroup">
                    <label class="form-label fw-bold">Inventory Item <span class="text-danger">*</span></label>
                    <select name="inventory_id" id="inventorySelect" class="form-select">
                        <option value="">-- Select Inventory Item --</option>
                        <?php foreach ($inventoryItems as $inv): ?>
                            <option value="<?php echo (int)$inv['inventory_id']; ?>" data-unit="<?php echo e($inv['unit']); ?>" data-category="<?php echo e($inv['category']); ?>">
                                <?php echo e($inv['item_name'] . ' (' . ucfirst($inv['category']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label fw-bold">Quantity</label>
                        <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-5">
                        <label class="form-label fw-bold">Unit</label>
                        <input type="text" name="unit" id="wasteUnitInput" class="form-control" value="Bags" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Reason for Waste</label>
                    <select name="reason" class="form-select" required>
                        <option value="spoiled" selected>Spoiled / Contaminated</option>
                        <option value="expired">Expired</option>
                        <option value="damaged">Damaged / Bag Tear</option>
                        <option value="cull">Cull / Defect</option>
                        <option value="other">Other</option>
                    </select>
                </div>


                <div class="mb-3">
                    <label class="form-label fw-bold">Batch (Optional)</label>
                    <select name="batch_id" class="form-select">
                        <option value="">-- None / General Farm --</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?php echo (int)$b['batch_id']; ?>">
                                <?php echo e($b['batch_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Estimated Cost Loss (₱)</label>
                    <input type="number" step="0.01" min="0" name="estimated_cost" class="form-control" placeholder="0.00 (Optional)">
                    <small class="text-muted">Optionally records an expense entry for accounting.</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                </div>

                <div class="form-actions-sticky mt-auto d-grid">
                    <button type="submit" name="save_waste" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Record Waste
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Waste & Damage Records List -->
    <div style="min-width: 0;">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h4 class="section-title mb-1 text-danger"><i class="fas fa-trash-can"></i>Waste & Damage Records</h4>
                        <p class="text-muted small mb-0">Audit history of farm write-offs, feed spoilage, and discarded supplies.</p>
                    </div>
                    <span class="badge bg-danger px-3 py-2 rounded-pill">
                        <?php echo count($wasteRecords); ?> Logged Records
                    </span>
                </div>

                <div class="table-responsive custom-scrollbar">
                <table class="table table-hover align-middle">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Date</th>
                            <th>Waste Type</th>
                            <th>Item / Grade</th>
                            <th>Reason</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Est. Loss Cost</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($wasteRecords)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-inbox me-2"></i>No waste records logged yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($wasteRecords as $w): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($w['waste_date'])); ?></td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase px-2 py-1">
                                    <?php echo e($w['waste_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($w['item_name'])): ?>
                                    <strong><?php echo e($w['item_name']); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">General Loss</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo e(ucfirst($w['reason'])); ?>
                                </span>
                                <?php if (!empty($w['remarks'])): ?>
                                    <small class="text-muted d-block"><?php echo e($w['remarks']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold"><?php echo number_format($w['quantity']); ?> <?php echo e($w['unit']); ?></td>
                            <td class="text-end text-danger fw-semibold">
                                <?php echo !empty($w['estimated_cost']) ? '₱' . number_format($w['estimated_cost'], 2) : '-'; ?>
                            </td>
                            <td class="small text-muted"><?php echo e($w['recorded_by_name'] ?? 'System'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
function toggleWasteTypeFields() {
    const type = document.getElementById('wasteTypeSelect').value;
    const invGroup = document.getElementById('inventoryItemGroup');
    const unitInput = document.getElementById('wasteUnitInput');

    if (type === 'medicine' || type === 'supply') {
        invGroup.classList.remove('d-none');
        unitInput.value = (type === 'supply') ? 'Bags' : 'Bottles';
    } else {
        invGroup.classList.add('d-none');
        unitInput.value = 'Units';
    }
}

document.getElementById('inventorySelect')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const unit = selected.getAttribute('data-unit');
    if (unit) {
        document.getElementById('wasteUnitInput').value = unit;
    }
});
</script>
