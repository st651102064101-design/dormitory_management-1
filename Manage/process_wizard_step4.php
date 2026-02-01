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
