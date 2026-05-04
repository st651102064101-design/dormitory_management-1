<?php
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php'; // For formatting thai date if needed
session_start();

if (!isset($_SESSION['tenant_token']) && empty($_SESSION['tenant_logged_in'])) {
    header("Location: /dormitory_management/");
    exit;
}

$pdo = connectDB();

// ดึงข้อมูลสัญญาปัจจุบันของผู้ใช้
$ctr_id = $_SESSION['tenant_ctr_id'] ?? null;
if (!$ctr_id) {
    die("ไม่พบข้อมูลสัญญา");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM contract WHERE ctr_id = ? LIMIT 1");
    $stmt->execute([$ctr_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        die("ไม่พบข้อมูลสัญญา");
    }

    // ถ้าสัญญาถูกยกเลิก (1) ตรวจสอบว่ามีผู้เช่าใหม่หรือไม่
    if ($contract['ctr_status'] == '1') {
        $checkOccupiedStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM contract 
            WHERE room_id = ? 
              AND ctr_status IN ('0', '2') 
              AND ctr_id != ?
        ");
        $checkOccupiedStmt->execute([$contract['room_id'], $contract['ctr_id']]);
        $hasNewTenant = (int)$checkOccupiedStmt->fetchColumn() > 0;

        if ($hasNewTenant) {
            die('
            <!DOCTYPE html>
            <html lang="th">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>ไม่สามารถเข้าถึงได้</title>
                <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
                <style>
                    :root {
                      --font-apple: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", sans-serif;
                    }

                    /* Typography Foundation */
                    * {
                      font-family: var(--font-apple);
                      -webkit-font-smoothing: antialiased;
                      -moz-osx-font-smoothing: grayscale;
                    }

                    body { font-family: var(--font-apple), sans-serif; background: #f8fafc; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; text-align: center; }
                    .card { background: white; padding: 40px 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 400px; width: 100%; border-top: 4px solid #ef4444; }
                    .icon { font-size: 48px; margin-bottom: 20px; color: #ef4444; }
                    h1 { color: #1e293b; font-size: 20px; margin-bottom: 10px; }
                    p { color: #64748b; font-size: 14px; margin-bottom: 30px; line-height: 1.6; }
                    .btn { display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; margin-top:20px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="icon">🚫</div>
                    <h1>ไม่สามารถทำรายการได้</h1>
                    <p>สัญญาเช่าของคุณสิ้นสุดแล้ว และมีผู้เช่ารายใหม่เข้าพักในห้องนี้แล้ว คุณไม่สามารถเข้าถึงหรือดูข้อมูลของห้องนี้ได้อีกเพื่อเป็นการรักษาความเป็นส่วนตัวของผู้เช่าปัจจุบัน</p>
                    <a href="index.php" class="btn">กลับหน้าหลัก</a>
                </div>
            </body>
            </html>
            ');
        }
    }
    
    $success = false;
    $error = '';
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew') {
        $months = (int)($_POST['duration_months'] ?? 12);
        if (!in_array($months, [6, 12, 24])) {
            $months = 12;
        }
        
        $currentEnd = $contract['ctr_end'] ?: date('Y-m-d');
        // Calculate new end date properly
        $newEnd = date('Y-m-d', strtotime("+{$months} months", strtotime($currentEnd)));
        
        // Update database
        $updateStmt = $pdo->prepare("UPDATE contract SET ctr_end = ?, ctr_status = '0' WHERE ctr_id = ?");
        if($updateStmt->execute([$newEnd, $contract['ctr_id']])) {
            $success = true;
            // Update local object to reflect changes immediately
            $contract['ctr_end'] = $newEnd;
            $contract['ctr_status'] = '0';
        } else {
            $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
        }
    }

} catch (PDOException $e) {
    error_log("Error in renew_contract.php: " . $e->getMessage());
    die("เกิดข้อผิดพลาดของระบบ");
}

$currentEndDate = $contract['ctr_end'] ?: date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ต่อสัญญาเช่า</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body { 
            font-family: 'Prompt', sans-serif; 
            background: var(--bg-color); 
            margin: 0; 
            padding: 20px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
        }
        .container {
            background: var(--card-bg); 
            padding: 40px 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            max-width: 450px; 
            width: 100%;
            border-top: 4px solid var(--primary);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header .icon {
            font-size: 40px;
            margin-bottom: 10px;
            display: inline-block;
        }
        h1 { color: var(--text-main); font-size: 22px; margin: 0 0 10px 0; }
        .subtitle { color: var(--text-muted); font-size: 14px; margin: 0; }
        
        .info-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14.5px;
        }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { color: var(--text-muted); }
        .info-val { font-weight: 500; color: var(--text-main); }
        
        .form-group { margin-bottom: 25px; }
        label {
            display: block;
            margin-bottom: 12px;
            color: var(--text-main);
            font-weight: 500;
            font-size: 15px;
        }
        
        .duration-pills {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .duration-pill {
            padding: 12px 0;
            text-align: center;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-muted);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        
        .duration-pill:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .duration-pill.selected {
            background: #eff6ff;
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 500;
        }
        
        .new-end-box {
            border: 1px dashed var(--primary);
            background: #f0fdf4;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .new-end-label {
            font-size: 13px;
            color: var(--primary);
            margin-bottom: 5px;
            display: block;
        }
        
        .new-end-date {
            font-size: 18px;
            font-weight: 600;
            color: #1e3a8a;
        }

        .actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-bg { background: #e2e8f0; color: #475569; }
        .btn-bg:hover { background: #cbd5e1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="icon">📝</span>
            <h1>ต่อสัญญาเช่าห้องพัก</h1>
            <p class="subtitle">ห้อง <?php echo htmlspecialchars((string)($_SESSION['tenant_room_number'] ?? '-')); ?></p>
        </div>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">วันที่สิ้นสุดสัญญาปัจจุบัน:</span>
                <span class="info-val"><?php echo function_exists('formatThaiDate') ? formatThaiDate($currentEndDate) : date('d/m/Y', strtotime($currentEndDate)); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">สถานะสัญญา:</span>
                <span class="info-val" style="color: <?php echo $contract['ctr_status'] == '0' ? '#10b981' : ($contract['ctr_status'] == '1' ? '#ef4444' : '#f59e0b'); ?>">
                    <?php 
                        if ($contract['ctr_status'] == '0') echo 'ปกติ';
                        elseif ($contract['ctr_status'] == '1') echo 'สิ้นสุดสัญญา';
                        elseif ($contract['ctr_status'] == '2') echo 'แจ้งยกเลิก';
                        else echo 'ไม่ทราบสถานะ';
                    ?>
                </span>
            </div>
        </div>

        <form method="POST" id="renewForm">
            <input type="hidden" name="action" value="renew">
            <input type="hidden" name="duration_months" id="durationInput" value="12">
            
            <div class="form-group">
                <label>ระยะเวลาต่อสัญญาเพิ่ม</label>
                <div class="duration-pills">
                    <div class="duration-pill" data-months="6">6 เดือน</div>
                    <div class="duration-pill selected" data-months="12">1 ปี</div>
                    <div class="duration-pill" data-months="24">2 ปี</div>
                </div>
            </div>

            <div class="new-end-box">
                <span class="new-end-label">สัญญาใหม่จะสิ้นสุดวันที่</span>
                <div class="new-end-date" id="newEndDateDisplay">-</div>
            </div>

            <div class="actions">
                <a href="index.php" class="btn btn-bg">กลับหน้าหลัก</a>
                <button type="button" class="btn btn-primary" id="submitBtn">ยืนยันการต่อสัญญา</button>
            </div>
        </form>
    </div>

    <script>
        const currentEndStr = '<?php echo $currentEndDate; ?>';
        // Need to calculate end dates in JS
        const monthNames = [
            "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
            "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
        ];

        function calculateNewDate(monthsAdd) {
            const d = new Date(currentEndStr);
            d.setMonth(d.getMonth() + parseInt(monthsAdd));
            
            const day = d.getDate().toString().padStart(2, '0');
            const month = monthNames[d.getMonth()];
            const year = d.getFullYear() + 543;
            
            return `${day} ${month} ${year}`;
        }

        const pills = document.querySelectorAll('.duration-pill');
        const durationInput = document.getElementById('durationInput');
        const endDisplay = document.getElementById('newEndDateDisplay');

        function updateDisplay() {
            const months = document.querySelector('.duration-pill.selected').dataset.months;
            durationInput.value = months;
            endDisplay.textContent = calculateNewDate(months);
        }

        pills.forEach(pill => {
            pill.addEventListener('click', function() {
                pills.forEach(p => p.classList.remove('selected'));
                this.classList.add('selected');
                updateDisplay();
            });
        });

        // initial display
        updateDisplay();
        
        document.getElementById('submitBtn').addEventListener('click', async function() {
            const confirmed = await showAppleConfirm(
                'คุณต้องการต่ออายุสัญญาเช่าของคุณใช่หรือไม่?',
                'ยืนยันการต่อสัญญา?'
            );
            if (confirmed) {
                document.getElementById('renewForm').submit();
            }
        });
        
        <?php if ($success): ?>
        showAppleAlert('สัญญาเช่าของคุณได้รับการต่ออายุเรียบร้อยแล้ว', 'ต่ออายุสัญญาสำเร็จ!');
        setTimeout(() => {
            const token = '<?php echo urlencode($_SESSION['tenant_token'] ?? ''); ?>';
            window.location.href = token ? `index.php?token=${token}` : 'index.php';
        }, 1500);
        <?php elseif ($error): ?>
        showAppleAlert('<?php echo addslashes($error); ?>', 'เกิดข้อผิดพลาด!');
        <?php endif; ?>
    </script>
    
    <?php include_once __DIR__ . '/../includes/apple_alert.php'; ?>
</body>
</html>
