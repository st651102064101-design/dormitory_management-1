<?php
declare(strict_types=1);

if (!function_exists('sendSms')) {
    function sendSms(PDO $pdo, string $phoneNumber, string $message): bool {
        if (empty(trim($message)) || empty(trim($phoneNumber))) {
            return false;
        }

        try {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_token'");
            $token = trim((string)$stmt->fetchColumn());
            
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_sender_name'");
            $sender = trim((string)$stmt->fetchColumn()) ?: 'SMS';

            if (empty($token)) {
                // Return false or mock success if no token is configured
                // For now, return false so the admin knows it failed
                return false;
            }

            // Clean phone number (e.g., Thai mobile format)
            $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
            if (strpos($phoneNumber, '0') === 0 && strlen($phoneNumber) === 10) {
                // Format to +66
                $phoneNumber = '66' . substr($phoneNumber, 1);
            }

            // Example using ThaiBulkSMS / generic SMS API
            // Because API providers vary, this uses a common ThaiBulkSMS structure for v2
            $data = [
                'msisdn'  => [$phoneNumber],
                'message' => $message,
                'sender'  => $sender
            ];

            $ch = curl_init('https://api-v2.thaibulksms.com/sms');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($token . ':')
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200 || $httpCode === 201;
        } catch (Exception $e) {
            error_log("Failed to send SMS: " . $e->getMessage());
            return false;
        }
    }
}
