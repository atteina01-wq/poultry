<?php
$_SESSION = [
    'user_id' => 2,
    'role' => 'staff',
    'full_name' => 'Farm Worker',
    'username' => 'worker'
];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
include('activities.php');
$out = ob_get_clean();
$tasks_tab_start = strpos($out, 'id="pills-tasks"');
if ($tasks_tab_start === false) {
    echo "pills-tasks not found!\n";
} else {
    echo "Found pills-tasks. Length of remaining HTML: " . strlen(substr($out, $tasks_tab_start)) . "\n";
    echo substr($out, $tasks_tab_start, 2000);
}
