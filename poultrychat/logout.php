<?php
session_start();
session_unset();
session_destroy();
header('Location: index.php?auth=login&logged_out=1');
exit();
?>
