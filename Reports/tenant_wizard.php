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
            cr.checkin_date
        FROM booking b
        INNER JOIN tenant t ON b.tnt_id = t.tnt_id
        LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
        LEFT JOIN room r ON b.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
        LEFT JOIN booking_payment bp ON b.bkg_id = bp.bkg_id
        LEFT JOIN checkin_record cr ON c.ctr_id = cr.ctr_id
        WHERE (tw.id IS NULL OR tw.completed = " . $completedFilter . ")
        ORDER BY COALESCE(tw.current_step, 1) ASC, b.bkg_date DESC";
    
    $stmt = $conn->query($sql);
    $wizardTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count completed workflows for button visibility
    $completedCountStmt = $conn->query("
        SELECT COUNT(*) as completed_count FROM tenant_workflow tw
        LEFT JOIN booking b ON tw.bkg_id = b.bkg_id
        WHERE tw.id IS NOT NULL AND tw.completed = 1
    ");
    $completedCountResult = $completedCountStmt->fetch(PDO::FETCH_ASSOC);
    $hasCompletedTenants = (int)($completedCountResult['completed_count'] ?? 0) > 0;
} catch (Exception $e) {
    $wizardTenants = [];
    $hasCompletedTenants = false;
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

        body,
        body main {
            background: #ffffff !important;
            color: #0f172a !important;
        }

        .wizard-panel {
            margin: 1.5rem;
            padding: 1.5rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15,23,42,0.08);
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
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

                <!-- Completion Status Filter Buttons -->
                <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <a href="tenant_wizard.php?completed=0" style="padding: 0.75rem 1.5rem; background: <?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? '#3b82f6' : 'rgba(59, 130, 246, 0.2)'; ?>; color: <?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? 'white !important' : '#111827 !important'; ?>; border: 2px solid #3b82f6; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#3b82f6'; this.style.setProperty('color','white','important');" onmouseout="this.style.background='<?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? '#3b82f6' : 'rgba(59, 130, 246, 0.2)'; ?>'; this.style.setProperty('color','<?php echo (!isset($_GET['completed']) || $_GET['completed'] == 0) ? 'white' : '#111827'; ?>','important');">⏳ ยังไม่ครบ 5 ขั้นตอน</a>
                    <?php if ($hasCompletedTenants): ?>
                    <a href="tenant_wizard.php?completed=1" style="padding: 0.75rem 1.5rem; background: <?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? '#22c55e' : 'rgba(34, 197, 94, 0.2)'; ?>; color: <?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? 'white' : '#22c55e'; ?>; border: 2px solid #22c55e; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#22c55e'; this.style.color='white';" onmouseout="this.style.background='<?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? '#22c55e' : 'rgba(34, 197, 94, 0.2)'; ?>'; this.style.color='<?php echo (isset($_GET['completed']) && $_GET['completed'] == 1) ? 'white' : '#22c55e'; ?>';">✅ ครบ 5 ขั้นตอนแล้ว</a>
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
                                            <div class="step-circle <?php echo $step1 ? 'completed' : ($currentStep == 1 ? 'current' : 'pending'); ?>" data-tooltip="1. ยืนยันจอง" <?php if ($step1): ?>onclick="openBookingModal(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['room_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>, '<?php echo date('d/m/Y', strtotime($tenant['bkg_date'])); ?>')" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step1 ? '✓' : '1'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step2 ? 'completed' : ($currentStep == 2 ? 'current' : 'pending'); ?>" data-tooltip="2. ยืนยันชำระเงินจอง" <?php if ($step2): ?>onclick="openPaymentModal(<?php echo $tenant['bp_id']; ?>, <?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['bp_amount'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['bp_proof'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step2 ? '✓' : '2'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step3 ? 'completed' : ($currentStep == 3 ? 'current' : 'pending'); ?>" data-tooltip="3. สร้างสัญญา" <?php if ($step3): ?>onclick="openContractModal('<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['room_id']; ?>, <?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['bkg_checkin_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['ctr_start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['ctr_end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['bp_amount'] ?? 0; ?>)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step3 ? '✓' : '3'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step4 ? 'completed' : ($currentStep == 4 ? 'current' : 'pending'); ?>" data-tooltip="4. เช็คอิน" <?php if ($step4): ?>onclick="openCheckinModal(<?php echo $tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_start'] ?? 'now')); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_end'] ?? 'now')); ?>')" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step4 ? '✓' : '4'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step5 ? 'completed' : ($currentStep == 5 ? 'current' : 'pending'); ?>" data-tooltip="5. เริ่มบิลรายเดือน" <?php if ($step5): ?>onclick="openBillingModal(<?php echo $tenant['ctr_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>)" style="cursor: pointer;"<?php endif; ?>>
                                                <?php echo $step5 ? '✓' : '5'; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($tenant['workflow_id'] === null): ?>
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
                                            <button type="button" class="action-btn btn-primary" onclick="openCheckinModal(<?php echo $tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_start'] ?? 'now')); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_end'] ?? 'now')); ?>')">เช็คอิน</button>
                                            <button type="button" class="action-btn btn-danger" onclick="cancelBooking(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>')">ยกเลิก</button>
                                        <?php elseif ($currentStep == 5): ?>
                                            <button type="button" class="action-btn btn-success" onclick="openBillingModal(<?php echo $tenant['ctr_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>)">เริ่มบิลรายเดือน</button>
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
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeBookingModal()">ยกเลิก</button>
                <button type="button" class="btn-modal btn-modal-primary" style="background: #3b82f6;" onclick="document.getElementById('bookingForm').submit()">✓ ยืนยันการจอง</button>
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
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeContractModal()">ยกเลิก</button>
                <button type="button" class="btn-modal btn-modal-primary" style="background: #8b5cf6;" onclick="document.getElementById('contractForm').submit()">✓ สร้างสัญญา</button>
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

                <form id="checkinForm" method="POST" action="../Manage/process_wizard_step4.php" enctype="multipart/form-data">
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

                    <!-- Section 3: ข้อมูลเพิ่มเติม -->
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <span style="background: #8b5cf6; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">3</span>
                            <span style="font-weight: 600; color: #f1f5f9;">🔑 ข้อมูลเพิ่มเติม</span>
                            <span style="font-size: 0.75rem; color: rgba(255,255,255,0.5);">(ไม่บังคับ)</span>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;">เลขกุญแจ</label>
                            <input type="text" name="key_number" placeholder="เช่น K-101, ชุดที่ 2" style="width: 100%; padding: 0.75rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9;">
                        </div>
                    </div>

                    <!-- Section 4: รูปสภาพห้อง -->
                    <div style="margin-bottom: 1.5rem; background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 1.25rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                            <span style="background: #a855f7; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">4</span>
                            <span style="font-weight: 600; color: #f1f5f9;">📸 รูปสภาพห้องก่อนเข้าพัก</span>
                        </div>
                        <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin: 0 0 1rem 0;">เก็บหลักฐานสภาพห้องก่อนผู้เช่าเข้าอยู่ เพื่อเปรียบเทียบตอนย้ายออก</p>
                        <div style="border: 2px dashed rgba(139, 92, 246, 0.3); border-radius: 10px; padding: 1.5rem; text-align: center; background: rgba(139, 92, 246, 0.05);">
                            <input type="file" name="room_images[]" accept="image/*" multiple id="roomImagesInput" style="display: none;">
                            <label for="roomImagesInput" style="cursor: pointer;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📷</div>
                                <div style="color: #a855f7; font-weight: 500; margin-bottom: 0.25rem;">คลิกเพื่อเลือกรูป</div>
                                <div style="color: rgba(255,255,255,0.5); font-size: 0.85rem;">เลือกได้หลายรูป (JPG, PNG)</div>
                            </label>
                            <div id="selectedFilesInfo" style="margin-top: 0.75rem; color: #4ade80; font-size: 0.85rem; display: none;"></div>
                        </div>
                    </div>

                    <!-- Section 5: หมายเหตุ -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;">📝 หมายเหตุ</label>
                        <textarea name="notes" placeholder="บันทึกข้อมูลเพิ่มเติม เช่น สภาพห้องมีรอยตำหนิ, อุปกรณ์ที่มอบให้..." rows="3" style="width: 100%; padding: 0.75rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; resize: vertical; font-family: inherit;"></textarea>
                    </div>

                    <!-- Summary Box -->
                    <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(234, 88, 12, 0.08)); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 1rem 1.25rem;">
                        <h4 style="margin: 0 0 0.75rem 0; color: #fbbf24; font-size: 1rem;">✅ ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.25rem; margin: 0; line-height: 1.8; color: #e2e8f0; font-size: 0.9rem;">
                            <li>บันทึกเลขมิเตอร์เริ่มต้น → <span style="color: #4ade80;">ใช้คำนวณค่าน้ำ-ไฟ</span></li>
                            <li>บันทึกรูปสภาพห้อง → <span style="color: #4ade80;">หลักฐานก่อนเข้าพัก</span></li>
                            <li>อัปเดตสถานะห้อง → <span style="color: #4ade80;">"มีผู้เช่า"</span></li>
                            <li>อัปเดตสถานะผู้เช่า → <span style="color: #4ade80;">"พักอยู่"</span></li>
                        </ul>
                    </div>
                </form>
            </div>

            <div class="modal-footer" style="display: flex; gap: 1rem; justify-content: flex-end; padding: 1.5rem 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeCheckinModal()" style="padding: 0.875rem 1.5rem;">ยกเลิก</button>
                <button type="button" class="btn-modal btn-modal-primary" onclick="validateAndSubmitCheckin()" style="padding: 0.875rem 2rem; background: linear-gradient(135deg, #f59e0b, #d97706); font-weight: 600;">🏠 บันทึกเช็คอิน</button>
            </div>
        </div>
    </div>

    <script>
    // Show selected files info
    document.getElementById('roomImagesInput')?.addEventListener('change', function(e) {
        const fileInfo = document.getElementById('selectedFilesInfo');
        if (this.files.length > 0) {
            fileInfo.style.display = 'block';
            fileInfo.textContent = '✓ เลือกแล้ว ' + this.files.length + ' รูป';
        } else {
            fileInfo.style.display = 'none';
        }
    });
    </script>

    <!-- Modal สำหรับเริ่มบิลรายเดือน (Step 5) -->
    <div id="billingModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: #22c55e; border-radius: 50%; font-size: 1.2rem;">5</span>
                    เริ่มบิลรายเดือน
                </h2>
                <button type="button" class="close-btn" onclick="closeBillingModal()" style="background: rgba(255,255,255,0.1); border: none; color: white; font-size: 1.5rem; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">&times;</button>
            </div>

            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 1.5rem; color: rgba(255,255,255,0.7);">
                    <p>🎉 ขั้นตอนสุดท้าย - เปิดระบบเรียกเก็บค่าบริการรายเดือน</p>
                </div>

                <div class="info-box-modal" id="billingTenantInfo"></div>

                <form id="billingForm" method="POST" action="../Manage/process_wizard_step5.php">
                    <input type="hidden" name="ctr_id" id="modal_billing_ctr_id">
                    <input type="hidden" name="tnt_id" id="modal_billing_tnt_id">
                    <input type="hidden" name="room_price" id="modal_billing_room_price">
                    <input type="hidden" name="rate_water" id="modal_billing_rate_water">
                    <input type="hidden" name="rate_elec" id="modal_billing_rate_elec">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1.5rem 0;">
                        <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 0.5rem;">รอบบิลแรก</div>
                            <div id="nextMonthDisplay" style="font-size: 1.1rem; font-weight: 600; color: #22c55e;"></div>
                        </div>
                        <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 0.5rem;">อัตราค่าน้ำ</div>
                            <div id="waterRateDisplay" style="font-size: 1.1rem; font-weight: 600; color: #3b82f6;">เหมาจ่าย ฿200</div>
                        </div>
                        <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 0.5rem;">อัตราค่าไฟ</div>
                            <div id="elecRateDisplay" style="font-size: 1.1rem; font-weight: 600; color: #f59e0b;">฿0.00/หน่วย</div>
                        </div>
                    </div>

                    <div class="alert-box-modal">
                        <h4>ℹ️ ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.5rem; line-height: 1.8; color: #e2e8f0;">
                            <li>สร้างบิลรายเดือนแรก (เดือนถัดไป)</li>
                            <li>เปิดใช้งานระบบคำนวณค่าน้ำ-ไฟอัตโนมัติ</li>
                            <li>ตั้งรอบการออกบิลทุกต้นเดือน</li>
                            <li>เปิดใช้งานระบบแจ้งเตือนการชำระเงิน</li>
                            <li><strong style="color: #22c55e;">เสร็จสิ้นกระบวนการ Wizard ทั้งหมด!</strong></li>
                        </ul>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeBillingModal()">ยกเลิก</button>
                <button type="button" class="btn-modal btn-modal-primary" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);" onclick="document.getElementById('billingForm').submit()">🎉 เริ่มบิลรายเดือนและเสร็จสิ้น</button>
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
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closePaymentModal()">ยกเลิก</button>
                <button type="button" class="btn-modal btn-modal-primary" style="background: #22c55e;" onclick="document.getElementById('paymentForm').submit()">✓ ยืนยันการชำระเงิน</button>
            </div>
        </div>
    </div>

