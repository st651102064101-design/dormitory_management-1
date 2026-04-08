<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

$phone = '0980102587';

$stmt = $pdo->prepare("SELECT * FROM tenant WHERE tnt_phone = ?");
$stmt->execute([$phone]);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "--- Tenants ---\n";
print_r($tenants);

if (!empty($tenants)) {
    $tnt_id = $tenants[0]['tnt_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM manage_booking WHERE tnt_id = ?");
    $stmt->execute([$tnt_id]);
    echo "--- Bookings ---\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $stmt = $pdo->prepare("SELECT * FROM contract WHERE tnt_id = ?");
    $stmt->execute([$tnt_id]);
    echo "--- Contracts ---\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $stmt = $pdo->prepare("SELECT * FROM tenant_workflow WHERE tnt_id = ?");
    $stmt->execute([$tnt_id]);
    echo "--- Workflows ---\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
