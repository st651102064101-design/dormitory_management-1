<?php
declare(strict_types=1);

header('Content-Type: application/json');
session_start();

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

/**
 * Build one expense + payment summary payload.
 */
function buildExpensePayload(PDO $pdo, array $expense): array
{
    $expenseId = (int)($expense['exp_id'] ?? 0);
    $expenseTotal = (float)($expense['exp_total'] ?? 0);
    $expenseStatus = (string)($expense['exp_status'] ?? '0');

    $statusTextMap = [
        '0' => 'รอชำระ',
        '1' => 'ชำระแล้ว',
        '2' => 'รอตรวจสอบ',
        '3' => 'ชำระยังไม่ครบ',
        '4' => 'ค้างชำระ',
    ];

    $paymentStmt = $pdo->prepare(" 
        SELECT pay_id, pay_date, pay_amount, pay_status, pay_remark, pay_proof
        FROM payment
        WHERE exp_id = :exp_id
          AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'
        ORDER BY pay_date ASC, pay_id ASC
    ");
    $paymentStmt->execute([':exp_id' => $expenseId]);

    $payments = [];
    $approvedAmount = 0.0;
    $pendingAmount = 0.0;

    while ($pay = $paymentStmt->fetch(PDO::FETCH_ASSOC)) {
        $payAmount = (float)($pay['pay_amount'] ?? 0);
        $payStatus = (string)($pay['pay_status'] ?? '0');

        if ($payStatus === '1') {
            $approvedAmount += $payAmount;
        } else {
            $pendingAmount += $payAmount;
        }

        $payDate = (string)($pay['pay_date'] ?? '');
        $payDateDisplay = '-';
        if ($payDate !== '' && strtotime($payDate) !== false) {
            $payDateDisplay = date('d/m/Y', strtotime($payDate));
        }

        $payments[] = [
            'pay_id' => (int)($pay['pay_id'] ?? 0),
            'pay_date' => $payDate,
            'pay_date_display' => $payDateDisplay,
            'pay_amount' => $payAmount,
            'pay_status' => $payStatus,
            'pay_remark' => (string)($pay['pay_remark'] ?? ''),
            'pay_proof' => (string)($pay['pay_proof'] ?? ''),
        ];
    }

    return [
        'has_expense' => true,
        'expense_id' => $expenseId,
        'bill_month' => (string)($expense['exp_month'] ?? ''),
        'expense_total' => $expenseTotal,
        'expense_status' => $expenseStatus,
        'expense_status_text' => $statusTextMap[$expenseStatus] ?? 'ไม่ทราบสถานะ',
        'approved_amount' => $approvedAmount,
        'pending_amount' => $pendingAmount,
        'payments' => $payments,
    ];
}

try {
    $ctrId = isset($_GET['ctr_id']) ? (int)$_GET['ctr_id'] : 0;
    if ($ctrId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ctr_id']);
        exit;
    }

    $pdo = connectDB();

    $contractStmt = $pdo->prepare('SELECT ctr_start FROM contract WHERE ctr_id = ? LIMIT 1');
    $contractStmt->execute([$ctrId]);
    $contract = $contractStmt->fetch(PDO::FETCH_ASSOC);

    $ctrStart = $contract['ctr_start'] ?? null;
    $expectedFirstMonth = null;
    if (!empty($ctrStart) && strtotime((string)$ctrStart) !== false) {
        $expectedFirstMonth = date('Y-m-01', strtotime('first day of next month', strtotime((string)$ctrStart)));
    }

    $expenseSql = "
        SELECT e.exp_id, e.exp_month, e.exp_total, e.exp_status
        FROM expense e
        WHERE e.ctr_id = :ctr_id
    ";

    if ($ctrStart) {
        $expenseSql .= " AND DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(:ctr_start, '%Y-%m')";
    }

    $expenseSql .= " ORDER BY e.exp_month ASC, e.exp_id DESC LIMIT 1";

    $expenseStmt = $pdo->prepare($expenseSql);
    $expenseStmt->bindValue(':ctr_id', $ctrId, PDO::PARAM_INT);
    if ($ctrStart) {
        $expenseStmt->bindValue(':ctr_start', (string)$ctrStart, PDO::PARAM_STR);
    }
    $expenseStmt->execute();

    $firstExpense = $expenseStmt->fetch(PDO::FETCH_ASSOC);

    $latestExpenseStmt = $pdo->prepare("
        SELECT e.exp_id, e.exp_month, e.exp_total, e.exp_status
        FROM expense e
        WHERE e.ctr_id = :ctr_id
          AND (
            :ctr_start_null IS NULL
            OR DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(:ctr_start_cmp, '%Y-%m')
          )
          AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
        ORDER BY e.exp_month DESC, e.exp_id DESC
        LIMIT 1
    ");
    $latestExpenseStmt->bindValue(':ctr_id', $ctrId, PDO::PARAM_INT);
    if ($ctrStart) {
        $latestExpenseStmt->bindValue(':ctr_start_null', (string)$ctrStart, PDO::PARAM_STR);
        $latestExpenseStmt->bindValue(':ctr_start_cmp', (string)$ctrStart, PDO::PARAM_STR);
    } else {
        $latestExpenseStmt->bindValue(':ctr_start_null', null, PDO::PARAM_NULL);
        $latestExpenseStmt->bindValue(':ctr_start_cmp', null, PDO::PARAM_NULL);
    }
    $latestExpenseStmt->execute();
    $latestExpense = $latestExpenseStmt->fetch(PDO::FETCH_ASSOC);

    if (!$latestExpense) {
        $latestAnyStmt = $pdo->prepare("
            SELECT e.exp_id, e.exp_month, e.exp_total, e.exp_status
            FROM expense e
            WHERE e.ctr_id = :ctr_id
              AND (
                :ctr_start_null IS NULL
                OR DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(:ctr_start_cmp, '%Y-%m')
              )
            ORDER BY e.exp_month DESC, e.exp_id DESC
            LIMIT 1
        ");
        $latestAnyStmt->bindValue(':ctr_id', $ctrId, PDO::PARAM_INT);
        if ($ctrStart) {
            $latestAnyStmt->bindValue(':ctr_start_null', (string)$ctrStart, PDO::PARAM_STR);
            $latestAnyStmt->bindValue(':ctr_start_cmp', (string)$ctrStart, PDO::PARAM_STR);
        } else {
            $latestAnyStmt->bindValue(':ctr_start_null', null, PDO::PARAM_NULL);
            $latestAnyStmt->bindValue(':ctr_start_cmp', null, PDO::PARAM_NULL);
        }
        $latestAnyStmt->execute();
        $latestExpense = $latestAnyStmt->fetch(PDO::FETCH_ASSOC);
    }

    $response = [
        'ctr_id' => $ctrId,
        'today_month' => date('Y-m-01'),
        'first_bill' => [
            'has_expense' => false,
            'bill_month' => $expectedFirstMonth,
            'expense_id' => null,
            'expense_total' => 0,
            'expense_status' => '0',
            'expense_status_text' => 'รอชำระ',
            'approved_amount' => 0,
            'pending_amount' => 0,
            'payments' => [],
        ],
        'latest_bill' => [
            'has_expense' => false,
            'bill_month' => null,
            'expense_id' => null,
            'expense_total' => 0,
            'expense_status' => '0',
            'expense_status_text' => 'รอชำระ',
            'approved_amount' => 0,
            'pending_amount' => 0,
            'payments' => [],
        ],
    ];

    if ($firstExpense) {
        $response['first_bill'] = buildExpensePayload($pdo, $firstExpense);
    }

    if ($latestExpense) {
        $response['latest_bill'] = buildExpensePayload($pdo, $latestExpense);
    }

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
    ]);
}
