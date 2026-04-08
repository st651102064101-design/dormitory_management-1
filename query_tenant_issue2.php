<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

$tnts = ['1774529248', '1775444378', '1775619508'];

foreach($tnts as $id) {
    echo "--- ID: $id ---\n";
    $stmt = $pdo->prepare("SELECT * FROM booking WHERE tnt_id = ?");
    $stmt->execute([$id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $stmt = $pdo->prepare("SELECT * FROM contract WHERE tnt_id = ?");
    $stmt->execute([$id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $stmt = $pdo->prepare("SELECT * FROM tenant_workflow WHERE tnt_id = ?");
    $stmt->execute([$id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
