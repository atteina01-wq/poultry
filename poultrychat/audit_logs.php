<?php 
include('database.php'); 
include('header.php'); 

if ($_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Access Denied. Administrator role required.</div>";
    include('footer.php');
    exit();
}

$stmt = $conn->query("
    SELECT al.*, u.username, u.full_name, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC, al.log_id DESC
    LIMIT 100
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-shield-halved me-2 text-success"></i>System Audit Trail & Security Logs</h4>
                        <p class="text-muted small mb-0">Immutable record of all operational and administrative actions across the farm.</p>
                    </div>
                </div>

                <div class="table-responsive custom-scrollbar">
                    <table class="table table-hover align-middle table-mobile-cards">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User / Role</th>
                                <th>Action</th>
                                <th>Target Table</th>
                                <th>Record ID</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No audit logs recorded yet.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td data-label="Timestamp" class="small"><i class="far fa-clock me-1 text-muted"></i><?php echo htmlspecialchars($l['created_at']); ?></td>
                                <td data-label="User / Role">
                                    <strong><?php echo htmlspecialchars($l['full_name'] ?? $l['username'] ?? 'System/Guest'); ?></strong>
                                    <?php if ($l['role']): ?>
                                        <span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars($l['role']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Action"><span class="badge bg-dark text-uppercase px-2 py-1"><?php echo htmlspecialchars($l['action']); ?></span></td>
                                <td data-label="Target Table"><code><?php echo htmlspecialchars($l['table_name']); ?></code></td>
                                <td data-label="Record ID"><?php echo $l['record_id'] ? '#' . $l['record_id'] : '-'; ?></td>
                                <td data-label="IP Address" class="small text-muted"><?php echo htmlspecialchars($l['ip_address'] ?? '-'); ?></td>
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

<?php include('footer.php'); ?>
