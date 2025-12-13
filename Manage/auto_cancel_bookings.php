<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// Grace period in days after planned check-in (fallback = 3 days)
$graceDays = 3;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'booking_grace_days' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val !== false && is_numeric($val)) {
        $graceDays = max(0, (int)$val);
    }
} catch (PDOException $e) {}

// Define status codes (per user's schema)
$BKG_STATUS_CONFIRMED = '1'; // booking: จองแล้ว
$BKG_STATUS_CANCELLED = '0'; // booking: ยกเลิก
$BKG_STATUS_CHECKEDIN = '2'; // booking: เข้าพักแล้ว

$TNT_STATUS_MOVED_OUT = '0'; // tenant: ย้ายออก
$TNT_STATUS_STAYING   = '1'; // tenant: พักอยู่
$TNT_STATUS_WAITING   = '2'; // tenant: รอการเข้าพัก
$TNT_STATUS_BOOKED    = '3'; // tenant: จองห้อง
$TNT_STATUS_CANCELLED = '4'; // tenant: ยกเลิกจองห้อง

// Find overdue bookings where tenant did not check in by grace period
$overdue = [];
try {
        $sql = "
                SELECT b.room_id, b.tnt_id, b.bkg_checkin_date
                FROM booking b
                WHERE b.bkg_status = :confirmed
                    AND b.bkg_checkin_date IS NOT NULL
                    AND NOW() > DATE_ADD(b.bkg_checkin_date, INTERVAL :grace DAY)
                    AND NOT EXISTS (
                         SELECT 1 FROM booking b2
                         WHERE b2.room_id = b.room_id AND b2.tnt_id = b.tnt_id AND b2.bkg_status = :checkedin
                    )
        ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['confirmed' => $BKG_STATUS_CONFIRMED, 'grace' => $graceDays, 'checkedin' => $BKG_STATUS_CHECKEDIN]);
    $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Query error: " . $e->getMessage();
    exit(1);
}

if (empty($overdue)) {
    echo "No overdue bookings found.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    foreach ($overdue as $row) {
        $roomId = (int)$row['room_id'];
        $tenantId = $row['tnt_id'];

        // 1) Update booking to Cancelled (by keys)
        $updB = $pdo->prepare("UPDATE booking SET bkg_status = :cancelled WHERE room_id = :rid AND tnt_id = :tid AND bkg_status = :confirmed");
        $updB->execute(['cancelled' => $BKG_STATUS_CANCELLED, 'rid' => $roomId, 'tid' => $tenantId, 'confirmed' => $BKG_STATUS_CONFIRMED]);

        // 2) Update tenant to Cancelled (ยกเลิกจองห้อง)
        $updT = $pdo->prepare("UPDATE tenant SET tnt_status = :cancelled WHERE tnt_id = :tid");
        $updT->execute(['cancelled' => $TNT_STATUS_CANCELLED, 'tid' => $tenantId]);

        // 3) Free the room (set room_status = '0' ว่าง)
        $updR = $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = :rid");
        $updR->execute(['rid' => $roomId]);

        // 4) Deposit forfeiture note (optional): record in a simple log table if exists
        // Optional: add log to a table if exists (skipped by default)
    }
    $pdo->commit();
    echo "Auto-cancelled " . count($overdue) . " bookings.\n";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Update error: " . $e->getMessage();
    exit(1);
}

?>
