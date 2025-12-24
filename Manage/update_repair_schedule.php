<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// GET request = get last technician info
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'last_technician') {
    try {
        $stmt = $pdo->query("
            SELECT technician_name, technician_phone 
            FROM repair 
            WHERE technician_name IS NOT NULL AND technician_name != ''
            ORDER BY repair_id DESC 
            LIMIT 1
        ");
        $lastTech = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $lastTech ?: null]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'data' => null]);
    }
    exit;
}

// POST request = save schedule
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$repairId = isset($_POST['repair_id']) ? (int)$_POST['repair_id'] : 0;
$scheduledDate = isset($_POST['scheduled_date']) ? trim($_POST['scheduled_date']) : '';
$scheduledTimeStart = isset($_POST['scheduled_time_start']) ? trim($_POST['scheduled_time_start']) : '';
$scheduledTimeEnd = isset($_POST['scheduled_time_end']) ? trim($_POST['scheduled_time_end']) : '';
$technicianName = isset($_POST['technician_name']) ? trim($_POST['technician_name']) : '';
$technicianPhone = isset($_POST['technician_phone']) ? trim($_POST['technician_phone']) : '';
$scheduleNote = isset($_POST['schedule_note']) ? trim($_POST['schedule_note']) : '';

if ($repairId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสการแจ้งซ่อม']);
    exit;
}

if (empty($scheduledDate)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุวันที่นัดซ่อม']);
    exit;
}

try {
    // ตรวจสอบว่ามีคอลัมน์นัดหมายหรือยัง
    $checkColumn = $pdo->query("SHOW COLUMNS FROM repair LIKE 'scheduled_date'");
    if ($checkColumn->rowCount() === 0) {
        // สร้างคอลัมน์ใหม่
        $pdo->exec("
            ALTER TABLE repair 
            ADD COLUMN scheduled_date DATE DEFAULT NULL COMMENT 'วันที่นัดซ่อม',
            ADD COLUMN scheduled_time_start TIME DEFAULT NULL COMMENT 'เวลาเริ่มต้น',
            ADD COLUMN scheduled_time_end TIME DEFAULT NULL COMMENT 'เวลาสิ้นสุด',
            ADD COLUMN technician_name VARCHAR(100) DEFAULT NULL COMMENT 'ชื่อช่างผู้รับผิดชอบ',
            ADD COLUMN technician_phone VARCHAR(20) DEFAULT NULL COMMENT 'เบอร์โทรช่าง',
            ADD COLUMN schedule_note TEXT DEFAULT NULL COMMENT 'หมายเหตุการนัดหมาย'
        ");
    }
    
    // อัปเดตข้อมูลนัดหมาย
    $stmt = $pdo->prepare("
        UPDATE repair SET 
            scheduled_date = :scheduled_date,
            scheduled_time_start = :scheduled_time_start,
            scheduled_time_end = :scheduled_time_end,
            technician_name = :technician_name,
            technician_phone = :technician_phone,
            schedule_note = :schedule_note
        WHERE repair_id = :repair_id
    ");
    
    $stmt->execute([
        ':scheduled_date' => $scheduledDate ?: null,
        ':scheduled_time_start' => $scheduledTimeStart ?: null,
        ':scheduled_time_end' => $scheduledTimeEnd ?: null,
        ':technician_name' => $technicianName ?: null,
        ':technician_phone' => $technicianPhone ?: null,
        ':schedule_note' => $scheduleNote ?: null,
        ':repair_id' => $repairId
    ]);
    
    // ดึงข้อมูลผู้เช่าเพื่อแจ้งเตือน
    $tenantStmt = $pdo->prepare("
        SELECT t.tnt_name, t.tnt_phone, rm.room_number, r.repair_desc
        FROM repair r
        LEFT JOIN contract c ON r.ctr_id = c.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room rm ON c.room_id = rm.room_id
        WHERE r.repair_id = :repair_id
    ");
    $tenantStmt->execute([':repair_id' => $repairId]);
    $tenantInfo = $tenantStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $formattedDate = '';
    if ($scheduledDate) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $scheduledDate);
        if ($dateObj) {
            $thaiYear = (int)$dateObj->format('Y') + 543;
            $formattedDate = $dateObj->format('d/m/') . $thaiYear;
        }
    }
    
    $timeRange = '';
    if ($scheduledTimeStart && $scheduledTimeEnd) {
        $timeRange = substr($scheduledTimeStart, 0, 5) . ' - ' . substr($scheduledTimeEnd, 0, 5) . ' น.';
    } elseif ($scheduledTimeStart) {
        $timeRange = substr($scheduledTimeStart, 0, 5) . ' น.';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'บันทึกนัดหมายเรียบร้อยแล้ว',
        'data' => [
            'scheduled_date' => $scheduledDate,
            'formatted_date' => $formattedDate,
            'time_range' => $timeRange,
            'technician_name' => $technicianName,
            'technician_phone' => $technicianPhone,
            'schedule_note' => $scheduleNote,
            'tenant_name' => $tenantInfo['tnt_name'] ?? '',
            'room_number' => $tenantInfo['room_number'] ?? ''
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
