<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/wizard_helper.php';

$pdo = connectDB();

try {
    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    $tnt_id = trim($_POST['tnt_id'] ?? '');
    $checkin_date = $_POST['checkin_date'] ?? '';
    $water_meter_start = isset($_POST['water_meter_start']) ? (float)$_POST['water_meter_start'] : 0;
    $elec_meter_start = isset($_POST['elec_meter_start']) ? (float)$_POST['elec_meter_start'] : 0;
    $key_number = trim($_POST['key_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($ctr_id <= 0 || empty($tnt_id) || empty($checkin_date)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }

    // อัปโหลดรูปภาพ (ถ้ามี)
    $roomImagePaths = [];
    if (!empty($_FILES['room_images']['name'][0])) {
        $uploadDir = __DIR__ . '/../Public/Assets/Images/Checkin';
        $roomImagePaths = uploadMultipleFiles($_FILES['room_images'], $uploadDir, ['jpg', 'jpeg', 'png']);
    }

    $pdo->beginTransaction();

    // บันทึกข้อมูลเช็คอิน
    $stmt = $pdo->prepare("
        INSERT INTO checkin_record
        (checkin_date, water_meter_start, elec_meter_start, room_images, key_number, notes, ctr_id, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $roomImagesJson = !empty($roomImagePaths) ? json_encode($roomImagePaths) : null;

    $stmt->execute([
        $checkin_date,
        $water_meter_start,
        $elec_meter_start,
        $roomImagesJson,
        $key_number,
        $notes,
        $ctr_id,
        $_SESSION['admin_username']
    ]);

    // อัปเดตสถานะผู้เช่าเป็น "พักอยู่" (1)
    $stmt = $pdo->prepare("UPDATE tenant SET tnt_status = '1' WHERE tnt_id = ?");
    $stmt->execute([$tnt_id]);
    
    // อัปเดตสถานะห้องเป็น "ไม่ว่าง" (1) เมื่อเช็คอิน
    // ดึง room_id จาก contract
    $stmt = $pdo->prepare("SELECT room_id FROM contract WHERE ctr_id = ?");
    $stmt->execute([$ctr_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contract) {
        $stmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
        $stmt->execute([$contract['room_id']]);
    }

    // อัปเดต Workflow Step 4
    updateWorkflowStep($pdo, $tnt_id, 4, $_SESSION['admin_username']);
    
    // สร้างบิลเดือนแรกอัตโนมัติ
    $checkinDt = new DateTime($checkin_date);
    $currentMonth = $checkinDt->format('Y-m');
    
    // ตรวจสอบว่ามีบิลสำหรับเดือนนี้แล้วหรือไม่
    $checkExpStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
    $checkExpStmt->execute([$ctr_id, $currentMonth]);
    
    if ((int)$checkExpStmt->fetchColumn() === 0) {
        // ดึงข้อมูลห้องและค่าเช่า
        $roomStmt = $pdo->prepare("
            SELECT rt.type_price 
            FROM contract c 
            LEFT JOIN room r ON c.room_id = r.room_id 
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
            WHERE c.ctr_id = ?
        ");
        $roomStmt->execute([$ctr_id]);
        $roomData = $roomStmt->fetch(PDO::FETCH_ASSOC);
        $room_price = (int)($roomData['type_price'] ?? 0);
        
        // ดึงอัตราค่าน้ำและไฟ
        $rateStmt = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY rate_id DESC LIMIT 1");
        $rateRow = $rateStmt ? $rateStmt->fetch(PDO::FETCH_ASSOC) : null;
        $rate_elec = (int)($rateRow['rate_elec'] ?? 8);
        $rate_water = (int)($rateRow['rate_water'] ?? 18);
        
        // สร้างบิลใหม่
        $exp_total = $room_price;
        
        $createExpStmt = $pdo->prepare("
            INSERT INTO expense (
                exp_month, 
                exp_elec_unit, 
                exp_water_unit, 
                rate_elec, 
                rate_water, 
                room_price, 
                exp_elec_chg, 
                exp_water, 
                exp_total, 
                exp_status, 
                ctr_id
            ) VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '0', ?)
        ");
        
        $createExpStmt->execute([
            $currentMonth . '-01',
            $rate_elec,
            $rate_water,
            $room_price,
            $exp_total,
            $ctr_id
        ]);
    }

    $pdo->commit();

    $_SESSION['success'] = "บันทึกเช็คอินเรียบร้อย! ขั้นตอนถัดไป: เริ่มบิลรายเดือน";
    header('Location: ../Reports/tenant_wizard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Process Wizard Step 4 Error: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/tenant_wizard.php');
    exit;
}
