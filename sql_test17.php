<?php
require 'ConnectDB.php';
$pdo = connectDB();
$_GET['bkg_id'] = 775954892;
$_GET['completed'] = 1;
include 'Reports/tenant_wizard.php';
