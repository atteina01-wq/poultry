<?php
$filePath = __DIR__ . '/../poultrychat/reservations.php';
$content = file_get_contents($filePath);

// 1. Actions TD min-width & text-nowrap
$targetTd = '<td data-label="Actions" class="text-center">';
$replaceTd = '<td data-label="Actions" class="text-center text-nowrap" style="min-width: 220px;">';
if (strpos($content, $targetTd) !== false) {
    $content = str_replace($targetTd, $replaceTd, $content);
    echo "[PASS] Replaced Actions TD tag\n";
} else {
    echo "[WARN] Target Actions TD not found\n";
}

// 2. Both d-flex containers in Actions
$targetFlex = '<div class="d-flex justify-content-center align-items-center gap-1">';
$replaceFlex = '<div class="d-flex justify-content-center align-items-center gap-1 flex-nowrap">';
if (strpos($content, $targetFlex) !== false) {
    $content = str_replace($targetFlex, $replaceFlex, $content);
    echo "[PASS] Replaced d-flex with flex-nowrap\n";
} else {
    echo "[WARN] Target d-flex container not found\n";
}

// 3. Review & Approve (Step 2) button in table
$targetBtn = '<button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#reviewReservationModal<?php echo $resId; ?>" title="Review Verification Checks &amp; Approve Reservation (Step 2)">
                                                <i class="fas fa-clipboard-check me-1"></i>Review &amp; Approve (Step 2)
                                            </button>';

$replaceBtn = '<button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold shadow-sm text-nowrap d-inline-flex align-items-center" style="white-space: nowrap; font-size: 0.8rem; line-height: 1.4;" data-bs-toggle="modal" data-bs-target="#reviewReservationModal<?php echo $resId; ?>" title="Conduct Feasibility Review &amp; Approve Reservation (Step 2)">
                                                <i class="fas fa-clipboard-check me-1"></i>Approve (Step 2)
                                            </button>';

if (strpos($content, $targetBtn) !== false) {
    $content = str_replace($targetBtn, $replaceBtn, $content);
    echo "[PASS] Replaced Review & Approve button in table\n";
} else {
    // Try normalizing line endings
    $normalizedContent = str_replace("\r\n", "\n", $content);
    $normalizedTarget = str_replace("\r\n", "\n", $targetBtn);
    if (strpos($normalizedContent, $normalizedTarget) !== false) {
        $content = str_replace($normalizedTarget, $replaceBtn, $normalizedContent);
        echo "[PASS] Replaced Review & Approve button with normalized line endings\n";
    } else {
        echo "[WARN] Target button not found\n";
    }
}

// 4. Manage dropdown button text-nowrap
$targetManage = '<button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown">';
$replaceManage = '<button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3 text-nowrap d-inline-flex align-items-center" style="white-space: nowrap; font-size: 0.8rem; line-height: 1.4;" type="button" data-bs-toggle="dropdown">';
if (strpos($content, $targetManage) !== false) {
    $content = str_replace($targetManage, $replaceManage, $content);
    echo "[PASS] Replaced Manage button with text-nowrap\n";
} else {
    echo "[WARN] Target Manage button not found\n";
}

file_put_contents($filePath, $content);
echo "Update script finished.\n";
