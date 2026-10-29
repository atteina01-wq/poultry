<?php
/**
 * Legacy integrator login URL — unified into the Partner Portal landing page.
 */
$params = [];
$tab = $_GET['tab'] ?? '';
if ($tab === 'register') {
    $params['auth'] = 'register';
} else {
    $params['auth'] = 'login';
}
if (!empty($_GET['logged_out'])) {
    $params['logged_out'] = '1';
}
if (!empty($_GET['registered'])) {
    $params['registered'] = '1';
}
if (!empty($_GET['u'])) {
    $params['u'] = $_GET['u'];
}
header('Location: index.php?' . http_build_query($params));
exit();
