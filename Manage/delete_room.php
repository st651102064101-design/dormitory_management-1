<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาต']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

    if ($room_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    // ดึงข้อมูลห้อง
    $stmt = $pdo->prepare("SELECT room_image, room_number FROM room WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบห้องพัก']);
        exit;
    }

    // ตรวจสอบว่ามีสัญญาที่ยังไม่เสร็จ
    $stmtContract = $pdo->prepare("SELECT COUNT(*) as count FROM contract WHERE room_id = ? AND ctr_status IN ('0', '2')");
    $stmtContract->execute([$room_id]);
    $contractResult = $stmtContract->fetch(PDO::FETCH_ASSOC);
    
    if ($contractResult['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ - มีผู้เช่าอยู่ในห้องนี้']);
        exit;
    }

    // ลบรูปภาพ
    if ($room['room_image']) {
        $imagePath = __DIR__ . '/..' . str_replace('/Dormitory_Management', '', $room['room_image']);
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $delete = $pdo->prepare("DELETE FROM room WHERE room_id = ?");
    $delete->execute([$room_id]);

    echo json_encode([
        'success' => true,
        'message' => 'ลบห้องพัก ห้อง ' . htmlspecialchars($room['room_number']) . ' เรียบร้อยแล้ว'
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
