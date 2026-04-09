<?php
/**
 * หน้าทดสอบ QR Code Generator
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบ QR Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            color: #fff;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #3b82f6;
        }
        .test-section {
            background: #1e293b;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .test-section h2 {
            margin-top: 0;
            color: #60a5fa;
        }
        .qr-box {
            background: #fff;
            padding: 1rem;
            border-radius: 8px;
            display: inline-block;
            margin: 1rem 0;
        }
        .qr-box img {
            display: block;
        }
        .status {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            display: inline-block;
            margin: 0.5rem 0;
        }
        .status.success {
            background: #22c55e;
            color: #fff;
        }
        .status.error {
            background: #ef4444;
            color: #fff;
        }
        .code {
            background: #334155;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-family: monospace;
            word-break: break-all;
        }
        a {
            color: #60a5fa;
        }
        .test-link {
            display: inline-block;
            background: #3b82f6;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 1rem;
        }
        .test-link:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <h1>🔍 ทดสอบ QR Code Generator</h1>

    <?php
    // Test 1: ตรวจสอบว่าไฟล์ phpqrcode.php มีอยู่
    $phpqrcodeExists = file_exists(__DIR__ . '/phpqrcode.php');
    ?>
    
    <div class="test-section">
        <h2>1. ตรวจสอบไฟล์ phpqrcode.php</h2>
        <?php if ($phpqrcodeExists): ?>
            <div class="status success">✅ พบไฟล์ phpqrcode.php</div>
            <p>ขนาดไฟล์: <?php echo number_format(filesize(__DIR__ . '/phpqrcode.php')); ?> bytes</p>
        <?php else: ?>
            <div class="status error">❌ ไม่พบไฟล์ phpqrcode.php</div>
            <p>กรุณาดาวน์โหลดไฟล์ phpqrcode.php ไปไว้ที่ root ของโปรเจค</p>
        <?php endif; ?>
    </div>

    <?php
    // Test 2: ตรวจสอบว่าไฟล์ qr_generate.php มีอยู่
    $qrGenerateExists = file_exists(__DIR__ . '/qr_generate.php');
    ?>
    
    <div class="test-section">
        <h2>2. ตรวจสอบไฟล์ qr_generate.php</h2>
        <?php if ($qrGenerateExists): ?>
            <div class="status success">✅ พบไฟล์ qr_generate.php</div>
        <?php else: ?>
            <div class="status error">❌ ไม่พบไฟล์ qr_generate.php</div>
        <?php endif; ?>
    </div>

    <div class="test-section">
        <h2>3. ทดสอบสร้าง QR Code</h2>
        <?php
        $testUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/Tenant/?token=TEST123';
        $qrApiUrl = 'qr_generate.php?data=' . urlencode($testUrl);
        ?>
        <p>URL ทดสอบ:</p>
        <div class="code"><?php echo htmlspecialchars($testUrl); ?></div>
        
        <p>QR Generator URL:</p>
        <div class="code"><?php echo htmlspecialchars($qrApiUrl); ?></div>
        
        <p>ผลลัพธ์ QR Code:</p>
        <div class="qr-box">
            <img src="<?php echo $qrApiUrl; ?>" alt="Test QR Code" width="200" height="200" 
                 onerror="this.parentElement.innerHTML='<span style=color:red>❌ ไม่สามารถโหลด QR Code ได้</span>'">
        </div>
        
        <p><small>ถ้าเห็น QR Code ด้านบน แสดงว่าระบบทำงานปกติ!</small></p>
    </div>

    <div class="test-section">
        <h2>4. ทดสอบ Tenant Portal</h2>
        <p>คลิกลิงก์ด้านล่างเพื่อทดสอบหน้า Tenant Portal (จะแสดง error เพราะ token ไม่ถูกต้อง ซึ่งถือว่าปกติ):</p>
        <a href="Tenant/?token=TEST123" target="_blank" class="test-link">🔗 เปิดหน้า Tenant Portal</a>
    </div>

    <div class="test-section">
        <h2>5. ทดสอบกับข้อมูลจริง</h2>
        <?php
        require_once __DIR__ . '/ConnectDB.php';
        $dbError = null;
        $contracts = [];
        try {
            $pdo = connectDB();
            $stmt = $pdo->query("SELECT c.ctr_id, c.access_token, t.tnt_name, r.room_number 
                                 FROM contract c 
                                 JOIN tenant t ON c.tnt_id = t.tnt_id 
                                 JOIN room r ON c.room_id = r.room_id 
                                 WHERE c.ctr_status = 'active' AND c.access_token IS NOT NULL AND c.access_token != ''
                                 LIMIT 3");
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }
        
        if ($dbError): ?>
            <div class="status error">❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้</div>
            <p><?php echo htmlspecialchars($dbError); ?></p>
        <?php elseif (count($contracts) > 0): ?>
                <div class="status success">✅ พบ <?php echo count($contracts); ?> สัญญาที่มี access_token</div>
                <table style="width:100%; margin-top:1rem; border-collapse:collapse;">
                    <tr style="background:#334155;">
                        <th style="padding:0.75rem; text-align:left;">ห้อง</th>
                        <th style="padding:0.75rem; text-align:left;">ผู้เช่า</th>
                        <th style="padding:0.75rem; text-align:left;">QR Code</th>
                        <th style="padding:0.75rem; text-align:left;">ทดสอบ</th>
                    </tr>
                    <?php foreach ($contracts as $c): 
                        $tenantUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/Tenant/?token=' . urlencode($c['access_token']);
                        $qrUrl = 'qr_generate.php?data=' . urlencode($tenantUrl);
                    ?>
                    <tr style="border-bottom:1px solid #334155;">
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($c['room_number']); ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($c['tnt_name']); ?></td>
                        <td style="padding:0.75rem;">
                            <div style="background:#fff; padding:5px; display:inline-block; border-radius:4px;">
                                <img src="<?php echo $qrUrl; ?>" width="80" height="80">
                            </div>
                        </td>
                        <td style="padding:0.75rem;">
                            <a href="<?php echo htmlspecialchars($tenantUrl); ?>" target="_blank">เปิด Portal</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div class="status error">⚠️ ไม่พบสัญญาที่มี access_token</div>
                <p>ต้องสร้าง access_token ก่อน โดยไปที่หน้า <a href="Reports/qr_codes.php">QR Code ผู้เช่า</a></p>
            <?php endif; ?>
    </div>

    <div class="test-section">
        <h2>📋 สรุป</h2>
        <ul>
            <li>✅ ถ้าเห็น QR Code ในส่วนที่ 3 = ระบบสร้าง QR ได้</li>
            <li>✅ ถ้าคลิกลิงก์ในส่วนที่ 4 แล้วเห็นหน้า error "Token ไม่ถูกต้อง" = Tenant Portal ทำงาน</li>
            <li>✅ ถ้าส่วนที่ 5 แสดง QR และกดเปิด Portal ได้ = ระบบพร้อมใช้งานจริง!</li>
        </ul>
        <p><a href="Reports/qr_codes.php" class="test-link">🎯 ไปหน้า QR Code ผู้เช่า</a></p>
    </div>

</body>
</html>
