<?php
declare(strict_types=1);
session_start();

// Check if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (empty($_SESSION['admin_username'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบก่อน']);
        exit;
    }
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    header('Location: ../Reports/manage_news.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $news_id = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;

    if ($news_id <= 0) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }
        $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
        header('Location: ../Reports/manage_news.php');
        exit;
    }

    $delete = $pdo->prepare("DELETE FROM news WHERE news_id = ?");
    $result = $delete->execute([$news_id]);

    if ($result) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'ลบข่าวเรียบร้อยแล้ว']);
            exit;
        }
        $_SESSION['success'] = 'ลบข่าวเรียบร้อยแล้ว';
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบข่าวได้']);
            exit;
        }
        $_SESSION['error'] = 'ไม่สามารถลบข่าวได้';
    }
    header('Location: ../Reports/manage_news.php');
    exit;
} catch (PDOException $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        exit;
    }
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_news.php');
    exit;
}
