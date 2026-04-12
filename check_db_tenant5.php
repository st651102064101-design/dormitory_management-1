<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT c.tnt_id, e.exp_id, e.exp_total, e.room_price, c.ctr_deposit, e.paid_amount, e.pending_amount, e.deposit_paid_amount, e.deposit_payment_count FROM expense e JOIN contract c ON e.ctr_id = c.ctr_id WHERE c.tnt_id=(SELECT tnt_id FROM tenant ORDER BY tnt_id DESC LIMIT 1)");
print_r($stmt->fetchAll());
