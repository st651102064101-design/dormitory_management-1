<?php
/**
 * Debug: ตรวจสอบว่า token map ไปที่ ctr_id ไหน และมี repair ไหม
 * เข้าด้วย: /Tenant/debug_repair_token.php?token=xxxx
 */
declare(strict_types=1);
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$token = $_GET['token'] ?? '';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:monospace;padding:20px;background:#111;color:#0f0;}
pre{background:#000;padding:15px;border-radius:8px;overflow:auto;}
.ok{color:#0f0}.warn{color:#ff0}.err{color:#f55}.section{color:#6af;margin-top:20px;font-weight:bold;font-size:1.1rem;}</style></head>
<body>
<h2 style="color:#6af">🔍 Repair Token Diagnostic</h2>
<p>Token: <strong style="color:#ff0"><?= htmlspecialchars($token) ?: '(ไม่ระบุ - ใส่ ?token=xxx)' ?></strong></p>
<pre>
<?php
if (empty($token)) {
    echo "⚠️  กรุณาใส่ ?token=xxxx ใน URL\n";
    exit;
}

// 1. หา contract จาก token
echo "=== STEP 1: หา Contract จาก Token ===\n";
$stmt = $pdo->prepare("
    SELECT c.ctr_id, c.tnt_id, c.room_id, c.ctr_status, c.access_token,
           t.tnt_name, r.room_number
    FROM contract c
    JOIN tenant t ON c.tnt_id = t.tnt_id
    JOIN room r ON c.room_id = r.room_id
    WHERE c.access_token = ?
    LIMIT 1
");
$stmt->execute([$token]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    echo "❌ ไม่พบ contract จาก token นี้!\n";
    // ลอง query แบบ LEFT JOIN เผื่อ JOIN ล้มเหลว
    $fallback = $pdo->prepare("SELECT c.* FROM contract c WHERE c.access_token = ? LIMIT 1");
    $fallback->execute([$token]);
    $raw = $fallback->fetch(PDO::FETCH_ASSOC);
    if ($raw) {
        echo "⚠️  Token มีใน DB แต่ JOIN ล้มเหลว!\n";
        echo "   ctr_id={$raw['ctr_id']}, room_id={$raw['room_id']}, ctr_status={$raw['ctr_status']}\n";
        // ตรวจสอบว่า room_id มีอยู่จริงไหม
        $roomCheck = $pdo->prepare("SELECT room_id, room_number FROM room WHERE room_id = ?");
        $roomCheck->execute([$raw['room_id']]);
        $room = $roomCheck->fetch(PDO::FETCH_ASSOC);
        if (!$room) {
            echo "❌ room_id={$raw['room_id']} ไม่มีในตาราง room!\n";
        } else {
            echo "✓  room_id={$room['room_id']}, room_number={$room['room_number']}\n";
        }
        $ctrId = $raw['ctr_id'];
        $ctrStatus = $raw['ctr_status'];
        echo "   NOTE: auth.php กรอง ctr_status IN ('0','2'), แต่ ctr_status ของสัญญานี้ = '$ctrStatus'\n";
        if (!in_array($ctrStatus, ['0', '2'])) {
            echo "⚠️  ctr_status='$ctrStatus' ไม่อยู่ใน ('0','2') → auth.php จะ redirect!\n";
        }
    } else {
        echo "❌ Token นี้ไม่มีในตาราง contract เลย\n";
    }
} else {
    echo "✓  พบ contract:\n";
    echo "   ctr_id = {$contract['ctr_id']}\n";
    echo "   tnt_id = {$contract['tnt_id']}\n";
    echo "   ชื่อ   = {$contract['tnt_name']}\n";
    echo "   room   = {$contract['room_number']} (room_id={$contract['room_id']})\n";
    echo "   status = {$contract['ctr_status']}\n";
    if (!in_array($contract['ctr_status'], ['0', '2'])) {
        echo "⚠️  ctr_status='{$contract['ctr_status']}' ไม่อยู่ใน ('0','2') → auth.php อาจ redirect!\n";
    }
    $ctrId = $contract['ctr_id'];
}

if (!isset($ctrId)) { exit; }

// 2. หา repairs สำหรับ ctr_id นี้
echo "\n=== STEP 2: หา Repairs สำหรับ ctr_id=$ctrId ===\n";
$repairStmt = $pdo->prepare("SELECT repair_id, ctr_id, repair_date, repair_time, repair_status, repair_desc FROM repair WHERE ctr_id = ? ORDER BY repair_date DESC");
$repairStmt->execute([$ctrId]);
$repairs = $repairStmt->fetchAll(PDO::FETCH_ASSOC);
echo "จำนวน repairs: " . count($repairs) . "\n";
foreach ($repairs as $r) {
    echo "  - repair_id={$r['repair_id']}, date={$r['repair_date']}, status={$r['repair_status']}, desc='" . mb_substr($r['repair_desc'], 0, 40) . "'\n";
}

// 3. ตรวจสอบ repairs ทั้งหมดในระบบ
echo "\n=== STEP 3: Repairs ทั้งหมดในระบบ (20 ล่าสุด) ===\n";
$allRepairs = $pdo->query("
    SELECT r.repair_id, r.ctr_id, rm.room_number, t.tnt_name, r.repair_date, r.repair_status
    FROM repair r
    LEFT JOIN contract c ON r.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room rm ON c.room_id = rm.room_id
    ORDER BY r.repair_date DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allRepairs as $r) {
    $isMine = ($r['ctr_id'] == $ctrId) ? ' ← ของคุณ!' : '';
    echo "  repair_id={$r['repair_id']}, ctr_id={$r['ctr_id']}, room=" . ($r['room_number'] ?? 'NULL') . ", tenant=" . ($r['tnt_name'] ?? 'NULL') . ", date={$r['repair_date']} $isMine\n";
}

// 4. สรุปปัญหา
echo "\n=== STEP 4: สรุป ===\n";
if (count($repairs) === 0) {
    echo "⚠️  ไม่มี repair records สำหรับ ctr_id=$ctrId\n";
    echo "   → หมายความว่าการแจ้งซ่อมถูกบันทึกด้วย ctr_id อื่น หรือยังไม่มีการแจ้งซ่อม\n";
} else {
    echo "✓  มี " . count($repairs) . " repair record(s) สำหรับ ctr_id=$ctrId\n";
    echo "   → ถ้า repair.php ยังไม่แสดง อาจมีปัญหา PHP error ที่ถูก catch ไว้\n";
}
?>
</pre>
</body></html>
