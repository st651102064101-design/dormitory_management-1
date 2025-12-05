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
    <link rel="stylesheet" href="../Assets/Css/main.css" />
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'รายงานรายรับ';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>รายงานรายรับ</h1>
              </div>
            </div>
            <div class="report-table">
            <table class="table--compact" id="table-revenue">
              <thead>
                <tr>
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
    <script src="../Assets/Javascript/main.js" defer></script>
  </body>


</html>

