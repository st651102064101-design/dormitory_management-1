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
    $stmt = $conn->query("
        SELECT
            t.tnt_id,
            t.tnt_name,
            t.tnt_phone,
            t.tnt_status,
            b.bkg_id,
            b.bkg_date,
            b.bkg_status,
            r.room_id,
            r.room_number,
            rt.type_name,
            rt.type_price,
            c.ctr_id,
            c.ctr_start,
            c.ctr_end,
            c.ctr_status,
            tw.id as workflow_id,
            tw.current_step,
            tw.step_1_confirmed,
            tw.step_1_date,
            tw.step_2_confirmed,
            tw.step_2_date,
            tw.step_3_confirmed,
            tw.step_3_date,
            tw.step_4_confirmed,
            tw.step_4_date,
            tw.step_5_confirmed,
            tw.step_5_date,
            tw.completed,
            bp.bp_status AS booking_payment_status,
            bp.bp_receipt_no,
            bp.bp_id,
            cr.checkin_id,
            cr.checkin_date
        FROM tenant t
        LEFT JOIN tenant_workflow tw ON t.tnt_id = tw.tnt_id
        LEFT JOIN booking b ON tw.bkg_id = b.bkg_id
        LEFT JOIN room r ON b.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
        LEFT JOIN booking_payment bp ON b.bkg_id = bp.bkg_id
        LEFT JOIN checkin_record cr ON c.ctr_id = cr.ctr_id
        WHERE tw.id IS NOT NULL AND tw.completed = FALSE
        ORDER BY tw.current_step ASC, tw.updated_at DESC
    ");
    $wizardTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $wizardTenants = [];
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้เช่า - Wizard</title>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <style>
        :root {
            --theme-bg-color: <?php echo $themeColor; ?>;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .wizard-panel {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
            background: rgba(10,25,41,0.8);
        }

        .wizard-table th,
        .wizard-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .wizard-table tbody tr:hover {
            background: rgba(30,41,59,0.4);
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
        }

        .step-circle.completed {
            background: #22c55e;
            color: white;
        }

        .step-circle.current {
            background: #3b82f6;
            color: white;
            animation: pulse 2s infinite;
        }

        .step-circle.pending {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.5);
        }

        .step-arrow {
            color: rgba(255,255,255,0.3);
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
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #22c55e;
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }

        .btn-disabled {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.3);
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
            color: rgba(255,255,255,0.7);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: rgba(255,255,255,0.6);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div style="display: flex;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; overflow-y: auto; height: 100vh; scrollbar-width: none; -ms-overflow-style: none; padding-bottom: 4rem;">
            <div class="wizard-panel">
                <?php $pageTitle = 'จัดการผู้เช่า - Wizard'; include '../includes/page_header.php'; ?>

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
                                $currentStep = (int)$tenant['current_step'];
                                $step1 = $tenant['step_1_confirmed'];
                                $step2 = $tenant['step_2_confirmed'];
                                $step3 = $tenant['step_3_confirmed'];
                                $step4 = $tenant['step_4_confirmed'];
                                $step5 = $tenant['step_5_confirmed'];
                                ?>
                                <tr>
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
                                            <div class="step-circle <?php echo $step1 ? 'completed' : ($currentStep == 1 ? 'current' : 'pending'); ?>">
                                                <?php echo $step1 ? '✓' : '1'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step2 ? 'completed' : ($currentStep == 2 ? 'current' : 'pending'); ?>">
                                                <?php echo $step2 ? '✓' : '2'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step3 ? 'completed' : ($currentStep == 3 ? 'current' : 'pending'); ?>">
                                                <?php echo $step3 ? '✓' : '3'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step4 ? 'completed' : ($currentStep == 4 ? 'current' : 'pending'); ?>">
                                                <?php echo $step4 ? '✓' : '4'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step5 ? 'completed' : ($currentStep == 5 ? 'current' : 'pending'); ?>">
                                                <?php echo $step5 ? '✓' : '5'; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($currentStep == 1): ?>
                                            <form method="GET" action="wizard_step1.php" style="display: inline;">
                                                <input type="hidden" name="bkg_id" value="<?php echo $tenant['bkg_id']; ?>">
                                                <input type="hidden" name="tnt_id" value="<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="action-btn btn-primary">ยืนยันจอง</button>
                                            </form>
                                        <?php elseif ($currentStep == 2): ?>
                                            <form method="GET" action="wizard_step2.php" style="display: inline;">
                                                <input type="hidden" name="bp_id" value="<?php echo $tenant['bp_id']; ?>">
                                                <input type="hidden" name="bkg_id" value="<?php echo $tenant['bkg_id']; ?>">
                                                <button type="submit" class="action-btn btn-primary">ยืนยันชำระเงินจอง</button>
                                            </form>
                                        <?php elseif ($currentStep == 3): ?>
                                            <form method="GET" action="wizard_step3.php" style="display: inline;">
                                                <input type="hidden" name="tnt_id" value="<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="room_id" value="<?php echo $tenant['room_id']; ?>">
                                                <input type="hidden" name="bkg_id" value="<?php echo $tenant['bkg_id']; ?>">
                                                <button type="submit" class="action-btn btn-primary">สร้างสัญญา</button>
                                            </form>
                                        <?php elseif ($currentStep == 4): ?>
                                            <form method="GET" action="wizard_step4.php" style="display: inline;">
                                                <input type="hidden" name="ctr_id" value="<?php echo $tenant['ctr_id']; ?>">
                                                <button type="submit" class="action-btn btn-primary">เช็คอิน</button>
                                            </form>
                                        <?php elseif ($currentStep == 5): ?>
                                            <form method="GET" action="wizard_step5.php" style="display: inline;">
                                                <input type="hidden" name="ctr_id" value="<?php echo $tenant['ctr_id']; ?>">
                                                <button type="submit" class="action-btn btn-success">เริ่มบิลรายเดือน</button>
                                            </form>
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
        </main>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
</body>
</html>
