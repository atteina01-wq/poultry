<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

function get_dropdown_options($file) {
    global $conn;
    $filterBatch = 0;
    $target_category = 'feed';
    ob_start();
    if ($file === 'healthrecords.php') {
        $batches = $conn->query("SELECT * FROM batches WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) ORDER BY arrival_date DESC, batch_id DESC");
        foreach($batches as $b) {
            $selected = ($filterBatch === (int)$b['batch_id']) ? 'selected' : '';
            $hp = !empty($b['house_pen']) ? ' - ' . htmlspecialchars($b['house_pen']) : '';
            echo "<option value='{$b['batch_id']}' {$selected}>" . htmlspecialchars($b['batch_code']) . $hp . " (Broiler - " . htmlspecialchars($b['breed'] ?: 'Cobb 500') . ")</option>\n";
        }
    } elseif ($file === 'feed.php') {
        $stmtBatches = $conn->query("SELECT batch_id, batch_code, breed, house_pen FROM batches WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) ORDER BY arrival_date DESC, batch_id DESC");
        $batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);
        foreach($batches as $b) {
            $hp = !empty($b['house_pen']) ? ' - ' . htmlspecialchars($b['house_pen']) : '';
            echo "<option value='{$b['batch_id']}'>" . htmlspecialchars($b['batch_code']) . $hp . " (Broiler - " . htmlspecialchars($b['breed'] ?: 'Cobb 500') . ")</option>\n";
        }
    } elseif ($file === 'activities.php') {
        $batches = $conn->query("SELECT * FROM batches WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) ORDER BY arrival_date DESC, batch_id DESC");
        foreach($batches as $b) {
            $hp = !empty($b['house_pen']) ? ' - ' . htmlspecialchars($b['house_pen']) : '';
            $breedStr = !empty($b['breed']) ? ' (Broiler - ' . htmlspecialchars($b['breed']) . ')' : ' (Broiler - Cobb 500)';
            echo "<option value='{$b['batch_id']}'>" . htmlspecialchars($b['batch_code']) . $hp . $breedStr . "</option>\n";
        }
    }
    return ob_get_clean();
}

echo "=== HEALTHRECORDS DROPDOWN ===\n" . get_dropdown_options('healthrecords.php');
echo "\n=== FEED DROPDOWN ===\n" . get_dropdown_options('feed.php');
echo "\n=== ACTIVITIES DROPDOWN ===\n" . get_dropdown_options('activities.php');

echo "\n=== TEST AUTOMATIC APPEARANCE OF NEW BATCH ===\n";
// Insert a temporary test batch to verify automatic appearance
$conn->exec("INSERT INTO batches (batch_code, house_pen, breed, status, arrival_date, flock_type, initial_quantity, current_quantity) VALUES ('BR-TEST-AUTONEW', 'House 2', 'Ross 308', 'active', CURDATE(), 'broiler', 5000, 5000)");
$newId = $conn->lastInsertId();

echo "After inserting new batch (BR-TEST-AUTONEW in House 2):\n";
echo "Healthrecords options:\n" . get_dropdown_options('healthrecords.php');
echo "Feed options:\n" . get_dropdown_options('feed.php');
echo "Activities options:\n" . get_dropdown_options('activities.php');

// Clean up the temporary test batch
$conn->exec("DELETE FROM batches WHERE batch_id = {$newId}");
echo "\nTemporary test batch cleaned up.\n";
