<?php
session_start();

// Check authorization
if (empty($_SESSION['admin_username'])) {
    die('Unauthorized - admin only');
}

// Fetch duplicate info
$host = '127.0.0.1';
$db = 'dormitory_mgt';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $findDupesStmt = $pdo->query(
        "SELECT tnt_id, room_id, COUNT(*) as cnt, GROUP_CONCAT(ctr_id ORDER BY ctr_id) as ctr_ids
         FROM contract
         WHERE ctr_status IN ('0','2')
         GROUP BY tnt_id, room_id
         HAVING cnt > 1
         ORDER BY cnt DESC"
    );
    $duplicates = $findDupesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Up Duplicate Contracts</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/Logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .duplicate-item { padding: 15px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px; background: #fff9e6; }
        .btn-lg { padding: 10px 30px; }
        .duplicate-item.warning { background: #ffe6e6; border-color: #ff9999; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 ลบสัญญาซ้ำ</h1>
        <p class="text-muted">พบสัญญาซ้ำ <?php echo count($duplicates); ?> ชุด</p>
        
        <?php if (empty($duplicates)): ?>
            <div class="alert alert-success">
                ✅ ไม่มีสัญญาซ้ำ - ระบบปกติ
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                ⚠️ พบสัญญาซ้ำ - ระบบจะลบสัญญาที่ตัวเลขมากกว่า (เก็บสัญญาเลขน้อยสุด)
            </div>
            
            <div style="max-height: 400px; overflow-y: auto; margin-bottom: 20px;">
                <?php foreach ($duplicates as $dup): ?>
                    <?php
                    $ctrIds = explode(',', $dup['ctr_ids']);
                    sort($ctrIds);
                    $keepId = $ctrIds[0];
                    $deleteIds = array_slice($ctrIds, 1);
                    ?>
                    <div class="duplicate-item">
                        <strong>ผู้เช่า ID: <?php echo $dup['tnt_id']; ?> | ห้อง ID: <?php echo $dup['room_id']; ?></strong>
                        <br>
                        📋 สัญญา: <?php echo implode(', ', $ctrIds); ?>
                        <br>
                        <span class="badge bg-success">เก็บไว้: <?php echo $keepId; ?></span>
                        <span class="badge bg-danger">ลบ: <?php echo implode(', ', $deleteIds); ?></span>
                        <p class="text-muted small" style="margin-top: 8px;">
                            จำนวนรวม: <?php echo $dup['cnt']; ?> สัญญา
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <hr>
            
            <div class="alert alert-info">
                <strong>⚠️ ระวัง</strong> - การลบสัญญาซ้ำจะ:
                <ul>
                    <li>✅ ลบสัญญาที่มีเลขเก่ากว่า (เหลือเพียง 1 สัญญา per ผู้เช่า+ห้อง)</li>
                    <li>✅ ข้ามการลบถ้าสัญญามี payment/utility/expense</li>
                    <li>❌ NOT ทำการ rollback ห้อง/ผู้เช่า status (ต้องทำเอง)</li>
                </ul>
            </div>
            
            <button id="cleanupBtn" class="btn btn-danger btn-lg">
                🗑️ ยืนยัน: ลบสัญญาซ้ำทั้งหมด
            </button>
            
            <div id="result" style="margin-top: 20px;"></div>
        <?php endif; ?>
    </div>

    <script>
        const cleanupBtn = document.getElementById('cleanupBtn');
        const resultDiv = document.getElementById('result');
        
        if (cleanupBtn) {
            cleanupBtn.addEventListener('click', async () => {
                if (!confirm('⚠️ ยืนยันการลบสัญญาซ้ำ? การกระทำนี้ไม่สามารถ undo')) {
                    return;
                }
                
                cleanupBtn.disabled = true;
                cleanupBtn.textContent = '⏳ กำลังประมวลผล...';
                
                try {
                    const form = new FormData();
                    form.append('confirm', '1');
                    
                    const res = await fetch('../Manage/cleanup_duplicate_contracts.php', {
                        method: 'POST',
                        body: form
                    });
                    
                    const data = await res.json();
                    
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-success">
                                ✅ <strong>สำเร็จ!</strong><br>
                                ลบสัญญาซ้ำ ${data.deleted_count} รายการ<br>
                                <small>${data.message}</small>
                            </div>
                            <button onclick="location.reload()" class="btn btn-primary">
                                🔄 รีเฟรช
                            </button>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <div class="alert alert-danger">
                                ❌ <strong>ผิดพลาด</strong><br>
                                ${data.error || data.message}
                            </div>
                        `;
                    }
                } catch (error) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            ❌ <strong>Error</strong><br>
                            ${error.message}
                        </div>
                    `;
                }
                
                cleanupBtn.disabled = false;
                cleanupBtn.textContent = '🗑️ ยืนยัน: ลบสัญญาซ้ำทั้งหมด';
            });
        }
    </script>
</body>
</html>
