<?php
session_start();
require_once __DIR__ . '/../ConnectDB.php';

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

$admin_name = $_SESSION['admin_username'];

$bookings = [];
$utilities = [];
$expenses = [];
$pendingPayments = [];
$pendingRepairs = [];
$wizardItems = [];
$pendingWater = 0;
$pendingElec = 0;
$wizardPendingCount = 0;
$themeColor = '#0f172a';
$isLight = false;

try {
    $pdo = connectDB();

    // ดึง theme_color
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
    $themeColor = $stmt->fetchColumn() ?: '#0f172a';
    $hex = ltrim($themeColor, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    $isLight = $brightness > 155;

    // === Tab 1: การจอง (รอดำเนินการ) ===
    try {
        $bookingStmt = $pdo->query("
            SELECT b.bkg_id, b.bkg_date, b.bkg_status,
                   COALESCE(t.tnt_name, '') AS tnt_name, t.tnt_phone,
                   r.room_number, rt.type_name AS roomtype_name
            FROM booking b
            LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
            LEFT JOIN room r ON b.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                        LEFT JOIN contract active_ctr
                            ON active_ctr.room_id = b.room_id
                         AND active_ctr.ctr_status IN ('0','2')
            WHERE b.bkg_status = 1
                            AND active_ctr.ctr_id IS NULL
            ORDER BY b.bkg_date DESC
            LIMIT 50
        ");
        $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $bookings = [];
    }

    // === Tab 2: จดมิเตอร์ (ห้องที่ยังไม่จด) ===
    try {
        $utilityStmt = $pdo->query("
            SELECT r.room_id, r.room_number, rt.type_name AS roomtype_name,
                   COALESCE(t.tnt_name, '') AS tnt_name,
                   c.ctr_id,
                   u.utl_id, u.utl_water_start, u.utl_water_end, u.utl_elec_start, u.utl_elec_end
            FROM contract c
            INNER JOIN (
                SELECT room_id, MAX(ctr_id) AS ctr_id
                FROM contract WHERE ctr_status = '0' GROUP BY room_id
            ) lc ON lc.ctr_id = c.ctr_id
            LEFT JOIN room r ON c.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            LEFT JOIN utility u ON u.ctr_id = c.ctr_id
                AND MONTH(u.utl_date) = MONTH(CURDATE())
                AND YEAR(u.utl_date) = YEAR(CURDATE())
            WHERE (u.utl_id IS NULL OR u.utl_water_end IS NULL OR u.utl_elec_end IS NULL)
            ORDER BY r.room_number ASC
        ");
        $utilities = $utilityStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $utilities = [];
    }

    $pendingWater = 0;
    $pendingElec = 0;
    foreach ($utilities as $u) {
        if (empty($u['utl_id']) || $u['utl_water_end'] === null) $pendingWater++;
        if (empty($u['utl_id']) || $u['utl_elec_end'] === null) $pendingElec++;
    }

    // === Tab 3: ค่าใช้จ่าย ===
    try {
        $expenseStmt = $pdo->query("
             SELECT e.exp_id, e.exp_month, e.exp_total,
                 e.room_price, e.exp_elec_chg, e.exp_water,
                   c.ctr_id, r.room_number, COALESCE(t.tnt_name, '') AS tnt_name,
                                     CASE WHEN EXISTS (
                                         SELECT 1
                                         FROM utility u2
                                         WHERE u2.ctr_id = e.ctr_id
                                             AND YEAR(u2.utl_date) = YEAR(e.exp_month)
                                             AND MONTH(u2.utl_date) = MONTH(e.exp_month)
                                             AND u2.utl_water_end IS NOT NULL
                                             AND u2.utl_elec_end IS NOT NULL
                                     ) THEN 1 ELSE 0 END AS has_complete_meter,
                   COALESCE(pay_agg.approved_amount, 0) AS paid_amount,
                   COALESCE(pay_agg.pending_count, 0) AS pending_count
            FROM expense e
            INNER JOIN contract c ON e.ctr_id = c.ctr_id AND c.ctr_status = '0'
            LEFT JOIN room r ON c.room_id = r.room_id
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            LEFT JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id
            LEFT JOIN (
                SELECT exp_id,
                    SUM(CASE WHEN pay_status = '1' THEN pay_amount ELSE 0 END) AS approved_amount,
                    SUM(CASE WHEN pay_status = '0' AND pay_proof IS NOT NULL AND pay_proof <> '' THEN 1 ELSE 0 END) AS pending_count
                FROM payment GROUP BY exp_id
            ) pay_agg ON pay_agg.exp_id = e.exp_id
            WHERE (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
              AND YEAR(e.exp_month) = YEAR(CURDATE())
              AND MONTH(e.exp_month) = MONTH(CURDATE())
              AND NOT (DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(c.ctr_start, '%Y-%m'))
              AND (
                COALESCE(pay_agg.pending_count, 0) > 0
                OR COALESCE(pay_agg.approved_amount, 0) < COALESCE(e.exp_total, 0)
              )
            ORDER BY r.room_number ASC
        ");
        $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $expenses = [];
    }

    // === Tab 4: การชำระเงิน (รอตรวจสอบ) ===
    try {
        $paymentStmt = $pdo->query("
            SELECT x.payment_kind, x.pay_id, x.pay_amount, x.pay_date, x.pay_status, x.pay_proof, x.pay_remark,
                   x.exp_id, x.exp_month, x.exp_total, x.room_number, x.tnt_name
            FROM (
                SELECT 'pending' AS payment_kind,
                       p.pay_id, p.pay_amount, p.pay_date, p.pay_status, p.pay_proof, p.pay_remark,
                       e.exp_id, e.exp_month, e.exp_total,
                       r.room_number, COALESCE(t.tnt_name, '') AS tnt_name
                FROM payment p
                LEFT JOIN expense e ON p.exp_id = e.exp_id
                LEFT JOIN contract c ON e.ctr_id = c.ctr_id
                LEFT JOIN room r ON c.room_id = r.room_id
                LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
                WHERE p.pay_status = '0'
                  AND p.pay_proof IS NOT NULL AND p.pay_proof <> ''

                UNION ALL

                SELECT 'unpaid' AS payment_kind,
                       NULL AS pay_id,
                       NULL AS pay_amount,
                       NULL AS pay_date,
                       '0' AS pay_status,
                       NULL AS pay_proof,
                       'รอชำระ' AS pay_remark,
                       e.exp_id, e.exp_month, e.exp_total,
                       r.room_number, COALESCE(t.tnt_name, '') AS tnt_name
                FROM expense e
                INNER JOIN contract c ON e.ctr_id = c.ctr_id
                LEFT JOIN room r ON c.room_id = r.room_id
                LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
                LEFT JOIN payment p ON p.exp_id = e.exp_id
                WHERE c.ctr_status = '0'
                  AND p.exp_id IS NULL
                  AND DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
                  AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
            ) x
            ORDER BY x.pay_date DESC, x.exp_month DESC
            LIMIT 50
        ");
        $pendingPayments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $pendingPayments = [];
    }

    // === Tab 5: แจ้งซ่อม (งานค้าง) ===
    try {
        $repairStmt = $pdo->query("
            SELECT r.repair_id, r.repair_date, r.repair_status, r.repair_desc,
                   rm.room_number,
                   COALESCE(t.tnt_name, '') AS tnt_name
            FROM repair r
            LEFT JOIN contract c ON r.ctr_id = c.ctr_id
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            LEFT JOIN room rm ON c.room_id = rm.room_id
            WHERE COALESCE(r.repair_status, '0') IN ('0', '1')
            ORDER BY r.repair_date DESC, r.repair_id DESC
            LIMIT 50
        ");
        $pendingRepairs = $repairStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $pendingRepairs = [];
    }

    // === Wizard: ตัวช่วยผู้เช่าที่ยังค้าง ===
    try {
                $wizardStmt = $pdo->query(" 
                        SELECT COUNT(*) AS incomplete_count
                        FROM booking b
                        LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
                        WHERE b.bkg_status != '0'
                            AND (tw.id IS NULL OR tw.completed = 0)
                ");
        $wizardPendingCount = (int)($wizardStmt->fetch(PDO::FETCH_ASSOC)['incomplete_count'] ?? 0);
    } catch (Exception $e) {
        $wizardPendingCount = 0;
    }

    try {
                $wizardItemsStmt = $pdo->query(" 
                        SELECT b.bkg_id, b.bkg_date,
                                     COALESCE(t.tnt_name, '') AS tnt_name,
                                     COALESCE(r.room_number, '-') AS room_number,
                                     COALESCE(tw.current_step, 0) AS current_step,
                                     COALESCE(tw.completed, 0) AS completed
                        FROM booking b
                        LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
                        LEFT JOIN room r ON b.room_id = r.room_id
                        LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
                        WHERE b.bkg_status != '0'
                            AND (tw.id IS NULL OR tw.completed = 0)
                        ORDER BY b.bkg_date DESC, b.bkg_id DESC
                        LIMIT 50
                ");
        $wizardItems = $wizardItemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $wizardItems = [];
    }

} catch (Exception $e) {
    // Keep partial data if some queries succeeded; fallback only for theme.
    $themeColor = '#0f172a';
    $isLight = false;
}

// นับจำนวนตาม tab
$bookingCount = count($bookings);
$utilityPendingCount = $pendingWater + $pendingElec;
$expenseCount = count($expenses);
$paymentCount = count($pendingPayments);
$repairCount = count($pendingRepairs);
$lightThemeClass = $isLight ? 'light-theme' : '';
?>
<!DOCTYPE html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>งานที่ต้องทำ - ระบบจัดการหอพัก</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <script>
        (function() {
            var c = localStorage.getItem('theme_class');
            if (c) {
                document.documentElement.classList.add(c);
            }
        })();
    </script>
    <style>
        html, body.reports-page {
            font-family: 'Prompt', system-ui, -apple-system, 'Segoe UI', sans-serif !important;
        }

        .todo-tabs {
            display: flex;
            gap: 8px;
            padding: 0 4px;
            margin: 24px 0 24px; /* top & bottom spacing so tabs aren’t tight against navbar */
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            /* additional room on small screens */
            .todo-tabs {
                margin-top: 32px;
            }
        }
        .todo-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            border: 2px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            white-space: nowrap;
            position: relative;
        }
        .todo-tab:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .todo-tab.active {
            color: #ffffff !important;
            border-color: transparent;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.24);
        }
        .todo-tab.active svg {
            color: #ffffff;
        }
        .todo-tab.active[data-tab="booking"] {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .todo-tab.active[data-tab="utility"] {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }
        .todo-tab.active[data-tab="expense"] {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        .todo-tab.active[data-tab="payment"] {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .todo-tab.active[data-tab="repair"] {
            background: linear-gradient(135deg, #14b8a6, #0f766e);
        }
        .todo-tab.active[data-tab="wizard"] {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        .todo-tab .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: bold;
            line-height: 1;
        }
        .todo-tab .tab-badge.booking { background: #f59e0b; color: white; }
        .todo-tab .tab-badge.utility { background: #06b6d4; color: white; }
        .todo-tab .tab-badge.expense { background: #8b5cf6; color: white; }
        .todo-tab .tab-badge.payment { background: #ef4444; color: white; }
        .todo-tab .tab-badge.repair { background: #14b8a6; color: white; }
        .todo-tab .tab-badge.wizard { background: #f59e0b; color: white; }

        .todo-panel {
            display: none;
            animation: fadeIn 0.25s ease;
        }
        .todo-panel.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .todo-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .todo-table thead th {
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .todo-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            vertical-align: middle;
        }
        .todo-table tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }
        .todo-table tbody tr.todo-row-link {
            cursor: pointer;
        }
        .todo-table tbody tr.todo-row-link td:last-child {
            text-align: right;
            white-space: nowrap;
        }
        /* mobile-friendly transform for todo tables */
        @media (max-width: 768px) {
            .todo-table thead {
                display: none;
            }
            .todo-table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 12px;
                overflow: hidden;
            }
            .todo-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid rgba(255,255,255,0.05);
            }
            .todo-table tbody td:last-child {
                border-bottom: none;
            }
            .todo-table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-secondary);
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                flex-shrink: 0;
                margin-right: 1rem;
            }
            .todo-table tbody td:first-child {
                margin-bottom: 0.5rem;
                padding-bottom: 1rem;
                border-bottom: 2px solid var(--glass-border);
            }
            .todo-table tbody td:first-child::before {
                display: none;
            }
        }
        .todo-manage-link {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 8px;
            color: #ffffff !important;
        }
        .btn-action,
        .btn-action:hover,
        .btn-action:focus,
        .btn-action:active,
        .todo-manage-link,
        .todo-manage-link:hover,
        .todo-manage-link:focus,
        .todo-manage-link:active {
            color: #ffffff !important;
            text-decoration: none;
        }
        .utility-manage-actions {
            display: inline-flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .utility-water-btn {
            background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
            border-color: #0369a1 !important;
            color: #ffffff !important;
        }
        .utility-water-btn:hover {
            background: linear-gradient(135deg, #38bdf8, #0ea5e9) !important;
            border-color: #0284c7 !important;
            color: #ffffff !important;
        }
        .utility-electric-btn {
            background: linear-gradient(135deg, #fb923c, #ea580c) !important;
            border-color: #c2410c !important;
            color: #ffffff !important;
        }
        .utility-electric-btn:hover {
            background: linear-gradient(135deg, #fdba74, #fb923c) !important;
            border-color: #ea580c !important;
            color: #ffffff !important;
        }
        .todo-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-chip.reserved { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .status-chip.checkedin { background: rgba(16,185,129,0.15); color: #34d399; }
        .status-chip.unpaid { background: rgba(239,68,68,0.15); color: #f87171; }
        .status-chip.pending { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .status-chip.partial { background: rgba(139,92,246,0.15); color: #a78bfa; }
        .status-chip.paid { background: rgba(16,185,129,0.15); color: #34d399; }
        .status-chip.done-water { background: rgba(6,182,212,0.15); color: #22d3ee; }
        .status-chip.done-elec { background: rgba(234,179,8,0.15); color: #facc15; }
        .status-chip.not-done { background: rgba(239,68,68,0.15); color: #f87171; }
        .status-chip.repair-pending { background: rgba(249,115,22,0.15); color: #fb923c; }
        .status-chip.repair-progress { background: rgba(96,165,250,0.15); color: #60a5fa; }

        .todo-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            overflow: hidden;
        }
        .todo-card-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .todo-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
        }
        .todo-card-body {
            max-height: 600px;
            overflow-y: auto;
        }
        .todo-card-body::-webkit-scrollbar { width: 4px; }
        .todo-card-body::-webkit-scrollbar-track { background: transparent; }
        .todo-card-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-action.primary {
            background: rgba(59,130,246,0.15);
            color: #60a5fa;
        }
        .btn-action.primary:hover {
            background: rgba(59,130,246,0.3);
        }
        .btn-action.success {
            background: rgba(16,185,129,0.15);
            color: #34d399;
        }
        .btn-action.success:hover {
            background: rgba(16,185,129,0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255,255,255,0.4);
        }
        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        .empty-state p {
            font-size: 14px;
            margin: 0;
        }

        /* Light theme */
        body.live-light .todo-tab { border-color: rgba(0,0,0,0.1); background: rgba(0,0,0,0.03); color: rgba(0,0,0,0.5); }
        body.live-light .todo-tab:hover { background: rgba(0,0,0,0.06); color: rgba(0,0,0,0.8); }
        body.live-light .todo-tab.active { color: #fff !important; border-color: transparent; box-shadow: 0 8px 16px rgba(37, 99, 235, 0.2); }
        body.live-light .todo-table thead th { background: rgba(0,0,0,0.03); color: rgba(0,0,0,0.5); border-bottom-color: rgba(0,0,0,0.08); }
        body.live-light .todo-table tbody td { color: rgba(0,0,0,0.8); border-bottom-color: rgba(0,0,0,0.06); }
        body.live-light .todo-table tbody tr:hover { background: rgba(0,0,0,0.02); }
        body.live-light .todo-card { background: white; border-color: rgba(0,0,0,0.1); }
        body.live-light .todo-card-header { border-bottom-color: rgba(0,0,0,0.06); }
        body.live-light .todo-card-header h3 { color: rgba(0,0,0,0.85); }
        body.live-light .empty-state { color: rgba(0,0,0,0.35); }

        /* Force active tab colors (override Bootstrap/main.css collisions) */
        body.reports-page .todo-tabs .todo-tab.active,
        body.live-light.reports-page .todo-tabs .todo-tab.active {
            color: #ffffff !important;
            border-color: transparent !important;
        }
        body.reports-page .todo-tabs .todo-tab.active[data-tab="booking"],
        body.live-light.reports-page .todo-tabs .todo-tab.active[data-tab="booking"] {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            box-shadow: 0 8px 18px rgba(217, 119, 6, 0.35) !important;
        }
        body.reports-page .todo-tabs .todo-tab.active[data-tab="utility"],
        body.live-light.reports-page .todo-tabs .todo-tab.active[data-tab="utility"] {
            background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
            box-shadow: 0 8px 18px rgba(8, 145, 178, 0.35) !important;
        }
        body.reports-page .todo-tabs .todo-tab.active[data-tab="expense"],
        body.live-light.reports-page .todo-tabs .todo-tab.active[data-tab="expense"] {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
            box-shadow: 0 8px 18px rgba(124, 58, 237, 0.35) !important;
        }
        body.reports-page .todo-tabs .todo-tab.active[data-tab="payment"],
        body.live-light.reports-page .todo-tabs .todo-tab.active[data-tab="payment"] {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            box-shadow: 0 8px 18px rgba(220, 38, 38, 0.35) !important;
        }
        body.reports-page .todo-tabs .todo-tab.active[data-tab="repair"],
        body.live-light.reports-page .todo-tabs .todo-tab.active[data-tab="repair"] {
            background: linear-gradient(135deg, #14b8a6, #0f766e) !important;
            box-shadow: 0 8px 18px rgba(15, 118, 110, 0.35) !important;
        }
        body.reports-page .todo-tabs .todo-tab.active[data-tab="wizard"],
        body.live-light.reports-page .todo-tabs .todo-tab.active[data-tab="wizard"] {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.35) !important;
        }
        body.reports-page .todo-tabs .todo-tab.active svg,
        body.live-light.reports-page .todo-tabs .todo-tab.active svg {
            color: #ffffff !important;
            stroke: #ffffff !important;
        }

        /* Ultimate fallback active style */
        body.reports-page .todo-tabs .todo-tab.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            color: #ffffff !important;
            border-color: transparent !important;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.35) !important;
        }
        body.reports-page .todo-tabs .todo-tab.active svg {
            stroke: #ffffff !important;
            color: #ffffff !important;
            opacity: 1 !important;
        }
    </style>
</head>
<body class="reports-page">
    <script>
        (function() {
            var c = localStorage.getItem('theme_class');
            if (c && document.body) {
                document.body.classList.add(c);
            }
        })();
    </script>
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div>
                <?php $pageTitle = 'งานที่ต้องทำ'; include __DIR__ . '/../includes/page_header.php'; ?>

                <style>
                    /* Late override: loaded after sidebar include, so it wins */
                    .app-main .todo-tabs .todo-tab.active,
                    .app-main .todo-tabs .todo-tab.is-active {
                        background: #1d4ed8 !important;
                        border-color: #1d4ed8 !important;
                        color: #ffffff !important;
                        box-shadow: 0 10px 20px rgba(29, 78, 216, 0.35) !important;
                    }

                    .app-main .todo-tabs .todo-tab.active svg,
                    .app-main .todo-tabs .todo-tab.is-active svg,
                    .app-main .todo-tabs .todo-tab.active svg *,
                    .app-main .todo-tabs .todo-tab.is-active svg * {
                        stroke: #ffffff !important;
                        color: #ffffff !important;
                        opacity: 1 !important;
                    }

                    .app-main .utility-manage-actions .btn-action,
                    .app-main .utility-manage-actions .btn-action:hover,
                    .app-main .utility-manage-actions .btn-action:focus,
                    .app-main .utility-manage-actions .btn-action:active,
                    .app-main .utility-manage-actions .todo-manage-link,
                    .app-main .utility-manage-actions .todo-manage-link:hover,
                    .app-main .utility-manage-actions .todo-manage-link:focus,
                    .app-main .utility-manage-actions .todo-manage-link:active {
                        color: #ffffff !important;
                    }
                </style>

                <!-- Tab Navigation -->
                <div class="todo-tabs">
                    <button type="button" class="todo-tab" data-tab="wizard" onclick="switchTab('wizard')" aria-pressed="false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/><circle cx="12" cy="12" r="10" opacity="0.3"/><path d="M12 5l-2 2M14 5l2 2M12 19l-2-2M14 19l2-2"/></svg>
                        ตัวช่วยผู้เช่า
                        <?php if ($wizardPendingCount > 0): ?><span class="tab-badge wizard"><?php echo $wizardPendingCount; ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="todo-tab active" data-tab="booking" onclick="switchTab('booking')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        การจอง
                        <?php if ($bookingCount > 0): ?><span class="tab-badge booking"><?php echo $bookingCount; ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="todo-tab" data-tab="utility" onclick="switchTab('utility')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                        จดมิเตอร์
                        <?php if ($utilityPendingCount > 0): ?><span class="tab-badge utility"><?php echo $utilityPendingCount; ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="todo-tab" data-tab="expense" onclick="switchTab('expense')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        ค่าใช้จ่าย
                        <?php if ($expenseCount > 0): ?><span class="tab-badge expense"><?php echo $expenseCount; ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="todo-tab" data-tab="payment" onclick="switchTab('payment')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        รอชำระเงิน
                        <?php if ($paymentCount > 0): ?><span class="tab-badge payment"><?php echo $paymentCount; ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="todo-tab" data-tab="repair" onclick="switchTab('repair')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        แจ้งซ่อม
                        <?php if ($repairCount > 0): ?><span class="tab-badge repair"><?php echo $repairCount; ?></span><?php endif; ?>
                    </button>
                </div>

                <!-- ═══ Tab 0: ตัวช่วยผู้เช่า ═══ -->
                <div id="panel-wizard" class="todo-panel">
                    <div class="todo-card">
                        <div class="todo-card-header">
                            <h3>รายการค้างในตัวช่วยผู้เช่า</h3>
                            <a href="tenant_wizard.php" class="btn-action primary">เปิดหน้าตัวช่วย →</a>
                        </div>
                        <div class="todo-card-body">
                            <?php if (empty($wizardItems)): ?>
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <p>ไม่มีรายการค้างในตัวช่วยผู้เช่า</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>วันที่จอง</th>
                                        <th>ขั้นตอน</th>
                                        <th>จัดการ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($wizardItems as $w): ?>
                                        <?php
                                            $wizardStep = (int)($w['current_step'] ?? 0);
                                            $wizardStepText = 'ขั้นตอนที่ ' . max(1, $wizardStep + 1);
                                        ?>
                                        <tr>
                                            <td data-label="ห้อง"><strong><?php echo htmlspecialchars($w['room_number'] ?? '-'); ?></strong></td>
                                            <td data-label="ผู้เช่า"><?php echo htmlspecialchars($w['tnt_name'] ?? '-'); ?></td>
                                            <td data-label="วันที่จอง"><?php echo !empty($w['bkg_date']) ? date('d/m/Y', strtotime((string)$w['bkg_date'])) : '-'; ?></td>
                                            <td data-label="ขั้นตอน"><span class="status-chip pending"><?php echo htmlspecialchars($wizardStepText); ?></span></td>
                                            <td data-label="จัดการ"><a class="btn-action primary todo-manage-link" href="tenant_wizard.php?bkg_id=<?php echo (int)($w['bkg_id'] ?? 0); ?>">จัดการ</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Tab 1: การจอง ═══ -->
                <div id="panel-booking" class="todo-panel active">
                    <div class="todo-card">
                        <div class="todo-card-header">
                            <h3>รายการจองที่รอดำเนินการ</h3>
                            <a href="manage_booking.php?todo_only=1&status=1" class="btn-action primary">ดูทั้งหมด →</a>
                        </div>
                        <div class="todo-card-body">
                            <?php if (empty($bookings)): ?>
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <p>ไม่มีรายการจองที่รอดำเนินการ</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>วันที่จอง</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td data-label="ห้อง"><strong><?php echo htmlspecialchars($b['room_number'] ?? '-'); ?></strong><br><small style="color:rgba(255,255,255,0.4)"><?php echo htmlspecialchars($b['roomtype_name'] ?? ''); ?></small></td>
                                            <td data-label="ผู้เช่า"><?php echo htmlspecialchars($b['tnt_name'] ?? '-'); ?></td>
                                            <td data-label="วันที่จอง"><?php echo $b['bkg_date'] ? date('d/m/Y', strtotime($b['bkg_date'])) : '-'; ?></td>
                                            <td data-label="สถานะ">
                                                <?php if ($b['bkg_status'] == 1): ?>
                                                    <span class="status-chip reserved">จองแล้ว</span>
                                                <?php elseif ($b['bkg_status'] == 2): ?>
                                                    <span class="status-chip checkedin">เข้าพักแล้ว</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="จัดการ">
                                                <a class="btn-action primary todo-manage-link" href="manage_booking.php?todo_only=1&status=1&bkg_id=<?php echo (int)$b['bkg_id']; ?>">จัดการ</a>
                                                <button type="button" class="btn-action btn-danger todo-cancel-booking" data-bkgid="<?php echo (int)$b['bkg_id']; ?>" style="margin-left:0.5rem;padding:0.35rem 0.6rem;">ยกเลิก</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Tab 2: จดมิเตอร์ ═══ -->
                <div id="panel-utility" class="todo-panel">
                    <div class="todo-card">
                        <div class="todo-card-header">
                            <h3>สถานะจดมิเตอร์เดือนนี้ <small style="color:rgba(255,255,255,0.4);">(น้ำยังไม่จด: <?php echo $pendingWater; ?> | ไฟยังไม่จด: <?php echo $pendingElec; ?>)</small></h3>
                            <a href="manage_utility.php" class="btn-action primary">จดมิเตอร์ →</a>
                        </div>
                        <div class="todo-card-body">
                            <?php if (empty($utilities)): ?>
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <p>ไม่มีห้องที่ต้องจดมิเตอร์</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>น้ำ</th>
                                        <th>ไฟ</th>
                                        <th>จัดการ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($utilities as $u): ?>
                                        <?php
                                            $waterDone = !empty($u['utl_id']) && $u['utl_water_end'] !== null;
                                            $elecDone = !empty($u['utl_id']) && $u['utl_elec_end'] !== null;
                                            $utilityCtrId = (int)($u['ctr_id'] ?? 0);
                                        ?>
                                        <tr>
                                            <td data-label="ห้อง"><strong><?php echo htmlspecialchars($u['room_number'] ?? '-'); ?></strong></td>
                                            <td data-label="ผู้เช่า"><?php echo htmlspecialchars($u['tnt_name'] ?? '-'); ?></td>
                                            <td data-label="น้ำ">
                                                <?php if ($waterDone): ?>
                                                    <span class="status-chip done-water">✓ จดแล้ว (<?php echo htmlspecialchars($u['utl_water_end']); ?>)</span>
                                                <?php else: ?>
                                                    <span class="status-chip not-done">✗ ยังไม่จด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="ไฟ">
                                                <?php if ($elecDone): ?>
                                                    <span class="status-chip done-elec">✓ จดแล้ว (<?php echo htmlspecialchars($u['utl_elec_end']); ?>)</span>
                                                <?php else: ?>
                                                    <span class="status-chip not-done">✗ ยังไม่จด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="จัดการ">
                                                <div class="utility-manage-actions">
                                                    <?php if (!$waterDone): ?>
                                                    <a class="btn-action primary todo-manage-link utility-water-btn" href="manage_utility.php?todo_only=1&ctr_id=<?php echo $utilityCtrId; ?>&tab=water">จัดการน้ำ</a>
                                                    <?php endif; ?>
                                                    <?php if (!$elecDone): ?>
                                                    <a class="btn-action primary todo-manage-link utility-electric-btn" href="manage_utility.php?todo_only=1&ctr_id=<?php echo $utilityCtrId; ?>&tab=electric">จัดการไฟ</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Tab 3: ค่าใช้จ่าย ═══ -->
                <div id="panel-expense" class="todo-panel">
                    <div class="todo-card">
                        <div class="todo-card-header">
                            <h3>ค่าใช้จ่ายเดือนนี้</h3>
                            <a href="manage_expenses.php" class="btn-action primary">จัดการค่าใช้จ่าย →</a>
                        </div>
                        <div class="todo-card-body">
                            <?php if (empty($expenses)): ?>
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <p>ไม่มีค่าใช้จ่ายเดือนนี้</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>ยอดรวม</th>
                                        <th>ชำระแล้ว</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($expenses as $exp): ?>
                                        <?php
                                            $total = floatval($exp['exp_total']);
                                            $paid = floatval($exp['paid_amount']);
                                            $hasPending = intval($exp['pending_count']) > 0;
                                            $hasCompleteMeter = intval($exp['has_complete_meter'] ?? 0) === 1;
                                            $meterCtrId = (int)($exp['ctr_id'] ?? 0);
                                            $meterManageHref = ($meterCtrId > 0) ? ('manage_utility.php?todo_only=1&ctr_id=' . $meterCtrId) : 'manage_utility.php';
                                            if (!$hasCompleteMeter) { $statusClass = 'unpaid'; $statusText = 'ยังไม่ได้จดมิเตอร์'; }
                                            elseif ($hasPending) { $statusClass = 'pending'; $statusText = 'รอตรวจสอบ'; }
                                            elseif ($paid >= $total && $total > 0) { $statusClass = 'paid'; $statusText = 'ชำระแล้ว'; }
                                            elseif ($paid > 0) { $statusClass = 'partial'; $statusText = 'ชำระบางส่วน'; }
                                            else { $statusClass = 'unpaid'; $statusText = 'รอชำระ'; }
                                        ?>
                                        <tr>
                                            <td data-label="ห้อง"><strong><?php echo htmlspecialchars($exp['room_number'] ?? '-'); ?></strong></td>
                                            <td data-label="ผู้เช่า"><?php echo htmlspecialchars($exp['tnt_name'] ?? '-'); ?></td>
                                            <td data-label="ยอดรวม"><?php echo number_format($total, 2); ?> ฿</td>
                                            <td data-label="ชำระแล้ว"><?php echo number_format($paid, 2); ?> ฿</td>
                                            <td data-label="สถานะ">
                                                <?php if (!$hasCompleteMeter): ?>
                                                    <a href="<?php echo htmlspecialchars($meterManageHref); ?>" class="status-chip <?php echo $statusClass; ?>" style="text-decoration:none;display:inline-flex;align-items:center;">
                                                        <?php echo $statusText; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="status-chip <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="จัดการ"><a class="btn-action primary todo-manage-link" href="manage_expenses.php">จัดการ</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Tab 4: การชำระเงิน ═══ -->
                <div id="panel-payment" class="todo-panel">
                    <div class="todo-card">
                        <div class="todo-card-header">
                            <h3>การชำระเงินรอตรวจสอบ</h3>
                            <a href="manage_payments.php" class="btn-action primary">จัดการทั้งหมด →</a>
                        </div>
                        <div class="todo-card-body">
                            <?php if (empty($pendingPayments)): ?>
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <p>ไม่มีรายการรอตรวจสอบ</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>จำนวนเงิน</th>
                                        <th>วันที่ชำระ</th>
                                        <th>สลิป</th>
                                        <th>จัดการ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($pendingPayments as $p): ?>
                                        <tr>
                                            <td data-label="ห้อง"><strong><?php echo htmlspecialchars($p['room_number'] ?? '-'); ?></strong></td>
                                            <td data-label="ผู้เช่า"><?php echo htmlspecialchars($p['tnt_name'] ?? '-'); ?></td>
                                            <td data-label="จำนวนเงิน"><?php echo number_format(floatval(($p['payment_kind'] ?? '') === 'unpaid' ? ($p['exp_total'] ?? 0) : ($p['pay_amount'] ?? 0)), 2); ?> ฿</td>
                                            <td data-label="วันที่ชำระ"><?php echo $p['pay_date'] ? date('d/m/Y', strtotime($p['pay_date'])) : '-'; ?></td>
                                            <td data-label="สลิป">
                                                <?php if (($p['payment_kind'] ?? '') === 'unpaid'): ?>
                                                    <span class="status-chip unpaid">รอชำระ</span>
                                                <?php elseif (!empty($p['pay_proof'])): ?>
                                                    <span class="status-chip pending">รอตรวจสอบ</span>
                                                <?php else: ?>
                                                    <span style="color:rgba(255,255,255,0.3);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="จัดการ"><a class="btn-action primary todo-manage-link" href="manage_payments.php">จัดการ</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Tab 5: แจ้งซ่อม ═══ -->
                <div id="panel-repair" class="todo-panel">
                    <div class="todo-card">
                        <div class="todo-card-header">
                            <h3>รายการแจ้งซ่อมที่ต้องจัดการ</h3>
                            <a href="manage_repairs.php" class="btn-action primary">จัดการแจ้งซ่อม →</a>
                        </div>
                        <div class="todo-card-body">
                            <?php if (empty($pendingRepairs)): ?>
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <p>ไม่มีรายการแจ้งซ่อมที่ต้องจัดการ</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>รายละเอียด</th>
                                        <th>วันที่แจ้ง</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($pendingRepairs as $rp): ?>
                                        <?php
                                            $repairStatus = (string)($rp['repair_status'] ?? '0');
                                            $repairStatusClass = ($repairStatus === '1') ? 'repair-progress' : 'repair-pending';
                                            $repairStatusText = ($repairStatus === '1') ? 'กำลังซ่อม' : 'รอซ่อม';
                                            $repairDesc = trim((string)($rp['repair_desc'] ?? ''));
                                        ?>
                                        <tr>
                                            <td data-label="ห้อง"><strong><?php echo htmlspecialchars($rp['room_number'] ?? '-'); ?></strong></td>
                                            <td data-label="ผู้เช่า"><?php echo htmlspecialchars($rp['tnt_name'] ?? '-'); ?></td>
                                            <td data-label="รายละเอียด"><?php echo htmlspecialchars($repairDesc !== '' ? $repairDesc : '-'); ?></td>
                                            <td data-label="วันที่แจ้ง"><?php echo !empty($rp['repair_date']) ? date('d/m/Y H:i', strtotime((string)$rp['repair_date'])) : '-'; ?></td>
                                            <td data-label="สถานะ"><span class="status-chip <?php echo $repairStatusClass; ?>"><?php echo $repairStatusText; ?></span></td>
                                            <td data-label="จัดการ"><a class="btn-action primary todo-manage-link" href="manage_repairs.php">จัดการ</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function applyTabVisualState(button, isActive) {
            if (!button) return;

            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('is-active', isActive);

            if (isActive) {
                button.style.setProperty('background', '#1d4ed8', 'important');
                button.style.setProperty('color', '#ffffff', 'important');
                button.style.setProperty('border-color', '#1d4ed8', 'important');
                button.style.setProperty('box-shadow', '0 10px 20px rgba(29, 78, 216, 0.35)', 'important');
            } else {
                button.style.removeProperty('background');
                button.style.removeProperty('color');
                button.style.removeProperty('border-color');
                button.style.removeProperty('box-shadow');
            }

            const icon = button.querySelector('svg');
            if (icon) {
                if (isActive) {
                    icon.style.setProperty('stroke', '#ffffff', 'important');
                    icon.style.setProperty('color', '#ffffff', 'important');
                    icon.style.setProperty('opacity', '1', 'important');
                } else {
                    icon.style.removeProperty('stroke');
                    icon.style.removeProperty('color');
                    icon.style.removeProperty('opacity');
                }

                icon.querySelectorAll('path, line, rect, circle, polyline, polygon, ellipse').forEach(function(node) {
                    if (isActive) {
                        node.style.setProperty('stroke', '#ffffff', 'important');
                        node.style.setProperty('opacity', '1', 'important');
                    } else {
                        node.style.removeProperty('stroke');
                        node.style.removeProperty('opacity');
                    }
                });
            }
        }

        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.todo-tab');
            const panels = document.querySelectorAll('.todo-panel');

            // Deactivate all tabs and panels
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
                applyTabVisualState(tab, false);
            });
            panels.forEach(function(panel) {
                panel.classList.remove('active');
            });

            // Activate selected
            const selectedTab = document.querySelector('.todo-tab[data-tab="' + tabName + '"]');
            const selectedPanel = document.getElementById('panel-' + tabName);

            if (selectedTab) {
                selectedTab.classList.add('active');
                applyTabVisualState(selectedTab, true);
            }
            if (selectedPanel) {
                selectedPanel.classList.add('active');
            }

            // Update URL hash without scrolling
            history.replaceState(null, '', '#' + tabName);
        }

        // Restore tab from URL hash on load
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.replace('#', '');
            const initialTab = ['wizard', 'booking', 'utility', 'expense', 'payment', 'repair'].includes(hash) ? hash : 'booking';
            switchTab(initialTab);

            document.querySelectorAll('.todo-row-link').forEach(function(row) {
                row.addEventListener('click', function(e) {
                    const interactive = e.target.closest('a, button, input, select, textarea, label');
                    if (interactive) {
                        return;
                    }
                    const href = row.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });

            // Cancel booking from todo list
            document.querySelectorAll('.todo-cancel-booking').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    const bkgId = btn.getAttribute('data-bkgid');
                    if (!bkgId) return alert('ไม่พบรหัสการจอง');
                    if (!confirm('ยืนยันการยกเลิกการจองใช่หรือไม่? รายการจะถูกลบถาวร')) return;

                    btn.disabled = true;
                    btn.textContent = 'กำลังยกเลิก...';

                    const form = new FormData();
                    form.append('bkg_id', bkgId);

                    fetch('../Manage/cancel_booking.php', { method: 'POST', body: form })
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.success) {
                                // remove row or reload
                                const row = btn.closest('tr');
                                if (row) row.remove();
                                alert(data.message || 'ยกเลิกการจองเรียบร้อยแล้ว');
                            } else {
                                btn.disabled = false;
                                btn.textContent = 'ยกเลิก';
                                alert((data && data.error) ? data.error : 'ไม่สามารถยกเลิกได้');
                            }
                        }).catch(err => {
                            console.error(err);
                            btn.disabled = false;
                            btn.textContent = 'ยกเลิก';
                            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                        });
                });
            });
        });
    </script>
</body>
</html>
