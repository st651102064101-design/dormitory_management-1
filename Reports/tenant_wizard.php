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
            COALESCE(c.ctr_id, tw.ctr_id) as ctr_id,
            c.ctr_start,
            c.ctr_end,
            c.ctr_status,
            tw.id as workflow_id,
            tw.ctr_id as workflow_ctr_id,
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
            background: rgba(15, 23, 42, 0.95);
            color: white;
            font-size: 0.75rem;
            font-weight: 400;
            border-radius: 6px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            border-color: rgba(15, 23, 42, 0.95) transparent transparent transparent;
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

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
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
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s;
            position: relative;
        }

        .modal-header {
            padding: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            background: rgba(15, 23, 42, 0.98);
            backdrop-filter: blur(10px);
            z-index: 10;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            color: #fff;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: rgba(255, 77, 79, 0.2);
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
            color: #f1f5f9;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            background: rgba(15, 23, 42, 0.6);
            color: #f1f5f9;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f59e0b;
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .info-box-modal {
            padding: 1rem;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .info-box-modal p {
            margin: 0.5rem 0;
            color: #e2e8f0;
        }

        .alert-box-modal {
            padding: 1rem;
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            margin: 1.5rem 0;
        }

        .alert-box-modal h4 {
            margin-top: 0;
            color: #fbbf24;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            background: rgba(15, 23, 42, 0.98);
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
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-modal-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
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
                                            <div class="step-circle <?php echo $step1 ? 'completed' : ($currentStep == 1 ? 'current' : 'pending'); ?>" data-tooltip="1. ยืนยันจอง">
                                                <?php echo $step1 ? '✓' : '1'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step2 ? 'completed' : ($currentStep == 2 ? 'current' : 'pending'); ?>" data-tooltip="2. ยืนยันชำระเงินจอง">
                                                <?php echo $step2 ? '✓' : '2'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step3 ? 'completed' : ($currentStep == 3 ? 'current' : 'pending'); ?>" data-tooltip="3. สร้างสัญญา">
                                                <?php echo $step3 ? '✓' : '3'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step4 ? 'completed' : ($currentStep == 4 ? 'current' : 'pending'); ?>" data-tooltip="4. เช็คอิน">
                                                <?php echo $step4 ? '✓' : '4'; ?>
                                            </div>
                                            <span class="step-arrow">→</span>
                                            <div class="step-circle <?php echo $step5 ? 'completed' : ($currentStep == 5 ? 'current' : 'pending'); ?>" data-tooltip="5. เริ่มบิลรายเดือน">
                                                <?php echo $step5 ? '✓' : '5'; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($currentStep == 1): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openBookingModal(<?php echo $tenant['bkg_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['room_id']; ?>, '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $tenant['type_price']; ?>, '<?php echo date('d/m/Y', strtotime($tenant['bkg_date'])); ?>')">ยืนยันจอง</button>
                                        <?php elseif ($currentStep == 2): ?>
                                            <a href="wizard_step2.php?bp_id=<?php echo $tenant['bp_id']; ?>&bkg_id=<?php echo $tenant['bkg_id']; ?>" class="action-btn btn-primary">ยืนยันชำระเงินจอง</a>
                                        <?php elseif ($currentStep == 3): ?>
                                            <a href="wizard_step3.php?tnt_id=<?php echo urlencode($tenant['tnt_id']); ?>&room_id=<?php echo $tenant['room_id']; ?>&bkg_id=<?php echo $tenant['bkg_id']; ?>" class="action-btn btn-primary">สร้างสัญญา</a>
                                        <?php elseif ($currentStep == 4): ?>
                                            <button type="button" class="action-btn btn-primary" onclick="openCheckinModal(<?php echo $tenant['ctr_id'] ?? $tenant['workflow_ctr_id'] ?? 0; ?>, '<?php echo htmlspecialchars($tenant['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_start'] ?? 'now')); ?>', '<?php echo date('d/m/Y', strtotime($tenant['ctr_end'] ?? 'now')); ?>')">เช็คอิน</button>
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

    <!-- Modal สำหรับเช็คอิน -->
    <div id="checkinModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <button class="modal-close" onclick="closeCheckinModal()">&times;</button>
                <div style="text-align: center;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">4</div>
                    <h2 style="color: #f8fafc; margin: 0.5rem 0;">เช็คอิน - บันทึกมิเตอร์และสภาพห้อง</h2>
                    <p style="color: rgba(241, 245, 249, 0.7); margin: 0;">บันทึกข้อมูลเริ่มต้นก่อนผู้เช่าเข้าพัก</p>
                </div>
            </div>
            
            <div class="modal-body">
                <div class="info-box-modal" id="tenantInfo"></div>

                <form id="checkinForm" method="POST" action="../Manage/process_wizard_step4.php" enctype="multipart/form-data">
                    <input type="hidden" name="ctr_id" id="modal_ctr_id">
                    <input type="hidden" name="tnt_id" id="modal_tnt_id">

                    <div class="form-group">
                        <label>วันที่เช็คอิน *</label>
                        <input type="date" name="checkin_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>มิเตอร์น้ำเริ่มต้น *</label>
                            <input type="number" name="water_meter_start" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>มิเตอร์ไฟเริ่มต้น *</label>
                            <input type="number" name="elec_meter_start" step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>เลขกุญแจ</label>
                        <input type="text" name="key_number" placeholder="เช่น K-101">
                    </div>

                    <div class="form-group">
                        <label>รูปสภาพห้อง (หลายรูป)</label>
                        <input type="file" name="room_images[]" accept="image/*" multiple style="color: #f1f5f9;">
                        <small style="color: rgba(241, 245, 249, 0.6); font-size: 0.85rem; display: block; margin-top: 0.25rem;">เลือกได้หลายรูป</small>
                    </div>

                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea name="notes" placeholder="บันทึกข้อมูลเพิ่มเติม..." rows="4" style="resize: vertical; font-family: inherit;"></textarea>
                    </div>

                    <div class="alert-box-modal">
                        <h4>🔑 ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.5rem; line-height: 1.8; color: #e2e8f0;">
                            <li>บันทึกเลขมิเตอร์เริ่มต้น (สำหรับคิดค่าน้ำ-ไฟ)</li>
                            <li>บันทึกรูปสภาพห้องก่อนเข้าพัก</li>
                            <li>อัปเดตสถานะผู้เช่าเป็น "พักอยู่"</li>
                        </ul>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeCheckinModal()">ยกเลิก</button>
                <button type="button" class="btn-modal btn-modal-primary" onclick="document.getElementById('checkinForm').submit()">✓ บันทึกเช็คอิน</button>
            </div>
        </div>
    </div>

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
                            <div id="waterRateDisplay" style="font-size: 1.1rem; font-weight: 600; color: #3b82f6;">฿0.00/หน่วย</div>
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

<!-- เพิ่ม JavaScript นี้ก่อน </body> -->
<script>
    function openCheckinModal(ctrId, tntId, tntName, roomNumber, ctrStart, ctrEnd) {
        document.getElementById('modal_ctr_id').value = ctrId;
        document.getElementById('modal_tnt_id').value = tntId;
        
        document.getElementById('tenantInfo').innerHTML = `
            <p><strong style="color: #60a5fa;">ผู้เช่า:</strong> ${tntName}</p>
            <p><strong style="color: #60a5fa;">ห้อง:</strong> ${roomNumber}</p>
            <p><strong style="color: #60a5fa;">สัญญา:</strong> ${ctrStart} - ${ctrEnd}</p>
        `;
        
        document.getElementById('checkinModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function closeCheckinModal() {
        document.getElementById('checkinModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        document.getElementById('checkinForm').reset();
    }

    // ปิด modal เมื่อคลิกนอก modal
    document.getElementById('checkinModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCheckinModal();
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
                document.getElementById('waterRateDisplay').textContent = `฿${Number(waterRate).toFixed(2)}/หน่วย`;
                document.getElementById('elecRateDisplay').textContent = `฿${Number(elecRate).toFixed(2)}/หน่วย`;
            })
            .catch(() => {
                // ใช้ค่า default ถ้าโหลดไม่ได้
                document.getElementById('modal_billing_rate_water').value = 18;
                document.getElementById('modal_billing_rate_elec').value = 8;
                document.getElementById('waterRateDisplay').textContent = '฿18.00/หน่วย';
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
</script>
