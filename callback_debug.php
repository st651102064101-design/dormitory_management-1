<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
        SELECT e.*, 
               (SELECT COALESCE(SUM(p.pay_amount), 0) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ') as paid_amount,
               (SELECT COALESCE(SUM(p.pay_amount), 0) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '0' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ') as pending_amount,
               (SELECT COUNT(*) FROM payment p WHERE p.exp_id = e.exp_id AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ') as payment_count,
               (SELECT p.pay_status FROM payment p WHERE p.exp_id = e.exp_id AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ' ORDER BY p.pay_date DESC LIMIT 1) as last_payment_status,
               (SELECT COUNT(*) FROM payment p WHERE p.exp_id = e.exp_id AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ') as deposit_payment_count,
               (SELECT COALESCE(SUM(p.pay_amount), 0) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ') as deposit_paid_amount,
               (SELECT COALESCE(SUM(p.pay_amount), 0) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '0' AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ') as deposit_pending_amount
        FROM expense e
        WHERE e.ctr_id = 775943900");
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPaid = 0;
$totalUnpaid = 0;
$contract = ['ctr_deposit'=>2000];

foreach ($expenses as $expIndex => $exp) {
    $paidAmount = (float)($exp['paid_amount'] ?? 0);
    $pendingAmount = (float)($exp['pending_amount'] ?? 0);
    $depositPaidAmount = (float)($exp['deposit_paid_amount'] ?? 0);
    $depositPendingAmount = (float)($exp['deposit_pending_amount'] ?? 0);
    $expTotal = (float)$exp['exp_total'];
    
    $roomPrice = (float)($exp['room_price'] ?? 0);
    $elecChg = (float)($exp['exp_elec_chg'] ?? 0);
    $waterChg = (float)($exp['exp_water'] ?? 0);
    $calculatedTotal = $roomPrice + $elecChg + $waterChg;
    $otherFee = $expTotal - $calculatedTotal;
    $ctrDeposit = (float)($contract['ctr_deposit'] ?? 2000);
    
    $depositPaymentCount = (int)($exp['deposit_payment_count'] ?? 0);
    $isDepositOnly = ($depositPaymentCount > 0) 
        || ($expIndex === count($expenses) - 1 && $expTotal == $ctrDeposit && $elecChg == 0 && $waterChg == 0 && $roomPrice > 0 && $expTotal != $calculatedTotal);

    if ($isDepositOnly) {
        $paidAmount = $paidAmount + $depositPaidAmount;
        $pendingAmount = $pendingAmount + $depositPendingAmount;
    }
    
    $remaining = max(0, $expTotal - $paidAmount);
    
    if ($paidAmount >= $expTotal && $expTotal > 0) {
        $totalPaid += $expTotal;
    } elseif ($pendingAmount >= $expTotal) {
    } else {
        $totalUnpaid += max(0, $remaining - $pendingAmount);
    }
}
echo "totalUnpaid: " . $totalUnpaid . "\ntotalPaid: " . $totalPaid;
