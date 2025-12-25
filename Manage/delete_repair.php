<?php
// ลบรายการแจ้งซ่อม
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_repairs.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    $pdo = connectDB();
    $repair_id = isset($_POST['repair_id']) ? (int)$_POST['repair_id'] : 0;

    if ($repair_id <= 0) {
        $msg = 'ข้อมูลไม่ถูกต้อง';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }

    $existsStmt = $pdo->prepare('SELECT repair_id, repair_image FROM repair WHERE repair_id = ?');
    $existsStmt->execute([$repair_id]);
    $repair = $existsStmt->fetch(PDO::FETCH_ASSOC);
    if (!$repair) {
        $msg = 'ไม่พบรายการแจ้งซ่อม';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        header('Location: ../Reports/manage_repairs.php');
        exit;
    }
    
    // ลบไฟล์รูปภาพ
    if (!empty($repair['repair_image'])) {
        $imagePath = __DIR__ . '/..//Assets/Images/Repairs/' . $repair['repair_image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $del = $pdo->prepare('DELETE FROM repair WHERE repair_id = ?');
    $del->execute([$repair_id]);

    $_SESSION['success'] = 'ลบรายการแจ้งซ่อมเรียบร้อยแล้ว';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'ยกเลิกการแจ้งซ่อมและลบรูปภาพแล้ว']);
        exit;
    }
    
    header('Location: ../Reports/manage_repairs.php');
    exit;
} catch (PDOException $e) {
    $msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    $_SESSION['error'] = $msg;
    header('Location: ../Reports/manage_repairs.php');
    exit;
}
