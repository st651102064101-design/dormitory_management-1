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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tnt_phone = trim($_POST['tnt_phone'] ?? '');
        $tnt_address = trim($_POST['tnt_address'] ?? '');
        $tnt_vehicle = trim($_POST['tnt_vehicle'] ?? '');
        $tnt_parent = trim($_POST['tnt_parent'] ?? '');
        $tnt_parentsphone = trim($_POST['tnt_parentsphone'] ?? '');
        
        $stmt = $pdo->prepare("
            UPDATE tenant SET 
                tnt_phone = ?,
                tnt_address = ?,
                tnt_vehicle = ?,
                tnt_parent = ?,
                tnt_parentsphone = ?
            WHERE tnt_id = ?
        ");
        $stmt->execute([
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
        
    } catch (PDOException $e) {
        $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ข้อมูลส่วนตัว - <?php echo htmlspecialchars($settings['site_name']); ?></title>
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
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title">ข้อมูลส่วนตัว</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="profile-card">
            <div class="profile-avatar">👤</div>
            <div class="profile-name"><?php echo htmlspecialchars($contract['tnt_name']); ?></div>
            <div class="profile-room">ห้อง <?php echo htmlspecialchars($contract['room_number']); ?></div>
        </div>
        
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
        
        <form method="POST">
            <div class="form-section">
                <div class="section-title">📋 ข้อมูลพื้นฐาน (แก้ไขไม่ได้)</div>
                <div class="form-group">
                    <label>เลขบัตรประชาชน</label>
                    <input type="text" value="<?php echo htmlspecialchars($contract['tnt_id']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" value="<?php echo htmlspecialchars($contract['tnt_name']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>อายุ</label>
                    <input type="text" value="<?php echo htmlspecialchars((string)($contract['tnt_age'] ?? '-')); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>สถานศึกษา</label>
                    <input type="text" value="<?php echo htmlspecialchars($contract['tnt_education'] ?? '-'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>คณะ/สาขา</label>
                    <input type="text" value="<?php echo htmlspecialchars($contract['tnt_faculty'] ?? '-'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>ชั้นปี</label>
                    <input type="text" value="<?php echo htmlspecialchars($contract['tnt_year'] ?? '-'); ?>" disabled>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title">✏️ ข้อมูลที่แก้ไขได้</div>
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
                <div class="section-title">👨‍👩‍👧 ข้อมูลผู้ปกครอง</div>
                <div class="form-group">
                    <label>ชื่อผู้ปกครอง</label>
                    <input type="text" name="tnt_parent" value="<?php echo htmlspecialchars($contract['tnt_parent'] ?? ''); ?>" placeholder="ชื่อ-นามสกุล ผู้ปกครอง">
                </div>
                <div class="form-group">
                    <label>เบอร์โทรผู้ปกครอง</label>
                    <input type="tel" name="tnt_parentsphone" value="<?php echo htmlspecialchars($contract['tnt_parentsphone'] ?? ''); ?>" placeholder="0812345678">
                </div>
            </div>
            
            <button type="submit" class="btn-save">💾 บันทึกการแก้ไข</button>
        </form>
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
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
                <div class="nav-icon">👤</div>
                โปรไฟล์
            </a>
        </div>
    </nav>
</body>
</html>
