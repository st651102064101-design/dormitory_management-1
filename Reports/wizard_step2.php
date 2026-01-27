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

$bp_id = isset($_GET['bp_id']) ? (int)$_GET['bp_id'] : 0;
$bkg_id = isset($_GET['bkg_id']) ? (int)$_GET['bkg_id'] : 0;

if ($bp_id <= 0 || $bkg_id <= 0) {
    $_SESSION['error'] = 'ข้อมูลไม่ครบถ้วน';
    header('Location: tenant_wizard.php');
    exit;
}

// ดึงข้อมูล
$stmt = $conn->prepare("
    SELECT bp.*, b.tnt_id, t.tnt_name, r.room_number
    FROM booking_payment bp
    LEFT JOIN booking b ON bp.bkg_id = b.bkg_id
    LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
    LEFT JOIN room r ON b.room_id = r.room_id
    WHERE bp.bp_id = ?
");
$stmt->execute([$bp_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    $_SESSION['error'] = 'ไม่พบข้อมูลการชำระเงิน';
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
    <title>Step 2: ยืนยันชำระเงินจอง</title>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <style>
        :root { --theme-bg-color: <?php echo $themeColor; ?>; }
        body { background: var(--bg-primary); color: var(--text-primary); }
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
            border-bottom: 2px solid rgba(34, 197, 94, 0.3);
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
        .info-card {
            padding: 1.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin: 1rem 0;
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
            transform: translateY(-2px);
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
    <div style="display: flex;">
        <?php include '../includes/sidebar.php'; ?>
        <main style="flex: 1; overflow-y: auto; height: 100vh;">
            <div class="wizard-container">
                <?php $pageTitle = 'Step 2: ยืนยันชำระเงินจอง'; include '../includes/page_header.php'; ?>

                <div class="step-header">
                    <div class="step-number">2</div>
                    <h2 style="margin: 0;">ยืนยันการชำระเงินจอง</h2>
                    <p style="color: rgba(255,255,255,0.7); margin: 0.5rem 0 0 0;">
                        ตรวจสอบหลักฐานและยืนยันการชำระเงินจอง
                    </p>
                </div>

                <div class="info-card">
                    <h3>ข้อมูลการชำระเงิน</h3>
                    <p><strong>ผู้เช่า:</strong> <?php echo htmlspecialchars($payment['tnt_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>ห้อง:</strong> <?php echo htmlspecialchars($payment['room_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>จำนวนเงินจอง:</strong> ฿<?php echo number_format($payment['bp_amount'], 2); ?></p>
                    <?php if (!empty($payment['bp_proof'])): ?>
                        <p><strong>หลักฐานการชำระ:</strong>
                            <a href="/<?php echo htmlspecialchars($payment['bp_proof'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="color: #3b82f6;">ดูหลักฐาน</a>
                        </p>
                    <?php else: ?>
                        <p style="color: #f59e0b;"><strong>⚠️ หมายเหตุ:</strong> ยังไม่มีหลักฐานการชำระเงิน</p>
                    <?php endif; ?>
                </div>

                <div style="padding: 1.5rem; background: rgba(34, 197, 94, 0.1); border: 2px solid rgba(34, 197, 94, 0.3); border-radius: 8px;">
                    <h3 style="margin: 0 0 1rem 0; color: #22c55e;">✓ การดำเนินการ</h3>
                    <ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">
                        <li>บันทึกวันที่ชำระเงินจอง</li>
                        <li>สร้างเลขที่ใบเสร็จอัตโนมัติ</li>
                        <li>ทำเครื่องหมายการชำระเงินเสร็จสิ้น</li>
                        <li>พร้อมสำหรับขั้นตอนถัดไป: สร้างสัญญา</li>
                    </ul>
                </div>

                <form method="POST" action="../Manage/process_wizard_step2.php">
                    <input type="hidden" name="bp_id" value="<?php echo $bp_id; ?>">
                    <input type="hidden" name="bkg_id" value="<?php echo $bkg_id; ?>">
                    <input type="hidden" name="tnt_id" value="<?php echo htmlspecialchars($payment['tnt_id'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='tenant_wizard.php'">
                            ← ย้อนกลับ
                        </button>
                        <button type="submit" class="btn btn-success">
                            ✓ ยืนยันการชำระเงิน
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
