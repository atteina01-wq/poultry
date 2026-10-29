<?php include('database.php'); ?>
<?php include('db_check.php'); ?>
<?php include('header.php'); ?>

<?php
if (!in_array($_SESSION['role'], ['admin', 'staff', 'worker'])) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Insufficient permissions.</div></div>";
    include('footer.php');
    exit();
}

$invService = new \Poultry\Services\InventoryService();
$message = '';
$error = '';
$canAddInventory = in_array($_SESSION['role'], ['admin', 'staff', 'worker'], true);

if (isset($_POST['save'])) {
    try {
        $itemName = trim($_POST['item_name'] ?? '');
        $category = $_POST['category'] ?? 'feed';
        $quantity = floatval($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $cost = floatval($_POST['cost'] ?? 0);
        $lotNumber = trim($_POST['lot_number'] ?? '');
        $supplier = trim($_POST['supplier'] ?? '');
        $receivedDate = !empty($_POST['received_date']) ? $_POST['received_date'] : date('Y-m-d');
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

        if (empty($itemName)) {
            throw new Exception("Item name is required.");
        }
        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than zero.");
        }

        $payload = [
            'item_name' => $itemName,
            'category' => $category,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_cost' => $cost,
            'lot_number' => $lotNumber,
            'supplier' => $supplier,
            'received_date' => $receivedDate,
            'expiry_date' => $expiryDate
        ];

        $savedItem = $invService->createItem($payload, (int)$_SESSION['user_id']);
        $message = "Inventory item '" . e($savedItem['item_name']) . "' saved and lot tracked successfully!";
    } catch (Exception $e) {
        error_log("Inventory save error: " . $e->getMessage());
        $error = "Unable to save inventory: " . $e->getMessage();
    }
}

// Fetch Inventory KPIs
$kpiData = $invService->getKpis();
$totalValuation = (float)($kpiData['total_valuation'] ?? 0.0);
$lowStockCount = (int)($kpiData['low_stock_count'] ?? 0);
$outOfStockCount = (int)($kpiData['out_of_stock_count'] ?? 0);

// Fetch expiring lots via InventoryService
$expiringLots = [];
try {
    $expiringLots = $invService->getExpiringLots(60);
} catch (Exception $e) {
    error_log("Expiring lots error: " . $e->getMessage());
}

$all_items = $conn->query("SELECT inventory_id, item_name, category FROM inventory ORDER BY category, item_name")->fetchAll(PDO::FETCH_ASSOC);
$selected_item = isset($_GET['item_filter']) ? $_GET['item_filter'] : 'all';

function getCategoryDisplayName(string $cat): string {
    $cat = strtolower(trim($cat));
    if ($cat === 'supply') return 'Other Farm Supplies';
    if ($cat === 'vitamins') return 'Vitamins';
    if ($cat === 'medicine') return 'Medicine';
    if ($cat === 'feed') return 'Feed';
    if ($cat === 'limestone') return 'Limestone';
    return ucfirst($cat);
}
?>

<style>
    .add-inventory-fab {
        position: fixed;
        right: 1.5rem;
        bottom: 1.5rem;
        z-index: 1040;
        padding: 0.85rem 1.35rem;
        font-size: 0.95rem;
        box-shadow: 0 10px 28px rgba(20, 83, 45, 0.35) !important;
    }
    .add-inventory-fab:hover {
        transform: translateY(-2px);
    }
    #inventoryStockTable_wrapper .dataTables_filter input,
    #inventoryStockTable_wrapper .dataTables_length select,
    #inventoryUsageTable_wrapper .dataTables_filter input,
    #inventoryUsageTable_wrapper .dataTables_length select {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        padding: 0.35rem 0.65rem;
    }
    @media (max-width: 576px) {
        .add-inventory-fab span { display: none; }
        .add-inventory-fab {
            width: 3.25rem;
            height: 3.25rem;
            padding: 0;
            border-radius: 50% !important;
            justify-content: center;
        }
    }
</style>

