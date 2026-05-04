<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ดึงค่า default_view_mode จาก database
$defaultViewMode = 'grid';
try {
    $viewStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_view_mode' LIMIT 1");
    $viewRow = $viewStmt->fetch(PDO::FETCH_ASSOC);
    if ($viewRow && strtolower($viewRow['setting_value']) === 'list') {
        $defaultViewMode = 'list';
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// รับค่า sort จาก query parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'ym DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'ym ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'ym DESC';
}

$stmt = $pdo->query("SELECT DATE_FORMAT(p.pay_date, '%Y-%m') AS ym, SUM(p.pay_amount) AS total_received FROM payment p WHERE p.pay_status = '1' GROUP BY ym ORDER BY $orderBy");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
?>

<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานรายรับ</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
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

      #table-view .datatable-wrapper {
        background: #ffffff !important;
        color: #0f172a !important;
      }

      #table-view .datatable-wrapper .datatable-input,
      #table-view .datatable-wrapper .datatable-selector {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        color: #0f172a !important;
      }

      #table-view .datatable-wrapper .datatable-input::placeholder {
        color: #94a3b8 !important;
      }

      #table-view .datatable-wrapper table thead {
        background: #f8fafc !important;
      }

      #table-view .datatable-wrapper table thead th {
        color: #475569 !important;
        border-bottom: 2px solid #e2e8f0 !important;
      }

      #table-view .datatable-wrapper table tbody tr {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
        transition: background-color 0.2s;
      }

      #table-view .datatable-wrapper table tbody td {
        color: #334155 !important;
        border-bottom: 1px solid #e2e8f0 !important;
        font-weight: 500;
      }

      #table-view .datatable-wrapper table tbody tr:hover {
        background: #f8fafc !important;
      }

      #table-view .datatable-wrapper .datatable-info {
        color: #475569 !important;
      }

      #table-view .datatable-wrapper .datatable-pagination-list-item a,
      #table-view .datatable-wrapper .datatable-pagination-list-item button,
      #table-view .datatable-wrapper .datatable-pagination-list-item .datatable-pagination-list-item-link {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        color: #64748b !important;
      }

      #table-view .datatable-wrapper .datatable-pagination-list-item a:hover,
      #table-view .datatable-wrapper .datatable-pagination-list-item button:hover,
      #table-view .datatable-wrapper .datatable-pagination-list-item .datatable-pagination-list-item-link:hover {
        background: #f1f5f9 !important;
        color: #0ea5e9 !important;
      }

      /* Mobile Responsive Styles */
      @media (max-width: 768px) {
        /* Toggle View Button */
        #toggle-view {
          padding: 0.5rem 0.75rem !important;
          font-size: 0.85rem !important;
        }
        
        #toggle-view svg {
          width: 14px !important;
          height: 14px !important;
        }
        
        .app-main > div > div {
          padding: 0 0.5rem !important;
          margin-bottom: 0.75rem !important;
        }
        
        /* Section Header */
        .section-header {
          flex-direction: column !important;
          align-items: flex-start !important;
          gap: 0.75rem !important;
        }
        
        .section-header > div:first-child {
          width: 100%;
        }
        
        .section-header h1 {
          font-size: 1.25rem !important;
        }
        
        .section-header p {
          font-size: 0.85rem !important;
        }
        
        #sortSelect {
          width: 100%;
          padding: 0.5rem 0.75rem !important;
          font-size: 0.9rem !important;
        }
        
        /* Card View Responsive */
        #card-view {
          grid-template-columns: 1fr !important;
          gap: 1rem !important;
          margin-top: 1rem !important;
        }
        
        #card-view > div {
          padding: 1.25rem !important;
        }
        
        /* Table View Mobile */
        #table-view {
          margin-top: 1rem !important;
          overflow-x: visible !important;
        }
        
        #table-revenue {
          display: block !important;
        }
        
        #table-revenue thead {
          display: none !important;
        }
        
        #table-revenue tbody {
          display: block !important;
        }
        
        #table-revenue tbody tr {
          display: block !important;
          background: #ffffff !important;
          border: 1px solid #e2e8f0 !important;
          border-radius: 8px !important;
          margin-bottom: 1rem !important;
          padding: 1rem !important;
        }
        
        #table-revenue tbody td {
          display: flex !important;
          justify-content: space-between !important;
          align-items: center !important;
          padding: 0.75rem 0 !important;
          border-bottom: 1px solid #e2e8f0 !important;
        }
        
        #table-revenue tbody td:last-child {
          border-bottom: none !important;
          padding-top: 1rem !important;
          margin-top: 0.5rem !important;
          border-top: 1px solid #e2e8f0 !important;
        }
        
        #table-revenue tbody td::before {
          content: attr(data-label);
          font-weight: 600;
          color: #64748b;
          font-size: 0.85rem;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }
        
        /* DataTable Controls Mobile */
        .datatable-top {
          flex-direction: column !important;
          gap: 0.75rem !important;
          padding: 0 0 1rem 0 !important;
        }
        
        .datatable-search {
          width: 100% !important;
          margin: 0 !important;
        }
        
        .datatable-search input {
          width: 100% !important;
          padding: 0.6rem 1rem !important;
          font-size: 0.9rem !important;
        }
        
        .datatable-dropdown {
          width: 100% !important;
        }
        
        .datatable-selector {
          width: 100% !important;
          padding: 0.6rem 1rem !important;
          font-size: 0.9rem !important;
        }
        
        .datatable-bottom {
          flex-direction: column !important;
          align-items: stretch !important;
          gap: 0.75rem !important;
        }
        
        .datatable-info {
          text-align: center !important;
          order: 1;
          font-size: 0.85rem !important;
        }
        
        .datatable-pagination {
          margin: 0 !important;
          order: 2;
        }
        
        .datatable-pagination ul {
          justify-content: center !important;
          flex-wrap: wrap;
        }
        
        .datatable-pagination li {
          margin: 0.25rem !important;
        }
        
        .datatable-pagination a {
          padding: 0.4rem 0.7rem !important;
          font-size: 0.85rem !important;
          min-width: 32px !important;
        }
      }
      
      @media (max-width: 480px) {
        #toggle-view {
          padding: 0.4rem 0.6rem !important;
          font-size: 0.8rem !important;
        }
        
        .section-header h1 {
          font-size: 1.1rem !important;
        }
        
        #card-view {
          gap: 0.75rem !important;
        }
        
        #card-view > div {
          padding: 1rem !important;
        }
        
        #table-revenue tbody tr {
          padding: 0.875rem !important;
        }
        
        #table-revenue tbody td {
          padding: 0.625rem 0 !important;
          font-size: 0.9rem !important;
        }
        
        #table-revenue tbody td::before {
          font-size: 0.75rem !important;
        }
        
        .datatable-pagination a {
          padding: 0.35rem 0.6rem !important;
          font-size: 0.8rem !important;
          min-width: 28px !important;
        }
      }
    </style>
  



