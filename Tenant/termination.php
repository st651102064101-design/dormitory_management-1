<?php
/**
 * Tenant Termination - แจ้งยกเลิกสัญญา
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);

$success = '';
$error = '';

// Check if already requested termination
$hasTermination = false;
$termination = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM termination WHERE ctr_id = ? ORDER BY term_date DESC LIMIT 1");
    $stmt->execute([$contract['ctr_id']]);
    $termination = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($termination) {
        $hasTermination = true;
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_termination' && $hasTermination) {
    try {
        $termId = (int)($termination['term_id'] ?? 0);
        if ($termId > 0) {
            $pdo->prepare("DELETE FROM termination WHERE term_id = ? AND ctr_id = ?")
                ->execute([$termId, $contract['ctr_id']]);
        } else {
            $pdo->prepare("DELETE FROM termination WHERE ctr_id = ?")
                ->execute([$contract['ctr_id']]);
        }
        $pdo->prepare("UPDATE contract SET ctr_status = '0' WHERE ctr_id = ?")
            ->execute([$contract['ctr_id']]);
        $success = 'ยกเลิกคำร้องยกเลิกสัญญาเรียบร้อยแล้ว';
        $hasTermination = false;
        $termination = null;
        // Refresh contract status
        $ctrStmt = $pdo->prepare("SELECT ctr_status FROM contract WHERE ctr_id = ?");
        $ctrStmt->execute([$contract['ctr_id']]);
        $contract = array_merge($contract, $ctrStmt->fetch(PDO::FETCH_ASSOC) ?: []);
    } catch (Exception $e) {
        $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasTermination) {
    try {
        $term_date = $_POST['term_date'] ?? '';
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_account_name = trim($_POST['bank_account_name'] ?? '');
        $bank_account_number = preg_replace('/[^0-9]/', '', $_POST['bank_account_number'] ?? '');

        $minAllowedDate = date('Y-m-d', strtotime('+1 month'));
        if (empty($term_date)) {
            $error = 'กรุณาระบุวันที่ต้องการยกเลิกสัญญา';
        } elseif ($term_date < $minAllowedDate) {
            $error = 'วันที่ย้ายออกต้องแจ้งล่วงหน้าอย่างน้อย 1 เดือน (ไม่ก่อน ' . thaiDate($minAllowedDate, 'long') . ')';
        } elseif (!empty($contract['ctr_end']) && $term_date > $contract['ctr_end']) {
            $error = 'วันที่ย้ายออกต้องไม่เกินวันที่สิ้นสุดสัญญา (' . thaiDate($contract['ctr_end'], 'long') . ')';
        } elseif ($lastPaidBillDate && $term_date <= $lastPaidBillDate) {
            $error = 'วันที่ย้ายออกต้องหลังวันที่ชำระบิลล่าสุด (' . thaiDate($lastPaidBillDate, 'long') . ')';
        } elseif (empty($bank_name) || empty($bank_account_name) || empty($bank_account_number)) {
            $error = 'กรุณาระบุข้อมูลบัญชีธนาคารสำหรับรับคืนเงินมัดจำให้ครบถ้วน';
        } else {
            // ตรวจสอบบิลค้างชำระก่อนอนุญาตให้แจ้งยกเลิก
            $unpaidCheckStmt = $pdo->prepare("
                SELECT COUNT(*) FROM expense e
                WHERE e.ctr_id = ?
                  AND e.exp_total > COALESCE((
                      SELECT SUM(p.pay_amount) FROM payment p
                      WHERE p.exp_id = e.exp_id
                        AND p.pay_status = '1'
                        
                  ), 0)
            ");
            $unpaidCheckStmt->execute([$contract['ctr_id']]);
            $unpaidCount = (int)$unpaidCheckStmt->fetchColumn();
            if ($unpaidCount > 0) {
                $error = 'ไม่สามารถแจ้งยกเลิกสัญญาได้ เนื่องจากยังมีบิลค้างชำระ ' . $unpaidCount . ' รายการ กรุณาชำระค่าเช่าให้ครบก่อน';
            } else {
            // Insert termination request
            $stmt = $pdo->prepare("INSERT INTO termination (ctr_id, term_date, bank_name, bank_account_name, bank_account_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$contract['ctr_id'], $term_date, $bank_name, $bank_account_name, $bank_account_number]);
            
            // Update contract status to "แจ้งยกเลิก" (2)
            $updateStmt = $pdo->prepare("UPDATE contract SET ctr_status = '2' WHERE ctr_id = ?");
            $updateStmt->execute([$contract['ctr_id']]);
            
            $success = 'ส่งคำร้องแจ้งยกเลิกสัญญาเรียบร้อยแล้ว';
            $hasTermination = true;
            
            // Refresh termination data
            $stmt = $pdo->prepare("SELECT * FROM termination WHERE ctr_id = ? ORDER BY term_date DESC LIMIT 1");
            $stmt->execute([$contract['ctr_id']]);
            $termination = $stmt->fetch(PDO::FETCH_ASSOC);
            } // end unpaid check else
        }
        
    } catch (Exception $e) {
        $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

$contractStatusMap = [
    '0' => ['label' => 'ปกติ', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)'],
    '1' => ['label' => 'ยกเลิกแล้ว', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.2)'],
    '2' => ['label' => 'แจ้งยกเลิก', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.2)']
];

// Auto-migrate: เพิ่มคอลัมน์ข้อมูลธนาคารในตาราง termination (ถ้ายังไม่มี)
try {
    $pdo->exec("ALTER TABLE termination ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) DEFAULT NULL AFTER term_date");
    $pdo->exec("ALTER TABLE termination ADD COLUMN IF NOT EXISTS bank_account_name VARCHAR(100) DEFAULT NULL AFTER bank_name");
    $pdo->exec("ALTER TABLE termination ADD COLUMN IF NOT EXISTS bank_account_number VARCHAR(20) DEFAULT NULL AFTER bank_account_name");
} catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// Calculate minimum date (1 month from now)
$minDate = date('Y-m-d', strtotime('+1 month'));
$maxDate = !empty($contract['ctr_end']) ? $contract['ctr_end'] : '';

// หาวันที่ชำระเงินล่าสุดของบิลในสัญญานี้ — term_date ต้อง > วันนั้น
$lastPaidBillDate = '';
try {
    $lpStmt = $pdo->prepare("
        SELECT MAX(p.pay_date)
        FROM payment p
        INNER JOIN expense e ON p.exp_id = e.exp_id
        WHERE e.ctr_id = ?
          AND p.pay_status = '1'
          
    ");
    $lpStmt->execute([$contract['ctr_id']]);
    $lpRow = $lpStmt->fetchColumn();
    if ($lpRow) { $lastPaidBillDate = $lpRow; }
} catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// minTermDate = max(minDate, lastPaidBillDate + 1 day)
$minTermDate = $minDate;
if ($lastPaidBillDate) {
    $minFromPaid = date('Y-m-d', strtotime($lastPaidBillDate . ' +1 day'));
    if ($minFromPaid > $minTermDate) { $minTermDate = $minFromPaid; }
}

// ดูว่า term_date ที่บันทึกไว้เกิน ctr_end หรือไม่
$termDateExceedsCtrEnd = $hasTermination
    && !empty($termination['term_date'])
    && !empty($contract['ctr_end'])
    && $termination['term_date'] > $contract['ctr_end'];

// ดึงข้อมูลคืนเงินมัดจำ
$depositRefund = null;
try {
    $rfStmt = $pdo->prepare("SELECT * FROM deposit_refund WHERE ctr_id = ? ORDER BY refund_id DESC LIMIT 1");
    $rfStmt->execute([$contract['ctr_id']]);
    $depositRefund = $rfStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// --- update_bank: ผู้เช่าอัปเดตบัญชีรับคืนเงินมัดจำ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_bank') {
    // อนุญาตเฉพาะเมื่อยังไม่โอนเงิน
    $refundTransferred = isset($depositRefund['refund_status']) && $depositRefund['refund_status'] === '1';
    if ($refundTransferred) {
        $error = 'ไม่สามารถแก้ไขบัญชีได้ เนื่องจากโอนเงินคืนเรียบร้อยแล้ว';
    } else {
        $bank_name        = trim($_POST['bank_name'] ?? '');
        $bank_account_name   = trim($_POST['bank_account_name'] ?? '');
        $bank_account_number = preg_replace('/[^0-9]/', '', $_POST['bank_account_number'] ?? '');
        if (empty($bank_name) || empty($bank_account_name) || empty($bank_account_number)) {
            $error = 'กรุณาระบุข้อมูลบัญชีให้ครบถ้วน';
        } else {
            try {
                if ($hasTermination) {
                    $pdo->prepare("UPDATE termination SET bank_name=?, bank_account_name=?, bank_account_number=? WHERE ctr_id=?")
                        ->execute([$bank_name, $bank_account_name, $bank_account_number, $contract['ctr_id']]);
                } else {
                    // กรณีสัญญาถูกยกเลิกโดย admin โดยไม่มี termination record — สร้าง record ใหม่
                    $pdo->prepare("INSERT INTO termination (ctr_id, term_date, bank_name, bank_account_name, bank_account_number) VALUES (?, CURDATE(), ?, ?, ?)")
                        ->execute([$contract['ctr_id'], $bank_name, $bank_account_name, $bank_account_number]);
                }
                // Refresh termination data
                $stmt2 = $pdo->prepare("SELECT * FROM termination WHERE ctr_id = ? ORDER BY term_id DESC LIMIT 1");
                $stmt2->execute([$contract['ctr_id']]);
                $termination = $stmt2->fetch(PDO::FETCH_ASSOC);
                $hasTermination = (bool)$termination;
                $success = 'บันทึกข้อมูลบัญชีธนาคารเรียบร้อยแล้ว';
            } catch (Exception $e) {
                $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
}

// ดึงจำนวนบิลค้างชำระ
$unpaidBillCount = 0;
try {
    $ubStmt = $pdo->prepare("
        SELECT COUNT(*) FROM expense e
        INNER JOIN (
            SELECT MAX(exp_id) AS exp_id FROM expense WHERE ctr_id = ? GROUP BY exp_month
        ) latest ON e.exp_id = latest.exp_id
        WHERE e.ctr_id = ?
          AND e.exp_total > COALESCE((
              SELECT SUM(p.pay_amount) FROM payment p
              WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                
          ), 0)
    ");
    $ubStmt->execute([$contract['ctr_id'], $contract['ctr_id']]);
    $unpaidBillCount = (int)$ubStmt->fetchColumn();
} catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

function _bankFormFields(?array $term): string {
    $banks = ['ธนาคารกสิกรไทย (KBank)','ธนาคารไทยพาณิชย์ (SCB)','ธนาคารกรุงเทพ (BBL)','ธนาคารกรุงไทย (KTB)','ธนาคารกรุงศรีอยุธยา (BAY)','ธนาคารทหารไทยธนชาต (TTB)','ธนาคารออมสิน (GSB)','ธนาคาร ธ.ก.ส.','ธนาคารอาคารสงเคราะห์ (GHB)','พร้อมเพย์ (PromptPay)'];
    $opts = '<option value="">-- เลือกธนาคาร --</option>';
    foreach ($banks as $b) {
        $sel = ($b === ($term['bank_name'] ?? '')) ? ' selected' : '';
        $opts .= '<option value="' . htmlspecialchars($b, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($b, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $acctName = htmlspecialchars($term['bank_account_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $acctNum  = htmlspecialchars($term['bank_account_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $s = 'width:100%;padding:0.6rem 0.75rem;border-radius:10px;border:1px solid rgba(255,255,255,0.15);background:rgba(15,23,42,0.7);color:#f8fafc;font-size:0.9rem;font-family:inherit;box-sizing:border-box;';
    return "<div class='form-group'><label>ธนาคาร *</label><select name='bank_name' required style='{$s}'>{$opts}</select></div>"
         . "<div class='form-group'><label>ชื่อบัญชี *</label><input type='text' name='bank_account_name' value='{$acctName}' placeholder='ชื่อ-นามสกุล ตามบัญชีธนาคาร' required style='{$s}'></div>"
         . "<div class='form-group' style='margin-bottom:0'><label>เลขที่บัญชี / เบอร์พร้อมเพย์ *</label><input type='text' name='bank_account_number' value='{$acctNum}' placeholder='กรอกตัวเลขเท่านั้น' required inputmode='numeric' pattern='[0-9]{10,15}' style='{$s}'></div>";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แจ้งยกเลิกสัญญา - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            padding-bottom: 80px;
        }
        .header {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .header-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-size: 1.5rem;
            padding: 0.5rem;
        }
        .header-title { font-size: 1.1rem; color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; padding: 1rem; }
        .contract-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .contract-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .contract-title { font-size: 1rem; color: #f8fafc; font-weight: 600; }
        .contract-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .contract-info { display: grid; gap: 0.75rem; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #94a3b8; font-size: 0.85rem; }
        .info-value { color: #f8fafc; font-size: 0.9rem; font-weight: 500; }
        .form-section {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1rem;
            color: #f8fafc;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .warning-box h4 {
            color: #f87171;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .warning-box ul {
            margin-left: 1.5rem;
            font-size: 0.85rem;
            color: #fca5a5;
        }
        .warning-box li { margin-bottom: 0.25rem; }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        .termination-status {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        .termination-status h3 {
            color: #fbbf24;
            margin-bottom: 0.5rem;
        }
        .termination-status p { color: #fcd34d; }
        .btn-cancel-termination {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.25rem;
            padding: 0.75rem 1.5rem;
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.4);
            border-radius: 10px;
            color: #f87171;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-cancel-termination:hover {
            background: rgba(239,68,68,0.22);
            transform: translateY(-1px);
        }
        .btn-cancel-termination svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.98);
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 0.5rem;
            backdrop-filter: blur(10px);
        }
        .bottom-nav-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            transition: color 0.2s;
            position: relative;
        }
        .nav-item.active, .nav-item:hover { color: #3b82f6; }
        .nav-badge {
            position: absolute;
            top: -2px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .nav-icon { font-size: 1.3rem; margin-bottom: 0.25rem; }
        .nav-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .section-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .alert-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        .btn-icon svg {
            width: 18px;
            height: 18px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }
        .status-icon svg {
            width: 32px;
            height: 32px;
            stroke: #fbbf24;
            stroke-width: 2;
            fill: none;
        }
        .warning-icon svg {
            width: 16px;
            height: 16px;
            stroke: #f87171;
            stroke-width: 2;
            fill: none;
        }
    </style>
    <?php if (($settings['public_theme'] ?? '') === 'light'): ?>
    <link rel="stylesheet" href="tenant-light-theme.css">
    <?php endif; ?>
</head>
<body class="<?= ($settings['public_theme'] ?? '') === 'light' ? 'light-theme' : '' ?>">
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span> แจ้งยกเลิกสัญญา</h1>
        </div>
    </header>
    
    <div class="container">
        <?php if ($success): ?>
        <div class="alert alert-success">
            <span class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <span class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Current Contract Info -->
        <div class="contract-card">
            <div class="contract-header">
                <span class="contract-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> สัญญาปัจจุบัน</span>
                <span class="contract-status" style="background: <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['bg']; ?>; color: <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['color']; ?>">
                    <?php echo $contractStatusMap[$contract['ctr_status'] ?? '0']['label']; ?>
                </span>
            </div>
            <div class="contract-info">
                <div class="info-row">
                    <span class="info-label">ห้องพัก</span>
                    <span class="info-value"><?php echo htmlspecialchars($contract['room_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">วันที่เริ่มสัญญา</span>
                    <span class="info-value"><?php echo !empty($contract['ctr_start']) ? thaiDate($contract['ctr_start'], 'long') : '-'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">วันที่สิ้นสุดสัญญา</span>
                    <span class="info-value"><?php echo !empty($contract['ctr_end']) ? thaiDate($contract['ctr_end'], 'long') : '-'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">เงินมัดจำ</span>
                    <span class="info-value"><?php echo number_format($contract['ctr_deposit'] ?? 0); ?> บาท</span>
                </div>
                <?php if ($depositRefund): ?>
                <div class="info-row" style="flex-direction:column;gap:0.5rem;">
                    <div style="display:flex;justify-content:space-between;width:100%;">
                        <span class="info-label">สถานะคืนเงินมัดจำ</span>
                        <span class="info-value">
                            <?php if ($depositRefund['refund_status'] === '1'): ?>
                                <span style="color:#22c55e;">✓ โอนคืนแล้ว</span>
                            <?php else: ?>
                                <span style="color:#fbbf24;">⏳ รอดำเนินการ</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ((int)$depositRefund['deduction_amount'] > 0): ?>
                    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:0.75rem;font-size:0.85rem;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;">
                            <span style="color:#94a3b8;">หักค่าเสียหาย</span>
                            <span style="color:#f87171;font-weight:600;"><?php echo number_format($depositRefund['deduction_amount']); ?> บาท</span>
                        </div>
                        <?php if (!empty($depositRefund['deduction_reason'])): ?>
                        <div style="color:#94a3b8;font-size:0.82rem;">เหตุผล: <?php echo htmlspecialchars($depositRefund['deduction_reason']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.15);border-radius:8px;padding:0.75rem;font-size:0.85rem;">
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:#94a3b8;">ยอดคืนเงิน</span>
                            <span style="color:#22c55e;font-weight:700;font-size:1rem;"><?php echo number_format($depositRefund['refund_amount']); ?> บาท</span>
                        </div>
                        <?php if ($depositRefund['refund_status'] === '1' && !empty($depositRefund['refund_date'])): ?>
                        <div style="margin-top:0.3rem;display:flex;justify-content:space-between;">
                            <span style="color:#94a3b8;">วันที่โอนคืน</span>
                            <span style="color:#000000;"><?php echo thaiDate($depositRefund['refund_date'], 'long'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($depositRefund['refund_proof'])): ?>
                        <div style="margin-top:0.8rem;border-top:1px dashed rgba(34,197,94,0.2);padding-top:0.8rem;">
                            <div style="color:#94a3b8;margin-bottom:0.4rem;font-size:0.85rem;">หลักฐานการโอนคืน:</div>
                            <a href="/<?php echo htmlspecialchars($depositRefund['refund_proof']); ?>" target="_blank" style="display:block;border-radius:6px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);">
                                <img src="/<?php echo htmlspecialchars($depositRefund['refund_proof']); ?>" alt="หลักฐานการโอนคืน" style="width:100%;max-width:100%;display:block;object-fit:cover;" />
                            </a>
                            <div style="text-align:center;margin-top:0.4rem;">
                                <a href="/<?php echo htmlspecialchars($depositRefund['refund_proof']); ?>" target="_blank"
                                   style="color:#38bdf8;font-size:0.82rem;text-decoration:none;">📎 เปิดดูภาพขนาดเต็ม</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (($contract['ctr_status'] ?? '0') === '1'): ?>
        <!-- Contract already cancelled — show final status only -->
        <div class="termination-status" style="background:rgba(100,116,139,0.15);border:1px solid rgba(100,116,139,0.3);">
            <h3 style="color:#94a3b8;"><span class="status-icon" style="color:#94a3b8;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> สัญญาสิ้นสุดแล้ว</h3>
            <p style="color:#94a3b8;">สถานะคืนเงินมัดจำแสดงในส่วน "ข้อมูลสัญญา" ด้านบน</p>
        </div>
        <?php if (($depositRefund['refund_status'] ?? '') !== '1'): ?>
        <!-- Allow tenant to add/edit bank account while refund pending -->
        <div class="form-section" style="margin-top:1rem;">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 6l7-3 7 3"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M8 14v3"/><path d="M12 14v3"/><path d="M16 14v3"/></svg></span> บัญชีธนาคารรับคืนเงินมัดจำ</div>
            <?php if (!empty($termination['bank_name']) || !empty($termination['bank_account_number'])): ?>
            <div style="padding:0.85rem;border-radius:10px;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);margin-bottom:0.85rem;">
                <div style="font-size:0.75rem;color:#64748b;font-weight:600;margin-bottom:0.35rem;">บัญชีที่ระบุไว้</div>
                <div style="font-size:0.9rem;color:#cbd5e1;"><?php echo htmlspecialchars($termination['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div style="font-size:0.88rem;color:#94a3b8;"><?php echo htmlspecialchars($termination['bank_account_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div style="font-size:0.95rem;color:#60a5fa;font-weight:700;letter-spacing:0.05em;"><?php echo htmlspecialchars($termination['bank_account_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php else: ?>
            <div style="padding:0.75rem;border-radius:10px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);margin-bottom:0.85rem;font-size:0.85rem;color:#fbbf24;">
                ⚠️ ยังไม่ได้ระบุบัญชีธนาคาร กรุณากรอกข้อมูลเพื่อให้ผู้ดูแลโอนเงินคืนได้
            </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="update_bank">
                <?php echo _bankFormFields($termination); ?>
                <button type="submit" class="btn-submit" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
                    <span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2-2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg></span>
                    บันทึกบัญชีธนาคาร
                </button>
            </form>
        </div>
        <?php endif; ?>
        <?php elseif ($hasTermination): ?>
        <!-- Already requested termination -->
        <div class="termination-status">
            <h3><span class="status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> รออนุมัติการยกเลิกสัญญา</h3>
            <p>วันที่ต้องการย้ายออก: <?php echo !empty($termination['term_date']) ? thaiDate($termination['term_date'], 'long') : htmlspecialchars($termination['term_date'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if ($termDateExceedsCtrEnd): ?>
            <div style="margin-top:0.5rem;padding:0.5rem 0.75rem;border-radius:8px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);font-size:0.82rem;color:#fca5a5;text-align:left;">
                ⚠️ วันที่ย้ายออกเกินวันสิ้นสุดสัญญา (<?php echo thaiDate($contract['ctr_end'], 'long'); ?>) กรุณาติดต่อผู้ดูแล
            </div>
            <?php endif; ?>
            <?php $refundDoneHT = isset($depositRefund['refund_status']) && $depositRefund['refund_status'] === '1'; ?>
            <?php if ($refundDoneHT): ?>
            <div class="bank-account-box" style="margin-top:0.75rem;background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.25);border-radius:10px;padding:0.8rem;text-align:left;">
                <div class="bank-title" style="font-size:0.78rem;color:#94a3b8;font-weight:600;margin-bottom:0.4rem;text-transform:uppercase;letter-spacing:0.03em;">🏦 บัญชีรับคืนเงินมัดจำที่ระบุไว้</div>
                <div class="bank-name" style="font-size:0.88rem;color:#e2e8f0;"><?php echo htmlspecialchars($termination['bank_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="bank-acc-name" style="font-size:0.88rem;color:#cbd5e1;"><?php echo htmlspecialchars($termination['bank_account_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="bank-acc-num" style="font-size:0.97rem;color:#60a5fa;font-weight:700;letter-spacing:0.06em;margin-top:0.2rem;"><?php echo htmlspecialchars($termination['bank_account_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php else: ?>
            <div style="margin-top:0.75rem;">
                <?php if (!empty($termination['bank_name']) || !empty($termination['bank_account_number'])): ?>
                <div class="bank-account-box" style="padding:0.7rem;border-radius:10px;background:rgba(15,23,42,0.4);border:1px solid rgba(59,130,246,0.3);margin-bottom:0.7rem;">
                    <div class="bank-title" style="font-size:0.75rem;color:#93c5fd;font-weight:600;margin-bottom:0.3rem;">บัญชีที่ระบุไว้</div>
                    <div class="bank-name" style="font-size:0.88rem;color:#f1f5f9;"><?php echo htmlspecialchars($termination['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="bank-acc-name" style="font-size:0.85rem;color:#cbd5e1;"><?php echo htmlspecialchars($termination['bank_account_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="bank-acc-num" style="font-size:0.95rem;color:#60a5fa;font-weight:700;letter-spacing:0.05em;margin-top:0.15rem;"><?php echo htmlspecialchars($termination['bank_account_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php else: ?>
                <div style="padding:0.6rem;border-radius:8px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);margin-bottom:0.7rem;font-size:0.82rem;color:#fbbf24;">
                    ⚠️ ยังไม่ได้ระบุบัญชีธนาคาร กรุณากรอกเพื่อให้ผู้ดูแลโอนเงินคืน
                </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update_bank">
                    <div style="padding:0.85rem;border-radius:12px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);margin-bottom:0.75rem;">
                        <div style="font-size:0.82rem;color:#60a5fa;font-weight:600;margin-bottom:0.75rem;">🏦 <?php echo empty($termination['bank_name']) ? 'ระบุบัญชีธนาคาร' : 'แก้ไขบัญชีธนาคาร'; ?></div>
                        <?php echo _bankFormFields($termination); ?>
                    </div>
                    <button type="submit" class="btn-submit" style="background:linear-gradient(135deg,#3b82f6,#2563eb);margin-bottom:0.75rem;">
                        <span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2-2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg></span>
                        บันทึกบัญชีธนาคาร
                    </button>
                </form>
            </div>
            <?php endif; ?>
            <!-- two-step confirm เพื่อหลีก window.confirm() ที่ถูก block บน mobile -->
            <div id="cancelTermStep1" style="margin-top:1.25rem;">
                <button type="button" class="btn-cancel-termination" onclick="document.getElementById('cancelTermStep1').style.display='none';document.getElementById('cancelTermStep2').style.display='block';">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    ยกเลิกคำร้องยกเลิกสัญญา
                </button>
            </div>
            <div id="cancelTermStep2" style="display:none;margin-top:1rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.35);border-radius:12px;padding:1rem;text-align:left;">
                <p style="color:#fca5a5;font-size:0.88rem;margin-bottom:0.85rem;">⚠ ยืนยันยกเลิกคำร้อง? สัญญาจะกลับสู่สถานะ “ปกติ”</p>
                <form method="POST">
                    <input type="hidden" name="action" value="cancel_termination">
                    <div style="display:flex;gap:0.6rem;">
                        <button type="submit" class="btn-cancel-termination" style="flex:1;justify-content:center;margin-top:0;padding:0.75rem;font-size:0.88rem;">✓ ยืนยัน</button>
                        <button type="button" style="flex:1;padding:0.75rem;background:rgba(100,116,139,0.15);border:1px solid rgba(100,116,139,0.4);border-radius:10px;color:#e2e8f0;font-family:inherit;font-size:0.88rem;cursor:pointer;" onclick="document.getElementById('cancelTermStep2').style.display='none';document.getElementById('cancelTermStep1').style.display='block';">ย้อนกลับ</button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Termination Form -->
        <div class="form-section">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span> แจ้งยกเลิกสัญญา</div>
            
            <div class="warning-box">
                <h4><span class="warning-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span> ข้อควรทราบ</h4>
                <ul>
                    <li>กรุณาแจ้งล่วงหน้าอย่างน้อย 1 เดือน</li>
                    <li>ต้องชำระค่าใช้จ่ายค้างทั้งหมดก่อนย้ายออก</li>
                    <li>เงินมัดจำจะคืนหลังตรวจสอบห้องพักเรียบร้อย</li>
                    <li>หากมีความเสียหายจะหักจากเงินมัดจำ</li>
                </ul>
            </div>
            
            <?php if ($unpaidBillCount > 0): ?>
            <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:1rem;margin-bottom:1rem;">
                <div style="color:#f87171;font-weight:600;font-size:0.9rem;">⚠️ ยังมีบิลค้างชำระ <?php echo $unpaidBillCount; ?> รายการ</div>
                <div style="color:#fca5a5;font-size:0.85rem;margin-top:0.3rem;">กรุณาชำระค่าห้องให้ครบทุกเดือนก่อนแจ้งยกเลิกสัญญา</div>
            </div>
            <?php else: ?>
            <form method="POST" onsubmit="return confirmTermination()">
                <div class="form-group">
                    <label>วันที่ต้องการย้ายออก *</label>
                    <input type="date" name="term_date" min="<?php echo $minTermDate; ?>"<?php echo $maxDate ? ' max="' . htmlspecialchars($maxDate, ENT_QUOTES, 'UTF-8') . '"' : ''; ?> required>
                </div>

                <div style="margin-bottom:1rem;padding:0.85rem;border-radius:12px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);">
                    <div style="font-size:0.82rem;color:#60a5fa;font-weight:600;margin-bottom:0.75rem;">🏦 บัญชีธนาคารสำหรับรับคืนเงินมัดจำ</div>
                    <div class="form-group">
                        <label>ธนาคาร *</label>
                        <select name="bank_name" required style="width:100%;padding:0.6rem 0.75rem;border-radius:10px;border:1px solid rgba(255,255,255,0.15);background:rgba(15,23,42,0.7);color:#f8fafc;font-size:0.9rem;font-family:inherit;">
                            <option value="">-- เลือกธนาคาร --</option>
                            <option value="ธนาคารกสิกรไทย (KBank)">ธนาคารกสิกรไทย (KBank)</option>
                            <option value="ธนาคารไทยพาณิชย์ (SCB)">ธนาคารไทยพาณิชย์ (SCB)</option>
                            <option value="ธนาคารกรุงเทพ (BBL)">ธนาคารกรุงเทพ (BBL)</option>
                            <option value="ธนาคารกรุงไทย (KTB)">ธนาคารกรุงไทย (KTB)</option>
                            <option value="ธนาคารกรุงศรีอยุธยา (BAY)">ธนาคารกรุงศรีอยุธยา (BAY)</option>
                            <option value="ธนาคารทหารไทยธนชาต (TTB)">ธนาคารทหารไทยธนชาต (TTB)</option>
                            <option value="ธนาคารออมสิน (GSB)">ธนาคารออมสิน (GSB)</option>
                            <option value="ธนาคาร ธ.ก.ส.">ธนาคาร ธ.ก.ส.</option>
                            <option value="ธนาคารอาคารสงเคราะห์ (GHB)">ธนาคารอาคารสงเคราะห์ (GHB)</option>
                            <option value="พร้อมเพย์ (PromptPay)">พร้อมเพย์ (PromptPay)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ชื่อบัญชี *</label>
                        <input type="text" name="bank_account_name" placeholder="ชื่อ-นามสกุล ตามบัญชีธนาคาร" required
                               style="width:100%;padding:0.6rem 0.75rem;border-radius:10px;border:1px solid rgba(255,255,255,0.15);background:rgba(15,23,42,0.7);color:#f8fafc;font-size:0.9rem;font-family:inherit;box-sizing:border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>เลขที่บัญชี / เบอร์พร้อมเพย์ *</label>
                        <input type="text" name="bank_account_number" placeholder="กรอกตัวเลขเท่านั้น" required
                               inputmode="numeric" pattern="[0-9]{10,15}"
                               title="กรอกเลขบัญชี 10–15 หลัก หรือเบอร์โทรศัพท์ 10 หลัก (สำหรับพร้อมเพย์)"
                               style="width:100%;padding:0.6rem 0.75rem;border-radius:10px;border:1px solid rgba(255,255,255,0.15);background:rgba(15,23,42,0.7);color:#f8fafc;font-size:0.9rem;font-family:inherit;box-sizing:border-box;letter-spacing:0.05em;">
                    </div>
                </div>

                <button type="submit" class="btn-submit"><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></span> ส่งคำร้องยกเลิกสัญญา</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php
    $repairCount = 0;
    try {
        $repairStmt = $pdo->prepare("SELECT COUNT(*) FROM repair WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE tnt_id = ?) AND repair_status = '0'");
        $repairStmt->execute([$contract['tnt_id']]);
        $repairCount = (int)($repairStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    $homeBadgeCount = 0;
    try {
        $homeBadgeStmt = $pdo->prepare("
            SELECT 1 
            FROM contract c
            LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant'
            LEFT JOIN tenant_workflow tw ON c.tnt_id = tw.tnt_id
            WHERE c.ctr_id = ? AND c.ctr_status != '1' AND tw.step_3_confirmed = 1 AND sl.id IS NULL
            LIMIT 1
        ");
        $homeBadgeStmt->execute([$contract['ctr_id'] ?? 0]);
        if ($homeBadgeStmt->fetchColumn()) {
            $homeBadgeCount = 1;
        }
    } catch (Exception $e) { error_log("Exception calculating home badge count in " . __FILE__ . ": " . $e->getMessage()); }

    $billCount = 0;
    try {
        $billStmt = $pdo->prepare("
            SELECT COUNT(*) FROM expense e
            INNER JOIN (
                SELECT MAX(exp_id) AS exp_id FROM expense WHERE ctr_id = ? GROUP BY exp_month
            ) latest ON e.exp_id = latest.exp_id
            WHERE e.ctr_id = ?
            AND DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(?, '%Y-%m')
            AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
            AND (
                e.exp_month = (SELECT MIN(e2.exp_month) FROM expense e2 WHERE e2.ctr_id = e.ctr_id)
                OR EXISTS (
                    SELECT 1
                    FROM utility u
                    WHERE u.ctr_id = e.ctr_id
                        AND YEAR(u.utl_date) = YEAR(e.exp_month)
                        AND MONTH(u.utl_date) = MONTH(e.exp_month)
                        AND u.utl_water_end IS NOT NULL
                        AND u.utl_elec_end IS NOT NULL
                )
            )
            AND COALESCE((
                SELECT SUM(p.pay_amount) FROM payment p
                WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
            ), 0) < e.exp_total
        ");
        $billStmt->execute([$contract['ctr_id'], $contract['ctr_id'], $contract['ctr_start'] ?? date('Y-m-d')]);
        $billCount = (int)($billStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception calculating bill count in " . __FILE__ . ": " . $e->getMessage()); }
    ?>
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                                หน้าหลัก<?php if ($homeBadgeCount > 0): ?><span class="nav-badge">1</span><?php endif; ?>
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>&_ts=<?php echo time(); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                บิล<?php if ($billCount > 0): ?><span class="nav-badge"><?php echo $billCount > 99 ? '99+' : $billCount; ?></span><?php endif; ?>
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                แจ้งซ่อม<?php if ($repairCount > 0): ?><span class="nav-badge"><?php echo $repairCount > 99 ? '99+' : $repairCount; ?></span><?php endif; ?></a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                โปรไฟล์
            </a>
        </div>
    </nav>
    
    <script>
    function confirmTermination() {
        return confirm('⚠️ คุณแน่ใจหรือไม่ที่จะยกเลิกสัญญา?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้');
    }
    function confirmCancelTermination() {
        return confirm('ยืนยันยกเลิกคำร้องยกเลิกสัญญา?\n\nสัญญาของคุณจะกลับสู่สถานะ “ปกติ”');
    }
    </script>
</body>
</html>
