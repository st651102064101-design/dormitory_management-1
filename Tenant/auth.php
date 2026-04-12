<?php
/**
 * Tenant Auth Helper - ตรวจสอบสิทธิ์การเข้าถึง
 */
declare(strict_types=1);

function getTenantTokenCookiePath(): string {
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($scriptName), '/');

    if ($dir === '' || $dir === '.') {
        return '/';
    }

    return $dir;
}

function persistTenantPortalToken(string $token): void {
    if ($token === '' || headers_sent()) {
        return;
    }

    setcookie('tenant_portal_token', $token, [
        'expires' => time() + (180 * 24 * 60 * 60),
        'path' => getTenantTokenCookiePath(),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearTenantPortalToken(): void {
    if (headers_sent()) {
        return;
    }

    setcookie('tenant_portal_token', '', [
        'expires' => time() - 3600,
        'path' => getTenantTokenCookiePath(),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function checkTenantAuth(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();
    
    // รับ token จาก URL, session หรือ cookie สำรอง (กรณี session หมดอายุ)
    $token = trim((string)($_GET['token'] ?? $_SESSION['tenant_token'] ?? $_COOKIE['tenant_portal_token'] ?? ''));

    if (isset($_GET['token']) && $token !== '') {
        persistTenantPortalToken($token);
    }
    
    if (empty($token)) {
        header('Location: ../index.php');
        exit;
    }
    
    // ตรวจสอบ token
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                 t.tnt_id, t.tnt_idcard, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age,
                   r.room_id, r.room_number, r.room_image,
                   rt.type_name, rt.type_price
            FROM contract c
            JOIN tenant t ON c.tnt_id = t.tnt_id
            JOIN room r ON c.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            WHERE c.access_token = ? AND c.ctr_status IN ('0', '1', '2')
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract) {
            clearTenantPortalToken();
            header('Location: ../index.php');
            exit;
        }

        // ถ้าสัญญาที่ตรงกับ token ไม่ใช่สัญญาที่ active อยู่ (เช่น แจ้งยกเลิก/ยกเลิกแล้ว)
        // ให้ใช้สัญญา active ล่าสุดของผู้เช่าคนเดียวกันในห้องเดียวกันแทน
        if ($contract['ctr_status'] !== '0') {
            $activeStmt = $pdo->prepare("
                SELECT c.*,
                      t.tnt_id, t.tnt_idcard, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age,
                       r.room_id, r.room_number, r.room_image,
                       rt.type_name, rt.type_price
                FROM contract c
                JOIN tenant t ON c.tnt_id = t.tnt_id
                JOIN room r ON c.room_id = r.room_id
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                WHERE c.tnt_id = ? AND c.room_id = ? AND c.ctr_status = '0'
                ORDER BY c.ctr_id DESC
                LIMIT 1
            ");
            $activeStmt->execute([$contract['tnt_id'], $contract['room_id']]);
            $activeContract = $activeStmt->fetch(PDO::FETCH_ASSOC);
            if ($activeContract) {
                $contract = $activeContract;
            }
        }
        
        $resolvedToken = !empty($contract['access_token']) ? (string)$contract['access_token'] : $token;

        // อัพเดท session
        $_SESSION['tenant_token'] = $resolvedToken;
        $_SESSION['tenant_ctr_id'] = $contract['ctr_id'];
        $_SESSION['tenant_tnt_id'] = $contract['tnt_id'];
        $_SESSION['tenant_room_id'] = $contract['room_id'];
        $_SESSION['tenant_room_number'] = $contract['room_number'];
        $_SESSION['tenant_name'] = $contract['tnt_name'];
        persistTenantPortalToken($resolvedToken);
        
        return [
            'pdo' => $pdo,
            'token' => $resolvedToken,
            'contract' => $contract
        ];
        
    } catch (PDOException $e) {
        header('Location: ../index.php');
        exit;
    }
}

function getSystemSettings(PDO $pdo): array {
    $settings = [
        'site_name' => 'Sangthian Dormitory',
        'logo_filename' => 'Logo.jpg',
        'bank_name' => '',
        'bank_account_name' => '',
        'bank_account_number' => '',
        'promptpay_number' => '',
        'public_theme' => 'dark',
        'openweathermap_api_key' => '',
        'openweathermap_city' => '',
        'google_maps_embed' => ''
    ];
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'bank_name', 'bank_account_name', 'bank_account_number', 'promptpay_number', 'public_theme', 'openweathermap_api_key', 'openweathermap_city', 'google_maps_embed')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    
    return $settings;
}

function getTenantBillBadgeCount(PDO $pdo, array $contract): int {
    $ctrId = (int)($contract['ctr_id'] ?? 0);
    if ($ctrId <= 0) {
        return 0;
    }

    $contractStart = (string)($contract['ctr_start'] ?? date('Y-m-d'));
    $ctrDeposit = (float)($contract['ctr_deposit'] ?? 2000);

    try {
        $stmt = $pdo->prepare(
            "
            SELECT e.exp_id,
                   e.exp_total,
                   e.exp_month,
                   e.room_price,
                   e.exp_elec_chg,
                   e.exp_water,
                   (SELECT COALESCE(SUM(p.pay_amount), 0)
                    FROM payment p
                    WHERE p.exp_id = e.exp_id
                      AND p.pay_status = '1'
                      AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ') AS paid_amount,
                   (SELECT COALESCE(SUM(p.pay_amount), 0)
                    FROM payment p
                    WHERE p.exp_id = e.exp_id
                      AND p.pay_status = '0'
                      AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ') AS pending_amount,
                   (SELECT COUNT(*)
                    FROM payment p
                    WHERE p.exp_id = e.exp_id
                      AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ') AS deposit_payment_count,
                   (SELECT COALESCE(SUM(p.pay_amount), 0)
                    FROM payment p
                    WHERE p.exp_id = e.exp_id
                      AND p.pay_status = '1'
                      AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ') AS deposit_paid_amount,
                   (SELECT COALESCE(SUM(p.pay_amount), 0)
                    FROM payment p
                    WHERE p.exp_id = e.exp_id
                      AND p.pay_status = '0'
                      AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ') AS deposit_pending_amount
            FROM expense e
            INNER JOIN (
                SELECT MAX(exp_id) AS exp_id
                FROM expense
                WHERE ctr_id = ?
                  AND DATE_FORMAT(exp_month, '%Y-%m') >= DATE_FORMAT(?, '%Y-%m')
                  AND DATE_FORMAT(exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
                GROUP BY DATE_FORMAT(exp_month, '%Y-%m')
            ) latest ON e.exp_id = latest.exp_id
            WHERE e.ctr_id = ?
              AND EXISTS (
                    SELECT 1
                    FROM utility u
                    WHERE u.ctr_id = e.ctr_id
                      AND YEAR(u.utl_date) = YEAR(e.exp_month)
                      AND MONTH(u.utl_date) = MONTH(e.exp_month)
                      AND u.utl_water_end IS NOT NULL
                      AND u.utl_elec_end IS NOT NULL
              )
            ORDER BY e.exp_month DESC, e.exp_id DESC
            "
        );
        $stmt->execute([$ctrId, $contractStart, $ctrId]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $billCount = 0;
        $expenseCount = count($expenses);
        foreach ($expenses as $expIndex => $exp) {
            $paidAmount = (float)($exp['paid_amount'] ?? 0);
            $pendingAmount = (float)($exp['pending_amount'] ?? 0);
            $depositPaidAmount = (float)($exp['deposit_paid_amount'] ?? 0);
            $depositPendingAmount = (float)($exp['deposit_pending_amount'] ?? 0);
            $expTotal = (float)($exp['exp_total'] ?? 0);

            $roomPrice = (float)($exp['room_price'] ?? 0);
            $elecChg = (float)($exp['exp_elec_chg'] ?? 0);
            $waterChg = (float)($exp['exp_water'] ?? 0);
            $calculatedTotal = $roomPrice + $elecChg + $waterChg;
            $depositPaymentCount = (int)($exp['deposit_payment_count'] ?? 0);

            $isDepositOnly = ($depositPaymentCount > 0)
                || ($expIndex === $expenseCount - 1
                    && $expTotal == $ctrDeposit
                    && $elecChg == 0
                    && $waterChg == 0
                    && $roomPrice > 0
                    && $expTotal != $calculatedTotal);

            if ($isDepositOnly) {
                $paidAmount += $depositPaidAmount;
                $pendingAmount += $depositPendingAmount;
            }

            $actualRemaining = max(0, $expTotal - $paidAmount - $pendingAmount);
            if ($actualRemaining > 0) {
                $billCount++;
            }
        }

        return max(0, $billCount);
    } catch (Throwable $e) {
        error_log('Exception calculating tenant bill badge count in ' . __FILE__ . ': ' . $e->getMessage());
    }

    return 0;
}
