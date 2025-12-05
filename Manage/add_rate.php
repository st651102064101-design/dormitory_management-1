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

$rate_water = isset($_POST['rate_water']) ? (float)$_POST['rate_water'] : null;
$rate_elec = isset($_POST['rate_elec']) ? (float)$_POST['rate_elec'] : null;

if ($rate_water === null || $rate_elec === null) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();

    $stmt = $pdo->prepare('INSERT INTO rate (rate_water, rate_elec) VALUES (?, ?)');
    $stmt->execute([$rate_water, $rate_elec]);
    $rateId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'rate_id' => $rateId,
        'rate_water' => $rate_water,
        'rate_elec' => $rate_elec,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
