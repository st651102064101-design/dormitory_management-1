<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT p.*, e.ctr_id, e.exp_month FROM payment p LEFT JOIN expense e ON p.exp_id = e.exp_id WHERE p.exp_id = 777608161");
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

foreach ($payments as $pay) {
    echo $pay['pay_id'] . " => " . $buildPaymentKey($pay) . "\n";
}
