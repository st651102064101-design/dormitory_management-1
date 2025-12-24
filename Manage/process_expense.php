<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    $exp_month = $_POST['exp_month'] ?? '';
    $exp_elec_unit = isset($_POST['exp_elec_unit']) ? (int)$_POST['exp_elec_unit'] : 0;
    $exp_water_unit = isset($_POST['exp_water_unit']) ? (int)$_POST['exp_water_unit'] : 0;
    $rate_elec = isset($_POST['rate_elec']) ? (float)$_POST['rate_elec'] : 0;
    $rate_water = isset($_POST['rate_water']) ? (float)$_POST['rate_water'] : 0;

    if ($ctr_id <= 0 || $exp_month === '') {
        die(json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']));
    }

    // แปลง month input (YYYY-MM) เป็น date (YYYY-MM-01)
    $exp_month_date = $exp_month . '-01';

    // ดึงข้อมูลสัญญาและราคาห้อง
    $ctrStmt = $pdo->prepare("
        SELECT c.ctr_id, c.ctr_status, r.room_id, rt.type_price
        FROM contract c
        LEFT JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE c.ctr_id = ?
    ");
    $ctrStmt->execute([$ctr_id]);
    $contract = $ctrStmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        die(json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลสัญญา']));
    }

    $room_price = (int)($contract['type_price'] ?? 0);

    // คำนวณค่าใช้จ่าย
    $exp_elec_chg = (int)round($exp_elec_unit * $rate_elec);
    $exp_water = (int)round($exp_water_unit * $rate_water);
    $exp_total = $room_price + $exp_elec_chg + $exp_water;

    // ตรวจสอบว่ามีการบันทึกค่าใช้จ่ายของเดือนนี้แล้วหรือไม่
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
    $checkStmt->execute([$ctr_id, $exp_month]);
    if ((int)$checkStmt->fetchColumn() > 0) {
        die(json_encode(['success' => false, 'error' => 'มีการบันทึกค่าใช้จ่ายของเดือนนี้แล้ว']));
    }

    // บันทึกข้อมูล (สถานะ 0 = ยังไม่จ่าย)
    $insert = $pdo->prepare("
        INSERT INTO expense 
        (exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '0', ?)
    ");
    $insert->execute([
        $exp_month_date,
        $exp_elec_unit,
        $exp_water_unit,
        (int)round($rate_elec), // เก็บเป็นบาทเต็ม
        (int)round($rate_water), // เก็บเป็นบาทเต็ม
        $room_price,
        $exp_elec_chg,
        $exp_water,
        $exp_total,
        $ctr_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'เพิ่มค่าใช้จ่ายเรียบร้อยแล้ว (ยอดรวม ฿' . number_format($exp_total) . ')'
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Process Expense Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log('Process Expense Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
?>
