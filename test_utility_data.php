<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

// ดึงข้อมูล utility พร้อมข้อมูลที่เกี่ยวข้อง
$utilStmt = $pdo->query("
  SELECT u.*,
         c.ctr_id,
         t.tnt_name,
         r.room_number
  FROM utility u
  LEFT JOIN contract c ON u.ctr_id = c.ctr_id
  LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
  LEFT JOIN room r ON c.room_id = r.room_id
  ORDER BY u.utl_date DESC, u.utl_id DESC
  LIMIT 3
");
$utilities = $utilStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบข้อมูล Utility</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a2e; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #444; }
        th { background: #2a2a3e; color: #22c55e; }
        td { background: #1e1e2e; }
        .highlight { color: #3b82f6; font-weight: bold; }
        pre { background: #2a2a3e; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 ทดสอบข้อมูล Utility</h1>
    <p>แสดง 3 รายการล่าสุด</p>
    
    <h2>ข้อมูลดิบ (Raw Data)</h2>
    <pre><?php print_r($utilities); ?></pre>
    
    <h2>ตารางแสดงผล</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ห้อง</th>
                <th>ผู้เช่า</th>
                <th>วันที่</th>
                <th colspan="3" style="text-align: center; color: #22c55e;">💧 น้ำ</th>
                <th colspan="3" style="text-align: center; color: #3b82f6;">⚡ ไฟ</th>
            </tr>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th style="color: #22c55e;">เริ่มต้น</th>
                <th style="color: #22c55e;">สิ้นสุด</th>
                <th style="color: #22c55e;">ใช้ไป</th>
                <th class="highlight">เริ่มต้น</th>
                <th class="highlight">สิ้นสุด</th>
                <th class="highlight">ใช้ไป</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($utilities as $util): ?>
                <?php
                    $waterUsage = (int)($util['utl_water_end'] ?? 0) - (int)($util['utl_water_start'] ?? 0);
                    $elecUsage = (int)($util['utl_elec_end'] ?? 0) - (int)($util['utl_elec_start'] ?? 0);
                ?>
                <tr>
                    <td><?php echo $util['utl_id']; ?></td>
                    <td><?php echo $util['room_number'] ?? '-'; ?></td>
                    <td><?php echo $util['tnt_name'] ?? '-'; ?></td>
                    <td><?php echo $util['utl_date'] ? date('d/m/Y', strtotime($util['utl_date'])) : '-'; ?></td>
                    <!-- น้ำ -->
                    <td style="text-align: right;"><?php echo number_format((int)($util['utl_water_start'] ?? 0)); ?></td>
                    <td style="text-align: right;"><?php echo number_format((int)($util['utl_water_end'] ?? 0)); ?></td>
                    <td style="text-align: right; color: #22c55e; font-weight: bold;"><?php echo number_format($waterUsage); ?></td>
                    <!-- ไฟ -->
                    <td style="text-align: right;" class="highlight"><?php echo number_format((int)($util['utl_elec_start'] ?? 0)); ?></td>
                    <td style="text-align: right;" class="highlight"><?php echo number_format((int)($util['utl_elec_end'] ?? 0)); ?></td>
                    <td style="text-align: right; color: #3b82f6; font-weight: bold;"><?php echo number_format($elecUsage); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h2>ตรวจสอบคอลัมน์</h2>
    <?php if (!empty($utilities)): ?>
        <ul>
            <?php foreach (array_keys($utilities[0]) as $col): ?>
                <li><strong><?php echo $col; ?></strong>: <?php echo $utilities[0][$col] ?? 'NULL'; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <p><a href="Reports/report_utility.php" style="color: #3b82f6;">← กลับไปยัง report_utility.php</a></p>
</body>
</html>
