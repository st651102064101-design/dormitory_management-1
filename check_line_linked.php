<?php
/**
 * Check LINE Linked Status
 * ตรวจสอบว่าผู้เช่า (Tenant) มีการผูก LINE หรือยัง เพื่ออัปเดต UI หน้าจอ
 */
declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

header('Content-Type: application/json; charset=utf-8');

$tenantId = $_GET['tenant_id'] ?? '';

if (empty($tenantId)) {
    echo json_encode(['linked' => false, 'error' => 'No tenant ID provided']);
    exit;
}

try {
    $pdo = connectDB();
    $stmt = $pdo->prepare("SELECT line_user_id FROM tenant WHERE tnt_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['line_user_id'])) {
        echo json_encode(['linked' => true]);
    } else {
        echo json_encode(['linked' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['linked' => false, 'error' => 'System error']);
}
