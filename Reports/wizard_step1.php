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

// รับพารามิเตอร์
$bkg_id = isset($_GET['bkg_id']) ? (int)$_GET['bkg_id'] : 0;
$tnt_id = $_GET['tnt_id'] ?? '';

if ($bkg_id <= 0 || empty($tnt_id)) {
    $_SESSION['error'] = 'ข้อมูลไม่ครบถ้วน';
    header('Location: tenant_wizard.php');
    exit;
}

// ดึงข้อมูลการจอง
$booking = getBookingDetails($conn, $bkg_id);
if (!$booking) {
    $_SESSION['error'] = 'ไม่พบข้อมูลการจอง';
    header('Location: tenant_wizard.php');
    exit;
}

// ดึง theme color
$settingsStmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
$themeColor = '#0f172a';
if ($settingsStmt) {
    $theme = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    if ($theme && !empty($theme['setting_value'])) {
        $themeColor = htmlspecialchars($theme['setting_value'], ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 1: ยืนยันจอง</title>
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
        .wizard-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .step-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid rgba(59, 130, 246, 0.3);
        }
        .step-number {
            display: inline-block;
            width: 48px;
            height: 48px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .info-item {
            padding: 1rem;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .info-label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.6);
            margin-bottom: 0.5rem;
        }
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #3b82f6;
        }
        .action-box {
            margin-top: 2rem;
            padding: 1.5rem;
            background: rgba(34, 197, 94, 0.1);
            border: 2px solid rgba(34, 197, 94, 0.3);
            border-radius: 8px;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
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
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }
    </style>
</head>
<body>
    <div style="display: flex;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; overflow-y: auto; height: 100vh;">
            <div class="wizard-container">
                <?php $pageTitle = 'Step 1: ยืนยันจอง'; include '../includes/page_header.php'; ?>

                <div class="step-header">
                    <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <div class="step-number">1</div>
                    </div>
                    <h2 style="margin: 0;">ยืนยันการจอง</h2>
                    <p style="color: rgba(255,255,255,0.7); margin: 0.5rem 0 0 0;">
                        ตรวจสอบข้อมูลและยืนยันการจองห้องพัก
                    </p>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">ผู้เช่า</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['tnt_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">เบอร์โทร</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['tnt_phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ห้อง</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['room_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ประเภทห้อง</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['type_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ค่าห้อง/เดือน</div>
                        <div class="info-value">฿<?php echo number_format($booking['type_price']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">วันที่จอง</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($booking['bkg_date'])); ?></div>
                    </div>
                </div>

                <div class="action-box">
                    <h3 style="margin: 0 0 1rem 0; color: #22c55e;">✓ การดำเนินการ</h3>
                    <ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">
                        <li>ล็อกห้องพักไม่ให้คนอื่นจองซ้ำ</li>
                        <li>สร้างยอดเงินจอง 2,000 บาท</li>
                        <li>อัปเดตสถานะผู้เช่าเป็น "จองห้อง"</li>
                        <li>บันทึก Workflow เพื่อติดตามขั้นตอนถัดไป</li>
                    </ul>
                </div>

                <form method="POST" action="../Manage/process_wizard_step1.php">
                    <input type="hidden" name="bkg_id" value="<?php echo $bkg_id; ?>">
                    <input type="hidden" name="tnt_id" value="<?php echo htmlspecialchars($tnt_id, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="room_id" value="<?php echo $booking['room_id']; ?>">

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='tenant_wizard.php'">
                            ← ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-primary">
                            ✓ ยืนยันการจอง
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
</body>
</html>