<!-- เพิ่ม JavaScript นี้ก่อน </body> -->
<script>
    function openCheckinModal(ctrId, tntId, tntName, roomNumber, ctrStart, ctrEnd) {
        document.getElementById('modal_ctr_id').value = ctrId;
        document.getElementById('modal_tnt_id').value = tntId;
        
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

        // 4. Validate รูปภาพ (ต้องมีอย่างน้อย 1 รูป)
        const imageInput = document.getElementById('roomImagesInput');
        if (!imageInput.files || imageInput.files.length === 0) {
            errors.push('กรุณาเลือกรูปภาพห้องอย่างน้อย 1 รูป');
        } else {
            // Validate file types
            for (let file of imageInput.files) {
                if (!file.type.startsWith('image/')) {
                    errors.push(`ไฟล์ "${file.name}" ไม่ใช่รูปภาพ`);
                }
            }
        }

        // 5. Validate หมายเหตุ (ยังไม่บังคับแต่ให้ warning ถ้าว่าง)
        // ข้ามการตรวจสอบเพราะเป็นไม่บังคับ

        // 6. Validate เลขกุญแจ (ไม่บังคับ)
        // ข้ามการตรวจสอบเพราะเป็นไม่บังคับ

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
        document.getElementById('checkinForm').reset();
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
    function openBookingModal(bkgId, tntId, roomId, tntName, tntPhone, roomNumber, typeName, typePrice, bkgDate) {
        document.getElementById('modal_bkg_id').value = bkgId;
        document.getElementById('modal_booking_tnt_id').value = tntId;
        document.getElementById('modal_room_id').value = roomId;
        
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
    function openContractModal(tntId, roomId, bkgId, tntName, roomNumber, typeName, typePrice, bkgCheckinDate, ctrStart, ctrEnd, bookingAmount) {
        document.getElementById('modal_contract_tnt_id').value = tntId;
        document.getElementById('modal_contract_room_id').value = roomId;
        document.getElementById('modal_contract_bkg_id').value = bkgId;

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const defaultStart = `${yyyy}-${mm}-${dd}`;

        const startDate = bkgCheckinDate || ctrStart || defaultStart;
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
        document.getElementById('contractForm').reset();
    }

    // Functions สำหรับ Billing Modal
    function openBillingModal(ctrId, tntId, tntName, roomNumber, roomType, roomPrice) {
        // ตั้งค่า hidden fields
        document.getElementById('modal_billing_ctr_id').value = ctrId;
        document.getElementById('modal_billing_tnt_id').value = tntId;
        document.getElementById('modal_billing_room_price').value = roomPrice;
        
        // แสดงข้อมูลผู้เช่า
        document.getElementById('billingTenantInfo').innerHTML = `
            <p><strong style="color: #60a5fa;">ผู้เช่า:</strong> ${tntName}</p>
            <p><strong style="color: #60a5fa;">ห้อง:</strong> ${roomNumber} (${roomType})</p>
            <p><strong style="color: #60a5fa;">ค่าห้อง:</strong> ฿${Number(roomPrice).toLocaleString()}/เดือน</p>
        `;

        // คำนวณเดือนถัดไป
        const now = new Date();
        const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('nextMonthDisplay').textContent = 
            `${monthNames[nextMonth.getMonth()]} ${nextMonth.getFullYear()}`;

        // โหลดอัตราค่าน้ำ-ไฟจาก API หรือใช้ค่าคงที่
        fetch('../Manage/get_latest_rate.php')
            .then(response => response.json())
            .then(data => {
                const waterRate = data.rate_water || 0;
                const elecRate = data.rate_elec || 0;
                
                document.getElementById('modal_billing_rate_water').value = waterRate;
                document.getElementById('modal_billing_rate_elec').value = elecRate;
                document.getElementById('waterRateDisplay').textContent = 'เหมาจ่าย ฿200';
                document.getElementById('elecRateDisplay').textContent = `฿${Number(elecRate).toFixed(2)}/หน่วย`;
            })
            .catch(() => {
                // ใช้ค่า default ถ้าโหลดไม่ได้
                document.getElementById('modal_billing_rate_water').value = 18;
                document.getElementById('modal_billing_rate_elec').value = 8;
                document.getElementById('waterRateDisplay').textContent = 'เหมาจ่าย ฿200';
                document.getElementById('elecRateDisplay').textContent = '฿8.00/หน่วย';
            });
        
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
    function openPaymentModal(bpId, bkgId, tntId, tntName, roomNumber, bpAmount, bpProof) {
        document.getElementById('modal_payment_bp_id').value = bpId;
        document.getElementById('modal_payment_bkg_id').value = bkgId;
        document.getElementById('modal_payment_tnt_id').value = tntId;
        
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
