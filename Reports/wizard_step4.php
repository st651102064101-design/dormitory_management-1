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
    <title>Step 4: เช็คอิน</title>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <style>
        :root { --theme-bg-color: <?php echo $themeColor; ?>; }
        body { 
            background: var(--bg-primary, linear-gradient(135deg, #0f172a 0%, #1e293b 100%)); 
            color: var(--text-primary, #f8fafc); 
        }
        .wizard-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .step-number {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 1.5rem;
        }
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #f1f5f9;
            font-size: 0.95rem;
        }
        .form-group input, 
        .form-group textarea,
        .form-group select {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            background: rgba(15, 23, 42, 0.6);
            color: #f1f5f9;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-group input:focus, 
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #f59e0b;
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(241, 245, 249, 0.4);
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }
        .form-group small {
            color: rgba(241, 245, 249, 0.6);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        .btn {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .info-box {
            padding: 1rem;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .info-box p {
            margin: 0.5rem 0;
            color: #e2e8f0;
        }
        .info-box strong {
            color: #60a5fa;
        }
        .alert-box {
            padding: 1rem;
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        .alert-box h4 {
            margin-top: 0;
            color: #fbbf24;
        }
        .alert-box ul {
            color: #e2e8f0;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div style="display: flex;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; overflow-y: auto; height: 100vh;">
            <div class="wizard-container">
                <?php $pageTitle = 'Step 4: เช็คอิน'; include '../includes/page_header.php'; ?>

                <div style="text-align: center; margin-bottom: 2rem;">
                    <div class="step-number">4</div>
                    <h2 style="color: #f8fafc; margin: 0.5rem 0;">เช็คอิน - บันทึกมิเตอร์และสภาพห้อง</h2>
                    <p style="color: rgba(241, 245, 249, 0.7); margin: 0;">บันทึกข้อมูลเริ่มต้นก่อนผู้เช่าเข้าพัก</p>
                </div>

                <div class="info-box">
                    <p><strong>ผู้เช่า:</strong> <?php echo htmlspecialchars($contract['tnt_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>ห้อง:</strong> <?php echo htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>สัญญา:</strong> <?php echo date('d/m/Y', strtotime($contract['ctr_start'])); ?> - <?php echo date('d/m/Y', strtotime($contract['ctr_end'])); ?></p>
                </div>

                <form method="POST" action="../Manage/process_wizard_step4.php" enctype="multipart/form-data">
                    <input type="hidden" name="ctr_id" value="<?php echo $ctr_id; ?>">
                    <input type="hidden" name="tnt_id" value="<?php echo htmlspecialchars($contract['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label>วันที่เช็คอิน *</label>
                        <input type="date" name="checkin_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
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
                        <small>เลือกได้หลายรูป</small>
                    </div>

                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea name="notes" placeholder="บันทึกข้อมูลเพิ่มเติม..."></textarea>
                    </div>

                    <div class="alert-box">
                        <h4>🔑 ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.5rem;">
                            <li>บันทึกเลขมิเตอร์เริ่มต้น (สำหรับคิดค่าน้ำ-ไฟ)</li>
                            <li>บันทึกรูปสภาพห้องก่อนเข้าพัก</li>
                            <li>อัปเดตสถานะผู้เช่าเป็น "พักอยู่"</li>
                        </ul>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='tenant_wizard.php'">
                            ← ย้อนกลับ
                        </button>
                        <button type="submit" class="btn btn-warning">
                            ✓ บันทึกเช็คอิน
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
</body>
</html>
