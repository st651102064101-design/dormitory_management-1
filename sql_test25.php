<?php
session_start();
$_SESSION['admin_username'] = 'admin01';
$_GET['completed'] = 1;
ob_start();
include 'Reports/tenant_wizard.php';
$html = ob_get_clean();
preg_match_all('/<tr[^>]*data-wiz-group="1"[^>]*>/is', $html, $matches);
echo "Completed rows matched: " . count($matches[0]) . "\n";
