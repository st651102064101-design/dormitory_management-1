<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once 'ConnectDB.php';
$pdo = connectDB();
print_r($pdo->query("SELECT (SELECT COUNT(*) FROM contract WHERE ctr_status = '2') AS term_req, (SELECT COUNT(*) FROM deposit_refund WHERE refund_status = '0') as ref_pend")->fetch(PDO::FETCH_ASSOC));
