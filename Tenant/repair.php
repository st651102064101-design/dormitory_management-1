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
$hasScheduleColumns = false;
try {
    // Check if schedule columns exist
    $checkCol = $pdo->query("SHOW COLUMNS FROM repair LIKE 'scheduled_date'");
    $hasScheduleColumns = $checkCol->rowCount() > 0;
    
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
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
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
        #preview-container { display: none; margin-top: 0.5rem; }
        #preview-container img { max-width: 100%; max-height: 150px; border-radius: 8px; }
        
        /* Schedule Info Styles */
        .schedule-info {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1));
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 10px;
            padding: 0.75rem;
            margin-top: 0.75rem;
        }
        .schedule-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #a78bfa;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .schedule-header svg {
            width: 16px;
            height: 16px;
            stroke: #a78bfa;
        }
        .schedule-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #e2e8f0;
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
        }
        .schedule-row:last-child {
            margin-bottom: 0;
        }
        .schedule-row svg {
            width: 14px;
            height: 14px;
            stroke: rgba(255,255,255,0.5);
            flex-shrink: 0;
        }
        .schedule-label {
            color: rgba(255,255,255,0.5);
            min-width: 60px;
        }
        .schedule-value {
            font-weight: 500;
        }
        .schedule-note {
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.7);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span> แจ้งซ่อม</h1>
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
        
        <div class="form-section">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span> แจ้งซ่อมใหม่</div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>รายละเอียดอุปกรณ์ที่ต้องการซ่อม *</label>
                    <textarea name="repair_desc" placeholder="เช่น พัดลมเพดานไม่หมุน, ก๊อกน้ำรั่ว, หลอดไฟเสีย ฯลฯ" required></textarea>
                </div>
                <div class="form-group">
                    <label>รูปภาพประกอบ (ถ้ามี)</label>
                    <div class="file-upload" onclick="document.getElementById('repair_image').click()">
                        <input type="file" name="repair_image" id="repair_image" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                        <div class="file-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                        <div class="file-upload-text">แตะเพื่อเลือกรูปภาพ</div>
                    </div>
                    <div id="preview-container">
                        <img id="preview-image" src="" alt="Preview">
                    </div>
                </div>
                <button type="submit" class="btn-submit"><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></span> ส่งแจ้งซ่อม</button>
            </form>
        </div>
        
        <div class="repair-history">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> ประวัติการแจ้งซ่อม</div>
            
            <?php if (empty($repairs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"/><path d="M5.5 5.1L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/></svg></div>
                <p>ยังไม่มีประวัติการแจ้งซ่อม</p>
            </div>
            <?php else: ?>
            <?php foreach ($repairs as $repair): ?>
            <?php 
                $hasSchedule = $hasScheduleColumns && !empty($repair['scheduled_date']);
                $scheduledDate = $repair['scheduled_date'] ?? '';
                $scheduledTimeStart = $repair['scheduled_time_start'] ?? '';
                $scheduledTimeEnd = $repair['scheduled_time_end'] ?? '';
                $technicianName = $repair['technician_name'] ?? '';
                $technicianPhone = $repair['technician_phone'] ?? '';
                $scheduleNote = $repair['schedule_note'] ?? '';
                
                // Format date for display
                $formattedDate = '';
                if ($scheduledDate) {
                    $dt = new DateTime($scheduledDate);
                    $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                    $formattedDate = (int)$dt->format('j') . ' ' . $thaiMonths[(int)$dt->format('n')] . ' ' . ((int)$dt->format('Y') + 543);
                }
                
                // Format time range
                $timeRange = '';
                if ($scheduledTimeStart && $scheduledTimeEnd) {
                    $timeRange = substr($scheduledTimeStart, 0, 5) . ' - ' . substr($scheduledTimeEnd, 0, 5) . ' น.';
                } elseif ($scheduledTimeStart) {
                    $timeRange = substr($scheduledTimeStart, 0, 5) . ' น.';
                }
            ?>
            <div class="repair-item">
                <div class="repair-header">
                    <div class="repair-date">
                        <span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> <?php echo $repair['repair_date'] ?? '-'; ?>
                        <?php if ($repair['repair_time']): ?>
                        <span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> <?php echo substr($repair['repair_time'], 0, 5); ?>
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
                
                <?php if ($hasSchedule): ?>
                <div class="schedule-info">
                    <div class="schedule-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        นัดหมายซ่อม
                    </div>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span class="schedule-label">วันที่</span>
                        <span class="schedule-value"><?php echo htmlspecialchars($formattedDate); ?></span>
                    </div>
                    <?php if ($timeRange): ?>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span class="schedule-label">เวลา</span>
                        <span class="schedule-value"><?php echo htmlspecialchars($timeRange); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($technicianName): ?>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="schedule-label">ช่าง</span>
                        <span class="schedule-value"><?php echo htmlspecialchars($technicianName); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($technicianPhone): ?>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span class="schedule-label">โทร</span>
                        <span class="schedule-value"><a href="tel:<?php echo htmlspecialchars($technicianPhone); ?>" style="color:#a78bfa; text-decoration:none;"><?php echo htmlspecialchars($technicianPhone); ?></a></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($scheduleNote): ?>
                    <div class="schedule-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; width: 1.2em; height: 1.2em; margin-right: 0.5em; vertical-align: -0.15em;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg> <?php echo htmlspecialchars($scheduleNote); ?>
                    </div>
                    <?php endif; ?>
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
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                หน้าหลัก
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                บิล
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
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