<style>
/* Fix margins all report pages v2 */
main > div:first-of-type,
.app-main > div:first-of-type, 
.main-content > div:first-of-type, 
.reports-container > div:first-of-type {
    max-width: 1280px !important;
    margin: 0 auto !important;
    padding: 20px !important;
    box-sizing: border-box;
}
@media (max-width: 768px) {
    main > div:first-of-type,
    .app-main > div:first-of-type, 
    .main-content > div:first-of-type, 
    .reports-container > div:first-of-type {
        padding: 10px !important;
    }
}
</style>

</head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div style="width:100%;">
          <?php $pageTitle = 'รายงานรายรับ'; include __DIR__ . '/../includes/page_header.php'; ?>
          
          <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:1rem;padding:0 1rem;">
            <button id="toggle-view" aria-label="Toggle view" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:0.5rem 1rem;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;transition:all 0.3s ease;display:inline-flex;align-items:center;gap:0.4rem;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>ตาราง</button>
          </div>

          <section style="margin:1rem 0 0;padding:2rem;border-radius:16px;background:#ffffff;box-shadow:0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);color:#1e293b;border:1px solid #f1f5f9;">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1.5rem;flex-wrap:wrap;border-bottom:1px solid #e2e8f0;padding-bottom:1.25rem;">
              <div>
                <h1 style="font-size:1.5rem;font-weight:700;color:#0f172a;letter-spacing:-0.025em;margin:0;">รายงานรายรับประจำเดือน</h1>
                <p style="color:#64748b;margin-top:0.4rem;font-size:0.95rem;font-weight:400;">สรุปยอดการชำระเงินของผู้เช่า (SaaS Overview)</p>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 2.5rem 0.6rem 1rem;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'/></svg>') no-repeat right 0.75rem center/16px 16px;color:#334155;font-size:0.95rem;font-weight:500;cursor:pointer;box-shadow:0 1px 2px 0 rgba(0,0,0,0.05);appearance:none;-webkit-appearance:none;">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>เรียง: เดือนล่าสุด</option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>เรียง: เดือนเก่าสุด</option>
              </select>
            </div>

            <!-- Card View -->
            <div id="card-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;margin-top:2rem;">
