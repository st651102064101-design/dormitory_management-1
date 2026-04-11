<?php
/**
 * ไฟล์ตั้งค่าหลักของระบบ
 * แก้ไขค่าต่างๆ ที่นี่
 */

// ===========================================
// ตั้งค่า IP / Domain สำหรับ QR Code และ OAuth Callback
// ===========================================
// ใส่ IP Address หรือ Domain ของเซิร์ฟเวอร์
// ตัวอย่าง: 'yourdomain.com' (ไม่ต้องใส่พอร์ตถ้าใช้ 80/443)
// ตัวอย่าง: 'project.3bbddns.com' 
// ถ้าเว้นว่าง '' จะใช้ค่าอัตโนมัติจาก $_SERVER['HTTP_HOST']
// ⚠️ สำคัญ: ต้องตรงกับที่ลงทะเบียนใน LINE/Google Developers Console พอดี!

define('SITE_HOST', 'project.3bbddns.com');

// ===========================================
// ตั้งค่า Protocol (http หรือ https)
// ===========================================
// ใส่ 'https' หรือ 'http' เพื่อบังคับใช้ค่าที่กำหนด
// ใส่ '' เพื่อให้ระบบตรวจจากการส่งข้อมูลอัตโนมัติ (ตรวจ HTTPS, SERVER_PORT, X_FORWARDED_PROTO)
// ⚠️ ใช้ HTTPS เพราะ XAMPP มี SSL certificate สำหรับ project.3bbddns.com บน port 443

define('SITE_PROTOCOL', 'https');

// ===========================================
// ฟังก์ชันสร้าง Base URL
// ===========================================
function getBaseUrl($path = '') {
    $protocol = SITE_PROTOCOL;

    // Auto-detect protocol when SITE_PROTOCOL is empty
    if (empty($protocol)) {
        $isHttps = false;
        
        // ตรวจสอบ #1: จากตัวแปร HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $isHttps = true;
        }
        // ตรวจสอบ #2: จากพอร์ต (443 = HTTPS)
        elseif (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
            $isHttps = true;
        }
        // ตรวจสอบ #3: จาก X_FORWARDED_PROTO header (สำคัญ: Reverse Proxy/Load Balancer)
        elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $isHttps = true;
        }
        // ตรวจสอบ #4: จาก HTTP_CF_VISITOR (Cloudflare)
        elseif (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) {
            $isHttps = true;
        }
        
        $protocol = $isHttps ? 'https' : 'http';
    }
    
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
    
    // ระบุชื่อโปรเจคจากโฟลเดอร์ถ้าเป็นไปได้ (ทำให้ไม่ต้อง hardcode ชื่อโฟลเดอร์)
    $projectFolder = basename(__DIR__);
    
    // ทำให้แน่ใจว่าพาธเริ่มด้วย / ถ้ามีการส่งเข้ามา
    if ($path !== '' && strpos($path, '/') !== 0) {
        $path = '/' . $path;
    }
    
    return $protocol . '://' . $host . '/' . $projectFolder . $path;
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