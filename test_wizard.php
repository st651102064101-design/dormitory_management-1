<?php
session_start();
$_SESSION['admin_username'] = 'admin';
$_SESSION['role'] = 'admin';
ob_start();
require 'Reports/tenant_wizard.php';
$html = ob_get_clean();
echo "HTML LENGTH: " . strlen($html) . "\n";
file_put_contents('test_wizard.html', $html);
