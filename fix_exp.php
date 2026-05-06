<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("UPDATE expense SET exp_status = '2' WHERE exp_status IN ('0', '3', '4') AND EXISTS (SELECT 1 FROM payment p WHERE p.exp_id = expense.exp_id AND p.pay_status = '0' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ')");
echo "Updated " . $stmt->rowCount() . " rows\n";
