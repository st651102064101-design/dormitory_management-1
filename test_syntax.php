<?php
session_start();
$_SESSION['admin_username'] = 'admin';
$_SESSION['admin_name'] = 'Admin';
ob_start();
require 'Reports/dashboard.php';
$html = ob_get_clean();
file_put_contents('dash_output.html', $html);
