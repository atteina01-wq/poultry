<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Alquizar Poultry Farm') — Integrated Farm Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Farm Shared Theme & Sidebar CSS -->
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <style>
        /* Logout Confirmation Modal - System Design Tokens */
        .modal-logout-card {
            background-color: var(--surface, #FBF9F4) !important;
            border: 1px solid var(--border, #DDD6C4) !important;
            border-radius: var(--radius-md, 14px) !important;
            box-shadow: 0 16px 36px -8px rgba(30, 43, 35, 0.2) !important;
        }
        .modal-logout-icon-wrap {
            width: 60px;
            height: 60px;
            background-color: var(--danger-bg, #F5E1DD) !important;
            color: var(--danger, #B03A2E) !important;
            border: 1px solid rgba(176, 58, 46, 0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .btn-logout-confirm {
            background-color: #B03A2E !important;
            border: 1px solid #8E2B20 !important;
            color: #FFFFFF !important;
            font-weight: 600 !important;
            font-size: 13.5px !important;
            border-radius: var(--radius-sm, 8px) !important;
            height: 42px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            text-decoration: none !important;
            box-shadow: 0 2px 6px rgba(176, 58, 46, 0.25) !important;
            transition: all 0.15s ease !important;
        }
        .btn-logout-confirm:hover, .btn-logout-confirm:focus {
            background-color: #942E24 !important;
            border-color: #79241C !important;
            color: #FFFFFF !important;
            box-shadow: 0 4px 10px rgba(176, 58, 46, 0.35) !important;
        }
        .btn-logout-confirm *, .btn-logout-confirm i, .btn-logout-confirm span {
            color: #FFFFFF !important;
            fill: #FFFFFF !important;
        }
        .btn-logout-cancel {
            background-color: var(--surface-alt, #F4F0E6) !important;
            border: 1px solid var(--border, #DDD6C4) !important;
            color: var(--text, #1E2B23) !important;
            font-weight: 600 !important;
            font-size: 13.5px !important;
            border-radius: var(--radius-sm, 8px) !important;
            height: 42px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.15s ease !important;
        }
        .btn-logout-cancel:hover {
            background-color: #EBE5D8 !important;
            border-color: var(--primary, #2E5B43) !important;
            color: var(--primary, #2E5B43) !important;
        }
    </style>
    @stack('styles')
</head>
<body>
<div id="app-layout">
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- LEFT FARM SIDEBAR -->
    <aside id="sidebar" class="sidebar">
        <!-- Farm Identity Header -->
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="brand-wrapper">
                <div class="brand-icon">
                    <i class="fas fa-drumstick-bite"></i>
                </div>
                <div class="brand-details">
                    <span class="brand-name">Alquizar Poultry Farm</span>
                    <span class="brand-location">Tampakan, South Cotabato</span>
                </div>
            </a>
            <button id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Scrollable Navigation Sections -->
        <div class="sidebar-nav-container">
            <!-- SECTION 1: MAIN OPERATIONS -->
            <div class="nav-section-title">Main Operations</div>
            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('dashboard*') || request()->is('/') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('batches*') ? 'active' : '' }}" href="{{ route('batches.index') }}">
                        <i class="nav-icon fas fa-layer-group"></i>
                        <span class="nav-text">Batch Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('activities*') ? 'active' : '' }}" href="{{ route('activities.index') }}">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <span class="nav-text">Farm Operations</span>
                    </a>
                </li>
            </ul>

            <!-- SECTION 2: FLOCK HEALTH -->
            <div class="nav-section-title">Flock Health</div>
            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('health*') ? 'active' : '' }}" href="{{ route('health.index') }}">
                        <i class="nav-icon fas fa-heart-pulse"></i>
                        <span class="nav-text">Health Records</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('mortality*') ? 'active' : '' }}" href="{{ route('mortality.index') }}">
                        <i class="nav-icon fas fa-skull"></i>
                        <span class="nav-text">Mortality</span>
                    </a>
                </li>
            </ul>

            <!-- SECTION 3: INVENTORY & RESOURCES -->
            <div class="nav-section-title">Inventory & Resources</div>
            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('inventory*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                        <i class="nav-icon fas fa-warehouse"></i>
                        <span class="nav-text">Stock Inventory</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('feed*') ? 'active' : '' }}" href="{{ route('feed.index') }}">
                        <i class="nav-icon fas fa-wheat-awn"></i>
                        <span class="nav-text">Feed Consumption</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('waste*') ? 'active' : '' }}" href="{{ route('waste.index') }}">
                        <i class="nav-icon fas fa-trash-can"></i>
                        <span class="nav-text">Waste & Damage</span>
                    </a>
                </li>
            </ul>

            <!-- SECTION 4: RETAILERS & RESERVATIONS -->
            <div class="nav-section-title">Retailers & Reservations</div>
            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('customers*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                        <i class="nav-icon fas fa-store"></i>
                        <span class="nav-text">Retailers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('reservations*') ? 'active' : '' }}" href="{{ route('reservations.index') }}">
                        <i class="nav-icon fas fa-calendar-check"></i>
                        <span class="nav-text">Reservations</span>
                    </a>
                </li>
            </ul>

            <!-- SECTION 5: FINANCE & REPORTS -->
            <div class="nav-section-title">Finance & Reports</div>
            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('expenses*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">
                        <i class="nav-icon fas fa-wallet"></i>
                        <span class="nav-text">Expenses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('reports*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                        <i class="nav-icon fas fa-chart-column"></i>
                        <span class="nav-text">Reports & Statistics</span>
                    </a>
                </li>
            </ul>

            <!-- SECTION 6: ADMINISTRATION -->
            <div class="nav-section-title">Administration</div>
            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('company*') ? 'active' : '' }}" href="{{ route('company.index') }}">
                        <i class="nav-icon fas fa-tractor"></i>
                        <span class="nav-text">Farm Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('users*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <i class="nav-icon fas fa-user-gear"></i>
                        <span class="nav-text">User Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('audit-logs*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}">
                        <i class="nav-icon fas fa-shield-halved"></i>
                        <span class="nav-text">Audit Logs</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Sidebar Footer: Authenticated User Info -->
        <div class="sidebar-footer">
            <div class="user-profile-widget">
                <div class="user-avatar-badge">
                    {{ strtoupper(substr(auth()->user()->full_name ?? auth()->user()->username ?? 'A', 0, 1)) }}
                </div>
                <div class="user-meta">
                    <span class="user-name">{{ auth()->user()->full_name ?? auth()->user()->username ?? 'Admin' }} ({{ ucfirst(auth()->user()->role ?? 'Admin') }})</span>
                    <span class="user-role">
                        @if((auth()->user()->role ?? '') === 'admin')
                            FARM OWNER / MANAGEMENT
                        @elseif((auth()->user()->role ?? '') === 'integrator')
                            INTEGRATOR / PARTNER COMPANY
                        @else
                            FARM STAFF / OPERATOR
                        @endif
                    </span>
                </div>
            </div>
            <button type="button" class="sidebar-logout-btn border-0 bg-transparent" title="Logout" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal" aria-label="Sign Out">
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
                <h1 class="page-title">@yield('title', 'Alquizar Poultry Farm')</h1>
            </div>
            <div class="top-header-right">
                <button class="btn btn-sm btn-outline-secondary btn-header-action" onclick="window.location.reload();" title="Refresh Page">
                    <i class="fas fa-arrows-rotate"></i>
                    <span class="d-none d-xl-inline">Refresh</span>
                </button>
            </div>
        </header>

        <!-- MAIN CONTENT INJECTION CONTAINER -->
        <main class="main-content p-4">
            <!-- Flash Message Alerts -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3 mb-4" role="alert" style="background-color: var(--success-bg, #E3EFE7); color: var(--success, #2E7D5B); border: 1px solid var(--success, #2E7D5B);">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 mb-4" role="alert" style="background-color: var(--danger-bg, #F5E1DD); color: var(--danger, #B03A2E); border: 1px solid var(--danger, #B03A2E);">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="app-footer">
            &copy; 2026 Alquizar Poultry Farm Management System (Laravel Edition). All rights reserved.
        </footer>
    </div>
</div>

<!-- MODAL: Logout Confirmation Warning -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 410px;">
        <div class="modal-content border-0 modal-logout-card overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="mx-auto mb-3 modal-logout-icon-wrap">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                </div>

                <h5 class="fw-bold mb-1" id="logoutConfirmModalLabel" style="color: var(--text, #1E2B23); font-size: 17px;">
                    Sign Out of Farm Portal?
                </h5>
                <p class="text-muted small mb-3" style="line-height: 1.5; font-size: 12.5px;">
                    Are you sure you want to end your current session? Any unsaved edits or active form entries will not be saved.
                </p>

                <div class="d-flex align-items-center gap-3 p-2 px-3 rounded-3 mb-4 text-start" style="background-color: var(--surface-alt, #F4F0E6); border: 1px solid var(--border, #DDD6C4);">
                    <div class="user-avatar-badge" style="width: 36px; height: 36px; font-size: 14px; font-weight: 600; flex-shrink: 0;">
                        {{ strtoupper(substr(auth()->user()->full_name ?? auth()->user()->username ?? 'U', 0, 1)) }}
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-bold text-truncate" style="color: var(--text, #1E2B23); font-size: 13px;">
                            {{ auth()->user()->full_name ?? auth()->user()->username ?? 'Current User' }}
                        </div>
                        <div class="text-muted text-truncate" style="font-size: 11px;">
                            {{ ucfirst(auth()->user()->role ?? 'User') }} Account
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <form method="POST" action="{{ route('logout') }}" id="confirmedLogoutForm">
                        @csrf
                        <button type="submit" class="btn btn-logout-confirm w-100">
                            <i class="fas fa-arrow-right-from-bracket"></i>
                            <span>Yes, Sign Out Now</span>
                        </button>
                    </form>
                    <button type="button" class="btn btn-logout-cancel w-100" data-bs-dismiss="modal">
                        Stay Signed In
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Farm Sidebar JS -->
<script src="{{ asset('js/sidebar.js') }}"></script>
@stack('scripts')
</body>
</html>
