<?php
session_start();
require_once __DIR__ . '/../ConnectDB.php';

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

$admin_name = $_SESSION['admin_username'];

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
    $bookingStmt = $pdo->query("
        SELECT b.bkg_id, b.bkg_date, b.bkg_status,
               t.tnt_name, t.tnt_lastname, t.tnt_phone,
               r.room_number, rt.roomtype_name
        FROM booking b
        LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
        LEFT JOIN room r ON b.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.roomtype_id = rt.roomtype_id
        WHERE b.bkg_status IN (1, 2)
        ORDER BY b.bkg_date DESC
        LIMIT 50
    ");
    $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

    // === Tab 2: จดมิเตอร์ (ห้องที่ยังไม่จด) ===
    $utilityStmt = $pdo->query("
        SELECT r.room_id, r.room_number, rt.roomtype_name,
               t.tnt_name, t.tnt_lastname,
               c.ctr_id,
               u.utl_id, u.utl_water_start, u.utl_water_end, u.utl_elec_start, u.utl_elec_end
        FROM contract c
        INNER JOIN (
            SELECT room_id, MAX(ctr_id) AS ctr_id
            FROM contract WHERE ctr_status = '0' GROUP BY room_id
        ) lc ON lc.ctr_id = c.ctr_id
        LEFT JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.roomtype_id = rt.roomtype_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN utility u ON u.ctr_id = c.ctr_id
            AND MONTH(u.utl_date) = MONTH(CURDATE())
            AND YEAR(u.utl_date) = YEAR(CURDATE())
        ORDER BY r.room_number ASC
    ");
    $utilities = $utilityStmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingWater = 0;
    $pendingElec = 0;
    foreach ($utilities as $u) {
        if (empty($u['utl_id']) || $u['utl_water_end'] === null) $pendingWater++;
        if (empty($u['utl_id']) || $u['utl_elec_end'] === null) $pendingElec++;
    }

    // === Tab 3: ค่าใช้จ่าย ===
    $expenseStmt = $pdo->query("
        SELECT e.exp_id, e.exp_month, e.exp_total, e.exp_room_price,
               e.exp_water_cost, e.exp_electric_cost, e.exp_common_fee,
               c.ctr_id, r.room_number, t.tnt_name, t.tnt_lastname,
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
        ORDER BY r.room_number ASC
    ");
    $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

    // === Tab 4: การชำระเงิน (รอตรวจสอบ) ===
    $paymentStmt = $pdo->query("
        SELECT p.pay_id, p.pay_amount, p.pay_date, p.pay_status, p.pay_proof, p.pay_remark,
               e.exp_id, e.exp_month, e.exp_total,
               r.room_number, t.tnt_name, t.tnt_lastname
        FROM payment p
        LEFT JOIN expense e ON p.exp_id = e.exp_id
        LEFT JOIN contract c ON e.ctr_id = c.ctr_id
        LEFT JOIN room r ON c.room_id = r.room_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        WHERE p.pay_status = '0'
          AND p.pay_proof IS NOT NULL AND p.pay_proof <> ''
        ORDER BY p.pay_date DESC
        LIMIT 50
    ");
    $pendingPayments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $bookings = [];
    $utilities = [];
    $expenses = [];
    $pendingPayments = [];
    $pendingWater = 0;
    $pendingElec = 0;
    $themeColor = '#0f172a';
    $isLight = false;
}

// นับจำนวนตาม tab
$bookingCount = count($bookings);
$utilityPendingCount = $pendingWater + $pendingElec;
$expenseCount = count($expenses);
$paymentCount = count($pendingPayments);
$lightThemeClass = $isLight ? 'light-theme' : '';
?>
<!DOCTYPE html>
<html lang="th" class="<?php echo $lightThemeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>งานที่ต้องทำ - ระบบจัดการหอพัก</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        (function() {
            var c = localStorage.getItem('theme_class');
            if (c) {
                document.documentElement.classList.add(c);
                document.body.classList.add(c);
            }
        })();
    </script>
    <style>
        .todo-tabs {
            display: flex;
            gap: 8px;
            padding: 0 4px;
            margin-bottom: 24px;
            flex-wrap: wrap;
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
        }
        .todo-tab:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .todo-tab.active {
            background: rgba(59,130,246,0.2);
            border-color: #3b82f6;
            color: #60a5fa;
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
        body.live-light .todo-tab.active { background: rgba(59,130,246,0.1); border-color: #3b82f6; color: #2563eb; }
        body.live-light .todo-table thead th { background: rgba(0,0,0,0.03); color: rgba(0,0,0,0.5); border-bottom-color: rgba(0,0,0,0.08); }
        body.live-light .todo-table tbody td { color: rgba(0,0,0,0.8); border-bottom-color: rgba(0,0,0,0.06); }
        body.live-light .todo-table tbody tr:hover { background: rgba(0,0,0,0.02); }
        body.live-light .todo-card { background: white; border-color: rgba(0,0,0,0.1); }
        body.live-light .todo-card-header { border-bottom-color: rgba(0,0,0,0.06); }
        body.live-light .todo-card-header h3 { color: rgba(0,0,0,0.85); }
        body.live-light .empty-state { color: rgba(0,0,0,0.35); }
    </style>
</head>
<body class="reports-page">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div>
                <?php $pageTitle = 'งานที่ต้องทำ'; include __DIR__ . '/../includes/page_header.php'; ?>

                <!-- Tab Navigation -->
                <div class="todo-tabs">
                    <button class="todo-tab active" data-tab="booking" onclick="switchTab('booking')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        การจอง
                        <?php if ($bookingCount > 0): ?><span class="tab-badge booking"><?php echo $bookingCount; ?></span><?php endif; ?>
                    </button>
                    <button class="todo-tab" data-tab="utility" onclick="switchTab('utility')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                        จดมิเตอร์
                        <?php if ($utilityPendingCount > 0): ?><span class="tab-badge utility"><?php echo $utilityPendingCount; ?></span><?php endif; ?>
                    </button>
                    <button class="todo-tab" data-tab="expense" onclick="switchTab('expense')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        ค่าใช้จ่าย
                        <?php if ($expenseCount > 0): ?><span class="tab-badge expense"><?php echo $expenseCount; ?></span><?php endif; ?>
                    </button>
                    <button class="todo-tab" data-tab="payment" onclick="switchTab('payment')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        รอชำระเงิน
                        <?php if ($paymentCount > 0): ?><span class="tab-badge payment"><?php echo $paymentCount; ?></span><?php endif; ?>
                    </button>
                </div>

                <!-- ═══ Tab 1: การจอง ═══ -->
                <div id="panel-booking" class="todo-panel active">
                    <div class="todo-card">
                        <div class="todo-card-header">
                            <h3>รายการจองที่รอดำเนินการ</h3>
                            <a href="manage_booking.php" class="btn-action primary">ดูทั้งหมด →</a>
                        </div>
                        <div class="todo-card-body">
                            <?php if (empty($bookings)): ?>
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <p>ไม่มีรายการจองที่รอดำเนินการ</p>
                                </div>
                            <?php else: ?>
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>วันที่จอง</th>
                                        <th>สถานะ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($b['room_number'] ?? '-'); ?></strong><br><small style="color:rgba(255,255,255,0.4)"><?php echo htmlspecialchars($b['roomtype_name'] ?? ''); ?></small></td>
                                            <td><?php echo htmlspecialchars(($b['tnt_name'] ?? '') . ' ' . ($b['tnt_lastname'] ?? '')); ?></td>
                                            <td><?php echo $b['bkg_date'] ? date('d/m/Y', strtotime($b['bkg_date'])) : '-'; ?></td>
                                            <td>
                                                <?php if ($b['bkg_status'] == 1): ?>
                                                    <span class="status-chip reserved">จองแล้ว</span>
                                                <?php elseif ($b['bkg_status'] == 2): ?>
                                                    <span class="status-chip checkedin">เข้าพักแล้ว</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>น้ำ</th>
                                        <th>ไฟ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($utilities as $u): ?>
                                        <?php
                                            $waterDone = !empty($u['utl_id']) && $u['utl_water_end'] !== null;
                                            $elecDone = !empty($u['utl_id']) && $u['utl_elec_end'] !== null;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($u['room_number'] ?? '-'); ?></strong></td>
                                            <td><?php echo htmlspecialchars(($u['tnt_name'] ?? '') . ' ' . ($u['tnt_lastname'] ?? '')); ?></td>
                                            <td>
                                                <?php if ($waterDone): ?>
                                                    <span class="status-chip done-water">✓ จดแล้ว (<?php echo htmlspecialchars($u['utl_water_end']); ?>)</span>
                                                <?php else: ?>
                                                    <span class="status-chip not-done">✗ ยังไม่จด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($elecDone): ?>
                                                    <span class="status-chip done-elec">✓ จดแล้ว (<?php echo htmlspecialchars($u['utl_elec_end']); ?>)</span>
                                                <?php else: ?>
                                                    <span class="status-chip not-done">✗ ยังไม่จด</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>ยอดรวม</th>
                                        <th>ชำระแล้ว</th>
                                        <th>สถานะ</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($expenses as $exp): ?>
                                        <?php
                                            $total = floatval($exp['exp_total']);
                                            $paid = floatval($exp['paid_amount']);
                                            $hasPending = intval($exp['pending_count']) > 0;
                                            if ($hasPending) { $statusClass = 'pending'; $statusText = 'รอตรวจสอบ'; }
                                            elseif ($paid >= $total && $total > 0) { $statusClass = 'paid'; $statusText = 'ชำระแล้ว'; }
                                            elseif ($paid > 0) { $statusClass = 'partial'; $statusText = 'ชำระบางส่วน'; }
                                            else { $statusClass = 'unpaid'; $statusText = 'ยังไม่ชำระ'; }
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($exp['room_number'] ?? '-'); ?></strong></td>
                                            <td><?php echo htmlspecialchars(($exp['tnt_name'] ?? '') . ' ' . ($exp['tnt_lastname'] ?? '')); ?></td>
                                            <td><?php echo number_format($total, 2); ?> ฿</td>
                                            <td><?php echo number_format($paid, 2); ?> ฿</td>
                                            <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                                <table class="todo-table">
                                    <thead><tr>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>จำนวนเงิน</th>
                                        <th>วันที่ชำระ</th>
                                        <th>สลิป</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($pendingPayments as $p): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($p['room_number'] ?? '-'); ?></strong></td>
                                            <td><?php echo htmlspecialchars(($p['tnt_name'] ?? '') . ' ' . ($p['tnt_lastname'] ?? '')); ?></td>
                                            <td><?php echo number_format(floatval($p['pay_amount']), 2); ?> ฿</td>
                                            <td><?php echo $p['pay_date'] ? date('d/m/Y', strtotime($p['pay_date'])) : '-'; ?></td>
                                            <td>
                                                <?php if (!empty($p['pay_proof'])): ?>
                                                    <span class="status-chip pending">มีสลิป</span>
                                                <?php else: ?>
                                                    <span style="color:rgba(255,255,255,0.3);">-</span>
                                                <?php endif; ?>
                                            </td>
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
        function switchTab(tabName) {
            // Deactivate all tabs and panels
            document.querySelectorAll('.todo-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.todo-panel').forEach(p => p.classList.remove('active'));

            // Activate selected
            document.querySelector(`.todo-tab[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById('panel-' + tabName).classList.add('active');

            // Update URL hash without scrolling
            history.replaceState(null, '', '#' + tabName);
        }

        // Restore tab from URL hash on load
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.replace('#', '');
            if (['booking', 'utility', 'expense', 'payment'].includes(hash)) {
                switchTab(hash);
            }
        });
    </script>
</body>
</html>
