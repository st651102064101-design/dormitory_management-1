<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_rooms.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $room_number = trim($_POST['room_number'] ?? '');
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    // สถานะห้องใหม่ให้เป็น "ว่าง" โดยอัตโนมัติ (0)
    $room_status = '0';

    if ($room_number === '' || $type_id <= 0) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_rooms.php');
        exit;
    }

    // ตรวจสอบหมายเลขห้องซ้ำ
    $check = $pdo->prepare("SELECT room_id FROM room WHERE room_number = ?");
    $check->execute([$room_number]);
    if ($check->fetch()) {
        $_SESSION['error'] = 'หมายเลขห้องนี้มีอยู่แล้ว';
        header('Location: ../Reports/manage_rooms.php');
        exit;
    }

    $room_image = '';
    if (!empty($_FILES['room_image']['name'])) {
        $file = $_FILES['room_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowed)) {
            $_SESSION['error'] = 'รูปแบบไฟล์ไม่ถูกต้อง';
            header('Location: ../Reports/manage_rooms.php');
            exit;
        }

        $uploadDir = __DIR__ . '/../Assets/Images/Rooms/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = time() . '_' . basename($file['name']);
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $room_image = $filename;
        } else {
            $room_image = '';
        }
    }

    $insert = $pdo->prepare("INSERT INTO room (room_number, type_id, room_status, room_image) VALUES (?, ?, ?, ?)");
    $insert->execute([$room_number, $type_id, $room_status, $room_image]);

    $_SESSION['success'] = 'เพิ่มห้องพัก ห้อง ' . htmlspecialchars($room_number) . ' เรียบร้อยแล้ว';
    header('Location: ../Reports/manage_rooms.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_rooms.php');
    exit;
}
