/**
 * San Isidro Poultry Farm - Left Sidebar & Hamburger Controller
 */
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const isMobile = () => window.innerWidth < 992;

    // Load saved desktop preference
    if (!isMobile()) {
        const isCollapsed = localStorage.getItem('poultry_sidebar_collapsed') === 'true';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
        }
    }

    function toggleSidebar() {
        if (isMobile()) {
            document.body.classList.toggle('mobile-sidebar-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
            const isNowCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('poultry_sidebar_collapsed', isNowCollapsed);
            initTooltips(isNowCollapsed);
        }
    }

    function closeMobileSidebar() {
        document.body.classList.remove('mobile-sidebar-open');
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeMobileSidebar();
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileSidebar);
    }

    // Handle responsive window resize
    window.addEventListener('resize', () => {
        if (!isMobile()) {
            document.body.classList.remove('mobile-sidebar-open');
            const isCollapsed = localStorage.getItem('poultry_sidebar_collapsed') === 'true';
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        } else {
            document.body.classList.remove('sidebar-collapsed');
        }
    });

    // Tooltips initialization for collapsed sidebar
    function initTooltips(enable) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(link => {
            const text = link.querySelector('.nav-text')?.innerText?.trim();
            if (enable && text) {
                link.setAttribute('data-bs-toggle', 'tooltip');
                link.setAttribute('data-bs-placement', 'right');
                link.setAttribute('title', text);
                new bootstrap.Tooltip(link);
            } else {
                const tooltip = bootstrap.Tooltip.getInstance(link);
                if (tooltip) {
                    tooltip.dispose();
                }
                link.removeAttribute('data-bs-toggle');
                link.removeAttribute('data-bs-placement');
                link.removeAttribute('title');
            }
        });
    }

    if (document.body.classList.contains('sidebar-collapsed')) {
        initTooltips(true);
    }
});
