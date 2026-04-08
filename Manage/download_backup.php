<?php
session_start();
if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    die('ไม่ได้รับอนุญาต');
}

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = __DIR__ . '/../backups/' . $file;
    
    if (file_exists($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        die('ไม่พบไฟล์');
    }
} else {
    http_response_code(400);
    die('คำขอไม่ถูกต้อง');
}
