<?php 
include('database.php'); 
include('header.php'); 

// Pagination Logic
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_records = (int)$conn->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch expenses
$stmt = $conn->prepare("
    SELECT e.*, b.batch_code, i.item_name as inventory_item_name, u.full_name as recorded_by_name
    FROM expenses e
    LEFT JOIN batches b ON e.batch_id = b.batch_id
    LEFT JOIN inventory i ON e.inventory_id = i.inventory_id
    LEFT JOIN users u ON e.recorded_by = u.user_id
    ORDER BY e.date DESC, e.expense_id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expense categories summary
$stmtCat = $conn->query("
    SELECT category, SUM(amount) as total_amount, COUNT(*) as count
    FROM expenses
    GROUP BY category
    ORDER BY total_amount DESC
");
$categorySummary = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-2 g-md-4 mb-4">
    <!-- Category Cards -->
    <?php foreach ($categorySummary as $cat): ?>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($cat['category']); ?></div>
                <h4 class="fw-bold text-dark mb-0 fs-5 fs-md-4">₱<?php echo number_format($cat['total_amount'], 2); ?></h4>
                <small class="text-muted"><?php echo $cat['count']; ?> entries</small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-wallet me-2 text-success"></i>Operational Expenses Ledger</h4>
                        <p class="text-muted small mb-0">Track all farm expenses (Feed, Limestone, Medicine, Supplies, Labor, Utilities, Waste).</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-mobile-cards">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Related Flock / Item</th>
                                <th class="text-end">Amount</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No expense records logged yet.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td data-label="Date"><?php echo htmlspecialchars($e['date']); ?></td>
                                <td data-label="Category"><span class="badge bg-secondary text-uppercase px-2 py-1"><?php echo htmlspecialchars($e['category']); ?></span></td>
                                <td data-label="Description"><?php echo htmlspecialchars($e['description'] ?? '-'); ?></td>
                                <td data-label="Flock / Item">
                                    <?php if ($e['batch_code']): ?>
                                        <span class="badge bg-light text-dark border"><i class="fas fa-feather-pointed me-1"></i><?php echo htmlspecialchars($e['batch_code']); ?></span>
                                    <?php elseif ($e['inventory_item_name']): ?>
                                        <span class="badge bg-light text-dark border"><i class="fas fa-box me-1"></i><?php echo htmlspecialchars($e['inventory_item_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Amount" class="text-end fw-bold text-dark">₱<?php echo number_format($e['amount'], 2); ?></td>
                                <td data-label="Recorded By" class="small text-muted"><?php echo htmlspecialchars($e['recorded_by_name'] ?? 'System'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Expenses pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
