<?php
session_start();
$_SESSION['admin_username'] = 'admin';
$_SESSION['csrf_token'] = 'test';
$_POST['csrf_token'] = 'test';
$_POST['bkg_id'] = 999999999;
$_POST['tnt_id'] = '1111111111';

// We need to set REQUEST_METHOD
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'Manage/cancel_booking.php';
$json = ob_get_clean();
echo "RESULT:\n" . $json;
