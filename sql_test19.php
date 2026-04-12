<?php
session_start();
$_SESSION['admin_username'] = 'admin01';
$_GET['bkg_id'] = 775954892;
$_GET['completed'] = 1;
require 'Reports/tenant_wizard.php';
