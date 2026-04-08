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

            $data = [
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $message
                    ]
                ]
            ];

            $ch = curl_init('https://api.line.me/v2/bot/message/broadcast');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (Exception $e) {
            error_log("Failed to send LINE Broadcast: " . $e->getMessage());
            return false;
        }
    }
}