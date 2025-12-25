<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$token = $_GET['token'] ?? '';
if (!$token) {
    http_response_code(400);
    echo "Missing token";
    exit;
}

try {
    // Find contract by access token
    $stmt = $pdo->prepare("SELECT ctr_id FROM contract WHERE access_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $ctrId = $stmt->fetchColumn();
    if (!$ctrId) {
        http_response_code(404);
        echo "Contract not found";
        exit;
    }
    // Redirect to existing print contract page
    header('Location: ../Reports/print_contract.php?ctr_id=' . urlencode((string)$ctrId));
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo "Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