<?php if (!empty($message)): ?>
    <div class="alert alert-success shadow-sm py-2 small mb-3">
        <i class="fas fa-check-circle me-2"></i><?php echo e($message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger shadow-sm py-2 small mb-3">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
    </div>
<?php endif; ?>

<!-- KPI Highlights -->
<div class="row g-2 g-md-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card p-3 shadow-sm border-0 rounded-4 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="p-2 p-md-3 bg-primary-subtle text-primary rounded-circle me-3">
                    <i class="fas fa-coins fa-2x"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-bold">Overall Valuation</small>
                    <h3 class="mb-0 fw-bold text-dark fs-4 fs-md-3">₱<?php echo number_format($totalValuation, 2); ?></h3>
                    <small class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>Active Farm Assets</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card p-3 shadow-sm border-0 rounded-4 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="p-2 p-md-3 bg-warning-subtle text-warning rounded-circle me-2 me-md-3">
                    <i class="fas fa-triangle-exclamation fa-lg fa-md-2x"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Low Stock</small>
                    <h4 class="mb-0 fw-bold <?php echo $lowStockCount > 0 ? 'text-warning' : 'text-dark'; ?>">
                        <?php echo $lowStockCount; ?>
                    </h4>
                    <small class="text-muted d-none d-sm-inline">≤ 10 threshold</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card p-3 shadow-sm border-0 rounded-4 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="p-2 p-md-3 bg-danger-subtle text-danger rounded-circle me-2 me-md-3">
                    <i class="fas fa-circle-xmark fa-lg fa-md-2x"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Out of Stock</small>
                    <h4 class="mb-0 fw-bold <?php echo $outOfStockCount > 0 ? 'text-danger' : 'text-dark'; ?>">
                        <?php echo $outOfStockCount; ?>
                    </h4>
                    <small class="text-muted d-none d-sm-inline">Immediate restock</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Expiring Soon Lots Monitoring -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="section-title mb-1 text-danger">
                    <i class="fas fa-clock-rotate-left"></i>Expiring Lots Monitoring
                </h4>
                <p class="text-muted small mb-0">Lots expiring within the next 60 days from <code>v_expiring_lots</code>.</p>
            </div>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">
                <?php echo count($expiringLots); ?> Expiring Lots
            </span>
        </div>

        <div class="table-responsive custom-scrollbar" style="max-height: 260px; overflow-y: auto;">
            <table class="table table-hover align-middle table-mobile-cards">
                <thead class="table-light sticky-top">
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Lot #</th>
                        <th class="text-end">Remaining</th>
                        <th>Expiry Date</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expiringLots)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-check-circle text-success me-2"></i>No lots expiring soon! All inventory lots are in good condition.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expiringLots as $lot): ?>
                            <?php
                            $days = (int)$lot['days_until_expiry'];
                            if ($days < 0) {
                                $expBadge = 'bg-danger text-white';
                                $expText = 'Expired (' . abs($days) . 'd ago)';
                            } elseif ($days <= 14) {
                                $expBadge = 'bg-danger-subtle text-danger border-danger';
                                $expText = $days . ' days left';
                            } elseif ($days <= 30) {
                                $expBadge = 'bg-warning-subtle text-warning border-warning';
                                $expText = $days . ' days left';
                            } else {
                                $expBadge = 'bg-info-subtle text-info border-info';
                                $expText = $days . ' days left';
                            }
                            ?>
                            <tr>
                                <td data-label="Item" class="fw-bold"><?php echo e($lot['item_name']); ?></td>
                                <td data-label="Category">
                                    <span class="badge bg-light text-dark border">
                                        <?php echo e(getCategoryDisplayName($lot['category'])); ?>
                                    </span>
                                </td>
                                <td data-label="Lot #"><code><?php echo e(!empty($lot['lot_number']) ? $lot['lot_number'] : 'N/A'); ?></code></td>
                                <td data-label="Remaining" class="text-end fw-bold"><?php echo number_format($lot['quantity_remaining'], 1); ?> <?php echo e($lot['unit'] ?? ''); ?></td>
                                <td data-label="Expiry Date"><?php echo date('M d, Y', strtotime($lot['expiry_date'])); ?></td>
                                <td data-label="Status" class="text-center">
                                    <span class="badge rounded-pill <?php echo $expBadge; ?> border px-3 py-1">
                                        <?php echo e($expText); ?>
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

