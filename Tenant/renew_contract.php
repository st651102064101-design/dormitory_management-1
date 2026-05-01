<?php
require_once __DIR__ . '/../ConnectDB.php';
session_start();

if (!isset($_SESSION['tenant_token']) && empty($_SESSION['tenant_logged_in'])) {
    header("Location: /dormitory_management/");
    exit;
}

$pdo = connectDB();

// ดึงข้อมูลสัญญาปัจจุบันของผู้ใช้
$ctr_id = $_SESSION['tenant_ctr_id'] ?? null;
if (!$ctr_id) {
    die("ไม่พบข้อมูลสัญญา");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM contract WHERE ctr_id = ? LIMIT 1");
    $stmt->execute([$ctr_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        die("ไม่พบข้อมูลสัญญา");
    }

    // ถ้าสัญญาถูกยกเลิก (1) ตรวจสอบว่ามีผู้เช่าใหม่หรือไม่
    if ($contract['ctr_status'] == '1') {
        $checkOccupiedStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM contract 
            WHERE room_id = ? 
              AND ctr_status IN ('0', '2') 
              AND ctr_id != ?
        ");
        $checkOccupiedStmt->execute([$contract['room_id'], $contract['ctr_id']]);
        $hasNewTenant = (int)$checkOccupiedStmt->fetchColumn() > 0;

        if ($hasNewTenant) {
            // ป้องกันการเข้าถึงและไม่แสดงข้อมูลของผู้เช่าใหม่
            die('
            <!DOCTYPE html>
            <html lang="th">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>ไม่สามารถเข้าถึงได้</title>
                <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
                <style>
                    body { font-family: "Prompt", sans-serif; background: #f8fafc; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; text-align: center; }
                    .card { background: white; padding: 40px 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 400px; width: 100%; border-top: 4px solid #ef4444; }
                    .icon { font-size: 48px; margin-bottom: 20px; color: #ef4444; }
                    h1 { color: #1e293b; font-size: 20px; margin-bottom: 10px; }
                    p { color: #64748b; font-size: 14px; margin-bottom: 30px; line-height: 1.6; }
                    .btn { display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="icon">🚫</div>
                    <h1>ไม่สามารถทำรายการได้</h1>
                    <p>สัญญาเช่าของคุณสิ้นสุดแล้ว และมีผู้เช่ารายใหม่เข้าพักในห้องนี้แล้ว คุณไม่สามารถเข้าถึงหรือดูข้อมูลของห้องนี้ได้อีกเพื่อเป็นการรักษาความเป็นส่วนตัวของผู้เช่าปัจจุบัน</p>
                    <a href="index.php" class="btn">กลับหน้าหลัก</a>
                </div>
            </body>
            </html>
            ');
        }
    }
} catch (PDOException $e) {
    error_log("Error in renew_contract.php: " . $e->getMessage());
    die("เกิดข้อผิดพลาดของระบบ");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ต่อสัญญาเช่า</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; text-align: center; }
        .card { background: white; padding: 40px 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 400px; width: 100%; border-top: 4px solid #3b82f6;}
        .icon { font-size: 48px; margin-bottom: 20px; }
        h1 { color: #1e293b; font-size: 20px; margin-bottom: 10px; }
        p { color: #64748b; font-size: 14px; margin-bottom: 30px; line-height: 1.6; }
        .btn { display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚧</div>
        <h1>ระบบต่อสัญญาเช่า</h1>
        <p>ฟังก์ชันการต่อสัญญาออนไลน์กำลังอยู่ในระหว่างการพัฒนา กรุณาติดต่อชำระเงินและต่อสัญญากับเจ้าหน้าที่ดูแลหอพักโดยตรง</p>
        <a href="index.php" class="btn">กลับหน้าหลัก</a>
    </div>
</body>
</html>
