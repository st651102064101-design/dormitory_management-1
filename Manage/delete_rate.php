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

$rate_id = isset($_POST['rate_id']) ? (int)$_POST['rate_id'] : null;

if (!$rate_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ rate_id']);
    exit;
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();

    // ตรวจสอบว่าไม่ใช่ record ล่าสุด (ไม่ให้ลบ record ที่ใช้งานอยู่)
    $latestStmt = $pdo->query("SELECT rate_id FROM rate ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latest && (int)$latest['rate_id'] === $rate_id) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบอัตราที่ใช้งานอยู่ได้']);
        exit;
    }

    // ดึงข้อมูลอัตราที่จะลบ
    $rateStmt = $pdo->prepare("SELECT rate_water, rate_elec FROM rate WHERE rate_id = ?");
    $rateStmt->execute([$rate_id]);
    $rateData = $rateStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rateData) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลอัตรา']);
        exit;
    }

    // ตรวจสอบว่ามี expense ใช้อัตรานี้หรือไม่
    $usageStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM expense WHERE rate_water = ? AND rate_elec = ?");
    $usageStmt->execute([$rateData['rate_water'], $rateData['rate_elec']]);
    $usageCount = (int)$usageStmt->fetchColumn();
    
    if ($usageCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "ไม่สามารถลบได้ เพราะมีบิลค่าใช้จ่าย {$usageCount} รายการใช้อัตรานี้อยู่"
        ]);
        exit;
    }

    // ลบ
    $stmt = $pdo->prepare("DELETE FROM rate WHERE rate_id = ?");
    $stmt->execute([$rate_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'ลบสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลที่ต้องการลบ']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