<!-- Current Stock -->
<div class="card p-3 p-md-4 shadow-sm border-0 rounded-4 mb-4">
    <h4 class="card-title mb-4 text-primary fw-bold">
        <i class="fas fa-warehouse me-2"></i>Current Stock
    </h4>
    <div class="table-responsive">
        <table id="inventoryStockTable" class="table table-hover align-middle w-100 m-0 table-mobile-cards">
            <thead class="table-light">
                <tr>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Prev. Stock</th>
                    <th>Current Stock</th>
                    <th class="text-end">Total Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $stmt = $conn->query("SELECT * FROM inventory ORDER BY category, item_name");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $totalValue = max(0, (float)$row['quantity']) * (float)$row['unit_cost'];
                        $qty = (float)$row['quantity'];

                        if ($qty <= 0) {
                            $stockStatusClass = 'text-danger fw-bold';
                            $statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
                        } elseif ($qty <= 10) {
                            $stockStatusClass = 'text-warning fw-bold';
                            $statusBadge = '<span class="badge bg-warning text-dark">Low Stock</span>';
                        } else {
                            $stockStatusClass = 'text-success fw-semibold';
                            $statusBadge = '<span class="badge bg-success">In Stock</span>';
                        }

                        echo "<tr>
                            <td data-label='Item Name' class='fw-bold'>" . e($row['item_name']) . "</td>
                            <td data-label='Category'><span class='badge bg-info-subtle text-info border rounded-pill'>" . e(getCategoryDisplayName($row['category'])) . "</span></td>
                            <td data-label='Prev. Stock' class='text-muted small'>{$row['previous_quantity']} " . e($row['unit']) . "</td>
                            <td data-label='Current Stock' class='{$stockStatusClass}'>" . number_format($qty, 2) . " " . e($row['unit']) . "</td>
                            <td data-label='Total Value' class='text-end fw-bold text-primary'>₱" . number_format($totalValue, 2) . "</td>
                            <td data-label='Status'>{$statusBadge}</td>
                        </tr>";
                    }
                } catch (PDOException $e) {
                    error_log("Stock fetch error: " . $e->getMessage());
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Usage History with Remaining Stock -->
<div class="card p-3 p-md-4 shadow-sm border-0 rounded-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="card-title mb-0 text-primary fw-bold">
            <i class="fas fa-history me-2"></i>Usage History & Stock Balance
        </h4>

        <!-- Item Filter -->
        <div class="d-flex align-items-center gap-2">
            <label class="small fw-bold text-muted text-nowrap">Filter by Item:</label>
            <select id="itemHistoryFilter" class="form-select form-select-sm rounded-pill" style="width: auto;" onchange="filterUsage(this.value)">
                <?php
                echo "<option value='all' " . ($selected_item == 'all' ? 'selected' : '') . ">-- All Items --</option>";
                foreach ($all_items as $ai) {
                    echo "<option value='{$ai['inventory_id']}' " . ($selected_item == $ai['inventory_id'] ? 'selected' : '') . ">" . e($ai['item_name']) . " (" . e(getCategoryDisplayName($ai['category'])) . ")</option>";
                }
                ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table id="inventoryUsageTable" class="table table-hover align-middle w-100 m-0 table-mobile-cards">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Broiler Batch</th>
                    <th>Item Name</th>
                    <th>Type</th>
                    <th class="text-end">Qty Used</th>
                    <th class="text-end">Remaining Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $feed_where = "";
                    $act_where = "WHERE a.type IN ('Health', 'Supply')";
                    $hr_where = "";
                    $feed_params = [];
                    $act_params = [];
                    $hr_params = [];

                    if ($selected_item != 'all') {
                        $stmt_name = $conn->prepare("SELECT item_name FROM inventory WHERE inventory_id = ?");
                        $stmt_name->execute([$selected_item]);
                        $item_name_val = $stmt_name->fetchColumn();

                        $feed_where = "WHERE f.inventory_id = ?";
                        $act_where = "WHERE a.item_name = ? AND a.type IN ('Health', 'Supply')";
                        $hr_where = "WHERE hr.medicine_name = ?";
                        $feed_params = [$selected_item];
                        $act_params = [$item_name_val];
                        $hr_params = [$item_name_val];
                    }

                    $usage_query = "
                        (SELECT f.date, b.batch_code, i.item_name, 'Feeding' as type, f.quantity_used as quantity, i.unit, i.quantity as remaining_stock
                         FROM feed_consumption f
                         JOIN batches b ON f.batch_id = b.batch_id
                         JOIN inventory i ON f.inventory_id = i.inventory_id
                         $feed_where)
                        UNION ALL
                        (SELECT a.date, b.batch_code, a.item_name, a.type, a.quantity, i.unit, COALESCE(i.quantity, 0) as remaining_stock
                         FROM activities a
                         JOIN batches b ON a.batch_id = b.batch_id
                         LEFT JOIN inventory i ON a.item_name = i.item_name
                         $act_where)
                        UNION ALL
                        (SELECT hr.date, b.batch_code, hr.medicine_name as item_name, 'Health' as type, hr.quantity_used as quantity, i.unit, COALESCE(i.quantity, 0) as remaining_stock
                         FROM health_records hr
                         JOIN batches b ON hr.batch_id = b.batch_id
                         LEFT JOIN inventory i ON hr.medicine_name = i.item_name
                         $hr_where)
                        ORDER BY date DESC
                    ";

                    $stmt = $conn->prepare($usage_query);
                    $stmt->execute(array_merge($feed_params, $act_params, $hr_params));
                    $usage = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($usage as $u) {
                        $badge_color = 'bg-secondary';
                        if ($u['type'] == 'Feeding') $badge_color = 'bg-warning text-dark';
                        elseif ($u['type'] == 'Health') $badge_color = 'bg-danger';
                        elseif ($u['type'] == 'Supply') $badge_color = 'bg-success';

                        echo "<tr>
                            <td data-label='Date'>" . date('M d, Y', strtotime($u['date'])) . "</td>
                            <td data-label='Broiler Batch'><span class='badge bg-light text-primary border'><i class='fas fa-drumstick-bite me-1 text-success'></i>" . e($u['batch_code']) . "</span></td>
                            <td data-label='Item Name' class='fw-bold'>" . e($u['item_name']) . "</td>
                            <td data-label='Type'><span class='badge {$badge_color} rounded-pill'>" . e(ucfirst($u['type'])) . "</span></td>
                            <td data-label='Qty Used' class='text-end fw-bold text-danger'>-" . number_format($u['quantity'], 2) . " <small class='text-muted fw-normal'>" . e($u['unit']) . "</small></td>
                            <td data-label='Remaining Stock' class='text-end fw-bold text-success'>" . number_format((float)$u['remaining_stock'], 2) . " <small class='text-muted fw-normal'>" . e($u['unit']) . "</small></td>
                        </tr>";
                    }
                } catch (PDOException $e) {
                    error_log("Usage history error: " . $e->getMessage());
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canAddInventory): ?>
<!-- Sticky FAB: Add Inventory & Lots -->
<button type="button"
        class="btn btn-primary add-inventory-fab shadow-lg rounded-pill fw-bold d-flex align-items-center gap-2"
        data-bs-toggle="modal"
        data-bs-target="#addInventoryModal"
        title="Add Inventory & Lots">
    <i class="fas fa-plus"></i>
    <span>Add Inventory & Lots</span>
