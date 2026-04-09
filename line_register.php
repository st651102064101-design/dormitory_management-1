<?php
/**
 * LINE Register - หน้าลงทะเบียนสำหรับผู้ใช้ใหม่ที่ล็อกอินด้วย LINE
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ฟังก์ชันสร้าง tnt_id อัตโนมัติ (13 หลัก เริ่มด้วย T)
function generateTntId($pdo) {
    do {
        $tntId = time() . sprintf('%02d', rand(0, 99));
        $tntId = substr((string)$tntId, 0, 13);
        $stmt = $pdo->prepare('SELECT tnt_id FROM tenant WHERE tnt_id = ?');
        $stmt->execute([$tntId]);
    } while ($stmt->fetch());
    return $tntId;
}

// =============================================
// AJAX Handler - ถ้าเป็น AJAX request
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json; charset=utf-8');
    
    // ตรวจสอบ session
    if (!isset($_SESSION['line_register'])) {
        echo json_encode(['success' => false, 'error' => 'Session หมดอายุ กรุณาล็อกอิน LINE ใหม่']);
        exit;
    }
    
    $lineData = $_SESSION['line_register'];
    $lineId = $lineData['line_id'];
    $email = $lineData['email'] ?? '';
    $picture = $lineData['picture'] ?? '';
    
    $tntName = trim($_POST['tnt_name'] ?? '');
    $tntPhone = trim($_POST['tnt_phone'] ?? '');
    $tntPhone = preg_replace('/[^0-9]/', '', $tntPhone);
    $tntPhone = substr($tntPhone, 0, 10);
    $consent = isset($_POST['consent']) && $_POST['consent'] === 'true';
    
    // Validation
    if (empty($tntName)) {
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกชื่อ-นามสกุล']);
        exit;
    }
    if (empty($tntPhone) || strlen($tntPhone) < 9) {
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (อย่างน้อย 9 หลัก)']);
        exit;
    }
    if (!$consent) {
        echo json_encode(['success' => false, 'error' => 'กรุณายินยอมการเก็บรวบรวมและใช้ข้อมูลส่วนบุคคล']);
        exit;
    }
    
    try {
        $tntId = generateTntId($pdo);
        
        $pdo->beginTransaction();
        
        // Insert tenant
        $insertTenant = $pdo->prepare('INSERT INTO tenant (tnt_id, tnt_name, tnt_phone, tnt_status) VALUES (?, ?, ?, "1")');
        $insertTenant->execute([$tntId, $tntName, $tntPhone]);
        
        // Insert OAuth link
        $insertOAuth = $pdo->prepare('INSERT INTO tenant_oauth (tnt_id, provider, provider_id, provider_email, picture) VALUES (?, "line", ?, ?, ?)');
        $insertOAuth->execute([$tntId, $lineId, $email, $picture]);
        
        // Add to tenant directly
        $updateLineUser = $pdo->prepare('UPDATE tenant SET line_user_id = ? WHERE tnt_id = ?');
        $updateLineUser->execute([$lineId, $tntId]);
        
        $pdo->commit();
        
        // Set session
        unset($_SESSION['line_register']);
        $_SESSION['tenant_id'] = $tntId;
        $_SESSION['tenant_name'] = $tntName;
        $_SESSION['tenant_picture'] = $picture;
        $_SESSION['tenant_logged_in'] = true;
        
        echo json_encode([
            'success' => true, 
            'message' => 'ลงทะเบียนสำเร็จ!',
            'redirect' => 'index.php?register=success'
        ]);
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        exit;
    }
}

// =============================================
// Normal Page Load
// =============================================

// ตรวจสอบว่ามีข้อมูล LINE หรือไม่
if (!isset($_SESSION['line_register'])) {
    header('Location: index.php');
    exit;
}

$lineData = $_SESSION['line_register'];
$lineId = $lineData['line_id'];
$email = $lineData['email'] ?? '';
$name = $lineData['name'];
$picture = $lineData['picture'] ?? '';

$suggestedPhone = '';
if (!empty($lineData['phone'])) {
    $suggestedPhone = preg_replace('/[^0-9]/', '', $lineData['phone']);
}

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
$publicTheme = 'dark';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'public_theme')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'public_theme') $publicTheme = $row['setting_value'];
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ลงทะเบียนผู้เช่าใหม่ | <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Prompt', sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
        }
        
        .register-card {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }
        
        .google-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 16px;
            margin-bottom: 24px;
        }
        
        .google-profile img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid #3b82f6;
        }
        
        .google-profile-info h3 {
            color: #fff;
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .google-profile-info p {
            color: #94a3b8;
            font-size: 14px;
        }
        
        .register-title {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .register-title h1 {
            color: #fff;
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .register-title p {
            color: #94a3b8;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #cbd5e1;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-group label .required {
            color: #ef4444;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .form-input::placeholder {
            color: #64748b;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        }
        
        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-text, .btn-loading {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Success Overlay */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.98);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #22c55e, #10b981);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            animation: successBounce 0.5s ease;
        }
        
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: #fff;
        }
        
        .success-text {
            color: #fff;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .success-subtext {
            color: #94a3b8;
            font-size: 14px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes successBounce {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        /* Success Message */
        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* PDPA Notice */
        .pdpa-notice {
            background: rgba(59, 130, 246, 0.05);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .pdpa-notice h4 {
            color: #3b82f6;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdpa-notice ul {
            list-style: none;
            padding: 0;
            margin: 0 0 12px 0;
        }
        
        .pdpa-notice li {
            color: #cbd5e1;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }
        
        .pdpa-notice li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #3b82f6;
            font-weight: bold;
        }
        
        .pdpa-notice .pdpa-detail {
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .consent-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
            padding: 16px;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .consent-checkbox:hover {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }
        
        .consent-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .consent-checkbox label {
            color: #cbd5e1;
            font-size: 13px;
            line-height: 1.6;
            cursor: pointer;
            margin: 0;
        }
        
        .consent-checkbox label a {
            color: #3b82f6;
            text-decoration: underline;
        }
        
        .consent-checkbox label a:hover {
            color: #60a5fa;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            color: #fff;
        }
        
        /* Success Animation */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
            animation: fadeIn 0.3s ease;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #22c55e, #10b981);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            animation: successBounce 0.5s ease;
        }
        
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: #fff;
        }
        
        .success-text {
            color: #fff;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .success-subtext {
            color: #94a3b8;
            font-size: 14px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes successBounce {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        @media (max-width: 480px) {
            .register-card {
                padding: 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-title">
                <h1>🏠 ลงทะเบียนผู้เช่าใหม่</h1>
                <p>กรุณากรอกข้อมูลเพื่อเข้าใช้งานระบบ</p>
            </div>
            
            <div class="google-profile">
                <img src="<?php echo htmlspecialchars($picture); ?>" alt="Profile" onerror="this.src='https://via.placeholder.com/60'">
                <div class="google-profile-info">
                    <h3><?php echo htmlspecialchars($name); ?></h3>
                    <p><?php echo htmlspecialchars($email); ?></p>
                </div>
            </div>
            
            <!-- Error/Success Message Container -->
            <div id="messageContainer" style="display: none;"></div>
            
            <form id="registerForm">
                <div class="form-group">
                    <label>ชื่อ-นามสกุล <span class="required">*</span></label>
                    <input type="text" name="tnt_name" id="tnt_name" class="form-input" placeholder="เช่น นายสมชาย ใจดี"
                           value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>เบอร์โทรศัพท์ <span class="required">*</span></label>
                    <input type="tel" name="tnt_phone" id="tnt_phone" class="form-input" placeholder="08X-XXX-XXXX"
                           value="<?php echo htmlspecialchars($suggestedPhone); ?>" required>
                    <?php if (!empty($suggestedPhone)): ?>
                    <small style="color: #94a3b8; font-size: 12px; margin-top: 4px; display: block;">💡 ดึงข้อมูลจาก LINE Account</small>
                    <?php endif; ?>
                </div>
                
                <!-- PDPA Notice -->
                <div class="pdpa-notice">
                    <h4>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="M9 12l2 2 4-4"/>
                        </svg>
                        การเก็บรวบรวมและใช้ข้อมูลส่วนบุคคล
                    </h4>
                    <ul>
                        <li><strong>ข้อมูลที่เก็บจาก LINE:</strong> ชื่อ, อีเมล, รูปโปรไฟล์, เบอร์โทร (ถ้ามี)</li>
                        <li><strong>ข้อมูลที่คุณกรอก:</strong> ชื่อ-นามสกุล, เบอร์โทรศัพท์</li>
                        <li><strong>วัตถุประสงค์:</strong> เพื่อสร้างบัญชีผู้เช่า, ยืนยันตัวตน, ติดต่อสื่อสาร</li>
                        <li><strong>ระยะเวลาเก็บข้อมูล:</strong> ตลอดระยะเวลาที่เป็นผู้เช่าและ 1 ปีหลังสิ้นสุดสัญญา</li>
                        <li><strong>การเปิดเผย:</strong> เฉพาะเจ้าของหอพักและเจ้าหน้าที่ที่เกี่ยวข้อง</li>
                    </ul>
                    <div class="pdpa-detail">
                        📋 <strong>สิทธิของคุณ:</strong> คุณมีสิทธิขอเข้าถึง แก้ไข ลบ หรือคัดค้านการประมวลผลข้อมูลได้ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA) และพระราชบัญญัติว่าด้วยการกระทำความผิดเกี่ยวกับคอมพิวเตอร์ พ.ศ. 2550
                    </div>
                </div>
                
                <!-- Consent Checkbox -->
                <div class="consent-checkbox">
                    <input type="checkbox" id="consent" name="consent">
                    <label for="consent">
                        ข้าพเจ้ายินยอมให้เก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลของข้าพเจ้าตามวัตถุประสงค์ที่ระบุข้างต้น และรับทราบสิทธิตาม PDPA <span class="required">*</span>
                    </label>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span class="btn-text">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        ลงทะเบียน
                    </span>
                    <span class="btn-loading" style="display: none;">
                        <svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" stroke-dasharray="30 70"/>
                        </svg>
                        กำลังลงทะเบียน...
                    </span>
                </button>
            </form>
            
            <a href="Login.php" class="back-link">← กลับหน้าหลัก</a>
        </div>
    </div>
    
    <!-- Success Overlay -->
    <div id="successOverlay" class="success-overlay" style="display: none;">
        <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <p class="success-text">ลงทะเบียนสำเร็จ!</p>
        <p class="success-subtext">กำลังนำคุณไปยังหน้าหลัก...</p>
    </div>
    
    <script>
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const messageContainer = document.getElementById('messageContainer');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        // Get form values
        const tntName = document.getElementById('tnt_name').value.trim();
        const tntPhone = document.getElementById('tnt_phone').value.trim();
        const consent = document.getElementById('consent').checked;
        
        // Reset message
        messageContainer.style.display = 'none';
        messageContainer.className = '';
        
        // Validation
        if (!tntName) {
            showMessage('กรุณากรอกชื่อ-นามสกุล', 'error');
            return;
        }
        if (!tntPhone || tntPhone.replace(/[^0-9]/g, '').length < 9) {
            showMessage('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (อย่างน้อย 9 หลัก)', 'error');
            return;
        }
        if (!consent) {
            showMessage('กรุณายินยอมการเก็บรวบรวมและใช้ข้อมูลส่วนบุคคล', 'error');
            return;
        }
        
        // Show loading
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        submitBtn.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('tnt_name', tntName);
            formData.append('tnt_phone', tntPhone);
            formData.append('consent', consent ? 'true' : 'false');
            
            const response = await fetch('line_register.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success overlay
                document.getElementById('successOverlay').style.display = 'flex';
                
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = result.redirect || 'index.php';
                }, 2000);
            } else {
                showMessage(result.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
                resetButton();
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message, 'error');
            resetButton();
        }
    });
    
    function showMessage(message, type) {
        const messageContainer = document.getElementById('messageContainer');
        messageContainer.className = type === 'error' ? 'error-message' : 'success-message';
        messageContainer.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            ${message}
        `;
        messageContainer.style.display = 'flex';
        messageContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function resetButton() {
        const submitBtn = document.getElementById('submitBtn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        btnText.style.display = 'inline-flex';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
    }
    </script>
</body>
</html>
