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
$orderBy = 'u.utl_date DESC, u.utl_id DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'u.utl_date ASC, u.utl_id ASC';
    break;
  case 'room_number':
    $orderBy = 'r.room_number ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'u.utl_date DESC, u.utl_id DESC';
}

// ดึงข้อมูลมิเตอร์น้ำ-ไฟทั้งหมด
$utilStmt = $pdo->query("
  SELECT u.*,
         c.ctr_id,
         t.tnt_name,
         r.room_number
  FROM utility u
  LEFT JOIN contract c ON u.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  ORDER BY $orderBy
");
$utilities = $utilStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการมิเตอร์น้ำ-ไฟ</title>
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
              <h2 style="margin:0;color:#fff;font-size:1.05rem">จัดการมิเตอร์น้ำ-ไฟ</h2>
            </div>
            <button id="toggle-view" aria-label="Toggle view" style="background:#334155;border:1px solid #475569;color:#fff;padding:0.5rem 1rem;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;transition:all 0.3s ease;margin-right:1rem;">🃏 การ์ด</button>
          </header>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>ประวัติการอ่านมิเตอร์น้ำ-ไฟ</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">บันทึกการอ่านมิเตอร์ของแต่ละสัญญา</p>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>อ่านล่าสุด</option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>อ่านเก่าสุด</option>
                <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>หมายเลขห้อง</option>
              </select>
            </div>
            
            <?php if (empty($utilities)): ?>
              <div style="text-align:center;padding:3rem 1rem;color:#64748b;">
                <div style="font-size:3rem;margin-bottom:1rem;">📊</div>
                <h3>ยังไม่มีข้อมูลมิเตอร์</h3>
                <p>เริ่มต้นบันทึกการอ่านมิเตอร์ของห้องพัก</p>
              </div>
            <?php else: ?>
              <!-- Card View -->
              <div id="card-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(500px,1fr));gap:1.5rem;margin-top:1.5rem;">
                <?php foreach ($utilities as $util): ?>
                  <?php
                    $waterUsage = (int)($util['utl_water_end'] ?? 0) - (int)($util['utl_water_start'] ?? 0);
                    $elecUsage = (int)($util['utl_elec_end'] ?? 0) - (int)($util['utl_elec_start'] ?? 0);
                    $readDate = $util['utl_date'] ? date('d/m/Y', strtotime($util['utl_date'])) : '-';
                  ?>
                  <div style="background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">
                    <!-- ส่วนหัว -->
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #475569;padding-bottom:1rem;">
                      <div>
                        <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.3rem;">รหัสมิเตอร์</div>
                        <div style="font-size:1.5rem;font-weight:700;color:#fff;">#<?php echo str_pad((string)($util['utl_id'] ?? '0'), 4, '0', STR_PAD_LEFT); ?></div>
                      </div>
                      <div style="text-align:right;">
                        <div style="font-size:0.875rem;color:#94a3b8;margin-bottom:0.3rem;">วันที่อ่าน</div>
                        <div style="font-size:1.1rem;font-weight:600;color:#fff;"><?php echo $readDate; ?></div>
                      </div>
                    </div>

                    <!-- ห้อง/ผู้เช่า -->
                    <div style="background:#0f172a;padding:1rem;border-radius:6px;">
                      <div style="font-size:1.1rem;font-weight:600;color:#fff;margin-bottom:0.3rem;">ห้อง <?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></div>
                      <div style="font-size:0.9rem;color:#cbd5e1;"><?php echo htmlspecialchars($util['tnt_name'] ?? '-'); ?></div>
                    </div>

                    <!-- น้ำ -->
                    <div>
                      <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                        <span style="font-size:1.25rem;">💧</span>
                        <div style="font-size:1rem;font-weight:700;color:#22c55e;">น้ำ</div>
                      </div>
                      <div style="background:#0f172a;padding:1rem;border-radius:6px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;">
                        <div style="text-align:center;padding:0.75rem;background:#1e293b;border-radius:4px;">
                          <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">เริ่มต้น</div>
                          <div style="font-size:1.25rem;font-weight:700;color:#fff;"><?php echo number_format((int)($util['utl_water_start'] ?? 0)); ?></div>
                        </div>
                        <div style="text-align:center;padding:0.75rem;background:#1e293b;border-radius:4px;">
                          <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">สิ้นสุด</div>
                          <div style="font-size:1.25rem;font-weight:700;color:#fff;"><?php echo number_format((int)($util['utl_water_end'] ?? 0)); ?></div>
                        </div>
                        <div style="text-align:center;padding:0.75rem;background:#064e3b;border-radius:4px;">
                          <div style="font-size:0.75rem;color:#86efac;margin-bottom:0.3rem;">ใช้ไป</div>
                          <div style="font-size:1.25rem;font-weight:700;color:#86efac;"><?php echo number_format($waterUsage); ?></div>
                        </div>
                      </div>
                    </div>

                    <!-- ไฟ -->
                    <div>
                      <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                        <span style="font-size:1.25rem;">⚡</span>
                        <div style="font-size:1rem;font-weight:700;color:#3b82f6;">ไฟ</div>
                      </div>
                      <div style="background:#0f172a;padding:1rem;border-radius:6px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;">
                        <div style="text-align:center;padding:0.75rem;background:#1e293b;border-radius:4px;">
                          <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">เริ่มต้น</div>
                          <div style="font-size:1.25rem;font-weight:700;color:#fff;"><?php echo number_format((int)($util['utl_elec_start'] ?? 0)); ?></div>
                        </div>
                        <div style="text-align:center;padding:0.75rem;background:#1e293b;border-radius:4px;">
                          <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.3rem;">สิ้นสุด</div>
                          <div style="font-size:1.25rem;font-weight:700;color:#fff;"><?php echo number_format((int)($util['utl_elec_end'] ?? 0)); ?></div>
                        </div>
                        <div style="text-align:center;padding:0.75rem;background:#0c4a6e;border-radius:4px;">
                          <div style="font-size:0.75rem;color:#93c5fd;margin-bottom:0.3rem;">ใช้ไป</div>
                          <div style="font-size:1.25rem;font-weight:700;color:#93c5fd;"><?php echo number_format($elecUsage); ?></div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Table View -->
              <div id="table-view" style="display:none;margin-top:1.5rem;overflow-x:auto;">
                <table class="table--compact" style="width:100%;border-collapse:collapse;">
                  <thead>
                    <tr style="text-align:left;border-bottom:1px solid #475569;background:#0f172a;">
                      <th style="padding:0.75rem;color:#94a3b8;">รหัส</th>
                      <th style="padding:0.75rem;color:#94a3b8;">ห้อง/ผู้เช่า</th>
                      <th style="padding:0.75rem;color:#94a3b8;">วันที่อ่าน</th>
                      <th colspan="3" style="padding:0.75rem;color:#22c55e;text-align:center;font-weight:700;">💧 น้ำ</th>
                      <th colspan="3" style="padding:0.75rem;color:#3b82f6;text-align:center;font-weight:700;">⚡ ไฟ</th>
                    </tr>
                    <tr style="text-align:right;border-bottom:2px solid #475569;background:#0f172a;">
                      <th style="padding:0.75rem;color:#94a3b8;text-align:left;"></th>
                      <th style="padding:0.75rem;color:#94a3b8;text-align:left;"></th>
                      <th style="padding:0.75rem;color:#94a3b8;text-align:left;"></th>
                      <th style="padding:0.75rem;color:#22c55e;">เริ่มต้น</th>
                      <th style="padding:0.75rem;color:#22c55e;">สิ้นสุด</th>
                      <th style="padding:0.75rem;color:#22c55e;">ใช้ไป</th>
                      <th style="padding:0.75rem;color:#3b82f6;">เริ่มต้น</th>
                      <th style="padding:0.75rem;color:#3b82f6;">สิ้นสุด</th>
                      <th style="padding:0.75rem;color:#3b82f6;">ใช้ไป</th>
                    </tr>
                  </thead>
                  <tbody>
<?php foreach ($utilities as $util): ?>
                    <?php
                      $waterUsage = (int)($util['utl_water_end'] ?? 0) - (int)($util['utl_water_start'] ?? 0);
                      $elecUsage = (int)($util['utl_elec_end'] ?? 0) - (int)($util['utl_elec_start'] ?? 0);
                      $readDate = $util['utl_date'] ? date('d/m/Y', strtotime($util['utl_date'])) : '-';
                    ?>
                    <tr style="border-bottom:1px solid #334155;background:#1e293b;">
                      <td style="padding:0.75rem;color:#fff;">#<?php echo str_pad((string)($util['utl_id'] ?? '0'), 4, '0', STR_PAD_LEFT); ?></td>
                      <td style="padding:0.75rem;">
                        <div style="color:#fff;font-weight:600;">ห้อง <?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></div>
                        <div style="color:#94a3b8;font-size:0.875rem;"><?php echo htmlspecialchars($util['tnt_name'] ?? '-'); ?></div>
                      </td>
                      <td style="padding:0.75rem;color:#fff;"><?php echo $readDate; ?></td>
                      <td style="padding:0.75rem;color:#fff;text-align:right;"><?php echo number_format((int)($util['utl_water_start'] ?? 0)); ?></td>
                      <td style="padding:0.75rem;color:#fff;text-align:right;"><?php echo number_format((int)($util['utl_water_end'] ?? 0)); ?></td>
                      <td style="padding:0.75rem;color:#22c55e;text-align:right;font-weight:700;"><?php echo number_format($waterUsage); ?></td>
                      <td style="padding:0.75rem;color:#fff;text-align:right;"><?php echo number_format((int)($util['utl_elec_start'] ?? 0)); ?></td>
                      <td style="padding:0.75rem;color:#fff;text-align:right;"><?php echo number_format((int)($util['utl_elec_end'] ?? 0)); ?></td>
                      <td style="padding:0.75rem;color:#3b82f6;text-align:right;font-weight:700;"><?php echo number_format($elecUsage); ?></td>
                    </tr>
<?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
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
              sidebar.style.transition = 'none';
              void sidebar.offsetHeight;
              sidebar.style.transition = '';
              
              sidebar.classList.toggle('collapsed');
              localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            } else {
              sidebar.classList.toggle('mobile-open');
              document.body.classList.toggle('sidebar-open');
            }
          });
        }
        
        document.addEventListener('click', function(e) {
          if (window.innerWidth <= 1024 && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
              sidebar.classList.remove('mobile-open');
              document.body.classList.remove('sidebar-open');
            }
          }
        });
        
        if (window.innerWidth > 1024 && localStorage.getItem('sidebarCollapsed') === 'true') {
          sidebar.classList.add('collapsed');
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

      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }
    </script>
  </body>
</html>
