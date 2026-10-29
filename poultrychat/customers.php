<?php include('database.php'); ?>
<?php include('db_check.php'); ?>
<?php include('header.php'); ?>

<?php
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Insufficient permissions.</div></div>";
    include('footer.php');
    exit();
}

$message = '';
$error = '';

if (isset($_POST['save_customer'])) {
    $customer_id = $_POST['customer_id'] ?? null;
    $customer_code = trim($_POST['customer_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $customer_type = $_POST['customer_type'] ?? 'retail';

    if (empty($customer_id)) {
        $error = "Integrator record is required for updates.";
    } elseif (empty($name)) {
        $error = "Company name is required";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address";
    } else {
        try {
            if ($customer_code === '') {
                $codeStmt = $conn->prepare("SELECT customer_code FROM customers WHERE customer_id = ?");
                $codeStmt->execute([(int)$customer_id]);
                $customer_code = (string)($codeStmt->fetchColumn() ?: ('INT-' . $customer_id));
            }
            if (!in_array($customer_type, ['retail', 'wholesale', 'institutional'], true)) {
                $customer_type = 'retail';
            }

            $sql = "UPDATE customers
                    SET customer_code = ?, name = ?, contact_person = ?, phone = ?, email = ?, address = ?, customer_type = ?
                    WHERE customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $customer_code,
                $name,
                $contact_person ?: null,
                $phone ?: null,
                $email ?: null,
                $address ?: null,
                $customer_type,
                (int)$customer_id
            ]);
            $message = "Integrator updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

if (isset($_POST['deactivate_customer'])) {
    $customer_id = intval($_POST['deactivate_customer']);
    try {
        $stmt = $conn->prepare("UPDATE customers SET is_active = 0 WHERE customer_id = ? AND is_active = 1");
        $stmt->execute([$customer_id]);
        if ($stmt->rowCount() > 0) {
            $message = "Integrator deactivated successfully!";
        } else {
            $error = "Integrator not found or already inactive.";
        }
    } catch (PDOException $e) {
        $error = "Deactivation failed: " . $e->getMessage();
    }
}

$stmt = $conn->query("SELECT * FROM customers ORDER BY is_active DESC, name ASC");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    #customersTable_wrapper .dataTables_filter input,
    #customersTable_wrapper .dataTables_length select {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        padding: 0.35rem 0.65rem;
    }
</style>

<div class="container-fluid px-2 px-md-3">
    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm py-2 small mb-3" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm py-2 small mb-3" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;">
                        <i class="fas fa-building text-primary me-2"></i>Registered Integrators
                    </h5>
                    <small class="text-muted">Directory of integrator partner companies.</small>
                </div>
                <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-medium">
                    <i class="fas fa-users me-1 text-primary"></i><?php echo count($customers); ?> Registered
                </span>
            </div>
            <div class="table-responsive custom-scrollbar">
                <table id="customersTable" class="table table-hover align-middle w-100 m-0 table-mobile-cards">
                    <thead class="table-light" style="border-bottom: 2px solid var(--border, #DDD6C4);">
                        <tr>
                            <th class="text-secondary fw-semibold text-uppercase small">Company Name</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Representative Name</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Phone</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Status</th>
                            <th class="text-secondary fw-semibold text-uppercase small">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $cust): ?>
                            <?php $isActive = (int)$cust['is_active'] === 1; ?>
                            <tr class="<?php echo $isActive ? '' : 'table-secondary'; ?>">
                                <td data-label="Company Name" class="fw-bold text-primary"><?php echo e($cust['name']); ?></td>
                                <td data-label="Representative Name"><?php echo $cust['contact_person'] ? e($cust['contact_person']) : '<span class="text-muted small">—</span>'; ?></td>
                                <td data-label="Phone"><?php echo $cust['phone'] ? e($cust['phone']) : '<span class="text-muted small">—</span>'; ?></td>
                                <td data-label="Status">
                                    <span class="badge rounded-pill <?php echo $isActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?> border px-3">
                                        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <?php
                                    $viewPayload = [
                                        'customer_id' => (int)$cust['customer_id'],
                                        'customer_code' => $cust['customer_code'] ?? '',
                                        'name' => $cust['name'] ?? '',
                                        'contact_person' => $cust['contact_person'] ?? '',
                                        'phone' => $cust['phone'] ?? '',
                                        'email' => $cust['email'] ?? '',
                                        'address' => $cust['address'] ?? '',
                                        'customer_type' => $cust['customer_type'] ?? 'retail',
                                        'is_active' => (int)$cust['is_active'],
                                        'created_at' => $cust['created_at'] ?? '',
                                    ];
                                    $viewJson = htmlspecialchars(json_encode($viewPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button type="button" class="btn btn-icon"
                                            title="View integrator"
                                            onclick='viewCustomer(<?php echo $viewJson; ?>)'>
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-icon"
                                            title="Edit integrator"
                                            onclick='editCustomer(<?php echo json_encode($cust['customer_id']); ?>, <?php echo json_encode($cust['customer_code']); ?>, <?php echo json_encode($cust['name']); ?>, <?php echo json_encode($cust['contact_person'] ?? ''); ?>, <?php echo json_encode($cust['phone'] ?? ''); ?>, <?php echo json_encode($cust['email'] ?? ''); ?>, <?php echo json_encode($cust['address'] ?? ''); ?>, <?php echo json_encode($cust['customer_type'] ?? 'retail'); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($isActive): ?>
                                        <button type="button" class="btn btn-icon" style="color: var(--danger); border-color: var(--danger-bg); background-color: var(--danger-bg);"
                                                title="Deactivate integrator"
                                                onclick="deactivateCustomer(<?php echo (int)$cust['customer_id']; ?>)">
                                            <i class="fas fa-user-slash"></i>
                                        </button>
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

<!-- Modal: View Integrator -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-labelledby="viewCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-eye fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="viewCustomerModalLabel">Integrator Details</h5>
                        <small class="text-muted">Full partner company information</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Company Name</div>
                        <h4 class="fw-bold mb-0" id="v_company_name">—</h4>
                        <div class="small text-muted mt-1" id="v_customer_code">—</div>
                    </div>
                    <span class="badge rounded-pill px-3 py-2" id="v_status_badge">—</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border bg-light h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Representative Name</div>
                            <div class="fw-semibold" id="v_representative">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border bg-light h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Phone</div>
                            <div class="fw-semibold" id="v_phone">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border bg-light h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Email</div>
                            <div class="fw-semibold text-break" id="v_email">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border bg-light h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Partner Type</div>
                            <div class="fw-semibold" id="v_type">—</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 rounded-3 border bg-light">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Address</div>
                            <div class="fw-semibold" id="v_address">—</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 rounded-3 border bg-light">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Registered On</div>
                            <div class="fw-semibold" id="v_created_at">—</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Integrator -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                        <i class="fas fa-building fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="customerModalLabel">Edit Integrator</h5>
                        <small class="text-muted">Update integrator partner details</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="customerForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="customer_id" id="customer_id" value="">
                    <input type="hidden" name="customer_code" id="customer_code" value="">
                    <input type="hidden" name="customer_type" id="customer_type" value="retail">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Company Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Company / integrator name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Representative Name</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Representative name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <input type="tel" inputmode="tel" name="phone" class="form-control" placeholder="Contact number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" inputmode="email" name="email" class="form-control" placeholder="email@example.com">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Optional address"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="save_customer" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#customersTable').DataTable({
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: {
                search: 'Search integrators:',
                lengthMenu: 'Show _MENU_ integrators',
                info: 'Showing _START_ to _END_ of _TOTAL_ integrators',
                infoEmpty: 'No integrators registered',
                zeroRecords: 'No matching integrators found',
                emptyTable: 'No integrators registered yet.'
            }
        });
    }
});

