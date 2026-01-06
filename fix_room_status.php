<?php
/**
 * Fix Room Status - Update room status to vacant if no active booking or contract
 * This script checks all rooms and updates their status based on actual occupancy
 */

declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

echo "🔍 เริ่มตรวจสอบสถานะห้องพัก...\n\n";

try {
    // Get all rooms
    $stmt = $pdo->query("SELECT room_id, room_number, room_status FROM room ORDER BY CAST(room_number AS UNSIGNED)");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalRooms = count($rooms);
    $updatedCount = 0;
    $alreadyCorrect = 0;
    
    echo "📊 ห้องทั้งหมด: {$totalRooms} ห้อง\n\n";
    
    foreach ($rooms as $room) {
        $roomId = $room['room_id'];
        $roomNumber = $room['room_number'];
        $currentStatus = $room['room_status'];
        
        // Check for active bookings (status 1 = pending, 2 = confirmed)
        $bookingStmt = $pdo->prepare("
            SELECT COUNT(*) as booking_count 
            FROM booking 
            WHERE room_id = ? AND bkg_status IN ('1', '2')
        ");
        $bookingStmt->execute([$roomId]);
        $activeBookings = (int)$bookingStmt->fetchColumn();
        
        // Check for active contracts (status 0 = active)
        $contractStmt = $pdo->prepare("
            SELECT COUNT(*) as contract_count 
            FROM contract 
            WHERE room_id = ? AND ctr_status = '0'
        ");
        $contractStmt->execute([$roomId]);
        $activeContracts = (int)$contractStmt->fetchColumn();
        
        // Determine correct status
        $shouldBeVacant = ($activeBookings === 0 && $activeContracts === 0);
        $correctStatus = $shouldBeVacant ? '0' : '1';
        
        // Update if needed
        if ($currentStatus !== $correctStatus) {
            $updateStmt = $pdo->prepare("UPDATE room SET room_status = ? WHERE room_id = ?");
            $updateStmt->execute([$correctStatus, $roomId]);
            
            $statusText = $correctStatus === '0' ? '✅ ว่าง' : '🔴 ไม่ว่าง';
            $oldStatusText = $currentStatus === '0' ? 'ว่าง' : 'ไม่ว่าง';
            
            echo "🔄 ห้อง {$roomNumber}: {$oldStatusText} → {$statusText}\n";
            echo "   └─ การจอง: {$activeBookings}, สัญญา: {$activeContracts}\n";
            $updatedCount++;
        } else {
            $statusText = $correctStatus === '0' ? 'ว่าง' : 'ไม่ว่าง';
            $alreadyCorrect++;
            // Uncomment to see all rooms
            // echo "✓ ห้อง {$roomNumber}: {$statusText} (ถูกต้องอยู่แล้ว)\n";
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📈 สรุปผลการดำเนินการ:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ ห้องที่ถูกต้องอยู่แล้ว: {$alreadyCorrect} ห้อง\n";
    echo "🔄 ห้องที่อัพเดท: {$updatedCount} ห้อง\n";
    echo "📊 ห้องทั้งหมด: {$totalRooms} ห้อง\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    if ($updatedCount > 0) {
        echo "✨ อัพเดทสถานะห้องพักสำเร็จแล้ว!\n";
    } else {
        echo "✓ สถานะห้องพักถูกต้องทั้งหมดแล้ว ไม่จำเป็นต้องอัพเดท\n";
    }
    
    // Show current room status summary
    echo "\n📋 สรุปสถานะห้องพักปัจจุบัน:\n";
    $statusSummary = $pdo->query("
        SELECT 
            CASE WHEN room_status = '0' THEN 'ว่าง' ELSE 'ไม่ว่าง' END as status_text,
            COUNT(*) as count
        FROM room
        GROUP BY room_status
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statusSummary as $summary) {
        $icon = $summary['status_text'] === 'ว่าง' ? '🟢' : '🔴';
        echo "{$icon} {$summary['status_text']}: {$summary['count']} ห้อง\n";
    }
    
} catch (PDOException $e) {
    echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
}

echo "\n✅ เสร็จสิ้น!\n";
