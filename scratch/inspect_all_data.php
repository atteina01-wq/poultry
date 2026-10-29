<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "--- 1. ACTIVITIES ---\n";
$acts = $conn->query("SELECT * FROM activities ORDER BY activity_id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($acts as $a) {
    echo "ID: {$a['activity_id']} | BatchID: {$a['batch_id']} | Type: {$a['type']} | Item: {$a['item_name']} | Qty: {$a['quantity']} | Date: {$a['date']} | Remarks: {$a['remarks']}\n";
}

echo "\n--- 2. CUSTOMERS ---\n";
$custs = $conn->query("SELECT * FROM customers")->fetchAll(PDO::FETCH_ASSOC);
foreach ($custs as $c) {
    echo "ID: {$c['customer_id']} | Code: {$c['customer_code']} | Name: {$c['name']} | Phone: {$c['phone']} | Email: {$c['email']} | Active: {$c['is_active']}\n";
}

echo "\n--- 3. HEALTH RECORDS ---\n";
$health = $conn->query("SELECT * FROM health_records")->fetchAll(PDO::FETCH_ASSOC);
foreach ($health as $h) {
    echo "ID: {$h['record_id']} | BatchID: {$h['batch_id']} | Date: {$h['record_date']} | Symptoms: {$h['symptoms']} | Diagnosis: {$h['diagnosis']} | Med: {$h['medication_given']} | Status: {$h['status']}\n";
}

echo "\n--- 4. EXPENSES ---\n";
$exp = $conn->query("SELECT * FROM expenses")->fetchAll(PDO::FETCH_ASSOC);
foreach ($exp as $e) {
    echo "ID: {$e['expense_id']} | BatchID: {$e['batch_id']} | Category: {$e['category']} | Desc: {$e['description']} | Amount: {$e['amount']} | Date: {$e['expense_date']}\n";
}

echo "\n--- 5. TASKS ---\n";
$tasks = $conn->query("SELECT * FROM tasks")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tasks as $t) {
    echo "ID: {$t['task_id']} | House: {$t['house_id']} | Title: {$t['title']} | Status: {$t['status']} | Desc: {$t['description']}\n";
}

echo "\n--- 6. BATCHES ---\n";
$batches = $conn->query("SELECT batch_id, batch_code, breed, house_pen, status, initial_quantity, current_quantity, supplier, notes FROM batches")->fetchAll(PDO::FETCH_ASSOC);
foreach ($batches as $b) {
    echo "ID: {$b['batch_id']} | Code: {$b['batch_code']} | Breed: {$b['breed']} | House: {$b['house_pen']} | Status: {$b['status']} | Init: {$b['initial_quantity']} | Cur: {$b['current_quantity']} | Supplier: {$b['supplier']} | Notes: {$b['notes']}\n";
}

echo "\n--- 7. INVENTORY (First 15) ---\n";
$inv = $conn->query("SELECT inventory_id, item_code, item_name, category, quantity, unit, unit_cost FROM inventory LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
foreach ($inv as $i) {
    echo "ID: {$i['inventory_id']} | Code: {$i['item_code']} | Name: {$i['item_name']} | Cat: {$i['category']} | Qty: {$i['quantity']} {$i['unit']} | Cost: {$i['unit_cost']}\n";
}
