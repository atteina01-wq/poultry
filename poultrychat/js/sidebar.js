/**
 * San Isidro Poultry Farm - Left Sidebar & Hamburger Controller
 * Robust, production-grade navigation controller with touch & click support.
 */
(function() {
    'use strict';

    // Prevent duplicate initializations
    if (window.__poultrySidebarLoaded) {
        return;
    }
    window.__poultrySidebarLoaded = true;

    function isMobile() {
        return window.innerWidth < 992;
    }

    function openMobileSidebar(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        document.body.classList.add('mobile-sidebar-open');
    }

    function closeMobileSidebar(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        document.body.classList.remove('mobile-sidebar-open');
    }

    function toggleSidebar(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        if (isMobile()) {
            document.body.classList.toggle('mobile-sidebar-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
            const isNowCollapsed = document.body.classList.contains('sidebar-collapsed');
            try {
                localStorage.setItem('poultry_sidebar_collapsed', isNowCollapsed ? 'true' : 'false');
            } catch (err) {
                // Storage may be disabled in private browsing
            }
            updateTooltips(isNowCollapsed);
        }
    }

    // Expose globally on window for direct access anywhere
    window.togglePoultrySidebar = toggleSidebar;
    window.openPoultrySidebar = openMobileSidebar;
    window.closePoultrySidebar = closeMobileSidebar;

    // Document-level event delegation (catches clicks on icons, buttons, overlays reliably)
    document.addEventListener('click', function(e) {
        // 1. Hamburger button click
        const hamburgerBtn = e.target.closest('#hamburgerBtn, .hamburger-btn');
        if (hamburgerBtn) {
            e.preventDefault();
            toggleSidebar(e);
            return;
        }

        // 2. Sidebar close 'X' button click
        const closeBtn = e.target.closest('#sidebarCloseBtn, .sidebar-close-btn');
        if (closeBtn) {
            e.preventDefault();
            closeMobileSidebar(e);
            return;
        }

        // 3. Backdrop overlay click
        const overlay = e.target.closest('#sidebarOverlay, .sidebar-overlay');
        if (overlay) {
            e.preventDefault();
            closeMobileSidebar(e);
            return;
        }

        // 4. Mobile navigation link click (auto-close sidebar drawer when navigating)
        const navLink = e.target.closest('.sidebar-menu .nav-link');
        if (navLink && isMobile()) {
            closeMobileSidebar();
            return;
        }

        // 5. Modal triggers inside sidebar (such as the Logout Confirmation button)
        const modalTrigger = e.target.closest('[data-bs-toggle="modal"], .sidebar-logout-btn');
        if (modalTrigger && isMobile()) {
            closeMobileSidebar();
        }
    });

    // Close mobile sidebar whenever ANY Bootstrap modal starts to open
    document.addEventListener('show.bs.modal', function() {
        closeMobileSidebar();
    });

    // Close on Escape key press
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.body.classList.contains('mobile-sidebar-open')) {
            closeMobileSidebar();
        }
    });

    // Responsive window resize handler
    window.addEventListener('resize', function() {
        if (!isMobile()) {
            document.body.classList.remove('mobile-sidebar-open');
            try {
                const isCollapsed = localStorage.getItem('poultry_sidebar_collapsed') === 'true';
                if (isCollapsed) {
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    document.body.classList.remove('sidebar-collapsed');
                }
            } catch (err) {}
        } else {
            // Mobile viewport: do not force collapse classes
            document.body.classList.remove('sidebar-collapsed');
        }
    });

    // Tooltips initialization for desktop collapsed sidebar
    function updateTooltips(enable) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(function(link) {
            const textElem = link.querySelector('.nav-text');
            const text = textElem ? textElem.innerText.trim() : '';
            if (enable && text) {
                link.setAttribute('data-bs-toggle', 'tooltip');
                link.setAttribute('data-bs-placement', 'right');
                link.setAttribute('title', text);
                try {
                    new bootstrap.Tooltip(link);
                } catch (err) {}
            } else {
                try {
                    const tooltip = bootstrap.Tooltip.getInstance(link);
                    if (tooltip) tooltip.dispose();
                } catch (err) {}
                link.removeAttribute('data-bs-toggle');
                link.removeAttribute('data-bs-placement');
                link.removeAttribute('title');
            }
        });
    }

    // Initialize desktop collapsed state from local storage on startup
    function initSidebarState() {
        if (!isMobile()) {
            try {
                const isCollapsed = localStorage.getItem('poultry_sidebar_collapsed') === 'true';
                if (isCollapsed) {
                    document.body.classList.add('sidebar-collapsed');
                    updateTooltips(true);
                }
            } catch (err) {}
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarState);
    } else {
        initSidebarState();
    }
})();
