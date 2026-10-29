<?php
include('database.php');
include('auth.php');

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $category = '';

    // Map activity type to inventory category
    $whereSql = "";
    switch ($type) {
        case 'Feeding':
            $whereSql = "category = 'feed'";
            break;
        case 'Health':
        case 'Health Inspection':
            $whereSql = "category IN ('medicine', 'vitamins')";
            break;
        case 'Supply':
            $whereSql = "category IN ('supply', 'vitamins', 'limestone')";
            break;
        default:
            echo json_encode([]);
            exit;
    }

    try {
        $stmt = $conn->prepare("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE {$whereSql} AND is_active = 1 ORDER BY item_name");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($items);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode([]);
}
?>