function dash(value) {
    const text = (value === null || value === undefined) ? '' : String(value).trim();
    return text !== '' ? text : '—';
}

function viewCustomer(data) {
    const active = Number(data.is_active) === 1;
    document.getElementById('v_company_name').textContent = dash(data.name);
    document.getElementById('v_customer_code').textContent = data.customer_code ? ('Code: ' + data.customer_code) : '—';
    document.getElementById('v_representative').textContent = dash(data.contact_person);
    document.getElementById('v_phone').textContent = dash(data.phone);
    document.getElementById('v_email').textContent = dash(data.email);
    document.getElementById('v_type').textContent = dash(data.customer_type ? String(data.customer_type).charAt(0).toUpperCase() + String(data.customer_type).slice(1) : '');
    document.getElementById('v_address').textContent = dash(data.address);

    const created = data.created_at ? new Date(data.created_at) : null;
    document.getElementById('v_created_at').textContent = (created && !isNaN(created))
        ? created.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
        : dash(data.created_at);

    const statusBadge = document.getElementById('v_status_badge');
    statusBadge.textContent = active ? 'Active' : 'Inactive';
    statusBadge.className = 'badge rounded-pill px-3 py-2 ' + (active
        ? 'bg-success-subtle text-success border border-success-subtle'
        : 'bg-secondary-subtle text-secondary border');

    const modalEl = document.getElementById('viewCustomerModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function editCustomer(id, code, name, contactPerson, phone, email, address, customerType) {
    document.getElementById('customer_id').value = id;
    document.getElementById('customer_code').value = code || '';
    document.getElementById('customer_type').value = customerType || 'retail';
    document.querySelector('#customerForm [name="name"]').value = name || '';
    document.querySelector('#customerForm [name="contact_person"]').value = contactPerson || '';
    document.querySelector('#customerForm [name="phone"]').value = phone || '';
    document.querySelector('#customerForm [name="email"]').value = email || '';
    document.querySelector('#customerForm [name="address"]').value = address || '';
    const modalEl = document.getElementById('customerModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function deactivateCustomer(id) {
    if (confirm('Are you sure you want to deactivate this integrator? Existing reservations will retain the company reference.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="deactivate_customer" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
