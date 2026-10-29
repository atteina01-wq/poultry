        </main> <!-- End main-content -->
        <footer class="app-footer">
            &copy; 2026 Alquizar Poultry Farm Management System. All rights reserved.
        </footer>
    </div> <!-- End page-wrapper -->
</div> <!-- End app-layout -->

<style>
/* Ensure Bootstrap modal and backdrop render above mobile sidebar (1060) */
.modal-backdrop {
    z-index: 1080 !important;
}
.modal {
    z-index: 1085 !important;
}

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

<!-- MODAL: Logout Confirmation Warning -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: min(410px, calc(100vw - 32px)); margin: 1.75rem auto;">
        <div class="modal-content border-0 modal-logout-card overflow-hidden">
            <div class="modal-body p-4 text-center">
                <!-- Warning / Signout Crest -->
                <div class="mx-auto mb-3 modal-logout-icon-wrap">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                </div>

                <h5 class="fw-bold mb-1" id="logoutConfirmModalLabel" style="color: var(--text, #1E2B23); font-size: 17px;">
                    Sign Out of Farm Portal?
                </h5>
                <p class="text-muted small mb-3" style="line-height: 1.5; font-size: 12.5px;">
                    Are you sure you want to end your current session? Any unsaved edits or active form entries will not be saved.
                </p>

                <!-- Active User Session Badge -->
                <div class="d-flex align-items-center gap-3 p-2 px-3 rounded-3 mb-4 text-start" style="background-color: var(--surface-alt, #F4F0E6); border: 1px solid var(--border, #DDD6C4);">
                    <div class="user-avatar-badge" style="width: 36px; height: 36px; font-size: 14px; font-weight: 600; flex-shrink: 0;">
                        <?php echo $user_initial ?? 'U'; ?>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-bold text-truncate" style="color: var(--text, #1E2B23); font-size: 13px;">
                            <?php echo $full_name ?? 'Current User'; ?>
                        </div>
                        <div class="text-muted text-truncate" style="font-size: 11px;">
                            <?php echo $role_title ?? 'FARM STAFF'; ?>
                        </div>
                    </div>
                    <span class="badge bg-light text-muted border font-monospace" style="font-size: 10px;">ONLINE</span>
                </div>

                <!-- Action Buttons: Cancel vs. Yes, Log Out -->
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-logout-cancel flex-fill" data-bs-dismiss="modal">
                        Stay Logged In
                    </button>
                    <a href="logout.php" class="btn btn-logout-confirm flex-fill">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Yes, Log Out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Notification Center & Inter-Role Messaging (Admin & Integrator) -->
<div class="modal fade" id="notificationCenterModal" tabindex="-1" aria-labelledby="notificationCenterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down" style="max-width: 580px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden bg-white">
            <div class="modal-header border-0 py-3 px-4 text-white" style="background: linear-gradient(135deg, #163023 0%, #102419 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,0.12); color: #C29A4B; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-white" id="notificationCenterModalLabel" style="font-size: 1.05rem;">
                            Notifications & Messages
                        </h6>
                        <small class="text-white-50" style="font-size: 0.75rem;">
                            Inter-role updates &bull; Admin &amp; Integrator Portal
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($unreadNotifCount) && $unreadNotifCount > 0): ?>
                    <form method="POST" action="notifications_action.php" class="m-0">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-xs btn-outline-light rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                            <i class="fas fa-check-double me-1"></i>Mark all read
                        </button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-3 p-md-4 bg-light">
                <?php if (empty($headerNotifs)): ?>
                <div class="text-center py-5">
                    <div class="mx-auto mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #e2e8f0; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 26px;">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">No notifications right now</h6>
                    <p class="text-muted small mb-0">You're all caught up! Flock releases, payment updates, and reminders will appear here.</p>
                </div>
                <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($headerNotifs as $hn): 
                        $isUnread = (($hn['status'] ?? 'unread') === 'unread');
                        $sev = $hn['severity'] ?? 'info';
                        $iconClass = match($sev) {
                            'warning' => 'fas fa-triangle-exclamation text-warning',
                            'critical' => 'fas fa-circle-exclamation text-danger',
                            default => 'fas fa-circle-check text-success'
                        };
                        $bgCard = $isUnread ? 'bg-white border-success-subtle shadow-sm' : 'bg-white border text-muted';
                    ?>
                    <div class="p-3 rounded-3 border <?php echo $bgCard; ?> position-relative transition-all">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mt-1 flex-shrink-0" style="font-size: 1.15rem;">
                                <i class="<?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <span class="fw-bold text-dark small" style="font-size: 0.82rem;">
                                        <?php if ($sev === 'warning'): ?>
                                            <span class="badge bg-warning text-dark px-2 py-0 rounded-pill me-1" style="font-size: 0.65rem;">ACTION REQUIRED</span>
                                        <?php elseif ($sev === 'critical'): ?>
                                            <span class="badge bg-danger text-white px-2 py-0 rounded-pill me-1" style="font-size: 0.65rem;">URGENT</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0 rounded-pill me-1" style="font-size: 0.65rem;">FLOCK UPDATE</span>
                                        <?php endif; ?>
                                        <?php echo ucfirst($hn['type'] ?? 'Notification'); ?>
                                    </span>
                                    <small class="text-muted" style="font-size: 0.70rem;">
                                        <?php echo !empty($hn['created_at']) ? date('M d, g:i A', strtotime($hn['created_at'])) : ''; ?>
                                    </small>
                                </div>
                                <p class="mb-2 text-dark small" style="font-size: 0.85rem; line-height: 1.45;">
                                    <?php echo htmlspecialchars($hn['message'] ?? ''); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <?php if (!empty($hn['entity_type']) && $hn['entity_type'] === 'reservations'): ?>
                                    <a href="reservations.php" class="btn btn-xs btn-outline-success rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.74rem;">
                                        <i class="fas fa-arrow-right me-1"></i>View Reservation
                                    </a>
                                    <?php else: ?>
                                    <span></span>
                                    <?php endif; ?>

                                    <?php if ($isUnread): ?>
                                    <form method="POST" action="notifications_action.php" class="m-0">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo (int)$hn['notification_id']; ?>">
                                        <button type="submit" class="btn btn-xs btn-light text-muted border rounded-pill px-2 py-0" style="font-size: 0.70rem;" title="Mark as read">
                                            <i class="fas fa-check me-1"></i>Mark read
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.68rem;"><i class="fas fa-check me-1"></i>Read</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light border-0 py-2 px-3 justify-content-between">
                <span class="text-muted small" style="font-size: 0.75rem;">
                    <i class="fas fa-shield-alt text-success me-1"></i>Auto-refreshes on actions
                </span>
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- GLOBAL MODAL: Aesthetic Farm System Confirmation & Warning Dialog -->
<div class="modal fade" id="farmConfirmModal" tabindex="-1" aria-labelledby="farmConfirmModalLabel" aria-hidden="true" style="z-index: 1090;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px; margin: 1.75rem auto;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: var(--surface, #FBF9F4); border: 1px solid var(--border, #DDD6C4) !important;">
            <div class="modal-body p-4 text-center">
                <!-- Icon Badge -->
                <div id="farmConfirmIconWrap" class="mx-auto mb-3" style="width: 64px; height: 64px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 26px;">
                    <i id="farmConfirmIcon" class="fas fa-question-circle"></i>
                </div>

                <h5 class="fw-bold mb-2" id="farmConfirmModalLabel" style="font-family: 'Bricolage Grotesque', sans-serif; color: #1E2B23; font-size: 1.18rem;">
                    Confirm Action
                </h5>
                <div class="text-muted mb-4" id="farmConfirmText" style="font-family: 'Figtree', sans-serif; font-size: 0.88rem; line-height: 1.5; color: #58685E;">
                    Are you sure you want to proceed with this operation?
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-logout-cancel flex-fill rounded-pill" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" id="farmConfirmActionBtn" class="btn flex-fill rounded-pill fw-bold shadow-sm">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Sidebar Controller JS -->
<script src="js/sidebar.js?v=<?php echo time(); ?>"></script>

