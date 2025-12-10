<?php
session_start();
$_SESSION['admin_username'] = 'test';
chdir(__DIR__);
ob_start();
include 'manage_contracts.php';
$html = ob_get_clean();
if (preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', $html, $m)) {
    echo "TBODY length=" . strlen($m[1]) . "\n";
    echo $m[1];
} else {
    echo "No tbody matched";
}
