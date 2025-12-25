<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();
// Sum of payments grouped by month
$stmt = $pdo->query("SELECT DATE_FORMAT(p.pay_date, '%Y-%m') AS ym, SUM(p.pay_amount) AS total_received FROM payment p GROUP BY ym ORDER BY ym DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>รายงานรายรับ</title>
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
              <h2 style="margin:0;color:#fff;font-size:1.05rem">รายงานรายรับ</h2>
            </div>
          </header>

          <section class="manage-panel" style="margin:1rem;padding:1.25rem 1rem;border-radius:1rem;background:linear-gradient(180deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));color:#f5f8ff">
            <div class="section-header">
              <div>
                <h1>รายงานรายรับ</h1>
              </div>
            </div>
            <div class="report-table" style="margin-top:0.75rem">
            <table class="table--compact" id="table-revenue">
              <thead>
                <tr style="text-align:left;border-bottom:1px solid rgba(255,255,255,0.08)">
                  <th>เดือน</th>
                  <th>ยอดรับรวม</th>
                </tr>
              </thead>
              <tbody>
<?php foreach($rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['ym']); ?></td>
                  <td><?php echo number_format((int)$r['total_received']); ?></td>
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

