<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

$ctrId = isset($_GET['ctr_id']) ? (int)$_GET['ctr_id'] : 0;
if ($ctrId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ctr_id']);
    exit;
}

try {
    $pdo = connectDB();

    // 1. Contract + Tenant + Room + RoomType
    $ctrStmt = $pdo->prepare("
        SELECT
            c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_status,
            COALESCE(c.contract_pdf_path, '') AS contract_pdf_path,
            t.tnt_id, t.tnt_name, t.tnt_phone,
            COALESCE(t.tnt_age, '') AS tnt_age,
            COALESCE(t.tnt_address, '') AS tnt_address,
            COALESCE(t.tnt_education, '') AS tnt_education,
            COALESCE(t.tnt_faculty, '') AS tnt_faculty,
            COALESCE(t.tnt_year, '') AS tnt_year,
            COALESCE(t.tnt_vehicle, '') AS tnt_vehicle,
            COALESCE(t.tnt_parent, '') AS tnt_parent,
            COALESCE(t.tnt_parentsphone, '') AS tnt_parentsphone,
            t.tnt_status,
            r.room_id, r.room_number,
            rt.type_name, rt.type_price
        FROM contract c
        LEFT JOIN tenant t  ON c.tnt_id  = t.tnt_id
        LEFT JOIN room r    ON c.room_id  = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE c.ctr_id = ?
        LIMIT 1
    ");
    $ctrStmt->execute([$ctrId]);
    $contract = $ctrStmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        http_response_code(404);
        echo json_encode(['error' => 'Contract not found']);
        exit;
    }

    // 2. Checkin record (most recent)
    $ciStmt = $pdo->prepare("
        SELECT checkin_id, checkin_date, water_meter_start, elec_meter_start
        FROM checkin_record
        WHERE ctr_id = ?
        ORDER BY checkin_id DESC
        LIMIT 1
    ");
    $ciStmt->execute([$ctrId]);
    $checkin = $ciStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // 3. Booking deposit (via tenant_workflow → booking_payment)
    $depStmt = $pdo->prepare("
        SELECT bp.bp_id, bp.bp_amount, bp.bp_status,
               COALESCE(bp.bp_proof, '') AS bp_proof,
               bp.bp_payment_date
        FROM booking_payment bp
        INNER JOIN tenant_workflow tw ON tw.bkg_id = bp.bkg_id
        WHERE tw.ctr_id = ?
        ORDER BY bp.bp_id DESC
        LIMIT 1
    ");
    $depStmt->execute([$ctrId]);
    $deposit = $depStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // 4. All expense records for this contract
    $expStmt = $pdo->prepare("
        SELECT exp_id, exp_month, exp_total, exp_status,
               room_price, exp_elec_chg, exp_water,
               exp_elec_unit, exp_water_unit,
               rate_elec, rate_water
        FROM expense
        WHERE ctr_id = ?
        ORDER BY exp_month ASC, exp_id ASC
    ");
    $expStmt->execute([$ctrId]);
    $expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. All payments grouped by exp_id
    $paysByExp = [];
    if (!empty($expenses)) {
        $expIds = array_column($expenses, 'exp_id');
        $ph = implode(',', array_fill(0, count($expIds), '?'));
        $payStmt = $pdo->prepare("
            SELECT pay_id, exp_id, pay_date, pay_amount,
                   pay_status, pay_remark,
                   COALESCE(pay_proof, '') AS pay_proof
            FROM payment
            WHERE exp_id IN ($ph)
            ORDER BY pay_date ASC, pay_id ASC
        ");
        $payStmt->execute($expIds);
        while ($p = $payStmt->fetch(PDO::FETCH_ASSOC)) {
            $paysByExp[(int)$p['exp_id']][] = $p;
        }
    }
    foreach ($expenses as &$exp) {
        $exp['payments'] = $paysByExp[(int)$exp['exp_id']] ?? [];
    }
    unset($exp);

    // 6. Utility meter readings
    $utilStmt = $pdo->prepare("
        SELECT utl_id, utl_date,
               utl_water_start, utl_water_end,
               utl_elec_start, utl_elec_end
        FROM utility
        WHERE ctr_id = ?
        ORDER BY utl_date ASC, utl_id ASC
    ");
    $utilStmt->execute([$ctrId]);
    $utility = $utilStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Deposit refund record
    $refundStmt = $pdo->prepare("
        SELECT refund_id, deposit_amount, deduction_amount, deduction_reason,
               refund_amount, refund_status, refund_proof, refund_date, created_at
        FROM deposit_refund
        WHERE ctr_id = ?
        ORDER BY refund_id DESC LIMIT 1
    ");
    $refundStmt->execute([$ctrId]);
    $refund = $refundStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // 8. Payment completion summary
    $unpaidStmt = $pdo->prepare("
        SELECT COUNT(*) AS unpaid_count,
               COALESCE(SUM(e.exp_total - COALESCE((
                   SELECT SUM(p.pay_amount) FROM payment p
                   WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                     AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
               ), 0)), 0) AS total_outstanding
        FROM expense e
        WHERE e.ctr_id = ?
          AND e.exp_total > COALESCE((
              SELECT SUM(p2.pay_amount) FROM payment p2
              WHERE p2.exp_id = e.exp_id AND p2.pay_status = '1'
                AND TRIM(COALESCE(p2.pay_remark, '')) <> 'มัดจำ'
          ), 0)
    ");
    $unpaidStmt->execute([$ctrId]);
    $paymentSummary = $unpaidStmt->fetch(PDO::FETCH_ASSOC) ?: ['unpaid_count' => 0, 'total_outstanding' => 0];

    // 9. Termination bank account info (migrate columns if not yet created)
    try {
        $pdo->exec("ALTER TABLE termination ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) DEFAULT NULL AFTER term_date");
        $pdo->exec("ALTER TABLE termination ADD COLUMN IF NOT EXISTS bank_account_name VARCHAR(100) DEFAULT NULL AFTER bank_name");
        $pdo->exec("ALTER TABLE termination ADD COLUMN IF NOT EXISTS bank_account_number VARCHAR(20) DEFAULT NULL AFTER bank_account_name");
    } catch (Throwable $ignored) {}

    $terminationInfo = null;
    try {
        $termBankStmt = $pdo->prepare("
            SELECT term_date, bank_name, bank_account_name, bank_account_number
            FROM termination
            WHERE ctr_id = ?
            ORDER BY term_id DESC LIMIT 1
        ");
        $termBankStmt->execute([$ctrId]);
        $terminationInfo = $termBankStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $ignored) {}

    echo json_encode([
        'contract'       => $contract,
        'checkin'        => $checkin,
        'deposit'        => $deposit,
        'expenses'       => $expenses,
        'utility'        => $utility,
        'refund'         => $refund,
        'paymentSummary' => $paymentSummary,
        'termination'    => $terminationInfo,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
