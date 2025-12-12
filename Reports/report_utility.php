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
} catch (PDOException $e) {}

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
    <!-- DataTable Modern -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="../Assets/Css/datatable-modern.css" />
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
            <button id="toggle-view" aria-label="Toggle view" style="background:#334155;border:1px solid #475569;color:#fff;padding:0.5rem 1rem;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;transition:all 0.3s ease;margin-right:1rem;display:inline-flex;align-items:center;gap:0.4rem;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>การ์ด</button>
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
                <div style="margin-bottom:1rem;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;opacity:0.5;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
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
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg, #22c55e, #16a34a);"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></span>
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
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg, #f59e0b, #d97706);"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
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
              <div id="table-view" style="display:none;margin-top:1.5rem;">
                <div style="overflow-x:auto;border:1px solid #334155;border-radius:8px;background:#0f172a;">
                  <table id="table-utility" style="width:100%;border-collapse:collapse;min-width:1400px;">
                    <thead>
                      <tr style="background:#0f172a;">
                        <th rowspan="2" style="padding:1rem;color:#94a3b8;border-bottom:2px solid #475569;white-space:nowrap;vertical-align:bottom;">รหัส</th>
                        <th rowspan="2" style="padding:1rem;color:#94a3b8;border-bottom:2px solid #475569;white-space:nowrap;vertical-align:bottom;">ห้อง/ผู้เช่า</th>
                        <th rowspan="2" style="padding:1rem;color:#94a3b8;border-bottom:2px solid #475569;white-space:nowrap;vertical-align:bottom;">วันที่อ่าน</th>
                        <th colspan="3" style="padding:0.75rem;color:#22c55e;text-align:center;font-weight:700;border-bottom:1px solid #475569;border-right:3px solid #475569;font-size:1.1rem;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>น้ำ</th>
                        <th colspan="3" style="padding:0.75rem;color:#3b82f6;text-align:center;font-weight:700;border-bottom:1px solid #475569;font-size:1.1rem;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>ไฟ</th>
                      </tr>
                      <tr style="background:#0f172a;">
                        <th style="padding:0.75rem;color:#22c55e;font-size:0.9rem;border-bottom:2px solid #475569;white-space:nowrap;">เริ่มต้น</th>
                        <th style="padding:0.75rem;color:#22c55e;font-size:0.9rem;border-bottom:2px solid #475569;white-space:nowrap;">สิ้นสุด</th>
                        <th style="padding:0.75rem;color:#22c55e;font-size:0.9rem;border-bottom:2px solid #475569;border-right:3px solid #475569;white-space:nowrap;">ใช้ไป</th>
                        <th style="padding:0.75rem;color:#3b82f6;font-size:0.9rem;border-bottom:2px solid #475569;white-space:nowrap;">เริ่มต้น</th>
                        <th style="padding:0.75rem;color:#3b82f6;font-size:0.9rem;border-bottom:2px solid #475569;white-space:nowrap;">สิ้นสุด</th>
                        <th style="padding:0.75rem;color:#3b82f6;font-size:0.9rem;border-bottom:2px solid #475569;white-space:nowrap;">ใช้ไป</th>
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
                      <td style="padding:0.85rem;color:#fff;font-weight:600;white-space:nowrap;">#<?php echo str_pad((string)($util['utl_id'] ?? '0'), 4, '0', STR_PAD_LEFT); ?></td>
                      <td style="padding:0.85rem;white-space:nowrap;">
                        <div style="color:#fff;font-weight:600;margin-bottom:0.25rem;">ห้อง <?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></div>
                        <div style="color:#94a3b8;font-size:0.875rem;"><?php echo htmlspecialchars($util['tnt_name'] ?? '-'); ?></div>
                      </td>
                      <td style="padding:0.85rem;color:#cbd5e1;white-space:nowrap;"><?php echo $readDate; ?></td>
                      <!-- น้ำ: เริ่มต้น -->
                      <td style="padding:0.85rem;color:#e2e8f0;text-align:right;font-variant-numeric:tabular-nums;font-size:0.95rem;white-space:nowrap;"><?php echo number_format((int)($util['utl_water_start'] ?? 0)); ?></td>
                      <!-- น้ำ: สิ้นสุด -->
                      <td style="padding:0.85rem;color:#e2e8f0;text-align:right;font-variant-numeric:tabular-nums;font-size:0.95rem;white-space:nowrap;"><?php echo number_format((int)($util['utl_water_end'] ?? 0)); ?></td>
                      <!-- น้ำ: ใช้ไป -->
                      <td style="padding:0.85rem;color:#22c55e;text-align:right;font-weight:700;font-variant-numeric:tabular-nums;font-size:0.95rem;border-right:3px solid #475569;white-space:nowrap;"><?php echo number_format($waterUsage); ?></td>
                      <!-- ไฟ: เริ่มต้น -->
                      <td style="padding:0.85rem;color:#e2e8f0;text-align:right;font-variant-numeric:tabular-nums;font-size:0.95rem;white-space:nowrap;"><?php echo number_format((int)($util['utl_elec_start'] ?? 0)); ?></td>
                      <!-- ไฟ: สิ้นสุด -->
                      <td style="padding:0.85rem;color:#e2e8f0;text-align:right;font-variant-numeric:tabular-nums;font-size:0.95rem;white-space:nowrap;"><?php echo number_format((int)($util['utl_elec_end'] ?? 0)); ?></td>
                      <!-- ไฟ: ใช้ไป -->
                      <td style="padding:0.85rem;color:#3b82f6;text-align:right;font-weight:700;font-variant-numeric:tabular-nums;font-size:0.95rem;white-space:nowrap;"><?php echo number_format($elecUsage); ?></td>
                    </tr>
<?php endforeach; ?>
                  </tbody>
                </table>
                </div>
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
        // Get default view mode from database (list -> table, grid -> card)
        let isCardView = '<?php echo $defaultViewMode === "list" ? "false" : "true"; ?>' === 'true';

        function updateView() {
          if (isCardView) {
            cardView.style.display = 'grid';
            tableView.style.display = 'none';
            viewToggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>ตาราง';
          } else {
            cardView.style.display = 'none';
            tableView.style.display = 'block';
            viewToggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>การ์ด';
          }
        }

        // กำหนดมุมมองเริ่มต้น
        updateView();

        if (viewToggle) {
          viewToggle.addEventListener('click', function() {
            isCardView = !isCardView;
            localStorage.setItem('utilityViewMode', isCardView ? 'card' : 'table');
            updateView();
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
        const utilityTable = document.getElementById('table-utility');
        if (utilityTable && typeof simpleDatatables !== 'undefined') {
          new simpleDatatables.DataTable(utilityTable, {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 25, 50, 100],
            labels: {
              placeholder: 'ค้นหามิเตอร์...',
              perPage: 'รายการต่อหน้า',
              noRows: 'ไม่พบข้อมูลมิเตอร์',
              info: 'แสดง {start} ถึง {end} จาก {rows} รายการ'
            }
          });
        }
      });
    </script>
  </body>
</html>
