document.addEventListener('DOMContentLoaded', () => {
    
    // UI Elements
    const authScreen = document.getElementById('auth-screen');
    const appScreen = document.getElementById('app-screen');
    const loginForm = document.getElementById('login-form');
    const loginError = document.getElementById('login-error');
    const viewContainer = document.getElementById('view-container');
    const pageTitle = document.getElementById('page-title');
    const navLinks = document.querySelectorAll('.nav-item');

    // --- Authentication Flow ---
    function checkAuth() {
        if (api.isLoggedIn()) {
            const userData = JSON.parse(localStorage.getItem('user_data'));
            document.getElementById('nav-username').textContent = userData.username;
            document.getElementById('nav-role').textContent = userData.role;
            
            authScreen.style.display = 'none';
            appScreen.style.display = 'flex';
            loadView('dashboard');
        } else {
            authScreen.style.display = 'flex';
            appScreen.style.display = 'none';
        }
    }

    window.addEventListener('auth_failed', () => {
        checkAuth();
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const user = document.getElementById('username').value;
        const pass = document.getElementById('password').value;
        const btn = loginForm.querySelector('button');
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
        btn.disabled = true;
        loginError.style.display = 'none';

        try {
            await api.login(user, pass);
            checkAuth();
        } catch (err) {
            loginError.textContent = err.message;
            loginError.style.display = 'block';
        } finally {
            btn.innerHTML = 'Sign In <i class="fas fa-arrow-right"></i>';
            btn.disabled = false;
        }
    });

    document.getElementById('logout-btn').addEventListener('click', () => {
        api.logout();
    });

    // --- Routing & Views ---
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            navLinks.forEach(n => n.classList.remove('active'));
            link.classList.add('active');
            loadView(link.dataset.view);
        });
    });

    async function loadView(view) {
        viewContainer.innerHTML = '<div style="text-align: center;"><div class="loader"></div></div>';
        
        try {
            if (view === 'dashboard') {
                pageTitle.textContent = 'Dashboard Overview';
                viewContainer.innerHTML = `
                    <div class="card glass-panel text-center">
                        <i class="fas fa-chart-line text-accent" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>Welcome to the Native SPA Frontend</h3>
                        <p class="text-muted mt-2">This is communicating directly with your PHP REST API using JWT Tokens.</p>
                    </div>
                `;
            } 
            else if (view === 'batches') {
                pageTitle.textContent = 'Batch Management';
                const res = await api.get('/batches/index.php');
                
                let html = `
                    <div class="card glass-panel">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Batch Code</th>
                                        <th>Type</th>
                                        <th>Arrival Date</th>
                                        <th>Headcount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;

                res.data.forEach(b => {
                    const statusClass = b.status === 'active' ? 'badge-success' : 'badge-warning';
                    html += `
                        <tr>
                            <td class="fw-bold">${b.batch_code}</td>
                            <td style="text-transform: capitalize;">${b.flock_type}</td>
                            <td>${new Date(b.arrival_date).toLocaleDateString()}</td>
                            <td>${parseInt(b.current_quantity).toLocaleString()}</td>
                            <td><span class="badge ${statusClass}">${b.status}</span></td>
                        </tr>
                    `;
                });

                html += `</tbody></table></div></div>`;
                viewContainer.innerHTML = html;
            }
            else if (view === 'reservations') {
                pageTitle.textContent = 'Reservations';
                viewContainer.innerHTML = `
                    <div class="card glass-panel text-center">
                        <i class="fas fa-tools text-muted" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>Reservations API Endpoint Needed</h3>
                        <p class="text-muted mt-2">We need to create api/v1/reservations/index.php next.</p>
                    </div>
                `;
            }
        } catch (error) {
            viewContainer.innerHTML = `
                <div class="error-banner">
                    <strong>Error loading view:</strong> ${error.message}
                </div>
            `;
        }
    }

    // Initialize
    checkAuth();
});
