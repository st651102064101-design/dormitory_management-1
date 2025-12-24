<?php
/**
 * ไฟล์ตั้งค่าหลักของระบบ
 * แก้ไขค่าต่างๆ ที่นี่
 */


// ===========================================
// ตั้งค่ากลางสำหรับ URL/IP/PORT
// ===========================================
// แก้ไขค่าตรงนี้เมื่อย้าย server หรือเปลี่ยน endpoint
// แนะนำ: แก้ไขเฉพาะค่านี้บน server ใหม่ (หรือกำหนดเป็น environment variables)
$env = function($name, $default = '') {
    $v = getenv($name);
    return ($v !== false && $v !== '') ? $v : $default;
};

// ค่าเริ่มต้นสามารถกำหนดที่นี่ หรือผ่าน environment variables:
// - SITE_PROTOCOL (http|https)
// - SITE_HOST (domain หรือ IP)
// - SITE_PORT (ถ้ามี เช่น 8080; ถ้าไม่มีให้เว้นว่าง)
// - BASE_PATH (path หลัก ของโปรเจกต์ เช่น /Dormitory_Management)

define('SITE_PROTOCOL', $env('SITE_PROTOCOL', 'http'));
define('SITE_HOST', $env('SITE_HOST', '192.168.1.106'));
define('SITE_PORT', $env('SITE_PORT', ''));
// เก็บ BASE_PATH โดยให้ขึ้นต้นด้วย slash แต่ไม่มี slash ท้าย
define('BASE_PATH', '/' . ltrim(rtrim($env('BASE_PATH', '/Dormitory_Management')), '/'));

// ฟังก์ชันสร้าง BASE_URL
function getBaseUrl($path = '') {
    $protocol = defined('SITE_PROTOCOL') ? SITE_PROTOCOL : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = defined('SITE_HOST') ? SITE_HOST : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $port = defined('SITE_PORT') ? SITE_PORT : ($_SERVER['SERVER_PORT'] ?? '');
    $basePath = defined('BASE_PATH') ? BASE_PATH : '/Dormitory_Management';

    $url = $protocol . '://' . $host;

    // เพิ่ม port ถ้ามีและไม่ใช่ค่าพื้นฐาน (80 หรือ 443)
    if (!empty($port) && !in_array((int)$port, [80, 443])) {
        $url .= ':' . $port;
    }

    // ต่อ base path (หลีกเลี่ยง double slash)
    if (!empty($basePath) && $basePath !== '/') {
        $url .= '/' . ltrim($basePath, '/');
    }

    // ต่อ path ที่ส่งเข้ามา
    if (!empty($path)) {
        $url .= (strpos($path, '/') === 0) ? $path : '/' . $path;
    }

    return rtrim($url, '/');
}

// ตัวแปร BASE_URL และ API_URL (สำหรับใช้ในไฟล์อื่น)
define('BASE_URL', getBaseUrl());
define('API_URL', getBaseUrl('/api'));

// ฟังก์ชันสร้าง URL สำหรับ endpoint อื่น ๆ
function getApiUrl($endpoint = '') {
    $apiBase = API_URL;
    if (!empty($endpoint)) {
        if ($endpoint[0] !== '/') $endpoint = '/' . $endpoint;
        return $apiBase . $endpoint;
    }
    return $apiBase;
}


// ตัวอย่างฟังก์ชันสร้าง URL สำหรับ Tenant Portal
function getTenantPortalUrl($token = '') {
    $url = getBaseUrl('/Tenant/index.php');
    if (!empty($token)) {
        $url .= '?token=' . urlencode($token);
    }
    return $url;
}






// 