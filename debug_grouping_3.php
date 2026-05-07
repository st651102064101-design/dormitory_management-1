<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT p.*, e.exp_month, c.ctr_id FROM payment p LEFT JOIN expense e ON p.exp_id = e.exp_id LEFT JOIN contract c ON e.ctr_id = c.ctr_id WHERE p.exp_id = 777608161");
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$buildPaymentKey = static function(array $pay): string {
    $source = (string)($pay['payment_source'] ?? 'payment');
    $isDeposit = strpos((string)($pay['pay_remark'] ?? ''), 'มัดจำ') !== false;
    $category = $isDeposit ? 'deposit' : 'bill';
    
    $ctrKey = trim((string)($pay['ctr_id'] ?? ''));
    if ($ctrKey !== '') {
      $expId = (int)($pay['exp_id'] ?? 0);
      if ($expId > 0) {
        return implode('|', [
          $category,
          'source:' . $source,
          'ctr:' . $ctrKey,
          'exp:' . $expId,
        ]);
      }
    }
    return 'fallback';
};

$paymentGroups = [];
foreach ($payments as $pay) {
  $paymentKey = $buildPaymentKey($pay);
  if (!isset($paymentGroups[$paymentKey])) {
      $paymentGroups[$paymentKey] = [
        'count' => 0,
        'active_count' => 0,
        'has_rejected' => false,
        'has_verified' => false,
        'has_pending' => false,
        'amount_by_status' => ['0' => 0, '1' => 0, '2' => 0, 'unpaid' => 0],
        'items' => [],
      ];
    }

    $paymentGroups[$paymentKey]['count']++;
    $currentStatus = (string)($pay['pay_status'] ?? '0');
    $currentAmount = (int)($pay['pay_amount'] ?? 0);
    if ($currentStatus !== '2') {
      $paymentGroups[$paymentKey]['active_count']++;
    }
    if (!isset($paymentGroups[$paymentKey]['amount_by_status'][$currentStatus])) {
      $paymentGroups[$paymentKey]['amount_by_status'][$currentStatus] = 0;
    }
    $paymentGroups[$paymentKey]['amount_by_status'][$currentStatus] += $currentAmount;

    $paymentGroups[$paymentKey]['items'][] = [
      'pay_id' => (int)($pay['pay_id'] ?? 0),
      'date' => (string)($pay['pay_date'] ?? ''),
      'amount' => $currentAmount,
      'status' => $currentStatus,
      'remark' => (string)($pay['pay_remark'] ?? ''),
      'pay_proof' => (string)($pay['pay_proof'] ?? ''),
    ];
}
print_r($paymentGroups);
