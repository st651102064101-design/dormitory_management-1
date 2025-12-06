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
          </header>

          <section class="manage-panel" style="margin:1rem;padding:1.25rem 1rem;border-radius:1rem;background:linear-gradient(180deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));color:#f5f8ff">
            <div class="section-header">
              <div>
                <h1>รายงานการแจ้งซ่อมอุปกรณ์ภายในห้อง</h1>
              </div>
            </div>
            <div class="report-table" style="margin-top:0.75rem">
            <table class="table--compact" id="table-repairs">
              <thead>
                <tr style="text-align:left;border-bottom:1px solid rgba(255,255,255,0.08)">
                  <th>วันที่</th>
                  <th>ผู้แจ้ง</th>
                  <th>รายละเอียด</th>
                  <th>สถานะ</th>
                </tr>
              </thead>
              <tbody>
<?php foreach($rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['repair_date']); ?></td>
                  <td><?php echo htmlspecialchars($r['tnt_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['repair_desc']); ?></td>
                  <?php $status = (string)($r['repair_status'] ?? ''); ?>
                  <td><?php echo htmlspecialchars($status_labels[$status] ?? $status); ?></td>
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
  </body>
</html>
