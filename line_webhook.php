<?php
require_once 'ConnectDB.php';

$pdo = connectDB();

// อ่านค่า Webhook Event
$content = file_get_contents('php://input');
if (empty($content)) {
    http_response_code(200);
    exit;
}

$events = json_decode($content, true);
if (empty($events['events'])) {
    http_response_code(200);
    exit;
}

$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_channel_token'");
$token = trim((string)$stmt->fetchColumn());
if (empty($token)) {
    http_response_code(200);
    exit;
}

foreach ($events['events'] as $event) {
    if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
        continue;
    }

    $replyToken = $event['replyToken'];
    $userId = $event['source']['userId'];
    $text = trim($event['message']['text']);
    
    // ตรวจสอบคำสั่ง "ลงทะเบียน 08xxxxxxxx"
    $replyMessage = "";
    if (preg_match('/^ลงทะเบียน\s+([0-9]{9,10})$/u', $text, $matches)) {
        $phone = $matches[1];
        
        // ค้นหาผู้เช่าด้วยเบอร์โทร
        $stmtCheck = $pdo->prepare("SELECT tnt_id, tnt_name FROM tenant WHERE tnt_phone = ? AND tnt_status = '1' LIMIT 1");
        $stmtCheck->execute([$phone]);
        $tenant = $stmtCheck->fetch();

        if ($tenant) {
            // หนึ่ง LINE ควรผูกกับผู้เช่าเดียว: ล้างการผูกเดิมก่อน
            $stmtClearDuplicate = $pdo->prepare("UPDATE tenant SET line_user_id = NULL WHERE line_user_id = ? AND tnt_id <> ?");
            $stmtClearDuplicate->execute([$userId, $tenant['tnt_id']]);

            // ผูก LINE ID
            $stmtUpdate = $pdo->prepare("UPDATE tenant SET line_user_id = ?, is_weather_alert_enabled = 1 WHERE tnt_id = ?");
            $stmtUpdate->execute([$userId, $tenant['tnt_id']]);
            $replyMessage = "✅ ลงทะเบียนสำเร็จ!\nยินดีต้อนรับคุณ {$tenant['tnt_name']}\nระบบได้ทำการผูกบัญชี LINE นี้กับหอพักเรียบร้อยแล้ว\n\n📌 หากต้องการปิดแจ้งเตือนสภาพอากาศ ให้พิมพ์คำว่า:\nปิดแจ้งเตือนอากาศ";
        } else {
            $replyMessage = "❌ ไม่พบเบอร์โทรศัพท์ {$phone} หรือคุณยังไม่ได้เป็นผู้เช่าปัจจุบัน กรุณาตรวจสอบเบอร์โทรศัพท์อีกครั้ง";
        }
    } elseif ($text === 'ปิดแจ้งเตือนอากาศ' || $text === 'ปิดแจ้งเตือนสภาพอากาศ') {
        $stmtUpdate = $pdo->prepare("UPDATE tenant SET is_weather_alert_enabled = 0 WHERE line_user_id = ?");
        $stmtUpdate->execute([$userId]);
        if ($stmtUpdate->rowCount() > 0) {
            $replyMessage = "🔕 ปิดการแจ้งเตือนสภาพอากาศเรียบร้อยแล้ว\n(คุณสามารถเปิดใหม่ได้โดยพิมพ์: เปิดแจ้งเตือนอากาศ)";
        } else {
            $replyMessage = "คุณยังไม่ได้ลงทะเบียนหอพัก พิมพ์: \nลงทะเบียน [เบอร์โทร]";
        }
    } elseif ($text === 'เปิดแจ้งเตือนอากาศ' || $text === 'เปิดแจ้งเตือนสภาพอากาศ') {
        $stmtUpdate = $pdo->prepare("UPDATE tenant SET is_weather_alert_enabled = 1 WHERE line_user_id = ?");
        $stmtUpdate->execute([$userId]);
        if ($stmtUpdate->rowCount() > 0) {
            $replyMessage = "🔔 เปิดการแจ้งเตือนสภาพอากาศเรียบร้อยแล้ว";
        } else {
            $replyMessage = "คุณยังไม่ได้ลงทะเบียนหอพัก พิมพ์: \nลงทะเบียน [เบอร์โทร]";
        }
    } elseif ($text === 'เช็คสถานะการแจ้งเตือน') {
        $stmtCheck = $pdo->prepare("SELECT is_weather_alert_enabled, tnt_name FROM tenant WHERE line_user_id = ? LIMIT 1");
        $stmtCheck->execute([$userId]);
        $t = $stmtCheck->fetch();
        if ($t) {
            $status = $t['is_weather_alert_enabled'] == 1 ? "🔔 เปิดใช้งาน" : "🔕 ปิดใช้งาน";
            $replyMessage = "สถานะของคุณ {$t['tnt_name']}:\nแจ้งเตือนสภาพอากาศ: $status\n\nสามารถแก้ไขได้โดยพิมพ์คำว่า:\n- ปิดแจ้งเตือนอากาศ\n- เปิดแจ้งเตือนอากาศ";
        }
    }

    if ($replyMessage !== "") {
        $data = [
            'replyToken' => $replyToken,
            'messages' => [['type' => 'text', 'text' => $replyMessage]]
        ];
        $ch = curl_init('https://api.line.me/v2/bot/message/reply');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
http_response_code(200);
