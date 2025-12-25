<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();
// Report: current stays (contracts with ctr_status = '0')
$stmt = $pdo->query("SELECT c.ctr_id, c.ctr_start, c.ctr_end, c.ctr_deposit, c.ctr_status, c.tnt_id, t.tnt_name, r.room_number
FROM contract c
LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
LEFT JOIN room r ON c.room_id = r.room_id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function renderField(?string $value, string $fallback = '—'): string
{
  return htmlspecialchars(($value === null || $value === '') ? $fallback : $value, ENT_QUOTES, 'UTF-8');
}

$statusLabels = [
  '0' => 'รอเข้าพัก',
  '1' => 'กำลังเข้าพัก',
  '2' => 'ยกเลิก/สิ้นสุด',
];
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการห้องพัก (เก็บถาวร)</title>
    <link rel="stylesheet" href="..//Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="..//Assets/Css/main.css" />
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการห้องพัก (เก็บถาวร)';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>
          <section class="manage-panel">
            <div class="section-header">
              <div>
                <p>&nbsp;</p>
              </div>
              <?php 
                $entityName = 'การจอง';
                $entityFields = 'room_id,bkg_date,bkg_checkin_date,bkg_status';
                include __DIR__ . '/../includes/manage_toolbar.php'; 
              ?>
            </div>
            <div class="report-table">
            <table class="table--compact" id="table-stay">
              <thead>
                <tr>
                  <th>รหัสสัญญา</th>
                  <th>ผู้เช่า</th>
                  <th>ห้อง</th>
                  <th>เริ่ม</th>
                  <th>สิ้นสุด</th>
                  <th>มัดจำ</th>
                  <th>สถานะ</th>
                  <th class="crud-column">จัดการ</th>
                </tr>
              </thead>
              <tbody>
<?php foreach($rows as $r): ?>
                <tr>
                  <td><?php echo renderField((string)($r['ctr_id'] ?? ''), 'ไม่ระบุรหัส'); ?></td>
                  <td><?php echo renderField($r['tnt_name'], 'ยังไม่ระบุผู้เช่า'); ?></td>
                  <td><?php echo renderField($r['room_number'], 'ยังไม่ระบุห้อง'); ?></td>
                  <td><?php echo renderField($r['ctr_start'], 'ยังไม่ระบุวันที่เริ่ม'); ?></td>
                  <td><?php echo renderField($r['ctr_end'], 'ยังไม่ระบุวันที่สิ้นสุด'); ?></td>
                  <td><?php echo number_format((int)($r['ctr_deposit'] ?? 0)); ?></td>
                  <?php $statusKey = (string)($r['ctr_status'] ?? ''); ?>
                  <td><?php echo renderField($statusLabels[$statusKey] ?? '', 'ยังไม่ระบุสถานะ'); ?></td>
                  <td class="crud-column">
                    <button type="button" class="animate-ui-action-btn edit crud-action" data-entity="สัญญา <?php echo htmlspecialchars((string)($r['ctr_id'] ?? '')); ?>" data-fields="รหัสสัญญา,ผู้เช่า,ห้อง,เริ่ม,สิ้นสุด,มัดจำ,สถานะ" data-ctr-id="<?php echo htmlspecialchars((string)($r['ctr_id'] ?? '')); ?>" data-tnt-id="<?php echo htmlspecialchars((string)($r['tnt_id'] ?? '')); ?>" data-tnt-name="<?php echo htmlspecialchars($r['tnt_name'] ?? ''); ?>" data-room-id="<?php echo htmlspecialchars((string)($r['room_id'] ?? '')); ?>" data-room-number="<?php echo htmlspecialchars($r['room_number'] ?? ''); ?>" data-ctr-start="<?php echo htmlspecialchars($r['ctr_start'] ?? ''); ?>" data-ctr-end="<?php echo htmlspecialchars($r['ctr_end'] ?? ''); ?>" data-ctr-deposit="<?php echo htmlspecialchars((string)($r['ctr_deposit'] ?? '')); ?>" data-ctr-status="<?php echo htmlspecialchars((string)($r['ctr_status'] ?? '')); ?>">แก้ไข</button>
                    <button type="button" class="animate-ui-action-btn delete crud-action" data-entity="สัญญา <?php echo htmlspecialchars((string)($r['ctr_id'] ?? '')); ?>" data-item-id="<?php echo htmlspecialchars((string)($r['ctr_id'] ?? '')); ?>" data-delete-endpoint="../Manage/delete_contract.php">ลบ</button>
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
    <script src="..//Assets/Javascript/animate-ui.js" defer></script>
    <script src="..//Assets/Javascript/main.js" defer></script>
    <script>
      window.tenants = <?php echo json_encode($pdo->query("SELECT tnt_id, tnt_name FROM tenant")->fetchAll(PDO::FETCH_ASSOC)); ?>;
      window.rooms = <?php echo json_encode($pdo->query("SELECT * FROM room WHERE room_status = 0")->fetchAll(PDO::FETCH_ASSOC)); ?>;
    </script>

  </body>
</html>
