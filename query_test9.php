<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
    SELECT * 
    FROM payment p 
    JOIN expense e ON p.exp_id = e.exp_id 
    JOIN contract c ON e.ctr_id = c.ctr_id 
    JOIN tenant_workflow tw ON c.ctr_id = tw.ctr_id 
    WHERE tw.bkg_id = ?
");
$stmt->execute(['777295620']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
