<?php
declare(strict_types=1);

if (!function_exists('ensureRoomPriceColumn')) {
    function ensureRoomPriceColumn(PDO $pdo): void
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM room LIKE 'room_price'");
            $hasColumn = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$hasColumn) {
                $pdo->exec("ALTER TABLE room ADD COLUMN room_price INT DEFAULT NULL COMMENT 'Room-specific price override' AFTER type_id");
            }
        } catch (Exception $e) {
            error_log('ensureRoomPriceColumn failed: ' . $e->getMessage());
        }
    }
}
