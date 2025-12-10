<?php
/**
 * Tenant Repair - แจ้งซ่อมอุปกรณ์ภายในห้อง
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
        $repair_desc = trim($_POST['repair_desc'] ?? '');
        
        if (empty($repair_desc)) {
            $error = 'กรุณาระบุรายละเอียดการแจ้งซ่อม';
        } else {
            $repair_image = null;
            
            // Handle image upload
            if (!empty($_FILES['repair_image']['name'])) {
                $file = $_FILES['repair_image'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if ($file['size'] > $maxFileSize) {
                    throw new Exception('ไฟล์รูปภาพใหญ่เกินไป (ไม่เกิน 5MB)');
                }
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mimeType, $allowedMimes)) {
                    throw new Exception('ประเภทไฟล์ไม่ถูกต้อง (สนับสนุน JPG, PNG, WebP)');
                }
                
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    throw new Exception('นามสกุลไฟล์ไม่ถูกต้อง');
                }
                
                $uploadsDir = __DIR__ . '/../Assets/Images/Repairs';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                
                $filename = 'repair_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $targetPath = $uploadsDir . '/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $repair_image = $filename;
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO repair (repair_desc, repair_date, repair_time, repair_status, repair_image, ctr_id)
                VALUES (?, NOW(), CURTIME(), '0', ?, ?)
            ");
            $stmt->execute([$repair_desc, $repair_image, $contract['ctr_id']]);
            
            $success = 'แจ้งซ่อมเรียบร้อยแล้ว';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get repair history
$repairs = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM repair WHERE ctr_id = ? ORDER BY repair_date DESC, repair_time DESC
    ");
    $stmt->execute([$contract['ctr_id']]);
    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$repairStatusMap = [
    '0' => ['label' => 'รอซ่อม', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.2)'],
    '1' => ['label' => 'กำลังซ่อม', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.2)'],
    '2' => ['label' => 'ซ่อมเสร็จ', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แจ้งซ่อม - <?php echo htmlspecialchars($settings['site_name']); ?></title>
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
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
        }
        .form-group textarea:focus {
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
        .file-upload:hover {
            border-color: #3b82f6;
        }
        .file-upload input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .file-upload-text {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .btn-submit {
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
        .btn-submit:hover {
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
        .repair-history {
            margin-top: 2rem;
        }
        .repair-item {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .repair-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        .repair-date {
            font-size: 0.75rem;
            color: #64748b;
        }
        .repair-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .repair-desc {
            font-size: 0.9rem;
            color: #e2e8f0;
            line-height: 1.5;
        }
        .repair-image {
            margin-top: 0.75rem;
            border-radius: 8px;
            overflow: hidden;
        }
        .repair-image img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
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
        #preview-container { display: none; margin-top: 0.5rem; }
        #preview-container img { max-width: 100%; max-height: 150px; border-radius: 8px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title">🔧 แจ้งซ่อม</h1>
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
            <div class="section-title">📝 แจ้งซ่อมใหม่</div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>รายละเอียดอุปกรณ์ที่ต้องการซ่อม *</label>
                    <textarea name="repair_desc" placeholder="เช่น พัดลมเพดานไม่หมุน, ก๊อกน้ำรั่ว, หลอดไฟเสีย ฯลฯ" required></textarea>
                </div>
                <div class="form-group">
                    <label>รูปภาพประกอบ (ถ้ามี)</label>
                    <div class="file-upload" onclick="document.getElementById('repair_image').click()">
                        <input type="file" name="repair_image" id="repair_image" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                        <div class="file-upload-icon">📷</div>
                        <div class="file-upload-text">แตะเพื่อเลือกรูปภาพ</div>
                    </div>
                    <div id="preview-container">
                        <img id="preview-image" src="" alt="Preview">
                    </div>
                </div>
                <button type="submit" class="btn-submit">📤 ส่งแจ้งซ่อม</button>
            </form>
        </div>
        
        <div class="repair-history">
            <div class="section-title">📋 ประวัติการแจ้งซ่อม</div>
            
            <?php if (empty($repairs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>ยังไม่มีประวัติการแจ้งซ่อม</p>
            </div>
            <?php else: ?>
            <?php foreach ($repairs as $repair): ?>
            <div class="repair-item">
                <div class="repair-header">
                    <div class="repair-date">
                        📅 <?php echo $repair['repair_date'] ?? '-'; ?>
                        <?php if ($repair['repair_time']): ?>
                        ⏰ <?php echo substr($repair['repair_time'], 0, 5); ?>
                        <?php endif; ?>
                    </div>
                    <span class="repair-status" style="background: <?php echo $repairStatusMap[$repair['repair_status'] ?? '0']['bg']; ?>; color: <?php echo $repairStatusMap[$repair['repair_status'] ?? '0']['color']; ?>">
                        <?php echo $repairStatusMap[$repair['repair_status'] ?? '0']['label']; ?>
                    </span>
                </div>
                <div class="repair-desc"><?php echo htmlspecialchars($repair['repair_desc'] ?? '-'); ?></div>
                <?php if (!empty($repair['repair_image'])): ?>
                <div class="repair-image">
                    <img src="../Assets/Images/Repairs/<?php echo htmlspecialchars($repair['repair_image']); ?>" alt="Repair Image">
                </div>
                <?php endif; ?>
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
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
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
    }
    </script>
</body>
</html>
