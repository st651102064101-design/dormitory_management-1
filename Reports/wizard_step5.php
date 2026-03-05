<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/wizard_helper.php';

$conn = connectDB();

$ctr_id = isset($_GET['ctr_id']) ? (int)$_GET['ctr_id'] : 0;

if ($ctr_id <= 0) {
    $_SESSION['error'] = 'ข้อมูลไม่ครบถ้วน';
    header('Location: tenant_wizard.php');
    exit;
}

$contract = getContractDetails($conn, $ctr_id);
if (!$contract) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสัญญา';
    header('Location: tenant_wizard.php');
    exit;
}

// ดึงอัตราค่าน้ำ-ไฟล่าสุด
$rate = getLatestRate($conn);

// คำนวณเดือนถัดไป
$nextMonth = date('Y-m-01', strtotime('first day of next month'));

$settingsStmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a';
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme) $themeColor = htmlspecialchars($theme['setting_value'] ?? '#0f172a', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 5: เริ่มบิลรายเดือน</title>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <style>
        :root { --theme-bg-color: <?php echo $themeColor; ?>; }
        body { background: var(--bg-primary); color: var(--text-primary); }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 9999;
        }
        .wizard-container {
            max-width: 900px;
            width: 100%;
            margin: 0;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 12px;
            max-height: 90vh;
            overflow: auto;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            font-size: 1.25rem;
            line-height: 1;
            cursor: pointer;
        }
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.16);
        }
        .step-number {
            width: 48px;
            height: 48px;
            background: #22c55e;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .summary-item {
            padding: 1rem;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .summary-label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.6);
            margin-bottom: 0.5rem;
        }
        .summary-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #22c55e;
        }
        .btn {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-success {
            background: #22c55e;
            color: white;
        }
        .btn-success:hover {
            background: #16a34a;
            transform: scale(1.05);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: var(--text-primary);
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="modal-backdrop">
        <div class="wizard-container">
            <button type="button" class="modal-close" onclick="window.location.href='tenant_wizard.php'" aria-label="ปิด">×</button>

                <div style="text-align: center; margin-bottom: 2rem;">
                    <div class="step-number">5</div>
                    <h2>🎉 เริ่มบิลรายเดือน</h2>
                    <p style="color: rgba(255,255,255,0.7);">ขั้นตอนสุดท้าย - เปิดระบบเรียกเก็บค่าบริการรายเดือน</p>
                </div>

                <div style="padding: 1.5rem; background: rgba(34, 197, 94, 0.1); border: 2px solid rgba(34, 197, 94, 0.3); border-radius: 8px; margin-bottom: 1.5rem;">
                    <h3 style="margin-top: 0; color: #22c55e;">✓ สรุปข้อมูล</h3>
                    <p><strong>ผู้เช่า:</strong> <?php echo htmlspecialchars($contract['tnt_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>ห้อง:</strong> <?php echo htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($contract['type_name'], ENT_QUOTES, 'UTF-8'); ?>)</p>
                    <p><strong>ค่าห้อง:</strong> ฿<?php echo number_format($contract['type_price']); ?>/เดือน</p>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">รอบบิลแรก</div>
                        <div class="summary-value"><?php echo date('F Y', strtotime($nextMonth)); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">อัตราค่าน้ำ</div>
                        <div class="summary-value">เหมาจ่าย ฿200 (≤10 หน่วย)<br><span style="font-size:0.85em;">เกินหน่วยละ ฿25</span></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">อัตราค่าไฟ</div>
                        <div class="summary-value">฿<?php echo number_format($rate['rate_elec'] ?? 0, 2); ?>/หน่วย</div>
                    </div>
                </div>

                <div style="padding: 1.5rem; background: rgba(59, 130, 246, 0.1); border: 2px solid rgba(59, 130, 246, 0.3); border-radius: 8px; margin: 1.5rem 0;">
                    <h4 style="margin-top: 0; color: #3b82f6;">ℹ️ ระบบจะดำเนินการ:</h4>
                    <ul style="padding-left: 1.5rem; line-height: 1.8;">
                        <li>สร้างบิลรายเดือนแรก (เดือนถัดไป)</li>
                        <li>เปิดใช้งานระบบคำนวณค่าน้ำ-ไฟอัตโนมัติ</li>
                        <li>ตั้งรอบการออกบิลทุกต้นเดือน</li>
                        <li>เปิดใช้งานระบบแจ้งเตือนการชำระเงิน</li>
                        <li><strong>เสร็จสิ้นกระบวนการ Wizard ทั้งหมด!</strong></li>
                    </ul>
                </div>

                <form method="POST" action="../Manage/process_wizard_step5.php">
                    <input type="hidden" name="ctr_id" value="<?php echo $ctr_id; ?>">
                    <input type="hidden" name="tnt_id" value="<?php echo htmlspecialchars($contract['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="room_price" value="<?php echo $contract['type_price']; ?>">
                    <input type="hidden" name="rate_water" value="<?php echo $rate['rate_water'] ?? 0; ?>">
                    <input type="hidden" name="rate_elec" value="<?php echo $rate['rate_elec'] ?? 0; ?>">

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='tenant_wizard.php'">
                            ← ย้อนกลับ
                        </button>
                        <button type="submit" class="btn btn-success" style="font-size: 1.1rem; padding: 0.875rem 2.5rem;">
                            🎉 เริ่มบิลรายเดือนและเสร็จสิ้น
                        </button>
                    </div>
                </form>
        </div>
    </div>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
</body>
</html>
