<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
        SELECT e.*, 
               (SELECT COALESCE(SUM(p.pay_amount), 0) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1') as paid_amount,
               (SELECT COALESCE(SUM(p.pay_amount), 0) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '0') as pending_amount
        FROM expense e
        WHERE e.exp_id = 775775590
");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
