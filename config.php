<?php
/**
 * ไฟล์ตั้งค่าหลักของระบบ
 * แก้ไขค่าต่างๆ ที่นี่
 */

// ===========================================
// ตั้งค่า IP / Domain สำหรับ QR Code
// ===========================================
// ใส่ IP Address หรือ Domain ของเซิร์ฟเวอร์
// ตัวอย่าง: '192.168.1.106' หรือ 'yourdomain.com'
// ถ้าเว้นว่าง '' จะใช้ค่าอัตโนมัติ

define('SITE_HOST', '192.168.1.106');

// ===========================================
// ตั้งค่า Protocol (http หรือ https)
// ===========================================
define('SITE_PROTOCOL', 'http');

// ===========================================
// ฟังก์ชันสร้าง Base URL
// ===========================================
function getBaseUrl($path = '') {
    $protocol = SITE_PROTOCOL;
    $host = SITE_HOST;
    
    // ถ้าไม่ได้กำหนด host ให้ใช้ค่าอัตโนมัติ
    if (empty($host)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // ถ้าเป็น localhost ลองหา IP
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $localIP = @shell_exec("ipconfig getifaddr en0 2>/dev/null") ?: @shell_exec("ipconfig getifaddr en1 2>/dev/null");
            $localIP = trim($localIP ?? '');
            if (!empty($localIP)) {
                $host = $localIP;
            }
        }
    }
    
    return $protocol . '://' . $host . '/Dormitory_Management' . $path;
}

/**
 * สร้าง URL สำหรับ Tenant Portal
 */
function getTenantPortalUrl($token = '') {
    $url = getBaseUrl('/Tenant/index.php');
    if (!empty($token)) {
        $url .= '?token=' . urlencode($token);
    }
    return $url;
}






// 