<?php
/**
 * Legacy staff login URL — unified into the Partner Portal landing page.
 */
$params = [];
if (!empty($_GET['logged_out'])) {
    $params['logged_out'] = '1';
}
$params['auth'] = 'login';
header('Location: index.php?' . http_build_query($params));
exit();
