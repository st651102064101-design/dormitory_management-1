<?php
/**
 * Debug OAuth Redirect URI
 * ตรวจสอบว่า redirect_uri ที่ระบบสร้างนั้นตรงกับที่ลงทะเบียนใน LINE/Google Developers หรือไม่
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

// ตรวจสอบ $_SERVER ต่างๆ
$debugInfo = [
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'NOT SET',
    'HTTPS' => $_SERVER['HTTPS'] ?? 'NOT SET',
    'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'NOT SET',
    'HTTP_CF_VISITOR' => $_SERVER['HTTP_CF_VISITOR'] ?? 'NOT SET',
    'REQUEST_SCHEME' => $_SERVER['REQUEST_SCHEME'] ?? 'NOT SET',
];

$lineRedirectUri = getBaseUrl('/line_callback.php');
$googleRedirectUri = getBaseUrl('/google_callback.php');
$configSiteHost = SITE_HOST;
$configSiteProtocol = SITE_PROTOCOL;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug OAuth Redirect URI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        h1 {
            color: white;
            margin-bottom: 24px;
            font-size: 2rem;
        }
        h2 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 16px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 8px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            width: 30%;
        }
        .info-value {
            color: #333;
            word-break: break-all;
            width: 70%;
            text-align: right;
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 6px;
        }
        .redirect-uri-box {
            background: #f8f9fa;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            color: #333;
            font-size: 0.95rem;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 12px 0;
            border-radius: 6px;
            color: #856404;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 12px;
            margin: 12px 0;
            border-radius: 6px;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 12px;
            margin: 12px 0;
            border-radius: 6px;
            color: #721c24;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .instruction {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin: 12px 0;
            border-radius: 6px;
            color: #0c5aa0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug OAuth Configuration</h1>
        
        <div class="card">
            <h2>📋 Configuration Settings</h2>
            <div class="info-row">
                <div class="info-label">SITE_HOST:</div>
                <div class="info-value"><?php echo htmlspecialchars($configSiteHost ?: '(empty - using auto-detect)'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">SITE_PROTOCOL:</div>
                <div class="info-value"><?php echo htmlspecialchars($configSiteProtocol ?: '(empty - using auto-detect)'); ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2>🌐 Server Information</h2>
            <?php foreach ($debugInfo as $key => $value): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars($key); ?>:</div>
                    <div class="info-value"><?php echo htmlspecialchars($value); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card">
            <h2>🔐 LINE Login Callback URI</h2>
            <div class="redirect-uri-box"><?php echo htmlspecialchars($lineRedirectUri); ?></div>
            
            <div class="instruction">
                <strong>📌 คำแนะนำ:</strong><br>
                ✅ คัดลอก URL ข้างบนไปลงทะเบียนใน <strong>LINE Developers Console</strong><br>
                📍 ไปที่: <strong>LINE Developers > Your Channel > OAuth Settings > Redirect URI</strong><br>
                ⚠️ URL ต้องตรงทุกจุด (protocol, domain, port, path)
            </div>
            
            <?php if (strpos($lineRedirectUri, 'http://') === 0 && strpos($lineRedirectUri, 'localhost') === false && strpos($lineRedirectUri, '127.0.0.1') === false): ?>
                <div class="warning">
                    ⚠️ <strong>ระวัง:</strong> ใช้ HTTP (ไม่ใช่ HTTPS)<br>
                    ถ้าเซิร์ฟเวอร์ของคุณเป็น HTTPS ให้ตั้งค่า <code>SITE_PROTOCOL = 'https'</code> ใน config.php
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>🔓 Google OAuth Callback URI</h2>
            <div class="redirect-uri-box"><?php echo htmlspecialchars($googleRedirectUri); ?></div>
            
            <div class="instruction">
                <strong>📌 คำแนะนำ:</strong><br>
                ✅ คัดลอก URL ข้างบนไปลงทะเบียนใน <strong>Google Cloud Console</strong><br>
                📍 ไปที่: <strong>APIs & Services > Credentials > OAuth 2.0 Client IDs > Authorized redirect URIs</strong><br>
                ⚠️ URL ต้องตรงทุกจุด
            </div>
        </div>
        
        <div class="card">
            <h2>🛠️ วิธีแก้ไข</h2>
            
            <h3 style="margin-top: 16px; color: #333;">ถ้าคุณใช้ที่อยู่ตามตัวอย่าง: <code>https://project.3bbddns.com:36140</code></h3>
            
            <div class="instruction">
                <strong>ขั้นที่ 1:</strong> แก้ไข <code>config.php</code> เพิ่ม:<br>
                <code style="display: block; padding: 8px; margin-top: 8px; background: white;">define('SITE_HOST', 'project.3bbddns.com:36140');</code>
                <code style="display: block; padding: 8px; margin-top: 4px; background: white;">define('SITE_PROTOCOL', 'https');</code>
            </div>
            
            <div class="instruction">
                <strong>ขั้นที่ 2:</strong> รีโหลดหน้านี้เพื่อดูว่า redirect_uri ถูกต้องไหม
            </div>
            
            <div class="instruction">
                <strong>ขั้นที่ 3:</strong> คัดลอก redirect_uri ที่ถูกต้องไปลงทะเบียนใน:<br>
                • LINE Developers Console<br>
                • Google Cloud Console
            </div>
        </div>
    </div>
</body>
</html>
