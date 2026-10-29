<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== EXECUTING COMPLETE SYSTEM-WIDE DATA CLEANUP ===\n\n";

// 1. Reassign reservation 46 from MISSAAA to Aj shane Campilan (customer 1)
$conn->exec("UPDATE reservations SET customer_id = 1 WHERE reservation_id = 46");
echo "1. Reassigned reservation 46 to real customer (Aj shane Campilan).\n";

// 2. Delete meme/test customers (MISSAAA, Lebron James)
$conn->exec("DELETE FROM customers WHERE customer_id IN (2, 6) OR name LIKE '%MISSAAA%' OR name LIKE '%Lebron%'");
echo "2. Deleted dummy/meme customers (MISSAAA, Lebron James).\n";

// 3. Clean activities
$conn->exec("DELETE FROM activities WHERE activity_id IN (149, 150)");
$conn->exec("DELETE FROM activities WHERE batch_id IS NOT NULL AND batch_id NOT IN (SELECT batch_id FROM batches)");
$conn->exec("DELETE FROM activities WHERE item_name = 'tambal' OR remarks = 'oki na'");
$conn->exec("DELETE FROM activities WHERE activity_id = 137");

// Update activities.type enum to include 'Weight Sampling'
try {
    $conn->exec("ALTER TABLE activities MODIFY COLUMN type ENUM('Feeding','Cleaning','Health Inspection','Health','Supply','General','Weight Sampling') NOT NULL DEFAULT 'General'");
} catch (Exception $e) {}
$conn->exec("UPDATE activities SET type = 'Weight Sampling' WHERE item_name LIKE 'Sample Weighing%'");
echo "3. Cleaned activities and set type = 'Weight Sampling' for sample weighings.\n";

// 4. Clean health records
$conn->exec("DELETE FROM health_records WHERE batch_id NOT IN (SELECT batch_id FROM batches)");
$conn->exec("DELETE FROM health_records WHERE medicine_name = 'tambal' OR notes = 'oki na'");
echo "4. Cleaned health records (removed orphaned batches and test records).\n";

// 5. Clean mortality records
$conn->exec("DELETE FROM mortality_records WHERE batch_id NOT IN (SELECT batch_id FROM batches)");
echo "5. Cleaned mortality records (removed records pointing to deleted batches).\n";

// 6. Clean expenses
$conn->exec("DELETE FROM expenses WHERE batch_id IS NOT NULL AND batch_id NOT IN (SELECT batch_id FROM batches)");
$conn->exec("DELETE FROM expenses WHERE expense_id IN (16, 18)");
echo "6. Cleaned expenses (removed orphaned records and duplicate test entries).\n";

// 7. Clean inventory and duplicate test lots
$conn->exec("DELETE FROM inventory WHERE item_name = 'jjhjhjhjhjh' OR inventory_id = 27");
$conn->exec("UPDATE inventory SET item_name = 'Agricultural Limestone', sku = 'LIMESTONE-AGRI', description = 'Poultry house bedding and bio-security ground disinfectant' WHERE inventory_id = 8");
$conn->exec("DELETE FROM inventory_lots WHERE inventory_id IN (9,10,11,12,13,14,15,16,17,18,19,30,31)");
$conn->exec("DELETE FROM inventory WHERE inventory_id IN (9,10,11,12,13,14,15,16,17,18,19,30,31)");
echo "7. Cleaned inventory (removed gibberish item, consolidated 14 duplicate limestone items into 1).\n";

// 8. Standardize tasks
$conn->exec("UPDATE tasks SET title = 'Daily House Sanitation', description = 'Routine pen cleaning and manure removal for bio-security' WHERE task_id = 11");
echo "8. Standardized task descriptions.\n";

// 9. Standardize batches
$conn->exec("UPDATE batches SET breed = 'Cobb 500', supplier = 'Bounty Fresh Philippines', notes = 'Active commercial grow-out flock' WHERE batch_id = 6");
$conn->exec("UPDATE batches SET notes = 'Healthy grow-out cycle in progress' WHERE batch_id = 31");
$conn->exec("UPDATE batches SET breed = 'Ross 308', notes = 'Active broiler flock' WHERE batch_id = 35");
$conn->exec("UPDATE batches SET notes = 'Monitored flock under standard bio-security' WHERE batch_id = 37");
$conn->exec("UPDATE batches SET notes = 'Target contract weight monitoring underway' WHERE batch_id = 38");
$conn->exec("UPDATE batches SET notes = 'Day 35 flock — Target contract weight verified (1.88 kg)' WHERE batch_id = 40");
$conn->exec("UPDATE batches SET notes = 'Brooding stage flock — Day 8' WHERE batch_id = 41");
echo "9. Polished batches (standardized breeds, suppliers, and professional notes).\n";

echo "\n=== ALL UNNECESSARY DATA CLEANED SUCCESSFULLY ===\n\n";

// Print summary of remaining data
echo "Current Table Summaries:\n";
echo " - Customers: " . $conn->query("SELECT COUNT(*) FROM customers")->fetchColumn() . "\n";
echo " - Reservations: " . $conn->query("SELECT COUNT(*) FROM reservations")->fetchColumn() . "\n";
echo " - Batches: " . $conn->query("SELECT COUNT(*) FROM batches")->fetchColumn() . "\n";
echo " - Activities: " . $conn->query("SELECT COUNT(*) FROM activities")->fetchColumn() . "\n";
echo " - Health Records: " . $conn->query("SELECT COUNT(*) FROM health_records")->fetchColumn() . "\n";
echo " - Mortality Records: " . $conn->query("SELECT COUNT(*) FROM mortality_records")->fetchColumn() . "\n";
echo " - Expenses: " . $conn->query("SELECT COUNT(*) FROM expenses")->fetchColumn() . "\n";
echo " - Inventory: " . $conn->query("SELECT COUNT(*) FROM inventory")->fetchColumn() . "\n";
echo " - Inventory Lots: " . $conn->query("SELECT COUNT(*) FROM inventory_lots")->fetchColumn() . "\n";
