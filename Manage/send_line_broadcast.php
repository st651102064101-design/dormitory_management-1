<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุข้อความ']);
    exit;
}

$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_channel_token'");
$token = $stmt->fetchColumn();
$token = is_string($token) ? trim($token) : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มี LINE Channel Token กรุณาตั้งค่าในระบบก่อน']);
    exit;
}

// Guard against common misconfiguration: channel ID is numeric, but token is a long opaque string.
if (preg_match('/^\d+$/', $token) === 1) {
    echo json_encode([
        'success' => false,
        'error' => 'ค่า Channel Access Token ไม่ถูกต้อง (ดูเหมือนใส่ Channel ID แทน) กรุณาสร้าง Long-lived token จาก LINE Developers แล้วบันทึกใหม่'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($token) < 30) {
    echo json_encode([
        'success' => false,
        'error' => 'ค่า Channel Access Token สั้นเกินไปหรือรูปแบบไม่ถูกต้อง กรุณาคัดลอก Long-lived token ทั้งชุดจาก LINE Developers'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../LineHelper.php';

// ใช้ sendLineBroadcast จาก LineHelper ซึ่งถูกปรับให้อ่านเฉพาะคนที่มีการผูกเบอร์เท่านั้น
$success = sendLineBroadcast($pdo, $message);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'ไม่สามารถส่งข้อความได้ (อาจไม่มีผู้เช่าที่ผูกเบอร์อยู่หรือ Token ไม่ถูกต้อง)'
    ], JSON_UNESCAPED_UNICODE);
}