</button>

<!-- Modal: Add Inventory & Lots -->
<div class="modal fade" id="addInventoryModal" tabindex="-1" aria-labelledby="addInventoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-box-open fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="addInventoryModalLabel">Add Inventory & Lots</h5>
                        <small class="text-muted">Add feed, vitamins, medicines, limestone, or supplies with lot tracking</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="addInventoryForm">
                    <?php echo csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" placeholder="e.g., Broiler Starter Feed" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="feed">Feed</option>
                            <option value="medicine">Medicine</option>
                            <option value="vitamins">Vitamins</option>
                            <option value="supply">Other Farm Supplies</option>
                            <option value="limestone">Limestone</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" inputmode="decimal" name="quantity" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Unit <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" placeholder="e.g. sacks, bottles" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Unit Cost (₱ per unit) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" inputmode="decimal" name="cost" class="form-control" placeholder="e.g., 150.00" required>
                        <small class="text-muted">Enter cost per single unit (sack, vial, bottle, etc.)</small>
                    </div>

                    <!-- Lot & Expiry Details -->
                    <div class="border rounded-3 p-3 mb-4 bg-light">
                        <h6 class="fw-bold small text-secondary mb-2">
                            <i class="fas fa-barcode me-1 text-primary"></i>Lot & Expiry Tracking (Recommended)
                        </h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Lot / Batch #</label>
                                <input type="text" name="lot_number" class="form-control form-control-sm" placeholder="e.g., LOT-2026-01">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Supplier</label>
                                <input type="text" name="supplier" class="form-control form-control-sm" placeholder="e.g., B-Meg Feeds">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Received Date</label>
                                <input type="date" name="received_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="save" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fas fa-save me-2"></i>Save Item & Lot
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
function filterUsage(inventoryId) {
    const url = new URL(window.location);
    url.searchParams.set('item_filter', inventoryId);
    url.searchParams.delete('page');
    window.location.href = url.href;
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#inventoryStockTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            language: {
                search: 'Search stock:',
                lengthMenu: 'Show _MENU_ items',
                info: 'Showing _START_ to _END_ of _TOTAL_ items',
                infoEmpty: 'No stock items',
                zeroRecords: 'No matching stock items found',
                emptyTable: 'No inventory stock found.'
            }
        });

        jQuery('#inventoryUsageTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            language: {
                search: 'Search usage:',
                lengthMenu: 'Show _MENU_ records',
                info: 'Showing _START_ to _END_ of _TOTAL_ records',
                infoEmpty: 'No usage records',
                zeroRecords: 'No matching usage records found',
                emptyTable: 'No usage records found.'
            }
        });
    }
});
</script>

<?php include('footer.php'); ?>
