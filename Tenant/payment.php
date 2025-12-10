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
            $uploadsDir = __DIR__ . '/../Assets/Images/Payments';
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
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title">💰 แจ้งชำระเงิน</h1>
        </div>
    </header>
    
    <div class="container">
        <?php if ($success): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <span>❌</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="form-section">
            <div class="section-title">📝 แจ้งชำระเงิน</div>
            
            <?php if (empty($unpaidExpenses)): ?>
            <div class="no-unpaid">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
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
                            <span class="bill-month">📅 <?php echo date('F Y', strtotime($expense['exp_month'])); ?></span>
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
                        <div class="file-upload-icon">📷</div>
                        <div class="file-upload-text">แตะเพื่อเลือกรูปสลิป</div>
                    </div>
                    <div id="preview-container">
                        <img id="preview-image" src="" alt="Preview">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn" disabled>📤 ส่งแจ้งชำระเงิน</button>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="payment-history">
            <div class="section-title">📋 ประวัติการแจ้งชำระเงิน</div>
            
            <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>ยังไม่มีประวัติการชำระเงิน</p>
            </div>
            <?php else: ?>
            <?php foreach ($payments as $payment): ?>
            <div class="payment-item">
                <div class="payment-header">
                    <span class="payment-date">📅 <?php echo $payment['pay_date'] ?? '-'; ?></span>
                    <span class="payment-status" style="background: <?php echo $paymentStatusMap[$payment['pay_status'] ?? '0']['bg']; ?>; color: <?php echo $paymentStatusMap[$payment['pay_status'] ?? '0']['color']; ?>">
                        <?php echo $paymentStatusMap[$payment['pay_status'] ?? '0']['label']; ?>
                    </span>
                </div>
                <div class="payment-amount">💵 <?php echo number_format($payment['pay_amount'] ?? 0); ?> บาท</div>
                <div class="bill-details">บิลเดือน <?php echo date('F Y', strtotime($payment['exp_month'])); ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">🏠</div>
                หน้าหลัก
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">🧾</div>
                บิล
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">🔧</div>
                แจ้งซ่อม
            </a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon">👤</div>
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
    </script>
</body>
</html>
