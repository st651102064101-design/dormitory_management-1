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
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>รายงานการแจ้งซ่อม</title>
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'รายงานการแจ้งซ่อมอุปกรณ์ภายในห้อง';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>รายงานการแจ้งซ่อมอุปกรณ์ภายในห้อง</h1>
              </div>
              <?php 
                $entityName = 'การซ่อม';
                $entityFields = 'วันที่,ผู้แจ้ง,รายละเอียด,สถานะ';
                include __DIR__ . '/../includes/manage_toolbar.php'; 
              ?>
            </div>
            <div class="report-table">
            <table class="table--compact" id="table-repairs">
              <thead>
                <tr>
                  <th>วันที่</th>
                  <th>ผู้แจ้ง</th>
                  <th>รายละเอียด</th>
                  <th>สถานะ</th>
                  <th class="crud-column">จัดการ</th>
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
                  <td class="crud-column">
                    <button type="button" class="animate-ui-action-btn edit crud-action" data-entity="การซ่อม <?php echo htmlspecialchars($r['repair_desc']); ?>" data-fields="วันที่,รายละเอียด,สถานะ" data-repair-id="<?php echo htmlspecialchars($r['repair_id']); ?>" data-repair-date="<?php echo htmlspecialchars($r['repair_date']); ?>" data-repair-desc="<?php echo htmlspecialchars($r['repair_desc']); ?>" data-repair-status="<?php echo htmlspecialchars($r['repair_status']); ?>">แก้ไข</button>
                    <button type="button" class="animate-ui-action-btn delete crud-action" data-entity="การซ่อม <?php echo htmlspecialchars($r['repair_desc']); ?>" data-item-id="<?php echo htmlspecialchars($r['repair_id']); ?>" data-delete-endpoint="../Manage/delete_repair.php">ลบ</button>
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
    <script src="../Assets/Javascript/main.js" defer></script>
  </body>
</html>
