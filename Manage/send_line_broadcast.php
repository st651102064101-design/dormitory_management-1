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

$data = [
    'messages' => [
        [
            'type' => 'text',
            'text' => $message
        ]
    ]
];

$ch = curl_init('https://api.line.me/v2/bot/message/broadcast');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$curlError = '';

$result = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($result === false) {
    echo json_encode([
        'success' => false,
        'error' => 'ไม่สามารถเชื่อมต่อ LINE API ได้: ' . ($curlError !== '' ? $curlError : 'unknown cURL error')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode === 200) {
    echo json_encode(['success' => true]);
} else {
    $errData = json_decode($result, true);
    $errMsg = $errData['message'] ?? $result;
    $detail = '';
    if (!empty($errData['details']) && is_array($errData['details'])) {
        $detail = ' (' . implode('; ', array_map(static function ($item) {
            if (is_array($item) && isset($item['message'])) {
                return (string)$item['message'];
            }
            return (string)$item;
        }, $errData['details'])) . ')';
    }

    echo json_encode([
        'success' => false,
        'error' => 'LINE API Error [' . $httpCode . ']: ' . $errMsg . $detail
    ], JSON_UNESCAPED_UNICODE);
}
