<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// Contracts by status
$rows = $pdo->query("SELECT ctr_status, COUNT(*) AS cnt FROM contract GROUP BY ctr_status")->fetchAll(PDO::FETCH_ASSOC);
$contractStatus = [];
foreach ($rows as $r) {
    $contractStatus[$r['ctr_status']] = (int)$r['cnt'];
}

// Rooms by status
$rows = $pdo->query("SELECT room_status, COUNT(*) AS cnt FROM room GROUP BY room_status")->fetchAll(PDO::FETCH_ASSOC);
$roomStatus = [];
foreach ($rows as $r) {
    $roomStatus[$r['room_status']] = (int)$r['cnt'];
}

// Repairs by status
$rows = $pdo->query("SELECT repair_status, COUNT(*) AS cnt FROM repair GROUP BY repair_status")->fetchAll(PDO::FETCH_ASSOC);
$repairStatus = [];
foreach ($rows as $r) {
    $repairStatus[$r['repair_status']] = (int)$r['cnt'];
}

// Payments by month (last 6 months)
$rows = $pdo->query("SELECT DATE_FORMAT(pay_date, '%Y-%m') as ym, SUM(pay_amount) AS total FROM payment GROUP BY ym ORDER BY ym DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$paymentsMonths = [];
$paymentsTotals = [];
$rows = array_reverse($rows);
foreach ($rows as $r) {
    $paymentsMonths[] = $r['ym'];
    $paymentsTotals[] = (int)$r['total'];
}

// Utility usage by month (last 6 months)
$rows = $pdo->query("SELECT DATE_FORMAT(utl_date, '%Y-%m') as ym, SUM(utl_usage) AS total FROM utility GROUP BY ym ORDER BY ym DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$utilMonths = [];
$utilTotals = [];
$rows = array_reverse($rows);
foreach ($rows as $r) {
    $utilMonths[] = $r['ym'];
    $utilTotals[] = (int)$r['total'];
}

// News count by month (last 6 months)
$rows = $pdo->query("SELECT DATE_FORMAT(news_date, '%Y-%m') AS ym, COUNT(*) AS cnt FROM news GROUP BY ym ORDER BY ym DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$newsMonths = [];
$newsCounts = [];
$rows = array_reverse($rows);
foreach ($rows as $r) {
  $newsMonths[] = $r['ym'];
  $newsCounts[] = (int)$r['cnt'];
}

// Prepare JSON for JS
$js = [
    'contractStatus' => $contractStatus,
    'roomStatus' => $roomStatus,
    'repairStatus' => $repairStatus,
    'payments' => ['months' => $paymentsMonths, 'totals' => $paymentsTotals],
    'utility' => ['months' => $utilMonths, 'totals' => $utilTotals],
  'news' => ['months' => $newsMonths, 'counts' => $newsCounts],
];
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการแดชบอร์ดกราฟ</title>
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <style>
      .charts-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(320px,1fr)); gap:1rem; }
      .chart-card { padding:1rem; border-radius:0.75rem; background: linear-gradient(135deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); }
      canvas { width:100% !important; height:260px !important; }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div style="width:100%;">
          <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <div style="display:flex;align-items:center;gap:0.5rem">
              <button id="sidebar-toggle" aria-label="Toggle sidebar" aria-expanded="true" style="background:transparent;border:0;color:#fff;padding:0.6rem 0.85rem;border-radius:6px;cursor:pointer;font-size:1.25rem">☰</button>
              <h2 style="margin:0;color:#fff;font-size:1.05rem">จัดการแดชบอร์ดกราฟ</h2>
            <div></div>
          </header>

          <section style="margin:1rem;padding:1.25rem 1rem;border-radius:1rem;background:linear-gradient(180deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));color:#f5f8ff">
            <h1>รายงาน</h1>
            <p>รวมกราฟสรุปจากรายงานต่าง ๆ</p>

            <div style="margin-top:1.25rem; display:flex; flex-direction:column; gap:1rem;">
              <div style="display:flex; gap:1rem;">
                <div class="small-card" style="flex:1">
                  <h3 style="margin:0 0 0.5rem 0;font-weight:600">รายงานข่าวประชาสัมพันธ์</h3>
                  <div id="report-news-content" style="margin-top:0.5rem">กำลังโหลด...</div>
                </div>
              </div>
              <div style="display:flex; gap:1rem;">
                <div class="small-card" style="flex:1">
                  <h3 style="margin:0 0 0.5rem 0;font-weight:600">รายงานการแจ้งซ่อมอุปกรณ์ภายในห้อง</h3>
                  <div id="report-repairs-content" style="margin-top:0.5rem">กำลังโหลด...</div>
                </div>
                <div class="small-card" style="flex:1">
                  <h3 style="margin:0 0 0.5rem 0;font-weight:600">ใบแจ้งชำระเงิน</h3>
                  <div id="report-invoice-content" style="margin-top:0.5rem">กำลังโหลด...</div>
                </div>
              </div>
              <div style="display:flex; gap:1rem;">
                <div class="small-card" style="flex:1">
                  <h3 style="margin:0 0 0.5rem 0;font-weight:600">รายงานห้องพัก</h3>

                </div>
                <div class="small-card" style="flex:1">
                  <h3 style="margin:0 0 0.5rem 0;font-weight:600">รายงานรายรับ</h3>
                  <div id="report-revenue-content" style="margin-top:0.5rem">กำลังโหลด...</div>
                </div>
              </div>
              <div style="display:flex; gap:1rem;">
                <div class="small-card" style="flex:1">
                  <h3 style="margin:0 0 0.5rem 0;font-weight:600">รายงานสรุปการใช้น้ำ-ไฟ</h3>
                  <div id="report-utility-content" style="margin-top:0.5rem">กำลังโหลด...</div>
                </div>
              </div>
            </div>

            <div class="charts-grid" style="margin-top:1rem">
              <div class="chart-card">
                <h3>สถานะสัญญา</h3>
                <canvas id="chartContracts"></canvas>
              </div>

              <div class="chart-card">
                <h3>สถานะห้องพัก</h3>
                <canvas id="chartRooms"></canvas>
              </div>

              <div class="chart-card">
                <h3>ข่าวประชาสัมพันธ์</h3>
                <canvas id="chartNews"></canvas>
              </div>

              <div class="chart-card">
                <h3>สถานะการแจ้งซ่อม</h3>
                <canvas id="chartRepairs"></canvas>
              </div>

              <div class="chart-card">
                <h3>ยอดชำระ (6 เดือน)</h3>
                <canvas id="chartPayments"></canvas>
              </div>

              <div class="chart-card">
                <h3>สรุปการใช้น้ำ-ไฟ (6 เดือน)</h3>
                <canvas id="chartUtility"></canvas>
              </div>
            </div>

          </section>
        </div>
      </main>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      const DATA = <?php echo json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

      function makePie(ctx, labels, data, colors){
        return new Chart(ctx, {
          type: 'doughnut',
          data: { labels, datasets: [{ data, backgroundColor: colors || ['#60a5fa','#f97316','#34d399','#f87171'] }] },
          options: { plugins: { legend: { position: 'bottom' } } }
        });
      }

      function makeBar(ctx, labels, data, color){
        return new Chart(ctx, { type: 'bar', data: { labels, datasets:[{ label:'', data, backgroundColor: color || '#60a5fa' }] }, options:{ scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ display:false } } } });
      }

      // Contracts
      (function(){
        const labels = Object.keys(DATA.contractStatus).map(k => ({'0':'รอเข้าพัก','1':'กำลังเข้าพัก','2':'ยกเลิก/สิ้นสุด'}[k] || k));
        const data = Object.values(DATA.contractStatus);
        makePie(document.getElementById('chartContracts').getContext('2d'), labels, data);
      })();

      // Rooms
      (function(){
        const labels = Object.keys(DATA.roomStatus).map(k => ({'0':'ว่าง','1':'ไม่ว่าง'}[k] || k));
        const data = Object.values(DATA.roomStatus);
        makePie(document.getElementById('chartRooms').getContext('2d'), labels, data);
      })();

      // Repairs
      (function(){
        const labels = Object.keys(DATA.repairStatus).map(k => ({'0':'รอซ่อม','1':'กำลังซ่อม','2':'ซ่อมเสร็จแล้ว'}[k] || k));
        const data = Object.values(DATA.repairStatus);
        makePie(document.getElementById('chartRepairs').getContext('2d'), labels, data);
      })();

      // Payments
      (function(){
        const labels = DATA.payments.months.length ? DATA.payments.months : ['-'];
        const data = DATA.payments.totals.length ? DATA.payments.totals : [0];
        makeBar(document.getElementById('chartPayments').getContext('2d'), labels, data, '#f97316');
      })();

      // Utility
      (function(){
        const labels = DATA.utility.months.length ? DATA.utility.months : ['-'];
        const data = DATA.utility.totals.length ? DATA.utility.totals : [0];
        makeBar(document.getElementById('chartUtility').getContext('2d'), labels, data, '#06b6d4');
      })();

      // News
      (function(){
        const labels = DATA.news.months.length ? DATA.news.months : ['-'];
        const data = DATA.news.counts.length ? DATA.news.counts : [0];
        makeBar(document.getElementById('chartNews').getContext('2d'), labels, data, '#3b82f6');
      })();

      // Auto-load all reports on page load
      const reportMap = {
        'report-news-content': 'manage_news.php',
        'report-repairs-content': 'report_repairs.php',
        'report-invoice-content': 'report_invoice.php',
        'report-utility-content': 'report_utility.php',
        'report-revenue-content': 'report_revenue.php',
      };

      Object.keys(reportMap).forEach(id => {
        const contentDiv = document.getElementById(id);
        if (!contentDiv) return;
        
        fetch(reportMap[id])
          .then(res => res.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const table = doc.querySelector('.report-table');
            contentDiv.innerHTML = table ? table.outerHTML : '<p>ไม่พบข้อมูล</p>';
          })
          .catch(err => {
            contentDiv.innerHTML = '<p style="color:#f87171">เกิดข้อผิดพลาดในการโหลด</p>';
            console.error(err);
          });
      });
    </script>
  </body>
</html>
