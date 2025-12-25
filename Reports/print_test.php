<?php
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$ctr_id = (int)($_GET['ctr_id'] ?? 0);

if ($ctr_id <= 0) {
    $contracts = $pdo->query("SELECT c.ctr_id, c.ctr_start, t.tnt_name, r.room_number FROM contract c LEFT JOIN tenant t ON c.tnt_id = t.tnt_id LEFT JOIN room r ON c.room_id = r.room_id ORDER BY c.ctr_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>พิมพ์สัญญา</title>
    <style>
        body { font-family: Tahoma; background: #667eea; padding: 40px; }
        .header { background: white; padding: 40px; border-radius: 10px; text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; margin: 0 0 10px 0; }
        .count { color: #3b82f6; font-weight: bold; margin-top: 15px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; text-decoration: none; color: #333; }
        .card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .card-num { color: #3b82f6; font-weight: bold; font-size: 18px; }
        .card-info { margin-top: 15px; border-top: 1px solid #ddd; padding-top: 10px; }
        .info-row { font-size: 13px; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>พิมพ์สัญญา</h1>
        <div class="count">พบ <?php echo count($contracts); ?> สัญญา</div>
    </div>
    
    <div class="grid">
        <?php foreach ($contracts as $c): ?>
        <a href="?ctr_id=<?php echo $c['ctr_id']; ?>" class="card">
            <div class="card-num"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>#<?php echo str_pad((string)$c['ctr_id'], 4, '0', STR_PAD_LEFT); ?></div>
            <div class="card-info">
                <div class="info-row"><strong>ผู้เช่า:</strong> <?php echo htmlspecialchars($c['tnt_name'] ?? '-'); ?></div>
                <div class="info-row"><strong>ห้อง:</strong> <?php echo htmlspecialchars($c['room_number'] ?? '-'); ?></div>
                <div class="info-row"><strong>วันที่:</strong> <?php echo htmlspecialchars($c['ctr_start'] ?? '-'); ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</body>
</html>
<?php
exit;
}
// Print page
// Load contract details
$stmt = $pdo->prepare("SELECT c.ctr_id, c.ctr_start, c.ctr_end, t.tnt_name, t.tnt_phone, r.room_number FROM contract c LEFT JOIN tenant t ON c.tnt_id = t.tnt_id LEFT JOIN room r ON c.room_id = r.room_id WHERE c.ctr_id = ?");
$stmt->execute([$ctr_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('HTTP/1.0 404 Not Found');
    echo 'ไม่พบสัญญาเลขที่ ' . $ctr_id;
    exit;
}

function h($value) {
    if ($value === null) return '';
    return htmlspecialchars((string)$value);
}

function formatThaiDate($dateStr) {
    if (!$dateStr) return '........';
    $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($dateStr);
    if (!$ts) return '........';
    $d = date('j', $ts);
    $m = $months[(int)date('n', $ts) - 1];
    $y = (int)date('Y', $ts) + 543;
    return $d . ' ' . $m . ' พ.ศ. ' . $y;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หนังสือสัญญาเช่า</title>
    <style>
        body { font-family: 'TH Sarabun New', Tahoma, sans-serif; background: #f5f5f5; padding: 32px; line-height: 1.6; }
        .sheet { max-width: 900px; margin: 0 auto; background: white; padding: 32px 40px; box-shadow: 0 6px 24px rgba(0,0,0,0.12); }
        .line { margin-bottom: 10px; font-size: 18px; }
        .line-1 { font-size: 22px; font-weight: bold; }
        .muted { color: #555; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="line line-1">ห้องเช่าที่ <?php echo h($contract['room_number'] ?? '-'); ?> ฝั่งใหม่</div>
        <div class="line">หนังสือสัญญาเช่าห้องของหอพักแสงเทียน</div>
        <div class="line">เขียนที่หอพักแสงเทียน เมื่อวันที่ <?php echo formatThaiDate($contract['ctr_start'] ?? null); ?></div>
        <div class="line">โดยหนังสือฉบับนี้</div>
        <div class="line muted">
            ข้าพเจ้า นางรุ่งทิพย์ ชิ้นจอหอ ผู้จัดการหอพักแสงเทียน ซึ่งต่อไปนี้เรียกว่า "ผู้ให้เช่า" ฝ่ายหนึ่ง กับข้าพเจ้า <?php echo h($contract['tnt_name'] ?? '................................'); ?> ซึ่งต่อไปนี้เรียกว่า "ผู้เช่า" อีกฝ่ายหนึ่ง
        </div>
    </div>
</body>
</html>
?>
