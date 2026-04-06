<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function prFormatBaht(int $amount): string
{
    return '฿' . number_format($amount);
}

function prFormatThaiMonthYear(string $dateValue): string
{
    $ts = strtotime($dateValue);
    if ($ts === false) {
        return '-';
    }

    $thaiMonths = [
        '01' => 'ม.ค.',
        '02' => 'ก.พ.',
        '03' => 'มี.ค.',
        '04' => 'เม.ย.',
        '05' => 'พ.ค.',
        '06' => 'มิ.ย.',
        '07' => 'ก.ค.',
        '08' => 'ส.ค.',
        '09' => 'ก.ย.',
        '10' => 'ต.ค.',
        '11' => 'พ.ย.',
        '12' => 'ธ.ค.',
    ];

    $month = date('m', $ts);
    $yearThai = (int)date('Y', $ts) + 543;

    return ($thaiMonths[$month] ?? $month) . ' ' . $yearThai;
}

function prFormatThaiDate(string $dateValue): string
{
    $ts = strtotime($dateValue);
    if ($ts === false) {
        return '-';
    }

    $thaiMonths = [
        '01' => 'ม.ค.',
        '02' => 'ก.พ.',
        '03' => 'มี.ค.',
        '04' => 'เม.ย.',
        '05' => 'พ.ค.',
        '06' => 'มิ.ย.',
        '07' => 'ก.ค.',
        '08' => 'ส.ค.',
        '09' => 'ก.ย.',
        '10' => 'ต.ค.',
        '11' => 'พ.ย.',
        '12' => 'ธ.ค.',
    ];

    $day = (int)date('j', $ts);
    $month = date('m', $ts);
    $yearThai = (int)date('Y', $ts) + 543;

    return $day . ' ' . ($thaiMonths[$month] ?? $month) . ' ' . $yearThai;
}

function prBuildDueDateIso(string $expenseMonth, int $paymentDueDay): string
{
    $ts = strtotime($expenseMonth);
    if ($ts === false) {
        return '';
    }

    $safeDueDay = max(1, min(28, $paymentDueDay));
    $year = (int)date('Y', $ts);
    $month = (int)date('m', $ts);

    return sprintf('%04d-%02d-%02d', $year, $month, $safeDueDay);
}

function prNormalizePhoneDigits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function prNormalizePhoneForIntl(string $phone): string
{
    $digits = prNormalizePhoneDigits($phone);
    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 10 && $digits[0] === '0') {
        return '+66' . substr($digits, 1);
    }

    if (strlen($digits) === 9) {
        return '+66' . $digits;
    }

    if (strpos($digits, '66') === 0) {
        return '+' . $digits;
    }

    return '+' . $digits;
}

function prGetSystemSettings(PDO $pdo): array
{
    static $loaded = false;
    static $settings = [];

    if ($loaded) {
        return $settings;
    }

    $loaded = true;

    try {
        $stmt = $pdo->query(
            "SELECT setting_key, setting_value
             FROM system_settings
             WHERE setting_key IN ('payment_due_day', 'bank_name', 'bank_account_number')"
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        // Keep defaults below
    }

    return $settings;
}

function prTableTenantOauthExists(PDO $pdo): bool
{
    static $checked = false;
    static $exists = false;

    if ($checked) {
        return $exists;
    }

    $checked = true;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tenant_oauth'");
        $exists = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

function prFetchTenantEmail(PDO $pdo, string $tenantId): string
{
    if ($tenantId === '' || !prTableTenantOauthExists($pdo)) {
        return '';
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT provider_email
             FROM tenant_oauth
             WHERE tnt_id = ?
               AND provider_email IS NOT NULL
               AND provider_email <> ''
             ORDER BY oauth_id DESC
             LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        return trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

function prAppendReminderLog(array $record): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        $line = json_encode([
            'timestamp' => date('c'),
            'error' => 'json_encode_failed',
        ]);
    }

    @file_put_contents($logDir . '/payment_reminder.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function prSendWebhook(array $payload, string $url, string $token = ''): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => 'invalid_webhook_url',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => 'curl_extension_not_available',
        ];
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => 'payload_encode_failed',
        ];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'http_code' => $httpCode,
            'response' => '',
            'error' => $curlError !== '' ? $curlError : 'curl_exec_failed',
        ];
    }

    $responsePreview = substr((string)$response, 0, 300);
    $isSuccess = $httpCode >= 200 && $httpCode < 300;

    return [
        'success' => $isSuccess,
        'http_code' => $httpCode,
        'response' => $responsePreview,
        'error' => $isSuccess ? '' : 'http_' . $httpCode,
    ];
}

/**
 * Dispatch reminder for one expense record.
 *
 * @return array<string,mixed>
 */
