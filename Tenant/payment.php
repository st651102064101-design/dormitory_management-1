<?php
/**
 * Tenant Payment - แจ้งชำระเงิน
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);

$success = '';
$error = '';

// Get unpaid expenses
$unpaidExpenses = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.*, r.room_number 
        FROM expense e
        JOIN contract c ON e.ctr_id = c.ctr_id
        JOIN room r ON c.room_id = r.room_id
        WHERE e.ctr_id = ? AND e.exp_status = '0'
        ORDER BY e.exp_month DESC
    ");
    $stmt->execute([$contract['ctr_id']]);
    $unpaidExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $exp_id = (int)($_POST['exp_id'] ?? 0);
        $pay_amount = (int)($_POST['pay_amount'] ?? 0);
        
        if ($exp_id <= 0) {
            $error = 'กรุณาเลือกบิลที่ต้องการชำระ';
        } elseif (empty($_FILES['pay_proof']['name'])) {
            $error = 'กรุณาแนบหลักฐานการชำระเงิน';
        } else {
            // Verify expense belongs to this contract
            $checkStmt = $pdo->prepare("SELECT * FROM expense WHERE exp_id = ? AND ctr_id = ?");
            $checkStmt->execute([$exp_id, $contract['ctr_id']]);
            $expense = $checkStmt->fetch();
            
            if (!$expense) {
                throw new Exception('ไม่พบบิลที่ระบุ');
            }
            
            // Handle file upload
            $file = $_FILES['pay_proof'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if ($file['size'] > $maxFileSize) {
                throw new Exception('ไฟล์ใหญ่เกินไป (ไม่เกิน 5MB)');
            }
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('ประเภทไฟล์ไม่ถูกต้อง (สนับสนุน JPG, PNG, WebP)');
            }
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uploadsDir = __DIR__ . '/..//Assets/Images/Payments';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            
            $filename = 'payment_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $targetPath = $uploadsDir . '/' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('ไม่สามารถอัพโหลดไฟล์ได้');
            }
            
            // Insert payment record
            $stmt = $pdo->prepare("
                INSERT INTO payment (pay_date, pay_amount, pay_proof, pay_status, exp_id)
                VALUES (CURDATE(), ?, ?, '0', ?)
            ");
            $stmt->execute([$pay_amount ?: $expense['exp_total'], $filename, $exp_id]);
            
            $success = 'แจ้งชำระเงินเรียบร้อยแล้ว รอการตรวจสอบจากผู้ดูแล';
            
            // Refresh unpaid expenses
            $stmt = $pdo->prepare("
                SELECT e.*, r.room_number 
                FROM expense e
                JOIN contract c ON e.ctr_id = c.ctr_id
                JOIN room r ON c.room_id = r.room_id
                WHERE e.ctr_id = ? AND e.exp_status = '0'
                ORDER BY e.exp_month DESC
            ");
            $stmt->execute([$contract['ctr_id']]);
            $unpaidExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get payment history
$payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.exp_month, e.exp_total 
        FROM payment p
        JOIN expense e ON p.exp_id = e.exp_id
        WHERE e.ctr_id = ?
        ORDER BY p.pay_date DESC
    ");
    $stmt->execute([$contract['ctr_id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$paymentStatusMap = [
    '0' => ['label' => 'รอตรวจสอบ', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.2)'],
    '1' => ['label' => 'ตรวจสอบแล้ว', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แจ้งชำระเงิน - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="..//Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
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
        .form-group select, .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .file-upload {
            position: relative;
            width: 100%;
            height: 120px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .file-upload:hover { border-color: #3b82f6; }
        .file-upload input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .file-upload-text { font-size: 0.85rem; color: #94a3b8; }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
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
        .bill-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .bill-card:hover { border-color: #3b82f6; }
        .bill-card.selected { border-color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .bill-month { font-weight: 600; color: #f8fafc; }
        .bill-total { font-size: 1.2rem; font-weight: 700; color: #f59e0b; }
        .bill-details { font-size: 0.8rem; color: #94a3b8; }
        .payment-history { margin-top: 2rem; }
        .payment-item {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .payment-date { font-size: 0.8rem; color: #64748b; }
        .payment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .payment-amount { font-size: 1rem; font-weight: 600; color: #f8fafc; }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }
        .empty-state-icon { font-size: 3rem; margin-bottom: 0.5rem; }
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
        }
        .nav-item.active, .nav-item:hover { color: #3b82f6; }
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
        .file-upload-icon svg {
            width: 32px;
            height: 32px;
            stroke: #64748b;
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
        .empty-state-icon svg {
            width: 48px;
            height: 48px;
            stroke: #64748b;
            stroke-width: 1.5;
            fill: none;
        }
        .date-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .date-icon svg {
            width: 12px;
            height: 12px;
            stroke: #64748b;
            stroke-width: 2;
            fill: none;
            margin-right: 4px;
        }
        .no-unpaid-icon svg {
            width: 32px;
            height: 32px;
            stroke: #34d399;
            stroke-width: 2;
            fill: none;
        }
        .amount-icon svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            margin-right: 4px;
            vertical-align: middle;
        }
        #preview-container { display: none; margin-top: 0.5rem; }
        #preview-container img { max-width: 100%; max-height: 150px; border-radius: 8px; }
        .no-unpaid {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            color: #34d399;
        }
        /* Bank Info Styles */
        .bank-info-section {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(51, 65, 85, 0.9) 100%);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .bank-info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .bank-info-item:last-child {
            border-bottom: none;
        }
        .bank-info-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .bank-info-icon.bank {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        .bank-info-icon.account {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        .bank-info-icon.number {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .bank-info-icon.promptpay {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .bank-info-icon svg {
            width: 22px;
            height: 22px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }
        .bank-info-content {
            flex: 1;
        }
        .bank-info-label {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 2px;
        }
        .bank-info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #f8fafc;
        }
        .copy-text {
            cursor: pointer;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .copy-text:hover {
            color: #3b82f6;
        }
        .copy-text:active {
            transform: scale(0.98);
        }
        .copy-icon {
            font-size: 0.9rem;
            opacity: 0.6;
        }
        .copy-toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(16, 185, 129, 0.95);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 1000;
            animation: toastIn 0.3s ease, toastOut 0.3s ease 1.5s forwards;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(-50%) translateY(20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateX(-50%) translateY(0); }
            to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span> แจ้งชำระเงิน</h1>
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
        
        <!-- Bank Information Section -->
        <?php if (!empty($settings['bank_name']) || !empty($settings['promptpay_number'])): ?>
        <div class="form-section bank-info-section">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> ข้อมูลการชำระเงิน</div>
            
            <?php if (!empty($settings['bank_name'])): ?>
            <div class="bank-info-item">
                <div class="bank-info-icon bank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 6l7-3 7 3"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M8 14v3"/><path d="M12 14v3"/><path d="M16 14v3"/></svg></div>
                <div class="bank-info-content">
                    <div class="bank-info-label">ธนาคาร</div>
                    <div class="bank-info-value"><?php echo htmlspecialchars($settings['bank_name']); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['bank_account_name'])): ?>
            <div class="bank-info-item">
                <div class="bank-info-icon account"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="bank-info-content">
                    <div class="bank-info-label">ชื่อบัญชี</div>
                    <div class="bank-info-value"><?php echo htmlspecialchars($settings['bank_account_name']); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['bank_account_number'])): ?>
            <div class="bank-info-item">
                <div class="bank-info-icon number"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 15h0M2 9.5h20"/></svg></div>
                <div class="bank-info-content">
                    <div class="bank-info-label">เลขบัญชี</div>
                    <div class="bank-info-value copy-text" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['bank_account_number']); ?>')"><?php echo htmlspecialchars($settings['bank_account_number']); ?> <span class="copy-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;opacity:0.75;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['promptpay_number'])): ?>
            <div class="bank-info-item">
                <div class="bank-info-icon promptpay"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
                <div class="bank-info-content">
                    <div class="bank-info-label">พร้อมเพย์</div>
                    <div class="bank-info-value copy-text" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['promptpay_number']); ?>')"><?php echo htmlspecialchars($settings['promptpay_number']); ?> <span class="copy-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;opacity:0.75;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="form-section">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span> แจ้งชำระเงิน</div>
            
            <?php if (empty($unpaidExpenses)): ?>
            <div class="no-unpaid">
                <div class="no-unpaid-icon" style="margin-bottom: 0.5rem;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <p>ไม่มีบิลค้างชำระ</p>
            </div>
            <?php else: ?>
            <form method="POST" enctype="multipart/form-data" id="paymentForm">
                <div class="form-group">
                    <label>เลือกบิลที่ต้องการชำระ *</label>
                    <?php foreach ($unpaidExpenses as $expense): ?>
                    <div class="bill-card" onclick="selectBill(<?php echo $expense['exp_id']; ?>, <?php echo $expense['exp_total']; ?>)">
                        <input type="radio" name="exp_id" value="<?php echo $expense['exp_id']; ?>" style="display:none;" id="bill_<?php echo $expense['exp_id']; ?>">
                        <div class="bill-header">
                            <span class="bill-month"><span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> <?php echo date('F Y', strtotime($expense['exp_month'])); ?></span>
                            <span class="bill-total"><?php echo number_format($expense['exp_total']); ?> บาท</span>
                        </div>
                        <div class="bill-details">
                            ค่าไฟ <?php echo number_format($expense['exp_elec_chg'] ?? 0); ?> | 
                            ค่าน้ำ <?php echo number_format($expense['exp_water'] ?? 0); ?> | 
                            ค่าห้อง <?php echo number_format($expense['room_price'] ?? 0); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <label>จำนวนเงินที่ชำระ</label>
                    <input type="number" name="pay_amount" id="pay_amount" placeholder="จะถูกกำหนดตามบิลที่เลือก" readonly>
                </div>
                
                <div class="form-group">
                    <label>หลักฐานการชำระเงิน (สลิป) *</label>
                    <div class="file-upload" onclick="document.getElementById('pay_proof').click()">
                        <input type="file" name="pay_proof" id="pay_proof" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)" required>
                        <div class="file-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                        <div class="file-upload-text">แตะเพื่อเลือกรูปสลิป</div>
                    </div>
                    <div id="preview-container">
                        <img id="preview-image" src="" alt="Preview">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn" disabled><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></span> ส่งแจ้งชำระเงิน</button>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="payment-history">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> ประวัติการแจ้งชำระเงิน</div>
            
            <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"/><path d="M5.5 5.1L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/></svg></div>
                <p>ยังไม่มีประวัติการชำระเงิน</p>
            </div>
            <?php else: ?>
            <?php foreach ($payments as $payment): ?>
            <div class="payment-item">
                <div class="payment-header">
                    <span class="payment-date"><span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> <?php echo $payment['pay_date'] ?? '-'; ?></span>
                    <span class="payment-status" style="background: <?php echo $paymentStatusMap[$payment['pay_status'] ?? '0']['bg']; ?>; color: <?php echo $paymentStatusMap[$payment['pay_status'] ?? '0']['color']; ?>">
                        <?php echo $paymentStatusMap[$payment['pay_status'] ?? '0']['label']; ?>
                    </span>
                </div>
                <div class="payment-amount"><span class="amount-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> <?php echo number_format($payment['pay_amount'] ?? 0); ?> บาท</div>
                <div class="bill-details">บิลเดือน <?php echo date('F Y', strtotime($payment['exp_month'])); ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                หน้าหลัก
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                บิล
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                แจ้งซ่อม
            </a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                โปรไฟล์
            </a>
        </div>
    </nav>
    
    <script>
    let selectedBill = null;
    
    function selectBill(expId, amount) {
        document.querySelectorAll('.bill-card').forEach(card => card.classList.remove('selected'));
        event.currentTarget.classList.add('selected');
        document.getElementById('bill_' + expId).checked = true;
        document.getElementById('pay_amount').value = amount;
        selectedBill = expId;
        checkFormValid();
    }
    
    function previewImage(input) {
        const container = document.getElementById('preview-container');
        const preview = document.getElementById('preview-image');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                container.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
        checkFormValid();
    }
    
    function checkFormValid() {
        const hasProof = document.getElementById('pay_proof').files.length > 0;
        document.getElementById('submitBtn').disabled = !(selectedBill && hasProof);
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showCopyToast('คัดลอกแล้ว ✓');
        }).catch(() => {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showCopyToast('คัดลอกแล้ว ✓');
        });
    }
    
    function showCopyToast(message) {
        // Remove existing toast
        const existingToast = document.querySelector('.copy-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = 'copy-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 2000);
    }
    </script>
</body>
</html>
