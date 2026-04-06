<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
$sessionCsrfToken = (string)($_SESSION['csrf_token'] ?? '');
if ($csrfToken === '' || $sessionCsrfToken === '' || !hash_equals($sessionCsrfToken, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$expenseIdsRaw = $_POST['expense_ids'] ?? '';
if (!is_string($expenseIdsRaw) || trim($expenseIdsRaw) === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ไม่พบรายการค่าใช้จ่ายที่ต้องการแจ้งเตือน'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $decoded = json_decode($expenseIdsRaw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'รูปแบบข้อมูลรายการค่าใช้จ่ายไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_array($decoded) || empty($decoded)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ไม่พบรายการค่าใช้จ่ายที่ต้องการแจ้งเตือน'], JSON_UNESCAPED_UNICODE);
    exit;
}

$expenseIds = [];
foreach ($decoded as $id) {
    $expenseId = (int)$id;
    if ($expenseId > 0) {
        $expenseIds[$expenseId] = true;
    }
}
$expenseIds = array_keys($expenseIds);

if (empty($expenseIds)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ไม่มีรหัสรายการค่าใช้จ่ายที่ใช้งานได้'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (count($expenseIds) > 200) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'จำนวนรายการต่อครั้งเกินกำหนด (สูงสุด 200 รายการ)'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    require_once __DIR__ . '/../includes/payment_reminder_service.php';
    $pdo = connectDB();

    $dueDayStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_due_day' LIMIT 1");
    $dueDayStmt->execute();
    $paymentDueDay = max(1, min(28, (int)($dueDayStmt->fetchColumn() ?: 5)));

    $summary = [
        'sent' => 0,
        'queued' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    $results = [];
    foreach ($expenseIds as $expenseId) {
        $result = dispatchPaymentReminder($pdo, (int)$expenseId, [
            'payment_due_day' => $paymentDueDay,
            'requested_by' => (string)($_SESSION['admin_username'] ?? ''),
            'source' => 'manage_expenses_batch',
            'request_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        $status = (string)($result['status'] ?? 'failed');
        if (!array_key_exists($status, $summary)) {
            $status = 'failed';
        }
        $summary[$status]++;

        $results[] = [
            'expense_id' => (int)($result['expense_id'] ?? $expenseId),
            'status' => $status,
            'channel' => (string)($result['channel'] ?? 'none'),
            'tenant_name' => (string)($result['tenant_name'] ?? ''),
            'room_number' => (string)($result['room_number'] ?? ''),
            'remaining_amount' => (int)($result['remaining_amount'] ?? 0),
            'error' => (string)($result['error'] ?? ''),
        ];
    }

    $messageParts = [];
    if ($summary['sent'] > 0) {
        $messageParts[] = 'ส่งสำเร็จ ' . number_format($summary['sent']) . ' รายการ';
    }
    if ($summary['queued'] > 0) {
        $messageParts[] = 'บันทึกคิว ' . number_format($summary['queued']) . ' รายการ';
    }
    if ($summary['skipped'] > 0) {
        $messageParts[] = 'ข้าม ' . number_format($summary['skipped']) . ' รายการ';
    }
    if ($summary['failed'] > 0) {
        $messageParts[] = 'ล้มเหลว ' . number_format($summary['failed']) . ' รายการ';
    }

    $message = implode(' | ', $messageParts);
    if ($message === '') {
        $message = 'ไม่พบรายการที่สามารถแจ้งเตือนได้';
    }

    $allFailed = ($summary['sent'] + $summary['queued'] + $summary['skipped']) === 0 && $summary['failed'] > 0;

    echo json_encode([
        'success' => !$allFailed,
        'message' => $message,
        'summary' => $summary,
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[send_payment_reminders] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาดในการส่งแจ้งเตือน: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
