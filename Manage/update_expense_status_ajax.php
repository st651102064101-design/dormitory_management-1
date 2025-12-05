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
$exp_status = (int)($_POST['exp_status'] ?? 0);

if (!$exp_id || !in_array($exp_status, [0, 1], true)) {
    die(json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']));
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();
    
    $stmt = $pdo->prepare('UPDATE expense SET exp_status = ? WHERE exp_id = ?');
    $success = $stmt->execute([$exp_status, $exp_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        $statusText = $exp_status === 0 ? 'ยังไม่จ่าย' : 'จ่ายแล้ว';
        echo json_encode(['success' => true, 'message' => "เปลี่ยนสถานะเป็น \"$statusText\" เรียบร้อยแล้ว"]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการค่าใช้จ่ายที่ต้องการอัพเดท']);
    }
} catch (PDOException $e) {
    error_log('Update expense status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Update expense status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>
