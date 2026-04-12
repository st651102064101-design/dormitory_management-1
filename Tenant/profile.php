<?php
/**
 * Tenant Profile - จัดการข้อมูลส่วนตัว
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
$profileReadOnly = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($profileReadOnly) {
        $error = 'หน้านี้ปิดการแก้ไขข้อมูลชั่วคราว';
    } else {
    try {
        $tnt_idcard = preg_replace('/\D+/', '', trim($_POST['tnt_idcard'] ?? ''));
        $tnt_name = trim($_POST['tnt_name'] ?? '');
        $tnt_age_raw = trim($_POST['tnt_age'] ?? '');
        $tnt_education = trim($_POST['tnt_education'] ?? '');
        $tnt_faculty = trim($_POST['tnt_faculty'] ?? '');
        $tnt_year = trim($_POST['tnt_year'] ?? '');
        $tnt_phone = trim($_POST['tnt_phone'] ?? '');
        $tnt_address = trim($_POST['tnt_address'] ?? '');
        $tnt_vehicle = trim($_POST['tnt_vehicle'] ?? '');
        $tnt_parent = trim($_POST['tnt_parent'] ?? '');
        $tnt_parentsphone = preg_replace('/\D+/', '', trim($_POST['tnt_parentsphone'] ?? ''));

        if ($tnt_idcard !== '' && !preg_match('/^\d{13}$/', $tnt_idcard)) {
            throw new RuntimeException('เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก');
        }

        if ($tnt_name === '') {
            throw new RuntimeException('กรุณากรอกชื่อ-นามสกุล');
        }

        if (strlen($tnt_parentsphone) > 10) {
            throw new RuntimeException('เบอร์โทรผู้ปกครองต้องไม่เกิน 10 หลัก');
        }

        $tnt_age = null;
        if ($tnt_age_raw !== '') {
            if (!preg_match('/^\d+$/', $tnt_age_raw)) {
                throw new RuntimeException('อายุต้องเป็นตัวเลข');
            }
            $ageNum = (int)$tnt_age_raw;
            if ($ageNum < 0 || $ageNum > 120) {
                throw new RuntimeException('อายุต้องอยู่ระหว่าง 0 - 120 ปี');
            }
            $tnt_age = $ageNum;
        }
        
        $stmt = $pdo->prepare("
            UPDATE tenant SET 
                tnt_idcard = ?,
                tnt_name = ?,
                tnt_age = ?,
                tnt_education = ?,
                tnt_faculty = ?,
                tnt_year = ?,
                tnt_phone = ?,
                tnt_address = ?,
                tnt_vehicle = ?,
                tnt_parent = ?,
                tnt_parentsphone = ?
            WHERE tnt_id = ?
        ");
        $stmt->execute([
            $tnt_idcard === '' ? null : $tnt_idcard,
            $tnt_name,
            $tnt_age,
            $tnt_education,
            $tnt_faculty,
            $tnt_year,
            $tnt_phone,
            $tnt_address,
            $tnt_vehicle,
            $tnt_parent,
            $tnt_parentsphone,
            $contract['tnt_id']
        ]);
        
        $success = 'บันทึกข้อมูลเรียบร้อยแล้ว';
        
        // Refresh contract data
        $auth = checkTenantAuth();
        $contract = $auth['contract'];
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ข้อมูลส่วนตัว - <?php echo htmlspecialchars($settings['site_name']); ?></title>
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
        .header-title {
            font-size: 1.1rem;
            color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
        }
        .profile-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: white;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
        }
        .profile-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .profile-room {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .form-section {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: 0.9rem;
            color: #94a3b8;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .form-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-group textarea:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .btn-save {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .btn-save:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            filter: grayscale(20%);
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
        .nav-item {
            position: relative;
        }
        .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .section-icon svg {
            width: 16px;
            height: 16px;
            stroke: #94a3b8;
            stroke-width: 2;
            fill: none;
        }
        .profile-avatar svg {
            width: 48px;
            height: 48px;
            stroke: white;
            stroke-width: 1.5;
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
    </style>
    <?php if (($settings['public_theme'] ?? '') === 'light'): ?>
    <link rel="stylesheet" href="tenant-light-theme.css">
    <?php endif; ?>
</head>
<body class="<?= ($settings['public_theme'] ?? '') === 'light' ? 'light-theme' : '' ?>">
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title">ข้อมูลส่วนตัว</h1>
            <?php if (!empty($_SESSION['tenant_logged_in'])): ?>
            <div style="margin-left: auto; display: flex; gap: 0.5rem;">
                <a href="../index.php" style="padding: 0.5rem 1rem; background: rgba(99, 102, 241, 0.2); color: #818cf8; border-radius: 8px; text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                                    หน้าหลัก<?php if ($homeBadgeCount > 0): ?><span class="nav-badge">1</span><?php endif; ?>
            </a>            <a href="../tenant_logout.php" style="padding: 0.5rem 1rem; background: rgba(239, 68, 68, 0.2); color: #f87171; border-radius: 8px; text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    ออกจากระบบ
                </a>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <div class="container">
        <div class="profile-card">
            <div class="profile-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
            <div class="profile-name"><?php echo htmlspecialchars($contract['tnt_name']); ?></div>
            <div class="profile-room">ห้อง <?php echo htmlspecialchars($contract['room_number']); ?></div>
        </div>
        
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
        
        <div style="background: rgba(59, 130, 246, 0.05); border-left: 4px solid #3b82f6; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; color: #1d4ed8; font-weight: 600; margin-bottom: 8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                ทำไมต้องให้ข้อมูลเหล่านี้?
            </div>
            <ul style="margin: 0; padding-left: 20px; color: #475569; font-size: 0.9rem; line-height: 1.6; list-style-type: disc;">
                <li><strong>ข้อมูลพื้นฐาน:</strong> ใช้เป็นหลักฐานทางกฎหมายประกอบการทำสัญญาเช่า และยืนยันตัวตนเพื่อความปลอดภัยของส่วนรวม</li>
                <li><strong>เบอร์โทร & ที่อยู่:</strong> เพื่อใช้ติดต่อเรื่องบิลค่าเช่า แจ้งพัสดุมาถึง และส่งคืนเงินประกันห้องพักเมื่อย้ายออก</li>
                <li><strong>ทะเบียนรถ:</strong> สำหรับจัดการปัญหาเรื่องที่จอด หรือกรณีมีเหตุฉุกเฉินเกี่ยวกับรถยนต์ของผู้เช่า</li>
                <li><strong>ผู้ปกครอง:</strong> เป็นข้อมูลติดต่อฉุกเฉิน (Emergency Contact) หากเกิดสุดวิสัยหรือผู้เช่าเจ็บป่วยรุนแรงในหอพัก</li>
            </ul>
        </div>
        
        <form method="POST">
            <fieldset <?php echo $profileReadOnly ? 'disabled' : ''; ?> style="border:0; padding:0; margin:0; min-inline-size:auto;">
            <div class="form-section">
                <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> ข้อมูลพื้นฐาน</div>
                <div class="form-group">
                    <label>เลขบัตรประชาชน</label>
                    <input type="text" name="tnt_idcard" value="<?php echo htmlspecialchars((string)($contract['tnt_idcard'] ?? '')); ?>" placeholder="เลขบัตรประชาชน 13 หลัก" maxlength="13" inputmode="numeric" pattern="[0-9]{13}">
                </div>
                <div class="form-group">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="tnt_name" value="<?php echo htmlspecialchars((string)($contract['tnt_name'] ?? '')); ?>" placeholder="ชื่อ-นามสกุล">
                </div>
                <div class="form-group">
                    <label>อายุ</label>
                    <input type="number" name="tnt_age" value="<?php echo htmlspecialchars(isset($contract['tnt_age']) && $contract['tnt_age'] !== null ? (string)$contract['tnt_age'] : ''); ?>" placeholder="อายุ" min="0" max="120">
                </div>
                <div class="form-group">
                    <label>สถานศึกษา</label>
                    <input type="text" name="tnt_education" value="<?php echo htmlspecialchars((string)($contract['tnt_education'] ?? '')); ?>" placeholder="สถานศึกษา">
                </div>
                <div class="form-group">
                    <label>คณะ/สาขา</label>
                    <input type="text" name="tnt_faculty" value="<?php echo htmlspecialchars((string)($contract['tnt_faculty'] ?? '')); ?>" placeholder="คณะ/สาขา">
                </div>
                <div class="form-group">
                    <label>ชั้นปี</label>
                    <input type="text" name="tnt_year" value="<?php echo htmlspecialchars((string)($contract['tnt_year'] ?? '')); ?>" placeholder="ชั้นปี">
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span> ข้อมูลที่แก้ไขได้</div>
                <div class="form-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="tel" name="tnt_phone" value="<?php echo htmlspecialchars($contract['tnt_phone'] ?? ''); ?>" placeholder="0812345678">
                </div>
                <div class="form-group">
                    <label>ที่อยู่</label>
                    <textarea name="tnt_address" placeholder="ที่อยู่ปัจจุบัน"><?php echo htmlspecialchars($contract['tnt_address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>ทะเบียนรถ</label>
                    <input type="text" name="tnt_vehicle" value="<?php echo htmlspecialchars($contract['tnt_vehicle'] ?? ''); ?>" placeholder="กข 1234">
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span> ข้อมูลผู้ปกครอง</div>
                <div class="form-group">
                    <label>ชื่อผู้ปกครอง</label>
                    <input type="text" name="tnt_parent" value="<?php echo htmlspecialchars($contract['tnt_parent'] ?? ''); ?>" placeholder="ชื่อ-นามสกุล ผู้ปกครอง">
                </div>
                <div class="form-group">
                    <label>เบอร์โทรผู้ปกครอง</label>
                    <input type="tel" name="tnt_parentsphone" value="<?php echo htmlspecialchars($contract['tnt_parentsphone'] ?? ''); ?>" placeholder="0812345678" maxlength="10" inputmode="numeric" pattern="[0-9]{0,10}" oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);">
                </div>
            </div>
            </fieldset>

            <?php if (!$profileReadOnly): ?>
            <button type="submit" class="btn-save"><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg></span> บันทึกการแก้ไข</button>
            <?php endif; ?>
        </form>
    </div>
    
    <?php
    // นับรายการแจ้งซ่อมที่ยังไม่เสร็จ
    $repairCount = 0;
    try {
        $repairStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM repair
            WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE tnt_id = ?)
            AND repair_status = '0'
        ");
        $repairStmt->execute([$contract['tnt_id']]);
        $repairCount = (int)($repairStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    
    // นับรายการบิลที่ยังไม่ชำระ
    $homeBadgeCount = 0;
    try {
        $homeBadgeStmt = $pdo->prepare("
            SELECT 1 
            FROM contract c
            LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant'
            WHERE c.ctr_id = ? AND c.ctr_status != '1' AND sl.id IS NULL
              AND (
                  SELECT step_3_confirmed 
                  FROM tenant_workflow 
                  WHERE tnt_id = c.tnt_id 
                  ORDER BY id DESC LIMIT 1
              ) = 1
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
            AND EXISTS (
                    SELECT 1
                    FROM utility u
                    WHERE u.ctr_id = e.ctr_id
                        AND YEAR(u.utl_date) = YEAR(e.exp_month)
                        AND MONTH(u.utl_date) = MONTH(e.exp_month)
                        AND u.utl_water_end IS NOT NULL
                        AND u.utl_elec_end IS NOT NULL
                )
            AND (
                GREATEST(0, (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0)) - COALESCE((
                    SELECT SUM(p.pay_amount) FROM payment p
                    WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                    AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                ), 0))
                +
                GREATEST(0, (e.exp_total - (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0))) - COALESCE((
                    SELECT SUM(p.pay_amount) FROM payment p
                    WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                    AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ'
                ), 0))
            ) > 0
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
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                โปรไฟล์
            </a>
        </div>
    </nav>
</body>
</html>
