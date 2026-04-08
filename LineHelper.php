<?php
declare(strict_types=1);

if (!function_exists('sendLineBroadcast')) {
    function sendLineBroadcast(PDO $pdo, string $message): bool {
        if (empty(trim($message))) {
            return false;
        }

        try {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_channel_token'");
            $token = $stmt->fetchColumn();
            $token = is_string($token) ? trim($token) : '';

            if (empty($token) || strlen($token) < 30 || preg_match('/^\d+$/', $token) === 1) {
                return false;
            }

            // ปรับจาก LINE Messaging API เป็น LINE Notify สำหรับแจ้งเตือนแอดมินหรือกลุ่ม
            // รองรับทั้ง Token ของ LINE Notify โดยตรงในช่อง line_channel_token
            $ch = curl_init('https://notify-api.line.me/api/notify');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['message' => $message]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Bearer ' . $token
            ]);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("LINE Notify Error (HTTP $httpCode): " . print_r($result, true) . " cURL Error: $error");
            }

            return $httpCode === 200;
        } catch (Exception $e) {
            error_log("Failed to send LINE Broadcast: " . $e->getMessage());
            return false;
        }
    }
}