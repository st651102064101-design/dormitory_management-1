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
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('openweathermap_api_key', 'openweathermap_city', 'site_name', 'last_weather_alert_type', 'last_weather_alert_date')");
    $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $apiKey = $dbSettings['openweathermap_api_key'] ?? '';
    $city = $dbSettings['openweathermap_city'] ?? '';
    $siteName = $dbSettings['site_name'] ?? 'หอพัก';
    
    $lastAlertType = $dbSettings['last_weather_alert_type'] ?? '';
    $lastAlertDate = $dbSettings['last_weather_alert_date'] ?? '';

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
    $icon = strtolower($weatherData['weather'][0]['icon'] ?? '');
    $isNight = strpos($icon, 'n') !== false; // มีตัว n หมายถึงกลางคืน (night)
    
    // ตรวจสอบเงื่อนไข แดดแรง หรือ มีฝน
    $isRainy = in_array($mainWeather, ['rain', 'drizzle', 'thunderstorm']);
    $isHotAndSunny = !$isNight && ($temp >= 35 || in_array($mainWeather, ['clear'])); // แดดแรงต้องไม่ใช่เวลากลางคืน
    
    $isTest = isset($_POST['test']) && $_POST['test'] === '1' || isset($_GET['test']) && $_GET['test'] === '1';

    $currentDate = date('Y-m-d');
    $currentType = '';
    $lineMsg = "";
    
    if ($isRainy) {
        $currentType = 'rain';
        $lineMsg = "🌧 แจ้งเตือนสภาพอากาศจาก {$siteName}\n";
        $lineMsg .= "ขณะนี้มีสภาพอากาศ: {$desc} (อุณหภูมิ {$temp}°C)\n";
        $lineMsg .= "มีโอกาสเกิดฝนตก กรุณาเก็บผ้าที่ตากไว้และตาสอดส่องเปิดหน้าต่างห้องพักเพื่อป้องกันละอองฝนด้วยครับ/ค่ะ";
    } elseif ($isHotAndSunny) {
        $currentType = 'sunny';
        $lineMsg = "☀️ แจ้งเตือนสภาพอากาศจาก {$siteName}\n";
        $lineMsg .= "สภาพอากาศ: {$desc} (อุณหภูมิ {$temp}°C)\n";
        $lineMsg .= "แดดแรง สภาพอากาศโปร่ง เหมาะกับการซักผ้าตากแดดครับ/ค่ะ";
    } else {
        if ($isTest) {
            // กรณีทดสอบแต่สภาพอากาศปกติ
            $currentType = 'normal';
            $lineMsg = "☁️ แจ้งเตือนสภาพอากาศจาก {$siteName}\n";
            $lineMsg .= "สภาพอากาศ: {$desc} (อุณหภูมิ {$temp}°C)\n";
            $lineMsg .= "ระบบรายงานอากาศทำงานปกติครับ/ค่ะ" . ($isNight ? " (เวลากลางคืน)" : "");
        } else {
            echo json_encode(['success' => true, 'message' => "ขณะนี้สภาพอากาศปกติ ($desc {$temp}°C" . ($isNight?", เวลากลางคืน":"") .") ไม่เข้าเงื่อนไขที่จะส่ง LINE"]);
            exit;
        }
    }

    // ระบบ Throttling: ถ้าเป็นการรันอัตโนมัติ (ไม่ใช้แบบกด Test) จะเช็คว่าส่งของวันนี้ไปหรือยัง
    if (!$isTest) {
        // เงื่อนไข: ถ้าส่งแบบเดิม ในวันเดียวกันไปแล้ว จะไม่ส่งซ้ำอีกเพื่อป้องกันสแปมผู้เช่า (เช่น ห้ามส่งแดดแรง 10 รอบในวันเดียวตลอดบ่าย)
        if ($lastAlertType === $currentType && $lastAlertDate === $currentDate) {
            echo json_encode(['success' => true, 'message' => "เข้าเงื่อนไข $currentType แต่ระบบได้ส่งแจ้งเตือนนี้ไปแล้วในวันนี้ ($currentDate) ข้ามการส่งเพื่อป้องกันสแปม"]);
            exit;
        }
    }

    if ($isTest) {
        $lineMsg = "[TEST]\n" . $lineMsg;
    }

    // 4. ส่งข้อความผ่าน LINE แบบ Multicast (เฉพาะผู้ที่เปิดแจ้งเตือน)
    if (!function_exists('sendLineMulticast')) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบฟังก์ชันส่ง LINE Multicast']);
        exit;
    }

    $stmtUsers = $pdo->query("SELECT line_user_id FROM tenant WHERE tnt_status = '1' AND line_user_id IS NOT NULL AND line_user_id != '' AND is_weather_alert_enabled = 1");
    $userIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userIds)) {
        echo json_encode(['success' => true, 'message' => "เข้าเงื่อนไขสภาพอากาศแต่ไม่มีรายชื่อผู้เช่าที่ลงทะเบียนรับการแจ้งเตือนไว้ ข้ามการส่ง LINE"]);
        exit;
    }

    $sendResult = sendLineMulticast($pdo, $userIds, $lineMsg);
    if ($sendResult) {
        // อัปเดตสถานะการส่งล่าสุดลง DB (เพื่อที่การรัน cron รอบต่อไปจะได้ไม่ส่งซ้ำรัวๆ)
        if (!$isTest && in_array($currentType, ['rain', 'sunny'])) {
            $stmtUpdate = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmtUpdate->execute(['last_weather_alert_date', $currentDate, $currentDate]);
            $stmtUpdate->execute(['last_weather_alert_type', $currentType, $currentType]);
        }
        
        echo json_encode(['success' => true, 'message' => "ส่งข้อความทาง LINE สำเร็จ ($desc {$temp}°C)"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ส่งข้อความ LINE ล้มเหลว โปรดตรวจสอบการตั้งค่า LINE OA']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
