<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();
// Simple invoice viewer: list expenses with contract and tenant
$stmt = $pdo->query("SELECT e.*, c.ctr_id, t.tnt_name, r.room_number FROM expense e LEFT JOIN contract c ON e.ctr_id = c.ctr_id LEFT JOIN tenant t ON c.tnt_id = t.tnt_id LEFT JOIN room r ON c.room_id = r.room_id ORDER BY e.exp_month DESC");
error_reporting(E_ALL);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$statusLabels = [
  '0' => 'รอการตรวจสอบ',
  '1' => 'ตรวจสอบแล้ว',
];

function renderField(?string $value, string $fallback = '—'): string
{
  return htmlspecialchars(($value === null || $value === '') ? $fallback : $value, ENT_QUOTES, 'UTF-8');
}

function renderNumber(mixed $value): string
{
  if ($value === null || $value === '') {
    return '0';
  }
  return number_format((int)$value);
}

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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานใบแจ้งชำระเงิน</title>
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
              <h2 style="margin:0;color:#fff;font-size:1.05rem">รายงานใบแจ้งชำระเงิน</h2>
            </div>
            <button id="toggle-view" aria-label="Toggle view" style="background:#334155;border:1px solid #475569;color:#fff;padding:0.5rem 1rem;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;transition:all 0.3s ease;margin-right:1rem;">🃏 การ์ด</button>
          </header>

          <section style="margin:1rem;padding:1.25rem 1rem;border-radius:1rem;background:linear-gradient(180deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));color:#f5f8ff">
            <div class="section-header">
              <div>
                <h1>รายงานใบแจ้งชำระเงิน</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">รายการค่าใช้ห้องประจำเดือน</p>
              </div>
            </div>
            <!-- Card View -->
            <div id="card-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:1.5rem;margin-top:1.5rem;">
