<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

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

    $roomNumber = isset($_POST['room_number']) ? trim($_POST['room_number']) : '';
    $typeId = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    
    if ($roomNumber === '' || $typeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    // ตรวจสอบห้องซ้ำ
    $stmtCheck = $pdo->prepare('SELECT room_id FROM room WHERE room_number = ?');
    $stmtCheck->execute([$roomNumber]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'หมายเลขห้องนี้มีอยู่แล้ว']);
        exit;
    }

    // อัปโหลดรูปถ้ามี
    $roomImage = '';
    if (!empty($_FILES['room_image']['name'])) {
        $file = $_FILES['room_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($file['type'], $allowed, true)) {
            $uploadDir = __DIR__ . '/../Assets/Images/Rooms/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = time() . '_' . basename($file['name']);
            $filepath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $roomImage = $filename;
            }
        }
    }

    // บันทึกข้อมูลห้อง
    $roomStatus = '0'; // ห้องใหม่เริ่มต้นเป็นว่าง
    $stmtInsert = $pdo->prepare('INSERT INTO room (room_number, type_id, room_status, room_image) VALUES (?, ?, ?, ?)');
    $stmtInsert->execute([$roomNumber, $typeId, $roomStatus, $roomImage]);
    
    $roomId = $pdo->lastInsertId();
    
    // ดึงข้อมูลห้องที่เพิ่มเพื่อส่งกลับ
    $stmt = $pdo->prepare('SELECT r.room_id, r.room_number, r.room_status, r.room_image, rt.type_name, rt.type_price FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id WHERE r.room_id = ?');
    $stmt->execute([$roomId]);
    $newRoom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newRoom) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถดึงข้อมูลห้องที่เพิ่มได้']);
        exit;
    }

    // คำนวณหมายเลขห้องถัดไป
    $stmtMax = $pdo->prepare('SELECT MAX(CAST(room_number AS UNSIGNED)) as maxNum FROM room');
    $stmtMax->execute();
    $maxResult = $stmtMax->fetch(PDO::FETCH_ASSOC);
    $maxNum = $maxResult['maxNum'] ?? 0;
    $nextRoomNum = str_pad(($maxNum + 1), 2, '0', STR_PAD_LEFT);

    echo json_encode([
        'success' => true,
        'message' => 'เพิ่มห้องพัก ห้อง ' . htmlspecialchars($roomNumber) . ' เรียบร้อยแล้ว',
        'room' => $newRoom,
        'nextRoomNumber' => $nextRoomNum
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
