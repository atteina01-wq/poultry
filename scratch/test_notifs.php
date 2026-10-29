<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("
    SELECT n.*, u.username, u.role 
    FROM notifications n 
    LEFT JOIN users u ON n.user_id = u.user_id 
    ORDER BY n.notification_id DESC 
    LIMIT 10
");
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Recent notifications count: " . count($notifs) . "\n";
foreach ($notifs as $n) {
    echo "#" . $n['notification_id'] . " [User: " . ($n['username'] ?? 'ALL') . " | " . ($n['role'] ?? 'global') . "] [" . $n['status'] . "]: " . $n['message'] . "\n";
}
