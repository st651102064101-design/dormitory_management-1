<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'room_id' => '1',
    'name' => 'ทดสอบ ระบบจอง',
    'phone' => '0812345678',
    'ctr_start' => '2026-01-15',
    'ctr_end' => '2026-07-15',
    'deposit' => '2000'
];

include 'booking.php';
