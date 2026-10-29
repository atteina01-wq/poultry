<?php
require __DIR__ . '/../poultrychat/database.php';

echo "Setting up realistic House & Batch demo data...\n";

// Ensure houses table has the status column allowing 'reserved'
try {
    $conn->exec("ALTER TABLE houses MODIFY COLUMN status ENUM('available', 'occupied', 'cleaning', 'reserved') DEFAULT 'available'");
    echo "[OK] Updated houses.status enum to include 'reserved'.\n";
} catch (Exception $e) {
    echo "[NOTE] " . $e->getMessage() . "\n";
}

// 1. Batch 40 in House 1: Mature Market Ready (Day 35, 1.88 kg)
$conn->exec("UPDATE batches SET arrival_date = DATE_SUB(CURDATE(), INTERVAL 35 DAY), production_date = DATE_SUB(CURDATE(), INTERVAL 35 DAY), target_weight_kg = 1.80, current_weight_kg = 1.88, house_pen = 'House 1', status = 'active' WHERE batch_id = 40");
$conn->exec("UPDATE houses SET status = 'occupied', current_batch_id = 40, vacant_date = DATE_ADD(CURDATE(), INTERVAL 2 DAY), notes = 'Tunnel-ventilated house. Flock mature & ready for release' WHERE house_id = 1");

// 2. House 2: Completely VACANT and Cleaned, Ready for new reservation
$conn->exec("UPDATE houses SET status = 'available', current_batch_id = NULL, vacant_date = NULL, notes = 'Sanitized, disinfected, and ready for Day-Old Chicks intake' WHERE house_id = 2");

// 3. Batch 41 in House 3: Brooding Stage (Day 8, 0.22 kg)
$conn->exec("UPDATE batches SET arrival_date = DATE_SUB(CURDATE(), INTERVAL 8 DAY), production_date = DATE_SUB(CURDATE(), INTERVAL 8 DAY), target_weight_kg = 1.80, current_weight_kg = 0.22, house_pen = 'House 3', status = 'active' WHERE batch_id = 41");
$conn->exec("UPDATE houses SET status = 'occupied', current_batch_id = 41, vacant_date = DATE_ADD(CURDATE(), INTERVAL 27 DAY), notes = 'High-capacity pen currently in brooding/starter phase' WHERE house_id = 3");

// 4. Batch 37 in House 4: Growing Stage (Day 18, 0.72 kg)
$conn->exec("UPDATE batches SET arrival_date = DATE_SUB(CURDATE(), INTERVAL 18 DAY), production_date = DATE_SUB(CURDATE(), INTERVAL 18 DAY), target_weight_kg = 1.80, current_weight_kg = 0.72, house_pen = 'House 4', status = 'active' WHERE batch_id = 37");
$conn->exec("UPDATE houses SET status = 'occupied', current_batch_id = 37, vacant_date = DATE_ADD(CURDATE(), INTERVAL 17 DAY), notes = 'Pad cooling active, grower pellet feeding stage' WHERE house_id = 4");

// 5. House 5: VACANT and Available
$conn->exec("UPDATE houses SET house_name = 'House 5', status = 'available', current_batch_id = NULL, vacant_date = NULL, notes = 'Commercial grow-out house, heaters pre-tested' WHERE house_id = 5");

echo "[OK] Demo houses and batches initialized!\n";
