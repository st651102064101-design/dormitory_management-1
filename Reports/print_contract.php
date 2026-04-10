<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if accessed by tenant (has tenant_logged_in session) - allow without admin check
$isTenantAccess = !empty($_SESSION['tenant_logged_in']) || !empty($_SESSION['tenant_token']) || !empty($_GET['from_tenant']);

// Don't check admin authentication yet if ctr_id is provided (will check token below)
if (!$isTenantAccess && empty($_SESSION['admin_username']) && empty($_GET['ctr_id'])) {
    header('Location: ../Login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';
$pdo = connectDB();

$defaultViewMode = 'grid';
try {
    $viewStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_view_mode' LIMIT 1");
    $viewRow = $viewStmt->fetch(PDO::FETCH_ASSOC);
    if ($viewRow && strtolower((string)$viewRow['setting_value']) === 'list') {
        $defaultViewMode = 'list';
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// NEW: Allow access if coming from contract.php with valid token
if (!empty($_GET['ctr_id']) && !$isTenantAccess && empty($_SESSION['admin_username'])) {
    try {
        $tokenCheckStmt = $pdo->prepare("SELECT access_token FROM contract WHERE ctr_id = ? AND access_token IS NOT NULL LIMIT 1");
        $tokenCheckStmt->execute([(int)$_GET['ctr_id']]);
        $tokenResult = $tokenCheckStmt->fetchColumn();
        if ($tokenResult) {
            $isTenantAccess = true; // Allow access as tenant
        } else {
            // No valid token, require authentication
            header('Location: ../Login.php');
            exit;
        }
    } catch (PDOException $e) {
        // Error checking token, require authentication
        header('Location: ../Login.php');
        exit;
    }
}

$isAdminOrOwnerAccess = !empty($_SESSION['admin_username']);
$canEditTenantFields = $isTenantAccess && !$isAdminOrOwnerAccess;

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
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/Logo.jpg">
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body,
        body.reports-page,
        body.reports-page .app-main,
        body.reports-page .container {
            font-family: Tahoma, Arial, sans-serif;
            background: #ffffff !important;
            color: #0f172a !important;
            min-height: 100vh;
        }
        .app-shell { display: flex; min-height: 100vh; }
        .container { width: 100%; max-width: 100%; margin: 0; padding: 0 24px 24px 24px; display: flex; flex-direction: column; gap: 16px; }
        .header { background: #ffffff; padding: 24px; border-radius: 14px; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }
        .header h1 { font-size: 28px; color: #0f172a; margin: 0; }
        .header p { font-size: 15px; color: #64748b; }
        .count { background: rgba(96,165,250,0.12); padding: 10px 16px; border-radius: 10px; margin-top: 4px; font-weight: 700; color: #60a5fa; display: block; border: 1px solid rgba(96,165,250,0.3); }
        .toolbar { display: flex; width: 100%; justify-content: flex-start; gap: 12px; margin: 4px 0 18px; flex-wrap: wrap; }
        .btn { padding: 10px 16px; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.12); transition: transform 0.15s ease, box-shadow 0.15s ease; background: #2563eb; color: #ffffff !important; }
        .btn.secondary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            border: 1px solid #1d4ed8;
        }
        .btn.secondary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.16); }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .card { background: #ffffff !important; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 8px 20px rgba(15,23,42,0.08); cursor: pointer; text-decoration: none; color: #1e293b; display: block; transition: all 0.2s ease; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 16px 32px rgba(15,23,42,0.12); border-color: #93c5fd; }
        .card-number { font-size: 20px; font-weight: bold; color: #60a5fa; margin-bottom: 12px; }
        .card-info { border-top: 1px solid #e2e8f0; padding-top: 12px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #334155; }
        .label { color: #64748b; font-weight: 600; min-width: 80px; }
        .value { color: #0f172a; text-align: right; flex: 1; }
        .table-wrap { background: #ffffff; border-radius: 12px; padding: 16px; box-shadow: 0 8px 20px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; color: #1e293b; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
        th { background: #f8fafc; font-weight: 700; color: #334155; }
        tr:hover td { background: #f8fafc; }

        #table-view .datatable-top,
        #table-view .datatable-bottom {
            background: #ffffff !important;
        }
        #table-view .datatable-input {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        #table-view .datatable-input::placeholder {
            color: #64748b !important;
        }
        #table-view .datatable-selector {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        #table-view .datatable-dropdown label,
        #table-view .datatable-info {
            color: #475569 !important;
        }
        #table-view .datatable-table thead th,
        #table-view .datatable-table thead td,
        #table-view .datatable-table th {
            background: #f8fafc !important;
            color: #334155 !important;
            border-color: #e2e8f0 !important;
        }
        #table-view .datatable-table tbody tr,
        #table-view .datatable-table tbody td,
        #table-view .datatable-table td {
            background: #ffffff !important;
            color: #1e293b !important;
            border-color: #e2e8f0 !important;
        }
        #table-view .datatable-table tbody tr:hover,
        #table-view .datatable-table tbody tr:hover td {
            background: #f8fafc !important;
        }
        #table-view .datatable-pagination a,
        #table-view .datatable-pagination button {
            background: #ffffff !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
        }
        #table-view .datatable-pagination .active a,
        #table-view .datatable-pagination .active button {
            background: #60a5fa !important;
            color: #ffffff !important;
            border-color: #60a5fa !important;
        }
        .hidden { display: none; }

        /* Mobile Responsive */
        @media screen and (max-width: 768px) {
            .container {
                padding: 0 10px 16px 10px;
                gap: 10px;
            }
            .header {
                padding: 16px;
                border-radius: 10px;
                margin-bottom: 12px;
            }
            .header h1 {
                font-size: 20px;
            }
            .header p {
                font-size: 13px;
            }
            .toolbar {
                margin: 0 0 10px;
            }
            .grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .card {
                padding: 14px;
            }
            .table-wrap {
                padding: 8px;
                border-radius: 8px;
            }

            /* DataTable controls */
            #table-view .datatable-top,
            #table-view .datatable-bottom {
                flex-direction: column;
                gap: 8px;
                padding: 8px 0 !important;
            }
            #table-view .datatable-top .datatable-dropdown,
            #table-view .datatable-top .datatable-search {
                width: 100%;
            }
            #table-view .datatable-input {
                width: 100% !important;
            }

            /* Table to card layout on mobile */
            #table-view .datatable-table thead {
                display: none;
            }
            #table-view .datatable-table,
            #table-view .datatable-table tbody {
                display: block;
            }
            #table-view .datatable-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                padding: 12px;
                margin-bottom: 8px;
                border: 1px solid #e2e8f0 !important;
                border-radius: 10px;
                position: relative;
                gap: 4px 0;
            }
            #table-view .datatable-table tbody tr td {
                display: flex;
                align-items: center;
                border: none !important;
                padding: 4px 8px;
                font-size: 13px;
            }
            #table-view .datatable-table tbody tr td::before {
                content: attr(data-label);
                font-weight: 700;
                color: #64748b;
                min-width: 60px;
                margin-right: 8px;
                font-size: 12px;
            }
            /* # column - full width, bold */
            #table-view .datatable-table tbody tr td:nth-child(1) {
                width: 100%;
                font-weight: 700;
                font-size: 15px;
                color: #60a5fa !important;
                padding-bottom: 6px;
                border-bottom: 1px solid #f1f5f9 !important;
                margin-bottom: 4px;
            }
            #table-view .datatable-table tbody tr td:nth-child(1)::before {
                content: none;
            }
            /* ผู้เช่า - full width */
            #table-view .datatable-table tbody tr td:nth-child(2) {
                width: 100%;
            }
            /* ห้อง - half */
            #table-view .datatable-table tbody tr td:nth-child(3) {
                width: 50%;
            }
            /* วันที่ - half */
            #table-view .datatable-table tbody tr td:nth-child(4) {
                width: 50%;
            }
            /* พิมพ์ button - full width */
            #table-view .datatable-table tbody tr td:nth-child(5) {
                width: 100%;
                justify-content: center;
                margin-top: 6px;
                padding-top: 8px;
                border-top: 1px solid #f1f5f9 !important;
            }
            #table-view .datatable-table tbody tr td:nth-child(5)::before {
                content: none;
            }
            #table-view .datatable-table tbody tr td:nth-child(5) .btn {
                width: 100%;
                text-align: center;
                padding: 8px 10px;
            }

            /* Pagination responsive */
            #table-view .datatable-pagination ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 4px;
            }
            #table-view .datatable-pagination a,
            #table-view .datatable-pagination button {
                padding: 6px 10px !important;
                font-size: 13px;
            }
        }
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
                                    <td><a href="print_contract.php?ctr_id=<?php echo (int)$c['ctr_id']; ?>" class="btn secondary" style="padding: 6px 10px; box-shadow: none; color: #ffffff !important;" target="_blank" rel="noopener">พิมพ์</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <script>
                    const toggleBtn = document.getElementById('toggle-view');
                    const gridView = document.querySelector('.grid');
                    const tableView = document.getElementById('table-view');
                    const safeGet = (key) => {
                        try { return localStorage.getItem(key); } catch (e) { return null; }
                    };
                    function showTable() {
                        tableView.classList.remove('hidden');
                        gridView.classList.add('hidden');
                        toggleBtn.textContent = 'ดูแบบการ์ด';
                    }
                    function showCard() {
                        tableView.classList.add('hidden');
                        gridView.classList.remove('hidden');
                        toggleBtn.textContent = 'ดูแบบตาราง';
                    }

                    if (toggleBtn && gridView && tableView) {
                        const dbDefaultView = '<?php echo $defaultViewMode === "list" ? "table" : "card"; ?>';
                        const globalViewMode = safeGet('adminDefaultViewMode');
                        const initialView = globalViewMode === 'list'
                            ? 'table'
                            : (globalViewMode === 'grid' ? 'card' : dbDefaultView);

                        if (initialView === 'table') {
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
                <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
                <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
                
                <!-- DataTable Initialization -->
                <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
                <script>
                  document.addEventListener('DOMContentLoaded', function() {
                    const contractsTable = document.getElementById('table-print-contracts');
                    if (contractsTable && typeof simpleDatatables !== 'undefined') {
                      var labels = ['#', 'ผู้เช่า', 'ห้อง', 'วันที่', 'พิมพ์'];
                      function addDataLabels() {
                        contractsTable.querySelectorAll('tbody tr').forEach(function(row) {
                          row.querySelectorAll('td').forEach(function(td, i) {
                            if (labels[i]) td.setAttribute('data-label', labels[i]);
                          });
                        });
                      }
                      var dt = new simpleDatatables.DataTable(contractsTable, {
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
                      addDataLabels();
                      dt.on('datatable.page', addDataLabels);
                      dt.on('datatable.perpage', addDataLabels);
                      dt.on('datatable.search', addDataLabels);
                      dt.on('datatable.sort', addDataLabels);
                      dt.on('datatable.update', addDataLabels);
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
    SELECT c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_status, c.tnt_id,
           t.tnt_name, t.tnt_phone, t.tnt_age, t.tnt_address, t.tnt_education, 
           t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone,
           t.tnt_idcard,
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

// Ensure tenant_id is available for updates
if (!isset($contract['tenant_id'])) {
    $contract['tenant_id'] = $contract['tnt_id'] ?? 0;
}

// Ensure all required fields have values (set to empty string if null)
$contract['tnt_name'] = $contract['tnt_name'] ?? '';
$contract['tnt_phone'] = $contract['tnt_phone'] ?? '';
$contract['tnt_age'] = $contract['tnt_age'] ?? '';
$contract['tnt_address'] = $contract['tnt_address'] ?? '';
$contract['tnt_education'] = $contract['tnt_education'] ?? '';
$contract['tnt_faculty'] = $contract['tnt_faculty'] ?? '';
$contract['tnt_year'] = $contract['tnt_year'] ?? '';
$contract['tnt_vehicle'] = $contract['tnt_vehicle'] ?? '';
$contract['tnt_parentsphone'] = $contract['tnt_parentsphone'] ?? '';
$contract['tnt_idcard'] = $contract['tnt_idcard'] ?? ''; // Separate ID card field
$contract['room_number'] = $contract['room_number'] ?? '-';
$contract['type_name'] = $contract['type_name'] ?? '-';
$contract['type_price'] = $contract['type_price'] ?? 0;
$contract['ctr_start'] = $contract['ctr_start'] ?? null;
$contract['ctr_end'] = $contract['ctr_end'] ?? null;

// Handle AJAX update for tenant data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');

    if (!$canEditTenantFields) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'คุณไม่มีสิทธิ์แก้ไขข้อมูลสัญญา']);
        exit;
    }
    
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');
    $tenantId = $contract['tenant_id'] ?? $contract['tnt_id'] ?? '';
    
    // Allowed fields to update
    $allowedFields = [
        'tnt_name', 'tnt_age', 'tnt_idcard', 'tnt_education', 
        'tnt_faculty', 'tnt_year', 'tnt_vehicle', 'tnt_address',
        'tnt_phone', 'tnt_parentsphone', 'tnt_parent'
    ];
    
    if (!in_array($field, $allowedFields) || empty($tenantId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field or tenant']);
        exit;
    }
    
    try {
        $updateStmt = $pdo->prepare("UPDATE tenant SET {$field} = ? WHERE tnt_id = ?");
        $updateStmt->execute([$value, $tenantId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ดึงลายเซ็นเจ้าของหอ (ถ้ามี)
$ownerSignature = '';
$ownerName = 'นางรุ่งทิพย์ ชิ้นจอหอ';
$stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('owner_signature', 'site_name')");
while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
    if ($row['setting_key'] === 'owner_signature') {
        $ownerSignature = $row['setting_value'] ?? '';
    }
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

// Ensure date parts have values
$datePartsStart = array_merge(['day' => '', 'month' => '', 'year' => ''], $datePartsStart ?? []);
$datePartsEnd = array_merge(['day' => '', 'month' => '', 'year' => ''], $datePartsEnd ?? []);

function h($value) {
    global $canEditTenantFields;
    if ($value === null || $value === '' || $value === '-') {
        return !empty($canEditTenantFields) ? '' : '-';
    }
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
        .print-container { width: 210mm; min-height: 297mm; height: auto; padding: 20mm 12.7mm 20mm 20.32mm; background: white; margin: 20px auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); font-family: 'Cordia New', Tahoma, serif; font-weight: normal; position: relative; }
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
        
        /* ===== SECURITY: Prevent signature theft ===== */
        /* Disable image selection and dragging */
        img { 
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            pointer-events: none;
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
            user-drag: none;
        }
        
        /* Watermark overlay on signature */
        .signature-protected {
            position: relative;
            display: inline-block;
        }
        
        .signature-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 9px;
            color: rgba(0, 0, 0, 0.15);
            font-weight: bold;
            white-space: nowrap;
            pointer-events: none;
            z-index: 10;
            text-shadow: 0 0 1px rgba(255,255,255,0.5);
        }
        
        /* Screen watermark (hidden on print) */
        .screen-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72px;
            color: rgba(0, 0, 0, 0.05);
            font-weight: bold;
            pointer-events: none;
            z-index: 9999;
            white-space: nowrap;
        }
        
        @media print {
            .screen-watermark { display: none; }
            body { background: white; padding: 0; }
            .print-container { box-shadow: none; margin: 0; }
            .no-print { display: none !important; }
            .print-only { display: inline-flex !important; }
        }
        
        /* Signature button styles */
        .sign-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
            transition: all 0.2s;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .sign-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
        }
        
        .sign-btn svg {
            width: 16px;
            height: 16px;
        }
        
        .print-only {
            display: none;
        }
        
        /* Editable fields styling */
        .editable-field {
            display: inline-block;
            vertical-align: bottom;
            min-width: 60px;
            min-height: 18px;
            border-bottom: 1px dotted #000;
            padding: 0 6px;
            text-align: center;
            line-height: normal;
            color: #0066cc;
            font-family: inherit;
            cursor: text;
            outline: none;
            transition: all 0.2s ease;
            position: relative;
            -webkit-user-select: text !important;
            -moz-user-select: text !important;
            -ms-user-select: text !important;
            user-select: text !important;
            -webkit-user-modify: read-write-plaintext-onlytext !important;
            -ms-user-select: text !important;
            user-select: text !important;
            -webkit-user-modify: read-write-plaintext-only;
        }
        
        .editable-field:empty::before,
        .editable-field.needs-input:empty::before {
            content: attr(data-placeholder);
            color: #999;
            font-style: italic;
        }
        
        /* Hide placeholder when focused or has content */
        .editable-field:focus::before,
        .editable-field:not(:empty)::before {
            content: '' !important;
            display: none !important;
        }
        
        .editable-field.needs-input {
            background: linear-gradient(90deg, transparent, rgba(249, 115, 22, 0.08), transparent);
            animation: pulse-hint 2s ease-in-out infinite;
        }
        
        /* Stop animation when focused */
        .editable-field:focus.needs-input {
            animation: none;
            background: rgba(59, 130, 246, 0.15);
        }
        
        @keyframes pulse-hint {
            0%, 100% { background-color: rgba(249, 115, 22, 0.05); }
            50% { background-color: rgba(249, 115, 22, 0.15); }
        }
        
        .editable-field:hover {
            background: rgba(59, 130, 246, 0.1);
            border-bottom-color: #3b82f6;
        }
        
        .editable-field:focus {
            background: rgba(59, 130, 246, 0.15);
            border-bottom: 2px solid #3b82f6;
            color: #000;
        }
        
        .editable-field.saving {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .editable-field.saved {
            animation: save-flash 0.5s ease;
        }
        
        @keyframes save-flash {
            0%, 100% { background: transparent; }
            50% { background: rgba(34, 197, 94, 0.3); }
        }
        
        .editable-field.error {
            border-bottom-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .read-only-contract-fields .editable-field,
        .editable-field.readonly-field {
            color: #000 !important;
            cursor: default !important;
            pointer-events: none !important;
            user-select: text;
            background: transparent !important;
            border-bottom: 1px dotted #000 !important;
            animation: none !important;
        }

        .read-only-contract-fields .editable-hint {
            display: none !important;
        }
        
        /* Tooltip for editable fields */
        .editable-hint {
            position: fixed;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            pointer-events: none;
            z-index: 1000;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .editable-hint.show {
            opacity: 1;
        }
        
        @media print {
            .editable-field {
                cursor: default;
                background: transparent !important;
                animation: none !important;
            }
            .editable-field:empty::before,
            .editable-field.needs-input::before {
                content: '';
            }
            .editable-hint { display: none !important; }
        }
        
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
<body class="<?php echo $canEditTenantFields ? 'tenant-editable-contract' : 'read-only-contract-fields'; ?>">
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
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3.&nbsp;&nbsp; ชื่อ <span class="editable-field underline-long <?php echo $canEditTenantFields && (empty($contract['tnt_name']) || $contract['tnt_name'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_name" data-type="firstname" data-placeholder="กรอกชื่อ..."><?php $fn = firstNameWithoutSurname($contract['tnt_name'] ?? ''); echo ($fn === '-' ? '' : h($fn)); ?></span>
                สกุล <span class="editable-field underline-long <?php echo $canEditTenantFields && (empty($contract['tnt_name']) || $contract['tnt_name'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_name" data-type="lastname" data-placeholder="กรอกนามสกุล..."><?php $ln = surnameFromFullName($contract['tnt_name'] ?? ''); echo ($ln === '-' ? '' : h($ln)); ?></span>
                อายุ <span class="editable-field underline-short <?php echo $canEditTenantFields && (empty($contract['tnt_age']) || $contract['tnt_age'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_age" data-placeholder="..." data-maxlength="3" data-minlength="2" data-type-validate="number"><?php $age = $contract['tnt_age'] ?? ''; echo ($age === '-' ? '' : h($age)); ?></span> ปี
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                เลขประจำตัวบัตรประชาชน <span class="editable-field underline-mid <?php echo $canEditTenantFields && (empty($contract['tnt_idcard']) || $contract['tnt_idcard'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_idcard" data-placeholder="กรอกเลขบัตร..." id="idcard-primary" data-maxlength="13" data-minlength="13" data-type-validate="number"><?php $idc = $contract['tnt_idcard'] ?? ''; echo ($idc === '-' ? '' : h($idc)); ?></span>
                สถานศึกษา <span class="editable-field underline-long <?php echo $canEditTenantFields && (empty($contract['tnt_education']) || $contract['tnt_education'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_education" data-placeholder="กรอกสถานศึกษา..."><?php $edu = $contract['tnt_education'] ?? ''; echo ($edu === '-' ? '' : h($edu)); ?></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                คณะ <span class="editable-field underline-long <?php echo $canEditTenantFields && (empty($contract['tnt_faculty']) || $contract['tnt_faculty'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_faculty" data-placeholder="กรอกคณะ..."><?php $fac = $contract['tnt_faculty'] ?? ''; echo ($fac === '-' ? '' : h($fac)); ?></span>
                ปีที่ <span class="editable-field underline-short <?php echo $canEditTenantFields && (empty($contract['tnt_year']) || $contract['tnt_year'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_year" data-placeholder="..."><?php $yr = formatYearValue($contract['tnt_year'] ?? ''); echo ($yr === '-' ? '' : h($yr)); ?></span>
                มีรถจักรยานยนต์หมายเลขทะเบียน <span class="editable-field underline-wide <?php echo $canEditTenantFields && (empty($contract['tnt_vehicle']) || $contract['tnt_vehicle'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_vehicle" data-placeholder="กรอกเลขทะเบียน..."><?php $veh = $contract['tnt_vehicle'] ?? ''; echo ($veh === '-' ? '' : h($veh)); ?></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                เบอร์โทร <span class="editable-field underline-phone <?php echo $canEditTenantFields && (empty($contract['tnt_phone']) || $contract['tnt_phone'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_phone" data-placeholder="กรอกเบอร์..." data-maxlength="10" data-minlength="10" data-type-validate="number"><?php $phone = $contract['tnt_phone'] ?? ''; echo ($phone === '-' ? '' : h($phone)); ?></span>
                เบอร์โทรผู้ปกครอง <span class="editable-field underline-phone <?php echo $canEditTenantFields && (empty($contract['tnt_parentsphone']) || $contract['tnt_parentsphone'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_parentsphone" data-placeholder="กรอกเบอร์..." data-maxlength="10" data-minlength="10" data-type-validate="number"><?php $pphone = $contract['tnt_parentsphone'] ?? ''; echo ($pphone === '-' ? '' : h($pphone)); ?></span>
                บัตรประจำตัวประชาชน <span class="underline underline-long" id="idcard-secondary"><?php $idc2 = $contract['tnt_idcard'] ?? ''; echo ($idc2 === '-' ? '' : h($idc2)); ?></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left; display: flex; align-items: flex-end; gap: 6px;">
                ที่อยู่ตามบัตร <span class="editable-field underline-xl <?php echo $canEditTenantFields && (empty($contract['tnt_address']) || $contract['tnt_address'] === '-') ? 'needs-input' : ''; ?>" contenteditable="<?php echo $canEditTenantFields ? 'true' : 'false'; ?>" data-field="tnt_address" data-placeholder="กรอกที่อยู่..." style="flex: 1; justify-content: flex-start; text-align: left;"><?php $addr = $contract['tnt_address'] ?? ''; echo ($addr === '-' ? '' : h($addr)); ?></span>
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ซึ่งต่อไปนี้ในสัญญานี้เรียกว่า "ผู้เช่า" อีกฝ่ายหนึ่ง ทั้งสองฝ่ายตกลงทำสัญญากันดังนี้มีข้อความต่อไปนี้ คือ
            </div>
            <div class="form-field" style="border: none; font-size: 13.5px; text-align: left; white-space: normal; word-break: break-word;">
                ข้อ 1. ผู้ให้เช่าตกลงให้เช่าและผู้เช่าตกลงเช่า ค่าห้องราคา <span class="underline underline-mid" style="min-width: 80px; padding: 0 3px 0;"><?php echo number_format((float)($contract['type_price'] ?? 0), 2); ?></span> บาท เงินประกัน 2,000 บาท (สองพันบาทถ้วน)
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left;">
                ค่าไฟฟ้าและค่าน้ำแยกต่างหากประกันจะคืนให้เมื่อเช่าหอพักครบตามกำหนด โดยวัตถุประสงค์เป็นที่อยู่อาศัยมีกำหนด
            </div>
            <div class="form-field" style="border: none; font-size: 14px; text-align: left; display: flex; flex-wrap: wrap; align-items: center; gap: 4px;">
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
                ข้อ 5. ถ้ามีสิ่งใดเสียหายผู้เช่ายินดีชดใช้ และให้หักเงินประกัน
            </div>
        </div>
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-row">
                    <?php
                    // Check if tenant has already signed
                    $tenantSignature = null;
                    $tenantSignedAt = null;
                    try {
                        $sigStmt = $pdo->prepare("SELECT signature_file, signed_at FROM signature_logs WHERE contract_id = ? AND signer_type = 'tenant' ORDER BY signed_at DESC LIMIT 1");
                        $sigStmt->execute([$ctr_id]);
                        $tenantSig = $sigStmt->fetch(PDO::FETCH_ASSOC);
                        if ($tenantSig) {
                            $tenantSignature = $tenantSig['signature_file'];
                            $tenantSignedAt = $tenantSig['signed_at'];
                        }
                    } catch (Exception $e) {
                        // Table might not exist yet
                    }
                    ?>
                    <?php if (!empty($tenantSignature)): ?>
                    <div class="signature-protected" style="margin-left: 5rem; margin-right: 3rem;">
                        <img src="/dormitory_management/Public/Assets/Signatures/<?php echo htmlspecialchars($tenantSignature); ?>" 
                             alt="ลายเซ็นผู้เช่า" 
                             style="height: 50px; max-width: 150px; object-fit: contain;"
                             oncontextmenu="return false;"
                             ondragstart="return false;">
                        <div class="signature-watermark" style="font-size: 8px;">
                            <?php echo thaiDate($tenantSignedAt, 'short_time'); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php if ($isTenantAccess && !$isAdminOrOwnerAccess): ?>
                    <button type="button" class="sign-btn no-print" onclick="openSignatureModal({
                        contractId: <?php echo $ctr_id; ?>,
                        signerType: 'tenant',
                        signerName: '<?php echo addslashes(h(nameWithoutNickname($contract['tnt_name'] ?? ''))); ?>',
                        documentName: 'สัญญาเช่าห้อง <?php echo addslashes(h($contract['room_number'] ?? '')); ?>'
                    })">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                            <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                        </svg>
                        คลิกเพื่อลงลายมือชื่อ
                    </button>
                    <?php endif; ?>
                    <span class="signature-line print-only"></span>
                    <?php endif; ?>
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
                    <?php if (!empty($ownerSignature)): ?>
                    <div class="signature-protected">
                        <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($ownerSignature); ?>" 
                             alt="ลายเซ็น" 
                             style="height: 50px; max-width: 150px; object-fit: contain;margin-left: 5.5rem;margin-right: 4.5rem;"
                             oncontextmenu="return false;"
                             ondragstart="return false;">
                        <div class="signature-watermark">
                            <?php echo htmlspecialchars(nameWithoutNickname($contract['tnt_name'] ?? '')); ?><br>
                            #<?php echo str_pad((string)$ctr_id, 4, '0', STR_PAD_LEFT); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <span class="signature-line"></span>
                    <?php endif; ?>
                    <span class="signature-label">ผู้ให้เช่า</span>
                </div>
                <div class="signature-row">
                    <span class="signature-paren">(</span>
                    <span class="signature-line" style="width: 220px; text-align: center; line-height: 1.4;"><?php echo htmlspecialchars($ownerName); ?></span>
                    <span class="signature-paren">)</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Screen watermark (hidden on print) -->
    <div class="screen-watermark">
        สัญญา #<?php echo str_pad((string)$ctr_id, 4, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars(nameWithoutNickname($contract['tnt_name'] ?? '')); ?>
    </div>
    
    <script>
        // Security: Disable right-click context menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Security: Disable common screenshot shortcuts
        document.addEventListener('keydown', function(e) {
            // Disable F12, Ctrl+Shift+I, Ctrl+Shift+C, Ctrl+U
            if (e.keyCode === 123 || 
                (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 67)) ||
                (e.ctrlKey && e.keyCode === 85)) {
                e.preventDefault();
                return false;
            }
            
            // Disable Print Screen (partially - can't fully block)
            if (e.keyCode === 44) {
                e.preventDefault();
                alert('การจับภาพหน้าจอถูกปิดใช้งานเพื่อความปลอดภัยของเอกสาร');
                return false;
            }
            
            // Disable Ctrl+P (print) - force use browser print button instead
            if (e.ctrlKey && e.keyCode === 80) {
                e.preventDefault();
                window.print();
                return false;
            }
        });
        
        // Disable drag and drop for all images
        document.querySelectorAll('img').forEach(function(img) {
            img.addEventListener('dragstart', function(e) {
                e.preventDefault();
                return false;
            });
        });
        
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

        // ===== EDITABLE FIELDS AUTO-SAVE =====
        const canEditTenantFields = <?php echo $canEditTenantFields ? 'true' : 'false'; ?>;
        const editableFields = document.querySelectorAll('.editable-field');
        let saveTimeout = null;
        let hint = null;

        if (!canEditTenantFields) {
            editableFields.forEach((el) => {
                el.setAttribute('contenteditable', 'false');
                el.classList.remove('needs-input', 'saving', 'saved', 'error');
                el.classList.add('readonly-field');
            });
        } else {
        
        // Create hint tooltip
        function createHint() {
            if (!hint) {
                hint = document.createElement('div');
                hint.className = 'editable-hint';
                document.body.appendChild(hint);
            }
            return hint;
        }
        
        // Show hint near element
        function showHint(el, message) {
            const h = createHint();
            const rect = el.getBoundingClientRect();
            h.textContent = message;
            h.style.left = rect.left + 'px';
            h.style.top = (rect.bottom + 8) + 'px';
            h.classList.add('show');
        }
        
        function hideHint() {
            if (hint) hint.classList.remove('show');
        }
        
        // Save field value via AJAX
        async function saveField(el) {
            const field = el.dataset.field;
            let value = el.textContent.trim();
            
            // Handle name fields specially (combine firstname + lastname)
            if (field === 'tnt_name') {
                const type = el.dataset.type;
                const firstnameEl = document.querySelector('.editable-field[data-field="tnt_name"][data-type="firstname"]');
                const lastnameEl = document.querySelector('.editable-field[data-field="tnt_name"][data-type="lastname"]');
                const firstname = firstnameEl ? firstnameEl.textContent.trim() : '';
                const lastname = lastnameEl ? lastnameEl.textContent.trim() : '';
                value = (firstname + ' ' + lastname).trim();
            }
            
            // Skip if empty placeholder
            if (value === '-' || value === '' || value === el.dataset.placeholder) {
                return;
            }
            
            el.classList.add('saving');
            showHint(el, '💾 กำลังบันทึก...');
            
            try {
                const formData = new FormData();
                formData.append('ajax_update', '1');
                formData.append('field', field);
                formData.append('value', value);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                el.classList.remove('saving');
                
                if (result.success) {
                    el.classList.add('saved');
                    el.classList.remove('needs-input');
                    showHint(el, '✓ บันทึกแล้ว');
                    setTimeout(() => {
                        el.classList.remove('saved');
                        hideHint();
                    }, 1500);
                    
                    // Sync ID card fields
                    if (field === 'tnt_idcard') {
                        const secondaryIdCard = document.getElementById('idcard-secondary');
                        if (secondaryIdCard) {
                            secondaryIdCard.textContent = value;
                        }
                    }
                } else {
                    el.classList.add('error');
                    showHint(el, '❌ เกิดข้อผิดพลาด');
                    setTimeout(() => {
                        el.classList.remove('error');
                        hideHint();
                    }, 2000);
                }
            } catch (err) {
                el.classList.remove('saving');
                el.classList.add('error');
                showHint(el, '❌ บันทึกไม่สำเร็จ');
                setTimeout(() => {
                    el.classList.remove('error');
                    hideHint();
                }, 2000);
            }
        }
        
        // Setup editable fields
        editableFields.forEach(el => {
            // Pre-clear "-" on load so we don't disrupt Safari's focus behavior
            if (el.textContent.trim() === '-') {
                el.textContent = '';
            }
            
            // Clear placeholder text or "-" on focus
            el.addEventListener('focus', function() {
                showHint(this, '✏️ พิมพ์ข้อมูลแล้วคลิกที่อื่นเพื่อบันทึก');
            });
            
            // Save on blur
            el.addEventListener('blur', function() {
                hideHint();
                if (this.textContent.trim() === '') {
                    this.classList.add('needs-input');
                } else {
                    saveField(this);
                }
            });
            
            // Auto-save after typing stops + validate maxlength
            el.addEventListener('input', function(e) {
                const maxLength = this.dataset.maxlength ? parseInt(this.dataset.maxlength) : null;
                const minLength = this.dataset.minlength ? parseInt(this.dataset.minlength) : null;
                const typeValidate = this.dataset.typeValidate;
                
                let text = this.textContent;
                let needsUpdate = false;
                
                // Remove non-numeric characters if number-only field
                if (typeValidate === 'number') {
                    const cleanText = text.replace(/[^0-9]/g, '');
                    if (cleanText !== text) {
                        text = cleanText;
                        needsUpdate = true;
                    }
                }
                
                // Enforce maxlength
                if (maxLength && text.length > maxLength) {
                    text = text.substring(0, maxLength);
                    needsUpdate = true;
                    showHint(this, `❌ กรอกได้สูงสุด ${maxLength} หลัก`);
                    setTimeout(() => hideHint(), 1500);
                }
                
                // Update text if changed
                if (needsUpdate) {
                    // Save current selection
                    const sel = window.getSelection();
                    const cursorPos = Math.min(sel.anchorOffset || 0, text.length);
                    
                    // Update content
                    this.textContent = text;
                    
                    // Restore cursor position
                    if (this.firstChild) {
                        try {
                            const range = document.createRange();
                            range.setStart(this.firstChild, cursorPos);
                            range.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(range);
                        } catch (e) {
                            // Ignore range errors
                        }
                    }
                }
                
                // Auto-save after typing stops
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    if (this.textContent.trim() !== '' && this.textContent.trim() !== '-') {
                        saveField(this);
                    }
                }, 1500);
            });
            
            // Prevent Enter key from creating new lines and validate input
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                    return;
                }
                
                // Check maxlength before allowing keypress
                const maxLength = parseInt(this.dataset.maxlength);
                if (maxLength && this.textContent.length >= maxLength) {
                    // Allow: backspace, delete, tab, escape, arrow keys, select all
                    if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' || 
                        e.key === 'Escape' || e.key.startsWith('Arrow') || 
                        (e.ctrlKey && e.key === 'a') || (e.metaKey && e.key === 'a') ||
                        e.ctrlKey || e.metaKey) {
                        return;
                    }
                    e.preventDefault();
                    showHint(this, `❌ กรอกได้สูงสุด ${maxLength} หลัก`);
                    setTimeout(() => hideHint(), 1500);
                    return;
                }
                
                // Validate number-only fields
                if (this.dataset.typeValidate === 'number') {
                    // Allow: backspace, delete, tab, escape, arrow keys, numbers
                    if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' || 
                        e.key === 'Escape' || e.key.startsWith('Arrow') || e.ctrlKey || e.metaKey) {
                        return;
                    }
                    // Check if it's a number
                    if (!/^[0-9]$/.test(e.key)) {
                        e.preventDefault();
                        showHint(this, '❌ กรอกได้เฉพาะตัวเลข');
                        setTimeout(() => hideHint(), 1500);
                    }
                }
            });
            
            // Handle paste event
            el.addEventListener('paste', function(e) {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text');
                const maxLength = parseInt(this.dataset.maxlength);
                const typeValidate = this.dataset.typeValidate;
                
                let cleanText = text;
                
                // Remove non-numeric if number-only
                if (typeValidate === 'number') {
                    cleanText = cleanText.replace(/[^0-9]/g, '');
                }
                
                // Limit to maxlength
                if (maxLength && cleanText.length > maxLength) {
                    cleanText = cleanText.substring(0, maxLength);
                    showHint(this, `❌ กรอกได้สูงสุด ${maxLength} หลัก`);
                    setTimeout(() => hideHint(), 1500);
                }
                
                // Insert cleaned text
                document.execCommand('insertText', false, cleanText);
            });
            
            // Show hint on hover for empty fields
            el.addEventListener('mouseenter', function() {
                if (this.classList.contains('needs-input') || this.textContent.trim() === '-') {
                    showHint(this, '👆 คลิกเพื่อกรอกข้อมูล');
                }
            });
            
            el.addEventListener('mouseleave', function() {
                if (!this.matches(':focus')) {
                    hideHint();
                }
            });
        });

        // === SPECIFIC VALIDATION FOR ID CARD FIELD (max 13 digits) ===
        const idcardField = document.getElementById('idcard-primary');
        if (idcardField) {
            idcardField.addEventListener('focus', function(e) {
                showHint(this, '✏️ พิมพ์ข้อมูลแล้วคลิกที่อื่นเพื่อบันทึก');
            }, true); // capture phase
            
            // Force max 13 digits on every input - use capture phase
            idcardField.addEventListener('input', function(e) {
                // Remove ALL non-digit characters (including "-")
                let text = this.textContent.replace(/[^0-9]/g, '');
                
                // Limit to 13 digits
                if (text.length > 13) {
                    text = text.substring(0, 13);
                    showHint(this, '❌ เลขบัตรประชาชนต้องไม่เกิน 13 หลัก');
                    setTimeout(() => hideHint(), 1500);
                }
                
                // Update if changed
                if (this.textContent !== text) {
                    this.textContent = text;
                    // Move cursor to end
                    if (text.length > 0) {
                        const range = document.createRange();
                        const sel = window.getSelection();
                        range.selectNodeContents(this);
                        range.collapse(false); // collapse to end
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }
                }
                
                // Auto-save after typing stops
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    if (this.textContent.trim() !== '' && this.textContent.trim() !== '-') {
                        saveField(this);
                    }
                }, 1500);
            }, true); // capture phase - runs first
            
            // Save on blur
            idcardField.addEventListener('blur', function() {
                hideHint();
                if (this.textContent.trim() === '' || this.textContent.trim() === '-') {
                    this.classList.add('needs-input');
                } else {
                    saveField(this);
                    // Also sync to secondary field
                    const secondary = document.getElementById('idcard-secondary');
                    if (secondary) {
                        secondary.textContent = this.textContent;
                    }
                }
            }, true);
            
            // Block input if already 13 digits or non-digit
            idcardField.addEventListener('keydown', function(e) {
                // Allow control keys
                if (['Backspace', 'Delete', 'Tab', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(e.key) ||
                    e.ctrlKey || e.metaKey) {
                    return;
                }
                
                // Block Enter
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                    return;
                }
                
                // Block non-digits
                if (!/^[0-9]$/.test(e.key)) {
                    e.preventDefault();
                    showHint(this, '❌ กรอกได้เฉพาะตัวเลข');
                    setTimeout(() => hideHint(), 1500);
                    return;
                }
                
                // Get current digits only
                const currentDigits = this.textContent.replace(/[^0-9]/g, '');
                
                // Block if already 13 digits
                if (currentDigits.length >= 13) {
                    e.preventDefault();
                    showHint(this, '❌ เลขบัตรประชาชนต้องไม่เกิน 13 หลัก');
                    setTimeout(() => hideHint(), 1500);
                }
            }, true); // capture phase
            
            // Handle paste - limit to 13 digits
            idcardField.addEventListener('paste', function(e) {
                e.preventDefault();
                
                let pastedText = (e.clipboardData || window.clipboardData).getData('text');
                // Remove non-digits and limit to 13
                pastedText = pastedText.replace(/[^0-9]/g, '').substring(0, 13);
                
                // Clear current content and insert
                this.textContent = pastedText;
                
                // Move cursor to end
                if (pastedText.length > 0) {
                    const range = document.createRange();
                    const sel = window.getSelection();
                    range.selectNodeContents(this);
                    range.collapse(false);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                
                showHint(this, '✓ วางข้อความแล้ว');
                setTimeout(() => hideHint(), 1000);
                
                // Auto-save
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    if (this.textContent.trim() !== '') {
                        saveField(this);
                    }
                }, 1500);
            }, true); // capture phase
        }

        }

        // Auto-print disabled
    </script>
    
    <!-- Include Signature Modal -->
    <?php include_once __DIR__ . '/../includes/signature_modal.php'; ?>
</body>
</html>
