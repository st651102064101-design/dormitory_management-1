<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT r.*, c.ctr_id, t.tnt_name FROM repair r LEFT JOIN contract c ON r.ctr_id = c.ctr_id LEFT JOIN tenant t ON c.tnt_id = t.tnt_id ORDER BY r.repair_date DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Map repair_status codes to human-readable labels
$status_labels = [
  '0' => 'รอซ่อม',
  '1' => 'กำลังซ่อม',
  '2' => 'ซ่อมเสร็จแล้ว',
];

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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานการแจ้งซ่อม</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div style="width:100%;">
          <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <div style="display:flex;align-items:center;gap:0.5rem">
              <button id="sidebar-toggle" aria-label="Toggle sidebar" aria-expanded="true" style="background:transparent;border:0;color:#fff;padding:0.6rem 0.85rem;border-radius:6px;cursor:pointer;font-size:1.25rem">☰</button>
              <h2 style="margin:0;color:#fff;font-size:1.05rem">รายงานการแจ้งซ่อมอุปกรณ์ภายในห้อง</h2>
            </div>
            <button id="toggle-view" aria-label="Toggle view" style="background:#334155;border:1px solid #475569;color:#fff;padding:0.5rem 1rem;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;transition:all 0.3s ease;margin-right:1rem;">🃏 การ์ด</button>
          </header>

          <section class="manage-panel" style="margin:1rem;padding:1.25rem 1rem;border-radius:1rem;background:linear-gradient(180deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));color:#f5f8ff">
            <div class="section-header">
              <div>
                <h1>รายงานการแจ้งซ่อมอุปกรณ์ภายในห้อง</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">บันทึกการแจ้งซ่อมและสถานะการแก้ไข</p>
              </div>
            </div>
            <!-- Card View -->
            <div id="card-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:1.5rem;margin-top:1.5rem;">
<?php foreach($rows as $r): ?>
              <?php
                $status = (string)($r['repair_status'] ?? '');
                $statusLabel = $status_labels[$status] ?? $status;
                $statusColors = [
                  '0' => '#f59e0b',
                  '1' => '#3b82f6',
                  '2' => '#10b981',
                ];
                $statusColor = $statusColors[$status] ?? '#6b7280';
              ?>
              <div style="background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #475569;padding-bottom:1rem;">
                  <div>
                    <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.3rem;">วันที่แจ้ง</div>
                    <div style="font-size:1.1rem;font-weight:600;color:#fff;"><?php echo htmlspecialchars($r['repair_date']); ?></div>
                  </div>
                  <div style="display:inline-block;padding:0.4rem 0.75rem;background-color:<?php echo $statusColor; ?>;color:#fff;border-radius:4px;font-size:0.75rem;font-weight:600;">
                    <?php echo $statusLabel; ?>
                  </div>
                </div>
                <div style="background:#0f172a;padding:1rem;border-radius:6px;">
                  <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.3rem;">ผู้แจ้ง</div>
                  <div style="font-size:1rem;font-weight:600;color:#fff;"><?php echo htmlspecialchars($r['tnt_name'] ?? '-'); ?></div>
                </div>
                <div style="background:#0f172a;padding:1rem;border-radius:6px;">
                  <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.3rem;">รายละเอียด</div>
                  <div style="font-size:0.95rem;color:#cbd5e1;line-height:1.5;"><?php echo htmlspecialchars($r['repair_desc']); ?></div>
                </div>
              </div>
<?php endforeach; ?>
            </div>

            <!-- Table View -->
            <div id="table-view" style="display:none;margin-top:1.5rem;overflow-x:auto;">
            <table class="table--compact" style="width:100%;border-collapse:collapse;">
              <thead>
                <tr style="text-align:left;border-bottom:2px solid #475569;background:#0f172a;">
                  <th style="padding:0.75rem;color:#94a3b8;">วันที่</th>
                  <th style="padding:0.75rem;color:#94a3b8;">ผู้แจ้ง</th>
                  <th style="padding:0.75rem;color:#94a3b8;">รายละเอียด</th>
                  <th style="padding:0.75rem;color:#94a3b8;">สถานะ</th>
                </tr>
              </thead>
              <tbody>
<?php foreach($rows as $r): ?>
                <?php
                  $status = (string)($r['repair_status'] ?? '');
                  $statusLabel = $status_labels[$status] ?? $status;
                ?>
                <tr style="border-bottom:1px solid #334155;background:#1e293b;">
                  <td style="padding:0.75rem;color:#fff;"><?php echo htmlspecialchars($r['repair_date']); ?></td>
                  <td style="padding:0.75rem;color:#fff;"><?php echo htmlspecialchars($r['tnt_name']); ?></td>
                  <td style="padding:0.75rem;color:#cbd5e1;"><?php echo htmlspecialchars($r['repair_desc']); ?></td>
                  <td style="padding:0.75rem;"><span style="display:inline-block;padding:0.3rem 0.6rem;background-color:<?php echo $statusColors[$status] ?? '#6b7280'; ?>;color:#fff;border-radius:4px;font-size:0.75rem;font-weight:600;"><?php echo $statusLabel; ?></span></td>
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
        const sidebar = document.querySelector('.app-sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle');
        
        if (toggleBtn) {
          toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (window.innerWidth > 1024) {
              sidebar.style.display = sidebar.style.display === 'none' ? 'flex' : 'none';
              document.body.style.marginLeft = sidebar.style.display === 'none' ? '0' : '250px';
            } else {
              sidebar.classList.toggle('show');
            }
          });
        }

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
    </script>
  </body>
</html>
