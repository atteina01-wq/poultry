<?php 
include('auth.php'); 
include('csrf.php'); 

$current_page = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']);
$user_role = strtolower($_SESSION['role'] ?? ($isLoggedIn ? 'staff' : 'guest'));
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Guest Visitor');
$username = htmlspecialchars($_SESSION['username'] ?? 'guest');
$user_initial = strtoupper(substr(trim($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'G')), 0, 1) ?: 'G');
$role_title = ($user_role === 'admin') ? 'FARM OWNER / MANAGEMENT' : (($user_role === 'integrator') ? 'INTEGRATOR / PARTNER COMPANY' : (($user_role === 'worker') ? 'FARM WORKER / FIELD HAND' : ($isLoggedIn ? 'FARM STAFF / OPERATOR' : 'COMMERCIAL PARTNER PORTAL')));

// Integrator: reservations is home — no Farm Overview / other pages
if ($user_role === 'integrator') {
    if ($current_page === 'index.php') {
        header('Location: reservations.php');
        exit();
    }
    if (!in_array($current_page, ['reservations.php', 'logout.php'], true)) {
        header('Location: reservations.php');
        exit();
    }
}

// Determine Page Title
$page_titles = [
    'index.php'        => 'Farm Overview & Quick Links',
    'dashboard.php'    => 'Dashboard & Farm Analytics',
    'batch.php'        => 'Batch Management',
    'inventory.php'    => 'Inventory & Supplies',
    'feed.php'         => 'Feed & Consumption Monitoring',
    'customers.php'    => 'Integrator Directory',
    'reservations.php' => ($user_role === 'worker') ? 'Reservation & Pen Management' : 'Reservation Management',
    'expenses.php'     => 'Expenses Ledger',
    'healthrecords.php'=> 'Flock Health Records',
    'mortality.php'    => 'Mortality & Batch Tracker',
    'activities.php'   => 'Farm Operations & Worker Audit Trail',
    'reports.php'      => 'Centralized Reports',
    'users.php'        => 'User Administration',
    'audit_logs.php'   => 'System Audit Logs'
];
$active_title = $page_titles[$current_page] ?? 'Alquizar Poultry Farm';

