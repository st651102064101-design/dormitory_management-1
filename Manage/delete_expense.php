<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

$exp_id = (int)($_POST['exp_id'] ?? 0);

if (!$exp_id) {
    die(json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']));
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dormitory_db;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    $stmt = $pdo->prepare('DELETE FROM expense WHERE exp_id = ?');
    $stmt->execute([$exp_id]);
    
    echo json_encode(['success' => true, 'message' => 'ลบรายการค่าใช้จ่ายเรียบร้อยแล้ว']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในฐานข้อมูล']);
}
?>
