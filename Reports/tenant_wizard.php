<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';

// Initialize database connection
$conn = connectDB();

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ดึง theme color จากการตั้งค่าระบบ
$settingsStmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a'; // ค่า default (dark mode)
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}

// ดึงข้อมูลผู้เช่าที่อยู่ในกระบวนการ Wizard
try {
    // Check if completion filter is applied
    $completedFilter = isset($_GET['completed']) ? (int)$_GET['completed'] : 0;
    $selectedBkgId = isset($_GET['bkg_id']) ? (int)$_GET['bkg_id'] : 0;
    $bookingFilterCondition = $selectedBkgId > 0 ? " AND b.bkg_id = {$selectedBkgId} " : '';

        $firstBillPaidCondition = "
                EXISTS (
                        SELECT 1
                        FROM expense e_first
                        WHERE e_first.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                            AND (
                                c.ctr_start IS NULL
                                OR DATE_FORMAT(e_first.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                            )
                            AND e_first.exp_month = (
                                SELECT MIN(e_min.exp_month)
                                FROM expense e_min
                                WHERE e_min.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND (
                                        c.ctr_start IS NULL
                                        OR DATE_FORMAT(e_min.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                            )
                            AND e_first.exp_total > 0
                            AND COALESCE((
                                SELECT SUM(p.pay_amount)
                                FROM payment p
                                WHERE p.exp_id = e_first.exp_id
                                  AND p.pay_status = '1'
                                  AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                            ), 0) >= e_first.exp_total - 0.00001
                )
        ";

    $meterRecordedCondition = "
        EXISTS (
            SELECT 1
            FROM utility u_meter
            WHERE u_meter.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
              AND u_meter.utl_water_end IS NOT NULL
        )
    ";

    // เช็คว่าบิลล่าสุดชำระแล้ว — ต้องผ่านทั้ง firstBill และ latestBill จึงจะ "ครบ 5 ขั้นตอน"
    $latestBillPaidCondition = "
        EXISTS (
            SELECT 1
            FROM expense e_latest
            WHERE e_latest.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                AND e_latest.exp_month = (
                    SELECT MAX(e_max.exp_month)
                    FROM expense e_max
                    WHERE e_max.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                )
                AND e_latest.exp_total > 0
                AND COALESCE((
                    SELECT SUM(p.pay_amount)
                    FROM payment p
                    WHERE p.exp_id = e_latest.exp_id
                      AND p.pay_status = '1'
                      AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                ), 0) >= e_latest.exp_total - 0.00001
        )
    ";

    $completionCondition = '';
    if ($completedFilter === 1) {
        $completionCondition = "
            AND COALESCE(tw.step_5_confirmed, 0) = 1
            AND cr.checkin_date IS NOT NULL
            AND cr.checkin_date <> '0000-00-00'
            AND $firstBillPaidCondition
            AND $latestBillPaidCondition
            AND $meterRecordedCondition
        ";
    } else {
        $completionCondition = "
            AND (
                COALESCE(tw.step_5_confirmed, 0) = 0
                OR cr.checkin_date IS NULL
                OR cr.checkin_date = '0000-00-00'
                OR NOT ($firstBillPaidCondition)
                OR NOT ($latestBillPaidCondition)
                OR NOT ($meterRecordedCondition)
            )
        ";
    }
    
    $sql = "
        SELECT
            t.tnt_id,
            t.tnt_name,
            t.tnt_phone,
            t.tnt_status,
            b.bkg_id,
            b.bkg_date,
            b.bkg_checkin_date,
            b.bkg_status,
            r.room_id,
            r.room_number,
            rt.type_name,
            rt.type_price,
            COALESCE(c.ctr_id, tw.ctr_id) as ctr_id,
            c.ctr_start,
            c.ctr_end,
            c.ctr_status,
            tw.id as workflow_id,
            tw.ctr_id as workflow_ctr_id,
            tw.current_step,
            COALESCE(tw.step_1_confirmed, 0) as step_1_confirmed,
            tw.step_1_date,
            COALESCE(tw.step_2_confirmed, 0) as step_2_confirmed,
            tw.step_2_date,
            COALESCE(tw.step_3_confirmed, 0) as step_3_confirmed,
            tw.step_3_date,
            COALESCE(tw.step_4_confirmed, 0) as step_4_confirmed,
            tw.step_4_date,
            COALESCE(tw.step_5_confirmed, 0) as step_5_confirmed,
            tw.step_5_date,
            tw.completed,
            bp.bp_status AS booking_payment_status,
            bp.bp_receipt_no,
            bp.bp_id,
            bp.bp_amount,
            bp.bp_proof,
            cr.checkin_id,
            cr.checkin_date,
            cr.water_meter_start,
            cr.elec_meter_start,
                        (
                                SELECT e.exp_month
                                FROM expense e
                                WHERE e.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND (
                                        c.ctr_start IS NULL
                                        OR DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                                ORDER BY e.exp_month ASC, e.exp_id DESC
                                LIMIT 1
                        ) AS first_exp_month,
                        (
                                SELECT
                                    CASE
                                        WHEN e.exp_total > 0
                                             AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) >= e.exp_total - 0.00001
                                            THEN '1'
                                        WHEN COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) > 0
                                            THEN '3'
                                        WHEN COALESCE((SELECT COUNT(*) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '0' AND p.pay_proof IS NOT NULL AND p.pay_proof <> '' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) > 0
                                            THEN '2'
                                        WHEN e.exp_status = '4' THEN '4'
                                        ELSE '0'
                                    END
                                FROM expense e
                                WHERE e.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND (
                                        c.ctr_start IS NULL
                                        OR DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                                ORDER BY e.exp_month ASC, e.exp_id DESC
                                LIMIT 1
                        ) AS first_exp_status,
                        (
                                SELECT
                                    CASE
                                        WHEN e.exp_total > 0
                                             AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) >= e.exp_total - 0.00001
                                            THEN '1'
                                        WHEN COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) > 0
                                            THEN '3'
                                        WHEN COALESCE((SELECT COUNT(*) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '0' AND p.pay_proof IS NOT NULL AND p.pay_proof <> '' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) > 0
                                            THEN '2'
                                        WHEN e.exp_status = '4' THEN '4'
                                        ELSE '0'
                                    END
                                FROM expense e
                                WHERE e.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                                ORDER BY e.exp_id DESC
                                LIMIT 1
                        ) AS current_exp_status,
                        (
                                SELECT e.exp_month
                                FROM expense e
                                WHERE e.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND (
                                        c.ctr_start IS NULL
                                        OR DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                                ORDER BY e.exp_month DESC, e.exp_id DESC
                                LIMIT 1
                        ) AS latest_exp_month,
                        (
                                SELECT
                                    CASE
                                        WHEN e.exp_total > 0
                                             AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) >= e.exp_total - 0.00001
                                            THEN '1'
                                        WHEN COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) > 0
                                            THEN '3'
                                        WHEN COALESCE((SELECT COUNT(*) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '0' AND p.pay_proof IS NOT NULL AND p.pay_proof <> '' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) > 0
                                            THEN '2'
                                        WHEN e.exp_status = '4' THEN '4'
                                        ELSE '0'
                                    END
                                FROM expense e
                                WHERE e.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND (
                                        c.ctr_start IS NULL
                                        OR DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                                ORDER BY e.exp_month DESC, e.exp_id DESC
                                LIMIT 1
                        ) AS latest_exp_status,
                        (
                            SELECT COUNT(*) FROM signature_logs sl
                            WHERE sl.contract_id = COALESCE(c.ctr_id, tw.ctr_id)
                              AND sl.signer_type = 'tenant'
                        ) AS has_tenant_signature
        FROM booking b
        INNER JOIN tenant t ON b.tnt_id = t.tnt_id
        LEFT JOIN (
            SELECT tw1.*
            FROM tenant_workflow tw1
            INNER JOIN (
                SELECT bkg_id, MAX(id) AS latest_workflow_id
                FROM tenant_workflow
                GROUP BY bkg_id
            ) tw2 ON tw1.id = tw2.latest_workflow_id
        ) tw ON b.bkg_id = tw.bkg_id
        LEFT JOIN room r ON b.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
        LEFT JOIN (
            SELECT active_contract.room_id, active_contract.ctr_id
            FROM contract active_contract
            WHERE active_contract.ctr_status = '0'
              AND NOT EXISTS (
                  SELECT 1
                  FROM contract newer_contract
                  WHERE newer_contract.room_id = active_contract.room_id
                    AND newer_contract.ctr_status = '0'
                    AND (
                        COALESCE(newer_contract.ctr_end, '0000-00-00') > COALESCE(active_contract.ctr_end, '0000-00-00')
                        OR (
                            COALESCE(newer_contract.ctr_end, '0000-00-00') = COALESCE(active_contract.ctr_end, '0000-00-00')
                            AND COALESCE(newer_contract.ctr_start, '0000-00-00') > COALESCE(active_contract.ctr_start, '0000-00-00')
                        )
                        OR (
                            COALESCE(newer_contract.ctr_end, '0000-00-00') = COALESCE(active_contract.ctr_end, '0000-00-00')
                            AND COALESCE(newer_contract.ctr_start, '0000-00-00') = COALESCE(active_contract.ctr_start, '0000-00-00')
                            AND newer_contract.ctr_id > active_contract.ctr_id
                        )
                    )
              )
        ) current_room_contract ON current_room_contract.room_id = b.room_id
        LEFT JOIN (
            SELECT bp1.*
            FROM booking_payment bp1
            INNER JOIN (
                SELECT bkg_id, MAX(bp_id) AS latest_bp_id
                FROM booking_payment
                GROUP BY bkg_id
            ) bp2 ON bp1.bp_id = bp2.latest_bp_id
        ) bp ON b.bkg_id = bp.bkg_id
        LEFT JOIN (
            SELECT cr1.*
            FROM checkin_record cr1
            INNER JOIN (
                SELECT ctr_id, MAX(checkin_id) AS latest_checkin_id
                FROM checkin_record
                GROUP BY ctr_id
            ) cr2 ON cr1.checkin_id = cr2.latest_checkin_id
        ) cr ON c.ctr_id = cr.ctr_id
                                WHERE (
                                            tw.id IS NULL
                                            OR tw.completed = 0
                                            OR tw.completed = 1
                                        )
                                    -- ไม่แสดงผู้เช่าที่สัญญาถูกยกเลิกแล้ว
                                    AND (c.ctr_id IS NULL OR c.ctr_status <> '1')
                                    " . $bookingFilterCondition . "
                                    -- Exclude bookings where a different active/notify-cancel contract exists in the same room
                                    AND NOT EXISTS (
                                        SELECT 1 FROM contract c3
                                        LEFT JOIN termination t3 ON c3.ctr_id = t3.ctr_id
                                        WHERE c3.room_id = b.room_id
                                          AND (
                                              (c3.ctr_status = '0' AND (c3.ctr_end IS NULL OR c3.ctr_end >= CURDATE()))
                                              OR (c3.ctr_status = '2' AND (t3.term_date IS NULL OR t3.term_date >= CURDATE()))
                                          )
                                          AND COALESCE(c3.tnt_id, '') <> COALESCE(b.tnt_id, '')
                                    )
                                        " . $completionCondition . "
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC";
    
    $stmt = $conn->query($sql);
    $wizardTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Server-side dedupe: if multiple wizard rows reference the same room, keep the one
    // with the highest workflow progress (current_step) or the newest booking date.
    if (!empty($wizardTenants)) {
        $deduped = [];
        foreach ($wizardTenants as $t) {
            $roomKey = isset($t['room_id']) && $t['room_id'] !== null ? 'r' . (int)$t['room_id'] : 'b' . (int)($t['bkg_id'] ?? 0);
            if (!isset($deduped[$roomKey])) {
                $deduped[$roomKey] = $t;
                continue;
            }
            $cur = $deduped[$roomKey];
            $curStep = (int)($cur['current_step'] ?? 1);
            $newStep = (int)($t['current_step'] ?? 1);
            if ($newStep > $curStep) {
                $deduped[$roomKey] = $t;
                continue;
            }
            if ($newStep === $curStep) {
                $curDate = strtotime($cur['bkg_date'] ?? '1970-01-01');
                $newDate = strtotime($t['bkg_date'] ?? '1970-01-01');
                if ($newDate > $curDate) {
                    $deduped[$roomKey] = $t;
                }
            }
        }
        $wizardTenants = array_values($deduped);
    }

    // Batch-fetch recorded utility months per contract (1 query, no N+1)
    $utilMonthsRecorded = [];
    try {
        $allCtrIds = array_values(array_filter(array_unique(array_map(
            fn($t) => (int)($t['ctr_id'] ?? 0), $wizardTenants
        ))));
        if (!empty($allCtrIds)) {
            $placeholders = implode(',', array_fill(0, count($allCtrIds), '?'));
            // เช็คว่า contract นี้มี meter reading ที่บันทึกแล้ว (utl_water_end > 0 OR utl_elec_end > 0)
            // รองรับ partial save: น้ำจดแล้ว/ไฟยังไม่ หรือกลับกัน
            $utilStmt = $conn->prepare(
                "SELECT ctr_id, DATE_FORMAT(utl_date, '%Y-%m') AS ym,
                        MAX(CASE WHEN utl_water_end IS NOT NULL AND utl_water_end > 0 THEN 1 ELSE 0 END) AS has_water,
                        MAX(CASE WHEN utl_elec_end IS NOT NULL AND utl_elec_end > 0 THEN 1 ELSE 0 END) AS has_elec
                 FROM utility WHERE ctr_id IN ($placeholders)
                 AND (
                    (utl_water_end IS NOT NULL AND utl_water_end > 0)
                    OR (utl_elec_end IS NOT NULL AND utl_elec_end > 0)
                 )
                 GROUP BY ctr_id, DATE_FORMAT(utl_date, '%Y-%m')"
            );
            $utilStmt->execute($allCtrIds);
            foreach ($utilStmt->fetchAll(PDO::FETCH_ASSOC) as $uRow) {
                $ctrIdKey = (int)$uRow['ctr_id'];
                $isFull = ((int)$uRow['has_water'] === 1 && (int)$uRow['has_elec'] === 1);
                $utilMonthsRecorded[$ctrIdKey][$uRow['ym']] = $isFull ? 'full' : 'partial';
                // __any__: ครบทั้งน้ำและไฟ อย่างน้อย 1 เดือน
                if ($isFull) {
                    $utilMonthsRecorded[$ctrIdKey]['__any__'] = true;
                }
                // __partial__: มีอย่างน้อย 1 เดือนที่จดบางส่วน
                if (!$isFull) {
                    $utilMonthsRecorded[$ctrIdKey]['__partial__'] = true;
                }
            }
        }
    } catch (Exception $e) { /* non-critical */ }

    // Batch-fetch latest checkin records per contract (for meter-start fallback)
    $checkinRecords = [];
    try {
        if (!empty($allCtrIds)) {
            $placeholders = implode(',', array_fill(0, count($allCtrIds), '?'));
            $checkinStmt = $conn->prepare(
                "SELECT cr1.ctr_id, cr1.water_meter_start, cr1.elec_meter_start
                 FROM checkin_record cr1
                 INNER JOIN (
                     SELECT ctr_id, MAX(checkin_id) AS max_id
                     FROM checkin_record WHERE ctr_id IN ($placeholders)
                     GROUP BY ctr_id
                 ) cr2 ON cr1.checkin_id = cr2.max_id"
            );
            $checkinStmt->execute($allCtrIds);
            foreach ($checkinStmt->fetchAll(PDO::FETCH_ASSOC) as $cRow) {
                $checkinRecords[(int)$cRow['ctr_id']] = $cRow;
            }
        }
    } catch (Exception $e) { /* non-critical */ }

    // Count completed workflows for button visibility
    $completedCountStmt = $conn->query("
        SELECT COUNT(*) as completed_count
        FROM tenant_workflow tw
        LEFT JOIN booking b ON tw.bkg_id = b.bkg_id
        LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
        LEFT JOIN (
            SELECT active_contract.room_id, active_contract.ctr_id
            FROM contract active_contract
            WHERE active_contract.ctr_status = '0'
              AND NOT EXISTS (
                  SELECT 1
                  FROM contract newer_contract
                  WHERE newer_contract.room_id = active_contract.room_id
                    AND newer_contract.ctr_status = '0'
                    AND (
                        COALESCE(newer_contract.ctr_end, '0000-00-00') > COALESCE(active_contract.ctr_end, '0000-00-00')
                        OR (
                            COALESCE(newer_contract.ctr_end, '0000-00-00') = COALESCE(active_contract.ctr_end, '0000-00-00')
                            AND COALESCE(newer_contract.ctr_start, '0000-00-00') > COALESCE(active_contract.ctr_start, '0000-00-00')
                        )
                        OR (
                            COALESCE(newer_contract.ctr_end, '0000-00-00') = COALESCE(active_contract.ctr_end, '0000-00-00')
                            AND COALESCE(newer_contract.ctr_start, '0000-00-00') = COALESCE(active_contract.ctr_start, '0000-00-00')
                            AND newer_contract.ctr_id > active_contract.ctr_id
                        )
                    )
              )
        ) current_room_contract ON current_room_contract.room_id = b.room_id
        LEFT JOIN (
            SELECT cr1.*
            FROM checkin_record cr1
            INNER JOIN (
                SELECT ctr_id, MAX(checkin_id) AS latest_checkin_id
                FROM checkin_record
                GROUP BY ctr_id
            ) cr2 ON cr1.checkin_id = cr2.latest_checkin_id
        ) cr ON c.ctr_id = cr.ctr_id
        WHERE tw.id IS NOT NULL
          AND tw.completed = 1
                    AND (
                            current_room_contract.ctr_id IS NULL
                            OR c.ctr_id = current_room_contract.ctr_id
                    )
          AND COALESCE(tw.step_5_confirmed, 0) = 1
          AND cr.checkin_date IS NOT NULL
          AND cr.checkin_date <> '0000-00-00'
          AND $firstBillPaidCondition
          AND $latestBillPaidCondition
    ");
    $completedCountResult = $completedCountStmt->fetch(PDO::FETCH_ASSOC);
    $hasCompletedTenants = (int)($completedCountResult['completed_count'] ?? 0) > 0;
} catch (Exception $e) {
    $wizardTenants = [];
    $hasCompletedTenants = false;
    $selectedBkgId = 0;
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

$completedZeroHref = 'tenant_wizard.php?completed=0' . ($selectedBkgId > 0 ? '&bkg_id=' . $selectedBkgId : '');
$completedOneHref = 'tenant_wizard.php?completed=1' . ($selectedBkgId > 0 ? '&bkg_id=' . $selectedBkgId : '');
$clearSelectionHref = 'tenant_wizard.php?completed=' . $completedFilter;

// นับจำนวนห้องที่ผ่านขั้นตอนที่ 5 แล้ว แต่ยังไม่ได้จดมิเตอร์เดือนนี้ครบทั้งน้ำและไฟ
$meterPendingBadgeCount = 0;
try {
    $meterBadgeStmt = $conn->prepare("
        SELECT COUNT(DISTINCT c.ctr_id) AS cnt
        FROM contract c
        INNER JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id
        WHERE c.ctr_status = '0'
          AND (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
          AND NOT EXISTS (
              SELECT 1 FROM utility u
              WHERE u.ctr_id = c.ctr_id
                AND MONTH(u.utl_date) = MONTH(CURDATE())
                AND YEAR(u.utl_date) = YEAR(CURDATE())
                AND u.utl_water_end IS NOT NULL AND u.utl_water_end > 0
                AND u.utl_elec_end IS NOT NULL AND u.utl_elec_end > 0
          )
    ");
    $meterBadgeStmt->execute();
    $meterPendingBadgeCount = (int)($meterBadgeStmt->fetchColumn() ?? 0);
} catch (Exception $e) {
    $meterPendingBadgeCount = 0;
}

// Format current month for display
$currentMonthDisplay = thaiMonthYear(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตัวช่วยผู้เช่า</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/Logo.jpg">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <style>
        /* ============================================================
           🎨 FUTURISTIC WIZARD UI — Bright Indigo-Purple Theme
           ============================================================ */

        /* === CSS Custom Property for animated conic-gradient border === */
        @property --wiz-angle {
            syntax: '<angle>';
            initial-value: 0deg;
            inherits: false;
        }

        :root {
            --theme-bg-color: <?php echo $themeColor; ?>;
            --wiz-primary: #6366f1;
            --wiz-violet: #8b5cf6;
            --wiz-purple: #a855f7;
            --wiz-indigo-light: #e0e7ff;
            --wiz-violet-light: #ede9fe;
            --wiz-text: #1e293b;
            --wiz-text-light: #64748b;
            --wiz-bg: #f0f2f8;
            --wiz-card: rgba(255,255,255,0.88);
            --wiz-border: rgba(99,102,241,0.18);
            --wiz-glow: rgba(99,102,241,0.25);
        }

        /* === Background with animated gradient mesh === */
        body, body main {
            background: var(--wiz-bg) !important;
            color: var(--wiz-text) !important;
        }
        body main {
            background:
                radial-gradient(ellipse 80% 60% at 15% 20%, rgba(99,102,241,0.10) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 85% 75%, rgba(168,85,247,0.08) 0%, transparent 55%),
                radial-gradient(ellipse 50% 40% at 50% 0%, rgba(139,92,246,0.06) 0%, transparent 50%),
                var(--wiz-bg) !important;
            background-size: 200% 200%, 200% 200%, 100% 100%, 100% 100% !important;
            animation: wizGradientShift 18s ease-in-out infinite !important;
        }

        /* === Floating particles layer === */
        body main::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background-image:
                radial-gradient(1.5px 1.5px at 10% 15%, rgba(99,102,241,0.22) 50%, transparent 50%),
                radial-gradient(1px 1px at 30% 45%, rgba(139,92,246,0.18) 50%, transparent 50%),
                radial-gradient(2px 2px at 55% 20%, rgba(168,85,247,0.15) 50%, transparent 50%),
                radial-gradient(1px 1px at 75% 60%, rgba(99,102,241,0.20) 50%, transparent 50%),
                radial-gradient(1.5px 1.5px at 90% 30%, rgba(139,92,246,0.16) 50%, transparent 50%),
                radial-gradient(1px 1px at 20% 80%, rgba(168,85,247,0.14) 50%, transparent 50%),
                radial-gradient(2px 2px at 65% 85%, rgba(99,102,241,0.12) 50%, transparent 50%),
                radial-gradient(1px 1px at 45% 10%, rgba(139,92,246,0.18) 50%, transparent 50%);
            animation: wizParticle 22s linear infinite;
        }

        /* === Wizard Panel — Glassmorphism + Rainbow Border === */
        .wizard-panel {
            margin: 1.5rem;
            background: var(--wiz-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px solid transparent;
            border-radius: 16px;
            box-shadow:
                0 8px 32px rgba(99,102,241,0.10),
                0 2px 8px rgba(139,92,246,0.06),
                inset 0 1px 0 rgba(255,255,255,0.7);
            overflow: visible;
            position: relative;
            z-index: 1;
            animation: wizFadeSlideUp 0.6s ease-out both;
        }
        /* Rainbow spinning border */
        .wizard-panel::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 18px;
            background: conic-gradient(from var(--wiz-angle), #6366f1, #8b5cf6, #a855f7, #ec4899, #f43f5e, #f97316, #eab308, #22c55e, #06b6d4, #6366f1);
            z-index: -1;
            animation: wizSpinBorder 6s linear infinite;
            opacity: 0.45;
        }
        .wizard-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 16px;
            background: var(--wiz-card);
            z-index: -1;
        }

        .wizard-panel > .page-header-bar {
            margin: 0 0 1rem !important;
            border-radius: 0;
            border: 0;
            border-bottom: 1px solid var(--wiz-border);
            box-shadow: none;
            background: rgba(255,255,255,0.55) !important;
            backdrop-filter: blur(10px);
        }
        .wizard-panel > .page-header-spacer {
            display: none !important;
        }

        .wizard-panel-body {
            padding: 1.5rem;
            padding-top: 1rem;
        }

        /* === Intro Box — Gradient + Shimmer === */
        .wizard-intro {
            padding: 1.5rem 1.5rem 1.5rem 1.75rem;
            background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(139,92,246,0.05) 50%, rgba(168,85,247,0.03) 100%);
            border: 1px solid var(--wiz-border);
            border-left: 4px solid;
            border-image: linear-gradient(180deg, #6366f1, #a855f7) 1;
            border-radius: 12px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            animation: wizFadeSlideUp 0.7s ease-out 0.1s both;
        }
        .wizard-intro::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            animation: wizShimmer 3.5s ease-in-out infinite;
        }
        .wizard-intro h3 {
            margin: 0 0 0.5rem 0;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.15rem;
        }
        .wizard-intro p {
            color: #475569 !important;
            line-height: 1.7;
        }
        .wizard-intro strong {
            color: #6366f1;
            -webkit-text-fill-color: #6366f1;
        }
        .wizard-intro-close {
            position: absolute;
            top: 0.6rem; right: 0.7rem;
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(100,116,139,0.6);
            font-size: 1.1rem;
            line-height: 1;
            padding: 0.2rem 0.35rem;
            border-radius: 6px;
            transition: background 0.15s, color 0.15s;
            z-index: 2;
        }
        .wizard-intro-close:hover { background: rgba(99,102,241,0.1); color: #6366f1; }

        /* === Filter Buttons === */
        .wiz-filter-bar {
            display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;
            animation: wizFadeSlideUp 0.7s ease-out 0.2s both;
        }
        .wiz-filter-btn {
            padding: 0.7rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .wiz-filter-btn::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transition: left 0.5s;
        }
        .wiz-filter-btn:hover::after { left: 150%; }
        .wiz-filter-btn:hover { transform: translateY(-2px); }

        .wiz-filter-btn.pending-filter {
            background: rgba(99,102,241,0.12);
            color: #4338ca;
            border-color: rgba(99,102,241,0.35);
        }
        .wiz-filter-btn.pending-filter.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff !important;
            border-color: transparent;
            box-shadow: 0 4px 18px rgba(99,102,241,0.35);
        }
        .wiz-filter-btn.complete-filter {
            background: rgba(34,197,94,0.12);
            color: #15803d;
            border-color: rgba(34,197,94,0.35);
        }
        .wiz-filter-btn.complete-filter.active {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff !important;
            border-color: transparent;
            box-shadow: 0 4px 18px rgba(34,197,94,0.35);
        }
        .wiz-filter-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.7rem 1rem;
            background: rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.25);
            border-radius: 12px;
            color: #4338ca; font-weight: 600;
        }
        .wiz-meter-alert {
            display: inline-flex; align-items: center; gap: 0.45rem;
            padding: 0.5rem 0.9rem;
            background: rgba(239,68,68,0.10);
            border: 1px solid rgba(239,68,68,0.35);
            border-radius: 10px;
            color: #dc2626; font-size: 0.85rem; font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .wiz-meter-alert:hover {
            background: rgba(239,68,68,0.18);
            box-shadow: 0 2px 10px rgba(239,68,68,0.2);
        }
        .wiz-meter-alert svg { flex-shrink: 0; }
        .wiz-meter-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; padding: 0 5px;
            background: #dc2626; color: #fff;
            border-radius: 999px; font-size: 11px; font-weight: 800;
        }
        .wiz-filter-clear {
            padding: 0.7rem 1.25rem;
            background: rgba(255,255,255,0.7);
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            text-decoration: none;
            color: #334155; font-weight: 600;
            transition: all 0.2s;
        }
        .wiz-filter-clear:hover { background: #f1f5f9; }

        /* === Wizard Table === */
        .wizard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
            margin-top: 1.5rem;
            animation: wizFadeSlideUp 0.7s ease-out 0.3s both;
        }
        .wizard-table thead {
            background: linear-gradient(135deg, #6366f1, #7c3aed, #8b5cf6);
            position: relative;
        }
        .wizard-table thead::after {
            content: '';
            position: absolute;
            bottom: 0; left: 10%; right: 10%; height: 3px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            border-radius: 2px;
        }
        .wizard-table th {
            padding: 1rem 1.25rem;
            text-align: left;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.03em;
            text-shadow: 0 1px 2px rgba(0,0,0,0.15);
            border: none;
        }
        .wizard-table th:first-child { border-radius: 12px 0 0 12px; }
        .wizard-table th:last-child  { border-radius: 0 12px 12px 0; }

        .wizard-table td {
            padding: 1rem 1.25rem;
            text-align: left;
            color: var(--wiz-text);
            border: none;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
        }
        .wizard-table td:first-child { border-radius: 12px 0 0 12px; }
        .wizard-table td:last-child  { border-radius: 0 12px 12px 0; }

        .wizard-table tbody tr {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: wizFadeSlideUp 0.5s ease-out both;
        }
        .wizard-table tbody tr:nth-child(1) { animation-delay: 0.05s; }
        .wizard-table tbody tr:nth-child(2) { animation-delay: 0.10s; }
        .wizard-table tbody tr:nth-child(3) { animation-delay: 0.15s; }
        .wizard-table tbody tr:nth-child(4) { animation-delay: 0.20s; }
        .wizard-table tbody tr:nth-child(5) { animation-delay: 0.25s; }
        .wizard-table tbody tr:nth-child(n+6) { animation-delay: 0.30s; }

        .wizard-table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99,102,241,0.12), 0 2px 8px rgba(139,92,246,0.08);
        }
        .wizard-table tbody tr:hover td {
            background: rgba(255,255,255,0.92);
        }

        /* Responsive table */
        @media (max-width: 900px) {
            .wizard-table, .wizard-table thead, .wizard-table tbody, .wizard-table th, .wizard-table td, .wizard-table tr {
                display: block;
            }
            .wizard-table { border-spacing: 0; }
            .wizard-table thead { display: none; }
            .wizard-table tr {
                margin-bottom: 1rem;
                border-radius: 16px;
                box-shadow: 0 4px 16px rgba(99,102,241,0.08);
                background: rgba(255,255,255,0.85);
                backdrop-filter: blur(10px);
                border: 1px solid var(--wiz-border);
                overflow: hidden;
                position: relative;
            }
            .wizard-table tr::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0; height: 3px;
                background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
            }
            .wizard-table td {
                padding: 0.8rem 1rem;
                border-radius: 0 !important;
                background: transparent;
            }
            .wizard-table td:before {
                content: attr(data-label);
                font-weight: 700;
                color: #6366f1;
                display: block;
                margin-bottom: 0.3rem;
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
        }

        /* === Step Indicator === */
        .step-indicator {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            position: relative;
            cursor: help;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .step-circle:hover {
            transform: scale(1.15);
            z-index: 5;
        }

        /* Tooltip */
        .step-circle::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 130%;
            left: 50%;
            transform: translateX(-50%) translateY(5px);
            padding: 8px 14px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px);
            color: #1e293b;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 10px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--wiz-border);
            box-shadow: 0 8px 24px rgba(99,102,241,0.15);
            z-index: 20;
            pointer-events: none;
        }
        .step-circle::after {
            content: '';
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%) translateY(5px);
            border-width: 6px;
            border-style: solid;
            border-color: rgba(255,255,255,0.95) transparent transparent transparent;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 20;
            pointer-events: none;
        }
        .step-circle:hover::before,
        .step-circle:hover::after {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        /* Step states */
        .step-circle.completed {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 3px 12px rgba(34,197,94,0.35);
        }
        .step-circle.current {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #ffffff !important;
            animation: wizPulseGlow 2.2s ease-in-out infinite;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.2), 0 4px 15px rgba(99,102,241,0.3);
        }
        .step-circle.pending {
            background: rgba(241,245,249,0.8);
            color: #94a3b8;
            border: 1.5px solid #e2e8f0;
        }
        .step-circle.wait {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1.5px solid #f59e0b;
            box-shadow: 0 3px 10px rgba(245,158,11,0.2);
        }
        .step-circle.meter-pending {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            color: #065f46;
            border: 1.5px solid #34d399;
            box-shadow: 0 3px 10px rgba(52,211,153,0.2);
        }
        .step-circle.overdue {
            background: linear-gradient(135deg, rgba(239,68,68,0.12), rgba(239,68,68,0.20));
            color: #dc2626;
            border: 1.5px solid rgba(239,68,68,0.5);
            font-weight: 800;
            box-shadow: 0 3px 10px rgba(239,68,68,0.15);
        }
        .step-circle.overdue:hover {
            animation: wizShake 0.4s ease-in-out;
        }

        .wait-spinner {
            width: 20px; height: 20px;
            animation: waitSpin 1.6s linear infinite;
            display: block;
        }
        .wait-spinner circle:last-child {
            animation: waitSpinReverse 1.2s linear infinite;
            transform-origin: center;
        }
        .meter-spinner {
            width: 20px; height: 20px;
            display: block;
            animation: meterPulse 1.8s ease-in-out infinite;
        }
        .checkin-anim {
            width: 20px; height: 20px;
            display: block;
        }
        .checkin-anim .c-arrow {
            animation: checkinEnter 1.5s ease-in-out infinite;
            transform-box: fill-box;
            transform-origin: left center;
        }
        .confirm-anim {
            width: 20px; height: 20px;
            display: block;
        }
        .confirm-anim .c-tick {
            stroke-dasharray: 14;
            stroke-dashoffset: 14;
            animation: confirmDraw 1.6s ease-in-out infinite;
        }
        .confirm-anim .c-ring {
            animation: confirmPop 1.6s ease-in-out infinite;
            transform-box: fill-box;
            transform-origin: center;
        }
        .payment-anim {
            width: 20px; height: 20px;
            display: block;
        }
        .payment-anim .p-coin {
            animation: paymentBounce 1.5s ease-in-out infinite;
            transform-box: fill-box;
            transform-origin: center top;
        }
        .payment-anim .p-slot {
            animation: paymentFade 1.5s ease-in-out infinite;
        }
        .bill-anim {
            width: 20px; height: 20px;
            display: block;
        }
        .bill-anim .b-doc {
            animation: billPop 1.8s ease-in-out infinite;
            transform-box: fill-box;
            transform-origin: center;
        }
        .bill-anim .b-line1 { animation: billLine 1.8s ease-in-out infinite 0.2s; }
        .bill-anim .b-line2 { animation: billLine 1.8s ease-in-out infinite 0.5s; }
        .bill-anim .b-line3 { animation: billLine 1.8s ease-in-out infinite 0.8s; }
        .contract-anim {
            width: 20px; height: 20px;
            display: block;
        }
        .contract-anim .ct-pen {
            animation: contractWrite 1.8s ease-in-out infinite;
            transform-box: fill-box;
            transform-origin: bottom left;
        }
        .contract-anim .ct-line1 { animation: contractReveal 1.8s ease-in-out infinite 0s; }
        .contract-anim .ct-line2 { animation: contractReveal 1.8s ease-in-out infinite 0.35s; }
        .contract-anim .ct-line3 { animation: contractReveal 1.8s ease-in-out infinite 0.65s; }

        /* Step connecting line */
        .step-arrow {
            color: transparent;
            font-size: 0;
            width: 20px;
            height: 3px;
            background: linear-gradient(90deg, var(--wiz-border), rgba(139,92,246,0.3), var(--wiz-border));
            border-radius: 2px;
            display: inline-block;
            vertical-align: middle;
            position: relative;
        }
        .step-arrow::after {
            content: '';
            position: absolute;
            right: -3px; top: 50%;
            transform: translateY(-50%);
            border-left: 5px solid rgba(139,92,246,0.4);
            border-top: 4px solid transparent;
            border-bottom: 4px solid transparent;
        }

        /* === Action Buttons === */
        .action-btn {
            padding: 0.55rem 1.3rem;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }
        .action-btn::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.4s;
        }
        .action-btn:hover::after { left: 150%; }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 3px 12px rgba(99,102,241,0.25);
        }
        .btn-primary:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 20px rgba(99,102,241,0.35);
        }
        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 3px 12px rgba(34,197,94,0.25);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34,197,94,0.35);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 3px 12px rgba(239,68,68,0.25);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239,68,68,0.35);
        }

        .wizard-table .action-btn.btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
            border: none !important;
            color: #ffffff !important;
        }
        .wizard-table .action-btn.btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
        }
        .wizard-table .action-btn.btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            border: none !important;
            color: #ffffff !important;
        }
        .wizard-table .action-btn.btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
        }

        .btn-disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* === Tenant Info === */
        .tenant-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .tenant-name {
            font-weight: 700;
            font-size: 1rem;
            background: linear-gradient(135deg, #4338ca, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .tenant-phone {
            font-size: 0.82rem;
            color: #64748b;
            background: rgba(99,102,241,0.06);
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            display: inline-block;
            width: fit-content;
        }

        /* === Empty State === */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            animation: wizFloat 3s ease-in-out infinite;
        }
        .empty-state svg {
            width: 80px; height: 80px;
            margin-bottom: 1rem;
            opacity: 0.4;
            filter: drop-shadow(0 4px 8px rgba(99,102,241,0.15));
        }
        .empty-state p {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* === Modal Styles === */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(99,102,241,0.08);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 9998;
            animation: fadeIn 0.3s;
        }
        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body.modal-open .page-header-bar,
        body.modal-open .page-header-spacer { display: none !important; }
        body.modal-open { overflow: hidden; }

        .modal-container {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px solid transparent;
            border-radius: 20px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
            box-shadow: 0 24px 64px rgba(99,102,241,0.15), 0 8px 24px rgba(139,92,246,0.08);
            animation: wizModalIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
            position: relative;
        }
        .modal-container::-webkit-scrollbar { display: none; }
        .modal-container::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 22px;
            background: conic-gradient(from var(--wiz-angle), #6366f1, #8b5cf6, #a855f7, #ec4899, #22c55e, #06b6d4, #6366f1);
            z-index: -1;
            animation: wizSpinBorder 6s linear infinite;
            opacity: 0.3;
        }
        .modal-container::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 20px;
            background: rgba(255,255,255,0.92);
            z-index: -1;
        }

        /* Gradient accent bar at top of modal */
        .modal-header {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid var(--wiz-border);
            position: sticky;
            top: 0;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            z-index: 10;
            position: relative;
        }
        .modal-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #a855f7, #ec4899);
            border-radius: 20px 20px 0 0;
        }
        .modal-header h2 {
            color: #1e293b !important;
            background: linear-gradient(135deg, #4338ca, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .modal-header p { color: #64748b !important; }

        .modal-close {
            position: absolute;
            top: 1rem; right: 1rem;
            background: rgba(99,102,241,0.08);
            border: 1px solid var(--wiz-border);
            width: 38px; height: 38px;
            border-radius: 50%;
            cursor: pointer;
            color: #6366f1;
            font-size: 1.4rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal-close:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-color: transparent;
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 4px 12px rgba(239,68,68,0.3);
        }

        .modal-body { padding: 2rem; }

        .modal-footer {
            padding: 1.25rem 2rem;
            border-top: 1px solid var(--wiz-border);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            position: sticky;
            bottom: 0;
            border-radius: 0 0 20px 20px;
        }

        /* Modal step number circle — gradient (48px and 56px variants) */
        .modal-header div[style*="border-radius: 50%"][style*="width: 48px"],
        .modal-header div[style*="border-radius: 50%"][style*="width: 56px"] {
            background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
            box-shadow: 0 4px 15px rgba(99,102,241,0.35) !important;
        }

        /* === Form Controls === */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #334155;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            background: rgba(255,255,255,0.8);
            color: #0f172a;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8b5cf6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(139,92,246,0.12), 0 4px 12px rgba(99,102,241,0.08);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .info-box-modal {
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(99,102,241,0.06), rgba(139,92,246,0.03));
            border: 1px solid var(--wiz-border);
            border-left: 4px solid #6366f1;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .info-box-modal p { margin: 0.5rem 0; color: #1e293b; }

        .alert-box-modal {
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(245,158,11,0.06), rgba(251,191,36,0.03));
            border: 2px solid rgba(245,158,11,0.25);
            border-left: 4px solid #f59e0b;
            border-radius: 12px;
            margin: 1.5rem 0;
        }
        .alert-box-modal h4 { margin-top: 0; color: #b45309; }
        .alert-box-modal h4[style*="color: #22c55e"],
        .alert-box-modal h4[style*="color:#22c55e"] { color: #16a34a !important; }
        .alert-box-modal h4[style*="color: #c4b5fd"],
        .alert-box-modal h4[style*="color:#c4b5fd"] { color: #7c3aed !important; }
        .alert-box-modal ul[style*="color: #e2e8f0"],
        .alert-box-modal ul[style*="color:#e2e8f0"] { color: #475569 !important; }
        .alert-box-modal li { color: inherit; }

        /* === Modal Buttons === */
        .btn-modal {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            border: none;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .btn-modal::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .btn-modal:hover::after { left: 150%; }

        .btn-modal-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 18px rgba(99,102,241,0.3);
        }
        .btn-modal-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(99,102,241,0.4);
        }
        .btn-modal-secondary {
            background: rgba(255,255,255,0.8);
            color: #334155;
            border: 1.5px solid #e2e8f0;
        }
        .btn-modal-secondary:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* === Fix dark-theme inline color remnants === */
        .wizard-panel p[style],
        .wizard-panel span[style],
        .wizard-panel div[style] { color: inherit; }

        .wizard-table span[style*="rgba(255,255,255"],
        .wizard-table span[style*="#e2e8f0"],
        .wizard-table span[style*="#f1f5f9"] {
            color: #64748b !important;
        }
        /* With-space variants */
        .modal-container [style*="color: #f8fafc"],
        .modal-container [style*="color: #f1f5f9"],
        .modal-container [style*="color: #e2e8f0"],
        .modal-container [style*="color: #fff"],
        .modal-container [style*="color: rgba(255,255,255"],
        .modal-container [style*="color: rgba(241,245,249"],
        .modal-container [style*="color: rgba(226,232,240"],
        .modal-container [style*="color: white"],
        /* No-space variants (billing/meter-only minified styles) */
        .modal-container [style*="color:#f8fafc"],
        .modal-container [style*="color:#f1f5f9"],
        .modal-container [style*="color:#e2e8f0"],
        .modal-container [style*="color:#fff"],
        .modal-container [style*="color:rgba(255,255,255"],
        .modal-container [style*="color:rgba(241,245,249"],
        .modal-container [style*="color:rgba(226,232,240"],
        .modal-container [style*="color:white"] {
            color: #334155 !important;
        }
        /* Secondary-text inline colors */
        .modal-container [style*="color: rgba(148,163,184"],
        .modal-container [style*="color:rgba(148,163,184"],
        .modal-container [style*="color: #94a3b8"],
        .modal-container [style*="color:#94a3b8"],
        .modal-container [style*="color: #cbd5e1"],
        .modal-container [style*="color:#cbd5e1"] {
            color: #64748b !important;
        }
        /* Protect accent colors from being overridden */
        .modal-container [style*="color: #4ade80"],
        .modal-container [style*="color:#4ade80"] { color: #16a34a !important; }
        .modal-container [style*="color: #60a5fa"],
        .modal-container [style*="color:#60a5fa"] { color: #3b82f6 !important; }
        .modal-container [style*="color: #fbbf24"],
        .modal-container [style*="color:#fbbf24"] { color: #d97706 !important; }
        .modal-container [style*="color: #93c5fd"],
        .modal-container [style*="color:#93c5fd"] { color: #3b82f6 !important; }
        .modal-container [style*="color: #f87171"],
        .modal-container [style*="color:#f87171"] { color: #ef4444 !important; }
        .modal-container [style*="color: #fca5a5"],
        .modal-container [style*="color:#fca5a5"] { color: #ef4444 !important; }
        .modal-container [style*="color: #d97706"],
        .modal-container [style*="color:#d97706"] { color: #b45309 !important; }
        .modal-container input,
        .modal-container textarea,
        .modal-container select {
            background: rgba(255,255,255,0.9) !important;
            border: 1.5px solid #e2e8f0 !important;
            color: #0f172a !important;
        }
        .modal-container input:focus,
        .modal-container textarea:focus,
        .modal-container select:focus {
            border-color: #8b5cf6 !important;
            box-shadow: 0 0 0 4px rgba(139,92,246,0.12) !important;
        }
        .modal-container input::placeholder,
        .modal-container textarea::placeholder { color: #94a3b8 !important; }
        .modal-container .close-btn {
            background: rgba(99,102,241,0.08) !important;
            color: #6366f1 !important;
            border: 1px solid var(--wiz-border) !important;
        }
        /* Dark transparent backgrounds → light */
        .modal-container [style*="background: rgba(255,255,255,0.05)"],
        .modal-container [style*="background: rgba(255,255,255,0.08)"],
        .modal-container [style*="background: rgba(255,255,255,0.1)"],
        .modal-container [style*="background:rgba(255,255,255,0.04)"],
        .modal-container [style*="background:rgba(255,255,255,0.05)"],
        .modal-container [style*="background:rgba(255,255,255,0.08)"] {
            background: rgba(99,102,241,0.04) !important;
            border-color: var(--wiz-border) !important;
        }
        /* Dark solid backgrounds on meter body / preview */
        .modal-container [style*="background:rgba(15,23,42"],
        .modal-container [style*="background: rgba(15,23,42"] {
            background: rgba(99,102,241,0.03) !important;
            border-color: var(--wiz-border) !important;
        }
        /* Dark border lines in modals → theme border */
        .modal-container [style*="border-bottom:1px solid rgba(255,255,255"],
        .modal-container [style*="border-top:1px solid rgba(255,255,255"],
        .modal-container [style*="border:1px solid rgba(255,255,255"],
        .modal-container [style*="border-bottom: 1px solid rgba(255,255,255"],
        .modal-container [style*="border-top: 1px solid rgba(255,255,255"],
        .modal-container [style*="border: 1px solid rgba(255,255,255"] {
            border-color: var(--wiz-border) !important;
        }
        .modal-container img { border-color: var(--wiz-border) !important; }

        /* Standalone close buttons (billing/meter) */
        .modal-container button[onclick*="close"][style*="background:rgba(255,255,255,0.08)"],
        .modal-container button[onclick*="close"][style*="background: rgba(255,255,255,0.08)"] {
            background: rgba(99,102,241,0.08) !important;
            color: #6366f1 !important;
            border: 1px solid var(--wiz-border) !important;
        }
        /* Billing modal footer close button (transparent bg) */
        .modal-container button[onclick*="close"][style*="background:transparent"],
        .modal-container button[onclick*="close"][style*="background: transparent"] {
            background: rgba(255,255,255,0.8) !important;
            color: #334155 !important;
            border: 1.5px solid #e2e8f0 !important;
        }

        /* Fix billing modal header gradient */
        #billingModal .modal-container > div:first-child {
            background: linear-gradient(135deg, rgba(34,197,94,0.08), rgba(59,130,246,0.06)) !important;
            border-bottom: 1px solid rgba(34,197,94,0.15) !important;
        }
        #billingModal .modal-container > div:first-child div[style*="background:#22c55e"] {
            background: linear-gradient(135deg, #22c55e, #16a34a) !important;
            box-shadow: 0 4px 12px rgba(34,197,94,0.3);
        }

        /* Fix meter-only modal header gradient */
        #meterOnlyModal .modal-container > div:first-child {
            background: linear-gradient(135deg, rgba(5,150,105,0.08), rgba(16,185,129,0.04)) !important;
            border-bottom: 1px solid rgba(5,150,105,0.15) !important;
        }
        #meterOnlyModal .modal-container > div:first-child div[style*="background:#059669"] {
            background: linear-gradient(135deg, #059669, #10b981) !important;
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }
        #meterOnlyModal .modal-container div[style*="font-weight:700"][style*="color:#f8fafc"] {
            color: #065f46 !important;
        }
        #meterOnlyModal .modal-container div[style*="color:rgba(226,232,240"] {
            color: #64748b !important;
        }

        /* Billing/meter save buttons — keep amber/green buttons visible on light theme */
        .modal-container button[onclick*="saveMeter"],
        .modal-container button[id="moSaveBtn"] {
            box-shadow: 0 2px 8px rgba(217,119,6,0.25);
        }
        #meterOnlyModal button[id="moSaveBtn"] {
            box-shadow: 0 2px 8px rgba(5,150,105,0.25);
        }

        /* Checkin modal validation error + summary boxes on light theme */
        #checkinModal #validationError {
            background: rgba(239,68,68,0.06) !important;
            border-color: rgba(239,68,68,0.2) !important;
            color: #dc2626 !important;
        }

        /* Billing modal footer */
        #billingModal .modal-container > div:last-child {
            border-top: 1px solid var(--wiz-border) !important;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 0 0 20px 20px;
        }

        /* === Billing SVG Icons === */
        .billing-inline-icon { display: inline-flex; align-items: center; gap: 0.32rem; }
        .billing-svg-icon { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; vertical-align: -2px; flex-shrink: 0; }
        .billing-svg-water  { animation: billingFloat 2.2s ease-in-out infinite; }
        .billing-svg-elec   { animation: billingFlicker 1.7s ease-in-out infinite; }
        .billing-svg-cal    { animation: billingTick 2.4s ease-in-out infinite; }
        .billing-svg-meter  { animation: billingMeterPulse 2.1s ease-in-out infinite; }
        .billing-svg-warning { width: 15px; height: 15px; animation: billingWarningPulse 1.9s ease-in-out infinite; }

        /* ============================================================
           🎬 KEYFRAMES
           ============================================================ */
        @keyframes wizGradientShift {
            0%, 100% { background-position: 0% 0%, 100% 100%, 0% 0%, 0% 0%; }
            50%      { background-position: 100% 100%, 0% 0%, 0% 0%, 0% 0%; }
        }
        @keyframes wizShimmer {
            0%, 100% { left: -100%; }
            50%      { left: 150%; }
        }
        @keyframes wizFloat {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-6px); }
        }
        @keyframes wizPulseGlow {
            0%, 100% { transform: scale(1);    box-shadow: 0 0 0 4px rgba(99,102,241,0.2), 0 4px 15px rgba(99,102,241,0.3); }
            50%      { transform: scale(1.08); box-shadow: 0 0 0 8px rgba(99,102,241,0.1), 0 6px 24px rgba(99,102,241,0.4); }
        }
        @keyframes wizFadeSlideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes wizSpinBorder {
            from { --wiz-angle: 0deg; }
            to   { --wiz-angle: 360deg; }
        }
        @keyframes wizParticle {
            0%   { transform: translateY(0); }
            100% { transform: translateY(-50px); }
        }
        @keyframes wizModalIn {
            from { opacity: 0; transform: scale(0.92) translateY(20px); filter: blur(4px); }
            to   { opacity: 1; transform: scale(1) translateY(0); filter: blur(0); }
        }
        @keyframes wizShake {
            0%, 100% { transform: translateX(0) scale(1.15); }
            20%      { transform: translateX(-3px) scale(1.15); }
            40%      { transform: translateX(3px) scale(1.15); }
            60%      { transform: translateX(-2px) scale(1.15); }
            80%      { transform: translateX(2px) scale(1.15); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.1); }
        }
        @keyframes waitSpin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        @keyframes waitSpinReverse {
            from { transform: rotate(0deg); }
            to   { transform: rotate(-360deg); }
        }
        @keyframes meterPulse {
            0%, 100% { opacity: 1;   transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.82); }
        }
        @keyframes checkinEnter {
            0%   { transform: translateX(-4px); opacity: 0; }
            35%  { transform: translateX(0);    opacity: 1; }
            65%  { transform: translateX(0);    opacity: 1; }
            100% { transform: translateX(-4px); opacity: 0; }
        }
        @keyframes confirmDraw {
            0%   { stroke-dashoffset: 14; opacity: 0; }
            30%  { stroke-dashoffset: 0;  opacity: 1; }
            70%  { stroke-dashoffset: 0;  opacity: 1; }
            100% { stroke-dashoffset: 14; opacity: 0; }
        }
        @keyframes confirmPop {
            0%   { transform: scale(0.7); opacity: 0; }
            30%  { transform: scale(1);   opacity: 1; }
            70%  { transform: scale(1);   opacity: 1; }
            100% { transform: scale(0.7); opacity: 0; }
        }
        @keyframes paymentBounce {
            0%   { transform: translateY(-5px); opacity: 0; }
            30%  { transform: translateY(0);    opacity: 1; }
            60%  { transform: translateY(0);    opacity: 1; }
            80%  { transform: translateY(2px);  opacity: 0.6; }
            100% { transform: translateY(-5px); opacity: 0; }
        }
        @keyframes paymentFade {
            0%   { opacity: 0.3; }
            40%  { opacity: 1; }
            70%  { opacity: 1; }
            100% { opacity: 0.3; }
        }
        @keyframes billPop {
            0%   { transform: scale(0.8); opacity: 0.4; }
            40%  { transform: scale(1);   opacity: 1; }
            70%  { transform: scale(1);   opacity: 1; }
            100% { transform: scale(0.8); opacity: 0.4; }
        }
        @keyframes billLine {
            0%   { stroke-dashoffset: 8; opacity: 0; }
            40%  { stroke-dashoffset: 0; opacity: 1; }
            70%  { stroke-dashoffset: 0; opacity: 1; }
            100% { stroke-dashoffset: 8; opacity: 0; }
        }
        @keyframes contractWrite {
            0%   { transform: translate(0,0)   rotate(-20deg); opacity: 0.4; }
            30%  { transform: translate(3px,-2px) rotate(-20deg); opacity: 1; }
            60%  { transform: translate(6px,-4px) rotate(-20deg); opacity: 1; }
            90%  { transform: translate(3px,-2px) rotate(-20deg); opacity: 0.4; }
            100% { transform: translate(0,0)   rotate(-20deg); opacity: 0.4; }
        }
        @keyframes contractReveal {
            0%   { stroke-dashoffset: 10; opacity: 0; }
            35%  { stroke-dashoffset: 0;  opacity: 1; }
            70%  { stroke-dashoffset: 0;  opacity: 1; }
            100% { stroke-dashoffset: 10; opacity: 0; }
        }
        @keyframes billingFloat {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-1.5px); }
        }
        @keyframes billingFlicker {
            0%, 100% { opacity: 1; }
            45%      { opacity: 0.65; }
            55%      { opacity: 1; }
        }
        @keyframes billingTick {
            0%, 100% { transform: rotate(0deg); }
            40%      { transform: rotate(-3deg); }
            65%      { transform: rotate(2deg); }
        }
        @keyframes billingMeterPulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.06); }
        }
        @keyframes billingWarningPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.75; transform: scale(1.06); }
        }
    </style>
