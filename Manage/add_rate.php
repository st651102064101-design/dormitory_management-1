<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'invalid method']);
    exit;
}

$rate_water = isset($_POST['rate_water']) ? (int)$_POST['rate_water'] : null;
$rate_elec = isset($_POST['rate_elec']) ? (int)$_POST['rate_elec'] : null;
$effective_date = isset($_POST['effective_date']) ? $_POST['effective_date'] : date('Y-m-d');

if ($rate_water === null || $rate_elec === null) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

if ($rate_water < 0 || $rate_elec < 0) {
    echo json_encode(['success' => false, 'message' => 'อัตราต้องไม่ติดลบ']);
    exit;
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();

    // สร้าง record ใหม่เสมอ (เก็บประวัติทุกครั้ง)
    $stmt = $pdo->prepare('INSERT INTO rate (rate_water, rate_elec, effective_date) VALUES (?, ?, ?)');
    $stmt->execute([$rate_water, $rate_elec, $effective_date]);
    $rateId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'rate_id' => $rateId,
        'rate_water' => $rate_water,
        'rate_elec' => $rate_elec,
        'effective_date' => $effective_date
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
