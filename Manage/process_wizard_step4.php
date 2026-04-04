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

// CSRF validation
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['wizard_error'] = 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่';
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
    $water_meter_start = isset($_POST['water_meter_start']) ? min(9999999, max(0, (int)$_POST['water_meter_start'])) : 0;
    $elec_meter_start  = isset($_POST['elec_meter_start'])  ? min(99999,   max(0, (int)$_POST['elec_meter_start']))  : 0;

    if ($ctr_id <= 0 || empty($tnt_id) || empty($checkin_date)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }

    // หมายเหตุ: ไม่ต้องตรวจสอบค่ามิเตอร์ในขั้นตอน 4 (เช็คอิน) เพราะจะจดมิเตอร์ในขั้นตอน 5
    // ปล่อยให้ค่ามิเตอร์เป็น 0 ได้

    $pdo->beginTransaction();

    // กันข้อมูลซ้ำ: 1 สัญญาควรมีข้อมูล check-in ล่าสุดเพียง 1 รายการ
    $existingCheckinStmt = $pdo->prepare("SELECT checkin_id FROM checkin_record WHERE ctr_id = ? ORDER BY checkin_id DESC LIMIT 1");
    $existingCheckinStmt->execute([$ctr_id]);
    $existingCheckin = $existingCheckinStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingCheckin) {
        $stmt = $pdo->prepare("
            UPDATE checkin_record
            SET checkin_date = ?,
                water_meter_start = ?,
                elec_meter_start = ?
            WHERE checkin_id = ?
        ");

        $stmt->execute([
            $checkin_date,
            $water_meter_start,
            $elec_meter_start,
            (int)$existingCheckin['checkin_id']
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO checkin_record
            (checkin_date, water_meter_start, elec_meter_start, ctr_id, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $checkin_date,
            $water_meter_start,
            $elec_meter_start,
            $ctr_id,
            $_SESSION['admin_username']
        ]);
    }

    // อัปเดตสถานะผู้เช่าเป็น "พักอยู่" (1)
    $stmt = $pdo->prepare("UPDATE tenant SET tnt_status = '1' WHERE tnt_id = ?");
    $stmt->execute([$tnt_id]);
    
    // อัปเดตสถานะห้องเป็น "ไม่ว่าง" (1) เมื่อเช็คอิน
    // ดึง room_id และวันเริ่มสัญญา
    $stmt = $pdo->prepare("SELECT room_id, ctr_start FROM contract WHERE ctr_id = ?");
    $stmt->execute([$ctr_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contract) {
        $stmt = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
        $stmt->execute([$contract['room_id']]);
    }

    // ใช้เดือนเริ่มสัญญาเป็นเดือนอ้างอิงหลักสำหรับมิเตอร์/บิลแรก
    $effectiveDate = $checkin_date;
    if (!empty($contract['ctr_start']) && $contract['ctr_start'] !== '0000-00-00') {
        $effectiveDate = (string)$contract['ctr_start'];
    }

    $effectiveDt = new DateTime($effectiveDate);
    $targetMonth = $effectiveDt->format('Y-m');
    $targetMonthDate = $targetMonth . '-01';

    // ไม่สร้าง utility record ถ้าค่ามิเตอร์ทั้งสองเป็น 0 (ยังไม่ได้จดจริง)
    if ($water_meter_start > 0 || $elec_meter_start > 0) {
    $checkUtilityStmt = $pdo->prepare("SELECT utl_id, utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? AND DATE_FORMAT(utl_date, '%Y-%m') = ? ORDER BY utl_id DESC LIMIT 1");
    $checkUtilityStmt->execute([$ctr_id, $targetMonth]);
    $existingUtility = $checkUtilityStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUtility) {
        // ถ้ามี row ของเดือนนั้นอยู่แล้ว ให้เติมค่าเริ่มต้นแทนค่าศูนย์เดิม
        $updateUtilityStmt = $pdo->prepare("\n            UPDATE utility\n            SET utl_water_start = ?,\n                utl_elec_start = ?,\n                utl_water_end = CASE WHEN COALESCE(utl_water_end, 0) <= 0 THEN ? ELSE utl_water_end END,\n                utl_elec_end = CASE WHEN COALESCE(utl_elec_end, 0) <= 0 THEN ? ELSE utl_elec_end END\n            WHERE utl_id = ?\n        ");
        $updateUtilityStmt->execute([
            $water_meter_start,
            $elec_meter_start,
            $water_meter_start,
            $elec_meter_start,
            (int)$existingUtility['utl_id']
        ]);
    } else {
        $insertUtilityStmt = $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) VALUES (?, ?, ?, ?, ?, ?)");
        $insertUtilityStmt->execute([
            $ctr_id,
            $water_meter_start,
            $water_meter_start,
            $elec_meter_start,
            $elec_meter_start,
            $targetMonthDate
        ]);
    }
    } // end: only if meter values > 0

    // อัปเดต Workflow Step 4 เมื่อข้อมูลครบถ้วนแล้ว
    updateWorkflowStep($pdo, $tnt_id, 4, $_SESSION['admin_username']);
    
    // สร้างบิลเดือนแรกอัตโนมัติ
    // ตรวจสอบว่ามีบิลสำหรับเดือนนี้แล้วหรือไม่
    $checkExpStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
    $checkExpStmt->execute([$ctr_id, $targetMonth]);
    
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
            $targetMonthDate,
            $rate_elec,
            $rate_water,
            $room_price,
            $exp_total,
            $ctr_id
        ]);
    }

    $pdo->commit();

    $_SESSION['success'] = "บันทึกเช็คอินเรียบร้อยแล้ว กรุณาดำเนินการขั้นตอนที่ 5 เพื่อจดมิเตอร์และออกบิล";
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
