<?php
/**
 * Link Google Account
 * เชื่อมบัญชี Google กับบัญชี Admin ที่ล็อกอินอยู่
 * ใช้ google_callback.php เดิมเพื่อไม่ต้องเพิ่ม redirect URI ใน Google Cloud Console
 */
declare(strict_types=1);

// DEBUG
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/ConnectDB.php';

function buildGoogleRedirectUri(string $redirectUri): string {
    $redirectUri = trim($redirectUri);
    if ($redirectUri === '') {
        $redirectUri = '/dormitory_management/google_callback.php';
    }

    if (preg_match('#^https?://#i', $redirectUri) === 1) {
        return $redirectUri;
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));

    if ($serverName === '' || $serverName === '_') {
        $httpHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $serverName = explode(':', $httpHost)[0];
    }

    if ($serverName === '') {
        $serverName = 'localhost';
    }

    $serverPort = (int)($_SERVER['SERVER_PORT'] ?? 0);
    $portPart = '';
    if (($protocol === 'http' && $serverPort > 0 && $serverPort !== 80) || ($protocol === 'https' && $serverPort > 0 && $serverPort !== 443)) {
        $portPart = ':' . $serverPort;
    }

    if ($redirectUri[0] !== '/') {
        $redirectUri = '/' . $redirectUri;
    }

    return $protocol . '://' . $serverName . $portPart . $redirectUri;
}

// DEBUG: Test database connection
try {
    $testPdo = connectDB();
    // echo "DB Connected OK<br>";
} catch (Exception $e) {
    die("DB Connection Error: " . $e->getMessage());
}

// ตรวจสอบการ login
if (empty($_SESSION['admin_id']) && empty($_SESSION['admin_username'])) {
    header('Location: Login.php?google_error=' . urlencode('กรุณาล็อกอินก่อน'));
    exit;
}

// ดึง admin_id ถ้ายังไม่มี
if (empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username'])) {
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare("SELECT admin_id FROM admin WHERE admin_username = ?");
        $stmt->execute([$_SESSION['admin_username']]);
        $admin = $stmt->fetch();
        if ($admin) {
            $_SESSION['admin_id'] = $admin['admin_id'];
        }
    } catch (PDOException $e) {
        header('Location: Reports/dashboard.php?error=' . urlencode('เกิดข้อผิดพลาด'));
        exit;
    }
}

// ดึงค่าตั้งค่า Google OAuth จากฐานข้อมูล
try {
    $pdo = connectDB();
    
    // ✅ Ensure google_oauth_state table exists
    try {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS google_oauth_state (
                id INT AUTO_INCREMENT PRIMARY KEY,
                state VARCHAR(255) NOT NULL UNIQUE,
                admin_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_state (state),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    } catch (Exception $e) {
        error_log("Warning: Could not ensure google_oauth_state table exists: " . $e->getMessage());
    }
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('google_client_id', 'google_redirect_uri')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $clientId = $settings['google_client_id'] ?? '';
    $redirectUri = $settings['google_redirect_uri'] ?? '';
    
    if (empty($clientId)) {
        header('Location: Reports/dashboard.php?google_error=' . urlencode('Google OAuth ยังไม่ได้ตั้งค่า Client ID'));
        exit;
    }
    
    // สร้าง redirect URI แบบคงที่ (หลีกเลี่ยงพอร์ตสุ่มจาก HTTP_HOST)
    $fullRedirectUri = buildGoogleRedirectUri($redirectUri);
    $_SESSION['google_redirect_uri'] = $fullRedirectUri;
    
    // สร้าง state token เพื่อป้องกัน CSRF
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_state'] = $state;  // ใช้ชื่อเดียวกับ google_login.php
    
    // ✅ บันทึก state token ลงในฐานข้อมูลด้วย (สำหรับ popup scenario)
    try {
        $storeStateStmt = $pdo->prepare('
            INSERT INTO google_oauth_state (state, admin_id, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE created_at = NOW()
        ');
        $storeStateStmt->execute([$state, $_SESSION['admin_id']]);
    } catch (Exception $e) {
        error_log("Warning: Could not store state token in database: " . $e->getMessage());
    }
    
    // บันทึกว่านี่คือการ link account
    $_SESSION['google_link_mode'] = true;
    $_SESSION['google_link_admin_id'] = $_SESSION['admin_id'];
    $action = $_GET['action'] ?? 'link';
    $_SESSION['google_link_action'] = $action;
    
    // สร้าง Google OAuth URL
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $fullRedirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
    
    // Redirect ไปยัง Google
    header('Location: ' . $authUrl);
    exit;
    
} catch (PDOException $e) {
    header('Location: Reports/dashboard.php?google_error=' . urlencode('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage()));
    exit;
}
