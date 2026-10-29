<?php
$file = 'c:/xampp/htdocs/poultry/poultrychat/reservations.php';
$content = file_get_contents($file);

// 1. Remove CSS for vouchers
$content = preg_replace('/\/\* --- Custom Reservation Voucher Styles ---\*\/(.*?)\/\*/s', '/*', $content);

// 2. Remove Dropdown Menu Item
$content = preg_replace('/<li>\s*<a class="dropdown-item" href="javascript:void\(0\)" data-bs-toggle="modal" data-bs-target="#voucherModal<\?php echo \$resId; \?>">\s*<i class="fas fa-file-invoice text-success me-2"><\/i>Reservation Voucher\s*<\/a>\s*<\/li>/s', '', $content);
$content = preg_replace('/<a class="dropdown-item" href="javascript:void\(0\)" data-bs-toggle="modal" data-bs-target="#voucherModal<\?php echo \$resId; \?>">\s*<i class="fas fa-file-invoice text-success me-2"><\/i>Reservation Voucher\s*<\/a>/s', '', $content);

// 3. Remove Button for "View & Print Voucher"
$content = preg_replace('/<button type="button" class="btn btn-outline-dark rounded-pill px-4" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#voucherModal<\?php echo \$resId; \?>">\s*<i class="fas fa-print me-1"><\/i>View & Print Voucher\s*<\/button>/s', '', $content);

// 4. Remove Voucher Modal
// It starts with <!-- MODAL: Printable Reservation Voucher --> and probably ends with <!-- End Modal --> or similar.
$content = preg_replace('/<!-- MODAL: Printable Reservation Voucher -->(.*?)<!-- END MODAL: Printable Reservation Voucher -->/is', '', $content);
// If it doesn't have an end modal tag, let's look for the next modal.
$content = preg_replace('/<!-- MODAL: Printable Reservation Voucher -->.*?(?=<!-- MODAL: Update Status -->|<!-- Modal: Delete Confirmation -->|<\?php endforeach; \?>)/is', '', $content);


// 5. Remove Javascript function
$content = preg_replace('/function printVoucher\(printAreaId\) \{.*?\}/s', '', $content);

// Clean up extra blank lines
$content = preg_replace("/\n\s*\n\s*\n/", "\n\n", $content);

file_put_contents($file, $content);
echo "Cleaned reservations.php\n";
