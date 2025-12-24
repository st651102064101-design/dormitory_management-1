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
    // Show only the latest contract per tenant to avoid duplicate tenant rows in the list
    $contracts = $pdo->query("
        SELECT c.ctr_id, c.ctr_start, c.ctr_end, t.tnt_name, r.room_number
        FROM contract c
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room r ON c.room_id = r.room_id
        WHERE c.ctr_id IN (
            SELECT MAX(c2.ctr_id) FROM contract c2 GROUP BY c2.tnt_id
        )
        ORDER BY c.ctr_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php $pageTitle = 'พิมพ์สัญญา'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกสัญญาเพื่อพิมพ์</title>
    <link rel="stylesheet" href="/Dormitory_Management/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/Dormitory_Management/Assets/Css/main.css">
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css">
    <link rel="stylesheet" href="/Dormitory_Management/Assets/Css/datatable-modern.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; background: #f5f5f5; min-height: 100vh; }
        .app-shell { display: flex; min-height: 100vh; }
        .container { width: 100%; max-width: 100%; margin: 20px 0 0 0; padding: 0 24px 24px 24px; display: flex; flex-direction: column; gap: 16px; }
        .header { background: rgba(255,255,255,0.04); padding: 24px; border-radius: 14px; margin-bottom: 20px; box-shadow: 0 18px 40px rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }
        .header h1 { font-size: 28px; color: #e2e8f0; margin: 0; }
        .header p { font-size: 15px; color: #cbd5e1; }
        .count { background: rgba(96,165,250,0.12); padding: 10px 16px; border-radius: 10px; margin-top: 4px; font-weight: 700; color: #60a5fa; display: block; border: 1px solid rgba(96,165,250,0.3); }
        .toolbar { display: flex; width: 100%; justify-content: flex-start; gap: 12px; margin: 4px 0 18px; flex-wrap: wrap; }
        .btn { padding: 10px 16px; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.12); transition: transform 0.15s ease, box-shadow 0.15s ease; background: #2563eb; color: #fff; }
        .btn.secondary { background: #e5e7eb; color: #111827; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.16); }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .card { background: rgba(255,255,255,0.04) !important; border-radius: 12px; padding: 20px; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 12px 30px rgba(0,0,0,0.35); cursor: pointer; text-decoration: none; color: #e2e8f0; display: block; transition: all 0.2s ease; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,0,0,0.45); border-color: rgba(96,165,250,0.4); }
        .card-number { font-size: 20px; font-weight: bold; color: #60a5fa; margin-bottom: 12px; }
        .card-info { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #e2e8f0; }
        .label { color: #94a3b8; font-weight: 600; min-width: 80px; }
        .value { color: #f8fafc; text-align: right; flex: 1; }
        .table-wrap { background: rgba(255,255,255,0.04); border-radius: 12px; padding: 16px; box-shadow: 0 12px 30px rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08); }
        table { width: 100%; border-collapse: collapse; color: #e2e8f0; }
        th, td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; font-size: 14px; }
        th { background: rgba(255,255,255,0.05); font-weight: 700; color: #f8fafc; }
        tr:hover td { background: rgba(255,255,255,0.03); }
        .hidden { display: none; }
    </style>
</head>
<body class="reports-page">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div class="container">
                <div>
                    <?php include __DIR__ . '/../includes/page_header.php'; ?>
                </div>
                <div class="toolbar">
                    <button id="toggle-view" class="btn">ดูแบบตาราง</button>
                </div>
                <div class="grid">
                    <?php foreach ($contracts as $c): ?>
                    <a href="print_contract.php?ctr_id=<?php echo (int)$c['ctr_id']; ?>" class="card" target="_blank" rel="noopener">
                        <div class="card-number"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>#<?php echo str_pad((string)$c['ctr_id'], 4, '0', STR_PAD_LEFT); ?></div>
                        <div class="card-info">
                            <div class="info-row"><span class="label">ผู้เช่า:</span><span class="value"><?php echo htmlspecialchars($c['tnt_name'] ?? '-'); ?></span></div>
                            <div class="info-row"><span class="label">ห้อง:</span><span class="value"><?php echo htmlspecialchars($c['room_number'] ?? '-'); ?></span></div>
                            <div class="info-row"><span class="label">วันที่:</span><span class="value"><?php echo htmlspecialchars($c['ctr_start'] ?? '-'); ?></span></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div id="table-view" class="table-wrap hidden">
                    <table id="table-print-contracts">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ผู้เช่า</th>
                                <th>ห้อง</th>
                                <th>วันที่</th>
                                <th>พิมพ์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $idx => $c): ?>
                                <tr>
                                    <td><?php echo str_pad((string)$c['ctr_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($c['tnt_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($c['room_number'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($c['ctr_start'] ?? '-'); ?></td>
                                    <td><a href="print_contract.php?ctr_id=<?php echo (int)$c['ctr_id']; ?>" class="btn secondary" style="padding: 6px 10px; box-shadow: none;" target="_blank" rel="noopener">พิมพ์</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <script>
                    const toggleBtn = document.getElementById('toggle-view');
                    const gridView = document.querySelector('.grid');
                    const tableView = document.getElementById('table-view');

                    function setViewCookie(mode) {
                        document.cookie = 'contractView=' + mode + '; path=/; max-age=' + (60 * 60 * 24 * 30);
                    }
                    function getViewCookie() {
                        const match = document.cookie.match(/(?:^|; )contractView=([^;]+)/);
                        return match ? decodeURIComponent(match[1]) : null;
                    }
                    function showTable() {
                        tableView.classList.remove('hidden');
                        gridView.classList.add('hidden');
                        toggleBtn.textContent = 'ดูแบบการ์ด';
                        setViewCookie('table');
                    }
                    function showCard() {
                        tableView.classList.add('hidden');
                        gridView.classList.remove('hidden');
                        toggleBtn.textContent = 'ดูแบบตาราง';
                        setViewCookie('card');
                    }

                    if (toggleBtn && gridView && tableView) {
                        const savedView = getViewCookie();
                        if (savedView === 'table') {
                            showTable();
                        } else {
                            showCard();
                        }

                        toggleBtn.addEventListener('click', () => {
                            const showingTable = !tableView.classList.contains('hidden');
                            if (showingTable) {
                                showCard();
                            } else {
                                showTable();
                            }
                        });
                    }
                </script>
                <script src="../Assets/Javascript/animate-ui.js" defer></script>
                <script src="../Assets/Javascript/main.js" defer></script>
                
                <!-- DataTable Initialization -->
                <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
                <script>
                  document.addEventListener('DOMContentLoaded', function() {
                    const contractsTable = document.getElementById('table-print-contracts');
                    if (contractsTable && typeof simpleDatatables !== 'undefined') {
                      new simpleDatatables.DataTable(contractsTable, {
                        searchable: true,
                        fixedHeight: false,
                        perPage: 10,
                        perPageSelect: [5, 10, 25, 50],
                        labels: {
                          placeholder: 'ค้นหาสัญญา...',
                          perPage: 'รายการต่อหน้า',
                          noRows: 'ไม่พบข้อมูลสัญญา',
                          info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
                        }
                      });
                    }
                  });
                </script>
            </div>
        </main>
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
        body { font-family: 'Cordia New', Tahoma, serif; font-size: 14px; line-height: 1.6; background: #f5f5f5; padding: 20px; font-weight: normal; -webkit-font-smoothing: antialiased; }
        @page { size: A4; margin: 0; font-family: 'Cordia New', Tahoma, serif; }
        .print-container { width: 210mm; min-height: 297mm; height: auto; padding: 20mm 12.7mm 20mm 20.32mm; background: white; margin: 20px auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); font-family: 'Cordia New', Tahoma, serif; font-weight: normal; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #000; font-family: 'Cordia New', Tahoma, serif; }
        .header h1 { font-size: 18px; margin-bottom: 5px; font-family: 'Cordia New', Tahoma, serif; font-weight: normal; }
        .header p { font-size: 13px; margin: 2px 0; font-family: 'Cordia New', Tahoma, serif; }
        .section { margin-bottom: 18px; font-family: 'Cordia New', Tahoma, serif; }
        .section-title { font-size: 13px; font-weight: normal; margin-bottom: 10px; padding: 5px; background: #f0f0f0; font-family: 'Cordia New', Tahoma, serif; }
        .row { display: flex; margin-bottom: 8px; gap: 15px; font-family: 'Cordia New', Tahoma, serif; }
        .col { flex: 1; font-family: 'Cordia New', Tahoma, serif; }
        .form-field { border-bottom: 1px solid #000; padding: 2px 5px; font-size: 12px; min-height: 16px; font-family: 'Cordia New', Tahoma, serif; font-weight: normal; }
        .label { font-size: 11px; font-weight: normal; display: block; margin-bottom: 2px; font-family: 'Cordia New', Tahoma, serif; }
        .terms { font-size: 12px; margin-top: 10px; line-height: 1.5; font-family: 'Cordia New', Tahoma, serif; }
        .terms ol { margin-left: 20px; }
        .terms li { margin-bottom: 4px; font-family: 'Cordia New', Tahoma, serif; }
        .signatures { margin-top: 25px; display: grid; grid-template-columns: 1fr; gap: 18px 0; font-family: 'Cordia New', Tahoma, serif; }
        .signature-box { font-size: 12px; font-family: 'Cordia New', Tahoma, serif; }
        .signature-row { display: flex; align-items: center; gap: 8px; margin-bottom: calc(12px + 0.6pt); justify-content: flex-start; font-family: 'Cordia New', Tahoma, serif; }
        .signature-line { width: 240px; border-bottom: 1px dotted #000; min-height: 18px; }
        .signature-label { white-space: nowrap; font-family: 'Cordia New', Tahoma, serif; }
        .signature-paren { white-space: nowrap; font-family: 'Cordia New', Tahoma, serif; }
        .clause-line { margin-bottom: 10px; font-family: 'Cordia New', Tahoma, serif; }
        .underline { display: inline-flex; align-items: flex-end; justify-content: center; vertical-align: baseline; min-width: 40px; border-bottom: 1px dotted #000; padding: 0 4px 0; text-align: center; line-height: 1; color: #0066cc; font-family: 'Cordia New', Tahoma, serif; font-weight: normal; }
        .underline-long { min-width: 120px; }
        .underline-mid { min-width: 90px; }
        .underline-short { min-width: 50px; }
        .underline-wide { min-width: 160px; }
        .underline-phone { min-width: 110px; }
        .underline-xl { min-width: 320px; }
        .underline-address { display: inline-flex; align-items: flex-end; justify-content: flex-start; vertical-align: baseline; min-width: 320px; border-bottom: 1px dotted #000; padding: 0 4px 0; text-align: left; line-height: 1.2; color: #0066cc; white-space: pre-line; font-family: 'Cordia New', Tahoma, serif; font-weight: normal; }
        @media print { body { background: white; padding: 0; font-family: 'Cordia New', Tahoma, serif; font-weight: normal; } .print-container { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20mm 12.7mm 20mm 20.32mm; box-shadow: none; font-family: 'Cordia New', Tahoma, serif; font-weight: normal; } }
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
            <div class="signature-box owner" style="max-width: 60%; margin: 0 auto;">
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
        // Toggle card/table view on list page
        const toggleBtn = document.getElementById('toggle-view');
        const gridView = document.querySelector('.grid');
        const tableView = document.getElementById('table-view');
        const readMoreBtn = document.getElementById('read-more');

        if (toggleBtn && gridView && tableView) {
            toggleBtn.addEventListener('click', () => {
                const showingTable = !tableView.classList.contains('hidden');
                if (showingTable) {
                    tableView.classList.add('hidden');
                    gridView.classList.remove('hidden');
                    toggleBtn.textContent = 'ดูแบบตาราง';
                } else {
                    tableView.classList.remove('hidden');
                    gridView.classList.add('hidden');
                    toggleBtn.textContent = 'ดูแบบการ์ด';
                }
            });
        }

        if (readMoreBtn) {
            readMoreBtn.addEventListener('click', () => {
                document.querySelectorAll('.extra-row').forEach(row => row.classList.remove('hidden'));
                readMoreBtn.classList.add('hidden');
            });
        }

        // Auto-print when page loads for single contract view only
        if (!toggleBtn) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
    </script>
</body>
</html>