<?php foreach($rows as $r): ?>
              <?php 
                $statusKey = (string)($r['exp_status'] ?? '');
                $statusLabel = $statusLabels[$statusKey] ?? 'ยังไม่ระบุสถานะ';
                $statusColor = $statusKey === '1' ? '#10b981' : '#f59e0b';
                $totalAmount = (int)($r['exp_total'] ?? 0);
              ?>
              <div style="background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">
                <!-- ส่วนหัว -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #475569;padding-bottom:1rem;">
                  <div>
                    <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.3rem;">เดือน</div>
                    <div style="font-size:1.25rem;font-weight:700;color:#fff;"><?php echo renderField($r['exp_month'], 'ยังไม่ระบุ'); ?></div>
                  </div>
                  <div style="text-align:right;">
                    <div style="display:inline-block;padding:0.4rem 0.75rem;background-color:<?php echo $statusColor; ?>;color:#fff;border-radius:4px;font-size:0.75rem;font-weight:600;">
                      <?php echo $statusLabel; ?>
                    </div>
                  </div>
                </div>

                <!-- ผู้เช่า/ห้อง -->
                <div style="background:#0f172a;padding:1rem;border-radius:6px;">
                  <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.3rem;">ผู้เช่า</div>
                  <div style="font-size:1rem;font-weight:600;color:#fff;margin-bottom:0.5rem;"><?php echo renderField($r['tnt_name'], 'ยังไม่ระบุ'); ?></div>
                  <div style="display:flex;gap:1rem;font-size:0.9rem;">
                    <div>
                      <span style="color:#94a3b8;">ห้อง:</span> <span style="color:#fff;font-weight:600;"><?php echo renderField($r['room_number'], '-'); ?></span>
                    </div>
                    <div>
                      <span style="color:#94a3b8;">สัญญา:</span> <span style="color:#fff;font-weight:600;"><?php echo renderField((string)($r['ctr_id'] ?? ''), '-'); ?></span>
                    </div>
                  </div>
                </div>

                <!-- รายการค่าใช้ -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                  <div style="background:#0f172a;padding:0.75rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">⚡ ค่าไฟ</div>
                    <div style="font-size:1.1rem;font-weight:700;color:#3b82f6;">฿<?php echo renderNumber($r['exp_elec_chg']); ?></div>
                  </div>
                  <div style="background:#0f172a;padding:0.75rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">💧 ค่าน้ำ</div>
                    <div style="font-size:1.1rem;font-weight:700;color:#22c55e;">฿<?php echo renderNumber($r['exp_water']); ?></div>
                  </div>
                  <div style="background:#0f172a;padding:0.75rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">🏠 ค่าห้อง</div>
                    <div style="font-size:1.1rem;font-weight:700;color:#f59e0b;">฿<?php echo renderNumber($r['room_price']); ?></div>
                  </div>
                  <div style="background:#0f172a;padding:0.75rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">อื่น ๆ</div>
                    <div style="font-size:1.1rem;font-weight:700;color:#8b5cf6;">฿<?php echo renderNumber($r['exp_other'] ?? 0); ?></div>
                  </div>
                </div>

                <!-- ยอดรวม -->
                <div style="background:linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);padding:1rem;border-radius:6px;text-align:center;">
                  <div style="font-size:0.875rem;color:#93c5fd;margin-bottom:0.3rem;">ยอดรวมทั้งสิ้น</div>
                  <div style="font-size:2rem;font-weight:700;color:#fff;">฿<?php echo renderNumber($totalAmount); ?></div>
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
                    <th style="padding:0.75rem;color:#94a3b8;">สัญญา</th>
                    <th style="padding:0.75rem;color:#94a3b8;">ผู้เช่า</th>
                    <th style="padding:0.75rem;color:#94a3b8;">ห้อง</th>
                    <th style="padding:0.75rem;color:#94a3b8;">⚡ ค่าไฟ</th>
                    <th style="padding:0.75rem;color:#94a3b8;">💧 ค่าน้ำ</th>
                    <th style="padding:0.75rem;color:#94a3b8;">🏠 ค่าห้อง</th>
                    <th style="padding:0.75rem;color:#94a3b8;">อื่น ๆ</th>
                    <th style="padding:0.75rem;color:#94a3b8;">ยอดรวม</th>
                    <th style="padding:0.75rem;color:#94a3b8;">สถานะ</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach($rows as $r): ?>
                  <?php 
                    $statusKey = (string)($r['exp_status'] ?? '');
                    $statusLabel = $statusLabels[$statusKey] ?? 'ยังไม่ระบุสถานะ';
                    $statusColor = $statusKey === '1' ? '#10b981' : '#f59e0b';
                  ?>
                  <tr style="border-bottom:1px solid #334155;background:#1e293b;">
                    <td style="padding:0.75rem;color:#fff;"><?php echo renderField($r['exp_month'], '-'); ?></td>
                    <td style="padding:0.75rem;color:#fff;"><?php echo renderField((string)($r['ctr_id'] ?? ''), '-'); ?></td>
                    <td style="padding:0.75rem;color:#fff;"><?php echo renderField($r['tnt_name'], '-'); ?></td>
                    <td style="padding:0.75rem;color:#fff;"><?php echo renderField($r['room_number'], '-'); ?></td>
                    <td style="padding:0.75rem;color:#3b82f6;text-align:right;font-weight:600;">฿<?php echo renderNumber($r['exp_elec_chg']); ?></td>
                    <td style="padding:0.75rem;color:#22c55e;text-align:right;font-weight:600;">฿<?php echo renderNumber($r['exp_water']); ?></td>
                    <td style="padding:0.75rem;color:#f59e0b;text-align:right;font-weight:600;">฿<?php echo renderNumber($r['room_price']); ?></td>
                    <td style="padding:0.75rem;color:#8b5cf6;text-align:right;font-weight:600;">฿<?php echo renderNumber($r['exp_other'] ?? 0); ?></td>
                    <td style="padding:0.75rem;color:#fff;text-align:right;font-weight:700;">฿<?php echo renderNumber($r['exp_total']); ?></td>
                    <td style="padding:0.75rem;">
                      <span style="display:inline-block;padding:0.3rem 0.6rem;background-color:<?php echo $statusColor; ?>;color:#fff;border-radius:4px;font-size:0.75rem;font-weight:600;">
                        <?php echo $statusLabel; ?>
                      </span>
                    </td>
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
