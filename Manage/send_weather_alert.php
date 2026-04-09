<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../LineHelper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = connectDB();

    // 1. ดึงข้อมูล Settings API
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('openweathermap_api_key', 'openweathermap_city', 'site_name')");
    $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $apiKey = $dbSettings['openweathermap_api_key'] ?? '';
    $city = $dbSettings['openweathermap_city'] ?? '';
    $siteName = $dbSettings['site_name'] ?? 'หอพัก';

    if (empty($apiKey) || empty($city)) {
        echo json_encode(['success' => false, 'message' => 'กรุณาตั้งค่า API Key และ City สำหรับ OpenWeatherMap ก่อนใช้งาน']);
        exit;
    }

    // 2. ดึงข้อมูลจาก OpenWeatherMap API
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=" . urlencode($apiKey) . "&units=metric&lang=th";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        $errorMsg = 'ไม่สามารถดึงข้อมูลสภาพอากาศได้ HTTP: ' . $httpCode;
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['message'])) $errorMsg .= ' - ' . $data['message'];
        }
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }

    $weatherData = json_decode($response, true);
    if (!$weatherData || !isset($weatherData['weather'][0])) {
        echo json_encode(['success' => false, 'message' => 'รูปแบบข้อมูลสภาพอากาศจากเซิร์ฟเวอร์ไม่ถูกต้อง']);
        exit;
    }

    $temp = round($weatherData['main']['temp'] ?? 0);
    $mainWeather = strtolower($weatherData['weather'][0]['main'] ?? '');
    $desc = $weatherData['weather'][0]['description'] ?? '';
    
    // ตรวจสอบเงื่อนไข แดดแรง หรือ มีฝน
    $isRainy = in_array($mainWeather, ['rain', 'drizzle', 'thunderstorm']);
    $isHotAndSunny = ($temp >= 35 || in_array($mainWeather, ['clear']));
    $isTest = isset($_POST['test']) && $_POST['test'] === '1';

    $lineMsg = "";
    
    if ($isRainy) {
        $lineMsg = "🌧 แจ้งเตือนสภาพอากาศจาก {$siteName}\n";
        $lineMsg .= "ขณะนี้มีสภาพอากาศ: {$desc} (อุณหภูมิ {$temp}°C)\n";
        $lineMsg .= "มีโอกาสเกิดฝนตก กรุณาเก็บผ้าที่ตากไว้และปิดหน้าต่างห้องพักเพื่อป้องกันละอองฝนด้วยครับ/ค่ะ";
    } elseif ($isHotAndSunny) {
        $lineMsg = "☀ แจ้งเตือนสภาพอากาศจาก {$siteName}\n";
        $lineMsg .= "สภาพอากาศ: {$desc} (อุณหภูมิ {$temp}°C)\n";
        $lineMsg .= "แดดแรง เหมาะกับการซักผ้าตากแดดครับ/ค่ะ";
    } else {
        if ($isTest) {
            // กรณีทดสอบแต่สภาพอากาศปกติ
            $lineMsg = "☁ แจ้งเตือนสภาพอากาศจาก {$siteName}\n";
            $lineMsg .= "สภาพอากาศ: {$desc} (อุณหภูมิ {$temp}°C)\n";
            $lineMsg .= "ระบบรายงานอากาศทำงานปกติครับ/ค่ะ";
        } else {
            echo json_encode(['success' => true, 'message' => "ขณะนี้สภาพอากาศปกติ ($desc {$temp}°C) ไม่เข้าเงื่อนไขที่จะส่ง LINE"]);
            exit;
        }
    }

    if ($isTest) {
        $lineMsg = "[TEST]\n" . $lineMsg;
    }

    // 4. ส่งข้อความผ่าน LINE Broadcast
    if (!function_exists('sendLineBroadcast')) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบฟังก์ชันส่ง LINE']);
        exit;
    }

    $sendResult = sendLineBroadcast($pdo, $lineMsg);
    if ($sendResult) {
        echo json_encode(['success' => true, 'message' => "ส่งข้อความทาง LINE สำเร็จ ($desc {$temp}°C)"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ส่งข้อความ LINE ล้มเหลว โปรดตรวจสอบการตั้งค่า LINE OA']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
