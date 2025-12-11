<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$repairId = isset($_GET['repair_id']) ? (int)$_GET['repair_id'] : 0;

if ($repairId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการแจ้งซ่อม']);
    exit;
}

try {
    // ตรวจสอบว่ามีคอลัมน์นัดหมายหรือยัง
    $checkColumn = $pdo->query("SHOW COLUMNS FROM repair LIKE 'scheduled_date'");
    $hasScheduleColumns = $checkColumn->rowCount() > 0;
    
    if ($hasScheduleColumns) {
        $stmt = $pdo->prepare("
            SELECT r.*, t.tnt_name, t.tnt_phone, rm.room_number
            FROM repair r
            LEFT JOIN contract c ON r.ctr_id = c.ctr_id
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            LEFT JOIN room rm ON c.room_id = rm.room_id
            WHERE r.repair_id = :repair_id
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT r.repair_id, r.repair_desc, r.repair_status, r.repair_date, r.repair_time,
                   NULL as scheduled_date, NULL as scheduled_time_start, NULL as scheduled_time_end,
                   NULL as technician_name, NULL as technician_phone, NULL as schedule_note,
                   t.tnt_name, t.tnt_phone, rm.room_number
            FROM repair r
            LEFT JOIN contract c ON r.ctr_id = c.ctr_id
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            LEFT JOIN room rm ON c.room_id = rm.room_id
            WHERE r.repair_id = :repair_id
        ");
    }
    
    $stmt->execute([':repair_id' => $repairId]);
    $repair = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$repair) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการแจ้งซ่อม']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $repair
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
