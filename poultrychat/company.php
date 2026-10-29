<?php 
include('database.php'); 
include('db_check.php'); 
include('header.php'); 

if (!in_array($_SESSION['role'], ['admin'])) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Insufficient permissions.</div></div>";
    include('footer.php');
    exit();
}

$message = '';
$error = '';

$uploadDir = 'uploads/logos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (isset($_POST['save_company'])) {
    $company_name = trim($_POST['company_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $additional_info = trim($_POST['additional_info'] ?? '');
    $logo_path = $_POST['current_logo'] ?? '';

    if (empty($company_name)) {
        $error = "Company name is required.";
    } else {
        // Handle Logo Upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['logo']['type'];
            $fileSize = $_FILES['logo']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB max
                $error = "File is too large. Maximum size is 2MB.";
            } else {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $newFilename = 'company_logo_' . time() . '.' . $ext;
                $destination = $uploadDir . $newFilename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
                    $logo_path = $destination;
                } else {
                    $error = "Failed to upload logo.";
                }
            }
        }

        if (empty($error)) {
            try {
                $stmt = $conn->prepare("
                    UPDATE company_profile 
                    SET company_name = ?, address = ?, contact_number = ?, email = ?, additional_info = ?, logo = ? 
                    WHERE id = 1
                ");
                $stmt->execute([
                    $company_name, $address, $contact_number, $email, $additional_info, $logo_path
                ]);
                $message = "Farm profile updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating farm profile: " . $e->getMessage();
            }
        }
    }
}

// Fetch current info
$company = [];
try {
    $stmt = $conn->query("SELECT * FROM company_profile WHERE id = 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        $conn->query("INSERT IGNORE INTO company_profile (id, company_name) VALUES (1, 'Alquizar Poultry Farm')");
        $stmt = $conn->query("SELECT * FROM company_profile WHERE id = 1");
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="row g-4 justify-content-center">
    <div class="col-12 col-md-8">
        <div class="card p-3 p-md-4 shadow-sm border-0 rounded-4">
            <h3 class="section-title mb-4"><i class="fas fa-tractor me-2 text-primary"></i>Farm Profile Setup</h3>

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

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                
                <div class="row align-items-center mb-4">
                    <div class="col-sm-4 text-center mb-3 mb-sm-0">
                        <?php if (!empty($company['logo']) && file_exists($company['logo'])): ?>
                            <img src="<?php echo e($company['logo']); ?>" alt="Farm Logo" class="img-thumbnail rounded-circle mb-2" style="width: 140px; height: 140px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 140px; height: 140px; border: 2px dashed #ccc;">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="current_logo" value="<?php echo e($company['logo'] ?? ''); ?>">
                        
                        <div class="mt-2">
                            <label for="logoUpload" class="form-label fw-bold small">Update Logo</label>
                            <input type="file" id="logoUpload" name="logo" class="form-control form-control-sm" accept="image/jpeg, image/png, image/gif">
                        </div>
                    </div>
                    
                    <div class="col-sm-8">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Farm Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo e($company['company_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" inputmode="email" name="email" class="form-control" value="<?php echo e($company['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact Number</label>
                            <input type="tel" inputmode="tel" name="contact_number" class="form-control" value="<?php echo e($company['contact_number'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Full Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo e($company['address'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Other Relevant Information (TIN, Registry, etc.)</label>
                    <textarea name="additional_info" class="form-control" rows="3"><?php echo e($company['additional_info'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4">Cancel</a>
                    <button type="submit" name="save_company" class="btn btn-primary rounded-pill px-5 fw-bold">
                        <i class="fas fa-save me-2"></i>Save Information
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
