<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $tntIdCard = preg_replace('/\D+/', '', trim($_POST['tnt_idcard'] ?? ''));
    $tntIdOriginal = trim($_POST['tnt_id_original'] ?? '');

    if (!preg_match('/^\d{13}$/', $tntIdCard)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'
        ]);
        exit;
    }

    $baseSql = "SELECT
            t.tnt_id,
            COALESCE(NULLIF(TRIM(t.tnt_name), ''), '-') AS tnt_name,
            (
                SELECT r.room_number
                FROM contract c
                JOIN room r ON r.room_id = c.room_id
                WHERE c.tnt_id = t.tnt_id
                ORDER BY (c.ctr_status = '0') DESC, (c.ctr_status = '2') DESC, c.ctr_id DESC
                LIMIT 1
            ) AS room_number
        FROM tenant t
        WHERE t.tnt_idcard = :tnt_idcard";

    $params = [':tnt_idcard' => $tntIdCard];
    if ($tntIdOriginal !== '') {
        $baseSql .= " AND t.tnt_id <> :tnt_id_original";
        $params[':tnt_id_original'] = $tntIdOriginal;
    }

    $baseSql .= " LIMIT 1";

    $stmt = $pdo->prepare($baseSql);
    $stmt->execute($params);

    $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($duplicate) {
        $roomNumber = trim((string)($duplicate['room_number'] ?? ''));
        $roomText = $roomNumber !== '' ? (' ห้อง ' . $roomNumber) : '';
        echo json_encode([
            'success' => true,
            'isDuplicate' => true,
            'message' => 'เลขบัตรนี้ซ้ำกับผู้เช่า ' . $duplicate['tnt_name'] . $roomText
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'isDuplicate' => false,
        'message' => 'เลขบัตรประชาชนนี้ใช้งานได้'
    ]);
} catch (Throwable $e) {
    error_log('check_tenant_idcard.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ระบบตรวจสอบข้อมูลขัดข้อง'
    ]);
}
