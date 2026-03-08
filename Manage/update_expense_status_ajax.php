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

$exp_id = (int)($_POST['exp_id'] ?? 0);
$exp_status = (int)($_POST['exp_status'] ?? 0);

if (!$exp_id || !in_array($exp_status, [0, 1], true)) {
    die(json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']));
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();

    $meterCheckStmt = $pdo->prepare(
        "SELECT
            e.exp_id,
            e.ctr_id,
            e.exp_month,
            u.utl_water_end,
            u.utl_elec_end
         FROM expense e
         LEFT JOIN utility u
           ON u.ctr_id = e.ctr_id
          AND YEAR(u.utl_date) = YEAR(e.exp_month)
          AND MONTH(u.utl_date) = MONTH(e.exp_month)
         WHERE e.exp_id = ?
         LIMIT 1"
    );
    $meterCheckStmt->execute([$exp_id]);
    $expenseRow = $meterCheckStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expenseRow) {
        die(json_encode(['success' => false, 'error' => 'ไม่พบรายการค่าใช้จ่ายที่ต้องการอัพเดท']));
    }

    $isMeterComplete = ($expenseRow['utl_water_end'] !== null && $expenseRow['utl_elec_end'] !== null);
    if (!$isMeterComplete) {
        die(json_encode([
            'success' => false,
            'error' => 'ยังไม่สามารถออกบิลได้ เนื่องจากยังไม่ได้จดมิเตอร์น้ำ/ไฟครบในเดือนนี้'
        ]));
    }
    
    $stmt = $pdo->prepare('UPDATE expense SET exp_status = ? WHERE exp_id = ?');
    $success = $stmt->execute([$exp_status, $exp_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        $statusText = $exp_status === 0 ? 'รอชำระ' : 'จ่ายแล้ว';
        echo json_encode(['success' => true, 'message' => "เปลี่ยนสถานะเป็น \"$statusText\" เรียบร้อยแล้ว"]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการค่าใช้จ่ายที่ต้องการอัพเดท']);
    }
} catch (PDOException $e) {
    error_log('Update expense status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Update expense status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>
