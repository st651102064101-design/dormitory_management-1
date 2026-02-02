<?php
/**
 * Tenant Check-in Details Page
 * แสดงข้อมูลการเช็คอินให้ผู้เช่าเห็น
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$token = $_GET['token'] ?? '';
if (!$token) {
    http_response_code(400);
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>Missing token</h1></div>');
}

try {
    // ดึงข้อมูลสัญญาและการเช็คอิน
    $stmt = $pdo->prepare("
        SELECT 
            c.ctr_id, c.ctr_start, c.ctr_end, c.access_token,
            t.tnt_name, t.tnt_phone,
            r.room_number,
            rt.type_name, rt.type_price,
            cr.checkin_id, cr.checkin_date, cr.water_meter_start, 
            cr.elec_meter_start, cr.room_images, cr.key_number, cr.notes
        FROM contract c
        JOIN tenant t ON c.tnt_id = t.tnt_id
        JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        LEFT JOIN checkin_record cr ON c.ctr_id = cr.ctr_id
        WHERE c.access_token = ? AND c.ctr_status IN ('0', '2')
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        http_response_code(403);
        die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>⚠️ ไม่พบข้อมูล</h1></div>');
    }

    // ดึงค่าตั้งค่าระบบ
    $siteName = 'Sangthian Dormitory';
    $logoFilename = 'Logo.jpg';
    try {
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
            if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        }
    } catch (PDOException $e) {}

} catch (PDOException $e) {
    http_response_code(500);
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h1>❌ เกิดข้อผิดพลาด</h1></div>');
}

$roomImages = [];
if (!empty($data['room_images'])) {
    $images = json_decode($data['room_images'], true);
    if (is_array($images)) {
        $roomImages = $images;
    }
}

$hasCheckIn = !empty($data['checkin_id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteName); ?> - ข้อมูลการเช็คอิน</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            padding-bottom: 20px;
        }
        
        .header {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .header-info h1 {
            font-size: 1rem;
            color: #f8fafc;
        }
        
        .header-info p {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .back-btn {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.5);
            color: #60a5fa;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(59, 130, 246, 0.3);
            border-color: rgba(59, 130, 246, 0.7);
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .room-info-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
        }
        
        .room-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .tenant-info {
            margin-top: 1rem;
            font-size: 0.95rem;
        }
        
        .tenant-info div {
            margin: 0.5rem 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .status-badge.no-checkin {
            background: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.5);
        }
        
        .status-badge.has-checkin {
            background: rgba(34, 197, 94, 0.3);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.5);
        }
        
        .section {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(59, 130, 246, 0.5);
        }
        
        .section-icon {
            width: 32px;
            height: 32px;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .section-icon svg {
            width: 18px;
            height: 18px;
            stroke: #60a5fa;
        }
        
        .meter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .meter-box {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(34, 211, 238, 0.3);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        
        .meter-label {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        
        .meter-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #06b6d4;
        }
        
        .meter-unit {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #f8fafc;
            font-weight: 500;
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .image-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 1;
            cursor: pointer;
            border: 1px solid rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        
        .image-item:hover {
            transform: scale(1.05);
            border-color: rgba(59, 130, 246, 0.8);
        }
        
        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-item svg {
            width: 100%;
            height: 100%;
            background: rgba(59, 130, 246, 0.2);
        }
        
        .notes-box {
            background: rgba(59, 130, 246, 0.15);
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.95rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #94a3b8;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            stroke: #475569;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: #0f172a;
            border-radius: 16px;
            padding: 1rem;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
            border: 1px solid rgba(255,255,255,0.1);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-image {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .date-contract {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .date-box {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(34, 211, 238, 0.3);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        
        .date-label {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        
        .date-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f8fafc;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="logo">
            <div class="header-info">
                <h1>ข้อมูลการเช็คอิน</h1>
                <p>Check-in Details</p>
            </div>
        </div>
        <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            ย้อนกลับ
        </a>
    </header>
    
    <div class="container">
        <!-- Room Info Card -->
        <div class="room-info-card">
            <div class="room-number">🏠 ห้อง <?php echo htmlspecialchars($data['room_number']); ?></div>
            <div class="tenant-info">
                <div>👤 <?php echo htmlspecialchars($data['tnt_name']); ?></div>
                <div>📱 <?php echo htmlspecialchars($data['tnt_phone']); ?></div>
                <div>💰 <?php echo htmlspecialchars($data['type_name'] ?? 'ไม่ระบุ'); ?> - <?php echo number_format((int)$data['type_price']); ?> บาท/เดือน</div>
                <span class="status-badge <?php echo $hasCheckIn ? 'has-checkin' : 'no-checkin'; ?>">
                    <?php echo $hasCheckIn ? '✅ เช็คอินแล้ว' : '⏳ ยังไม่เช็คอิน'; ?>
                </span>
            </div>
        </div>

        <!-- Contract Dates -->
        <div class="section">
            <div class="section-title">
                <div class="section-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                ช่วงสัญญา
            </div>
            <div class="date-contract">
                <div class="date-box">
                    <div class="date-label">📅 วันเริ่มต้น</div>
                    <div class="date-value"><?php echo date('d/m/Y', strtotime($data['ctr_start'])); ?></div>
                </div>
                <div class="date-box">
                    <div class="date-label">📅 วันสิ้นสุด</div>
                    <div class="date-value"><?php echo date('d/m/Y', strtotime($data['ctr_end'])); ?></div>
                </div>
            </div>
        </div>

        <?php if ($hasCheckIn): ?>
            <!-- Check-in Date -->
            <div class="section">
                <div class="section-title">
                    <div class="section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    วันที่เช็คอิน
                </div>
                <div class="info-row">
                    <span class="info-label">📆 วันเช็คอิน</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($data['checkin_date'])); ?></span>
                </div>
            </div>

            <!-- Meter Reading -->
            <div class="section">
                <div class="section-title">
                    <div class="section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="12" cy="12" r="4"/><path d="M9 9h.01"/></svg>
                    </div>
                    ค่าเริ่มต้นมิเตอร์
                </div>
                <div class="meter-grid">
                    <div class="meter-box">
                        <div class="meter-label">💧 มิเตอร์น้ำ</div>
                        <div class="meter-value"><?php echo number_format((float)$data['water_meter_start'], 2); ?></div>
                        <div class="meter-unit">หน่วย</div>
                    </div>
                    <div class="meter-box">
                        <div class="meter-label">⚡ มิเตอร์ไฟฟ้า</div>
                        <div class="meter-value"><?php echo number_format((float)$data['elec_meter_start'], 2); ?></div>
                        <div class="meter-unit">หน่วย</div>
                    </div>
                </div>
            </div>

            <!-- Key Number -->
            <?php if (!empty($data['key_number'])): ?>
            <div class="section">
                <div class="section-title">
                    <div class="section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><path d="M19.07 4.93a10 10 0 010 14.14M5.93 19.07a10 10 0 010-14.14"/></svg>
                    </div>
                    เลขกุญแจ
                </div>
                <div class="info-row">
                    <span class="info-label">🔑 หมายเลขกุญแจ</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['key_number']); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Room Images -->
            <?php if (!empty($roomImages)): ?>
            <div class="section">
                <div class="section-title">
                    <div class="section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                    รูปภาพห้องพัก
                </div>
                <div class="images-grid">
                    <?php foreach ($roomImages as $index => $image): ?>
                    <div class="image-item" onclick="openImageModal(this)">
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Room image <?php echo $index + 1; ?>" loading="lazy">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($data['notes'])): ?>
            <div class="section">
                <div class="section-title">
                    <div class="section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                    หมายเหตุ
                </div>
                <div class="notes-box">
                    <?php echo htmlspecialchars($data['notes']); ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="section">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v20M2 12h20"/><path d="M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                    </svg>
                    <h2 style="color:#f8fafc; font-size:1.2rem; margin-bottom:0.5rem;">⏳ ยังไม่มีข้อมูลการเช็คอิน</h2>
                    <p>กรุณารอให้ผู้ดูแลหอพักทำการเช็คอินห้องของคุณ</p>
                    <p style="font-size:0.8rem; margin-top:1rem; color:#64748b;">
                        เมื่อทำการเช็คอินเสร็จแล้ว ข้อมูลมิเตอร์น้ำ มิเตอร์ไฟฟ้า<br>
                        กุญแจ และรูปภาพห้องจะปรากฎที่นี่
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeImageModal()">&times;</button>
            <img id="modalImage" class="modal-image" src="" alt="">
        </div>
    </div>

    <script>
        function openImageModal(element) {
            const img = element.querySelector('img');
            if (img) {
                document.getElementById('modalImage').src = img.src;
                document.getElementById('imageModal').classList.add('active');
            }
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>
</html>
