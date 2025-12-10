<?php
/**
 * Admin - สร้าง QR Code สำหรับผู้เช่า
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../config.php';
$pdo = connectDB();

// ดึงรายการสัญญาที่ยังใช้งานอยู่
$contracts = [];
try {
    // ตรวจสอบว่ามีคอลัมน์ access_token หรือยัง
    $checkColumn = $pdo->query("SHOW COLUMNS FROM contract LIKE 'access_token'");
    $hasColumn = $checkColumn->fetch();
    
    if (!$hasColumn) {
        // เพิ่มคอลัมน์ access_token
        $pdo->exec("ALTER TABLE `contract` ADD COLUMN `access_token` VARCHAR(64) DEFAULT NULL COMMENT 'Token สำหรับเข้าถึง Tenant Portal' AFTER `room_id`");
        $pdo->exec("ALTER TABLE `contract` ADD UNIQUE KEY `access_token_unique` (`access_token`)");
    }
    
    // สร้าง token สำหรับสัญญาที่ยังไม่มี
    $pdo->exec("UPDATE `contract` SET `access_token` = MD5(CONCAT(ctr_id, '-', tnt_id, '-', room_id, '-', NOW(), '-', RAND())) WHERE `access_token` IS NULL AND `ctr_status` IN ('0', '2')");
    
    $stmt = $pdo->query("
        SELECT c.*, 
               t.tnt_name, t.tnt_phone,
               r.room_number,
               rt.type_name
        FROM contract c
        JOIN tenant t ON c.tnt_id = t.tnt_id
        JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE c.ctr_status IN ('0', '2')
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
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

// ใช้ค่าจาก config.php
$baseUrl = getTenantPortalUrl();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้าง QR Code ผู้เช่า - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <link rel="stylesheet" href="../Assets/Css/main.css">
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css">
    <style>
        .manage-panel {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--card-bg);
            border-radius: 12px;
        }
        .page-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .qr-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
        }
        .room-badge {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .tenant-name {
            font-size: 1rem;
            color: #f8fafc;
            margin-bottom: 0.5rem;
        }
        .tenant-phone {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }
        .qr-container {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .qr-container canvas {
            display: block;
        }
        .btn-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .btn-download, .btn-print {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-download {
            background: #10b981;
            color: white;
        }
        .btn-download:hover {
            background: #059669;
        }
        .btn-print {
            background: #3b82f6;
            color: white;
        }
        .btn-print:hover {
            background: #2563eb;
        }
        .btn-print-all {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 1.5rem;
            transition: all 0.2s;
        }
        .btn-print-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .manage-panel { margin: 0; padding: 0; background: white !important; }
            .qr-card { 
                break-inside: avoid; 
                page-break-inside: avoid;
                border: 1px solid #ccc !important;
                margin-bottom: 1rem;
            }
            .qr-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
    </style>
</head>
<body class="reports-page">
    <div class="app-shell">
        <?php 
        $currentPage = 'qr_codes.php';
        include __DIR__ . '/../includes/sidebar.php'; 
        ?>
        <div class="app-main">
            <main>
                <div class="manage-panel">
                    <h1 class="page-title">📱 สร้าง QR Code สำหรับผู้เช่า</h1>
                    
                    <?php if (!empty($contracts)): ?>
                    <button class="btn-print-all no-print" onclick="window.print()">🖨️ พิมพ์ QR Code ทั้งหมด</button>
                    
                    <div class="qr-grid">
                        <?php foreach ($contracts as $contract): ?>
                        <?php 
                            $tenantUrl = $baseUrl . '?token=' . urlencode($contract['access_token']);
                            $qrId = 'qr_' . $contract['ctr_id'];
                            // ใช้ PHP QR Code generator
                            $qrImageUrl = '../qr_generate.php?data=' . urlencode($tenantUrl);
                        ?>
                        <div class="qr-card">
                            <div class="room-badge">ห้อง <?php echo htmlspecialchars($contract['room_number']); ?></div>
                            <div class="tenant-name">👤 <?php echo htmlspecialchars($contract['tnt_name']); ?></div>
                            <div class="tenant-phone">📱 <?php echo htmlspecialchars($contract['tnt_phone'] ?? '-'); ?></div>
                            <div class="qr-container">
                                <img id="<?php echo $qrId; ?>" src="<?php echo $qrImageUrl; ?>" alt="QR Code ห้อง <?php echo htmlspecialchars($contract['room_number']); ?>" style="width: 180px; height: 180px;">
                            </div>
                            <div class="btn-actions no-print">
                                <button class="btn-download" onclick="downloadQR('<?php echo $qrImageUrl; ?>', '<?php echo $contract['room_number']; ?>')">💾 ดาวน์โหลด</button>
                                <button class="btn-print" onclick="printSingleQR('<?php echo $qrImageUrl; ?>', '<?php echo htmlspecialchars($contract['room_number']); ?>', '<?php echo htmlspecialchars(addslashes($contract['tnt_name'])); ?>')">🖨️ พิมพ์</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>ไม่มีสัญญาที่ใช้งานอยู่</p>
                        <p style="font-size: 0.85rem; margin-top: 0.5rem;">กรุณาเพิ่มสัญญาเช่าก่อนสร้าง QR Code</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
    function downloadQR(imageUrl, roomNumber) {
        // สร้าง link ดาวน์โหลด
        const link = document.createElement('a');
        link.download = 'QR_Room_' + roomNumber + '.png';
        link.href = imageUrl;
        link.target = '_blank';
        link.click();
    }
    
    function printSingleQR(imageUrl, roomNumber, tenantName) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>QR Code ห้อง ${roomNumber}</title>
                <style>
                    body {
                        font-family: 'Sarabun', sans-serif;
                        text-align: center;
                        padding: 2rem;
                    }
                    .qr-print-card {
                        border: 2px solid #000;
                        padding: 1.5rem;
                        display: inline-block;
                        border-radius: 12px;
                    }
                    .room-number {
                        font-size: 2rem;
                        font-weight: bold;
                        margin-bottom: 0.5rem;
                    }
                    .tenant-name {
                        font-size: 1.2rem;
                        margin-bottom: 1rem;
                    }
                    .qr-image {
                        width: 200px;
                        height: 200px;
                    }
                    .instructions {
                        margin-top: 1rem;
                        font-size: 0.9rem;
                        color: #666;
                    }
                </style>
            </head>
            <body>
                <div class="qr-print-card">
                    <div class="room-number">🏠 ห้อง ${roomNumber}</div>
                    <div class="tenant-name">${tenantName}</div>
                    <img src="${imageUrl}" class="qr-image" alt="QR Code">
                    <div class="instructions">สแกน QR Code เพื่อเข้าสู่ระบบผู้เช่า</div>
                </div>
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
    </script>
</body>
</html>