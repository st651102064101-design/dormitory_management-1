<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// อัพเดทสถานะห้องให้ถูกต้อง
$pdo->exec("UPDATE room SET room_status = '0'");
$pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (SELECT 1 FROM contract c WHERE c.room_id = room.room_id AND c.ctr_status IN ('0','2'))");
$pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (SELECT 1 FROM booking b WHERE b.room_id = room.room_id AND b.bkg_status IN ('1','2'))");

// ดึงข้อมูลห้องพัก
$rooms = $pdo->query("
  SELECT r.room_id, r.room_number, r.room_status, r.room_image, r.type_id, 
         rt.type_name, rt.type_price
  FROM room r
  LEFT JOIN roomtype rt ON r.type_id = rt.type_id
  ORDER BY CAST(r.room_number AS UNSIGNED) ASC
")->fetchAll(PDO::FETCH_ASSOC);

// คำนวณสถิติ
$totalRooms = count($rooms);
$vacant = 0;
$occupied = 0;

foreach ($rooms as $room) {
  if ($room['room_status'] === '0') {
    $vacant++;
  } else {
    $occupied++;
  }
}

$occupancyRate = $totalRooms > 0 ? ($occupied / $totalRooms) * 100 : 0;

// สถิติตามประเภทห้อง
$roomTypeStats = $pdo->query("
  SELECT rt.type_name, rt.type_price,
         COUNT(r.room_id) as total,
         SUM(CASE WHEN r.room_status = '0' THEN 1 ELSE 0 END) as vacant_count,
         SUM(CASE WHEN r.room_status = '1' THEN 1 ELSE 0 END) as occupied_count
  FROM roomtype rt
  LEFT JOIN room r ON rt.type_id = r.type_id
  GROUP BY rt.type_id, rt.type_name, rt.type_price
  ORDER BY rt.type_price DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'รายงานห้องพัก';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <link rel="stylesheet" href="../Assets/Css/animate-ui.css">
  <link rel="stylesheet" href="../Assets/Css/main.css">
  <style>
    .reports-container {
      width: 100%;
      max-width: 100%;
      padding: 0;
    }
    .reports-container .container {
      max-width: 100%;
      width: 100%;
      padding: 1.5rem;
    }
    .report-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .report-card {
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .report-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }
    .report-card-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .report-card-icon {
      font-size: 2.5rem;
      width: 60px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 12px;
    }
    .report-card-title {
      font-size: 0.9rem;
      color: #cbd5e1;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 600;
    }
    .report-card-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: #f8fafc;
      margin: 0.5rem 0;
    }
    .report-card-subtitle {
      font-size: 0.85rem;
      color: #94a3b8;
    }
    .progress-bar {
      width: 100%;
      height: 8px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 4px;
      overflow: hidden;
      margin-top: 1rem;
    }
    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #22c55e, #10b981);
      transition: width 0.6s ease;
    }
    .type-stats-table {
      width: 100%;
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      overflow: hidden;
      margin-top: 2rem;
    }
    .type-stats-table table {
      width: 100%;
      border-collapse: collapse;
    }
    .type-stats-table th,
    .type-stats-table td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .type-stats-table th {
      background: rgba(255, 255, 255, 0.05);
      color: #cbd5e1;
      font-weight: 600;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .type-stats-table td {
      color: #e2e8f0;
    }
    .type-stats-table tr:last-child td {
      border-bottom: none;
    }
    .status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .status-vacant {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }
    .status-occupied {
      background: rgba(239, 68, 68, 0.15);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .chart-container {
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 2rem;
      margin-top: 2rem;
    }
    .chart-title {
      font-size: 1.2rem;
      font-weight: 600;
      color: #f8fafc;
      margin-bottom: 1.5rem;
    }
    .room-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 1rem;
      margin-top: 2rem;
    }
    .room-item {
      background: rgba(15, 23, 42, 0.6);
      border: 2px solid rgba(255, 255, 255, 0.1);
      border-radius: 10px;
      padding: 1rem;
      text-align: center;
      transition: all 0.2s;
    }
    .room-item:hover {
      transform: scale(1.05);
      border-color: rgba(96, 165, 250, 0.5);
    }
    .room-item.vacant {
      border-color: rgba(34, 197, 94, 0.4);
    }
    .room-item.occupied {
      border-color: rgba(239, 68, 68, 0.4);
    }
    .room-number {
      font-size: 1.5rem;
      font-weight: 700;
      color: #f8fafc;
      margin-bottom: 0.5rem;
    }
    .room-type {
      font-size: 0.8rem;
      color: #94a3b8;
      margin-bottom: 0.5rem;
    }
    .room-price {
      font-size: 0.85rem;
      color: #cbd5e1;
      font-weight: 600;
    }
  </style>
</head>
<body class="reports-page">
  <div class="app-shell">
    <?php 
    $currentPage = 'report_rooms.php';
    include __DIR__ . '/../includes/sidebar.php'; 
    ?>
    <div class="app-main">
      <main class="reports-container">
        <div class="container">
          <?php include __DIR__ . '/../includes/page_header.php'; ?>

          <!-- สถิติภาพรวม -->
          <div class="report-grid">
            <div class="report-card">
              <div class="report-card-header">
                <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                <div class="report-card-title">ห้องพักทั้งหมด</div>
              </div>
              <div class="report-card-value"><?php echo number_format($totalRooms); ?></div>
              <div class="report-card-subtitle">รวมทุกประเภท</div>
            </div>

            <div class="report-card">
              <div class="report-card-header">
                <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg></div>
                <div class="report-card-title">ห้องว่าง</div>
              </div>
              <div class="report-card-value" style="color:#22c55e;"><?php echo number_format($vacant); ?></div>
              <div class="report-card-subtitle">พร้อมให้เช่า</div>
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?php echo $totalRooms > 0 ? ($vacant/$totalRooms*100) : 0; ?>%;"></div>
              </div>
            </div>

            <div class="report-card">
              <div class="report-card-header">
                <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
                <div class="report-card-title">ห้องไม่ว่าง</div>
              </div>
              <div class="report-card-value" style="color:#ef4444;"><?php echo number_format($occupied); ?></div>
              <div class="report-card-subtitle">มีผู้เช่าอยู่</div>
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?php echo $totalRooms > 0 ? ($occupied/$totalRooms*100) : 0; ?>%; background:linear-gradient(90deg, #ef4444, #dc2626);"></div>
              </div>
            </div>

            <div class="report-card">
              <div class="report-card-header">
                <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                <div class="report-card-title">อัตราการเข้าพัก</div>
              </div>
              <div class="report-card-value" style="color:#60a5fa;"><?php echo number_format($occupancyRate, 1); ?>%</div>
              <div class="report-card-subtitle">จากห้องทั้งหมด</div>
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?php echo $occupancyRate; ?>%; background:linear-gradient(90deg, #60a5fa, #3b82f6);"></div>
              </div>
            </div>
          </div>

          <!-- สถิติตามประเภทห้อง -->
          <div class="type-stats-table">
            <table>
              <thead>
                <tr>
                  <th>ประเภทห้อง</th>
                  <th style="text-align:center;">ราคา/เดือน</th>
                  <th style="text-align:center;">จำนวนทั้งหมด</th>
                  <th style="text-align:center;">ว่าง</th>
                  <th style="text-align:center;">ไม่ว่าง</th>
                  <th style="text-align:center;">อัตราเข้าพัก</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($roomTypeStats as $stat): ?>
                  <?php
                    $typeOccupancyRate = $stat['total'] > 0 ? ($stat['occupied_count'] / $stat['total']) * 100 : 0;
                  ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($stat['type_name']); ?></strong></td>
                    <td style="text-align:center;">฿<?php echo number_format((int)$stat['type_price']); ?></td>
                    <td style="text-align:center;font-weight:600;"><?php echo number_format((int)$stat['total']); ?></td>
                    <td style="text-align:center;">
                      <span class="status-indicator status-vacant">
                        ✓ <?php echo number_format((int)$stat['vacant_count']); ?>
                      </span>
                    </td>
                    <td style="text-align:center;">
                      <span class="status-indicator status-occupied">
                        ✗ <?php echo number_format((int)$stat['occupied_count']); ?>
                      </span>
                    </td>
                    <td style="text-align:center;font-weight:700;color:#60a5fa;">
                      <?php echo number_format($typeOccupancyRate, 1); ?>%
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- แสดงห้องพักทั้งหมด -->
          <div class="chart-container">
            <div class="chart-title">ภาพรวมห้องพักทั้งหมด</div>
            <div class="room-list">
              <?php foreach ($rooms as $room): ?>
                <div class="room-item <?php echo $room['room_status'] === '0' ? 'vacant' : 'occupied'; ?>">
                  <div class="room-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></div>
                  <div class="room-type"><?php echo htmlspecialchars($room['type_name'] ?? '-'); ?></div>
                  <div class="room-price">฿<?php echo number_format((int)($room['type_price'] ?? 0)); ?>/ด.</div>
                  <div style="margin-top:0.5rem;">
                    <?php if ($room['room_status'] === '0'): ?>
                      <span class="status-indicator status-vacant" style="font-size:0.75rem;padding:0.3rem 0.6rem;">✓ ว่าง</span>
                    <?php else: ?>
                      <span class="status-indicator status-occupied" style="font-size:0.75rem;padding:0.3rem 0.6rem;">✗ ไม่ว่าง</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script src="../Assets/Javascript/animate-ui.js" defer></script>
  <script src="../Assets/Javascript/main.js" defer></script>
</body>
</html>
