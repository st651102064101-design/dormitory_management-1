<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/dormitory_management/Reports/dashboard.php';
session_start();
$_SESSION['admin_username'] = 'admin';
$_SESSION['admin_name'] = 'Admin';
ob_start();
require 'Reports/dashboard.php';
$html = ob_get_clean();
file_put_contents('dash_browser.html', $html);
