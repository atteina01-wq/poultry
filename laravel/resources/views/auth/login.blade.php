<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Alquizar Poultry Farm Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Shared Farm Theme -->
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <style>
        body {
            background-color: var(--primary-deep, #142E1F);
            background-image: radial-gradient(circle at top right, rgba(255,255,255,0.05) 0%, transparent 40%),
                              radial-gradient(circle at bottom left, rgba(0,0,0,0.3) 0%, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .login-card {
            background: var(--surface, #FBF9F4);
            border-radius: var(--radius-md, 14px);
            box-shadow: var(--shadow-sm, 0 8px 24px rgba(30, 43, 35, 0.12));
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border: 1px solid var(--border, #DDD6C4);
            text-align: center;
        }
        .brand-icon-wrapper {
            width: 56px;
            height: 56px;
            background-color: var(--primary-soft, #DDECE3);
            color: var(--primary, #2E5B43);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .login-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text, #1E2B23);
            margin-bottom: 4px;
        }
        .login-subtitle {
            font-size: 13px;
            color: var(--text-muted, #728076);
            margin-bottom: 32px;
        }
        .login-form {
            text-align: left;
        }
        .btn-login {
            width: 100%;
            margin-top: 16px;
            background-color: var(--primary, #2E5B43);
            border-color: var(--primary, #2E5B43);
            color: #fff;
            font-weight: 600;
            padding: 10px 16px;
            border-radius: var(--radius-sm, 8px);
        }
        .btn-login:hover {
            background-color: var(--primary-deep, #142E1F);
            border-color: var(--primary-deep, #142E1F);
            color: #fff;
        }
        .footer-note {
            margin-top: 32px;
            font-size: 12px;
            color: var(--text-muted, #728076);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-icon-wrapper">
            <i class="fas fa-drumstick-bite"></i>
        </div>
        <h1 class="login-title">Alquizar Poultry Farm</h1>
        <div class="login-subtitle">Integrated Production & Management System (Laravel)</div>

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small rounded-3 d-flex align-items-center mb-4" style="background-color: var(--success-bg, #E3EFE7); color: var(--success, #2E7D5B); border: 1px solid var(--success, #2E7D5B);">
                <i class="fas fa-check-circle me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small rounded-3 d-flex align-items-center mb-4" style="background-color: var(--danger-bg, #F5E1DD); color: var(--danger, #B03A2E); border: 1px solid var(--danger, #B03A2E);">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="login-form">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-semibold" style="color: var(--text, #1E2B23);">Username</label>
                <input type="text" name="username" id="usernameInput" class="form-control" placeholder="Enter username" value="{{ old('username') }}" required autofocus>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-semibold" style="color: var(--text, #1E2B23);">Password</label>
                <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn btn-login btn-lg">
                Log In
            </button>
        </form>

        <!-- Quick Demo Account Switcher -->
        <div class="mt-4 pt-3 border-top text-start">
            <div class="small fw-bold text-muted mb-2 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Quick Demo Accounts:</div>
            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary text-start d-flex justify-content-between align-items-center py-1 px-2 rounded-3" onclick="fillCredentials('admin', 'admin123')">
                    <span><i class="fas fa-crown text-primary me-1"></i><strong>Admin</strong> (Farm Owner)</span>
                    <code class="small text-muted">admin / admin123</code>
                </button>
                <button type="button" class="btn btn-sm btn-outline-success text-start d-flex justify-content-between align-items-center py-1 px-2 rounded-3" onclick="fillCredentials('integrator', 'integrator123')">
                    <span><i class="fas fa-building text-success me-1"></i><strong>Integrator</strong> (Company Rep)</span>
                    <code class="small text-muted">integrator / integrator123</code>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start d-flex justify-content-between align-items-center py-1 px-2 rounded-3" onclick="fillCredentials('worker', 'worker123')">
                    <span><i class="fas fa-hammer text-secondary me-1"></i><strong>Worker</strong> (Farm Staff)</span>
                    <code class="small text-muted">worker / worker123</code>
                </button>
            </div>
        </div>

        <div class="footer-note">
            Tampakan, South Cotabato &bull; Official Portal
        </div>
    </div>

    <script>
    function fillCredentials(user, pass) {
        document.getElementById('usernameInput').value = user;
        document.getElementById('passwordInput').value = pass;
    }
    </script>
</body>
</html>