</head>
<body>
    <div style="display: flex;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; overflow-y: auto; height: 100vh; scrollbar-width: none; -ms-overflow-style: none; padding-bottom: 4rem;">
            <div class="wizard-panel">
                <?php $pageTitle = 'ตัวช่วยผู้เช่า'; include '../includes/page_header.php'; ?>
                <div class="wizard-panel-body">

                <?php if (!empty($_SESSION['error'])): ?>
                    <div style="margin: 0.5rem 0 1rem; padding: 0.75rem 1rem; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 6px;">
                        <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div style="margin: 0.5rem 0 1rem; padding: 0.75rem 1rem; background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px;">
                        <?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <div class="wizard-intro" id="wizardIntroBox">
                    <button class="wizard-intro-close" onclick="closeWizardIntro()" title="ซ่อน">✕</button>
                    <h3>🎯 ระบบจัดการผู้เช่า 5 ขั้นตอน</h3>
                    <p style="margin: 0; line-height: 1.7;">
                        ระบบนี้ช่วยให้คุณจัดการผู้เช่าได้อย่างเป็นระบบ ตั้งแต่การจองห้องจนถึงการออกบิลรายเดือน<br>
                        <strong>ขั้นตอน:</strong> ① ยืนยันจอง → ② ยืนยันชำระเงินจอง → ③ สร้างสัญญา → ④ เช็คอิน → ⑤ เริ่มบิลรายเดือน
                    </p>
                </div>
                <script>
                    (function(){
                        if (localStorage.getItem('wizardIntroHidden') === '1') {
                            var el = document.getElementById('wizardIntroBox');
                            if (el) el.style.display = 'none';
                        }
                    })();
                </script>

                <!-- Completion Status Filter Buttons -->
                <div class="wiz-filter-bar">
                    <a href="<?php echo htmlspecialchars($completedZeroHref, ENT_QUOTES, 'UTF-8'); ?>" class="wiz-filter-btn pending-filter <?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? 'active' : ''; ?>">⏳ ยังไม่ครบ 5 ขั้นตอน</a>
                    <?php if ($hasCompletedTenants): ?>
                    <a href="<?php echo htmlspecialchars($completedOneHref, ENT_QUOTES, 'UTF-8'); ?>" class="wiz-filter-btn complete-filter <?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? 'active' : ''; ?>">✅ ครบ 5 ขั้นตอนแล้ว</a>
                    <?php endif; ?>
                    <?php if ($meterPendingBadgeCount > 0): ?>
                    <a href="manage_utility.php" class="wiz-meter-alert" title="ไปจดมิเตอร์">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                        ยังไม่จดมิเตอร์เดือนนี้ (<?php echo htmlspecialchars($currentMonthDisplay, ENT_QUOTES, 'UTF-8'); ?>)
                        <span class="wiz-meter-count"><?php echo $meterPendingBadgeCount > 99 ? '99+' : $meterPendingBadgeCount; ?></span>
                        ห้อง
                    </a>
                    <?php endif; ?>
                    <?php if ($selectedBkgId > 0): ?>
                    <span class="wiz-filter-badge">กำลังแสดงเฉพาะรายการ #<?php echo (int)$selectedBkgId; ?></span>
                    <a href="<?php echo htmlspecialchars($clearSelectionHref, ENT_QUOTES, 'UTF-8'); ?>" class="wiz-filter-clear">ล้างตัวกรอง</a>
                    <?php endif; ?>
                </div>

                <?php if (count($wizardTenants) > 0): ?>
                    <div id="wizardTableWrapper">
                    <table class="wizard-table">
                        <thead>
                            <tr>
                                <th>ผู้เช่า</th>
                                <th>ห้อง</th>
                                <th style="min-width: 300px;">สถานะ</th>
                                <th>ขั้นตอนถัดไป</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wizardTenants as $tenant): ?>
                                <?php
                                // ถ้ายังไม่มี workflow row → step 1 ยังไม่ได้ยืนยันผ่าน wizard (แสดงวงกลม "1" current ไม่ใช่ ✓)
                                $currentStep = ($tenant['workflow_id'] === null) ? 1 : (int)$tenant['current_step'];
                                // workflow_id = NULL → step1 ยังไม่สมบูรณ์ ต้องกด "ยืนยันการจอง" ก่อน
                                $step1 = ($tenant['workflow_id'] === null) ? 0 : (int)$tenant['step_1_confirmed'];
                                $step2 = $tenant['step_2_confirmed'];
                                $step3 = $tenant['step_3_confirmed'];

                                $hasCheckinDate = !empty($tenant['checkin_date']) && $tenant['checkin_date'] !== '0000-00-00';
                                // หมายเหตุ: ไม่ต้องตรวจสอบค่ามิเตอร์ในขั้นตอน 4 (เช็คอิน) เพราะจะจดมิเตอร์ในขั้นตอน 5
                                // checkinDataComplete เพียงแค่ต้องมีวันเช็คอิน
                                $checkinDataComplete = $hasCheckinDate;

                                $step4 = ((int)$tenant['step_4_confirmed'] === 1 && $checkinDataComplete) ? 1 : 0;
                                // step 5 ต้องรอให้ step 4 (เช็คอิน) เสร็จเรียบร้อยก่อน
                                $step5 = ((int)$tenant['step_5_confirmed'] === 1 && $step4 === 1) ? 1 : 0;

                                $contractStartRaw = (string)($tenant['ctr_start'] ?? '');
                                $expectedFirstBillMonthRaw = '';
                                if ($contractStartRaw !== '' && strtotime($contractStartRaw) !== false) {
                                    // บิลเดือนแรก = เดือนเดียวกับวันเริ่มสัญญา (ไม่ใช่เดือนถัดไป)
                                    $expectedFirstBillMonthRaw = date('Y-m-01', strtotime($contractStartRaw));
                                }

                                $firstBillMonthRaw = (string)($tenant['first_exp_month'] ?? '');
                                if ($firstBillMonthRaw === '' && $expectedFirstBillMonthRaw !== '') {
                                    $firstBillMonthRaw = $expectedFirstBillMonthRaw;
                                }
                                $firstBillMonthDisplay = '-';
                                $firstBillDueReached = false;
                                if ($firstBillMonthRaw !== '' && strtotime($firstBillMonthRaw) !== false) {
                                    $firstBillMonthDisplay = thaiMonthYear($firstBillMonthRaw);
                                    $firstBillDueReached = strtotime(date('Y-m-d')) >= strtotime(date('Y-m-01', strtotime($firstBillMonthRaw)));
                                }
                                $firstExpStatus   = (string)($tenant['first_exp_status'] ?? '');
                                $firstBillPaid    = ($firstExpStatus === '1');
                                $firstBillWaiting = ($firstExpStatus === '2');
                                $firstBillOverdue = in_array($firstExpStatus, ['3', '4']);
                                $firstBillUnpaid  = in_array($firstExpStatus, ['0', '3', '4']);

                                // ใช้ latest exp เป็นหลักสำหรับแสดงสถานะล่าสุด
                                $latestExpMonthRaw = (string)($tenant['latest_exp_month'] ?? '');
                                $latestExpStatus   = (string)($tenant['latest_exp_status'] ?? '');
                                $latestMonthDisplay = ($latestExpMonthRaw !== '' && strtotime($latestExpMonthRaw) !== false)
                                    ? thaiMonthYear($latestExpMonthRaw) : $firstBillMonthDisplay;
                                $latestBillPaid    = ($latestExpStatus === '1');
                                $latestBillWaiting = ($latestExpStatus === '2');
                                $latestBillOverdue = in_array($latestExpStatus, ['3', '4']);
                                $latestBillUnpaid  = in_array($latestExpStatus, ['0', '3', '4']);

                                // Check if current month is also paid
                                $currentExpStatus = (string)($tenant['current_exp_status'] ?? '');
                                $currentBillPaid  = ($currentExpStatus === '1');
                                $currentMonthDisplay = thaiMonthYear(date('Y-m-d'));

                                // --- มิเตอร์: เช็คว่าจดเดือนบิล + เดือนก่อนไว้แล้วหรือยัง ---
                                $ctrIdInt      = (int)($tenant['ctr_id'] ?? 0);
                                $currentStepInt = (int)($tenant['current_step'] ?? 1);
                                
                                // ดึก billYearMonth จากฐานข้อมูล (เน็ต exp_month format)
                                $billYearMonth = null;
                                if ($firstBillMonthRaw !== '' && strtotime($firstBillMonthRaw) !== false) {
                                    $expMonthDt = new DateTime(date('Y-m-01', strtotime($firstBillMonthRaw)));
                                    $billYearMonth = $expMonthDt->format('Y-m');
                                }
                                
                                // prevYearMonth = null เมื่อบิลเดือนแรก = เดือนที่เริ่มสัญญา (ไม่ต้องจดมิเตอร์ก่อนหน้า)
                                $ctrStartYm = ($contractStartRaw !== '' && strtotime($contractStartRaw) !== false)
                                    ? date('Y-m', strtotime($contractStartRaw)) : null;
                                $prevYearMonth = ($billYearMonth && $ctrStartYm && $billYearMonth !== $ctrStartYm)
                                    ? date('Y-m', strtotime($billYearMonth . '-01 -1 month'))
                                    : null;
                                
                                // ตรวจสอบว่าจดมิเตอร์แล้วหรือไม่ (ใช้ batch data)
                                // __any__ = ครบทั้งน้ำและไฟอย่างน้อย 1 เดือน
                                $meterBillDone = false;
                                $meterPartialDone = false;
                                if ($step4 == 1 || $step5 == 1) {
                                    $meterBillDone = !empty($utilMonthsRecorded[$ctrIdInt]['__any__']);
                                    $meterPartialDone = !empty($utilMonthsRecorded[$ctrIdInt]['__partial__']);
                                }
                                
                                // ตรวจสอบว่าจดมิเตอร์เดือนก่อนหน้าแล้วหรือไม่ (ใช้ batch data)
                                // 'full' = ครบทั้ง 2 มิเตอร์, 'partial' = จดบางส่วน
                                $meterPrevDone = $prevYearMonth === null;
                                if (!$meterPrevDone && $prevYearMonth !== null) {
                                    $meterPrevDone = ($utilMonthsRecorded[$ctrIdInt][$prevYearMonth] ?? '') === 'full';
                                }
                                $meterPrevPartial = $prevYearMonth !== null
                                    && ($utilMonthsRecorded[$ctrIdInt][$prevYearMonth] ?? '') === 'partial';

                                // ตรวจสอบว่า billing modal จะแสดงสถานะมิเตอร์ถูกต้องหรือไม่
                                // billing modal โหลดเดือนปัจจุบันเสมอ
                                // จะไม่แสดง "ยังไม่ได้จดมิเตอร์" เมื่อ: จดมิเตอร์เดือนนี้แล้ว หรือ สัญญาเริ่มเดือนนี้ (first reading)
                                $currentYm = date('Y-m');
                                $currentMonthMeterDone = ($utilMonthsRecorded[$ctrIdInt][$currentYm] ?? '') === 'full';
                                $billingModalMeterOk = ($ctrStartYm === $currentYm) || $currentMonthMeterDone;

                                // HTML สถานะมิเตอร์ (แสดงใต้สถานะบิล สำหรับแถว ⏳ เท่านั้น)
                                $openBillingJs = "openBillingModal(" . (int)$tenant['ctr_id'] . ", "
                                    . json_encode($tenant['tnt_id']) . ", "
                                    . json_encode($tenant['tnt_name']) . ", "
                                    . json_encode($tenant['room_number']) . ", "
                                    . json_encode($tenant['type_name']) . ", "
                                    . (int)$tenant['type_price'] . ")";
                                // JS สำหรับปุ่มจดมิเตอร์
                                $openMeterJs = fn(string $ym) =>
                                    "openMeterOnlyModal("
                                    . (int)$tenant['ctr_id'] . ", "
                                    . json_encode($tenant['tnt_name']) . ", "
                                    . json_encode($tenant['room_number']) . ", "
                                    . json_encode($ym) . ")";
                                if ($meterBillDone) {
                                    // Check if it's first meter and bill status
                                    $isFirstMeter = $prevYearMonth === null;
                                    
                                    if ($isFirstMeter && $latestBillOverdue) {
                                        // Latest bill is overdue (status 3/4)
                                        $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#f87171;font-weight:600;">⚠ ค้างชำระ</span>';
                                    } elseif ($isFirstMeter && $latestBillUnpaid) {
                                        if ($billingModalMeterOk) {
                                            // billing modal จะพร้อมแสดงข้อมูลได้
                                            $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#f59e0b;font-weight:600;">รอชำระเงิน</span>';
                                        } else {
                                            // เดือนปัจจุบันยังไม่ได้จดมิเตอร์ → แสดงปุ่มจดมิเตอร์
                                            $currentDisp = thaiMonthYear($currentYm . '-01');
                                            $meterStatusHtml = '<button type="button" onclick="' . htmlspecialchars($openMeterJs($currentYm), ENT_QUOTES, 'UTF-8') . '"'
                                                . ' style="display:inline-block;margin-top:0.25rem;background:rgba(20,184,166,0.12);border:1px solid rgba(20,184,166,0.35);color:#2dd4bf;font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:12px;cursor:pointer;"'
                                                . '>📋 จดมิเตอร์ (' . htmlspecialchars($currentDisp, ENT_QUOTES, 'UTF-8') . ')</button>';
                                        }
                                    } elseif ($firstBillWaiting) {
                                        // First bill pending review — ต้องแสดง "รอตรวจสอบ" จนกว่าจะอนุมัติ
                                        $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#f59e0b;font-weight:600;">⏳ รอตรวจสอบ (' . htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8') . ')</span>';
                                    } elseif ($firstBillOverdue) {
                                        // First bill overdue
                                        $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#f87171;font-weight:600;">⚠ ค้างชำระ (' . htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8') . ')</span>';
                                    } elseif ($latestBillWaiting) {
                                        // Latest bill pending review
                                        $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#f59e0b;font-weight:600;">⏳ รอตรวจสอบ (' . htmlspecialchars($latestMonthDisplay, ENT_QUOTES, 'UTF-8') . ')</span>';
                                    } elseif ($latestBillPaid && $firstBillPaid) {
                                        // ชำระครบทุกบิลแล้ว — ✓ ชำระแล้ว แสดงเมื่อ step 5 ครบหมดเท่านั้น
                                        $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#4ade80;">✓ ชำระแล้ว</span>';
                                    } elseif ($latestBillPaid) {
                                        // Latest bill paid but first bill not yet
                                        $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#f59e0b;font-weight:600;">รอชำระ (' . htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8') . ')</span>';
                                    } else {
                                        // Default: meter recorded (subsequent months)
                                        $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#4ade80;">✓ จดมิเตอร์แล้ว</span>';
                                    }
                                } elseif (!$meterPrevDone && $prevYearMonth !== null) {
                                    $prevDisp = thaiMonthYear($prevYearMonth . '-01');
                                    $partialLabel = $meterPrevPartial ? ' ⚠ บางส่วน' : '';
                                    $meterStatusHtml = '<button type="button" onclick="' . htmlspecialchars($openMeterJs($prevYearMonth), ENT_QUOTES, 'UTF-8') . '"'
                                        . ' style="display:inline-block;margin-top:0.25rem;background:rgba(20,184,166,0.12);border:1px solid rgba(20,184,166,0.35);color:#2dd4bf;font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:12px;cursor:pointer;"'
                                        . '>📋 จดมิเตอร์ (' . htmlspecialchars($prevDisp, ENT_QUOTES, 'UTF-8') . ')' . $partialLabel . '</button>';
                                } else {
                                    $billDisp   = $firstBillMonthDisplay !== '-' ? $firstBillMonthDisplay : '';
                                    $openMeterYm = $billYearMonth ?? '';
                                    
                                    // ปล่อยให้จดมิเตอร์ได้ทันที ไม่ต้องรอถึงเดือนบิล
                                    $disabledAttr = '';
                                    $tooltipAttr = '';
                                    $buttonStyle = 'style="display:inline-block;margin-top:0.25rem;background:rgba(20,184,166,0.12);border:1px solid rgba(20,184,166,0.35);color:#2dd4bf;font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:12px;cursor:pointer;"';
                                    
                                    // ตรวจสอบว่าเป็นการจดมิเตอร์ครั้งแรก
                                    $isFirstMeter = $prevYearMonth === null;
                                    $firstMeterLabel = $isFirstMeter ? ' <span style="color:#f59e0b;font-weight:700;">(ครั้งแรก)</span>' : '';
                                    
                                    $meterStatusHtml = '<button type="button"' . ($disabledAttr ? ' ' . $disabledAttr : '') . ' ' . $buttonStyle . ($tooltipAttr ? ' ' . $tooltipAttr : '') . ' onclick="' . htmlspecialchars($openMeterJs($openMeterYm), ENT_QUOTES, 'UTF-8') . '"'
                                        . '>📋 จดมิเตอร์' . ($billDisp ? ' (' . htmlspecialchars($billDisp, ENT_QUOTES, 'UTF-8') . ')' : '') . $firstMeterLabel . '</button>';
                                }
                                // ---------------------------------------------------

                                $isCancelPending = ((string)($tenant['ctr_status'] ?? '') === '2');

                                $step5CircleClass = $step5 ? 'completed' : (($currentStep == 5) ? 'current' : 'pending');
                                $step5CircleLabel = $step5 ? '✓' : (($currentStep == 5) ? '<svg class="bill-anim" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect class="b-doc" x="5" y="2" width="14" height="18" rx="2" stroke="rgba(255,255,255,0.85)" stroke-width="1.8" fill="rgba(255,255,255,0.1)"/><line class="b-line1" x1="8" y1="7" x2="16" y2="7" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="8" stroke-dashoffset="8"/><line class="b-line2" x1="8" y1="11" x2="16" y2="11" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="8" stroke-dashoffset="8"/><line class="b-line3" x1="8" y1="15" x2="13" y2="15" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="8" stroke-dashoffset="8"/></svg>' : '5');
                                $step5Tooltip = '5. เริ่มบิลรายเดือน';

                                if ($step5) {
                                    if (!$meterBillDone) {
                                        // ยังไม่จดมิเตอร์จริงๆ — ต้องจดก่อนเสมอ ไม่ว่าบิลจะชำระแล้วหรือไม่
                                        if ($meterPrevDone && !$firstBillDueReached && $prevYearMonth !== null) {
                                            // จดมิเตอร์ต้นแล้ว (เดือนก่อน) แต่ยังไม่ถึงเดือนบิล — รอ
                                            $step5CircleClass = 'wait';
                                            $step5CircleLabel = '<svg class="wait-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="animation-direction:reverse"/></svg>';
                                            $step5Tooltip = '5. จดมิเตอร์ต้นบันทึกแล้ว - รอถึงเดือนบิล (' . $firstBillMonthDisplay . ')';
                                        } else {
                                            // ยังไม่จดมิเตอร์เดือนนี้ (หรือเดือนก่อน)
                                            $step5CircleClass = 'meter-pending';
                                            $meterSvg = '<svg class="meter-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
                                                . '<rect x="3" y="11" width="18" height="10" rx="2" stroke="#34d399" stroke-width="2"/>'
                                                . '<path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="#34d399" stroke-width="2" stroke-linecap="round"/>'
                                                . '<circle cx="12" cy="16" r="1.5" fill="#34d399"/>'
                                                . '</svg>';
                                            $step5CircleLabel = $meterSvg;
                                            $tooltipPrefix = !$meterPrevDone && $prevYearMonth !== null
                                                ? '5. จดมิเตอร์ (' . thaiMonthYear($prevYearMonth . '-01') . ')'
                                                : '5. ยังไม่จดมิเตอร์';
                                            $step5Tooltip = $tooltipPrefix . ($firstBillMonthDisplay !== '-' ? ' (' . $firstBillMonthDisplay . ')' : '');
                                        }
                                    } elseif ($firstBillPaid && $latestBillPaid) {
                                        // ชำระครบทุกบิลแล้ว — ✓ เฉพาะเมื่อบิลล่าสุดชำระแล้วด้วย
                                        $step5CircleClass = 'completed';
                                        $step5CircleLabel = '✓';
                                        $step5Tooltip = '5. ชำระแล้ว (' . $latestMonthDisplay . ')';
                                    } elseif ($latestBillOverdue || $firstBillOverdue) {
                                        $step5CircleClass = 'overdue';
                                        $step5CircleLabel = '!';
                                        $step5Tooltip = '5. บิลค้างชำระ';
                                    } elseif ($latestBillWaiting || $firstBillWaiting) {
                                        $step5CircleClass = 'wait';
                                        $step5CircleLabel = '<svg class="wait-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="animation-direction:reverse"/></svg>';
                                        $waitMonthDisp = $latestBillWaiting ? $latestMonthDisplay : $firstBillMonthDisplay;
                                        $step5Tooltip = '5. รอตรวจสอบหลักฐาน (' . $waitMonthDisp . ')';
                                    } else {
                                        // บิลยังไม่ชำระ (รอชำระ)
                                        $step5CircleClass = 'wait';
                                        $step5CircleLabel = '<svg class="wait-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="animation-direction:reverse"/></svg>';
                                        $unpaidMonthDisp = $latestMonthDisplay !== '-' ? $latestMonthDisplay : $firstBillMonthDisplay;
                                        if ($unpaidMonthDisp !== '-') {
                                            $step5Tooltip = '5. รอชำระ (' . $unpaidMonthDisp . ')';
                                        } elseif ($firstBillMonthDisplay !== '-') {
                                            $step5Tooltip = $firstBillDueReached
                                                ? '5. บิลเดือนแรก (' . $firstBillMonthDisplay . ') รอชำระ'
                                                : '5. บิลเดือนแรก (' . $firstBillMonthDisplay . ') ยังไม่ถึงกำหนด';
                                        } else {
                                            $step5Tooltip = '5. รอสร้างบิลเดือนแรก';
                                        }
                                    }
                                } else {
                                    // step5 = 0 (ยังไม่ confirm) แต่ขึ้นถึง step 5 แล้ว
                                    // ถ้ามีบิลและจดมิเตอร์แล้ว — แสดง loading แทน "5" เพื่อสื่อว่ากำลังรอชำระ
                                    if ($meterBillDone && ($latestBillUnpaid || $latestBillWaiting || $firstBillUnpaid || $firstBillWaiting)) {
                                        $step5CircleClass = 'wait';
                                        $step5CircleLabel = '<svg class="wait-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="animation-direction:reverse"/></svg>';
                                        if ($latestBillWaiting || $firstBillWaiting) {
                                            $waitDisp = $latestBillWaiting ? $latestMonthDisplay : $firstBillMonthDisplay;
                                            $step5Tooltip = '5. รอตรวจสอบหลักฐาน (' . $waitDisp . ')';
                                        } else {
                                            $unpaidDisp = $latestMonthDisplay !== '-' ? $latestMonthDisplay : $firstBillMonthDisplay;
                                            $step5Tooltip = '5. รอชำระ' . ($unpaidDisp !== '-' ? ' (' . $unpaidDisp . ')' : '');
                                        }
                                    } elseif ($latestBillOverdue || $firstBillOverdue) {
                                        $step5CircleClass = 'overdue';
                                        $step5CircleLabel = '!';
                                        $step5Tooltip = '5. บิลค้างชำระ';
                                    }
                                }

                                // Advance currentStep based on completed steps
                                // Ensure we move to the next action step based on what's completed
                                if ($step1) $currentStep = max($currentStep, 1);
                                if ($step2) $currentStep = max($currentStep, 2);
                                if ($step3) $currentStep = max($currentStep, 3);
                                
                                // If step 4 is complete, move to step 5
                                // But if step 4 is not complete and we have a contract, show step 4 as current
                                if ($step4) {
                                    $currentStep = max($currentStep, 5); // Jump to 5 since check-in is done
                                } else if ($step3 && !empty($tenant['ctr_id'])) {
                                    // Only go to step 4 if step 3 (contract) is actually completed
                                    $currentStep = max($currentStep, 4);
                                }
                                ?>
                                <tr<?php if ($isCancelPending): ?> style="background:rgba(239,68,68,0.05)!important;border-left:3px solid rgba(239,68,68,0.45);"<?php endif; ?>>
                                    <td data-label="ผู้เช่า">
                                        <div class="tenant-info">
                                            <span class="tenant-name"><?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="tenant-phone"><?php echo htmlspecialchars($tenant['tnt_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="ห้อง">
                                        <strong><?php echo htmlspecialchars($tenant['room_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <br>
                                        <span style="font-size: 0.85rem; color: #64748b;">
                                            <?php echo htmlspecialchars($tenant['type_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td data-label="สถานะ">
                                        <div class="step-indicator">
                                            <div class="step-circle <?php echo $step1 ? 'completed' : ($currentStep == 1 ? 'current' : 'pending'); ?>" data-tooltip="1. ยืนยันจอง" <?php if ($step1): ?>onclick="openBookingModal(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['room_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['type_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['type_price']; ?>, <?php echo htmlspecialchars(json_encode(thaiDate($tenant['bkg_date'])), ENT_QUOTES, 'UTF-8'); ?>, true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step1 ? '✓' : ($currentStep == 1 ? '<svg class="confirm-anim" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle class="c-ring" cx="12" cy="12" r="8" stroke="rgba(255,255,255,0.8)" stroke-width="1.8" fill="rgba(255,255,255,0.1)"/><polyline class="c-tick" points="8,12.5 11,15.5 16.5,9" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>' : '1'); ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step2 ? 'completed' : ($currentStep == 2 ? 'current' : 'pending'); ?>" data-tooltip="2. ยืนยันชำระเงินจอง" <?php if ($step2): ?>onclick="openPaymentModal(<?php echo (int)$tenant['bp_id']; ?>, <?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)($tenant['bp_amount'] ?? 0); ?>, <?php echo htmlspecialchars(json_encode($tenant['bp_proof'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step2 ? '✓' : ($currentStep == 2 ? '<svg class="payment-anim" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect class="p-slot" x="4" y="12" width="16" height="8" rx="2" stroke="rgba(255,255,255,0.8)" stroke-width="1.8" fill="rgba(255,255,255,0.1)"/><line class="p-slot" x1="7" y1="16" x2="11" y2="16" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round"/><circle class="p-coin" cx="12" cy="7" r="3.5" stroke="#fff" stroke-width="1.8" fill="rgba(255,255,255,0.15)"/><line class="p-coin" x1="12" y1="5.5" x2="12" y2="8.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/></svg>' : '2'); ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step3 ? 'completed' : ($currentStep == 3 ? 'current' : 'pending'); ?>" data-tooltip="3. สร้างสัญญา" <?php if ($step3): ?>onclick="openContractModal(<?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['room_id']; ?>, <?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['type_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)($tenant['type_price'] ?? 0); ?>, <?php echo htmlspecialchars(json_encode($tenant['bkg_checkin_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['ctr_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['ctr_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)($tenant['bp_amount'] ?? 0); ?>, <?php echo (int)($tenant['ctr_id'] ?? 0); ?>, <?php echo ((int)($tenant['has_tenant_signature'] ?? 0) > 0) ? 'true' : 'false'; ?>, true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step3 ? '✓' : ($currentStep == 3 ? '<svg class="contract-anim" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="2" width="12" height="16" rx="1.5" stroke="rgba(255,255,255,0.75)" stroke-width="1.6" fill="rgba(255,255,255,0.08)"/><line class="ct-line1" x1="7" y1="7" x2="13" y2="7" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-dasharray="10" stroke-dashoffset="10"/><line class="ct-line2" x1="7" y1="10" x2="13" y2="10" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-dasharray="10" stroke-dashoffset="10"/><line class="ct-line3" x1="7" y1="13" x2="10" y2="13" stroke="rgba(255,255,255,0.6)" stroke-width="1.4" stroke-linecap="round" stroke-dasharray="10" stroke-dashoffset="10"/><g class="ct-pen"><line x1="14" y1="15" x2="20" y2="9" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><polyline points="14,18 14,15 17,15" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></g></svg>' : '3'); ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step4 ? 'completed' : ($currentStep == 4 ? 'current' : 'pending'); ?>" data-tooltip="4. เช็คอิน" <?php if ($step4): ?>onclick="openCheckinModal(<?php echo (int)($tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode(thaiDate($tenant['ctr_start'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode(thaiDate($tenant['ctr_end'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($tenant['checkin_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($tenant['water_meter_start'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($tenant['elec_meter_start'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step4 ? '✓' : ($currentStep == 4 ? '<svg class="checkin-anim" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="9" y="2" width="10" height="20" rx="1.5" stroke="rgba(255,255,255,0.8)" stroke-width="1.8" fill="rgba(255,255,255,0.08)"/><circle cx="16.5" cy="12" r="1.2" fill="rgba(255,255,255,0.8)"/><g class="c-arrow"><line x1="2" y1="12" x2="9" y2="12" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/><polyline points="6,9 9.5,12 6,15" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g></svg>' : '4'); ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step5CircleClass; ?>" data-ctr-id="<?php echo (int)$tenant['ctr_id']; ?>" data-tooltip="<?php echo htmlspecialchars($step5Tooltip, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($step5): ?>onclick="openBillingModal(<?php echo (int)$tenant['ctr_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['type_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['type_price']; ?>)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step5CircleLabel; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="ขั้นตอนถัดไป">
                                        <?php if ($isCancelPending): ?>
                                            <div style="display:flex;flex-direction:column;align-items:flex-start;gap:0.4rem;">
                                                <div style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.28rem 0.7rem;border-radius:20px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.4);color:#f87171;font-size:0.82rem;font-weight:700;">
                                                    ⚠ ผู้เช่าแจ้งยกเลิกสัญญา
                                                </div>
                                                <a href="manage_contracts.php?ctr_id=<?php echo (int)($tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0); ?>" style="font-size:0.78rem;color:#60a5fa;text-decoration:none;font-weight:600;">จัดการสัญญา →</a>
                                            </div>
                                        <?php elseif ($tenant['workflow_id'] === null): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openBookingModal(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['room_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['type_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['type_price']; ?>, <?php echo htmlspecialchars(json_encode(thaiDate($tenant['bkg_date'])), ENT_QUOTES, 'UTF-8'); ?>)">ยืนยันการชำระการจอง</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>)">ยกเลิก</button>
                                        <?php elseif ($currentStep == 1): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openBookingModal(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['room_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['type_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['type_price']; ?>, <?php echo htmlspecialchars(json_encode(thaiDate($tenant['bkg_date'])), ENT_QUOTES, 'UTF-8'); ?>)">ยืนยันการชำระการจอง</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>)">ยกเลิก</button>
                                        <?php elseif ($currentStep == 2): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openPaymentModal(<?php echo (int)$tenant['bp_id']; ?>, <?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)($tenant['bp_amount'] ?? 0); ?>, <?php echo htmlspecialchars(json_encode($tenant['bp_proof'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">ยืนยันชำระเงินจอง</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>)">ยกเลิก</button>
                                        <?php elseif ($currentStep == 3): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openContractModal(
                                                <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo (int)$tenant['room_id']; ?>,
                                                <?php echo (int)$tenant['bkg_id']; ?>,
                                                <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo htmlspecialchars(json_encode($tenant['room_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo htmlspecialchars(json_encode($tenant['type_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo (int)($tenant['type_price'] ?? 0); ?>,
                                                <?php echo htmlspecialchars(json_encode($tenant['bkg_checkin_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo htmlspecialchars(json_encode($tenant['ctr_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo htmlspecialchars(json_encode($tenant['ctr_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo (int)($tenant['bp_amount'] ?? 0); ?>,
                                                0, false
                                            )">สร้างสัญญา</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>)">ยกเลิก</button>
                                        <?php elseif ($currentStep == 4): ?>
                                            <?php $tenantSigned = (int)($tenant['has_tenant_signature'] ?? 0) > 0; ?>
                                            <?php if ($tenantSigned): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openCheckinModal(<?php echo (int)($tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode(thaiDate($tenant['ctr_start'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode(thaiDate($tenant['ctr_end'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($tenant['checkin_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($tenant['water_meter_start'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($tenant['elec_meter_start'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>)">เช็คอิน</button>
                                            <?php else: ?>
                                            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                                            <button type="button" class="action-btn btn-primary" style="opacity:0.55;cursor:not-allowed;" title="ผู้เช่ายังไม่ได้เซ็นสัญญา" onclick="showNotSignedToast()">🔒 เช็คอิน</button>
                                            <?php if (!empty($tenant['ctr_id'])): ?>
                                            <a href="print_contract.php?ctr_id=<?php echo (int)$tenant['ctr_id']; ?>" target="_blank" class="action-btn" style="background:rgba(139,92,246,0.18);border:1px solid rgba(139,92,246,0.4);color:#c4b5fd;font-size:0.8rem;" title="เปิดสัญญาเพื่อให้ผู้เช่าเซ็น">📄 ดูสัญญา</a>
                                            <?php endif; ?>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo (int)$tenant['bkg_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>)">ยกเลิก</button>
                                            </div>
                                            <?php endif; ?>
                                        <?php elseif ($currentStep == 5 || $currentStep >= 6 || (int)($tenant['completed'] ?? 0) === 1): ?>
                                            <?php if ($step5 && $meterBillDone && $latestBillPaid && $firstBillPaid): ?>
                                                <span style="color: #16a34a; font-weight: 600;">✓ ชำระแล้ว (<?php echo htmlspecialchars($latestMonthDisplay, ENT_QUOTES, 'UTF-8'); ?>)</span>
                                            <?php elseif ($step5 && $meterBillDone && ($firstBillWaiting || $latestBillWaiting)): ?>
                                                <?php
                                                    // แสดงเดือนที่รอตรวจสอบ — first bill ก่อนเสมอ ถ้ายังค้างอยู่
                                                    $waitingMonthDisp = $firstBillWaiting ? $firstBillMonthDisplay : $latestMonthDisplay;
                                                ?>
                                                <button type="button"
                                                    onclick="openBillingModal(<?php echo (int)$tenant['ctr_id']; ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($tenant['type_name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$tenant['type_price']; ?>)"
                                                    style="background:rgba(96,165,250,0.15);border:1px solid rgba(96,165,250,0.4);color:#60a5fa;font-weight:600;font-size:0.82rem;padding:0.3rem 0.75rem;border-radius:20px;cursor:pointer;transition:background 0.2s;"
                                                    onmouseover="this.style.background='rgba(96,165,250,0.28)'" onmouseout="this.style.background='rgba(96,165,250,0.15)'"
                                                >🔍 <?php echo $waitingMonthDisp !== '-' ? '(' . htmlspecialchars($waitingMonthDisp, ENT_QUOTES, 'UTF-8') . ')' : ''; ?> รอตรวจสอบ</button>
                                            <?php elseif ($step5): ?>
                                                <div style="display:flex;flex-direction:column;align-items:flex-start;gap:0;">
                                                    <?php if ($meterBillDone): ?>
                                                    <span style="display:inline-flex;align-items:center;gap:0.35rem;color:#d97706;font-weight:600;">
                                                        <svg style="flex-shrink:0;" width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round" style="transform-origin:center;animation:waitSpin 1s linear infinite;"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="transform-origin:center;animation:waitSpin 1s linear infinite reverse;"/></svg>
                                                        <?php echo $latestMonthDisplay !== '-' ? '(' . htmlspecialchars($latestMonthDisplay, ENT_QUOTES, 'UTF-8') . ')' : ''; ?> <?php echo $firstBillDueReached ? 'รอชำระ' : 'ยังไม่ถึงกำหนด'; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php echo $meterStatusHtml; ?>
                                                </div>
                                            <?php elseif ((int)($tenant['completed'] ?? 0) === 1): ?>
                                                <span style="color: #16a34a; font-weight: 600;">ดำเนินการครบทุกขั้นตอนแล้ว</span>
                                            <?php else: ?>
                                                <?php
                                                // ใช้ $meterBillDone (__any__ flag) เพื่อรองรับกรณีที่จดมิเตอร์เดือนปัจจุบัน
                                                // แต่เดือนบิลแรกมีค่า 0 (เช็คอินด้วยค่า 0)
                                                ?>
                                                <?php if ($meterBillDone && $billingModalMeterOk): ?>
                                                    <?php
                                                        // เตรียม onclick สำหรับ billing modal
                                                        $openBillingArgs = (int)$tenant['ctr_id'] . ', '
                                                            . htmlspecialchars(json_encode($tenant['tnt_id']), ENT_QUOTES, 'UTF-8') . ', '
                                                            . htmlspecialchars(json_encode($tenant['tnt_name']), ENT_QUOTES, 'UTF-8') . ', '
                                                            . htmlspecialchars(json_encode($tenant['room_number']), ENT_QUOTES, 'UTF-8') . ', '
                                                            . htmlspecialchars(json_encode($tenant['type_name']), ENT_QUOTES, 'UTF-8') . ', '
                                                            . (int)$tenant['type_price'];
                                                    ?>
                                                    <div style="display:flex;flex-direction:column;align-items:flex-start;gap:0.35rem;">
                                                        <?php if ($firstBillWaiting || $latestBillWaiting): ?>
                                                            <?php $wDisp = $firstBillWaiting ? $firstBillMonthDisplay : $latestMonthDisplay; ?>
                                                            <button type="button" onclick="openBillingModal(<?php echo $openBillingArgs; ?>)"
                                                                style="display:inline-flex;align-items:center;gap:0.3rem;background:rgba(96,165,250,0.12);border:1px solid rgba(96,165,250,0.35);color:#60a5fa;font-size:0.75rem;font-weight:600;padding:0.25rem 0.65rem;border-radius:12px;cursor:pointer;">
                                                                🔍 รอตรวจสอบ<?php echo $wDisp !== '-' ? ' (' . htmlspecialchars($wDisp, ENT_QUOTES, 'UTF-8') . ')' : ''; ?>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (!$latestBillPaid || !$firstBillPaid): ?>
                                                            <?php
                                                                $unpaidDisp = $firstBillUnpaid && !$firstBillWaiting && $firstBillMonthDisplay !== '-'
                                                                    ? $firstBillMonthDisplay
                                                                    : ($latestMonthDisplay !== '-' && !$latestBillWaiting ? $latestMonthDisplay : '');
                                                                $unpaidLabel = $firstBillDueReached ? 'รอชำระเงิน' : 'ยังไม่ถึงกำหนด';
                                                                // ถ้าบิลรอตรวจสอบทั้งหมดอยู่แล้ว ไม่ต้องแสดงซ้ำ
                                                                $hasUnpaidNonWaiting = ($firstBillUnpaid && !$firstBillWaiting) || ($latestBillUnpaid && !$latestBillWaiting);
                                                            ?>
                                                            <?php if ($hasUnpaidNonWaiting): ?>
                                                                <button type="button" onclick="openBillingModal(<?php echo $openBillingArgs; ?>)"
                                                                    style="display:inline-flex;align-items:center;gap:0.3rem;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.35);color:#f59e0b;font-size:0.75rem;font-weight:600;padding:0.25rem 0.65rem;border-radius:12px;cursor:pointer;">
                                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/></svg>
                                                                    <?php echo $unpaidLabel; ?><?php echo $unpaidDisp !== '' ? ' (' . htmlspecialchars($unpaidDisp, ENT_QUOTES, 'UTF-8') . ')' : ''; ?>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?php echo $meterStatusHtml; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <h3>ไม่มีผู้เช่าในกระบวนการ</h3>
                        <p>เมื่อมีการจองห้องใหม่ จะแสดงรายการที่นี่</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal สำหรับยืนยันการจอง (Step 1) -->
    <div id="bookingModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <button class="modal-close" onclick="closeBookingModal()">&times;</button>
                <div style="text-align: center;">
                    <div style="width: 48px; height: 48px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">1</div>
                    <h2 style="color: #f8fafc; margin: 0.5rem 0;">ยืนยันการจอง</h2>
                    <p style="color: rgba(241, 245, 249, 0.7); margin: 0;">ตรวจสอบข้อมูลและยืนยันการจองห้องพัก</p>
                </div>
            </div>
            
            <div class="modal-body">
                <div class="info-box-modal" id="bookingInfo"></div>

                <div class="alert-box-modal" style="background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.3);">
                    <h4 style="color: #22c55e;">✓ การดำเนินการ:</h4>
                    <ul style="padding-left: 1.5rem; line-height: 1.8; color: #e2e8f0;">
                        <li>ล็อกห้องพักไม่ให้คนอื่นจองซ้ำ</li>
                        <li>สร้างยอดเงินจอง 2,000 บาท</li>
                        <li>อัปเดตสถานะผู้เช่าเป็น "จองห้อง"</li>
                        <li>บันทึก Workflow เพื่อติดตามขั้นตอนถัดไป</li>
                    </ul>
                </div>

                <form id="bookingForm" method="POST" action="../Manage/process_wizard_step1.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="bkg_id" id="modal_bkg_id">
                    <input type="hidden" name="tnt_id" id="modal_booking_tnt_id">
                    <input type="hidden" name="room_id" id="modal_room_id">
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" id="bookingCloseBtn" class="btn-modal btn-modal-secondary" onclick="closeBookingModal()">ยกเลิก</button>
                <button type="button" id="bookingSubmitBtn" class="btn-modal btn-modal-primary" style="background: #3b82f6;" onclick="submitWizardStep('bookingForm', closeBookingModal)">✓ ยืนยันการจอง</button>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับสร้างสัญญา (Step 3) -->
    <div id="contractModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <button class="modal-close" onclick="closeContractModal()">&times;</button>
                <div style="text-align: center;">
                    <div style="width: 48px; height: 48px; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);">3</div>
                    <h2 style="color: #f8fafc; margin: 0.5rem 0;">สร้างสัญญาเช่า</h2>
                    <p style="color: rgba(241, 245, 249, 0.7); margin: 0;">กำหนดรายละเอียดสัญญาและสร้างเอกสาร</p>
                </div>
            </div>
            
            <div class="modal-body">
                <div class="info-box-modal" id="contractInfo"></div>

                <form id="contractForm" method="POST" action="../Manage/process_wizard_step3.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="tnt_id" id="modal_contract_tnt_id">
                    <input type="hidden" name="room_id" id="modal_contract_room_id">
                    <input type="hidden" name="bkg_id" id="modal_contract_bkg_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>วันเริ่มสัญญา *</label>
                            <input type="date" name="ctr_start" id="modal_contract_start" required onchange="updateContractEndDate()">
                        </div>
                        <div class="form-group">
                            <label>ระยะเวลาสัญญา *</label>
                            <select name="contract_duration" id="modal_contract_duration" required onchange="updateContractEndDate()">
                                <option value="3">3 เดือน</option>
                                <option value="6" selected>6 เดือน</option>
                                <option value="12">12 เดือน</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>วันสิ้นสุดสัญญา</label>
                            <div id="modal_contract_end_display" style="padding:0.875rem 1rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.15);border-radius:10px;color:#c4b5fd;font-size:1rem;min-height:3rem;display:flex;align-items:center;">-</div>
                        </div>
                        <div class="form-group">
                            <label>เงินประกัน (บาท) *</label>
                            <input type="number" name="ctr_deposit" id="modal_contract_deposit" min="0" step="0.01" required readonly>
                        </div>
                    </div>

                    <div class="alert-box-modal" style="background: rgba(139, 92, 246, 0.1); border-color: rgba(139, 92, 246, 0.3);">
                        <h4 style="color: #c4b5fd;">📄 ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.5rem; line-height: 1.8; color: #e2e8f0;">
                            <li>บันทึกข้อมูลสัญญาลงฐานข้อมูล</li>
                            <li>สร้างไฟล์ PDF สัญญา (ถ้ามีระบบ)</li>
                            <li>อัปเดตสถานะผู้เช่าเป็น "รอเข้าพัก"</li>
                        </ul>
                    </div>
                </form>
                <!-- Section แสดงลิงก์สัญญาและสถานะการเซ็น (เติมโดย JS) -->
                <div id="contractSignatureSection" style="display:none;margin-top:1rem;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" id="contractCloseBtn" class="btn-modal btn-modal-secondary" onclick="closeContractModal()">ยกเลิก</button>
                <button type="button" id="contractSubmitBtn" class="btn-modal btn-modal-primary" style="background: #8b5cf6;" onclick="submitWizardStep('contractForm', closeContractModal)">✓ สร้างสัญญา</button>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับเช็คอิน -->
    <div id="checkinModal" class="modal-overlay">
        <div class="modal-container" style="max-width: 700px;">
            <div class="modal-header">
                <button class="modal-close" onclick="closeCheckinModal()">&times;</button>
                <div style="text-align: center;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: bold; margin: 0 auto 1rem; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);">4</div>
                    <h2 style="color: #f8fafc; margin: 0.5rem 0; font-size: 1.5rem;">🏠 เช็คอินผู้เช่า</h2>
                    <p style="color: rgba(241, 245, 249, 0.7); margin: 0;">บันทึกข้อมูลเริ่มต้นก่อนผู้เช่าเข้าพัก</p>
                </div>
            </div>
            
            <div class="modal-body">
                <!-- Tenant Info Card -->
                <div id="tenantInfo" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(99, 102, 241, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;"></div>

                <form id="checkinForm" method="POST" action="../Manage/process_wizard_step4.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="ctr_id" id="modal_ctr_id">
                    <input type="hidden" name="tnt_id" id="modal_tnt_id">

                    <!-- Validation Error Message -->
                    <div id="validationError" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; color: #fca5a5; display: none; font-size: 0.9rem;">
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">⚠️ กรุณากรอกข้อมูลให้ครบถ้วน:</div>
                        <ul id="errorList" style="margin: 0; padding-left: 1.25rem;"></ul>
                    </div>

                    <!-- Section 1: วันที่เช็คอิน -->
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                            <span style="background: #3b82f6; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">1</span>
                            <span style="font-weight: 600; color: #f1f5f9;">วันที่เช็คอิน</span>
                        </div>
                        <input type="hidden" name="checkin_date" id="checkin_date_hidden" value="<?php echo date('Y-m-d'); ?>">
                        <div style="display: grid; grid-template-columns: 1fr 2fr 1.5fr; gap: 0.5rem;">
                            <?php
                                $cd = date('Y-m-d');
                                $cd_day = (int)date('d');
                                $cd_month = (int)date('m');
                                $cd_year = (int)date('Y');
                            ?>
                            <select id="checkin_day" onchange="updateCheckinDate()" style="padding: 0.875rem 0.5rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; font-size: 1rem; width: 100%;">
                                <?php for($d=1;$d<=31;$d++): ?>
                                    <option value="<?php echo $d; ?>" <?php echo $d==$cd_day?'selected':''; ?>><?php echo $d; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="checkin_month" onchange="updateCheckinDate()" style="padding: 0.875rem 0.5rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; font-size: 1rem; width: 100%;">
                                <?php
                                $thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                                foreach($thaiMonths as $i=>$m): $mNum=$i+1;
                                    if($mNum < $cd_month) continue; ?>
                                    <option value="<?php echo $mNum; ?>" <?php echo $mNum==$cd_month?'selected':''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="checkin_year" onchange="updateCheckinDate()" style="padding: 0.875rem 0.5rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; font-size: 1rem; width: 100%;">
                                <?php for($y=$cd_year;$y<=$cd_year+5;$y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y==$cd_year?'selected':''; ?>>พ.ศ. <?php echo $y+543; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <script>
                        var thaiMonthsCI = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                        function updateCheckinDate() {
                            var dayEl = document.getElementById('checkin_day');
                            var monthEl = document.getElementById('checkin_month');
                            var yearEl = document.getElementById('checkin_year');
                            var selYear = parseInt(yearEl.value);
                            var today = new Date();
                            var todayYear = today.getFullYear();
                            var todayMonth = today.getMonth() + 1;
                            var todayDay = today.getDate();
                            // อัพเดตเดือนตามปีที่เลือก
                            var currentSelMonth = parseInt(monthEl.value);
                            var minMonth = (selYear === todayYear) ? todayMonth : 1;
                            monthEl.innerHTML = '';
                            for (var mi = 1; mi <= 12; mi++) {
                                if (mi < minMonth) continue;
                                var mopt = document.createElement('option');
                                mopt.value = mi;
                                mopt.textContent = thaiMonthsCI[mi-1];
                                if (mi === currentSelMonth) mopt.selected = true;
                                monthEl.appendChild(mopt);
                            }
                            if (currentSelMonth < minMonth) monthEl.value = minMonth;
                            var selMonth = parseInt(monthEl.value);
                            // อัพเดตวันตามเดือนและปีที่เลือก
                            var daysInMonth = new Date(selYear, selMonth, 0).getDate();
                            var minDay = (selYear === todayYear && selMonth === todayMonth) ? todayDay : 1;
                            var currentDay = parseInt(dayEl.value);
                            dayEl.innerHTML = '';
                            for (var d = minDay; d <= daysInMonth; d++) {
                                var opt = document.createElement('option');
                                opt.value = d;
                                opt.textContent = d;
                                if (d === currentDay) opt.selected = true;
                                dayEl.appendChild(opt);
                            }
                            if (currentDay < minDay || currentDay > daysInMonth) dayEl.value = minDay;
                            var dd = String(dayEl.value).padStart(2,'0');
                            var mm = String(selMonth).padStart(2,'0');
                            document.getElementById('checkin_date_hidden').value = selYear+'-'+mm+'-'+dd;
                        }
                        document.addEventListener('DOMContentLoaded', updateCheckinDate);
                        </script>
                    </div>

                    <!-- Summary Box -->
                    <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(234, 88, 12, 0.08)); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 1rem 1.25rem;">
                        <h4 style="margin: 0 0 0.75rem 0; color: #fbbf24; font-size: 1rem;">✅ ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.25rem; margin: 0; line-height: 1.8; color: #e2e8f0; font-size: 0.9rem;">
                            <li>อัปเดตสถานะห้อง → <span style="color: #4ade80;">"มีผู้เช่า"</span></li>
                            <li>อัปเดตสถานะผู้เช่า → <span style="color: #4ade80;">"พักอยู่"</span></li>
                        </ul>
                    </div>
                </form>
            </div>

            <div class="modal-footer" style="display: flex; gap: 1rem; justify-content: flex-end; padding: 1.5rem 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="button" id="checkinCloseBtn" class="btn-modal btn-modal-secondary" onclick="closeCheckinModal()" style="padding: 0.875rem 1.5rem;">ยกเลิก</button>
                <button type="button" id="checkinSubmitBtn" class="btn-modal btn-modal-primary" onclick="validateAndSubmitCheckinAjax()" style="padding: 0.875rem 2rem; background: linear-gradient(135deg, #f59e0b, #d97706); font-weight: 600;">🏠 บันทึกเช็คอิน</button>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับเริ่มบิลรายเดือน (Step 5) -->
    <div id="billingModal" class="modal-overlay">
        <div class="modal-container" style="max-width: 640px;">

            <!-- Header -->
            <div style="display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.1); background:linear-gradient(135deg,rgba(34,197,94,0.15),rgba(59,130,246,0.1));">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <div id="billingModalIcon" style="width:40px; height:40px; border-radius:50%; background:#22c55e; display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; color:#fff; flex-shrink:0;">5</div>
                    <div>
                        <div id="billingModalTitle" style="font-size:1.05rem; font-weight:700; color:#f8fafc;">บิลรายเดือน</div>
                        <div id="billingModalSub" style="font-size:0.8rem; color:rgba(226,232,240,0.7); margin-top:1px;"></div>
                    </div>
                </div>
                <button type="button" onclick="closeBillingModal()" style="background:rgba(255,255,255,0.08); border:none; color:rgba(255,255,255,0.7); font-size:1.3rem; width:34px; height:34px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">&times;</button>
            </div>

            <div class="modal-body" style="padding:1.25rem 1.5rem;">

                <!-- Tenant + rates bar -->
                <div style="display:flex; flex-wrap:wrap; gap:0.6rem; margin-bottom:1.25rem; padding:0.85rem 1rem; background:rgba(255,255,255,0.04); border-radius:10px; border:1px solid rgba(255,255,255,0.08); font-size:0.85rem; color:rgba(226,232,240,0.9); align-items:center;">
                    <span id="billingBarTenant" style="font-weight:600; color:#f8fafc;"></span>
                    <span style="color:rgba(255,255,255,0.25);">|</span>
                    <span id="billingBarRoom" style="color:#93c5fd;"></span>
                    <span style="color:rgba(255,255,255,0.25);">|</span>
                    <span class="billing-inline-icon" style="color:#60a5fa;">
                        <svg class="billing-svg-icon billing-svg-water" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3C9 7 6 9.8 6 14a6 6 0 0 0 12 0c0-4.2-3-7-6-11z"></path>
                        </svg>
                        <span id="waterRateDisplay" style="color:#60a5fa;">-</span>
                    </span>
                    <span style="color:rgba(255,255,255,0.25);">|</span>
                    <span class="billing-inline-icon" style="color:#fbbf24;">
                        <svg class="billing-svg-icon billing-svg-elec" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"></path>
                        </svg>
                        <span id="elecRateDisplay" style="color:#fbbf24;">-</span>
                    </span>
                    <span style="color:rgba(255,255,255,0.25);">|</span>
                    <span class="billing-inline-icon" style="color:#4ade80;">
                        <svg class="billing-svg-icon billing-svg-cal" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                            <path d="M16 3v4M8 3v4M3 10h18"></path>
                        </svg>
                        <span>รอบแรก: <span id="nextMonthDisplay" style="color:#4ade80; font-weight:600;">-</span></span>
                    </span>
                </div>

                <!-- Hidden fields -->
                <form id="billingForm" method="POST" action="../Manage/process_wizard_step5.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="ctr_id" id="modal_billing_ctr_id">
                    <input type="hidden" name="tnt_id" id="modal_billing_tnt_id">
                    <input type="hidden" name="room_price" id="modal_billing_room_price">
                    <input type="hidden" name="rate_water" id="modal_billing_rate_water">
                    <input type="hidden" name="rate_elec" id="modal_billing_rate_elec">
                </form>

                <!-- Meter reading section -->
                <div id="meterSection" style="margin-bottom:1.1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.55rem;">
                        <span class="billing-inline-icon" style="font-size:0.88rem;font-weight:700;color:#fbbf24;">
                            <svg class="billing-svg-icon billing-svg-meter" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 20h16"></path>
                                <rect x="6" y="11" width="2.8" height="6" rx="1"></rect>
                                <rect x="10.6" y="8" width="2.8" height="9" rx="1"></rect>
                                <rect x="15.2" y="5" width="2.8" height="12" rx="1"></rect>
                            </svg>
                            <span>จดมิเตอร์เดือนนี้</span>
                        </span>
                        <span id="meterSavedBadge" style="display:none;padding:0.15rem 0.55rem;border-radius:20px;background:rgba(34,197,94,0.15);color:#4ade80;font-size:0.75rem;font-weight:600;">✓ บันทึกแล้ว</span>
                    </div>
                    <div id="meterBody" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);border-radius:10px;padding:0.9rem 1rem;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                            <!-- Water -->
                            <div>
                                <div class="billing-inline-icon" style="font-size:0.75rem;color:rgba(148,163,184,0.8);margin-bottom:0.3rem;">
                                    <svg class="billing-svg-icon billing-svg-water" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 3C9 7 6 9.8 6 14a6 6 0 0 0 12 0c0-4.2-3-7-6-11z"></path>
                                    </svg>
                                    <span>มิเตอร์น้ำ (ครั้งก่อน: <span id="prevWaterDisplay">-</span>)</span>
                                </div>
                                <input type="number" id="meterWaterInput" min="0" max="9999999" placeholder="เลขมิเตอร์ใหม่"
                                    style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.6);border:1px solid rgba(96,165,250,0.4);border-radius:7px;color:#f8fafc;padding:0.5rem 0.65rem;font-size:0.9rem;outline:none;"
                                    oninput="if(this.value.length > 7) this.value = this.value.slice(0, 7); updateMeterPreview()">
                            </div>
                            <!-- Elec -->
                            <div>
                                <div class="billing-inline-icon" style="font-size:0.75rem;color:rgba(148,163,184,0.8);margin-bottom:0.3rem;">
                                    <svg class="billing-svg-icon billing-svg-elec" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"></path>
                                    </svg>
                                    <span>มิเตอร์ไฟ (ครั้งก่อน: <span id="prevElecDisplay">-</span>)</span>
                                </div>
                                <input type="number" id="meterElecInput" min="0" max="99999" placeholder="เลขมิเตอร์ใหม่"
                                    style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.6);border:1px solid rgba(251,191,36,0.4);border-radius:7px;color:#f8fafc;padding:0.5rem 0.65rem;font-size:0.9rem;outline:none;"
                                    oninput="if(this.value.length > 5) this.value = this.value.slice(0, 5); updateMeterPreview()">
                            </div>
                        </div>
                        <!-- preview -->
                        <div id="meterPreview" style="display:none;margin-top:0.65rem;padding:0.5rem 0.75rem;border-radius:7px;background:rgba(15,23,42,0.4);border:1px solid rgba(255,255,255,0.07);font-size:0.82rem;color:rgba(226,232,240,0.85);display:flex;gap:1rem;flex-wrap:wrap;"></div>
                        <div style="display:flex;align-items:center;gap:0.6rem;margin-top:0.7rem;">
                            <button type="button" id="saveMeterBtn" onclick="saveMeterReading()"
                                style="padding:0.45rem 1.2rem;border:none;border-radius:7px;background:#d97706;color:#fff;cursor:pointer;font-size:0.85rem;font-weight:600;transition:background 0.2s;"
                                onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#d97706'">
                                บันทึกมิเตอร์
                            </button>
                            <span id="meterMsg" style="font-size:0.82rem;"></span>
                        </div>
                    </div>
                </div>

                <!-- Bill sections (hidden until meter is confirmed) -->
                <div id="meterNoticeBlock" style="display:none;padding:0.75rem 1rem;border-radius:10px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);color:#fbbf24;font-size:0.85rem;font-weight:600;margin-bottom:0.85rem;text-align:center;">
                    <span class="billing-inline-icon">
                        <svg class="billing-svg-icon billing-svg-warning" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3 2 21h20L12 3z"></path>
                            <path d="M12 9v5M12 18h.01"></path>
                        </svg>
                        <span>กรุณาจดมิเตอร์ก่อน เพื่อดูรายการบิล</span>
                    </span>
                </div>
                <div id="billSectionsWrapper" style="display:none; max-height:300px; overflow-y:auto; scrollbar-width:thin; scrollbar-color:rgba(148,163,184,0.35) transparent;">
                    <div id="firstBillPaymentsSection" style="margin-bottom:0.85rem; color:#e2e8f0;"></div>
                    <div id="latestBillPaymentsSection" style="color:#e2e8f0;"></div>
                </div>

            </div>

            <div style="padding:1rem 1.5rem; border-top:1px solid rgba(255,255,255,0.08); display:flex; justify-content:flex-end;">
                <button type="button" onclick="closeBillingModal()" style="padding:0.6rem 1.5rem; border:1px solid rgba(255,255,255,0.2); border-radius:8px; background:transparent; color:rgba(226,232,240,0.85); cursor:pointer; font-size:0.9rem; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='transparent'">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Modal จดมิเตอร์ (Standalone) -->
    <div id="meterOnlyModal" class="modal-overlay">
        <div class="modal-container" style="max-width:420px;overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid rgba(255,255,255,0.1);background:linear-gradient(135deg,rgba(5,150,105,0.2),rgba(16,185,129,0.08));">
                <div style="display:flex;align-items:center;gap:0.65rem;">
                    <div style="width:38px;height:38px;border-radius:50%;background:#059669;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="10" rx="2" stroke="#fff" stroke-width="2"/><path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="#fff" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1.5" fill="#fff"/></svg>
                    </div>
                    <div>
                        <div style="font-size:1rem;font-weight:700;color:#f8fafc;">จดมิเตอร์</div>
                        <div id="moHeaderSub" style="font-size:0.78rem;color:rgba(226,232,240,0.7);"></div>
                    </div>
                </div>
                <button type="button" onclick="closeMeterOnlyModal()" style="background:rgba(255,255,255,0.08);border:none;color:rgba(255,255,255,0.7);font-size:1.3rem;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='rgba(255,255,255,0.18)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">&times;</button>
            </div>
            <div class="modal-body" style="padding:1.25rem 1.4rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;margin-bottom:0.75rem;">
                    <div>
                        <div style="font-size:0.8rem;font-weight:600;color:#60a5fa;margin-bottom:0.3rem;">💧 มิเตอร์น้ำ</div>
                        <div style="font-size:0.72rem;color:rgba(148,163,184,0.8);margin-bottom:0.4rem;">ค่าก่อน: <span id="moPrevWater" style="color:#f8fafc;font-weight:600;">...</span></div>
                        <input type="number" id="moWaterInput" min="0" max="9999999" placeholder="เลขมิเตอร์ใหม่"
                            style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.7);border:1px solid rgba(96,165,250,0.4);border-radius:8px;color:#f8fafc;padding:0.55rem 0.7rem;font-size:0.95rem;outline:none;"
                            oninput="if(this.value.length > 7) this.value = this.value.slice(0, 7)"
                            onfocus="this.style.borderColor='#60a5fa'" onblur="this.style.borderColor='rgba(96,165,250,0.4)'" oninput="updateMoPreview()">
                    </div>
                    <div>
                        <div style="font-size:0.8rem;font-weight:600;color:#fbbf24;margin-bottom:0.3rem;">⚡ มิเตอร์ไฟ</div>
                        <div style="font-size:0.72rem;color:rgba(148,163,184,0.8);margin-bottom:0.4rem;">ค่าก่อน: <span id="moPrevElec" style="color:#f8fafc;font-weight:600;">...</span></div>
                        <input type="number" id="moElecInput" min="0" max="99999" placeholder="เลขมิเตอร์ใหม่"
                            style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.7);border:1px solid rgba(251,191,36,0.4);border-radius:8px;color:#f8fafc;padding:0.55rem 0.7rem;font-size:0.95rem;outline:none;"
                            oninput="if(this.value.length > 5) this.value = this.value.slice(0, 5)"
                            onfocus="this.style.borderColor='#fbbf24'" onblur="this.style.borderColor='rgba(251,191,36,0.4)'" oninput="updateMoPreview()">
                    </div>
                </div>
                <div id="moPreview" style="display:none;flex-wrap:wrap;gap:0.75rem;padding:0.6rem 0.75rem;border-radius:8px;background:rgba(15,23,42,0.4);border:1px solid rgba(255,255,255,0.08);font-size:0.82rem;color:rgba(226,232,240,0.9);margin-bottom:0.85rem;"></div>
                <div id="moFirstReadingMsg" style="display:none;padding:0.6rem 0.75rem;border-radius:8px;background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.25);font-size:0.82rem;color:#4ade80;margin-bottom:0.85rem;font-weight:600;">
                    ℹ️ จดมิเตอร์ครั้งแรก — ไม่มีค่าใช้จ่าย (เฉพาะค่าห้อง)
                </div>
                <div style="display:flex;align-items:center;gap:0.65rem;">
                    <button type="button" id="moSaveBtn" onclick="saveMeterOnly()"
                        style="padding:0.55rem 1.4rem;border:none;border-radius:8px;background:#059669;color:#fff;cursor:pointer;font-size:0.9rem;font-weight:700;transition:background 0.2s;"
                        onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'">
                        ✓ บันทึกมิเตอร์
                    </button>
                    <button type="button" onclick="closeMeterOnlyModal()"
                        style="padding:0.55rem 1rem;border:1px solid rgba(255,255,255,0.18);border-radius:8px;background:transparent;color:rgba(226,232,240,0.8);cursor:pointer;font-size:0.88rem;transition:background 0.2s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.07)'" onmouseout="this.style.background='transparent'">ยกเลิก</button>
                    <span id="moMsg" style="font-size:0.82rem;"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับยืนยันชำระเงินจอง (Step 2) -->
    <div id="paymentModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <button class="modal-close" onclick="closePaymentModal()">&times;</button>
                <div style="text-align: center;">
                    <div style="width: 48px; height: 48px; background: #22c55e; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);">2</div>
                    <h2 style="color: #f8fafc; margin: 0.5rem 0;">ยืนยันการชำระเงินจอง</h2>
                    <p style="color: rgba(241, 245, 249, 0.7); margin: 0;">ตรวจสอบหลักฐานและยืนยันการชำระเงินจอง</p>
                </div>
            </div>
            
            <div class="modal-body">
                <div class="info-box-modal" id="paymentInfo"></div>
                <div id="paymentProofContainer" style="margin: 1rem 0; text-align: center; display: none;">
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 0.5rem;">หลักฐานการชำระเงิน:</p>
                    <a id="paymentProofLink" href="#" target="_blank">
                        <img id="paymentProofImg" src="" style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2);">
                    </a>
                </div>

                <div class="alert-box-modal" style="background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.3);">
                    <h4 style="color: #22c55e;">✓ การดำเนินการ:</h4>
                    <ul style="padding-left: 1.5rem; line-height: 1.8; color: #e2e8f0;">
                        <li>บันทึกวันที่ชำระเงินจอง</li>
                        <li>สร้างเลขที่ใบเสร็จอัตโนมัติ</li>
                        <li>ทำเครื่องหมายการชำระเงินเสร็จสิ้น</li>
                        <li>พร้อมสำหรับขั้นตอนถัดไป: สร้างสัญญา</li>
                    </ul>
                </div>

                <form id="paymentForm" method="POST" action="../Manage/process_wizard_step2.php">
                    <input type="hidden" name="bp_id" id="modal_payment_bp_id">
                    <input type="hidden" name="bkg_id" id="modal_payment_bkg_id">
                    <input type="hidden" name="tnt_id" id="modal_payment_tnt_id">
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" id="paymentCloseBtn" class="btn-modal btn-modal-secondary" onclick="closePaymentModal()">ยกเลิก</button>
                <button type="button" id="paymentSubmitBtn" class="btn-modal btn-modal-primary" style="background: #22c55e;" onclick="submitWizardStep('paymentForm', closePaymentModal)">✓ ยืนยันการชำระเงิน</button>
            </div>
        </div>
    </div>

<!-- เพิ่ม JavaScript นี้ก่อน </body> -->
<script>
    function openCheckinModal(ctrId, tntId, tntName, roomNumber, ctrStart, ctrEnd, checkinDate = '', waterMeter = '', elecMeter = '', readOnly = false) {
        document.getElementById('modal_ctr_id').value = ctrId;
        document.getElementById('modal_tnt_id').value = tntId;

        const normalizeDateInput = (rawDate) => {
            if (!rawDate) return '';
            const dateStr = String(rawDate).trim();
            if (!dateStr) return '';
            const yyyyMmDd = dateStr.slice(0, 10);
            if (/^\d{4}-\d{2}-\d{2}$/.test(yyyyMmDd)) {
                return yyyyMmDd;
            }
            const parsed = new Date(dateStr);
            if (Number.isNaN(parsed.getTime())) {
                return '';
            }
            const y = parsed.getFullYear();
            const m = String(parsed.getMonth() + 1).padStart(2, '0');
            const d = String(parsed.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const form = document.getElementById('checkinForm');
        const normalizedCheckinDate = normalizeDateInput(checkinDate);
        const today = new Date();
        const todayValue = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

        form.checkin_date.value = normalizedCheckinDate || todayValue;

        const closeBtn = document.getElementById('checkinCloseBtn');
        const submitBtn = document.getElementById('checkinSubmitBtn');
        closeBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        submitBtn.style.display = readOnly ? 'none' : 'inline-block';

        // โหมดดูอย่างเดียว: ปิดการแก้ไขทุก field ยกเว้น hidden
        form.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.type === 'hidden') return;
            field.disabled = readOnly;
        });

        // Format dates to Thai format
        const formatDate = (dateStr) => {
            const date = new Date(dateStr);
            const months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear() + 543; // Thai Buddhist year
            return `${day} ${month} ${year}`;
        };
        
        document.getElementById('tenantInfo').innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; color: #e2e8f0;">
                <div>
                    <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.25rem;">👤 ชื่อผู้เช่า</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #60a5fa;">${tntName}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.25rem;">🚪 เลขห้อง</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #60a5fa;">${roomNumber}</div>
                </div>
            </div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(96, 165, 250, 0.3);">
                <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.25rem;">📋 ระยะเวลาสัญญา</div>
                <div style="font-size: 0.95rem; color: #cbd5e1;">
                    <span style="color: #4ade80;">✓ ${ctrStart}</span> 
                    <span style="color: #94a3b8;"> ถึง </span>
                    <span style="color: #f87171;">${ctrEnd}</span>
                </div>
            </div>
        `;
        
        document.getElementById('checkinModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function validateAndSubmitCheckin() {
        const form = document.getElementById('checkinForm');
        const errorContainer = document.getElementById('validationError');
        const errorList = document.getElementById('errorList');
        const errors = [];

        // Validate วันที่เช็คอิน
        const checkinDate = form.checkin_date.value.trim();
        if (!checkinDate) {
            errors.push('กรุณาระบุวันที่เช็คอิน');
        } else {
            const date = new Date(checkinDate);
            if (isNaN(date.getTime())) {
                errors.push('วันที่เช็คอิน ไม่ถูกต้อง');
            }
        }

        // Display errors or submit
        if (errors.length > 0) {
            errorList.innerHTML = errors.map(err => `<li>${err}</li>`).join('');
            errorContainer.style.display = 'block';
            // Scroll to error
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            errorContainer.style.display = 'none';
            form.submit();
        }
    }

    function closeCheckinModal() {
        document.getElementById('checkinModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        const form = document.getElementById('checkinForm');
        form.reset();
        form.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.type === 'hidden') return;
            field.disabled = false;
        });
        document.getElementById('checkinCloseBtn').textContent = 'ยกเลิก';
        document.getElementById('checkinSubmitBtn').style.display = 'inline-block';
        document.getElementById('validationError').style.display = 'none';
    }

    // ปิด modal เมื่อคลิกนอก modal
    document.getElementById('checkinModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCheckinModal();
        }
    });

    document.getElementById('contractModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeContractModal();
        }
    });

    document.getElementById('billingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBillingModal();
        }
    });

    // ปิด modal เมื่อกด ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeContractModal();
            closeCheckinModal();
            closeBillingModal();
        }
    });

    // Functions สำหรับ Booking Modal
    function openBookingModal(bkgId, tntId, roomId, tntName, tntPhone, roomNumber, typeName, typePrice, bkgDate, readOnly = false) {
        document.getElementById('modal_bkg_id').value = bkgId;
        document.getElementById('modal_booking_tnt_id').value = tntId;
        document.getElementById('modal_room_id').value = roomId;

        const bookingSubmitBtn = document.getElementById('bookingSubmitBtn');
        const bookingCloseBtn = document.getElementById('bookingCloseBtn');
        bookingSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
        bookingCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        
        document.getElementById('bookingInfo').innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ผู้เช่า</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${tntName}</div>
                    <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">${tntPhone}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ห้องพัก</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${roomNumber}</div>
                    <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">${typeName}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ราคา</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #3b82f6;">฿${Number(typePrice).toLocaleString()}/เดือน</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">วันที่จอง</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${bkgDate}</div>
                </div>
            </div>
        `;
        
        document.getElementById('bookingModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function closeBookingModal() {
        document.getElementById('bookingModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
    }

    // Functions สำหรับ Contract Modal (Step 3)
    function showNotSignedToast() {
        if (typeof showErrorToast === 'function') {
            showErrorToast('ผู้เช่ายังไม่ได้เซ็นสัญญา กรุณาให้ผู้เช่าเซ็นสัญญาก่อนทำการเช็คอิน');
        } else {
            alert('ผู้เช่ายังไม่ได้เซ็นสัญญา กรุณาให้ผู้เช่าเซ็นสัญญาก่อนทำการเช็คอิน');
        }
    }

    function openContractModal(tntId, roomId, bkgId, tntName, roomNumber, typeName, typePrice, bkgCheckinDate, ctrStart, ctrEnd, bookingAmount, ctrId = 0, hasSigned = false, readOnly = false) {
        document.getElementById('modal_contract_tnt_id').value = tntId;
        document.getElementById('modal_contract_room_id').value = roomId;
        document.getElementById('modal_contract_bkg_id').value = bkgId;

        const toDateInputValue = (rawDate) => {
            if (!rawDate) return '';
            const dateStr = String(rawDate).trim();
            if (!dateStr) return '';

            const isValidYyyyMmDd = (value) => {
                if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
                if (value === '0000-00-00') return false;
                const parsed = new Date(`${value}T00:00:00`);
                if (Number.isNaN(parsed.getTime())) return false;
                const y = parsed.getFullYear();
                const m = String(parsed.getMonth() + 1).padStart(2, '0');
                const d = String(parsed.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}` === value;
            };

            // รองรับทั้งรูปแบบ YYYY-MM-DD และ YYYY-MM-DD HH:MM:SS
            const yyyyMmDd = dateStr.slice(0, 10);
            if (isValidYyyyMmDd(yyyyMmDd)) {
                return yyyyMmDd;
            }

            const parsed = new Date(dateStr);
            if (Number.isNaN(parsed.getTime())) {
                return '';
            }

            const y = parsed.getFullYear();
            const m = String(parsed.getMonth() + 1).padStart(2, '0');
            const d = String(parsed.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const contractSubmitBtn = document.getElementById('contractSubmitBtn');
        const contractCloseBtn = document.getElementById('contractCloseBtn');
        const contractStartInput = document.getElementById('modal_contract_start');
        const contractDurationInput = document.getElementById('modal_contract_duration');

        contractSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
        contractCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        contractStartInput.disabled = false;
        contractStartInput.readOnly = readOnly;
        contractStartInput.style.pointerEvents = readOnly ? 'none' : '';
        contractDurationInput.disabled = readOnly;

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const defaultStart = `${yyyy}-${mm}-${dd}`;

        // ถ้ามีสัญญาอยู่แล้ว (ctrStart) ให้ใช้วันที่จากสัญญาเสมอ
        // ถ้ายังไม่มีสัญญา ให้ใช้ bkgCheckinDate เป็นค่าแนะนำ
        const startDate = toDateInputValue(ctrStart) || toDateInputValue(bkgCheckinDate) || defaultStart;
        document.getElementById('modal_contract_start').value = startDate;

        let durationMonths = 6;
        if (ctrStart && ctrEnd) {
            const start = new Date(ctrStart);
            const end = new Date(ctrEnd);
            if (!isNaN(start) && !isNaN(end)) {
                const months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
                if (months > 0) durationMonths = months;
            }
        }
        const durationSelect = document.getElementById('modal_contract_duration');
        if ([3, 6, 12].includes(durationMonths)) {
            durationSelect.value = String(durationMonths);
        } else {
            durationSelect.value = '6';
        }

        const depositValue = Number(bookingAmount) > 0 ? Number(bookingAmount) : 2000;
        document.getElementById('modal_contract_deposit').value = depositValue;

        document.getElementById('contractInfo').innerHTML = `
            <p><strong style="color: #a78bfa;">ผู้เช่า:</strong> ${tntName}</p>
            <p><strong style="color: #a78bfa;">ห้อง:</strong> ${roomNumber} (${typeName})</p>
            <p><strong style="color: #a78bfa;">ค่าห้อง:</strong> ฿${Number(typePrice).toLocaleString()}/เดือน</p>
        `;

        // แสดงส่วน signature ถ้ามี ctrId
        const sigSection = document.getElementById('contractSignatureSection');
        if (ctrId > 0) {
            let sigHtml;
            if (hasSigned) {
                sigHtml = '<div style="padding:0.75rem 1rem;border-radius:10px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);display:flex;align-items:center;gap:0.6rem;">'
                    + '<span style="font-size:1.2rem;">\u2705</span>'
                    + '<div>'
                    + '<div style="color:#22c55e;font-weight:600;font-size:0.9rem;">\u0e1c\u0e39\u0e49\u0e40\u0e0a\u0e48\u0e32\u0e40\u0e0b\u0e47\u0e19\u0e2a\u0e31\u0e0d\u0e0d\u0e32\u0e41\u0e25\u0e49\u0e27</div>'
                    + '<div style="color:#64748b;font-size:0.8rem;">\u0e2a\u0e32\u0e21\u0e32\u0e23\u0e16\u0e14\u0e33\u0e40\u0e19\u0e34\u0e19\u0e01\u0e32\u0e23\u0e40\u0e0a\u0e47\u0e04\u0e2d\u0e34\u0e19\u0e44\u0e14\u0e49</div>'
                    + '</div>'
                    + '<a href="print_contract.php?ctr_id=' + ctrId + '" target="_blank" style="margin-left:auto;font-size:0.82rem;color:#38bdf8;text-decoration:none;">\ud83d\udcc4 \u0e14\u0e39\u0e2a\u0e31\u0e0d\u0e0d\u0e32</a>'
                    + '</div>';
            } else {
                sigHtml = '<div style="padding:0.8rem 1rem;border-radius:10px;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.3);">'
                    + '<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.45rem;">'
                    + '<span style="font-size:1.1rem;">\u270d\ufe0f</span>'
                    + '<span style="color:#fbbf24;font-weight:600;font-size:0.9rem;">\u0e23\u0e2d\u0e1c\u0e39\u0e49\u0e40\u0e0a\u0e48\u0e32\u0e40\u0e0b\u0e47\u0e19\u0e2a\u0e31\u0e0d\u0e0d\u0e32</span>'
                    + '</div>'
                    + '<div style="font-size:0.82rem;color:#94a3b8;margin-bottom:0.65rem;">'
                    + '\u0e43\u0e2b\u0e49\u0e1c\u0e39\u0e49\u0e40\u0e0a\u0e48\u0e32\u0e40\u0e1b\u0e34\u0e14\u0e25\u0e34\u0e07\u0e01\u0e4c\u0e14\u0e49\u0e32\u0e19\u0e25\u0e48\u0e32\u0e07\u0e41\u0e25\u0e30\u0e40\u0e0b\u0e47\u0e19\u0e0a\u0e37\u0e48\u0e2d \u0e08\u0e36\u0e07\u0e08\u0e30\u0e2a\u0e32\u0e21\u0e32\u0e23\u0e16\u0e40\u0e0a\u0e47\u0e04\u0e2d\u0e34\u0e19\u0e44\u0e14\u0e49'
                    + '</div>'
                    + '<a href="print_contract.php?ctr_id=' + ctrId + '" target="_blank" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.45rem 1rem;border-radius:7px;background:rgba(139,92,246,0.18);border:1px solid rgba(139,92,246,0.45);color:#c4b5fd;font-size:0.85rem;font-weight:500;text-decoration:none;">'
                    + '\ud83d\udcc4 \u0e40\u0e1b\u0e34\u0e14\u0e2a\u0e31\u0e0d\u0e0d\u0e32\u0e2a\u0e33\u0e2b\u0e23\u0e31\u0e1a\u0e40\u0e0b\u0e47\u0e19</a>'
                    + '</div>';
            }
            sigSection.innerHTML = sigHtml;
            sigSection.style.display = 'block';
        } else {
            sigSection.style.display = 'none';
        }

        document.getElementById('contractModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
        updateContractEndDate();
    }

    function updateContractEndDate() {
        const startVal = document.getElementById('modal_contract_start').value;
        const durationVal = parseInt(document.getElementById('modal_contract_duration').value) || 0;
        const endDisplay = document.getElementById('modal_contract_end_display');
        if (!startVal || !durationVal) { endDisplay.textContent = '-'; return; }
        const start = new Date(startVal + 'T00:00:00');
        if (isNaN(start.getTime())) { endDisplay.textContent = '-'; return; }
        const end = new Date(start);
        end.setMonth(end.getMonth() + durationVal);
        end.setDate(end.getDate() - 1);
        endDisplay.textContent = end.toLocaleDateString('th-TH', {year:'numeric', month:'long', day:'numeric'});
    }

    function closeContractModal() {
        document.getElementById('contractModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        const contractStartInput = document.getElementById('modal_contract_start');
        contractStartInput.readOnly = false;
        contractStartInput.style.pointerEvents = '';
        document.getElementById('modal_contract_duration').disabled = false;
        document.getElementById('contractCloseBtn').textContent = 'ยกเลิก';
        document.getElementById('contractSubmitBtn').style.display = 'inline-block';
        document.getElementById('contractForm').reset();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatMonthDisplay(dateValue) {
        if (!dateValue) return '-';
        const date = new Date(dateValue);
        if (Number.isNaN(date.getTime())) return '-';
        const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        return `${monthNames[date.getMonth()]} ${date.getFullYear() + 543}`;
    }

    function getBillRemarkText(rawRemark, monthText, fallbackPrefix = 'ชำระบิล') {
        const remark = String(rawRemark || '').trim();
        if (remark !== '') {
            return escapeHtml(remark);
        }
        return escapeHtml(`${fallbackPrefix} (${monthText})`);
    }

    function renderBillSection(containerId, title, billPayload, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const allowReviewAction = options.allowReviewAction === true;
        const emptyHint = options.emptyHint || 'ยังไม่มีข้อมูล';
        const monthText = formatMonthDisplay(billPayload?.bill_month || '');

        if (!billPayload?.has_expense) {
            container.innerHTML = `
                <div style="padding:1rem; background:rgba(255,255,255,0.04); border-radius:10px; border:1px solid rgba(255,255,255,0.08); text-align:center; color:rgba(148,163,184,0.8); font-size:0.88rem;">
                    ${escapeHtml(emptyHint)}
                </div>`;
            return;
        }

        const expenseTotal   = Number(billPayload.expense_total   || 0);
        const approvedAmount = Number(billPayload.approved_amount  || 0);
        const pendingAmount  = Number(billPayload.pending_amount   || 0);
        const remainAmount   = Math.max(expenseTotal - approvedAmount, 0);
        const expenseId      = Number(billPayload.expense_id       || 0);
        const payments       = Array.isArray(billPayload.payments) ? billPayload.payments : [];

        // เลือกสีสถานะบิล
        const statusText = billPayload?.expense_status_text || '-';
        const statusClr  = billPayload?.expense_status === '1' ? '#4ade80'
                         : billPayload?.expense_status === '2' ? '#fbbf24'
                         : billPayload?.expense_status === '3' ? '#f97316'
                         : billPayload?.expense_status === '4' ? '#ef4444'
                         : '#94a3b8';

        // progress bar
        const pct = expenseTotal > 0 ? Math.min((approvedAmount / expenseTotal) * 100, 100) : 0;
        const barColor = pct >= 100 ? '#4ade80' : pct > 0 ? '#fbbf24' : '#475569';

        // payment rows
        const paymentRows = payments.length
            ? payments.map((pay) => {
                const payId    = Number(pay.pay_id    || 0);
                const amount   = Number(pay.pay_amount || 0);
                const payStatus = String(pay.pay_status || '0');
                const statusBadge = payStatus === '1'
                    ? `<span style="display:inline-block;padding:0.2rem 0.55rem;border-radius:20px;background:rgba(34,197,94,0.15);color:#4ade80;font-size:0.78rem;font-weight:600;">✓ อนุมัติแล้ว</span>`
                    : `<span style="display:inline-block;padding:0.2rem 0.55rem;border-radius:20px;background:rgba(245,158,11,0.15);color:#fbbf24;font-size:0.78rem;font-weight:600;">⏳ รอตรวจสอบ</span>`;
                const purpose  = getBillRemarkText(pay.pay_remark, monthText, `ชำระ${title}`);
                const reviewBtn = allowReviewAction && payId > 0 && payStatus === '0'
                    ? `<button type="button" onclick="reviewBillPayment(${payId},${expenseId},'1',this)" style="padding:0.4rem 0.9rem;border:none;border-radius:6px;background:#16a34a;color:#fff;cursor:pointer;font-size:0.82rem;font-weight:600;">✓ อนุมัติ</button>`
                    : '';
                const proofFilename = String(pay.pay_proof || '').trim();
                const slipThumb = proofFilename
                    ? (() => {
                        const url = '/dormitory_management/Public/Assets/Images/Payments/' + encodeURIComponent(proofFilename);
                        return `<a href="${url}" target="_blank" title="ดูสลิป" style="flex-shrink:0;">
                            <img src="${url}" alt="สลิป" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.15);cursor:pointer;transition:transform 0.15s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'" onerror="this.parentElement.style.display='none'">
                        </a>`;
                    })()
                    : `<div style="width:44px;height:44px;border-radius:6px;border:1px dashed rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;" title="ไม่มีสลิป"><span style="font-size:1.1rem;opacity:0.3;">🖼</span></div>`;
                return `<div style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 0.75rem;border-radius:8px;background:rgba(15,23,42,0.3);margin-bottom:0.4rem;flex-wrap:wrap;">
                    ${slipThumb}
                    <div style="flex:1;min-width:80px;">
                        <div style="font-size:0.78rem;color:rgba(148,163,184,0.8);">${escapeHtml(pay.pay_date_display || '-')}</div>
                        <div style="font-weight:700;color:#f8fafc;font-size:0.95rem;">฿${amount.toLocaleString()}</div>
                    </div>
                    <div style="flex:2;min-width:100px;font-size:0.8rem;color:rgba(226,232,240,0.75);">${purpose}</div>
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-shrink:0;">${statusBadge}${reviewBtn ? `<span style="margin-left:0.25rem;">${reviewBtn}</span>` : ''}</div>
                </div>`;
            }).join('')
            : `<div style="padding:0.85rem;text-align:center;color:rgba(148,163,184,0.7);font-size:0.85rem;">ยังไม่มีรายการชำระ</div>`;

        container.innerHTML = `
            <div style="padding:1rem; background:rgba(255,255,255,0.04); border-radius:12px; border:1px solid rgba(255,255,255,0.09);">
                <!-- header row -->
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.9rem;">
                    <div>
                        <span style="font-weight:700;color:#93c5fd;font-size:0.95rem;">${escapeHtml(title)}</span>
                        <span style="margin-left:0.5rem;font-size:0.82rem;color:rgba(148,163,184,0.7);">${escapeHtml(monthText)}</span>
                    </div>
                    <span style="padding:0.2rem 0.65rem;border-radius:20px;background:rgba(255,255,255,0.06);font-size:0.78rem;color:${statusClr};font-weight:600;">${escapeHtml(statusText)}</span>
                </div>
                <!-- amount summary -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.4rem;margin-bottom:0.85rem;">
                    ${[
                        {label:'ยอดบิล',    val:expenseTotal,   clr:'#f8fafc',  bdr:'rgba(148,163,184,0.25)'},
                        {label:'ชำระแล้ว',  val:approvedAmount, clr:'#4ade80',  bdr:'rgba(34,197,94,0.3)'},
                        {label:'รอตรวจ',   val:pendingAmount,  clr:'#fbbf24',  bdr:'rgba(245,158,11,0.3)'},
                        {label:'คงเหลือ',   val:remainAmount,   clr:'#f87171',  bdr:'rgba(239,68,68,0.3)'},
                    ].map(c=>`<div style="background:rgba(15,23,42,0.4);border:1px solid ${c.bdr};border-radius:8px;padding:0.5rem 0.6rem;text-align:center;">
                        <div style="font-size:0.72rem;color:rgba(226,232,240,0.65);">${c.label}</div>
                        <div style="font-weight:700;font-size:0.9rem;color:${c.clr};">฿${c.val.toLocaleString()}</div>
                    </div>`).join('')}
                </div>
                <!-- progress bar — แสดงเฉพาะเมื่อมีการชำระบางส่วนแล้ว -->
                ${pct > 0 ? `<div style="height:5px;background:rgba(255,255,255,0.08);border-radius:99px;margin-bottom:0.9rem;overflow:hidden;">
                    <div style="height:100%;width:${pct.toFixed(1)}%;background:${barColor};border-radius:99px;transition:width 0.4s;"></div>
                </div>` : ''}
                <!-- payments -->
                ${paymentRows}
            </div>`;
    }

    function refreshBillingPayments(ctrId) {
        const firstBillPaymentsSection  = document.getElementById('firstBillPaymentsSection');
        const latestBillPaymentsSection = document.getElementById('latestBillPaymentsSection');
        const loadingHtml = `<div style="padding:1rem; text-align:center; color:rgba(148,163,184,0.8); font-size:0.88rem;">
            <svg style="width:20px;height:20px;animation:waitSpin 1s linear infinite;vertical-align:middle;margin-right:6px;" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#60a5fa" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/></svg>
            กำลังโหลด...
        </div>`;
        firstBillPaymentsSection.innerHTML  = loadingHtml;
        latestBillPaymentsSection.innerHTML = loadingHtml;

        fetch(`../Manage/get_first_bill_payments.php?ctr_id=${encodeURIComponent(ctrId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load bill payments');
                }
                return response.json();
            })
            .then(data => {
                const firstBill  = data?.first_bill  || {};
                const latestBill = data?.latest_bill || {};
                const allBills   = data?.all_bills   || [];
                const firstBillMonth = firstBill?.bill_month || '';
                if (firstBillMonth) {
                    document.getElementById('nextMonthDisplay').textContent = formatMonthDisplay(firstBillMonth);
                }

                // Check if latest bill is fully paid (for meter disable logic)
                const lastBillIdx = allBills.length - 1;
                const billToCheck = allBills.length > 0 ? allBills[lastBillIdx] : firstBill;
                const billTotal = Number(billToCheck?.expense_total || 0);
                const billApproved = Number(billToCheck?.approved_amount || 0);
                const isFirstBillFullyPaid = billTotal > 0 && billApproved >= billTotal;

                // Disable update meter button if latest bill is fully paid
                const moSaveBtn = document.getElementById('moSaveBtn');
                const saveMeterBtn = document.getElementById('saveMeterBtn');
                if (isFirstBillFullyPaid) {
                    if (moSaveBtn) { moSaveBtn.style.display = 'none'; moSaveBtn.disabled = true; }
                    if (saveMeterBtn) { saveMeterBtn.style.display = 'none'; saveMeterBtn.disabled = true; }
                    const meterNoticeBlock = document.getElementById('meterNoticeBlock');
                    if (meterNoticeBlock) {
                        meterNoticeBlock.innerHTML = '<span class="billing-inline-icon" style="color:#4ade80;"><svg class="billing-svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14M1 4.5L8.5 12 15 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg><span>ชำระแล้ว — ไม่สามารถอัปเดตมิเตอร์ได้</span></span>';
                        meterNoticeBlock.style.background = 'rgba(34, 197, 94, 0.08)';
                        meterNoticeBlock.style.borderColor = 'rgba(34, 197, 94, 0.25)';
                        meterNoticeBlock.style.color = '#4ade80';
                        meterNoticeBlock.style.display = '';
                    }
                }

                // แสดงบิลทุกเดือนจาก all_bills
                firstBillPaymentsSection.style.display = 'none';
                firstBillPaymentsSection.innerHTML = '';
                latestBillPaymentsSection.innerHTML = '';

                if (allBills.length === 0) {
                    latestBillPaymentsSection.innerHTML = '<div style="color:rgba(148,163,184,0.7);font-size:0.88rem;padding:0.5rem 0;">ยังไม่มีบิลในระบบ</div>';
                } else {
                    // สร้าง container สำหรับทุกบิลใน latestBillPaymentsSection (เดือนล่าสุดขึ้นก่อน)
                    const billsReversed = [...allBills].reverse();
                    billsReversed.forEach((bill, idx) => {
                        const isLast = idx === 0; // isLast = bill ล่าสุด (ซึ่งอยู่ index 0 หลัง reverse)
                        const isFirst = idx === billsReversed.length - 1;
                        let title = '';
                        if (billsReversed.length === 1) {
                            title = 'รายการชำระเดือนแรก (บิลปัจจุบัน)';
                        } else if (isFirst) {
                            title = 'รายการชำระเดือนแรก';
                        } else if (isLast) {
                            title = 'บิลล่าสุดที่ต้องจัดการ';
                        } else {
                            // เดือนกลาง
                            const bm = bill.bill_month || '';
                            title = 'บิล ' + (bm ? formatMonthDisplay(bm) : '');
                        }
                        // สร้าง div container ชั่วคราวใน latestBillPaymentsSection
                        const divId = 'billSection_' + idx;
                        const divEl = document.createElement('div');
                        divEl.id = divId;
                        if (idx > 0) divEl.style.marginTop = '0.85rem';
                        latestBillPaymentsSection.appendChild(divEl);
                        renderBillSection(divId, title, bill, {
                            allowReviewAction: isLast,
                            emptyHint: 'ยังไม่มีรายการชำระ',
                        });
                    });
                }

            })
            .catch(() => {
                firstBillPaymentsSection.innerHTML = `
                    <div style="font-weight: 700; color: #93c5fd; margin-bottom: 0.5rem;">รายการชำระเดือนแรก</div>
                    <div style="color: #fca5a5;">ไม่สามารถโหลดข้อมูลการชำระจากระบบได้</div>
                `;
                latestBillPaymentsSection.innerHTML = `
                    <div style="font-weight: 700; color: #93c5fd; margin-bottom: 0.5rem;">บิลล่าสุดที่ต้องจัดการ</div>
                    <div style="color: #fca5a5;">ไม่สามารถโหลดข้อมูลบิลล่าสุดจากระบบได้</div>
                `;
            });
    }

    function reviewBillPayment(payId, expId, nextStatus, btnEl) {
        const actionText = nextStatus === '1' ? 'อนุมัติ' : 'ตีกลับ';

        // ถ้ายังไม่ได้กดยืนยัน ให้เปลี่ยนปุ่มเป็นโหมดยืนยัน
        if (btnEl && btnEl.dataset.confirming !== 'true') {
            btnEl.dataset.confirming = 'true';
            const origText = btnEl.textContent;
            const origBg   = btnEl.style.background;
            btnEl.textContent     = `ยืนยัน${actionText}?`;
            btnEl.style.background = nextStatus === '1' ? '#15803d' : '#c2410c';
            btnEl.style.outline   = '2px solid #fff';

            // คืนสภาพเดิมถ้าไม่กดภายใน 3 วินาที
            const timer = setTimeout(() => {
                btnEl.dataset.confirming = 'false';
                btnEl.textContent       = origText;
                btnEl.style.background  = origBg;
                btnEl.style.outline     = '';
            }, 3000);
            btnEl._reviewTimer = timer;
            return;
        }

        // กดยืนยันแล้ว — ดำเนินการ
        if (btnEl) {
            clearTimeout(btnEl._reviewTimer);
            btnEl.disabled         = true;
            btnEl.style.opacity    = '0.6';
            btnEl.textContent      = 'กำลังดำเนินการ...';
            btnEl.style.outline    = '';
        }

        const ctrId = document.getElementById('modal_billing_ctr_id').value;
        const formData = new URLSearchParams();
        formData.append('csrf_token', '<?php echo $csrfToken; ?>');
        formData.append('pay_id', String(payId));
        formData.append('exp_id', String(expId));
        formData.append('pay_status', String(nextStatus));

        fetch('../Manage/update_payment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString(),
        })
            .then(response => response.json())
            .then(result => {
                if (!result?.success) {
                    throw new Error(result?.error || 'ไม่สามารถอัปเดตสถานะรายการชำระได้');
                }
                // Refresh billing payments in-place + soft refresh table
                const ctrId = document.getElementById('modal_billing_ctr_id').value;
                refreshBillingPayments(ctrId);
                refreshWizardTable();
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast('อัปเดตสถานะการชำระเรียบร้อย');
                }
            })
            .catch((error) => {
                if (btnEl) { btnEl.disabled = false; btnEl.style.opacity = '1'; btnEl.dataset.confirming = 'false'; }
                const errDiv = document.createElement('div');
                errDiv.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;padding:0.75rem 1rem;background:rgba(239,68,68,0.9);color:#fff;border-radius:8px;font-size:0.9rem;';
                errDiv.textContent = error.message || 'ไม่สามารถอัปเดตสถานะรายการชำระได้';
                document.body.appendChild(errDiv);
                setTimeout(() => errDiv.remove(), 4000);
            });
    }

    // Functions สำหรับ Billing Modal
    // ---- Meter-Only Modal ----
    var _moCtrId = 0, _moPrevWater = 0, _moPrevElec = 0;
    var _moMonth = 0, _moYear = 0, _moRateElec = 8;
    var _moWaterBaseUnits  = 10;    // ค่าน้ำเหมาจ่าย - หน่วยฐาน
    var _moWaterBasePrice  = 200;   // ค่าน้ำเหมาจ่าย - ราคาเหมาจ่าย
    var _moWaterExcessRate = 25;    // ค่าน้ำเหมาจ่าย - ค่าส่วนเกิน

    function openMeterOnlyModal(ctrId, tntName, roomNumber, targetYm) {
        _moCtrId = ctrId;
        document.getElementById('moHeaderSub').textContent =
            'ห้อง ' + roomNumber + ' • ' + tntName
            + (targetYm ? ' (' + formatMonthDisplay(targetYm + '-01') + ')' : '');
        document.getElementById('moPrevWater').textContent = '...';
        document.getElementById('moPrevElec').textContent  = '...';
        document.getElementById('moWaterInput').value = '';
        document.getElementById('moElecInput').value  = '';
        document.getElementById('moWaterInput').disabled = false;
        document.getElementById('moElecInput').disabled  = false;
        document.getElementById('moPreview').style.display = 'none';
        document.getElementById('moFirstReadingMsg').style.display = 'none';
        document.getElementById('moMsg').textContent = '';
        const btn = document.getElementById('moSaveBtn');
        btn.style.display = 'inline-block';
        btn.disabled = false;
        btn.textContent = '✓ บันทึกมิเตอร์';

        if (targetYm && /^\d{4}-\d{2}$/.test(targetYm)) {
            const p = targetYm.split('-');
            _moYear  = parseInt(p[0], 10);
            _moMonth = parseInt(p[1], 10);
        } else {
            const n = new Date();
            _moYear  = n.getFullYear();
            _moMonth = n.getMonth() + 1;
        }

        // ปล่อยให้จดมิเตอร์ได้ทันที ไม่ต้องรอถึงเดือนบิล
        _moIsFuture = false;  // always allow meter recording regardless of date

        document.getElementById('meterOnlyModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';

        fetch('../Manage/get_utility_reading.php?ctr_id=' + encodeURIComponent(ctrId) + '&target_month=' + _moMonth + '&target_year=' + _moYear)
            .then(r => r.text().then(txt => { try { return JSON.parse(txt); } catch(e) { return {error:'Invalid response'}; } }))
            .then(d => {
                if (d.error) return;
                _moPrevWater = d.prev_water || 0;
                _moPrevElec  = d.prev_elec  || 0;
                _moRateElec  = d.rate_elec  || 8;
                _moWaterBaseUnits  = d.water_base_units  || 10;
                _moWaterBasePrice  = d.water_base_price  || 200;
                _moWaterExcessRate = d.water_excess_rate || 25;
                _meterIsFirstReading = d.is_first_reading || false;  // ตั้งค่า first reading flag
                document.getElementById('moPrevWater').textContent = String(_moPrevWater).padStart(7, '0');
                document.getElementById('moPrevElec').textContent  = String(_moPrevElec).padStart(5, '0');
                if (d.saved && d.meter_month == _moMonth && d.meter_year == _moYear && !_moIsFuture && d.curr_water !== null && d.curr_elec !== null) {
                    document.getElementById('moWaterInput').value    = d.curr_water != null ? String(d.curr_water).padStart(7, '0') : '';
                    document.getElementById('moElecInput').value     = d.curr_elec  != null ? String(d.curr_elec).padStart(5, '0')  : '';
                    // Allow editing even after saved - just show the current values
                    btn.style.display = 'inline-block';
                    btn.textContent = 'อัปเดตมิเตอร์';
                    const m = document.getElementById('moMsg');
                    m.style.color = '#4ade80'; m.textContent = '✓ บันทึกแล้ว (สามารถแก้ไขได้)';
                    updateMoPreview();
                    // มิเตอร์บันทึกแล้ว (อาจจะจากเซสชันก่อน) → อัปเดตตารางในพื้นหลัง
                    refreshWizardTable();
                } else if (!d.saved && d.meter_month == _moMonth && d.meter_year == _moYear && (d.water_saved || d.elec_saved)) {
                    // Partial save: one meter recorded, the other not
                    if (d.water_saved && d.curr_water !== null) {
                        document.getElementById('moWaterInput').value = String(d.curr_water).padStart(7, '0');
                        document.getElementById('moWaterInput').disabled = true;
                        document.getElementById('moWaterInput').style.opacity = '0.6';
                    }
                    if (d.elec_saved && d.curr_elec !== null) {
                        document.getElementById('moElecInput').value = String(d.curr_elec).padStart(5, '0');
                        document.getElementById('moElecInput').disabled = true;
                        document.getElementById('moElecInput').style.opacity = '0.6';
                    }
                    btn.style.display = 'inline-block';
                    btn.textContent = '✓ บันทึกมิเตอร์';
                    const m = document.getElementById('moMsg');
                    m.style.color = '#fbbf24'; m.textContent = '⚠ บันทึกบางส่วนแล้ว';
                    updateMoPreview();
                }
            })
            .catch(() => {
                document.getElementById('moPrevWater').textContent = '-';
                document.getElementById('moPrevElec').textContent  = '-';
            });
    }

    function closeMeterOnlyModal() {
        document.getElementById('meterOnlyModal').classList.remove('active');
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    }

    function updateMoPreview() {
        const wv  = document.getElementById('moWaterInput').value.trim();
        const ev  = document.getElementById('moElecInput').value.trim();
        const pre = document.getElementById('moPreview');
        const msg = document.getElementById('moFirstReadingMsg');
        if (wv === '' && ev === '') { pre.style.display = 'none'; msg.style.display = 'none'; return; }
        pre.style.display = 'flex';
        
        // Show first reading message if applicable
        if (_meterIsFirstReading) {
            msg.style.display = 'block';
        } else {
            msg.style.display = 'none';
        }
        
        const parts = [];
        if (wv !== '') {
            const used = parseInt(wv, 10) - _moPrevWater;
            // ครั้งแรกไม่เสียตัง (cost = 0)
            const cost = _meterIsFirstReading ? 0 : (used <= 0 ? 0 : (used <= _moWaterBaseUnits ? _moWaterBasePrice : _moWaterBasePrice + (used - _moWaterBaseUnits) * _moWaterExcessRate));
            parts.push('💧 ใช้ <b style="color:#60a5fa">' + Math.max(0, used) + '</b> หน่วย → <b style="color:#4ade80">฿' + cost.toLocaleString() + '</b>');
        }
        if (ev !== '') {
            const used = parseInt(ev, 10) - _moPrevElec;
            // ครั้งแรกไม่เสียตัง (cost = 0)
            const cost = _meterIsFirstReading ? 0 : (Math.max(0, used) * _moRateElec);
            parts.push('⚡ ใช้ <b style="color:#fbbf24">' + Math.max(0, used) + '</b> หน่วย → <b style="color:#4ade80">฿' + cost.toLocaleString() + '</b>');
        }
        pre.innerHTML = parts.join('<span style="color:rgba(255,255,255,0.2);margin:0 0.35rem">|</span>');
    }

    function saveMeterOnly() {
        const wv  = document.getElementById('moWaterInput').value.trim();
        const ev  = document.getElementById('moElecInput').value.trim();
        const btn = document.getElementById('moSaveBtn');
        const msg = document.getElementById('moMsg');
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
        msg.textContent = '';
        const fd = new FormData();
        fd.append('csrf_token',  '<?php echo $csrfToken; ?>');
        fd.append('ctr_id',      _moCtrId);
        fd.append('water_new',   wv);
        fd.append('elec_new',    ev);
        fd.append('meter_month', _moMonth);
        fd.append('meter_year',  _moYear);
        fetch('../Manage/save_utility_ajax.php', { method: 'POST', body: fd })
            .then(r => {
                if (!r.ok) {
                    return r.text().then(txt => {
                        try { return JSON.parse(txt); } catch(e) { return {success:false, error:'เซิร์ฟเวอร์ตอบกลับ HTTP ' + r.status}; }
                    });
                }
                return r.text().then(txt => {
                    try { return JSON.parse(txt); } catch(e) { return {success:false, error:'เซิร์ฟเวอร์ตอบกลับข้อมูลไม่ถูกต้อง'}; }
                });
            })
            .then(d => {
                if (d.success) {
                    msg.style.color = '#4ade80';
                    msg.textContent = '✓ บันทึกสำเร็จ';
                    btn.style.display = 'none';
                    document.getElementById('moWaterInput').disabled = true;
                    document.getElementById('moElecInput').disabled  = true;
                    setTimeout(() => {
                        closeMeterOnlyModal();
                        showSuccessToast('บันทึกมิเตอร์เรียบร้อยแล้ว');
                        refreshWizardTable();
                    }, 700);
                } else {
                    msg.style.color = '#fca5a5';
                    msg.textContent = d.error || 'เกิดข้อผิดพลาด';
                    btn.disabled = false;
                    btn.textContent = 'บันทึกมิเตอร์';
                }
            })
            .catch(err => {
                msg.style.color = '#fca5a5';
                msg.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message;
                btn.disabled = false;
                btn.textContent = 'บันทึกมิเตอร์';
            });
    }

    document.getElementById('meterOnlyModal').addEventListener('click', function(e) {
        if (e.target === this) closeMeterOnlyModal();
    });
    // ---- end Meter-Only Modal ----

    // ---- Meter reading helpers ----
    var _meterCtrId = 0;
    var _meterPrevWater = 0;
    var _meterPrevElec  = 0;
    var _meterMonth = 0;
    var _meterYear  = 0;
    var _meterRateWater = 18;
    var _meterRateElec  = 8;
    var _meterWaterBaseUnits  = 10;    // ค่าน้ำเหมาจ่าย - หน่วยฐาน
    var _meterWaterBasePrice  = 200;   // ค่าน้ำเหมาจ่าย - ราคาเหมาจ่าย
    var _meterWaterExcessRate = 25;    // ค่าน้ำเหมาจ่าย - ค่าส่วนเกิน
    var _meterIsFirstReading  = false;  // แฉลก: เป็นการจดมิเตอร์ครั้งแรก

    function loadMeterReading(ctrId) {
        _meterCtrId = ctrId;
        const badge = document.getElementById('meterSavedBadge');
        const btn   = document.getElementById('saveMeterBtn');
        const msgDiv = document.getElementById('meterMsg');
        badge.style.display = 'none';
        btn.style.display = 'inline-block';
        btn.disabled = false;
        msgDiv.textContent = '';
        document.getElementById('meterWaterInput').value = '';
        document.getElementById('meterElecInput').value  = '';
        document.getElementById('meterWaterInput').disabled = false;
        document.getElementById('meterElecInput').disabled  = false;
        document.getElementById('meterPreview').style.display = 'none';
        document.getElementById('prevWaterDisplay').textContent = '...';
        document.getElementById('prevElecDisplay').textContent  = '...';

        fetch(`../Manage/get_utility_reading.php?ctr_id=${encodeURIComponent(ctrId)}`)
            .then(r => r.text().then(txt => { try { return JSON.parse(txt); } catch(e) { return {error:'Invalid response'}; } }))
            .then(d => {
                if (d.error) return;
                _meterPrevWater  = d.prev_water  || 0;
                _meterPrevElec   = d.prev_elec   || 0;
                _meterMonth      = d.meter_month || (new Date().getMonth() + 1);
                _meterYear       = d.meter_year  || (new Date().getFullYear());
                _meterRateWater  = d.rate_water  || 18;
                _meterRateElec   = d.rate_elec   || 8;
                _meterWaterBaseUnits  = d.water_base_units  || 10;
                _meterWaterBasePrice  = d.water_base_price  || 200;
                _meterWaterExcessRate = d.water_excess_rate || 25;
                _meterIsFirstReading  = d.is_first_reading || false;

                document.getElementById('prevWaterDisplay').textContent = String(_meterPrevWater).padStart(7, '0');
                document.getElementById('prevElecDisplay').textContent  = String(_meterPrevElec).padStart(5, '0');

                if (d.saved) {
                    // already saved this month — show saved badge + allow edit and re-save
                    badge.style.display = 'inline-block';
                    btn.style.display   = 'inline-block';
                    btn.textContent     = 'อัปเดตมิเตอร์';
                    document.getElementById('meterWaterInput').value    = (d.curr_water != null && d.curr_water > 0) ? String(d.curr_water).padStart(7, '0') : '';
                    document.getElementById('meterElecInput').value     = (d.curr_elec  != null && d.curr_elec  > 0) ? String(d.curr_elec).padStart(5, '0')  : '';
                    document.getElementById('meterWaterInput').disabled = false;
                    document.getElementById('meterElecInput').disabled  = false;
                    updateMeterPreview();
                    // โหลดและแสดงรายการบิล เฉพาะเมื่อจดมิเตอร์แล้วเท่านั้น
                    document.getElementById('billSectionsWrapper').style.display = '';
                    document.getElementById('meterNoticeBlock').style.display = 'none';
                    refreshBillingPayments(_meterCtrId);
                } else {
                    // ยังไม่จดมิเตอร์ — ซ่อนบิล แสดงแจ้งเตือน ซ่อนแบดจ์
                    badge.style.display = 'none';  // เพราะยังไม่ได้จดมิเตอร์
                    document.getElementById('billSectionsWrapper').style.display = 'none';
                    
                    // แสดงข้อความแตกต่างกันสำหรับการจดมิเตอร์ครั้งแรก
                    if (_meterIsFirstReading) {
                        const noticeDiv = document.getElementById('meterNoticeBlock');
                        noticeDiv.innerHTML = '<span class="billing-inline-icon" style="color:#4ade80;"><svg class="billing-svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2A10 10 0 1 0 12 22A10 10 0 0 0 12 2zm-1 15h2v2h-2v-2zm0-8h2v6h-2v-6z" fill="currentColor"></path></svg><span>จดมิเตอร์ครั้งแรก — ไม่มีค่าใช้จ่าย</span></span>';
                        noticeDiv.style.background = 'rgba(52, 211, 153, 0.08)';
                        noticeDiv.style.borderColor = 'rgba(52, 211, 153, 0.25)';
                        noticeDiv.style.color = '#4ade80';
                    } else {
                        const noticeDiv = document.getElementById('meterNoticeBlock');
                        noticeDiv.innerHTML = '<span class="billing-inline-icon" style="color:#ef4444;"><svg class="billing-svg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="16" x2="12" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg><span>ยังไม่ได้จดมิเตอร์ — ไม่ได้มีข้อมูล</span></span>';
                        noticeDiv.style.background = 'rgba(239, 68, 68, 0.08)';
                        noticeDiv.style.borderColor = 'rgba(239, 68, 68, 0.25)';
                        noticeDiv.style.color = '#ef4444';
                    }
                    document.getElementById('meterNoticeBlock').style.display = '';
                }
            })
            .catch(() => {
                document.getElementById('prevWaterDisplay').textContent = '-';
                document.getElementById('prevElecDisplay').textContent  = '-';
                // กรณีโหลดไม่ได้ — แสดงแจ้งเตือนมิเตอร์
                document.getElementById('billSectionsWrapper').style.display = 'none';
                document.getElementById('meterNoticeBlock').style.display = '';
            });
    }

    function updateMeterPreview() {
        const waterVal = document.getElementById('meterWaterInput').value.trim();
        const elecVal  = document.getElementById('meterElecInput').value.trim();
        const preview  = document.getElementById('meterPreview');
        if (waterVal === '' && elecVal === '') { preview.style.display = 'none'; return; }
        preview.style.display = 'flex';
        let parts = [];
        if (waterVal !== '') {
            const used = parseInt(waterVal, 10) - _meterPrevWater;
            // ถ้าเป็นการจดมิเตอร์ครั้งแรก ไม่คิดค่าใช้จ่าย
            let cost = 0;
            if (!_meterIsFirstReading) {
                // ใช้ค่าน้ำเหมาจ่าย (tiered pricing) เฉพาะครั้งที่ 2 เป็นต้นไป
                cost = used <= 0 ? 0 : (used <= _meterWaterBaseUnits ? _meterWaterBasePrice : _meterWaterBasePrice + (used - _meterWaterBaseUnits) * _meterWaterExcessRate);
            }
            parts.push(`<span class="billing-inline-icon" style="color:#60a5fa;"><svg class="billing-svg-icon billing-svg-water" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C9 7 6 9.8 6 14a6 6 0 0 0 12 0c0-4.2-3-7-6-11z"></path></svg><span>ใช้ <b style="color:#60a5fa">${Math.max(0,used)}</b> หน่วย → <b style="color:#4ade80">฿${cost.toLocaleString()}${_meterIsFirstReading ? ' (ครั้งแรก)' : ''}</b></span></span>`);
        }
        if (elecVal !== '') {
            const used = parseInt(elecVal, 10) - _meterPrevElec;
            // ถ้าเป็นการจดมิเตอร์ครั้งแรก ไม่คิดค่าใช้จ่าย
            const cost = _meterIsFirstReading ? 0 : (Math.max(0, used) * _meterRateElec);
            parts.push(`<span class="billing-inline-icon" style="color:#fbbf24;"><svg class="billing-svg-icon billing-svg-elec" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"></path></svg><span>ใช้ <b style="color:#fbbf24">${Math.max(0,used)}</b> หน่วย → <b style="color:#4ade80">฿${cost.toLocaleString()}${_meterIsFirstReading ? ' (ครั้งแรก)' : ''}</b></span></span>`);
        }
        preview.innerHTML = parts.join('<span style="color:rgba(255,255,255,0.2);margin:0 0.25rem">|</span>');
    }

    function saveMeterReading() {
        const waterVal = document.getElementById('meterWaterInput').value.trim();
        const elecVal  = document.getElementById('meterElecInput').value.trim();
        const btn      = document.getElementById('saveMeterBtn');
        const msg      = document.getElementById('meterMsg');
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
        msg.textContent = '';

        const fd = new FormData();
        fd.append('csrf_token', '<?php echo $csrfToken; ?>');
        fd.append('ctr_id',      _meterCtrId);
        fd.append('water_new',   waterVal);
        fd.append('elec_new',    elecVal);
        fd.append('meter_month', _meterMonth);
        fd.append('meter_year',  _meterYear);

        fetch('../Manage/save_utility_ajax.php', { method: 'POST', body: fd })
            .then(r => {
                return r.text().then(txt => {
                    try { return JSON.parse(txt); } catch(e) { return {success:false, error:'เซิร์ฟเวอร์ตอบกลับข้อมูลไม่ถูกต้อง (HTTP ' + r.status + ')'}; }
                });
            })
            .then(d => {
                if (d.success) {
                    msg.style.color = '#4ade80';
                    msg.textContent = '✓ บันทึกสำเร็จ';
                    btn.style.display = 'inline-block';
                    btn.textContent = 'อัปเดตมิเตอร์';
                    document.getElementById('meterSavedBadge').style.display = 'inline-block';
                    document.getElementById('meterWaterInput').disabled = false;
                    document.getElementById('meterElecInput').disabled  = false;
                    document.getElementById('billSectionsWrapper').style.display = '';
                    document.getElementById('meterNoticeBlock').style.display = 'none';
                    refreshBillingPayments(_meterCtrId);
                    refreshWizardTable();
                    showSuccessToast('อัปเดตมิเตอร์เรียบร้อยแล้ว');
                } else {
                    msg.style.color = '#fca5a5';
                    msg.textContent = d.error || 'เกิดข้อผิดพลาด';
                    btn.disabled = false;
                    btn.textContent = 'บันทึกมิเตอร์';
                }
            })
            .catch(err => {
                msg.style.color = '#fca5a5';
                msg.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message;
                btn.disabled = false;
                btn.textContent = 'บันทึกมิเตอร์';
            });
    }
    // ---- end meter helpers ----

    function openBillingModal(ctrId, tntId, tntName, roomNumber, roomType, roomPrice) {
        // เก็บ ctrId สำหรับ meter update later
        _meterCtrId = ctrId;
        
        // ตั้งค่า hidden fields
        document.getElementById('modal_billing_ctr_id').value = ctrId;
        document.getElementById('modal_billing_tnt_id').value = tntId;
        document.getElementById('modal_billing_room_price').value = roomPrice;
        
        // แสดงข้อมูลผู้เช่า
        document.getElementById('billingBarTenant').textContent = tntName;
        document.getElementById('billingBarRoom').textContent = `ห้อง ${roomNumber} (${roomType}) • ฿${Number(roomPrice).toLocaleString()}/เดือน`;
        document.getElementById('billingModalSub').textContent = `ห้อง ${roomNumber} — ฿${Number(roomPrice).toLocaleString()}/เดือน`;

        // แสดงเดือนปัจจุบัน (เดือนที่จดมิเตอร์)
        const now = new Date();
        const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        document.getElementById('nextMonthDisplay').textContent = 
            `${monthNames[now.getMonth()]} ${now.getFullYear() + 543}`;

        // รีเซ็ต bill sections — ซ่อนไว้ก่อนจนกว่าจะรู้ว่าจดมิเตอร์แล้วหรือไม่
        document.getElementById('billSectionsWrapper').style.display = 'none';
        document.getElementById('meterNoticeBlock').style.display = 'none';
        document.getElementById('firstBillPaymentsSection').innerHTML = '';
        document.getElementById('latestBillPaymentsSection').innerHTML = '';

        // โหลดอัตราค่าน้ำ-ไฟจาก DB
        fetch('../Manage/get_latest_rate.php')
            .then(response => {
                // even if response.ok, server may signal failure via JSON
                return response.json();
            })
            .then(data => {
                if (data.success === false || data.error) {
                    throw new Error(data.message || 'ไม่สามารถดึงอัตราล่าสุดได้');
                }
                const waterRate = data.rate_water || 0;
                const elecRate = data.rate_elec || 0;
                
                document.getElementById('modal_billing_rate_water').value = waterRate;
                document.getElementById('modal_billing_rate_elec').value = elecRate;
                document.getElementById('waterRateDisplay').textContent = `฿${Number(waterRate).toFixed(2)}/หน่วย`;
                document.getElementById('elecRateDisplay').textContent = `฿${Number(elecRate).toFixed(2)}/หน่วย`;
            })
            .catch((err) => {
                console.error('rate fetch error', err);
                // ใช้ค่า default ถ้าโหลดไม่ได้
                document.getElementById('modal_billing_rate_water').value = 18;
                document.getElementById('modal_billing_rate_elec').value = 8;
                document.getElementById('waterRateDisplay').textContent = '฿18.00/หน่วย';
                document.getElementById('elecRateDisplay').textContent = '฿8.00/หน่วย';
            });

        loadMeterReading(ctrId);

        document.getElementById('billingModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function closeBillingModal() {
        document.getElementById('billingModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        document.getElementById('billingForm').reset();
    }

    function closeWizardIntro() {
        const introBox = document.getElementById('wizardIntroBox');
        if (introBox) {
            introBox.style.display = 'none';
            localStorage.setItem('wizardIntroHidden', '1');
        }
    }

    // Functions สำหรับ Payment Modal (Step 2)
    function openPaymentModal(bpId, bkgId, tntId, tntName, roomNumber, bpAmount, bpProof, readOnly = false) {
        document.getElementById('modal_payment_bp_id').value = bpId;
        document.getElementById('modal_payment_bkg_id').value = bkgId;
        document.getElementById('modal_payment_tnt_id').value = tntId;

        const paymentSubmitBtn = document.getElementById('paymentSubmitBtn');
        const paymentCloseBtn = document.getElementById('paymentCloseBtn');
        paymentSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
        paymentCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        
        document.getElementById('paymentInfo').innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ผู้เช่า</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${tntName}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ห้องพัก</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${roomNumber}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">จำนวนเงินจอง</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #22c55e;">฿${Number(bpAmount).toLocaleString()}</div>
                </div>
            </div>
        `;
        
        const proofContainer = document.getElementById('paymentProofContainer');
        if (bpProof) {
            // Check if bpProof already contains the path or just filename
            // Typically in DB it's stored relative to project root or full path?
            // In wizard_step2.php: href="..."
            // The path in DB seems to be relative to web root or include 'dormitory_management'?
            // Usually DB stores 'Public/Assets/Images/Payments/filename.jpg'.
            // So '/dormitory_management/' + bpProof might be safer if running in subdir.
            
            // If proof is just filename, we might need to prepend path.
            // Let's assume it's the stored path.
            // But we need to make sure the image URL is correct.
            // If stored path starts with 'Public/...', we need '/dormitory_management/Public/...' or just '/Public/...' depending on setup.
            // From wizard_step2.php: href="..." implies absolute path from root.
            
            // Let's try adding /dormitory_management/ if it doesn't start with /
            let proofUrl = bpProof;
            if (!proofUrl.startsWith('/')) {
                proofUrl = '/' + proofUrl;
            }
             // Actually, let's just use what's passed and let the caller handle format or assume relative to domain root if starting with /
             // Or relative to current page if not.
             // bpProof is just filename (e.g., 'payment_1770004240_d69375905c6f0f51.png')
             // Build full path: /dormitory_management/Public/Assets/Images/Payments/filename
             proofUrl = '/dormitory_management/Public/Assets/Images/Payments/' + bpProof;
            
            document.getElementById('paymentProofImg').src = proofUrl;
            document.getElementById('paymentProofLink').href = proofUrl;
            proofContainer.style.display = 'block';
        } else {
            proofContainer.style.display = 'none';
        }
        
        document.getElementById('paymentModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
    }

    // Function สำหรับยกเลิกการจอง
    async function cancelBooking(bkgId, tntId, tntName) {
        let confirmed = false;
        if (typeof showConfirmDialog === 'function') {
            confirmed = await showConfirmDialog(
                'ยืนยันการยกเลิกการจอง',
                `คุณต้องการยกเลิกการจองของ "${tntName}" หรือไม่?\n\n⚠️ ข้อมูลที่จะถูกลบ:\n• ข้อมูลการจอง\n• ข้อมูลการชำระเงินมัดจำ\n• ข้อมูลสัญญา (ถ้ามี)\n• ข้อมูลค่าใช้จ่าย (ถ้ามี)\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้!`,
                'delete'
            );
        } else {
            confirmed = confirm(`คุณต้องการยกเลิกการจองของ "${tntName}" หรือไม่?\n\nข้อมูลทั้งหมดที่เกี่ยวข้องจะถูกลบ!`);
        }
        
        if (confirmed) {
            await doCancelBooking(bkgId, tntId);
        }
    }

    async function doCancelBooking(bkgId, tntId) {
        try {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $csrfToken; ?>');
            formData.append('bkg_id', bkgId);
            formData.append('tnt_id', tntId);

            const response = await fetch('../Manage/cancel_booking.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(data.message || 'ยกเลิกการจองเรียบร้อยแล้ว');
                }
                refreshWizardTable();
            } else {
                if (typeof showErrorToast === 'function') {
                    showErrorToast(data.error || 'เกิดข้อผิดพลาด');
                } else {
                    alert(data.error || 'เกิดข้อผิดพลาด');
                }
            }
        } catch (err) {
            console.error(err);
            if (typeof showErrorToast === 'function') {
                showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            } else {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
        }
    }

    // === AJAX Wizard Step Submission ===
    function submitWizardStep(formId, closeModalFn) {
        const form = document.getElementById(formId);
        if (!form) return;
        const formData = new FormData(form);
        const actionUrl = form.getAttribute('action');
        
        // Find and disable the submit button
        const modal = form.closest('.modal-overlay') || form.closest('.modal-container');
        let submitBtn = null;
        if (modal) {
            submitBtn = modal.querySelector('.btn-modal-primary') || modal.querySelector('[id$="SubmitBtn"]');
        }
        let origBtnText = '';
        if (submitBtn) {
            origBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'กำลังบันทึก...';
        }

        fetch(actionUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.text().then(txt => {
            try { return JSON.parse(txt); }
            catch(e) { return { success: false, error: 'เซิร์ฟเวอร์ตอบกลับข้อมูลไม่ถูกต้อง' }; }
        }))
        .then(data => {
            if (data.success) {
                if (typeof closeModalFn === 'function') closeModalFn();
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(data.message || 'บันทึกเรียบร้อย');
                }
                refreshWizardTable();
            } else {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origBtnText; }
                if (typeof showErrorToast === 'function') {
                    showErrorToast(data.error || 'เกิดข้อผิดพลาด');
                } else {
                    alert(data.error || 'เกิดข้อผิดพลาด');
                }
            }
        })
        .catch(err => {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origBtnText; }
            if (typeof showErrorToast === 'function') {
                showErrorToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            } else {
                alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message);
            }
        });
    }

    // Soft-refresh: fetch page and replace table content without full reload
    function refreshWizardTable() {
        const wrapper = document.getElementById('wizardTableWrapper');
        if (!wrapper) { location.reload(); return; }
        // Add cache-busting timestamp to prevent stale browser cache
        const sep = location.href.includes('?') ? '&' : '?';
        const freshUrl = location.href + sep + '_t=' + Date.now();
        fetch(freshUrl, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newWrapper = doc.getElementById('wizardTableWrapper');
            if (newWrapper) {
                wrapper.innerHTML = newWrapper.innerHTML;
            } else {
                // Table might have been replaced by empty state
                const newPanelBody = doc.querySelector('.wizard-panel-body');
                if (newPanelBody) {
                    const panelBody = document.querySelector('.wizard-panel-body');
                    if (panelBody) panelBody.innerHTML = newPanelBody.innerHTML;
                }
            }
        })
        .catch(() => {
            // Fallback: full reload if soft refresh fails
            location.reload();
        });
    }

    // Submit checkin form via AJAX (with validation)
    function validateAndSubmitCheckinAjax() {
        const form = document.getElementById('checkinForm');
        const errors = [];
        const checkinDate = document.getElementById('checkin_date_hidden').value;
        if (!checkinDate) errors.push('กรุณาเลือกวันที่เช็คอิน');
        
        const errorContainer = document.getElementById('validationError');
        const errorList = document.getElementById('errorList');
        
        if (errors.length > 0) {
            errorList.innerHTML = errors.map(err => '<li>' + err + '</li>').join('');
            errorContainer.style.display = 'block';
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            errorContainer.style.display = 'none';
            submitWizardStep('checkinForm', closeCheckinModal);
        }
    }
</script>
<?php include_once __DIR__ . '/../includes/apple_alert.php'; ?>
<script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
</body>
</html>
