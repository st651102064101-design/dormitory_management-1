<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is CLI only.\n";
    exit(1);
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/payment_reminder_service.php';

$opts = getopt('', ['month::', 'limit::', 'dry-run']);

$targetMonth = isset($opts['month']) ? trim((string)$opts['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
    fwrite(STDERR, "Invalid --month format. Use YYYY-MM\n");
    exit(1);
}

$limit = isset($opts['limit']) ? (int)$opts['limit'] : 200;
$limit = max(1, min(500, $limit));
$dryRun = array_key_exists('dry-run', $opts);

try {
    $pdo = connectDB();

    $dueDayStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_due_day' LIMIT 1");
    $dueDayStmt->execute();
    $paymentDueDay = max(1, min(28, (int)($dueDayStmt->fetchColumn() ?: 5)));

    $idStmt = $pdo->prepare(
        "SELECT e.exp_id
         FROM expense e
         INNER JOIN contract c ON c.ctr_id = e.ctr_id
         WHERE c.ctr_status = '0'
           AND e.exp_status IN ('0', '3', '4')
           AND DATE_FORMAT(e.exp_month, '%Y-%m') <= :targetMonth
         ORDER BY e.exp_id ASC
         LIMIT :limitRows"
    );
    $idStmt->bindValue(':targetMonth', $targetMonth, PDO::PARAM_STR);
    $idStmt->bindValue(':limitRows', $limit, PDO::PARAM_INT);
    $idStmt->execute();

    $expenseIds = array_map('intval', $idStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($expenseIds)) {
        echo "No payable expenses found for month <= {$targetMonth}.\n";
        exit(0);
    }

    $summary = [
        'sent' => 0,
        'queued' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    foreach ($expenseIds as $expenseId) {
        $result = dispatchPaymentReminder($pdo, $expenseId, [
            'payment_due_day' => $paymentDueDay,
            'requested_by' => 'cron',
            'source' => 'cron_batch',
            'request_ip' => 'cli',
            'dry_run' => $dryRun,
        ]);

        $status = (string)($result['status'] ?? 'failed');
        if (!array_key_exists($status, $summary)) {
            $status = 'failed';
        }
        $summary[$status]++;
    }

    echo "Payment reminder batch complete\n";
    echo "Month target : {$targetMonth}\n";
    echo "Limit        : {$limit}\n";
    echo "Dry run      : " . ($dryRun ? 'yes' : 'no') . "\n";
    echo "Sent         : {$summary['sent']}\n";
    echo "Queued       : {$summary['queued']}\n";
    echo "Skipped      : {$summary['skipped']}\n";
    echo "Failed       : {$summary['failed']}\n";

    exit($summary['failed'] > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, '[send_payment_reminders_cron] ' . $e->getMessage() . "\n");
    exit(1);
}