<?php foreach($rows as $r): ?>
              <?php
                $monthName = '';
                $parts = explode('-', $r['ym']);
                if (count($parts) === 2) {
                  $year = (int)$parts[0];
                  $month = (int)$parts[1];
                  $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                  $monthName = $months[$month] . ' ' . ($year + 543);
                }
                $totalAmount = (int)($r['total_received'] ?? 0);
              ?>
              <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:1.5rem;display:flex;flex-direction:column;gap:1.25rem;text-align:center;box-shadow:0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05)'">
                <div>
                  <div style="font-size:0.75rem;color:#64748b;margin-bottom:0.3rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">รอบบิลประจำเดือน</div>
                  <div style="font-size:1.15rem;font-weight:700;color:#0f172a;"><?php echo $monthName; ?></div>
                </div>
                
                <div style="background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);padding:1.5rem 1rem;border-radius:12px;border:1px solid #bae6fd;">
                  <div style="font-size:0.875rem;color:#0284c7;margin-bottom:0.4rem;font-weight:600;">ยอดรับชำระสุทธิ</div>
                  <div style="font-size:2.25rem;font-weight:800;color:#0369a1;letter-spacing:-0.025em;line-height:1;">฿<?php echo number_format($totalAmount); ?></div>
                </div>

                <div style="padding-top:1rem;border-top:1px dashed #cbd5e1;">
                  <div style="font-size:0.8rem;color:#64748b;font-weight:500;">สรุปยอดเงินเข้าจากทุกช่องทาง</div>
                </div>
              </div>
<?php endforeach; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" style="display:none;margin-top:1.5rem;overflow-x:auto;">
              <table id="table-revenue" class="table--compact" style="width:100%;border-collapse:collapse;">
                <thead>
                  <tr style="text-align:left;border-bottom:2px solid #e2e8f0;background:#f8fafc;">
                    <th style="padding:0.75rem;color:#475569;">เดือน</th>
                    <th style="padding:0.75rem;color:#475569;text-align:right;">ยอดรับรวม</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach($rows as $r): ?>
                  <?php
                    $monthName = '';
                    $parts = explode('-', $r['ym']);
                    if (count($parts) === 2) {
                      $year = (int)$parts[0];
                      $month = (int)$parts[1];
                      $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                      $monthName = $months[$month] . ' ' . ($year + 543);
                    }
                    $totalAmount = (int)($r['total_received'] ?? 0);
                  ?>
                  <tr style="border-bottom:1px solid #e2e8f0;background:#ffffff;">
                    <td data-label="เดือน" style="padding:0.75rem;color:#0f172a;"><?php echo $monthName; ?></td>
                    <td data-label="ยอดรับรวม" style="padding:0.75rem;color:#0284c7;text-align:right;font-weight:700;font-size:1.1rem;">฿<?php echo number_format($totalAmount); ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>
    </div>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script>
      (function() {
        // View Toggle
        const viewToggle = document.getElementById('toggle-view');
        const cardView = document.getElementById('card-view');
        const tableView = document.getElementById('table-view');
        // Get default view mode from database (list -> false, grid -> true)
        let isCardView = <?php echo $defaultViewMode === "list" ? "false" : "true"; ?>;

        function updateViewDisplay() {
          if (isCardView) {
            cardView.style.display = 'grid';
            tableView.style.display = 'none';
            if (viewToggle) viewToggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>ตาราง';
          } else {
            cardView.style.display = 'none';
            tableView.style.display = 'block';
            if (viewToggle) viewToggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>การ์ด';
          }
        }

        // Initialize view on page load
        updateViewDisplay();

        if (viewToggle) {
          viewToggle.addEventListener('click', function() {
            isCardView = !isCardView;
            updateViewDisplay();
          });
        }
      })();

      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }
    </script>
    
    <!-- DataTable Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const revenueTable = document.getElementById('table-revenue');
        if (revenueTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(revenueTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50],
            labels: {
              placeholder: 'ค้นหาเดือน...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลรายรับ',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>


</html>