// Fetch notifications for the current authenticated user
$headerNotifs = [];
$unreadNotifCount = 0;
if ($isLoggedIn) {
    try {
        if (!isset($conn)) {
            include_once('database.php');
        }
        $userIdCurrent = (int)$_SESSION['user_id'];
        if ($user_role === 'admin') {
            $stmtNotifH = $conn->prepare("
                SELECT * FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL) 
                ORDER BY notification_id DESC 
                LIMIT 20
            ");
            $stmtNotifH->execute([$userIdCurrent]);
        } elseif ($user_role === 'integrator') {
            $userCustId = (int)($_SESSION['customer_id'] ?? 0);
            if ($userCustId <= 0) {
                $stmtFindCust = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
                $stmtFindCust->execute([$userIdCurrent]);
                $userCustId = (int)($stmtFindCust->fetchColumn() ?: 0);
            }
            if ($userCustId > 0) {
                $stmtNotifH = $conn->prepare("
                    SELECT DISTINCT * FROM notifications 
                    WHERE user_id = ? 
                       OR user_id IN (SELECT user_id FROM users WHERE customer_id = ?)
                    ORDER BY notification_id DESC 
                    LIMIT 20
                ");
                $stmtNotifH->execute([$userIdCurrent, $userCustId]);
            } else {
                $stmtNotifH = $conn->prepare("
                    SELECT * FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY notification_id DESC 
                    LIMIT 20
                ");
                $stmtNotifH->execute([$userIdCurrent]);
            }
        } else {
            $stmtNotifH = $conn->prepare("
                SELECT * FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL) 
                ORDER BY notification_id DESC 
                LIMIT 20
            ");
            $stmtNotifH->execute([$userIdCurrent]);
        }
        $headerNotifs = $stmtNotifH->fetchAll(PDO::FETCH_ASSOC);
        foreach ($headerNotifs as $hn) {
            if (($hn['status'] ?? 'unread') === 'unread') {
                $unreadNotifCount++;
            }
        }
    } catch (\Exception $exH) {
        $headerNotifs = [];
        $unreadNotifCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $active_title; ?> — Alquizar Poultry Farm</title>
    <!-- Google Fonts: Bricolage Grotesque & Figtree -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;700;800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Farm Shared Theme CSS -->
    <link rel="stylesheet" href="css/theme.css?v=<?php echo time(); ?>">
    <?php
    // Neon glass ops theme for farm roles + integrator partner portal
    $useNeonFarmTheme = in_array($user_role, ['admin', 'staff', 'worker', 'integrator'], true);
    ?>
    <?php if ($useNeonFarmTheme): ?>
    <!-- Shared: Neon Glass Ops Theme (poultry-branded) -->
    <link rel="stylesheet" href="css/theme-neon-farm.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <style>
    /* Integrator Partner Portal — top bar only (no page navigation links) */
    .integrator-layout {
        display: block !important;
        min-height: 100vh;
        background-color: var(--bg);
    }
    .integrator-layout .page-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        min-height: calc(100vh - 65px);
    }
    .integrator-navbar {
        background-color: #123524 !important;
        background-image: linear-gradient(180deg, #1c4a33 0%, #123524 100%) !important;
        box-shadow: 0 4px 18px rgba(18, 53, 36, 0.3);
        border-bottom: 1px solid rgba(228, 224, 212, 0.12);
        padding: 0.65rem 1rem;
        z-index: 1030;
    }
    body[data-theme="neon-farm"] .integrator-navbar {
        background: rgba(4, 14, 10, 0.92) !important;
        background-image: none !important;
        backdrop-filter: blur(18px) saturate(140%);
        -webkit-backdrop-filter: blur(18px) saturate(140%);
        border-bottom: 1px solid rgba(61, 255, 154, 0.16) !important;
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
    }
    body[data-theme="neon-farm"] .integrator-layout {
        background-color: transparent !important;
    }
    .integrator-navbar .brand-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #0a0a0a;
        color: #123524;
        display: block;
        flex-shrink: 0;
        object-fit: cover;
        padding: 0;
        overflow: hidden;
        border: 1.5px solid rgba(242, 182, 50, 0.45);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }
    .integrator-navbar .brand-title {
        font-family: 'Bricolage Grotesque', sans-serif;
        font-weight: 700;
        font-size: 16px;
        color: #ffffff;
        line-height: 1.2;
    }
    .integrator-navbar .brand-sub {
        font-family: 'Figtree', sans-serif;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        letter-spacing: 0.3px;
    }
    .integrator-avatar-badge {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background-color: #f2b632;
        color: #123524;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13.5px;
        flex-shrink: 0;
    }
    body[data-theme="neon-farm"] .integrator-avatar-badge {
        background: linear-gradient(135deg, #3dff9a, #f2b632);
        color: #06140f;
        box-shadow: 0 0 16px rgba(61, 255, 154, 0.35);
    }
    @media (max-width: 991.98px) {
        .integrator-navbar .navbar-collapse {
            background: #123524;
            border-radius: 14px;
            padding: 1rem;
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        body[data-theme="neon-farm"] .integrator-navbar .navbar-collapse {
            background: rgba(6, 20, 14, 0.96);
            border-color: rgba(61, 255, 154, 0.18);
        }
    }
    </style>
</head>
<body<?php echo !empty($useNeonFarmTheme) ? ' data-theme="neon-farm"' : ''; ?>>

<?php if ($user_role === 'integrator' || !$isLoggedIn): ?>
    <!-- ========================================================================= -->
    <!-- REAL INTEGRATOR & GUEST PORTAL: CLEAN TOP NAVBAR ONLY, NO SIDEBAR, MOBILE-FRIENDLY -->
    <!-- ========================================================================= -->
    <nav class="navbar integrator-navbar sticky-top">
        <div class="container-fluid px-2 px-md-4 d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <!-- Brand only (no page navigation) -->
            <a class="navbar-brand d-flex align-items-center gap-2 text-white py-1 me-2 mb-0" href="<?php echo ($isLoggedIn && $user_role === 'integrator') ? 'reservations.php' : 'index.php'; ?>">
                <img src="uploads/logos/alquizar_chicken_logo.png" alt="Alquizar Poultry Farm" class="brand-icon-box" style="object-fit:cover;padding:0;overflow:hidden;">
                <div>
                    <div class="brand-title">Alquizar Poultry Farm</div>
                    <div class="brand-sub d-none d-sm-flex align-items-center gap-1">
                        <span>Partner & Integrator Portal</span>
                        <span class="badge bg-success-subtle text-success py-0 px-1 border border-success-subtle" style="font-size: 0.65rem;">PARTNER</span>
                    </div>
                </div>
            </a>

            <!-- Account actions only -->
            <div class="d-flex align-items-center gap-2 ms-auto">
                <?php if ($isLoggedIn && $user_role === 'integrator'): ?>
                <button type="button" class="btn btn-sm btn-outline-light position-relative rounded-pill px-2 px-md-3 py-1 shadow-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#notificationCenterModal" title="Notifications & Messages">
                    <i class="fas fa-bell"></i>
                    <span class="d-none d-md-inline">Messages</span>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.68rem;">
                            <?php echo $unreadNotifCount > 9 ? '9+' : $unreadNotifCount; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="d-none d-sm-flex align-items-center gap-2">
                    <div class="integrator-avatar-badge">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="text-start">
                        <span class="text-white fw-semibold small d-block"><?php echo $full_name; ?></span>
                        <small class="text-white-50" style="font-size: 0.70rem;">Integrator Partner</small>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal" title="Sign Out">
                    <i class="fas fa-arrow-right-from-bracket me-1"></i><span class="d-none d-sm-inline">Sign Out</span>
                </button>
                <?php else: ?>
                <a href="index.php?auth=login" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 fw-semibold shadow-sm">
                    <i class="fas fa-right-to-bracket me-1"></i>Sign In
                </a>
                <a href="index.php?auth=register" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold shadow-sm">
                    <i class="fas fa-user-plus me-1"></i>Create Account
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Page Layout for Integrator & Guest (Full-Width, No Sidebar) -->
    <div id="app-layout" class="integrator-layout">
        <div class="page-wrapper">
            <main class="main-content py-3 px-2 px-md-4">
<?php else: ?>
    <!-- ========================================================================= -->
    <!-- FARM ADMIN & STAFF: EXACT SAME COMPLETE OPERATIONS SIDEBAR LAYOUT UNCHANGED -->
    <!-- ========================================================================= -->
    <div id="app-layout">
        <!-- Mobile Sidebar Backdrop Overlay -->
        <div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

        <!-- LEFT FARM SIDEBAR -->
        <aside id="sidebar" class="sidebar">
            <!-- Farm Identity Header -->
            <div class="sidebar-brand">
                <a href="dashboard.php" class="brand-wrapper">
                    <div class="brand-icon">
                        <img src="uploads/logos/alquizar_chicken_logo.png" alt="Alquizar Poultry Farm" class="brand-logo-img">
                    </div>
                    <div class="brand-details">
                        <span class="brand-name">Alquizar Poultry Farm</span>
                        <span class="brand-location">Tampakan, South Cotabato</span>
                    </div>
                </a>
                <button id="sidebarCloseBtn" class="sidebar-close-btn" type="button" aria-label="Close Sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Scrollable Navigation Sections -->
            <div class="sidebar-nav-container">
                <!-- SECTION 1: MAIN OPERATIONS -->
                <div class="nav-section-title">Main Operations</div>
                <ul class="sidebar-menu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'batch.php') ? 'active' : ''; ?>" href="batch.php">
                            <i class="nav-icon fas fa-layer-group"></i>
                            <span class="nav-text">Batch Management</span>
                        </a>
                    </li>
                    <?php if ($user_role === 'staff' || $user_role === 'admin' || $user_role === 'worker'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'activities.php') ? 'active' : ''; ?>" href="activities.php">
                            <i class="nav-icon fas fa-clipboard-check"></i>
                            <span class="nav-text">Farm Operations</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($user_role === 'worker'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'mortality.php') ? 'active' : ''; ?>" href="mortality.php">
                            <i class="nav-icon fas fa-skull"></i>
                            <span class="nav-text">Mortality</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- SECTION 2: FLOCK HEALTH -->
                <div class="nav-section-title">Flock Health</div>
                <ul class="sidebar-menu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'healthrecords.php') ? 'active' : ''; ?>" href="healthrecords.php">
                            <i class="nav-icon fas fa-heart-pulse"></i>
                            <span class="nav-text">Health Records</span>
                        </a>
                    </li>
                    <?php if ($user_role === 'staff' || $user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'mortality.php') ? 'active' : ''; ?>" href="mortality.php">
                            <i class="nav-icon fas fa-skull"></i>
                            <span class="nav-text">Mortality</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- SECTION 3: INVENTORY & RESOURCES -->
                <div class="nav-section-title">Inventory & Resources</div>
                <ul class="sidebar-menu">
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>" href="inventory.php">
                            <i class="nav-icon fas fa-warehouse"></i>
                            <span class="nav-text">Stock Inventory</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'feed.php') ? 'active' : ''; ?>" href="feed.php">
                            <i class="nav-icon fas fa-wheat-awn"></i>
                            <span class="nav-text">Feed Consumption</span>
                        </a>
                    </li>
                </ul>

                <!-- SECTION 4: INTEGRATORS & RESERVATIONS -->
                <div class="nav-section-title"><?php echo ($user_role === 'worker') ? 'Reservations & Sanitation' : 'Integrators & Reservations'; ?></div>
                <ul class="sidebar-menu">
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>" href="customers.php">
                            <i class="nav-icon fas fa-building"></i>
                            <span class="nav-text">Integrator</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'reservations.php') ? 'active' : ''; ?>" href="reservations.php">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <span class="nav-text"><?php echo ($user_role === 'worker') ? 'Reservation' : 'Reservations'; ?></span>
                        </a>
                    </li>
                </ul>

                <?php if ($user_role === 'admin'): ?>

                <!-- SECTION 5: FINANCE & REPORTS -->
                <div class="nav-section-title">Finance & Reports</div>
                <ul class="sidebar-menu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'expenses.php') ? 'active' : ''; ?>" href="expenses.php">
                            <i class="nav-icon fas fa-wallet"></i>
                            <span class="nav-text">Expenses</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                            <i class="nav-icon fas fa-chart-column"></i>
                            <span class="nav-text">Reports & Statistics</span>
                        </a>
                    </li>
                </ul>
                <?php endif; ?>

                <!-- SECTION 6: SYSTEM ADMINISTRATION (ADMIN ONLY) -->
                <?php if ($user_role === 'admin'): ?>
                <div class="nav-section-title">Administration</div>
                <ul class="sidebar-menu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'company.php') ? 'active' : ''; ?>" href="company.php">
                            <i class="nav-icon fas fa-tractor"></i>
                            <span class="nav-text">Farm Profile</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                            <i class="nav-icon fas fa-user-gear"></i>
                            <span class="nav-text">User Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'audit_logs.php') ? 'active' : ''; ?>" href="audit_logs.php">
                            <i class="nav-icon fas fa-shield-halved"></i>
                            <span class="nav-text">Audit Logs</span>
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Sidebar Footer: Authenticated User Info -->
            <div class="sidebar-footer">
                <div class="user-profile-widget">
                    <div class="user-avatar-badge">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="user-meta">
                        <span class="user-name"><?php echo $full_name; ?> (<?php echo ucfirst($user_role); ?>)</span>
                        <span class="user-role"><?php echo $role_title; ?></span>
                    </div>
                </div>
                <button type="button" class="sidebar-logout-btn border-0 bg-transparent" title="Logout" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal" aria-label="Sign Out" onclick="if(window.innerWidth < 992) document.body.classList.remove('mobile-sidebar-open');">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                </button>
            </div>
        </aside>

        <!-- RIGHT PAGE CONTENT WRAPPER -->
        <div class="page-wrapper">
            <!-- TOP HEADER BAR -->
            <header class="top-header">
                <div class="top-header-left">
                    <button id="hamburgerBtn" class="hamburger-btn" type="button" aria-label="Toggle Navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?php echo $active_title; ?></h1>
                </div>
                <div class="top-header-right">
                    <?php if ($isLoggedIn): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary position-relative rounded-pill px-3 py-1 btn-header-action shadow-sm" data-bs-toggle="modal" data-bs-target="#notificationCenterModal" title="Notifications & Messages">
                        <i class="fas fa-bell me-1"></i>
                        <span class="d-none d-md-inline">Notifications</span>
                        <?php if ($unreadNotifCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.68rem;">
                                <?php echo $unreadNotifCount > 9 ? '9+' : $unreadNotifCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary btn-header-action" onclick="window.location.reload();" title="Refresh Page">
                        <i class="fas fa-arrows-rotate"></i>
                        <span class="d-none d-xl-inline">Refresh</span>
                    </button>
                </div>
            </header>

            <!-- MAIN CONTENT INJECTION CONTAINER -->
            <main class="main-content">
<?php endif; ?>
