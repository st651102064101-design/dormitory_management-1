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

    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

    if ($room_id <= 0) {
        $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
        header('Location: ../Reports/manage_rooms.php');
        exit;
    }

    // ดึงข้อมูลห้อง
    $stmt = $pdo->prepare("SELECT room_image, room_number FROM room WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        $_SESSION['error'] = 'ไม่พบห้องพัก';
        header('Location: ../Reports/manage_rooms.php');
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

    $_SESSION['success'] = 'ลบห้องพัก ห้อง ' . htmlspecialchars($room['room_number']) . ' เรียบร้อยแล้ว';
    header('Location: ../Reports/manage_rooms.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_rooms.php');
    exit;
}
