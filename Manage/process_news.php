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

    $news_title = trim($_POST['news_title'] ?? '');
    $news_details = trim($_POST['news_details'] ?? '');
    $news_date = $_POST['news_date'] ?? '';
    $news_by = trim($_POST['news_by'] ?? '');

    if ($news_title === '' || $news_details === '' || $news_date === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_news.php');
        exit;
    }

    $insert = $pdo->prepare("INSERT INTO news (news_title, news_details, news_date, news_by) VALUES (?, ?, ?, ?)");
    $insert->execute([$news_title, $news_details, $news_date, $news_by === '' ? null : $news_by]);

    $_SESSION['success'] = 'เผยแพร่ข่าวเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_news.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_news.php');
    exit;
}
