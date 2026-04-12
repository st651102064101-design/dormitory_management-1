<?php
declare(strict_types=1);

if (!function_exists('getLineChannelToken')) {
    function getLineChannelToken(PDO $pdo): string {
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_channel_token'");
        $token = $stmt->fetchColumn();
        $token = is_string($token) ? trim($token) : '';

        if (empty($token) || strlen($token) < 30 || preg_match('/^\d+$/', $token) === 1) {
            return '';
        }

        return $token;
    }
}

if (!function_exists('sendLinePushToUserId')) {
    function sendLinePushToUserId(PDO $pdo, string $lineUserId, string $message): bool {
        $lineUserId = trim($lineUserId);
        if ($lineUserId === '' || empty(trim($message))) {
            return false;
        }

        try {
            $token = getLineChannelToken($pdo);
            if ($token === '') {
                return false;
            }

            $data = [
                'to' => $lineUserId,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $message,
                    ],
                ],
            ];

            $ch = curl_init('https://api.line.me/v2/bot/message/push');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ]);
            $result = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log('LINE push failed [' . $httpCode . ']: ' . (string)$result);
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log('Failed to send LINE push: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sendLineToTenant')) {
    function sendLineToTenant(PDO $pdo, string $tenantId, string $message): bool {
        if ($tenantId === '' || empty(trim($message))) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('SELECT line_user_id FROM tenant WHERE tnt_id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $lineUserId = (string)($stmt->fetchColumn() ?: '');
            return sendLinePushToUserId($pdo, $lineUserId, $message);
        } catch (Exception $e) {
            error_log('Failed to send LINE to tenant: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sendLineToContract')) {
    function sendLineToContract(PDO $pdo, int $contractId, string $message): bool {
        if ($contractId <= 0 || empty(trim($message))) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('SELECT t.line_user_id FROM contract c JOIN tenant t ON t.tnt_id = c.tnt_id WHERE c.ctr_id = ? LIMIT 1');
            $stmt->execute([$contractId]);
            $lineUserId = (string)($stmt->fetchColumn() ?: '');
            return sendLinePushToUserId($pdo, $lineUserId, $message);
        } catch (Exception $e) {
            error_log('Failed to send LINE to contract: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sendLineToExpense')) {
    function sendLineToExpense(PDO $pdo, int $expenseId, string $message): bool {
        if ($expenseId <= 0 || empty(trim($message))) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(' 
                SELECT t.line_user_id
                FROM expense e
                JOIN contract c ON c.ctr_id = e.ctr_id
                JOIN tenant t ON t.tnt_id = c.tnt_id
                WHERE e.exp_id = ?
                LIMIT 1
            ');
            $stmt->execute([$expenseId]);
            $lineUserId = (string)($stmt->fetchColumn() ?: '');
            return sendLinePushToUserId($pdo, $lineUserId, $message);
        } catch (Exception $e) {
            error_log('Failed to send LINE to expense: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sendLineToRepair')) {
    function sendLineToRepair(PDO $pdo, int $repairId, string $message): bool {
        if ($repairId <= 0 || empty(trim($message))) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(' 
                SELECT t.line_user_id
                FROM repair rep
                JOIN contract c ON c.ctr_id = rep.ctr_id
                JOIN tenant t ON t.tnt_id = c.tnt_id
                WHERE rep.repair_id = ?
                LIMIT 1
            ');
            $stmt->execute([$repairId]);
            $lineUserId = (string)($stmt->fetchColumn() ?: '');
            return sendLinePushToUserId($pdo, $lineUserId, $message);
        } catch (Exception $e) {
            error_log('Failed to send LINE to repair: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sendLineBroadcast')) {
    function sendLineBroadcast(PDO $pdo, string $message): bool {
        if (empty(trim($message))) {
            return false;
        }

        try {
            $token = getLineChannelToken($pdo);
            if ($token === '') {
                return false;
            }

            // แทนที่จะใช้ API Broadcast ของ LINE ซึ่งจะส่งหาทุกคนที่แอดบอท (รวมถึงคนที่ยังไม่ลงทะเบียนเบอร์)
            // เราจะดึงเฉพาะ line_user_id ที่ได้ทำการลงทะเบียนผูกเบอร์กับระบบแล้วเท่านั้น แล้วส่งผ่าน Multicast
            $usersStmt = $pdo->query("SELECT DISTINCT line_user_id FROM tenant WHERE line_user_id IS NOT NULL AND line_user_id != ''");
            $userIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($userIds)) {
                return true; // ไม่มีใครให้ส่ง ก็ถือว่าสำเร็จ
            }

            return sendLineMulticast($pdo, $userIds, $message);
        } catch (Exception $e) {
            error_log("Failed to send LINE Broadcast (Multicast Fallback): " . $e->getMessage());
            return false;
        }
    }
}
if (!function_exists('sendLineMulticast')) {
    function sendLineMulticast(PDO $pdo, array $userIds, string $message): bool {
        if (empty(trim($message)) || empty($userIds)) {
            return false;
        }

        try {
            $token = getLineChannelToken($pdo);
            if ($token === '') {
                return false;
            }

            $userIds = array_values(array_unique(array_filter(array_map(static function ($id) {
                return trim((string)$id);
            }, $userIds), static function ($id) {
                return $id !== '';
            })));

            if (empty($userIds)) {
                return false;
            }

            // LINE Multicast allows a maximum of 500 user IDs per request
            $chunks = array_chunk($userIds, 500);
            $success = true;

            foreach ($chunks as $chunk) {
                $data = [
                    'to' => $chunk,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message
                        ]
                    ]
                ];

                $ch = curl_init('https://api.line.me/v2/bot/message/multicast');
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

                if ($httpCode !== 200) {
                    error_log('LINE multicast failed [' . (string)$httpCode . ']: ' . (string)$result);
                    $success = false;
                }
            }

            return $success;
        } catch (Exception $e) {
            error_log("Failed to send LINE Multicast: " . $e->getMessage());
            return false;
        }
    }
}
