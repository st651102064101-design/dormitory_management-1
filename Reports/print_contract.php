<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$ctr_id = isset($_GET['ctr_id']) ? (int)$_GET['ctr_id'] : 0;

// Page 1: List all contracts
if ($ctr_id === 0) {
    $contracts = $pdo->query("
        SELECT c.ctr_id, c.ctr_start, c.ctr_end, t.tnt_name, r.room_number
        FROM contract c
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room r ON c.room_id = r.room_id
        ORDER BY c.ctr_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกสัญญาเพื่อพิมพ์</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 40px; border-radius: 12px; margin-bottom: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
        .header h1 { font-size: 32px; color: #333; margin-bottom: 10px; }
        .header p { font-size: 16px; color: #666; }
        .count { background: #f0f0f0; padding: 12px 20px; border-radius: 8px; margin-top: 20px; font-weight: bold; color: #3b82f6; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); cursor: pointer; text-decoration: none; color: inherit; display: block; transition: all 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .card-number { font-size: 24px; font-weight: bold; color: #3b82f6; margin-bottom: 15px; }
        .card-info { border-top: 2px solid #e0e0e0; padding-top: 15px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
        .label { color: #666; font-weight: bold; min-width: 80px; }
        .value { color: #333; text-align: right; flex: 1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖨️ พิมพ์สัญญา</h1>
            <div class="count">📋 พบ <?php echo count($contracts); ?> สัญญา</div>
        </div>
        <div class="grid">
            <?php foreach ($contracts as $c): ?>
            <a href="print_contract.php?ctr_id=<?php echo (int)$c['ctr_id']; ?>" class="card">
                <div class="card-number">📄 #<?php echo str_pad((string)$c['ctr_id'], 4, '0', STR_PAD_LEFT); ?></div>
                <div class="card-info">
                    <div class="info-row"><span class="label">ผู้เช่า:</span><span class="value"><?php echo htmlspecialchars($c['tnt_name'] ?? '-'); ?></span></div>
                    <div class="info-row"><span class="label">ห้อง:</span><span class="value"><?php echo htmlspecialchars($c['room_number'] ?? '-'); ?></span></div>
                    <div class="info-row"><span class="label">วันที่:</span><span class="value"><?php echo htmlspecialchars($c['ctr_start'] ?? '-'); ?></span></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// Page 2: Print single contract
$stmt = $pdo->prepare("
    SELECT c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_status,
           t.tnt_name, t.tnt_phone, t.tnt_age, t.tnt_address, t.tnt_education, 
           t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone,
           r.room_number,
           rt.type_name, rt.type_price
    FROM contract c
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    WHERE c.ctr_id = ?
");
$stmt->execute([$ctr_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('HTTP/1.0 404 Not Found');
    die('ไม่พบข้อมูลสัญญา ID: ' . $ctr_id);
}

function formatThaiDate($dateStr) {
    if (!$dateStr) return '-';
    $months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $ts = strtotime($dateStr);
    if (!$ts) return '-';
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return $d . ' ' . $months[$m - 1] . ' ' . $y;
}

function formatThaiDateParts($dateStr) {
    $blank = ['day' => '', 'month' => '', 'year' => ''];
    if (!$dateStr) return $blank;
    $months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $ts = strtotime($dateStr);
    if (!$ts) return $blank;
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return ['day' => $d, 'month' => $months[$m - 1] ?? '', 'year' => $y];
}
$datePartsStart = formatThaiDateParts($contract['ctr_start'] ?? null);
$datePartsEnd = formatThaiDateParts($contract['ctr_end'] ?? null);

function h($value) {
    if ($value === null) return '';
    return htmlspecialchars((string)$value);
}

function surnameFromFullName($fullName) {
    if (!$fullName) return '';
    $parts = preg_split('/\s+/', trim((string)$fullName));
    if (!$parts || count($parts) === 0) return '';
    return end($parts);
}

function firstNameWithoutSurname($fullName) {
    if (!$fullName) return '';
    $parts = preg_split('/\s+/', trim((string)$fullName));
    if (!$parts || count($parts) === 0) return '';
    if (count($parts) === 1) return $parts[0];
    array_pop($parts); // remove surname
    return implode(' ', $parts);
}

function formatYearValue($rawYear) {
    if ($rawYear === null) return '';
    $raw = trim((string)$rawYear);
    if ($raw === '') return '';
    // Extract the first digit sequence to avoid duplicated "ปี" prefixes.
    if (preg_match('/(\d+)/u', $raw, $m)) {
        return $m[1];
    }
    return $raw;
}

function nameWithoutNickname($fullName) {
    if (!$fullName) return '';
    $stripped = preg_replace('/\s*\(.*?\)\s*/u', ' ', (string)$fullName);
    $stripped = preg_replace('/\s{2,}/u', ' ', $stripped);
    return trim($stripped);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์สัญญา</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cordia New', Tahoma, serif; font-size: 14px; line-height: 1.6; background: #f5f5f5; padding: 20px; }
        @page { size: A4; margin: 0; }
        .print-container { width: 210mm; min-height: 297mm; height: auto; padding: 20mm 12.7mm 20mm 20.32mm; background: white; margin: 20px auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #000; }
        .header h1 { font-size: 18px; margin-bottom: 5px; }
        .header p { font-size: 13px; margin: 2px 0; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 13px; font-weight: bold; margin-bottom: 10px; padding: 5px; background: #f0f0f0; }
        .row { display: flex; margin-bottom: 8px; gap: 15px; }
        .col { flex: 1; }
        .form-field { border-bottom: 1px solid #000; padding: 2px 5px; font-size: 12px; min-height: 16px; }
        .label { font-size: 11px; font-weight: bold; display: block; margin-bottom: 2px; }
        .terms { font-size: 12px; margin-top: 10px; line-height: 1.5; }
        .terms ol { margin-left: 20px; }
        .terms li { margin-bottom: 4px; }
        .signatures { margin-top: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px 30px; }
        .signature-box { font-size: 12px; }
        .signature-row { display: flex; align-items: center; gap: 8px; margin-bottom: calc(12px + 0.6pt); justify-content: center; }
        .signature-line { width: 240px; border-bottom: 1px dotted #000; min-height: 18px; }
        .signature-label { white-space: nowrap; }
        .signature-paren { white-space: nowrap; }
        .clause-line { margin-bottom: 10px; }
        .underline { display: inline-flex; align-items: flex-end; justify-content: center; vertical-align: baseline; min-width: 40px; border-bottom: 1px dotted #000; padding: 0 4px 0; text-align: center; line-height: 1; color: #0066cc; }
        .underline-long { min-width: 120px; }
        .underline-mid { min-width: 90px; }
        .underline-short { min-width: 50px; }
        .underline-wide { min-width: 160px; }
        .underline-phone { min-width: 110px; }
        .underline-xl { min-width: 320px; }
        @media print { body { background: white; padding: 0; } .print-container { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20mm 12.7mm 20mm 20.32mm; box-shadow: none; } }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header" style="text-align: center; border-bottom: none; margin-bottom: 10px;">
            <div class="form-field" style="border: none; font-size: 16px; font-weight: normal;">ห้องเช่าที่ <span class="underline"><?php echo h($contract['room_number'] ?? ''); ?></span> ( <?php echo h($contract['type_name'] ?? ''); ?> )</div>
            <div class="form-field" style="border: none; font-size: 14px;">หนังสือสัญญาเช่าห้องของหอพักแสงเทียน</div>
            <div class="form-field" style="border: none; font-size: 14px;">เขียนที่หอพักแสงเทียน เมื่อวันที่ <span class="underline"><?php echo h($datePartsStart['day']); ?></span> เดือน <span class="underline"><?php echo h($datePartsStart['month']); ?></span> ปี <span class="underline"><?php echo h($datePartsStart['year']); ?></span></div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">โดยหนังสือฉบับนี้</div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ข้าพเจ้า นางรุ่งทิพย์ ชิ้นจอหอ ผู้จัดการหอพักแสงเทียน ซึ่งต่อไปนี้เรียกว่า "ผู้ให้เช่า" ฝ่ายหนึ่ง กับข้าพเจ้า
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3.&nbsp;&nbsp; ชื่อ <span class="underline underline-long"><?php echo h(firstNameWithoutSurname($contract['tnt_name'] ?? '')); ?></span>
                สกุล <span class="underline underline-long"><?php echo h(surnameFromFullName($contract['tnt_name'] ?? '')); ?></span>
                อายุ <span class="underline underline-short"><?php echo h($contract['tnt_age'] ?? ''); ?></span> ปี
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                เลขประจำตัวบัตรประชาชน <span class="underline underline-mid"></span>
                สถานศึกษา <span class="underline underline-long"><?php echo h($contract['tnt_education'] ?? ''); ?></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                คณะ <span class="underline underline-long"><?php echo h($contract['tnt_faculty'] ?? ''); ?></span>
                ปีที่ <span class="underline underline-short"><?php echo h(formatYearValue($contract['tnt_year'] ?? '')); ?></span>
                มีรถจักรยานยนต์หมายเลขทะเบียน <span class="underline underline-wide"><?php echo h($contract['tnt_vehicle'] ?? ''); ?></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                เบอร์โทร <span class="underline underline-phone"><?php echo h($contract['tnt_phone'] ?? ''); ?></span>
                เบอร์โทรผู้ปกครอง <span class="underline underline-phone"><?php echo h($contract['tnt_parentsphone'] ?? ''); ?></span>
                บัตรประจำตัวประชาชน <span class="underline underline-long"></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left; display: flex; align-items: flex-end; gap: 6px;">
                ที่อยู่ตามบัตร <span class="underline underline-xl" style="flex: 1; justify-content: flex-start; text-align: left; color: #0066cc;"><?php echo h($contract['tnt_address'] ?? ''); ?></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ซึ่งต่อไปนี้ในสัญญานี้เรียกว่า "ผู้เช่า" อีกฝ่ายหนึ่ง ทั้งสองฝ่ายตกลงทำสัญญากันดังนี้มีข้อความต่อไปนี้ คือ
            </div>
            <div class="form-field" style="border: none; font-size: 13.5px; text-align: left; white-space: nowrap;">
                ข้อ 1. ผู้ให้เช่าตกลงให้เช่าและผู้เช่าตกลงเช่า ค่าห้องราคา <span class="underline underline-mid" style="min-width: 80px; padding: 0 3px 0;"><?php echo number_format((float)($contract['type_price'] ?? 0), 2); ?></span> บาท เงินประกัน 2,000 บาท (สองพันบาทถ้วน)
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ค่าไฟฟ้าและค่าน้ำแยกต่างหากประกันจะคืนให้เมื่อเช่าหอพักครบตามกำหนด โดยวัตถุประสงค์เป็นที่อยู่อาศัยมีกำหนด
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left; display: flex; flex-wrap: nowrap; align-items: center; gap: 4px; white-space: nowrap;">
                <span>เช่าเริ่มตั้งแต่วันที่</span>
                <span class="underline underline-short" style="padding: 0 3px 0; min-width: 36px; line-height: 1;">
                    <?php echo h($datePartsStart['day']); ?>
                </span>
                <span>เดือน</span>
                <span class="underline underline-mid" style="padding: 0 3px 0; min-width: 70px; line-height: 1;">
                    <?php echo h($datePartsStart['month']); ?>
                </span>
                <span>พ.ศ.</span>
                <span class="underline underline-short" style="padding: 0 3px 0; min-width: 36px; line-height: 1;">
                    <?php echo h($datePartsStart['year']); ?>
                </span>
                <span>ถึงวันที่</span>
                <span class="underline underline-short" style="padding: 0 3px 0; min-width: 36px; line-height: 1;">
                    <?php echo h($datePartsEnd['day']); ?>
                </span>
                <span>เดือน</span>
                <span class="underline underline-mid" style="padding: 0 3px 0; min-width: 70px; line-height: 1;">
                    <?php echo h($datePartsEnd['month']); ?>
                </span>
                <span>พ.ศ.</span>
                <span class="underline underline-short" style="padding: 0 3px 0; min-width: 36px; line-height: 1;">
                    <?php echo h($datePartsEnd['year']); ?>
                </span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                และจ่ายเงินก่อนอยู่ทุกเดือนไม่เกินวันที่ 5 ของทุกเดือน เงินประกันจะคืนให้เมื่อเช่าหอพักอยู่ครบตามกำหนด ถ้าผู้เช่า
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                มีเหตุที่จะต้องเลิกเช่าก่อนกำหนดผู้เช่าจะไม่เรียกร้องขอรับเงินประกันคืนไม่ว่ากรณีใดๆทั้งสิ้น และผู้เช่าต้องปฏิบัติ
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ตามระเบียบของหอพักทุกประการ ถ้าไม่ปฏิบัติตามระเบียบของหอพัก ผู้ให้เช่าสามารถยกเลิกสัญญาและไม่ให้เช่า
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ห้องได้
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ข้อ 1. <span class="underline underline-xl" style="color: #000;">ห้ามผู้เช่าดื่มสุรา ของมึนเมา ห้ามเล่นการพนับ ห้ามนำสิ่งเสพติดผิดกฎหมายเข้ามาในบริเวณหอพัก</span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ข้อ 2. <span class="underline underline-xl" style="color: #000;">ผู้เช่าจะไม่ติดภาพ หรือดอกตะปู หรือทำการสิ่งอื่นใดที่ทำให้ผนังเสียหาย พร้อมที่จะส่งมอบคืนตามสภาพ</span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left; display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
                <span>ข้อที่ 3. ห้ามเลี้ยงสัตว์เพราะจะรบกวนห้องข้างเคียง</span>
                <span class="underline underline-wide" style="color: #000;">ถ้าผู้เช่าห้องไม่อยู่ห้ามให้ผู้อื่นมาใช้ห้อง</span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left; display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
                <span>ข้อ4. ผู้เช่าไม่ส่งเสียงดังรบกวนเพื่อนในห้องและนอกห้อง</span>
                <span class="underline underline-wide" style="color: #000;">และเจ้าของหอพักมีสิทธิ์ตักเตอนได้และขอเลิกให้เช่าก่อน</span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                <span class="underline underline-xl" style="color: #000;">กำหนดเมื่อผู้เช่ากระทำผิดระเบียบของหอพัก คู่สัญญาได้อ่านและเข้าใจข้อความดีแล้ว จึงลงลายมือชื่อไว้เป็นสำคัญ</span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ข้อ 5. ถ้ามีสิ่งใดเสียหายผู้เช่ายินดีชดใช้ และให้หักเงินประกั
            </div>
        </div>
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-row">
                    <span class="signature-line"></span>
                    <span class="signature-label">ผู้เช่า</span>
                </div>
                <div class="signature-row">
                    <span class="signature-paren">(</span>
                    <span class="signature-line" style="width: 220px; text-align: center; line-height: 1.4;">
                        <?php echo h(nameWithoutNickname($contract['tnt_name'] ?? '')); ?>
                    </span>
                    <span class="signature-paren">)</span>
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-row">
                    <span class="signature-line"></span>
                    <span class="signature-label">ผู้เช่า</span>
                </div>
                <div class="signature-row">
                    <span class="signature-paren">(</span>
                    <span class="signature-line" style="width: 220px;"></span>
                    <span class="signature-paren">)</span>
                </div>
            </div>
            <div class="signature-box owner" style="grid-column: 1 / span 2; max-width: 60%; margin: 0 auto;">
                <div class="signature-row">
                    <span class="signature-line"></span>
                    <span class="signature-label">ผู้ให้เช่า</span>
                </div>
                <div class="signature-row">
                    <span class="signature-paren">(</span>
                    <span class="signature-line" style="width: 220px; text-align: center; line-height: 1.4;">นางรุ่งทิพย์ ชิ้นจอหอ</span>
                    <span class="signature-paren">)</span>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Auto-print when page loads, but allow time for page to render
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
