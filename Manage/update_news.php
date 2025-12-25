<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', '0');

session_start();

// Check if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Set JSON header for AJAX
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if (empty($_SESSION['admin_username'])) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบก่อน']);
        exit;
    }
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    header('Location: ../Reports/manage_news.php');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=localhost:3306;dbname=dormitory_management_db;charset=utf8mb4",
        'root',
        '12345678',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $news_id = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
    $news_title = trim($_POST['news_title'] ?? '');
    $news_details = trim($_POST['news_details'] ?? '');
    $news_date = $_POST['news_date'] ?? '';
    $news_by = trim($_POST['news_by'] ?? '');

    if ($news_id <= 0 || $news_title === '' || $news_details === '' || $news_date === '') {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit;
        }
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_news.php');
        exit;
    }

    $update = $pdo->prepare("UPDATE news SET news_title = ?, news_details = ?, news_date = ?, news_by = ? WHERE news_id = ?");
    $result = $update->execute([$news_title, $news_details, $news_date, $news_by === '' ? null : $news_by, $news_id]);

    if ($result) {
        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => 'แก้ไขข่าวเรียบร้อยแล้ว']);
            exit;
        }
        $_SESSION['success'] = 'แก้ไขข่าวเรียบร้อยแล้ว';
        header('Location: ../Reports/manage_news.php');
        exit;
    } else {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถแก้ไขข่าวได้']);
            exit;
        }
        $_SESSION['error'] = 'ไม่สามารถแก้ไขข่าวได้';
        header('Location: ../Reports/manage_news.php');
        exit;
    }
} catch (PDOException $e) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_news.php');
    exit;
}
