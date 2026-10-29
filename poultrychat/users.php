<?php 
include('database.php'); 
include('header.php'); 

if ($_SESSION['role'] !== 'admin') {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Administrator role required.</div></div>";
    include('footer.php');
    exit();
}

$message = '';
$error = '';

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'worker');
        $customer_id = ($role === 'integrator' && !empty($_POST['customer_id'])) ? intval($_POST['customer_id']) : null;

        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }

        if (!in_array($role, ['admin', 'staff', 'worker', 'integrator'])) {
            throw new Exception("Invalid role selected.");
        }

        // Check if username exists
        $chkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $chkStmt->execute([$username]);
        if ($chkStmt->fetch()) {
            throw new Exception("Username '{$username}' is already taken.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role, customer_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $insertStmt->execute([$username, $hashedPassword, $full_name, $email, $phone, $role, $customer_id]);

        $message = "User '{$username}' with role '{$role}' created successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$customersList = $conn->query("SELECT customer_id, name, customer_code FROM customers WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("
    SELECT u.user_id, u.username, u.full_name, u.email, u.phone, u.role, u.customer_id, u.is_active, u.last_login_at, u.created_at,
           c.name as customer_name, c.customer_code
    FROM users u 
    LEFT JOIN customers c ON u.customer_id = c.customer_id 
    ORDER BY u.is_active DESC, u.role ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-user-gear me-2 text-success"></i>User Administration & Role Management</h4>
                        <p class="text-muted small mb-0">Manage system users including Farm Owner, Workers, Staff, and Integrator Partner Representatives.</p>
                    </div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-1"></i>Add New User
                    </button>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo e($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive custom-scrollbar">
                    <table class="table table-hover align-middle table-mobile-cards">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Email / Contact</th>
                                <th>Assigned Role</th>
                                <th class="text-center">Account Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td data-label="User">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                            <?php echo strtoupper(substr($u['full_name'] ?? $u['username'], 0, 1)); ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars($u['full_name'] ?? 'User'); ?></strong>
                                    </div>
                                </td>
                                <td data-label="Username"><code><?php echo htmlspecialchars($u['username']); ?></code></td>
                                <td data-label="Email / Contact"><?php echo htmlspecialchars($u['email'] ?? $u['phone'] ?? 'N/A'); ?></td>
                                <td data-label="Role">
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-primary text-uppercase px-3 py-2"><i class="fas fa-crown me-1"></i>Admin (Owner)</span>
                                    <?php elseif ($u['role'] === 'integrator'): ?>
                                        <span class="badge bg-success text-uppercase px-3 py-2"><i class="fas fa-building me-1"></i>Integrator Partner</span>
                                        <?php if (!empty($u['customer_name'])): ?>
                                            <div class="small fw-semibold text-primary mt-1">
                                                <i class="fas fa-handshake text-success me-1"></i><?php echo htmlspecialchars($u['customer_name']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="small text-muted mt-1"><em>No partner company assigned</em></div>
                                        <?php endif; ?>
                                    <?php elseif ($u['role'] === 'staff'): ?>
                                        <span class="badge bg-info-subtle text-info border text-uppercase px-3 py-2"><i class="fas fa-user-tie me-1"></i>Staff</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border text-uppercase px-3 py-2"><i class="fas fa-hammer me-1"></i>Worker</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status" class="text-center">
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-1">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-1">Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Last Login" class="small text-muted"><?php echo $u['last_login_at'] ? htmlspecialchars($u['last_login_at']) : 'Never'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add New User -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="create_user" value="1">
                <div class="modal-header bg-light border-0 py-3">
                    <h5 class="modal-title fw-bold" id="addUserModalLabel"><i class="fas fa-user-plus me-2 text-primary"></i>Create User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="e.g. Juan Dela Cruz" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6 mb-2 mb-sm-0">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" autocapitalize="none" autocorrect="off" class="form-control" placeholder="e.g. juan2026" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">User Role</label>
                        <select name="role" id="userRoleSelect" class="form-select" required>
                            <option value="worker">Worker (Daily feeding, activities, mortality logging)</option>
                            <option value="staff">Staff (Operational management, view reports)</option>
                            <option value="admin">Admin (Full farm owner & financial controls)</option>
                        </select>
                        <div class="alert alert-info py-2 px-3 small rounded-3 mt-2 mb-0">
                            <i class="fas fa-handshake me-1 text-primary"></i><strong>Commercial Integrators:</strong>
                            Integrator Partners create their own accounts independently via the <a href="index.php?auth=register" target="_blank" class="fw-bold text-decoration-underline">Partner Portal</a>.
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-sm-6 mb-2 mb-sm-0">
                            <label class="form-label fw-bold">Email (Optional)</label>
                            <input type="email" inputmode="email" name="email" class="form-control" placeholder="name@example.com">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-bold">Phone (Optional)</label>
                            <input type="tel" inputmode="tel" name="phone" class="form-control" placeholder="09XX-XXX-XXXX">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i>Create Internal User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
