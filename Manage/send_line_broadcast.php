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

if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มี LINE Channel Token กรุณาตั้งค่าในระบบก่อน']);
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
    'Authorization: Bearer ' . trim($token)
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['success' => true]);
} else {
    $errData = json_decode($result, true);
    $errMsg = $errData['message'] ?? $result;
    echo json_encode(['success' => false, 'error' => 'LINE API Error: ' . $errMsg]);
}
