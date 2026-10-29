<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('database.php'); 
include('db_check.php'); 
include_once('auth.php'); 

$loginError = '';
$registerError = '';
$authTab = (isset($_GET['auth']) && $_GET['auth'] === 'register') || (isset($_GET['tab']) && $_GET['tab'] === 'register')
    ? 'register'
    : 'login';
$showAuthModal = isset($_GET['auth']) || isset($_GET['tab']) || isset($_GET['registered']) || isset($_GET['logged_out']);

// ---- Unified Sign In (all roles) on landing page ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portal_login'])) {
    $authTab = 'login';
    $showAuthModal = true;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        if ($username === '' || $password === '') {
            throw new Exception('Please enter both username and password.');
        }
        $stmt = $conn->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid username or password.');
        }
        if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
            throw new Exception('This account has been deactivated. Please contact farm management.');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['customer_id'] = $user['customer_id'] ?? null;
        $role = strtolower($user['role'] ?? '');
        session_write_close();
        header('Location: ' . ($role === 'integrator' ? 'reservations.php' : 'dashboard.php'));
        exit();
    } catch (Exception $e) {
        $loginError = $e->getMessage();
    }
}

// ---- Integrator Create Account on landing page ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['integrator_register'])) {
    $authTab = 'register';
    $showAuthModal = true;
    try {
        $companyName = trim($_POST['company_name'] ?? '');
        $companyCode = strtoupper(trim($_POST['company_code'] ?? ''));
        $repName = trim($_POST['rep_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $username = trim($_POST['reg_username'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirmPassword = $_POST['reg_confirm_password'] ?? '';

        if ($companyName === '') throw new Exception('Company / Integrator Name is required.');
        if ($repName === '') throw new Exception('Representative Name is required.');
        if ($phone === '') throw new Exception('Contact Phone Number is required.');
        if ($address === '') throw new Exception('Company Location / Address is required.');
        if ($username === '') throw new Exception('Username is required.');
        if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters.');
        if ($password !== $confirmPassword) throw new Exception('Passwords do not match.');

        $chkUser = $conn->prepare('SELECT user_id FROM users WHERE username = ? OR email = ?');
        $chkUser->execute([$username, $email]);
        if ($chkUser->fetch()) {
            throw new Exception("Username '{$username}' or Email '{$email}' is already registered. Please choose another or sign in.");
        }

        $chkCust = $conn->prepare("
            SELECT customer_id, name FROM customers
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
               OR (customer_code IS NOT NULL AND customer_code != '' AND LOWER(TRIM(customer_code)) = LOWER(TRIM(?)))
            LIMIT 1
        ");
        $chkCust->execute([$companyName, $companyCode]);
        if ($chkCust->fetch()) {
            throw new Exception("Company / Integrator '{$companyName}' already has an account. Each company is strictly limited to 1 partner account only.");
        }

        if ($companyCode === '') {
            $words = explode(' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $companyName));
            $acronym = '';
            foreach ($words as $w) {
                if ($w !== '') $acronym .= strtoupper($w[0]);
            }
            $companyCode = 'INT-' . substr($acronym, 0, 4) . rand(10, 99);
        }

        $stmtInsCust = $conn->prepare("
            INSERT INTO customers (customer_code, name, contact_person, phone, email, address, customer_type, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'institutional', 1, NOW())
        ");
        $stmtInsCust->execute([$companyCode, $companyName, $repName, $phone, $email, $address]);
        $customerId = (int)$conn->lastInsertId();

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmtInsUser = $conn->prepare("
            INSERT INTO users (username, password, full_name, email, phone, address, role, customer_id, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'integrator', ?, 1, NOW())
        ");
        $stmtInsUser->execute([$username, $hashedPassword, $repName, $email, $phone, $address, $customerId]);
        $newUserId = (int)$conn->lastInsertId();

        // Auto sign-in after successful registration, then go to reservations
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $username;
        $_SESSION['full_name'] = $repName;
        $_SESSION['role'] = 'integrator';
        $_SESSION['customer_id'] = $customerId;
        session_write_close();
        header('Location: reservations.php');
        exit();
    } catch (Exception $e) {
        $registerError = $e->getMessage();
    }
}

// Landing page enforcement: Admin and Staff default to dashboard.php; Integrator remains on index.php
if (isset($_SESSION['user_id'])) {
    $r = strtolower($_SESSION['role'] ?? '');
    if (in_array($r, ['admin', 'staff', 'worker']) && !isset($_GET['public']) && !isset($_GET['preview'])) {
        header("Location: dashboard.php");
        exit();
    }
}

// Handle Status Toggle (POST with CSRF verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && isset($_POST['batch_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        $currentStatus = $_POST['toggle_status'];
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        $batchId = (int)$_POST['batch_id'];
        $stmt = $conn->prepare("UPDATE batches SET status = ? WHERE batch_id = ?");
        $stmt->execute([$newStatus, $batchId]);
    }
    header("Location: index.php");
    exit();
}

// Handle Mortality/Adjustment Record
if (isset($_POST['save_mortality'])) {
    $batch_id = (int)$_POST['batch_id'];
    $qty = (int)$_POST['qty']; // positive for add, negative for loss/mortality
    $reason = trim($_POST['reason']);
    $date = $_POST['date'];
    $userId = $_SESSION['user_id'] ?? null;

    if (record_mortality($conn, $batch_id, $qty, $reason, $date, $userId)) {
        $msg = "Record saved successfully!";
    } else {
        $error = $_SESSION['last_mortality_error'] ?? "Error recording mortality/adjustment. Please try again.";
        unset($_SESSION['last_mortality_error']);
    }
}

// =========================================================================
// REAL DATA INGESTION: MATCHING EXACT RESERVATIONS.PHP METRICS & HOUSE BOARD
// =========================================================================

// 1. KPI Counts from reservations table
$isIntegratorUser = (strtolower($_SESSION['role'] ?? $_SESSION['user_type'] ?? '') === 'integrator');
$currUserId = (int)($_SESSION['user_id'] ?? 0);
$currCustId = (int)($_SESSION['customer_id'] ?? 0);

$integratorCustomer = null;
if ($isIntegratorUser) {
    if ($currCustId <= 0 && $currUserId > 0) {
        $stmtFindC = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
        $stmtFindC->execute([$currUserId]);
        $currCustId = (int)($stmtFindC->fetchColumn() ?: 0);
    }
    if ($currCustId > 0) {
        $stmtCust = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmtCust->execute([$currCustId]);
        $integratorCustomer = $stmtCust->fetch(PDO::FETCH_ASSOC);
    }
    if ($currCustId > 0) {
        $kpi_stmt = $conn->prepare("
            SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
            FROM reservations r 
            WHERE r.status='active' AND (r.customer_id = ? OR r.created_by = ?)
        ");
        $kpi_stmt->execute([$currCustId, $currUserId]);
    } else {
        $kpi_stmt = $conn->prepare("
            SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
            FROM reservations r 
            WHERE r.status='active' AND r.created_by = ?
        ");
        $kpi_stmt->execute([$currUserId]);
    }
} else {
    $kpi_stmt = $conn->query("
        SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
        FROM reservations r 
        WHERE r.status='active'
    ");
}
$kpi_data = $kpi_stmt ? $kpi_stmt->fetch(PDO::FETCH_ASSOC) : [];
$kpi_active_count = (int)($kpi_data['active_count'] ?? 0);
$kpi_reserved_birds = (int)($kpi_data['birds'] ?? 0);

$userActiveRes = null;
if ($isIntegratorUser) {
    $stmtMyRes = $conn->prepare("
        SELECT r.*, h.house_name, b.batch_code, b.current_weight_kg 
        FROM reservations r 
        LEFT JOIN houses h ON r.house_id = h.house_id 
        LEFT JOIN batches b ON (r.batch_id = b.batch_id OR h.current_batch_id = b.batch_id)
        WHERE (r.customer_id = ? OR r.created_by = ?) AND r.status = 'active'
        ORDER BY r.reservation_id DESC LIMIT 1
    ");
    $stmtMyRes->execute([$currCustId, $currUserId]);
    $userActiveRes = $stmtMyRes->fetch(PDO::FETCH_ASSOC);
}

// 2. Fetch Real Houses with Active Batch & Headcount
$housesQuery = "
SELECT 
    h.*,
    b.batch_id as active_batch_id,
    b.batch_code as active_batch_code,
    b.current_quantity as active_headcount,
    b.arrival_date as active_arrival_date,
    b.expected_harvest_date as active_harvest_date,
    b.target_weight_kg as active_target_weight,
    b.current_weight_kg as active_current_weight,
    b.supplier as active_integrator
FROM houses h
LEFT JOIN batches b ON (
    (h.current_batch_id IS NOT NULL AND b.batch_id = h.current_batch_id)
    OR (h.current_batch_id IS NULL AND b.status = 'active' AND b.house_pen = h.house_name)
)
ORDER BY h.house_id ASC
";
$stmtHouses = $conn->query($housesQuery);
$housesList = $stmtHouses ? $stmtHouses->fetchAll(PDO::FETCH_ASSOC) : [];

$totalHouses = count($housesList);
$vacantCount = 0;
$reservedCount = 0;
$growingCount = 0;
$cleaningCount = 0;
$totalAvailableCap = 0;
$totalOccupiedBirds = 0;
$totalCapacityAll = 0;

foreach ($housesList as &$hl) {
    $isOccupied = !empty($hl['active_batch_id']) && (int)$hl['active_headcount'] > 0;
    $activeBId = (int)($hl['active_batch_id'] ?? 0);
    $hId = (int)($hl['house_id'] ?? 0);

    // Fetch active reservations linked to this house or its active batch
    $stmtHouseRes = $conn->prepare("
        SELECT DISTINCT r.*, c.name as customer_name, c.customer_code, c.phone as customer_phone
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE r.status = 'active'
          AND (
              (r.house_id = ?)
              OR (? > 0 AND r.batch_id = ?)
          )
        ORDER BY r.quantity_birds DESC
    ");
    $stmtHouseRes->execute([$hId, $activeBId, $activeBId]);
    $houseReservations = $stmtHouseRes->fetchAll(PDO::FETCH_ASSOC);

    $isReserved = ($hl['status'] === 'reserved' || (!empty($houseReservations) && !$isOccupied));

    $resBirdsSum = 0;
    foreach ($houseReservations as $hResItem) {
        $resBirdsSum += (int)$hResItem['quantity_birds'];
    }
    $activeHeadcount = (int)($hl['active_headcount'] ?? 0);
    $committedBirds = max($resBirdsSum, $activeHeadcount);
    $houseCap = (int)($hl['capacity'] > 0 ? $hl['capacity'] : 5000);
    $remainingCapacity = max(0, $houseCap - $committedBirds);

    $totalOccupiedBirds += $activeHeadcount;
    $totalCapacityAll += $houseCap;

    $hl['house_capacity'] = $houseCap;
    $hl['occupied_heads'] = $activeHeadcount;
    $hl['remaining_capacity'] = $remainingCapacity;

    if ($isOccupied) {
        $hl['computed_status'] = 'growing';
        $hl['status_label'] = 'Growing';
        $growingCount++;
    } elseif ($isReserved) {
        $hl['computed_status'] = 'reserved';
        $hl['status_label'] = 'Reserved';
        $reservedCount++;
    } elseif ($hl['status'] === 'cleaning') {
        $hl['computed_status'] = 'cleaning';
        $hl['status_label'] = 'Cleaning';
        $cleaningCount++;
    } else {
        $hl['computed_status'] = 'vacant';
        $hl['status_label'] = 'Vacant & Available';
        $vacantCount++;
        $totalAvailableCap += $houseCap;
    }

    $firstResCustId = !empty($houseReservations[0]['customer_id']) ? (int)$houseReservations[0]['customer_id'] : 0;
    $firstResCreatedBy = !empty($houseReservations[0]['created_by']) ? (int)$houseReservations[0]['created_by'] : 0;
    $isMyHouse = false;
    if ($isIntegratorUser) {
        if ($currCustId && $firstResCustId === $currCustId) {
            $isMyHouse = true;
        } elseif ($currUserId && $firstResCreatedBy === $currUserId) {
            $isMyHouse = true;
        } elseif (!empty($hl['active_integrator']) && !empty($integratorCustomer['name']) && stripos($hl['active_integrator'], $integratorCustomer['name']) !== false) {
            $isMyHouse = true;
        }
    }
    $hl['is_my_house'] = $isMyHouse;
    $hl['res_birds_sum'] = $resBirdsSum;

    $reservedBy = !empty($houseReservations[0]['customer_name']) ? $houseReservations[0]['customer_name'] : '';
    if (count($houseReservations) > 1) {
        $reservedBy .= ' (+' . (count($houseReservations) - 1) . ')';
    }
    $hl['reserved_by'] = $reservedBy;
}
unset($hl);

// 3. Fetch Real Batches
$stmtBatches = $conn->query("SELECT * FROM batches ORDER BY arrival_date DESC");
$batchRows = $stmtBatches ? $stmtBatches->fetchAll(PDO::FETCH_ASSOC) : [];

$activeBatchesCount = 0;
foreach ($batchRows as $b) {
    if (($b['status'] ?? '') === 'active') {
        $activeBatchesCount++;
    }
}
?>
<?php
$isLoggedIn = isset($_SESSION['user_id']);
$user_role = strtolower($_SESSION['role'] ?? ($isLoggedIn ? 'staff' : 'guest'));
// Logged-in integrators use reservations only (no Farm Overview)
if ($isLoggedIn && $user_role === 'integrator') {
    header('Location: reservations.php');
    exit();
}
$prefillUser = htmlspecialchars($_GET['u'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alquizar Poultry Farm &mdash; Partner &amp; Integrator Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;700;800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/theme-neon-farm.css?v=<?php echo time(); ?>">
    <style>
:root{
  --bg:#050a08;
  --surface:rgba(12,28,22,.72);
  --primary:#3dff9a;
  --accent:#f2b632;
  --cyan:#3ee0ff;
  --text:#eef7f1;
  --muted:#8fa89a;
  --border:rgba(61,255,154,.16);
  --glow:0 0 24px rgba(61,255,154,.28);
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:'Figtree',system-ui,sans-serif;color:var(--text)}
body{
  min-height:100vh;overflow-x:hidden;
  background-color:var(--bg);
  background-image:
    radial-gradient(ellipse 70% 50% at 12% 8%, rgba(61,255,154,.16), transparent 55%),
    radial-gradient(ellipse 55% 45% at 88% 12%, rgba(242,182,50,.12), transparent 50%),
    radial-gradient(ellipse 50% 40% at 70% 85%, rgba(62,224,255,.1), transparent 55%),
    linear-gradient(165deg,#050a08 0%,#081510 45%,#060d0a 100%);
  background-attachment:fixed;
}
.shell{min-height:100vh;display:flex;flex-direction:column}

/* ===== TOP NAV ===== */
.topnav{
  display:flex;align-items:center;justify-content:space-between;gap:1rem;
  padding:.85rem 2rem;position:relative;z-index:5;
  background:rgba(4,14,10,.88);
  backdrop-filter:blur(18px) saturate(140%);
  -webkit-backdrop-filter:blur(18px) saturate(140%);
  border-bottom:1px solid var(--border);
  box-shadow:0 8px 28px rgba(0,0,0,.35);
}
.brand{display:flex;align-items:center;gap:.75rem;text-decoration:none;min-width:0}
.brand-logo{
  width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;
  background:#0a0a0a;border:2px solid rgba(242,182,50,.45);
  box-shadow:0 0 16px rgba(242,182,50,.25);
}
.brand-name{
  font-family:'Bricolage Grotesque',sans-serif;
  color:#fff;font-size:.95rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
  line-height:1.15;
}
.nav-actions{display:flex;align-items:center;gap:.55rem;flex-shrink:0}
.btn-nav-ghost,.btn-nav-gold{
  display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1.05rem;border-radius:999px;
  font-size:.84rem;font-weight:700;text-decoration:none;transition:.15s ease;white-space:nowrap;
}
.btn-nav-ghost{
  color:var(--text);border:1.5px solid rgba(61,255,154,.35);background:rgba(61,255,154,.06);
}
.btn-nav-ghost:hover{background:rgba(61,255,154,.14);color:#fff;border-color:rgba(61,255,154,.55)}
.btn-nav-gold{
  color:#06140f;background:linear-gradient(135deg,#3dff9a,#f2b632);border:0;
  box-shadow:var(--glow);
}
.btn-nav-gold:hover{filter:brightness(1.06);color:#06140f}

/* ===== HERO ===== */
.hero-wrap{
  position:relative;flex:1;display:flex;align-items:center;
  min-height:calc(100vh - 168px);
  background:
    linear-gradient(105deg, rgba(5,10,8,.96) 0%, rgba(8,21,16,.9) 36%, rgba(6,20,14,.55) 62%, rgba(5,10,8,.2) 100%),
    url("uploads/backgrounds/partner_portal_bg.jpg") center / cover no-repeat;
}
.hero-inner{
  width:100%;max-width:1200px;margin:0 auto;padding:3rem 2rem 3.5rem;
}
.badge-hub{
  display:inline-flex;align-items:center;gap:.45rem;
  background:rgba(61,255,154,.1);border:1px solid rgba(61,255,154,.35);
  color:var(--text);padding:.4rem .9rem;border-radius:999px;font-size:.78rem;font-weight:700;
  margin-bottom:1.15rem;box-shadow:0 0 18px rgba(61,255,154,.12);
}
.badge-hub .dot{
  width:8px;height:8px;border-radius:50%;background:var(--primary);
  box-shadow:0 0 0 0 rgba(61,255,154,.55);animation:pulse 2s infinite;
}
@keyframes pulse{
  0%{box-shadow:0 0 0 0 rgba(61,255,154,.55)}
  70%{box-shadow:0 0 0 8px rgba(61,255,154,0)}
  100%{box-shadow:0 0 0 0 rgba(61,255,154,0)}
}
.hero-inner h1{
  margin:0 0 1rem;max-width:18ch;
  font-family:'Bricolage Grotesque',sans-serif;
  font-size:clamp(2.1rem,4.2vw,3.4rem);font-weight:800;line-height:1.12;
  letter-spacing:-.03em;color:#fff;
}
.hero-inner h1 .accent{color:var(--accent);text-shadow:0 0 28px rgba(242,182,50,.35)}
.hero-inner .copy{
  margin:0 0 1.6rem;max-width:34rem;font-size:1.05rem;line-height:1.55;
  color:rgba(238,247,241,.88);font-weight:500;
}
.ctas{display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1.75rem}
.cta-gold,.cta-line{
  display:inline-flex;align-items:center;gap:.5rem;padding:.9rem 1.35rem;border-radius:999px;
  font-size:.95rem;font-weight:700;text-decoration:none;min-height:50px;transition:.15s ease;
}
.cta-gold{
  background:linear-gradient(135deg,#3dff9a,#f2b632);color:#06140f;border:0;
  box-shadow:0 12px 28px rgba(61,255,154,.28);
}
.cta-gold:hover{filter:brightness(1.05);color:#06140f;transform:translateY(-1px)}
.cta-line{
  background:rgba(61,255,154,.06);color:#fff;border:1.8px solid rgba(61,255,154,.4);
}
.cta-line:hover{background:rgba(61,255,154,.14);color:#fff;border-color:rgba(61,255,154,.65)}
.trust{
  display:flex;flex-wrap:wrap;align-items:center;gap:.15rem 0;color:rgba(238,247,241,.9);
}
.trust span{
  display:inline-flex;align-items:center;gap:.4rem;padding:.2rem 1rem .2rem 0;
  font-size:.82rem;font-weight:600;white-space:nowrap;position:relative;
}
.trust span:not(:last-child){padding-right:1.1rem;margin-right:.15rem}
.trust span:not(:last-child)::after{
  content:"";position:absolute;right:0;top:18%;bottom:18%;width:1px;background:rgba(61,255,154,.22);
}
.trust i{color:var(--primary);font-size:.9rem}

/* ===== FEATURES BAR ===== */
.features{
  background:rgba(12,28,22,.72);
  border-top:1px solid var(--border);
  backdrop-filter:blur(14px);
  padding:1.35rem 2rem;
  display:grid;grid-template-columns:repeat(3,1fr);gap:0;
}
.feat{
  display:flex;align-items:center;gap:.9rem;padding:.55rem 1.25rem;position:relative;
}
.feat:not(:last-child)::after{
  content:"";position:absolute;right:0;top:18%;bottom:18%;width:1px;background:rgba(61,255,154,.14);
}
.feat-ico{
  width:46px;height:46px;border-radius:50%;flex-shrink:0;
  background:rgba(61,255,154,.12);color:var(--primary);
  border:1px solid rgba(61,255,154,.28);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:1.15rem;box-shadow:0 0 16px rgba(61,255,154,.12);
}
.feat strong{display:block;font-size:.95rem;font-weight:800;color:var(--text);line-height:1.2;font-family:'Bricolage Grotesque',sans-serif}
.feat em{display:block;font-style:normal;font-size:.8rem;color:var(--muted);margin-top:.15rem;font-weight:500}

@media (max-width:900px){
  .features{grid-template-columns:1fr;gap:.35rem;padding:1rem 1.25rem}
  .feat:not(:last-child)::after{display:none}
  .feat{padding:.5rem 0}
  .hero-inner{padding:2rem 1.25rem 2.5rem}
  .trust span:not(:last-child)::after{display:none}
}
@media (max-width:640px){
  .topnav{padding:.75rem 1rem}
  .brand-name{font-size:.78rem}
  .btn-nav-ghost span,.btn-nav-gold span{display:none}
  .hero-wrap{background-attachment:scroll;min-height:auto}
}

/* ===== AUTH MODAL ===== */
.auth-overlay{
  position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;
  padding:1rem;background:rgba(5,10,8,.72);backdrop-filter:blur(8px);
}
.auth-overlay.open{display:flex}
.auth-card{
  width:100%;max-width:440px;max-height:92vh;overflow:auto;
  background:rgba(12,28,22,.94);border:1px solid rgba(61,255,154,.22);
  border-radius:24px;padding:1.5rem 1.45rem 1.25rem;
  box-shadow:0 28px 64px rgba(0,0,0,.5),0 0 40px rgba(61,255,154,.08);position:relative;
  color:var(--text);
}
.auth-close{
  position:absolute;top:.75rem;right:.75rem;width:36px;height:36px;border:0;border-radius:10px;
  background:rgba(61,255,154,.1);color:var(--text);cursor:pointer;border:1px solid rgba(61,255,154,.2);
}
.auth-close:hover{background:rgba(61,255,154,.2)}
.auth-card h3{margin:0 0 .35rem;font-size:1.2rem;font-weight:800;color:#fff;font-family:'Bricolage Grotesque',sans-serif}
.auth-card .sub{margin:0 0 1rem;font-size:.82rem;color:var(--muted)}
.auth-tabs{display:grid;grid-template-columns:1fr 1fr;gap:.45rem;margin-bottom:1rem}
.auth-tabs button{
  border-radius:999px;border:1.5px solid rgba(61,255,154,.25);background:rgba(0,0,0,.25);color:var(--muted);
  font-weight:700;font-size:.82rem;padding:.55rem .5rem;cursor:pointer;font-family:inherit;
}
.auth-tabs button.on{
  background:rgba(61,255,154,.18);border-color:rgba(61,255,154,.5);color:var(--primary);
  box-shadow:0 0 18px rgba(61,255,154,.18);
}
.auth-pane{display:none}
.auth-pane.on{display:block}
.auth-lbl{display:block;font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--primary);margin-bottom:.35rem}
.auth-field{position:relative;margin-bottom:.85rem}
.auth-field .ico{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}
.auth-field input,.auth-card .form-control{
  width:100%;border:1px solid rgba(61,255,154,.2);background:rgba(0,0,0,.35);border-radius:12px;
  padding:.75rem .9rem .75rem 2.45rem;font-size:.94rem;font-family:inherit;outline:none;color:var(--text);
}
.auth-card .form-control{padding-left:.9rem}
.auth-field input:focus,.auth-card .form-control:focus{
  border-color:rgba(61,255,154,.55);background:rgba(0,0,0,.45);
  box-shadow:0 0 0 3px rgba(61,255,154,.14);
}
.auth-field .eye{
  position:absolute;right:.45rem;top:50%;transform:translateY(-50%);border:0;background:transparent;
  width:36px;height:36px;color:var(--muted);border-radius:8px;cursor:pointer;
}
.auth-go{
  width:100%;border:0;border-radius:12px;
  background:linear-gradient(135deg,#3dff9a,#1aa868);
  color:#06140f;font-weight:800;font-size:.95rem;padding:.85rem 1rem;cursor:pointer;font-family:inherit;
  box-shadow:0 12px 24px rgba(61,255,154,.22);
}
.auth-go:hover{filter:brightness(1.05)}
.auth-alert{border-radius:12px;font-size:.8rem;padding:.65rem .8rem;margin-bottom:.85rem;display:flex;gap:.5rem;align-items:flex-start}
.auth-alert.ok{background:rgba(61,255,154,.12);color:#3dff9a;border:1px solid rgba(61,255,154,.3)}
.auth-alert.err{background:rgba(255,77,109,.12);color:#ff8da3;border:1px solid rgba(255,77,109,.35)}
.auth-hint{font-size:.72rem;color:var(--muted);margin-top:.2rem}
    </style>
</head>
<body data-theme="neon-farm">
<div class="shell">
    <header class="topnav">
        <a class="brand" href="index.php">
      <img class="brand-logo" src="uploads/logos/alquizar_chicken_logo.png" alt="Alquizar Poultry Farm logo">
      <span class="brand-name">Alquizar Poultry Farm</span>
    </a>
        <div class="nav-actions">
            <?php if ($isLoggedIn): ?>
                <?php if ($user_role === 'integrator'): ?>
                    <a class="btn-nav-ghost" href="reservations.php"><i class="fas fa-calendar-check"></i> <span>My Reservations</span></a>
                <?php else: ?>
                    <a class="btn-nav-ghost" href="dashboard.php"><i class="fas fa-gauge-high"></i> <span>Dashboard</span></a>
                <?php endif; ?>
        <a class="btn-nav-gold" href="logout.php"><i class="fas fa-right-from-bracket"></i> <span>Sign Out</span></a>
            <?php else: ?>
        <a class="btn-nav-ghost" href="index.php?auth=login" data-open-auth="login"><i class="fas fa-user"></i> <span>Sign In</span></a>
        <a class="btn-nav-gold" href="index.php?auth=register" data-open-auth="register"><i class="fas fa-user-plus"></i> <span>Create Account</span></a>
            <?php endif; ?>
        </div>
    </header>

  <section class="hero-wrap" aria-label="Partner hub">
    <div class="hero-inner">
      <div class="badge-hub"><span class="dot"></span> Commercial Partner &amp; Integrator Hub</div>
      <h1>Modern Commercial Broiler Management &amp; <span class="accent">Partner Capacity</span></h1>
      <p class="copy">Access verified open capacity, real-time flock health transparency, and seamless reservation workflows.</p>
      <div class="ctas">
                <?php if ($user_role === 'integrator'): ?>
          <a class="cta-gold" href="reservations.php"><i class="fas fa-calendar-check"></i> Book Your Reservation <i class="fas fa-chevron-right"></i></a>
          <a class="cta-line" href="reservations.php"><i class="fas fa-user"></i> My Bookings</a>
                <?php elseif (!$isLoggedIn): ?>
          <a class="cta-gold" href="index.php?auth=register" data-open-auth="register"><i class="fas fa-calendar-check"></i> Book Your Reservation <i class="fas fa-chevron-right"></i></a>
          <a class="cta-line" href="index.php?auth=login" data-open-auth="login"><i class="fas fa-user"></i> Sign In</a>
                <?php else: ?>
          <a class="cta-gold" href="dashboard.php"><i class="fas fa-gauge-high"></i> Dashboard <i class="fas fa-chevron-right"></i></a>
          <a class="cta-line" href="reservations.php"><i class="fas fa-calendar-days"></i> Reservations</a>
                <?php endif; ?>
            </div>
      <div class="trust" aria-label="Trust signals">
        <span><i class="fas fa-shield-halved"></i> Secure Access</span>
        <span><i class="fas fa-chart-column"></i> Real-Time Monitoring</span>
        <span><i class="fas fa-handshake"></i> Trusted Partnership</span>
        <span><i class="fas fa-heart"></i> Healthy Flocks</span>
      </div>
            </div>
        </section>

  <section class="features" aria-label="Value propositions">
    <div class="feat">
      <div class="feat-ico"><i class="fas fa-kiwi-bird"></i></div>
      <div>
        <strong>Optimized Production</strong>
        <em>Maximize your farm's performance</em>
      </div>
    </div>
    <div class="feat">
      <div class="feat-ico"><i class="fas fa-chart-line"></i></div>
      <div>
        <strong>Reliable Partnership</strong>
        <em>Grow together with confidence</em>
      </div>
    </div>
    <div class="feat">
      <div class="feat-ico"><i class="fas fa-shield-halved"></i></div>
      <div>
        <strong>Transparent Operations</strong>
        <em>Real-time insights, total peace of mind</em>
      </div>
    </div>
  </section>
            </div>

<?php if (!$isLoggedIn): ?>
<div class="auth-overlay<?php echo !empty($showAuthModal) ? ' open' : ''; ?>" id="authOverlay" aria-hidden="<?php echo !empty($showAuthModal) ? 'false' : 'true'; ?>">
  <div class="auth-card" role="dialog" aria-modal="true" aria-labelledby="authTitle">
    <button type="button" class="auth-close" id="authClose" aria-label="Close"><i class="fas fa-xmark"></i></button>
    <h3 id="authTitle">Partner Portal Access</h3>
    <p class="sub">Sign in with your account, or create an integrator partner account.</p>

    <div class="auth-tabs" role="tablist">
      <button type="button" class="<?php echo $authTab === 'login' ? 'on' : ''; ?>" data-auth-tab="login" role="tab">
        <i class="fas fa-right-to-bracket me-1"></i>Sign In
      </button>
      <button type="button" class="<?php echo $authTab === 'register' ? 'on' : ''; ?>" data-auth-tab="register" role="tab">
        <i class="fas fa-user-plus me-1"></i>Create Account
      </button>
                        </div>

    <div class="auth-pane <?php echo $authTab === 'login' ? 'on' : ''; ?>" id="authLoginPane">
      <?php if (!empty($_GET['registered'])): ?>
        <div class="auth-alert ok"><i class="fas fa-check-circle"></i><div>Account created successfully! Please sign in.</div></div>
                                <?php endif; ?>
      <?php if (!empty($_GET['logged_out'])): ?>
        <div class="auth-alert ok"><i class="fas fa-check-circle"></i><div>You have been signed out safely.</div></div>
                                <?php endif; ?>
      <?php if ($loginError !== ''): ?>
        <div class="auth-alert err"><i class="fas fa-exclamation-circle"></i><div><?php echo htmlspecialchars($loginError); ?></div></div>
                            <?php endif; ?>

      <form method="POST" action="index.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="portal_login" value="1">
        <label class="auth-lbl" for="loginUsername">Username</label>
        <div class="auth-field">
          <i class="fas fa-user ico"></i>
          <input type="text" name="username" id="loginUsername" placeholder="e.g. your_username" value="<?php echo $prefillUser; ?>" required <?php echo $prefillUser === '' ? 'autofocus' : ''; ?>>
                        </div>
        <label class="auth-lbl" for="loginPassword">Password</label>
        <div class="auth-field">
          <i class="fas fa-lock ico"></i>
          <input type="password" name="password" id="loginPassword" placeholder="Enter your password" required <?php echo $prefillUser !== '' ? 'autofocus' : ''; ?>>
          <button type="button" class="eye" id="toggleLoginPass" aria-label="Show password"><i class="fas fa-eye" id="toggleLoginPassIcon"></i></button>
        </div>
        <button type="submit" class="auth-go">Sign In to Portal <i class="fas fa-arrow-right-to-bracket ms-1"></i></button>
      </form>
    </div>

    <div class="auth-pane <?php echo $authTab === 'register' ? 'on' : ''; ?>" id="authRegisterPane">
      <?php if ($registerError !== ''): ?>
        <div class="auth-alert err"><i class="fas fa-exclamation-circle"></i><div><?php echo htmlspecialchars($registerError); ?></div></div>
                <?php endif; ?>
      <form method="POST" action="index.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="integrator_register" value="1">
        <label class="auth-lbl">Company / Integrator Name *</label>
        <div class="auth-field"><i class="fas fa-building ico"></i>
          <input type="text" name="company_name" placeholder="e.g. Bounty Fresh Food Inc." required>
            </div>
        <div class="auth-hint" style="margin:-.45rem 0 .75rem">Your reservations will be bound to this company.</div>

        <div class="row g-2 mb-2">
          <div class="col-7">
            <label class="auth-lbl">Representative Name *</label>
            <input type="text" name="rep_name" class="form-control" placeholder="e.g. John Doe" required>
            </div>
          <div class="col-5">
            <label class="auth-lbl">Company Code</label>
            <input type="text" name="company_code" class="form-control" placeholder="e.g. INT-BFF">
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="auth-lbl">Phone *</label>
            <input type="tel" name="phone" class="form-control" placeholder="09XX-XXX-XXXX" required>
          </div>
          <div class="col-6">
            <label class="auth-lbl">Email</label>
            <input type="email" name="email" class="form-control" placeholder="partner@company.com">
          </div>
        </div>
        <label class="auth-lbl">Company Location / Address *</label>
        <div class="auth-field"><i class="fas fa-location-dot ico"></i>
          <input type="text" name="address" placeholder="e.g. South Cotabato Processing Facility" required>
        </div>
        <label class="auth-lbl">Portal Username *</label>
        <div class="auth-field"><i class="fas fa-user ico"></i>
          <input type="text" name="reg_username" placeholder="e.g. bounty_rep" required>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="auth-lbl">Password *</label>
            <input type="password" name="reg_password" class="form-control" placeholder="Min 6 chars" required>
          </div>
          <div class="col-6">
            <label class="auth-lbl">Confirm *</label>
            <input type="password" name="reg_confirm_password" class="form-control" placeholder="Re-type" required>
          </div>
        </div>
        <button type="submit" class="auth-go"><i class="fas fa-user-plus me-1"></i>Create Integrator Account</button>
      </form>
    </div>
  </div>
</div>
<script>
(function () {
  var overlay = document.getElementById('authOverlay');
  if (!overlay) return;
  var loginPane = document.getElementById('authLoginPane');
  var regPane = document.getElementById('authRegisterPane');
  var tabs = overlay.querySelectorAll('[data-auth-tab]');

  function openAuth(tab) {
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    tabs.forEach(function (t) { t.classList.toggle('on', t.getAttribute('data-auth-tab') === tab); });
    loginPane.classList.toggle('on', tab === 'login');
    regPane.classList.toggle('on', tab === 'register');
    document.body.style.overflow = 'hidden';
  }
  function closeAuth() {
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', 'index.php');
    }
  }

  document.querySelectorAll('[data-open-auth]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openAuth(el.getAttribute('data-open-auth') || 'login');
    });
  });
  tabs.forEach(function (t) {
    t.addEventListener('click', function () { openAuth(t.getAttribute('data-auth-tab')); });
  });
  document.getElementById('authClose').addEventListener('click', closeAuth);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeAuth(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) closeAuth(); });

  var eye = document.getElementById('toggleLoginPass');
  var pass = document.getElementById('loginPassword');
  var icon = document.getElementById('toggleLoginPassIcon');
  if (eye && pass && icon) {
    eye.addEventListener('click', function () {
      var show = pass.type === 'password';
      pass.type = show ? 'text' : 'password';
      icon.classList.toggle('fa-eye', !show);
      icon.classList.toggle('fa-eye-slash', show);
    });
  }
})();
</script>
<?php endif; ?>
</body>
</html>
