<?php
$filePath = __DIR__ . '/../poultrychat/reservations.php';
$content = file_get_contents($filePath);

$needle = '<!-- MODAL: Edit Reservation -->';

$modalHtml = <<<HTML
                                    <?php if (in_array(strtolower(\$user_role), ['admin', 'staff'])): ?>
                                    <!-- MODAL: Verify Chick Receipt & Activate Batch (Step 4 & 5) -->
                                    <div class="modal fade text-start" id="verifyReceiptModal<?php echo \$resId; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down modal-lg">
                                            <div class="modal-content border-0 rounded-4 shadow">
                                                <form method="POST" action="reservations.php">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="verify_receipt_and_activate" value="1">
                                                    <input type="hidden" name="reservation_id" value="<?php echo \$resId; ?>">
                                                    
                                                    <div class="modal-header border-0 rounded-top-4 text-white" style="background: linear-gradient(135deg, #123524 0%, #1c4a33 100%);">
                                                        <div>
                                                            <h5 class="modal-title fw-bold mb-0">
                                                                <i class="fas fa-clipboard-check text-warning me-2"></i>Step 4 &amp; 5: Verify Chick Receipt &amp; Activate Batch
                                                            </h5>
                                                            <small class="text-white-50">Reservation #<?php echo e(\$r['reservation_number']); ?> &bull; <?php echo e(\$r['customer_name']); ?></small>
                                                        </div>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    
                                                    <div class="modal-body p-3 p-md-4">
                                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                                            <div class="row g-2">
                                                                <div class="col-6 col-md-3">
                                                                    <span class="text-muted small d-block">Reservation Ref:</span>
                                                                    <span class="fw-bold font-monospace text-primary">#<?php echo e(\$r['reservation_number']); ?></span>
                                                                </div>
                                                                <div class="col-6 col-md-3">
                                                                    <span class="text-muted small d-block">Partner / Integrator:</span>
                                                                    <span class="fw-bold text-dark"><?php echo e(\$r['customer_name']); ?></span>
                                                                </div>
                                                                <div class="col-6 col-md-3">
                                                                    <span class="text-muted small d-block">Designated Pen:</span>
                                                                    <span class="fw-bold text-success"><i class="fas fa-warehouse me-1"></i><?php echo e(\$r['house_name'] ?? 'House 1'); ?></span>
                                                                </div>
                                                                <div class="col-6 col-md-3">
                                                                    <span class="text-muted small d-block">Contract Reserved:</span>
                                                                    <span class="fw-bold text-dark"><?php echo number_format((int)\$r['display_quantity']); ?> chicks</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label fw-bold">Accepted Chick Quantity <span class="text-danger">*</span></label>
                                                                <div class="input-group">
                                                                    <input type="number" name="accepted_quantity" class="form-control" value="<?php echo (int)\$r['display_quantity']; ?>" min="1" max="<?php echo ((int)\$r['display_quantity'] * 2); ?>" required>
                                                                    <span class="input-group-text">birds</span>
                                                                </div>
                                                                <small class="text-muted">Live chicks healthy and placed into pen.</small>
                                                            </div>
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label fw-bold">Shortage / Rejected Chicks</label>
                                                                <div class="input-group">
                                                                    <input type="number" name="rejected_quantity" class="form-control" value="0" min="0">
                                                                    <span class="input-group-text">birds</span>
                                                                </div>
                                                                <small class="text-muted">DOA (Dead on Arrival) or rejected chicks.</small>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label fw-bold">Delivery Receipt (DR) / Waybill No.</label>
                                                                <input type="text" name="receipt_number" class="form-control font-monospace" value="DR-<?php echo date('Ymd'); ?>-<?php echo str_pad(\$resId, 3, '0', STR_PAD_LEFT); ?>" placeholder="e.g. DR-20261017-001">
                                                                <small class="text-muted">Physical delivery receipt number from company delivery driver.</small>
                                                            </div>
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label fw-bold">Initial Chick Weight</label>
                                                                <input type="text" class="form-control bg-light" value="0.04 kg (Day-0 Standard Weight)" readonly>
                                                                <small class="text-muted">Standard baseline chick intake weight.</small>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Intake Discrepancy / Health Condition Remarks</label>
                                                            <textarea name="rejection_reason" class="form-control" rows="2" placeholder="e.g. All chicks active and healthy, crop filled with water, no physical abnormalities or dehydration detected."></textarea>
                                                        </div>

                                                        <div class="alert alert-success d-flex align-items-center mb-0 p-3 rounded-3 border-0">
                                                            <i class="fas fa-feather-pointed fs-3 text-success me-3"></i>
                                                            <div class="small">
                                                                <strong>Step 5 Batch Activation Protocol:</strong>
                                                                <div class="text-muted">Confirming this receipt will automatically generate a production flock batch (<code>BR-<?php echo date('Ymd'); ?>-XXX</code>), mark House <?php echo e(\$r['house_name'] ?? 'Pen'); ?> as <strong>Occupied</strong>, update workflow to <strong>Growing</strong>, and dispatch real-time system notifications to both Farm Staff and <?php echo e(\$r['customer_name']); ?>.</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer border-0 bg-light rounded-bottom-4">
                                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                                            <i class="fas fa-check-double me-1"></i>Confirm Receipt &amp; Activate Batch
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

HTML;

if (strpos($content, $needle) !== false && strpos($content, 'id="verifyReceiptModal') === false) {
    $content = str_replace($needle, $modalHtml . "\n                                    " . $needle, $content);
    file_put_contents($filePath, $content);
    echo "SUCCESS: verifyReceiptModal injected successfully.\n";
} else {
    echo "NOTICE: Already injected or needle not found.\n";
}
