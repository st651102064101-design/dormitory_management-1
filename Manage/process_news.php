<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

// Log เพื่อ debug
error_log('process_news.php called');
error_log('Session admin_username: ' . ($_SESSION['admin_username'] ?? 'NOT SET'));

// Check if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (empty($_SESSION['admin_username'])) {
    error_log('No admin session - redirecting to login');
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบก่อน']);
        exit;
    }
    $_SESSION['error'] = 'กรุณาเข้าสู่ระบบก่อน';
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Not POST request - redirecting');
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

    $news_title = trim($_POST['news_title'] ?? '');
    $news_details = trim($_POST['news_details'] ?? '');
    $news_date = $_POST['news_date'] ?? '';
    $news_by = trim($_POST['news_by'] ?? '');

    if ($news_title === '' || $news_details === '' || $news_date === '') {
        error_log('Validation failed - missing required fields');
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit;
        }
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_news.php');
        exit;
    }

    error_log('Inserting news: ' . $news_title);
    $insert = $pdo->prepare("INSERT INTO news (news_title, news_details, news_date, news_by) VALUES (?, ?, ?, ?)");
    $result = $insert->execute([$news_title, $news_details, $news_date, $news_by === '' ? null : $news_by]);
    
    $lastId = $pdo->lastInsertId();
    error_log('Insert result: ' . ($result ? 'success' : 'failed') . ', Last ID: ' . $lastId);

    if ($result && $lastId > 0) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'เพิ่มข่าวเรียบร้อยแล้ว', 'news_id' => $lastId]);
            exit;
        }
        $_SESSION['success'] = 'เพิ่มข่าวเรียบร้อยแล้ว (ID: ' . $lastId . ')';
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
            exit;
        }
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
    }
    
    header('Location: ../Reports/manage_news.php');
    exit;
} catch (PDOException $e) {
    error_log('PDO Exception in process_news.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        exit;
    }
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_news.php');
    exit;
} catch (Exception $e) {
    error_log('General Exception in process_news.php: ' . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
        exit;
    }
    $_SESSION['error'] = 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage();
    header('Location: ../Reports/manage_news.php');
    exit;
}