function dispatchPaymentReminder(PDO $pdo, int $expenseId, array $options = []): array
{
    if ($expenseId <= 0) {
        return [
            'success' => false,
            'status' => 'failed',
            'expense_id' => $expenseId,
            'error' => 'invalid_expense_id',
            'channel' => 'none',
        ];
    }

    $expenseStmt = $pdo->prepare(
        "SELECT
            e.exp_id,
            e.exp_month,
            e.exp_total,
            e.exp_status,
            e.ctr_id,
            c.tnt_id,
            c.access_token,
            t.tnt_name,
            t.tnt_phone,
            r.room_number
         FROM expense e
         INNER JOIN contract c ON c.ctr_id = e.ctr_id
         LEFT JOIN tenant t ON t.tnt_id = c.tnt_id
         LEFT JOIN room r ON r.room_id = c.room_id
         WHERE e.exp_id = :expenseId
           AND c.ctr_status = '0'
         LIMIT 1"
    );
    $expenseStmt->execute([':expenseId' => $expenseId]);
    $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        return [
            'success' => false,
            'status' => 'failed',
            'expense_id' => $expenseId,
            'error' => 'expense_not_found_or_inactive_contract',
            'channel' => 'none',
        ];
    }

    $rawStatus = (string)($expense['exp_status'] ?? '0');
    if (!in_array($rawStatus, ['0', '3', '4'], true)) {
        return [
            'success' => true,
            'status' => 'skipped',
            'expense_id' => $expenseId,
            'reason' => 'status_not_payable',
            'channel' => 'none',
        ];
    }

    $paidStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(pay_amount), 0)
         FROM payment
         WHERE exp_id = :expenseId
           AND pay_status = '1'
           AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'"
    );
    $paidStmt->execute([':expenseId' => $expenseId]);

    $totalAmount = (int)($expense['exp_total'] ?? 0);
    $approvedPaid = (int)($paidStmt->fetchColumn() ?: 0);
    $remainingAmount = max(0, $totalAmount - $approvedPaid);

    if ($remainingAmount <= 0) {
        return [
            'success' => true,
            'status' => 'skipped',
            'expense_id' => $expenseId,
            'reason' => 'no_remaining_amount',
            'channel' => 'none',
        ];
    }

    $settings = prGetSystemSettings($pdo);
    $paymentDueDay = isset($options['payment_due_day'])
        ? (int)$options['payment_due_day']
        : (int)($settings['payment_due_day'] ?? 5);
    $paymentDueDay = max(1, min(28, $paymentDueDay));

    $tenantName = trim((string)($expense['tnt_name'] ?? 'ผู้เช่า'));
    $roomNumber = trim((string)($expense['room_number'] ?? '-'));
    $tenantId = trim((string)($expense['tnt_id'] ?? ''));
    $tenantPhoneRaw = trim((string)($expense['tnt_phone'] ?? ''));
    $tenantPhoneDigits = prNormalizePhoneDigits($tenantPhoneRaw);
    $tenantPhoneIntl = prNormalizePhoneForIntl($tenantPhoneRaw);
    $tenantEmail = prFetchTenantEmail($pdo, $tenantId);

    $expenseMonth = (string)($expense['exp_month'] ?? '');
    $expenseMonthDisplay = prFormatThaiMonthYear($expenseMonth);
    $dueDateIso = prBuildDueDateIso($expenseMonth, $paymentDueDay);
    $dueDateDisplay = $dueDateIso !== '' ? prFormatThaiDate($dueDateIso) : '-';

    $bankName = trim((string)($settings['bank_name'] ?? ''));
    $bankAccountNumber = trim((string)($settings['bank_account_number'] ?? ''));

    $senderName = trim((string)($options['sender_name'] ?? (getenv('PAYMENT_REMINDER_SENDER_NAME') ?: 'ผู้ดูแลหอพัก')));

    $includePortalUrl = false;
    if (array_key_exists('include_portal_url', $options)) {
        $includePortalUrl = (bool)$options['include_portal_url'];
    } else {
        $includePortalUrl = ((string)(getenv('PAYMENT_REMINDER_INCLUDE_PORTAL_URL') ?: '0')) === '1';
    }

    $tenantPortalUrl = '';
    if ($includePortalUrl && !empty($expense['access_token']) && function_exists('getTenantPortalUrl')) {
        $tenantPortalUrl = getTenantPortalUrl((string)$expense['access_token']);
    }

    $messageLines = [
        'แจ้งเตือนชำระค่าใช้จ่ายหอพัก',
        'ชื่อผู้เช่า: ' . ($tenantName !== '' ? $tenantName : '-'),
        'ห้อง: ' . $roomNumber,
        'เดือนที่เรียกเก็บ: ' . $expenseMonthDisplay,
        'ยอดคงค้าง: ' . prFormatBaht($remainingAmount),
    ];

    if ($dueDateDisplay !== '-') {
        $messageLines[] = 'ครบกำหนดชำระ: ' . $dueDateDisplay;
    }

    if ($bankName !== '' || $bankAccountNumber !== '') {
        $bankLine = 'บัญชีรับชำระ: ';
        if ($bankName !== '') {
            $bankLine .= $bankName;
        }
        if ($bankAccountNumber !== '') {
            $bankLine .= ($bankName !== '' ? ' ' : '') . $bankAccountNumber;
        }
        $messageLines[] = $bankLine;
    }

    if ($tenantPortalUrl !== '') {
        $messageLines[] = 'ลิงก์ผู้เช่า: ' . $tenantPortalUrl;
    }

    $messageLines[] = 'ผู้ส่ง: ' . $senderName;

    $messageBody = implode("\n", $messageLines);

    $payload = [
        'event' => 'payment_reminder',
        'expense_id' => (int)$expense['exp_id'],
        'tenant' => [
            'id' => $tenantId,
            'name' => $tenantName,
            'phone_raw' => $tenantPhoneRaw,
            'phone_digits' => $tenantPhoneDigits,
            'phone_international' => $tenantPhoneIntl,
            'email' => $tenantEmail,
        ],
        'contract' => [
            'id' => (int)$expense['ctr_id'],
            'room_number' => $roomNumber,
        ],
        'billing' => [
            'month' => $expenseMonth,
            'month_display' => $expenseMonthDisplay,
            'due_date' => $dueDateIso,
            'due_date_display' => $dueDateDisplay,
            'total_amount' => $totalAmount,
            'approved_paid_amount' => $approvedPaid,
            'remaining_amount' => $remainingAmount,
            'status' => $rawStatus,
        ],
        'bank' => [
            'name' => $bankName,
            'account_number' => $bankAccountNumber,
        ],
        'message' => $messageBody,
        'meta' => [
            'requested_by' => (string)($options['requested_by'] ?? ''),
            'source' => (string)($options['source'] ?? 'manual'),
            'request_ip' => (string)($options['request_ip'] ?? ''),
            'sent_at' => date('c'),
        ],
    ];

    $webhookUrl = trim((string)(getenv('PAYMENT_REMINDER_WEBHOOK_URL') ?: ''));
    $webhookToken = trim((string)(getenv('PAYMENT_REMINDER_WEBHOOK_TOKEN') ?: ''));
    $dryRun = !empty($options['dry_run']);

    $dispatchStatus = 'queued';
    $dispatchChannel = 'log';
    $dispatchError = '';
    $dispatchHttpCode = 0;
    $dispatchResponse = '';

    if ($dryRun) {
        $dispatchStatus = 'queued';
        $dispatchChannel = 'dry-run';
    } elseif ($webhookUrl === '') {
        $dispatchStatus = 'queued';
        $dispatchChannel = 'log';
        $dispatchError = 'webhook_not_configured';
    } else {
        $sendResult = prSendWebhook($payload, $webhookUrl, $webhookToken);
        $dispatchHttpCode = (int)($sendResult['http_code'] ?? 0);
        $dispatchResponse = (string)($sendResult['response'] ?? '');

        if (!empty($sendResult['success'])) {
            $dispatchStatus = 'sent';
            $dispatchChannel = 'webhook';
        } else {
            $dispatchStatus = 'failed';
            $dispatchChannel = 'webhook';
            $dispatchError = (string)($sendResult['error'] ?? 'webhook_send_failed');
        }
    }

    prAppendReminderLog([
        'timestamp' => date('c'),
        'expense_id' => $expenseId,
        'status' => $dispatchStatus,
        'channel' => $dispatchChannel,
        'tenant_id' => $tenantId,
        'tenant_name' => $tenantName,
        'room_number' => $roomNumber,
        'tenant_phone' => $tenantPhoneRaw,
        'tenant_email' => $tenantEmail,
        'remaining_amount' => $remainingAmount,
        'due_date' => $dueDateIso,
        'requested_by' => (string)($options['requested_by'] ?? ''),
        'source' => (string)($options['source'] ?? 'manual'),
        'request_ip' => (string)($options['request_ip'] ?? ''),
        'http_code' => $dispatchHttpCode,
        'error' => $dispatchError,
        'response_preview' => $dispatchResponse,
    ]);

    return [
        'success' => $dispatchStatus !== 'failed',
        'status' => $dispatchStatus,
        'expense_id' => $expenseId,
        'channel' => $dispatchChannel,
        'tenant_name' => $tenantName,
        'room_number' => $roomNumber,
        'remaining_amount' => $remainingAmount,
        'due_date' => $dueDateIso,
        'message' => $messageBody,
        'error' => $dispatchError,
        'http_code' => $dispatchHttpCode,
    ];
}
