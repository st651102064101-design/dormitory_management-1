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
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

QRcode::png($data, false, QR_ECLEVEL_M, 6, 2);
