<?php
/**
 * QR Code Generator API
 * สร้าง QR Code เป็นรูปภาพ PNG
 */
require_once __DIR__ . '/phpqrcode.php';

$data = $_GET['data'] ?? '';

if (empty($data)) {
    http_response_code(400);
    die('Missing data parameter');
}

// สร้าง QR Code และส่งออกเป็น PNG
header('Access-Control-Allow-Origin: *');
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

// ตรวจสอบว่า download parameter มีหรือไม่
$isDownload = isset($_GET['download']) && $_GET['download'] === '1';
if ($isDownload) {
    $filename = isset($_GET['filename']) ? basename($_GET['filename']) . '.png' : 'QR_Code.png';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

QRcode::png($data, false, QR_ECLEVEL_M, 6, 2);
