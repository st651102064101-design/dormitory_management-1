<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

$conn = connectDB();

$tnt_id = $_GET['tnt_id'] ?? '';
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$bkg_id = isset($_GET['bkg_id']) ? (int)$_GET['bkg_id'] : 0;

if (empty($tnt_id) || $room_id <= 0) {
    $_SESSION['error'] = 'ข้อมูลไม่ครบถ้วน';
    header('Location: tenant_wizard.php');
    exit;
}

// ดึงข้อมูล
$stmt = $conn->prepare("
    SELECT t.*, r.room_number, rt.type_name, rt.type_price
    FROM tenant t
    LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_id = ?
    LEFT JOIN room r ON b.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    WHERE t.tnt_id = ?
");
$stmt->execute([$bkg_id, $tnt_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Step 3: สร้างสัญญา</title>
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
            background: #8b5cf6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            padding: 0.75rem;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            background: rgba(255,255,255,0.05);
            color: #e2e8f0;
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
        .btn-primary {
            background: #8b5cf6;
            color: white;
        }
        .btn-primary:hover {
            background: #7c3aed;
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
                    <div class="step-number">3</div>
                    <h2>สร้างสัญญาเช่า</h2>
                    <p style="color: rgba(255,255,255,0.7);">กำหนดรายละเอียดสัญญาและสร้าง PDF</p>
                </div>

                <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 1.5rem;">
                    <p><strong>ผู้เช่า:</strong> <?php echo htmlspecialchars($tenant['tnt_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>ห้อง:</strong> <?php echo htmlspecialchars($tenant['room_number'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($tenant['type_name'], ENT_QUOTES, 'UTF-8'); ?>)</p>
                </div>

                <form method="POST" action="../Manage/process_wizard_step3.php">
                    <input type="hidden" name="tnt_id" value="<?php echo htmlspecialchars($tnt_id, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                    <input type="hidden" name="bkg_id" value="<?php echo $bkg_id; ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>วันเริ่มสัญญา *</label>
                            <input type="date" name="ctr_start" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>ระยะเวลาสัญญา *</label>
                            <select name="contract_duration" id="contract_duration" required>
                                <option value="3">3 เดือน</option>
                                <option value="6" selected>6 เดือน</option>
                                <option value="12">12 เดือน</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>เงินประกัน (บาท) *</label>
                            <input type="number" name="ctr_deposit" value="2000" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div style="padding: 1rem; background: rgba(139, 92, 246, 0.1); border: 2px solid rgba(139, 92, 246, 0.3); border-radius: 8px; margin: 1.5rem 0;">
                        <h4 style="margin-top: 0;">📄 ระบบจะดำเนินการ:</h4>
                        <ul style="padding-left: 1.5rem; line-height: 1.8;">
                            <li>บันทึกข้อมูลสัญญาลงฐานข้อมูล</li>
                            <li>สร้างไฟล์ PDF สัญญา (ถ้ามีระบบ)</li>
                            <li>อัปเดตสถานะผู้เช่าเป็น "รอเข้าพัก"</li>
                        </ul>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='tenant_wizard.php'">
                            ← ย้อนกลับ
                        </button>
                        <button type="submit" class="btn btn-primary">
                            ✓ สร้างสัญญา
                        </button>
                    </div>
                </form>
        </div>
    </div>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
</body>
</html>
