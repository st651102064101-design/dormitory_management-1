<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(0);

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

header('Content-Type: application/json; charset=utf-8');

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

session_start();

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    $update = $pdo->prepare("UPDATE news SET news_title = ?, news_details = ?, news_date = ?, news_by = ? WHERE news_id = ?");
    $result = $update->execute([$news_title, $news_details, $news_date, $news_by === '' ? null : $news_by, $news_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'แก้ไขข่าวเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถแก้ไขข่าวได้']);
    }
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}