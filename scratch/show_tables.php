<?php
require 'c:\xampp\htdocs\poultry\poultrychat\database.php';
print_r($conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
