<?php
require 'ConnectDB.php';
$pdo = connectDB();
$sql = "SELECT e.exp_id, e.exp_month, e.exp_total, c.ctr_start, c.ctr_id, p.pay_amount, p.pay_remark 
        FROM contract c
        JOIN room r ON c.room_id = r.room_id
        LEFT JOIN expense e ON e.ctr_id = c.ctr_id
        LEFT JOIN payment p ON e.exp_id = p.exp_id
        WHERE r.room_number = '7' AND c.ctr_status = '0'";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
