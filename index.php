<?php
declare(strict_types=1);
session_start();

// ถ้า logged in แล้ว ให้ไปหน้า dashboard
if (!empty($_SESSION['admin_username'])) {
    header('Location: Reports/manage_news.php');
    exit;
}

// ไม่งั้น ไปหน้า login
header('Location: Login.php');
exit;
