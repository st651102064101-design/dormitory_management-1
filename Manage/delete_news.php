<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_news.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $news_id = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;

    if ($news_id <= 0) {
        $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
        header('Location: ../Reports/manage_news.php');
        exit;
    }

    $delete = $pdo->prepare("DELETE FROM news WHERE news_id = ?");
    $delete->execute([$news_id]);

    $_SESSION['success'] = 'ลบข่าวเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_news.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_news.php');
    exit;
}
