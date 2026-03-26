<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

// Initialize database connection
$conn = connectDB();

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
                                OR DATE_FORMAT(e_first.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
                            )
                            AND e_first.exp_month = (
                                SELECT MIN(e_min.exp_month)
                                FROM expense e_min
                                WHERE e_min.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND (
                                        c.ctr_start IS NULL
                                        OR DATE_FORMAT(e_min.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                            )
                            AND COALESCE(e_first.exp_status, '0') = '1'
                )
        ";

    $completionCondition = '';
    if ($completedFilter === 1) {
        $completionCondition = "
            AND COALESCE(tw.step_5_confirmed, 0) = 1
            AND cr.checkin_date IS NOT NULL
            AND cr.checkin_date <> '0000-00-00'
            AND COALESCE(cr.water_meter_start, 0) > 0
            AND COALESCE(cr.elec_meter_start, 0) > 0
            AND $firstBillPaidCondition
        ";
    } else {
        $completionCondition = "
            AND (
                COALESCE(tw.step_5_confirmed, 0) = 0
                OR cr.checkin_date IS NULL
                OR cr.checkin_date = '0000-00-00'
                OR COALESCE(cr.water_meter_start, 0) <= 0
                OR COALESCE(cr.elec_meter_start, 0) <= 0
                OR NOT ($firstBillPaidCondition)
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
                                        OR DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                                ORDER BY e.exp_month ASC, e.exp_id DESC
                                LIMIT 1
                        ) AS first_exp_month,
                        (
                                SELECT e.exp_status
                                FROM expense e
                                WHERE e.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                                    AND (
                                        c.ctr_start IS NULL
                                        OR DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
                                    )
                                ORDER BY e.exp_month ASC, e.exp_id DESC
                                LIMIT 1
                        ) AS first_exp_status
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
        ORDER BY COALESCE(tw.current_step, 1) ASC, b.bkg_date DESC";
    
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
            $utilStmt = $conn->prepare(
                "SELECT ctr_id, DATE_FORMAT(utl_date, '%Y-%m') AS ym
                 FROM utility WHERE ctr_id IN ($placeholders)
                 GROUP BY ctr_id, DATE_FORMAT(utl_date, '%Y-%m')"
            );
            $utilStmt->execute($allCtrIds);
            foreach ($utilStmt->fetchAll(PDO::FETCH_ASSOC) as $uRow) {
                $utilMonthsRecorded[(int)$uRow['ctr_id']][$uRow['ym']] = true;
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
          AND COALESCE(cr.water_meter_start, 0) > 0
                        AND COALESCE(cr.elec_meter_start, 0) > 0
                    AND $firstBillPaidCondition
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตัวช่วยผู้เช่า</title>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <style>
        :root {
            --theme-bg-color: <?php echo $themeColor; ?>;
        }

        body,
        body main {
            background: #ffffff !important;
            color: #0f172a !important;
        }

        .wizard-panel {
            margin: 1.5rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15,23,42,0.08);
            overflow: hidden;
        }

        .wizard-panel > .page-header-bar {
            margin: 0 0 1rem !important;
            border-radius: 0;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: none;
        }

        .wizard-panel > .page-header-spacer {
            display: none !important;
        }

        .wizard-panel-body {
            padding: 1.5rem;
            padding-top: 1rem;
        }

        .wizard-intro {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .wizard-intro h3 {
            margin: 0 0 0.5rem 0;
            color: #3b82f6;
        }

        .wizard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
        }

        .wizard-table thead {
            background: #f8fafc;
        }

        .wizard-table th,
        .wizard-table td {
            padding: 1rem;
            text-align: left;
            color: #1e293b;
            border-bottom: 1px solid #e2e8f0;
        }

        .wizard-table tbody tr:hover {
            background: #f8fafc;
        }
            /* Responsive table for wizard-table */
            @media (max-width: 900px) {
                .wizard-table, .wizard-table thead, .wizard-table tbody, .wizard-table th, .wizard-table td, .wizard-table tr {
                    display: block;
                }
                .wizard-table thead {
                    display: none;
                }
                .wizard-table tr {
                    margin-bottom: 1.2rem;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                    background: #fff;
                    border: none;
                }
                .wizard-table td {
                    padding: 0.8rem 1rem;
                    border: none;
                    position: relative;
                    font-size: 1rem;
                }
                .wizard-table td:before {
                    content: attr(data-label);
                    font-weight: 600;
                    color: #64748b;
                    display: block;
                    margin-bottom: 0.3rem;
                    font-size: 0.95rem;
                }
            }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            position: relative;
            cursor: help;
        }
        
        /* Tooltip Styles */
        .step-circle::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%) translateY(5px);
            padding: 6px 10px;
            background: #f8fafc;
            color: #334155;
            font-size: 0.75rem;
            font-weight: 400;
            border-radius: 6px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 10;
            pointer-events: none;
        }
        
        .step-circle::after {
            content: '';
            position: absolute;
            bottom: 115%;
            left: 50%;
            transform: translateX(-50%) translateY(5px);
            border-width: 5px;
            border-style: solid;
            border-color: #f8fafc transparent transparent transparent;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
            pointer-events: none;
        }

        .step-circle:hover::before,
        .step-circle:hover::after {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        .step-circle.completed {
            background: #22c55e;
            color: white;
        }

        .step-circle.current {
            background: #3b82f6;
            color: #ffffff !important;
            animation: pulse 2s infinite;
        }


        .step-circle.pending {
            background: #f1f5f9;
            color: #94a3b8;
        }

        .step-circle.wait {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #f59e0b;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .step-circle.meter-pending {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #34d399;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wait-spinner {
            width: 20px;
            height: 20px;
            animation: waitSpin 1.6s linear infinite;
            display: block;
        }
        .wait-spinner circle:last-child {
            animation: waitSpinReverse 1.2s linear infinite;
            transform-origin: center;
        }
        .meter-spinner {
            width: 20px;
            height: 20px;
            display: block;
            animation: meterPulse 1.8s ease-in-out infinite;
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

        .step-arrow {
            color: #94a3b8;
            font-size: 1rem;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .action-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #22c55e;
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .wizard-table .action-btn.btn-primary {
            background: #2563eb !important;
            border: 1px solid #1d4ed8 !important;
            color: #ffffff !important;
        }

        .wizard-table .action-btn.btn-primary:hover {
            background: #1d4ed8 !important;
            border-color: #1e40af !important;
        }

        .wizard-table .action-btn.btn-danger {
            background: #dc2626 !important;
            border: 1px solid #b91c1c !important;
            color: #ffffff !important;
        }

        .wizard-table .action-btn.btn-danger:hover {
            background: #b91c1c !important;
            border-color: #991b1b !important;
        }

        .btn-disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .tenant-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .tenant-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .tenant-phone {
            font-size: 0.85rem;
            color: #64748b;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(8px);
            z-index: 9998;
            animation: fadeIn 0.3s;
        }

        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ซ่อน header เมื่อ modal เปิด */
        body.modal-open .page-header-bar,
        body.modal-open .page-header-spacer {
            display: none !important;
        }

        body.modal-open {
            overflow: hidden;
        }

        .modal-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.2);
            animation: slideUp 0.3s;
            position: relative;
        }

        .modal-header {
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            background: #ffffff;
            backdrop-filter: blur(10px);
            z-index: 10;
        }

        .modal-header h2,
        .modal-header p {
            color: #0f172a !important;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #f8fafc;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            color: #334155;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #fee2e2;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            color: #0f172a;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #60a5fa;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .info-box-modal {
            padding: 1rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .info-box-modal p {
            margin: 0.5rem 0;
            color: #1e293b;
        }

        .alert-box-modal {
            padding: 1rem;
            background: #fffbeb;
            border: 2px solid #fde68a;
            border-radius: 8px;
            margin: 1.5rem 0;
        }

        .alert-box-modal h4 {
            margin-top: 0;
            color: #b45309;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            background: #ffffff;
            position: sticky;
            bottom: 0;
        }

        .btn-modal {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-modal-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .btn-modal-primary:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .btn-modal-secondary {
            background: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .btn-modal-secondary:hover {
            background: #f1f5f9;
        }

        .wizard-panel p[style],
        .wizard-panel span[style],
        .wizard-panel div[style] {
            color: inherit;
        }

        .wizard-table span[style*="rgba(255,255,255"],
        .wizard-table span[style*="#e2e8f0"],
        .wizard-table span[style*="#f1f5f9"] {
            color: #64748b !important;
        }

        .modal-container [style*="color: #f8fafc"],
        .modal-container [style*="color: #f1f5f9"],
        .modal-container [style*="color: #e2e8f0"],
        .modal-container [style*="color: #fff"],
        .modal-container [style*="color: rgba(255,255,255"],
        .modal-container [style*="color: white"] {
            color: #334155 !important;
        }

        .modal-container input,
        .modal-container textarea,
        .modal-container select {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }

        .modal-container input::placeholder,
        .modal-container textarea::placeholder {
            color: #64748b !important;
        }

        .modal-container .close-btn {
            background: #f8fafc !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
        }

        .modal-container [style*="background: rgba(255,255,255,0.05)"],
        .modal-container [style*="background: rgba(255,255,255,0.08)"],
        .modal-container [style*="background: rgba(255,255,255,0.1)"] {
            background: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }

        .modal-container img {
            border-color: #cbd5e1 !important;
        }

        .billing-inline-icon {
            display: inline-flex;
            align-items: center;
            gap: 0.32rem;
        }

        .billing-svg-icon {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            vertical-align: -2px;
            flex-shrink: 0;
        }

        .billing-svg-water {
            animation: billingFloat 2.2s ease-in-out infinite;
        }

        .billing-svg-elec {
            animation: billingFlicker 1.7s ease-in-out infinite;
        }

        .billing-svg-cal {
            animation: billingTick 2.4s ease-in-out infinite;
        }

        .billing-svg-meter {
            animation: billingMeterPulse 2.1s ease-in-out infinite;
        }

        .billing-svg-warning {
            width: 15px;
            height: 15px;
            animation: billingWarningPulse 1.9s ease-in-out infinite;
        }

        @keyframes billingFloat {
            0%,
            100% { transform: translateY(0); }
            50% { transform: translateY(-1.5px); }
        }

        @keyframes billingFlicker {
            0%,
            100% { opacity: 1; }
            45% { opacity: 0.65; }
            55% { opacity: 1; }
        }

        @keyframes billingTick {
            0%,
            100% { transform: rotate(0deg); }
            40% { transform: rotate(-3deg); }
            65% { transform: rotate(2deg); }
        }

        @keyframes billingMeterPulse {
            0%,
            100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }

        @keyframes billingWarningPulse {
            0%,
            100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.75; transform: scale(1.06); }
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

                <div class="wizard-intro">
                    <h3>🎯 ระบบจัดการผู้เช่า 5 ขั้นตอน</h3>
                    <p style="margin: 0; color: rgba(255,255,255,0.8); line-height: 1.6;">
                        ระบบนี้ช่วยให้คุณจัดการผู้เช่าได้อย่างเป็นระบบ ตั้งแต่การจองห้องจนถึงการออกบิลรายเดือน<br>
                        <strong>ขั้นตอน:</strong> ① ยืนยันจอง → ② ยืนยันชำระเงินจอง → ③ สร้างสัญญา → ④ เช็คอิน → ⑤ เริ่มบิลรายเดือน
                    </p>
                </div>

                <!-- Completion Status Filter Buttons -->
                <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <a href="<?php echo htmlspecialchars($completedZeroHref, ENT_QUOTES, 'UTF-8'); ?>" style="padding: 0.75rem 1.5rem; background: <?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? '#3b82f6' : 'rgba(59, 130, 246, 0.2)'; ?>; color: <?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? 'white !important' : '#111827 !important'; ?>; border: 2px solid #3b82f6; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#3b82f6'; this.style.setProperty('color','white','important');" onmouseout="this.style.background='<?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? '#3b82f6' : 'rgba(59, 130, 246, 0.2)'; ?>'; this.style.setProperty('color','<?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? 'white' : '#111827'; ?>','important');">⏳ ยังไม่ครบ 5 ขั้นตอน</a>
                    <?php if ($hasCompletedTenants): ?>
                    <a href="<?php echo htmlspecialchars($completedOneHref, ENT_QUOTES, 'UTF-8'); ?>" style="padding: 0.75rem 1.5rem; background: <?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? '#22c55e' : 'rgba(34, 197, 94, 0.2)'; ?>; color: <?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? 'white' : '#22c55e'; ?>; border: 2px solid #22c55e; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#22c55e'; this.style.color='white';" onmouseout="this.style.background='<?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? '#22c55e' : 'rgba(34, 197, 94, 0.2)'; ?>'; this.style.color='<?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? 'white' : '#22c55e'; ?>';">✅ ครบ 5 ขั้นตอนแล้ว</a>
                    <?php endif; ?>
                    <?php if ($selectedBkgId > 0): ?>
                    <span style="padding: 0.75rem 1rem; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 8px; font-weight: 600;">กำลังแสดงเฉพาะรายการ #<?php echo (int)$selectedBkgId; ?></span>
                    <a href="<?php echo htmlspecialchars($clearSelectionHref, ENT_QUOTES, 'UTF-8'); ?>" style="padding: 0.75rem 1.25rem; background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; font-weight: 600;">ล้างตัวกรอง</a>
                    <?php endif; ?>
                </div>

                <?php if (count($wizardTenants) > 0): ?>
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
                                // If no workflow exists, default to step 2 (since booking already means step 1 is done)
                                $currentStep = ($tenant['workflow_id'] === null) ? 2 : (int)$tenant['current_step'];
                                // If no workflow, step 1 is implicitly completed (booking exists)
                                $step1 = ($tenant['workflow_id'] === null) ? 1 : $tenant['step_1_confirmed'];
                                $step2 = $tenant['step_2_confirmed'];
                                $step3 = $tenant['step_3_confirmed'];

                                $hasCheckinDate = !empty($tenant['checkin_date']) && $tenant['checkin_date'] !== '0000-00-00';
                                $hasWaterMeter = isset($tenant['water_meter_start']) && (float)$tenant['water_meter_start'] > 0;
                                $hasElecMeter = isset($tenant['elec_meter_start']) && (float)$tenant['elec_meter_start'] > 0;
                                $checkinDataComplete = $hasCheckinDate && $hasWaterMeter && $hasElecMeter;

                                $step4 = ((int)$tenant['step_4_confirmed'] === 1 && $checkinDataComplete) ? 1 : 0;
                                $step5 = ((int)$tenant['step_5_confirmed'] === 1 && $step4 === 1) ? 1 : 0;

                                $contractStartRaw = (string)($tenant['ctr_start'] ?? '');
                                $expectedFirstBillMonthRaw = '';
                                if ($contractStartRaw !== '' && strtotime($contractStartRaw) !== false) {
                                    $expectedFirstBillMonthRaw = date('Y-m-01', strtotime('first day of next month', strtotime($contractStartRaw)));
                                }

                                $firstBillMonthRaw = (string)($tenant['first_exp_month'] ?? '');
                                if ($firstBillMonthRaw === '' && $expectedFirstBillMonthRaw !== '') {
                                    $firstBillMonthRaw = $expectedFirstBillMonthRaw;
                                }
                                $firstBillMonthDisplay = '-';
                                $firstBillDueReached = false;
                                if ($firstBillMonthRaw !== '' && strtotime($firstBillMonthRaw) !== false) {
                                    $firstBillMonthDisplay = date('m/Y', strtotime($firstBillMonthRaw));
                                    $firstBillDueReached = strtotime(date('Y-m-d')) >= strtotime(date('Y-m-01', strtotime($firstBillMonthRaw)));
                                }
                                $firstBillPaid    = ((string)($tenant['first_exp_status'] ?? '') === '1');
                                $firstBillWaiting = ((string)($tenant['first_exp_status'] ?? '') === '2');

                                // --- มิเตอร์: เช็คว่าจดเดือนบิล + เดือนก่อนไว้แล้วหรือยัง ---
                                $ctrIdInt      = (int)($tenant['ctr_id'] ?? 0);
                                $billYearMonth = ($firstBillMonthRaw !== '' && strtotime($firstBillMonthRaw) !== false)
                                    ? date('Y-m', strtotime($firstBillMonthRaw)) : null;
                                $prevYearMonth = $billYearMonth
                                    ? date('Y-m', strtotime($billYearMonth . '-01 -1 month')) : null;
                                $meterBillDone = $billYearMonth !== null
                                    && !empty($utilMonthsRecorded[$ctrIdInt][$billYearMonth]);
                                $meterPrevDone = $prevYearMonth === null
                                    || !empty($utilMonthsRecorded[$ctrIdInt][$prevYearMonth]);

                                // HTML สถานะมิเตอร์ (แสดงใต้สถานะบิล สำหรับแถว ⏳ เท่านั้น)
                                $openBillingJs = "openBillingModal({$tenant['ctr_id']}, '"
                                    . htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8') . "', '"
                                    . htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8') . "', '"
                                    . htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8') . "', '"
                                    . htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8') . "', "
                                    . (int)$tenant['type_price'] . ")";
                                // JS สำหรับปุ่มจดมิเตอร์
                                $openMeterJs = fn(string $ym) =>
                                    "openMeterOnlyModal("
                                    . (int)$tenant['ctr_id'] . ", '"
                                    . htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8') . "', '"
                                    . htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8') . "', '"
                                    . htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') . "')";
                                if ($meterBillDone) {
                                    $meterStatusHtml = '<span style="display:inline-block;margin-top:0.25rem;font-size:0.78rem;color:#4ade80;">✓ จดมิเตอร์แล้ว</span>';
                                } elseif (!$meterPrevDone && $prevYearMonth !== null) {
                                    $prevDisp = date('m/Y', strtotime($prevYearMonth . '-01'));
                                    $meterStatusHtml = '<button type="button" onclick="' . htmlspecialchars($openMeterJs($prevYearMonth), ENT_QUOTES, 'UTF-8') . '"'
                                        . ' style="display:inline-block;margin-top:0.25rem;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);color:#f87171;font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:12px;cursor:pointer;"'
                                        . '>⚠ ต้องจดมิเตอร์ (' . htmlspecialchars($prevDisp, ENT_QUOTES, 'UTF-8') . ') ก่อน</button>';
                                } else {
                                    $billDisp   = $firstBillMonthDisplay !== '-' ? $firstBillMonthDisplay : '';
                                    $openMeterYm = $billYearMonth ?? '';
                                    $meterStatusHtml = '<button type="button" onclick="' . htmlspecialchars($openMeterJs($openMeterYm), ENT_QUOTES, 'UTF-8') . '"'
                                        . ' style="display:inline-block;margin-top:0.25rem;background:rgba(20,184,166,0.12);border:1px solid rgba(20,184,166,0.35);color:#2dd4bf;font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:12px;cursor:pointer;"'
                                        . '>📋 จดมิเตอร์' . ($billDisp ? ' (' . htmlspecialchars($billDisp, ENT_QUOTES, 'UTF-8') . ')' : '') . '</button>';
                                }
                                // ---------------------------------------------------

                                $isCancelPending = ((string)($tenant['ctr_status'] ?? '') === '2');

                                $step5CircleClass = $step5 ? 'completed' : (($currentStep == 5) ? 'current' : 'pending');
                                $step5CircleLabel = $step5 ? '✓' : '5';
                                $step5Tooltip = '5. เริ่มบิลรายเดือน';

                                if ($step5) {
                                    if ($firstBillPaid) {
                                        $step5CircleClass = 'completed';
                                        $step5CircleLabel = '✓';
                                        $step5Tooltip = '5. บิลเดือนแรกชำระแล้ว (' . $firstBillMonthDisplay . ')';
                                    } elseif ($firstBillWaiting) {
                                        $step5CircleClass = 'wait';
                                        $step5CircleLabel = '<svg class="wait-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="animation-direction:reverse"/></svg>';
                                        $step5Tooltip = '5. บิลเดือนแรก (' . $firstBillMonthDisplay . ') รอตรวจสอบหลักฐาน';
                                    } elseif (!$meterBillDone) {
                                        // ยังไม่จดมิเตอร์เดือนนี้ (หรือเดือนก่อน)
                                        $step5CircleClass = 'meter-pending';
                                        $meterSvg = '<svg class="meter-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
                                            . '<rect x="3" y="11" width="18" height="10" rx="2" stroke="#34d399" stroke-width="2"/>'
                                            . '<path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="#34d399" stroke-width="2" stroke-linecap="round"/>'
                                            . '<circle cx="12" cy="16" r="1.5" fill="#34d399"/>'
                                            . '</svg>';
                                        $step5CircleLabel = $meterSvg;
                                        $tooltipPrefix = !$meterPrevDone && $prevYearMonth !== null
                                            ? '5. ต้องจดมิเตอร์ (' . date('m/Y', strtotime($prevYearMonth . '-01')) . ') ก่อน'
                                            : '5. ยังไม่จดมิเตอร์';
                                        $step5Tooltip = $tooltipPrefix . ($firstBillMonthDisplay !== '-' ? ' (' . $firstBillMonthDisplay . ')' : '');
                                    } else {
                                        $step5CircleClass = 'wait';
                                        $step5CircleLabel = '<svg class="wait-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="animation-direction:reverse"/></svg>';
                                        if ($firstBillMonthDisplay !== '-') {
                                            $step5Tooltip = $firstBillDueReached
                                                ? '5. บิลเดือนแรก (' . $firstBillMonthDisplay . ') รอชำระ'
                                                : '5. บิลเดือนแรก (' . $firstBillMonthDisplay . ') ยังไม่ถึงกำหนด';
                                        } else {
                                            $step5Tooltip = '5. รอสร้างบิลเดือนแรก';
                                        }
                                    }
                                }

                                if ($step3 && !$step4 && $currentStep >= 4) {
                                    $currentStep = 4;
                                }
                                if ($step4 && !$step5 && $currentStep >= 5) {
                                    $currentStep = 5;
                                }
                                ?>
                                <tr<?php if ($isCancelPending): ?> style="background:rgba(239,68,68,0.05)!important;border-left:3px solid rgba(239,68,68,0.45);"<?php endif; ?>>
                                    <td>
                                        <div class="tenant-info">
                                            <span class="tenant-name"><?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="tenant-phone"><?php echo htmlspecialchars($tenant['tnt_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($tenant['room_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <br>
                                        <span style="font-size: 0.85rem; color: rgba(255,255,255,0.7);">
                                            <?php echo htmlspecialchars($tenant['type_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="step-indicator">
                                            <div class="step-circle <?php echo $step1 ? 'completed' : ($currentStep == 1 ? 'current' : 'pending'); ?>" data-tooltip="1. ยืนยันจอง" <?php if ($step1): ?>onclick="openBookingModal(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['room_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>, '<?php echo date('d/m/Y', strtotime($tenant['bkg_date'])); ?>', true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step1 ? '✓' : '1'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step2 ? 'completed' : ($currentStep == 2 ? 'current' : 'pending'); ?>" data-tooltip="2. ยืนยันชำระเงินจอง" <?php if ($step2): ?>onclick="openPaymentModal(<?php echo $tenant['bp_id']; ?>, <?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['bp_amount'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['bp_proof'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step2 ? '✓' : '2'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step3 ? 'completed' : ($currentStep == 3 ? 'current' : 'pending'); ?>" data-tooltip="3. สร้างสัญญา" <?php if ($step3): ?>onclick="openContractModal('<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['room_id']; ?>, <?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['bkg_checkin_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['ctr_start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['ctr_end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['bp_amount'] ?? 0; ?>, true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step3 ? '✓' : '3'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step4 ? 'completed' : ($currentStep == 4 ? 'current' : 'pending'); ?>" data-tooltip="4. เช็คอิน" <?php if ($step4): ?>onclick="openCheckinModal(<?php echo $tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_start'] ?? 'now')); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_end'] ?? 'now')); ?>', '<?php echo htmlspecialchars((string)($tenant['checkin_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)($tenant['water_meter_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)($tenant['elec_meter_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', true)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step4 ? '✓' : '4'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step5CircleClass; ?>" data-tooltip="<?php echo htmlspecialchars($step5Tooltip, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($step5): ?>onclick="openBillingModal(<?php echo $tenant['ctr_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step5CircleLabel; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isCancelPending): ?>
                                            <div style="display:flex;flex-direction:column;align-items:flex-start;gap:0.4rem;">
                                                <div style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.28rem 0.7rem;border-radius:20px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.4);color:#f87171;font-size:0.82rem;font-weight:700;">
                                                    ⚠ ผู้เช่าแจ้งยกเลิกสัญญา
                                                </div>
                                                <a href="http://project.3bbddns.com:36140/dormitory_management/Reports/manage_contracts.php?ctr_id=<?php echo (int)($tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0); ?>" style="font-size:0.78rem;color:#60a5fa;text-decoration:none;font-weight:600;">จัดการสัญญา →</a>
                                            </div>
                                        <?php elseif ($tenant['workflow_id'] === null): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openBookingModal(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['room_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>, '<?php echo date('d/m/Y', strtotime($tenant['bkg_date'])); ?>')">ยืนยันจอง</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>')">ยกเลิก</button>
                                        <?php elseif ($currentStep == 1): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openBookingModal(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['room_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>, '<?php echo date('d/m/Y', strtotime($tenant['bkg_date'])); ?>')">ยืนยันจอง</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>')">ยกเลิก</button>
                                        <?php elseif ($currentStep == 2): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openPaymentModal(<?php echo $tenant['bp_id']; ?>, <?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['bp_amount'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['bp_proof'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">ยืนยันชำระเงินจอง</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>')">ยกเลิก</button>
                                        <?php elseif ($currentStep == 3): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openContractModal(
                                                '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>',
                                                <?php echo $tenant['room_id']; ?>,
                                                <?php echo $tenant['bkg_id']; ?>,
                                                '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>',
                                                '<?php echo htmlspecialchars($tenant['room_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                                '<?php echo htmlspecialchars($tenant['type_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                                <?php echo $tenant['type_price'] ?? 0; ?>,
                                                '<?php echo htmlspecialchars($tenant['bkg_checkin_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                                '<?php echo htmlspecialchars($tenant['ctr_start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                                '<?php echo htmlspecialchars($tenant['ctr_end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                                <?php echo $tenant['bp_amount'] ?? 0; ?>
                                            )">สร้างสัญญา</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>')">ยกเลิก</button>
                                        <?php elseif ($currentStep == 4): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openCheckinModal(<?php echo $tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_start'] ?? 'now')); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_end'] ?? 'now')); ?>', '<?php echo htmlspecialchars((string)($tenant['checkin_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)($tenant['water_meter_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)($tenant['elec_meter_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">เช็คอิน</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>')">ยกเลิก</button>
                                        <?php elseif ($currentStep == 5): ?>
                                            <?php if ($step5 && $firstBillPaid): ?>
                                                <span style="color: #16a34a; font-weight: 600;">✓ บิลเดือนแรกชำระแล้ว (<?php echo htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8'); ?>)</span>
                                            <?php elseif ($step5 && $firstBillWaiting): ?>
                                                <button type="button"
                                                    onclick="openBillingModal(<?php echo $tenant['ctr_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$tenant['type_price']; ?>)"
                                                    style="background:rgba(96,165,250,0.15);border:1px solid rgba(96,165,250,0.4);color:#60a5fa;font-weight:600;font-size:0.82rem;padding:0.3rem 0.75rem;border-radius:20px;cursor:pointer;transition:background 0.2s;"
                                                    onmouseover="this.style.background='rgba(96,165,250,0.28)'" onmouseout="this.style.background='rgba(96,165,250,0.15)'"
                                                >🔍 <?php echo $firstBillMonthDisplay !== '-' ? '(' . htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8') . ')' : ''; ?> รอตรวจสอบ</button>
                                        <?php elseif ($step5): ?>
                                                <div style="display:flex;flex-direction:column;align-items:flex-start;gap:0;">
                                                    <?php if ($meterBillDone): ?>
                                                    <span style="display:inline-flex;align-items:center;gap:0.35rem;color:#d97706;font-weight:600;">
                                                        <svg style="flex-shrink:0;" width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round" style="transform-origin:center;animation:waitSpin 1s linear infinite;"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="transform-origin:center;animation:waitSpin 1s linear infinite reverse;"/></svg>
                                                        <?php echo $firstBillMonthDisplay !== '-' ? '(' . htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8') . ')' : ''; ?> <?php echo $firstBillDueReached ? 'รอชำระ' : 'ยังไม่ถึงกำหนด'; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php echo $meterStatusHtml; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #16a34a; font-weight: 600;">ระบบเริ่มบิลให้อัตโนมัติ</span>
                                            <?php endif; ?>
                                        <?php elseif ($step5 && !$firstBillPaid && $firstBillWaiting): ?>
                                            <button type="button"
                                                onclick="openBillingModal(<?php echo $tenant['ctr_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$tenant['type_price']; ?>)"
                                                style="background:rgba(96,165,250,0.15);border:1px solid rgba(96,165,250,0.4);color:#60a5fa;font-weight:600;font-size:0.82rem;padding:0.3rem 0.75rem;border-radius:20px;cursor:pointer;transition:background 0.2s;"
                                                onmouseover="this.style.background='rgba(96,165,250,0.28)'" onmouseout="this.style.background='rgba(96,165,250,0.15)'"
                                            >🔍 <?php echo $firstBillMonthDisplay !== '-' ? '(' . htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8') . ')' : ''; ?> รอตรวจสอบ</button>
                                        <?php elseif ($step5 && !$firstBillPaid): ?>
                                            <div style="display:flex;flex-direction:column;align-items:flex-start;gap:0;">
                                                <?php if ($meterBillDone): ?>
                                                <span style="display:inline-flex;align-items:center;gap:0.35rem;color:#d97706;font-weight:600;">
                                                    <svg style="flex-shrink:0;" width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round" style="transform-origin:center;animation:waitSpin 1s linear infinite;"/><circle cx="12" cy="12" r="5" stroke="#b45309" stroke-width="2" stroke-dasharray="12 32" stroke-linecap="round" style="transform-origin:center;animation:waitSpin 1s linear infinite reverse;"/></svg>
                                                    <?php echo $firstBillMonthDisplay !== '-' ? '(' . htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8') . ')' : ''; ?> <?php echo $firstBillDueReached ? 'รอชำระ' : 'ยังไม่ถึงกำหนด'; ?>
                                                </span>
                                                <?php endif; ?>
                                                <?php echo $meterStatusHtml; ?>
                                            </div>
                                        <?php elseif ($step5 && $firstBillPaid): ?>
                                            <span style="color: #16a34a; font-weight: 600;">✓ บิลเดือนแรกชำระแล้ว (<?php echo htmlspecialchars($firstBillMonthDisplay, ENT_QUOTES, 'UTF-8'); ?>)</span>
                                        <?php elseif ((int)($tenant['completed'] ?? 0) === 1 || $currentStep >= 6): ?>
                                            <span style="color: #16a34a; font-weight: 600;">ดำเนินการครบทุกขั้นตอนแล้ว</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                    <input type="hidden" name="bkg_id" id="modal_bkg_id">
                    <input type="hidden" name="tnt_id" id="modal_booking_tnt_id">
                    <input type="hidden" name="room_id" id="modal_room_id">
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" id="bookingCloseBtn" class="btn-modal btn-modal-secondary" onclick="closeBookingModal()">ยกเลิก</button>
                <button type="button" id="bookingSubmitBtn" class="btn-modal btn-modal-primary" style="background: #3b82f6;" onclick="document.getElementById('bookingForm').submit()">✓ ยืนยันการจอง</button>
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
                    <input type="hidden" name="tnt_id" id="modal_contract_tnt_id">
                    <input type="hidden" name="room_id" id="modal_contract_room_id">
                    <input type="hidden" name="bkg_id" id="modal_contract_bkg_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>วันเริ่มสัญญา *</label>
                            <input type="date" name="ctr_start" id="modal_contract_start" required>
                        </div>
                        <div class="form-group">
                            <label>ระยะเวลาสัญญา *</label>
                            <select name="contract_duration" id="modal_contract_duration" required>
                                <option value="3">3 เดือน</option>
                                <option value="6" selected>6 เดือน</option>
                                <option value="12">12 เดือน</option>
                            </select>
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
            </div>

            <div class="modal-footer">
                <button type="button" id="contractCloseBtn" class="btn-modal btn-modal-secondary" onclick="closeContractModal()">ยกเลิก</button>
                <button type="button" id="contractSubmitBtn" class="btn-modal btn-modal-primary" style="background: #8b5cf6;" onclick="document.getElementById('contractForm').submit()">✓ สร้างสัญญา</button>
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
                        <input type="date" name="checkin_date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 0.875rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; font-size: 1rem;">
                    </div>

                    <!-- Section 2: มิเตอร์ -->
                    <div style="margin-bottom: 1.5rem; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 12px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <span style="background: #22c55e; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">2</span>
                            <span style="font-weight: 600; color: #f1f5f9;">⚡ บันทึกมิเตอร์เริ่มต้น</span>
                            <span style="font-size: 0.75rem; background: rgba(34, 197, 94, 0.2); color: #4ade80; padding: 0.25rem 0.5rem; border-radius: 4px;">สำคัญ</span>
                        </div>
                        <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin: 0 0 1rem 0;">📌 ใช้คำนวณค่าน้ำ-ไฟรายเดือน กรุณากรอกตัวเลขจากมิเตอร์จริง</p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;">💧 มิเตอร์น้ำ (หน่วย)</label>
                                <input type="number" name="water_meter_start" step="0.01" min="0" required placeholder="เช่น 1234.56" style="width: 100%; padding: 0.875rem 1rem; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; font-size: 1.1rem; font-weight: 500;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;">⚡ มิเตอร์ไฟ (หน่วย)</label>
                                <input type="number" name="elec_meter_start" step="0.01" min="0" required placeholder="เช่น 5678.90" style="width: 100%; padding: 0.875rem 1rem; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; font-size: 1.1rem; font-weight: 500;">
                            </div>
                        </div>
                    </div>

                    <!-- Summary Box -->
                    <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(234, 88, 12, 0.08)); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 1rem 1.25rem;">
                        <h4 style="margin: 0 0 0.75rem 0; color: #fbbf24; font-size: 1rem;">✅ ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.25rem; margin: 0; line-height: 1.8; color: #e2e8f0; font-size: 0.9rem;">
                            <li>บันทึกเลขมิเตอร์เริ่มต้น → <span style="color: #4ade80;">ใช้คำนวณค่าน้ำ-ไฟ</span></li>
                            <li>อัปเดตสถานะห้อง → <span style="color: #4ade80;">"มีผู้เช่า"</span></li>
                            <li>อัปเดตสถานะผู้เช่า → <span style="color: #4ade80;">"พักอยู่"</span></li>
                        </ul>
                    </div>
                </form>
            </div>

            <div class="modal-footer" style="display: flex; gap: 1rem; justify-content: flex-end; padding: 1.5rem 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="button" id="checkinCloseBtn" class="btn-modal btn-modal-secondary" onclick="closeCheckinModal()" style="padding: 0.875rem 1.5rem;">ยกเลิก</button>
                <button type="button" id="checkinSubmitBtn" class="btn-modal btn-modal-primary" onclick="validateAndSubmitCheckin()" style="padding: 0.875rem 2rem; background: linear-gradient(135deg, #f59e0b, #d97706); font-weight: 600;">🏠 บันทึกเช็คอิน</button>
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
                                <input type="number" id="meterWaterInput" min="0" placeholder="เลขมิเตอร์ใหม่"
                                    style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.6);border:1px solid rgba(96,165,250,0.4);border-radius:7px;color:#f8fafc;padding:0.5rem 0.65rem;font-size:0.9rem;outline:none;"
                                    oninput="updateMeterPreview()">
                            </div>
                            <!-- Elec -->
                            <div>
                                <div class="billing-inline-icon" style="font-size:0.75rem;color:rgba(148,163,184,0.8);margin-bottom:0.3rem;">
                                    <svg class="billing-svg-icon billing-svg-elec" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"></path>
                                    </svg>
                                    <span>มิเตอร์ไฟ (ครั้งก่อน: <span id="prevElecDisplay">-</span>)</span>
                                </div>
                                <input type="number" id="meterElecInput" min="0" placeholder="เลขมิเตอร์ใหม่"
                                    style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.6);border:1px solid rgba(251,191,36,0.4);border-radius:7px;color:#f8fafc;padding:0.5rem 0.65rem;font-size:0.9rem;outline:none;"
                                    oninput="updateMeterPreview()">
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
                <div id="billSectionsWrapper" style="display:none;">
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
        <div class="modal-container" style="max-width:420px;">
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
                        <input type="number" id="moWaterInput" min="0" placeholder="เลขมิเตอร์ใหม่"
                            style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.7);border:1px solid rgba(96,165,250,0.4);border-radius:8px;color:#f8fafc;padding:0.55rem 0.7rem;font-size:0.95rem;outline:none;"
                            onfocus="this.style.borderColor='#60a5fa'" onblur="this.style.borderColor='rgba(96,165,250,0.4)'" oninput="updateMoPreview()">
                    </div>
                    <div>
                        <div style="font-size:0.8rem;font-weight:600;color:#fbbf24;margin-bottom:0.3rem;">⚡ มิเตอร์ไฟ</div>
                        <div style="font-size:0.72rem;color:rgba(148,163,184,0.8);margin-bottom:0.4rem;">ค่าก่อน: <span id="moPrevElec" style="color:#f8fafc;font-weight:600;">...</span></div>
                        <input type="number" id="moElecInput" min="0" placeholder="เลขมิเตอร์ใหม่"
                            style="width:100%;box-sizing:border-box;background:rgba(15,23,42,0.7);border:1px solid rgba(251,191,36,0.4);border-radius:8px;color:#f8fafc;padding:0.55rem 0.7rem;font-size:0.95rem;outline:none;"
                            onfocus="this.style.borderColor='#fbbf24'" onblur="this.style.borderColor='rgba(251,191,36,0.4)'" oninput="updateMoPreview()">
                    </div>
                </div>
                <div id="moPreview" style="display:none;flex-wrap:wrap;gap:0.75rem;padding:0.6rem 0.75rem;border-radius:8px;background:rgba(15,23,42,0.4);border:1px solid rgba(255,255,255,0.08);font-size:0.82rem;color:rgba(226,232,240,0.9);margin-bottom:0.85rem;"></div>
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
                <button type="button" id="paymentSubmitBtn" class="btn-modal btn-modal-primary" style="background: #22c55e;" onclick="document.getElementById('paymentForm').submit()">✓ ยืนยันการชำระเงิน</button>
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
        form.water_meter_start.value = waterMeter !== '' ? waterMeter : '';
        form.elec_meter_start.value = elecMeter !== '' ? elecMeter : '';

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

        // 1. Validate วันที่เช็คอิน
        const checkinDate = form.checkin_date.value.trim();
        if (!checkinDate) {
            errors.push('กรุณาระบุวันที่เช็คอิน');
        } else {
            const date = new Date(checkinDate);
            if (isNaN(date.getTime())) {
                errors.push('วันที่เช็คอิน ไม่ถูกต้อง');
            }
        }

        // 2. Validate มิเตอร์น้ำ
        const waterMeter = form.water_meter_start.value.trim();
        if (!waterMeter) {
            errors.push('กรุณาระบุเลขมิเตอร์น้ำ');
        } else {
            const water = parseFloat(waterMeter);
            if (isNaN(water) || water < 0) {
                errors.push('เลขมิเตอร์น้ำ ต้องเป็นตัวเลขที่มากกว่าหรือเท่ากับ 0');
            }
        }

        // 3. Validate มิเตอร์ไฟ
        const elecMeter = form.elec_meter_start.value.trim();
        if (!elecMeter) {
            errors.push('กรุณาระบุเลขมิเตอร์ไฟฟ้า');
        } else {
            const elec = parseFloat(elecMeter);
            if (isNaN(elec) || elec < 0) {
                errors.push('เลขมิเตอร์ไฟฟ้า ต้องเป็นตัวเลขที่มากกว่าหรือเท่ากับ 0');
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
    function openContractModal(tntId, roomId, bkgId, tntName, roomNumber, typeName, typePrice, bkgCheckinDate, ctrStart, ctrEnd, bookingAmount, readOnly = false) {
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

        const startDate = toDateInputValue(bkgCheckinDate) || toDateInputValue(ctrStart) || defaultStart;
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

        document.getElementById('contractModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
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
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        return `${monthNames[date.getMonth()]} ${date.getFullYear()}`;
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
                <!-- progress bar -->
                <div style="height:5px;background:rgba(255,255,255,0.08);border-radius:99px;margin-bottom:0.9rem;overflow:hidden;">
                    <div style="height:100%;width:${pct.toFixed(1)}%;background:${barColor};border-radius:99px;transition:width 0.4s;"></div>
                </div>
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
                const firstBillMonth = firstBill?.bill_month || '';
                if (firstBillMonth) {
                    document.getElementById('nextMonthDisplay').textContent = formatMonthDisplay(firstBillMonth);
                }

                // ถ้า first bill และ latest bill เป็นเดือนเดียวกัน (expense เดียวกัน) → แสดงแค่ตารางเดียว
                const isSameExpense = firstBill?.has_expense && latestBill?.has_expense
                    && Number(firstBill.expense_id) === Number(latestBill.expense_id);

                if (isSameExpense) {
                    firstBillPaymentsSection.style.display = 'none';
                    firstBillPaymentsSection.innerHTML = '';
                    renderBillSection('latestBillPaymentsSection', 'รายการชำระเดือนแรก (บิลปัจจุบัน)', latestBill, {
                        allowReviewAction: true,
                        emptyHint: 'ยังไม่มีรายการชำระ',
                    });
                } else {
                    firstBillPaymentsSection.style.display = '';
                    renderBillSection('firstBillPaymentsSection', 'รายการชำระเดือนแรก', firstBill, {
                        allowReviewAction: false,
                        emptyHint: 'ยังไม่มีบิลเดือนแรกในระบบ',
                    });
                    renderBillSection('latestBillPaymentsSection', 'บิลล่าสุดที่ต้องจัดการ', latestBill, {
                        allowReviewAction: true,
                        emptyHint: 'ยังไม่มีบิลที่ออกแล้วสำหรับจัดการ',
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
                // รีโหลดหน้าเพื่ออัปเดตตารางผู้เช่าและสถานะ Step
                window.location.reload();
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

        document.getElementById('meterOnlyModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        fetch('../Manage/get_utility_reading.php?ctr_id=' + encodeURIComponent(ctrId))
            .then(r => r.json())
            .then(d => {
                if (d.error) return;
                _moPrevWater = d.prev_water || 0;
                _moPrevElec  = d.prev_elec  || 0;
                _moRateElec  = d.rate_elec  || 8;
                document.getElementById('moPrevWater').textContent = _moPrevWater;
                document.getElementById('moPrevElec').textContent  = _moPrevElec;
                if (d.saved && d.meter_month == _moMonth && d.meter_year == _moYear) {
                    document.getElementById('moWaterInput').value    = d.curr_water ?? '';
                    document.getElementById('moElecInput').value     = d.curr_elec  ?? '';
                    document.getElementById('moWaterInput').disabled = true;
                    document.getElementById('moElecInput').disabled  = true;
                    btn.style.display = 'none';
                    const m = document.getElementById('moMsg');
                    m.style.color = '#4ade80'; m.textContent = '✓ บันทึกแล้ว';
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
    }

    function updateMoPreview() {
        const wv  = document.getElementById('moWaterInput').value.trim();
        const ev  = document.getElementById('moElecInput').value.trim();
        const pre = document.getElementById('moPreview');
        if (wv === '' && ev === '') { pre.style.display = 'none'; return; }
        pre.style.display = 'flex';
        const parts = [];
        if (wv !== '') {
            const used = parseInt(wv, 10) - _moPrevWater;
            const cost = used <= 0 ? 0 : (used <= 10 ? 200 : 200 + (used - 10) * 25);
            parts.push('💧 ใช้ <b style="color:#60a5fa">' + Math.max(0, used) + '</b> หน่วย → <b style="color:#4ade80">฿' + cost.toLocaleString() + '</b>');
        }
        if (ev !== '') {
            const used = parseInt(ev, 10) - _moPrevElec;
            const cost = Math.max(0, used) * _moRateElec;
            parts.push('⚡ ใช้ <b style="color:#fbbf24">' + Math.max(0, used) + '</b> หน่วย → <b style="color:#4ade80">฿' + cost.toLocaleString() + '</b>');
        }
        pre.innerHTML = parts.join('<span style="color:rgba(255,255,255,0.2);margin:0 0.35rem">|</span>');
    }

    function saveMeterOnly() {
        const wv  = document.getElementById('moWaterInput').value.trim();
        const ev  = document.getElementById('moElecInput').value.trim();
        const btn = document.getElementById('moSaveBtn');
        const msg = document.getElementById('moMsg');
        if (wv === '' && ev === '') {
            msg.style.color = '#fca5a5';
            msg.textContent = 'กรุณากรอกค่ามิเตอร์อย่างน้อย 1 ค่า';
            return;
        }
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
        msg.textContent = '';
        const fd = new FormData();
        fd.append('ctr_id',      _moCtrId);
        fd.append('water_new',   wv);
        fd.append('elec_new',    ev);
        fd.append('meter_month', _moMonth);
        fd.append('meter_year',  _moYear);
        fetch('../Manage/save_utility_ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    msg.style.color = '#4ade80';
                    msg.textContent = '✓ บันทึกสำเร็จ';
                    btn.style.display = 'none';
                    document.getElementById('moWaterInput').disabled = true;
                    document.getElementById('moElecInput').disabled  = true;
                    setTimeout(() => { closeMeterOnlyModal(); window.location.reload(); }, 700);
                } else {
                    msg.style.color = '#fca5a5';
                    msg.textContent = d.error || 'เกิดข้อผิดพลาด';
                    btn.disabled = false;
                    btn.textContent = '✓ บันทึกมิเตอร์';
                }
            })
            .catch(() => {
                msg.style.color = '#fca5a5';
                msg.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
                btn.disabled = false;
                btn.textContent = '✓ บันทึกมิเตอร์';
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

    function loadMeterReading(ctrId) {
        _meterCtrId = ctrId;
        const badge = document.getElementById('meterSavedBadge');
        const btn   = document.getElementById('saveMeterBtn');
        badge.style.display = 'none';
        btn.style.display = 'inline-block';
        document.getElementById('meterMsg').textContent = '';
        document.getElementById('meterWaterInput').value = '';
        document.getElementById('meterElecInput').value  = '';
        document.getElementById('meterWaterInput').disabled = false;
        document.getElementById('meterElecInput').disabled  = false;
        document.getElementById('meterPreview').style.display = 'none';
        document.getElementById('prevWaterDisplay').textContent = '...';
        document.getElementById('prevElecDisplay').textContent  = '...';

        fetch(`../Manage/get_utility_reading.php?ctr_id=${encodeURIComponent(ctrId)}`)
            .then(r => r.json())
            .then(d => {
                if (d.error) return;
                _meterPrevWater  = d.prev_water  || 0;
                _meterPrevElec   = d.prev_elec   || 0;
                _meterMonth      = d.meter_month || (new Date().getMonth() + 1);
                _meterYear       = d.meter_year  || (new Date().getFullYear());
                _meterRateWater  = d.rate_water  || 18;
                _meterRateElec   = d.rate_elec   || 8;

                document.getElementById('prevWaterDisplay').textContent = _meterPrevWater;
                document.getElementById('prevElecDisplay').textContent  = _meterPrevElec;

                if (d.saved) {
                    // already saved this month — show saved badge + allow edit and re-save
                    badge.style.display = 'inline-block';
                    btn.style.display   = 'inline-block';
                    btn.textContent     = 'อัปเดตมิเตอร์';
                    document.getElementById('meterWaterInput').value    = d.curr_water ?? '';
                    document.getElementById('meterElecInput').value     = d.curr_elec  ?? '';
                    document.getElementById('meterWaterInput').disabled = false;
                    document.getElementById('meterElecInput').disabled  = false;
                    updateMeterPreview();
                    // โหลดและแสดงรายการบิล
                    document.getElementById('billSectionsWrapper').style.display = '';
                    document.getElementById('meterNoticeBlock').style.display = 'none';
                    refreshBillingPayments(_meterCtrId);
                } else {
                    // ยังไม่จดมิเตอร์ — ซ่อนบิล แสดงแจ้งเตือน
                    document.getElementById('billSectionsWrapper').style.display = 'none';
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
            const cost = used <= 0 ? 0 : (used <= 10 ? 200 : 200 + (used - 10) * 25);
            parts.push(`<span class="billing-inline-icon" style="color:#60a5fa;"><svg class="billing-svg-icon billing-svg-water" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C9 7 6 9.8 6 14a6 6 0 0 0 12 0c0-4.2-3-7-6-11z"></path></svg><span>ใช้ <b style="color:#60a5fa">${Math.max(0,used)}</b> หน่วย → <b style="color:#4ade80">฿${cost.toLocaleString()}</b></span></span>`);
        }
        if (elecVal !== '') {
            const used = parseInt(elecVal, 10) - _meterPrevElec;
            const cost = Math.max(0, used) * _meterRateElec;
            parts.push(`<span class="billing-inline-icon" style="color:#fbbf24;"><svg class="billing-svg-icon billing-svg-elec" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"></path></svg><span>ใช้ <b style="color:#fbbf24">${Math.max(0,used)}</b> หน่วย → <b style="color:#4ade80">฿${cost.toLocaleString()}</b></span></span>`);
        }
        preview.innerHTML = parts.join('<span style="color:rgba(255,255,255,0.2);margin:0 0.25rem">|</span>');
    }

    function saveMeterReading() {
        const waterVal = document.getElementById('meterWaterInput').value.trim();
        const elecVal  = document.getElementById('meterElecInput').value.trim();
        const btn      = document.getElementById('saveMeterBtn');
        const msg      = document.getElementById('meterMsg');
        if (waterVal === '' && elecVal === '') {
            msg.style.color = '#fca5a5';
            msg.textContent = 'กรุณากรอกค่ามิเตอร์อย่างน้อย 1 ค่า';
            return;
        }
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
        msg.textContent = '';

        const fd = new FormData();
        fd.append('ctr_id',      _meterCtrId);
        fd.append('water_new',   waterVal);
        fd.append('elec_new',    elecVal);
        fd.append('meter_month', _meterMonth);
        fd.append('meter_year',  _meterYear);

        fetch('../Manage/save_utility_ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    msg.style.color = '#4ade80';
                    msg.textContent = '✓ บันทึกสำเร็จ';
                    // Keep button to allow update/edit if needed
                    btn.style.display = 'inline-block';
                    btn.textContent = 'อัปเดตมิเตอร์';
                    document.getElementById('meterSavedBadge').style.display = 'inline-block';
                    document.getElementById('meterWaterInput').disabled = false;
                    document.getElementById('meterElecInput').disabled  = false;
                    // รีโหลดยอดบิลใหม่หลังจดมิเตอร์ และแสดง bill sections
                    document.getElementById('billSectionsWrapper').style.display = '';
                    document.getElementById('meterNoticeBlock').style.display = 'none';
                    refreshBillingPayments(_meterCtrId);
                } else {
                    msg.style.color = '#fca5a5';
                    msg.textContent = d.error || 'เกิดข้อผิดพลาด';
                    btn.disabled = false;
                    btn.textContent = 'บันทึกมิเตอร์';
                }
            })
            .catch(() => {
                msg.style.color = '#fca5a5';
                msg.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
                btn.disabled = false;
                btn.textContent = 'บันทึกมิเตอร์';
            });
    }
    // ---- end meter helpers ----

    function openBillingModal(ctrId, tntId, tntName, roomNumber, roomType, roomPrice) {
        // ตั้งค่า hidden fields
        document.getElementById('modal_billing_ctr_id').value = ctrId;
        document.getElementById('modal_billing_tnt_id').value = tntId;
        document.getElementById('modal_billing_room_price').value = roomPrice;
        
        // แสดงข้อมูลผู้เช่า
        document.getElementById('billingBarTenant').textContent = tntName;
        document.getElementById('billingBarRoom').textContent = `ห้อง ${roomNumber} (${roomType}) • ฿${Number(roomPrice).toLocaleString()}/เดือน`;
        document.getElementById('billingModalSub').textContent = `ห้อง ${roomNumber} — ฿${Number(roomPrice).toLocaleString()}/เดือน`;

        // คำนวณเดือนถัดไป
        const now = new Date();
        const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('nextMonthDisplay').textContent = 
            `${monthNames[nextMonth.getMonth()]} ${nextMonth.getFullYear()}`;

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
                setTimeout(() => location.reload(), 1500);
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
</script>
