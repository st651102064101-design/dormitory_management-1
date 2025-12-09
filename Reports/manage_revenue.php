<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

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

// Sum of payments grouped by month
$stmt = $pdo->query("SELECT DATE_FORMAT(p.pay_date, '%Y-%m') AS ym, SUM(p.pay_amount) AS total_received FROM payment p GROUP BY ym ORDER BY $orderBy");
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
} catch (PDOException $e) {}
?>

<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานรายรับ</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div style="width:100%;">
          <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <div style="display:flex;align-items:center;gap:0.5rem">
              <button id="sidebar-toggle" aria-label="Toggle sidebar" aria-expanded="true" style="background:transparent;border:0;color:#fff;padding:0.6rem 0.85rem;border-radius:6px;cursor:pointer;font-size:1.25rem">☰</button>
              <h2 style="margin:0;color:#fff;font-size:1.05rem">รายงานรายรับ</h2>
            </div>
            <button id="toggle-view" aria-label="Toggle view" style="background:#334155;border:1px solid #475569;color:#fff;padding:0.5rem 1rem;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;transition:all 0.3s ease;margin-right:1rem;">📊 ตาราง</button>
          </header>

          <section style="margin:1rem;padding:1.25rem 1rem;border-radius:1rem;background:linear-gradient(180deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));color:#f5f8ff">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>รายงานรายรับประจำเดือน</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">สรุปยอดการชำระเงินของผู้เช่า</p>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>เดือนล่าสุด</option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>เดือนเก่าสุด</option>
              </select>
            </div>

            <!-- Card View -->
            <div id="card-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;margin-top:1.5rem;">
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
              <div style="background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;text-align:center;">
                <div>
                  <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.5rem;">เดือน</div>
                  <div style="font-size:1.25rem;font-weight:700;color:#fff;"><?php echo $monthName; ?></div>
                </div>
                
                <div style="background:linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);padding:1.5rem;border-radius:8px;margin-top:0.5rem;">
                  <div style="font-size:0.875rem;color:#e0f2fe;margin-bottom:0.5rem;">ยอดรับรวม</div>
                  <div style="font-size:2.5rem;font-weight:700;color:#fff;">฿<?php echo number_format($totalAmount); ?></div>
                </div>

                <div style="padding-top:1rem;border-top:1px solid #475569;">
                  <div style="font-size:0.75rem;color:#94a3b8;">รวมยอดการชำระเงินทั้งสิ้น</div>
                </div>
              </div>
<?php endforeach; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" style="display:none;margin-top:1.5rem;overflow-x:auto;">
              <table class="table--compact" style="width:100%;border-collapse:collapse;">
                <thead>
                  <tr style="text-align:left;border-bottom:2px solid #475569;background:#0f172a;">
                    <th style="padding:0.75rem;color:#94a3b8;">เดือน</th>
                    <th style="padding:0.75rem;color:#94a3b8;text-align:right;">ยอดรับรวม</th>
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
                  <tr style="border-bottom:1px solid #334155;background:#1e293b;">
                    <td style="padding:0.75rem;color:#fff;"><?php echo $monthName; ?></td>
                    <td style="padding:0.75rem;color:#0ea5e9;text-align:right;font-weight:700;font-size:1.1rem;">฿<?php echo number_format($totalAmount); ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>
    </div>
    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script>
      (function() {
        // View Toggle
        const viewToggle = document.getElementById('toggle-view');
        const cardView = document.getElementById('card-view');
        const tableView = document.getElementById('table-view');
        let isCardView = true;

        if (viewToggle) {
          viewToggle.addEventListener('click', function() {
            isCardView = !isCardView;
            if (isCardView) {
              cardView.style.display = 'grid';
              tableView.style.display = 'none';
              viewToggle.textContent = '📊 ตาราง';
            } else {
              cardView.style.display = 'none';
              tableView.style.display = 'block';
              viewToggle.textContent = '🃏 การ์ด';
            }
          });
        }
      })();

      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }
    </script>


</html>

