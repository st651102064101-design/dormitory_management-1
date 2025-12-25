<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $room_number = trim($_POST['room_number'] ?? '');
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;

    if ($room_id <= 0 || $room_number === '' || $type_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    // ตรวจสอบหมายเลขห้องซ้ำ (ไม่ซ้ำกับห้องเดียวกัน)
    $check = $pdo->prepare("SELECT room_id FROM room WHERE room_number = ? AND room_id != ?");
    $check->execute([$room_number, $room_id]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'หมายเลขห้องนี้มีอยู่แล้ว']);
        exit;
    }

    // ดึงข้อมูลเดิม
    $old = $pdo->prepare("SELECT room_image, room_status FROM room WHERE room_id = ?");
    $old->execute([$room_id]);
    $oldData = $old->fetch(PDO::FETCH_ASSOC);
    $room_image = $oldData['room_image'];
    // คงสถานะห้องเดิม ไม่เปิดให้แก้ไขผ่านฟอร์มนี้
    $room_status = $oldData['room_status'];

    // ตรวจสอบว่าต้องการลบรูปภาพหรือไม่
    $deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';
    
    if ($deleteImage && $room_image) {
        // ลบรูปภาพเก่า
        $oldPath = __DIR__ . '/..//Assets/Images/Rooms/' . $room_image;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
        $room_image = null;
    }

    // อัพโหลดรูปภาพใหม่ถ้ามี
    if (!empty($_FILES['room_image']['name'])) {
        $file = $_FILES['room_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'รูปแบบไฟล์ไม่ถูกต้อง']);
            exit;
        }

        // ลบรูปเก่า
        if ($room_image) {
            $oldPath = __DIR__ . '/..//Assets/Images/Rooms/' . $room_image;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $uploadDir = __DIR__ . '/..//Assets/Images/Rooms/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = time() . '_' . basename($file['name']);
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $room_image = $filename;
        }
    }

    $update = $pdo->prepare("UPDATE room SET room_number = ?, type_id = ?, room_image = ? WHERE room_id = ?");
    $update->execute([$room_number, $type_id, $room_image, $room_id]);

    // ดึงข้อมูลห้องที่อัปเดตพร้อมชื่อประเภทห้อง
    $roomQuery = $pdo->prepare("
        SELECT r.*, rt.type_name, rt.type_price 
        FROM room r 
        LEFT JOIN room_type rt ON r.type_id = rt.type_id 
        WHERE r.room_id = ?
    ");
    $roomQuery->execute([$room_id]);
    $updatedRoom = $roomQuery->fetch(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => 'แก้ไขห้องพัก ห้อง ' . htmlspecialchars($room_number) . ' เรียบร้อยแล้ว',
        'room' => $updatedRoom
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