<script>
window.showFarmConfirm = function(options) {
    const modalEl = document.getElementById('farmConfirmModal');
    if (!modalEl) {
        if (confirm(options.text || 'Are you sure?')) {
            if (options.onConfirm) options.onConfirm();
        }
        return;
    }
    
    const titleEl = document.getElementById('farmConfirmModalLabel');
    const textEl = document.getElementById('farmConfirmText');
    const iconWrap = document.getElementById('farmConfirmIconWrap');
    const iconEl = document.getElementById('farmConfirmIcon');
    const actionBtn = document.getElementById('farmConfirmActionBtn');
    
    titleEl.textContent = options.title || 'Farm System Confirmation';
    textEl.innerHTML = options.text || 'Are you sure you want to perform this operation?';
    
    // Style icon wrap and buttons based on type
    const type = options.type || 'info';
    if (type === 'danger' || type === 'delete' || type === 'cancel') {
        iconWrap.style.background = 'rgba(176, 58, 46, 0.14)';
        iconWrap.style.color = '#B03A2E';
        iconWrap.style.border = '1px solid rgba(176, 58, 46, 0.3)';
        iconEl.className = 'fas fa-triangle-exclamation';
        actionBtn.className = 'btn btn-danger flex-fill rounded-pill fw-bold shadow-sm';
    } else if (type === 'warning') {
        iconWrap.style.background = 'rgba(242, 182, 50, 0.2)';
        iconWrap.style.color = '#B27B08';
        iconWrap.style.border = '1px solid rgba(242, 182, 50, 0.4)';
        iconEl.className = 'fas fa-exclamation-triangle';
        actionBtn.className = 'btn btn-warning text-dark flex-fill rounded-pill fw-bold shadow-sm';
    } else {
        iconWrap.style.background = 'rgba(34, 197, 94, 0.16)';
        iconWrap.style.color = '#15803d';
        iconWrap.style.border = '1px solid rgba(34, 197, 94, 0.35)';
        iconEl.className = 'fas fa-check-circle';
        actionBtn.className = 'btn btn-success flex-fill rounded-pill fw-bold shadow-sm';
    }
    
    if (options.confirmBtnText) {
        actionBtn.textContent = options.confirmBtnText;
    } else {
        actionBtn.textContent = 'Confirm';
    }
    
    if (options.confirmBtnClass) {
        actionBtn.className = 'btn flex-fill rounded-pill fw-bold shadow-sm ' + options.confirmBtnClass;
    }
    
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    actionBtn.onclick = function() {
        bsModal.hide();
        if (typeof options.onConfirm === 'function') {
            options.onConfirm();
        }
    };
    
    bsModal.show();
};

// Auto-bind any element with data-farm-confirm
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-farm-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const promptMsg = this.getAttribute('data-farm-confirm');
            const promptTitle = this.getAttribute('data-farm-title') || 'Confirmation Required';
            const promptType = this.getAttribute('data-farm-type') || 'info';
            const promptBtn = this.getAttribute('data-farm-btn') || 'Yes, Confirm';
            
            const targetForm = this.form || this.closest('form');
            const targetLink = (this.tagName === 'A') ? this.href : null;
            
            window.showFarmConfirm({
                title: promptTitle,
                text: promptMsg,
                type: promptType,
                confirmBtnText: promptBtn,
                onConfirm: function() {
                    if (targetForm) {
                        // If button had a name and value, append hidden input so PHP receives it
                        if (el.name && el.value) {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = el.name;
                            hidden.value = el.value;
                            targetForm.appendChild(hidden);
                        }
                        targetForm.submit();
                    } else if (targetLink) {
                        window.location.href = targetLink;
                    }
                }
            });
        });
    });
});
</script>
</body>
</html>
