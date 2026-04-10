<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

if (!function_exists('connectDB')) {
    function connectDB(){
        $host = 'localhost';
        $port = '3306';
        $db   = 'dormitory_management_db';
        $user = 'root';
        $pass = ''; 

        $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 30,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $user, $pass, $options);
            
            // Set session wait_timeout and interactive_timeout
            $pdo->exec("SET SESSION wait_timeout = 300");
            $pdo->exec("SET SESSION interactive_timeout = 300");

            // ── Session timeout enforcement (runs once per PHP request) ──────────
            static $sessionChecked = false;
            $currentScript = strtolower((string)basename($_SERVER['SCRIPT_NAME'] ?? ''));
            $skipTimeoutCheck = ($currentScript === 'login.php');
            if (!$sessionChecked
                && !$skipTimeoutCheck
                && session_status() === PHP_SESSION_ACTIVE
                && !empty($_SESSION['admin_username'])
            ) {
                $sessionChecked = true;
                try {
                    $tStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'session_timeout_minutes' LIMIT 1");
                    $tMin  = max(1, (int)($tStmt->fetchColumn() ?: 30));
                } catch (Exception $e) {
                    $tMin = 30;
                }
                $timeoutSec = $tMin * 60;
                // Cache timeout in session so JS countdown can read it
                $_SESSION['_timeout_min'] = $tMin;

                if (!isset($_SESSION['last_activity'])) {
                    // First request after upgrade – initialise
                    $_SESSION['last_activity'] = time();
                } elseif (time() - (int)$_SESSION['last_activity'] >= $timeoutSec) {
                    // ── SESSION EXPIRED ──────────────────────────────────────────
                    // Calculate web-relative redirect path to Login.php
                    $scriptUrl  = isset($_SERVER['SCRIPT_NAME'])
                                  ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])
                                  : '/';
                    $scriptDepth = count(array_filter(explode('/', dirname($scriptUrl)))) - 1;
                    $loginUrl    = str_repeat('../', max(0, $scriptDepth)) . 'Login.php?reason=timeout';
                    
                    // Append current URL to return to after login
                    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
                    if (!empty($currentUri)) {
                        $loginUrl .= '&redirect=' . urlencode($currentUri);
                    }

                    session_unset();
                    session_destroy();

                    // Detect AJAX endpoint (files under /Manage/)
                    $scriptPathLower = strtolower(str_replace('\\', '/', isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''));
                    $isAjaxEndpoint  = strpos($scriptPathLower, '/manage/') !== false;

                    if ($isAjaxEndpoint) {
                        header('Content-Type: application/json');
                        http_response_code(401);
                        echo json_encode([
                            'success' => false,
                            'error'   => 'session_expired',
                            'message' => 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่',
                        ]);
                        exit;
                    }

                    if (!headers_sent()) {
                        header('Location: ' . $loginUrl);
                    } else {
                        echo '<script>window.location.href=' . json_encode($loginUrl) . ';</script>';
                    }
                    exit;
                } else {
                    // Refresh timestamp on every normal request
                    $_SESSION['last_activity'] = time();
                }
            }
            // ─────────────────────────────────────────────────────────────────────

            return $pdo;

        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
            exit;
        }
    } 
} // <--- ต้องมีปีกกาปิดตัวนี้ เพื่อปิด 'if' ที่เปิดไว้ในบรรทัดที่ 5
?>