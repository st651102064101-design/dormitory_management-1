<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// Helper: safe count query to avoid fatal errors
function safeCount(PDO $pdo, string $sql): int {
  try {
    $stmt = $pdo->query($sql);
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return 0;
  }
}

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

// ดึงสถิติต่างๆ
$roomCount = safeCount($pdo, "SELECT COUNT(*) FROM room");
$tenantCount = safeCount($pdo, "SELECT COUNT(*) FROM tenant");
$contractCount = safeCount($pdo, "SELECT COUNT(*) FROM contract WHERE ctr_status = '0'");
$contractTotalCount = safeCount($pdo, "SELECT COUNT(*) FROM contract");
$bookingCount = safeCount($pdo, "SELECT COUNT(*) FROM booking WHERE bkg_status = '1'");
$repairCount = safeCount($pdo, "SELECT COUNT(*) FROM repair WHERE repair_status = '0'");
$newsCount = safeCount($pdo, "SELECT COUNT(*) FROM news");
$paymentPendingCount = safeCount($pdo, "SELECT COUNT(*) FROM payment WHERE pay_status = '0'");
$utilityCount = safeCount($pdo, "SELECT COUNT(*) FROM utility");
$qrCodeCount = safeCount($pdo, "SELECT COUNT(*) FROM contract WHERE ctr_status IN ('0', '2')");
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการระบบ</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <style>
      .manage-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
      }
      
      .manage-card {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 12px;
        padding: 2rem;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
      }
      
      .manage-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        border-color: #3b82f6;
      }
      
      .manage-card-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
      }
      
      .manage-card-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 0.5rem;
      }
      
      .manage-card-desc {
        color: #94a3b8;
        margin-bottom: 1rem;
        line-height: 1.6;
      }
      
      .manage-card-count {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #3b82f6;
        font-weight: 600;
        font-size: 1.1rem;
      }
      
      .page-header {
        margin-bottom: 2rem;
      }
      
      .page-header h1 {
        color: #fff;
        font-size: 2rem;
        margin-bottom: 0.5rem;
      }
      
      .page-header p {
        color: #94a3b8;
        font-size: 1.1rem;
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div style="width:100%;">
          <header style="display:flex;align-items:center;gap:0.5rem;margin-bottom:2rem;justify-content:flex-start;">
            <button id="sidebar-toggle" aria-label="Toggle sidebar" aria-expanded="true" style="background:transparent;border:0;color:#fff;padding:0.6rem 0.85rem;border-radius:6px;cursor:pointer;font-size:1.25rem;flex:0 0 auto;">☰</button>
            <h2 style="margin:0;color:#fff;font-size:1.05rem;flex:0 0 auto;text-align:left;">จัดการระบบ</h2>
          </header>

          <section class="manage-panel">
            <div class="page-header">
              <p>เลือกเมนูที่ต้องการจัดการ</p>
            </div>

            <div class="manage-grid">
              <a href="manage_news.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg></span>
                <div class="manage-card-title">ข่าวประชาสัมพันธ์</div>
                <div class="manage-card-desc">จัดการข่าวสาร ประกาศ และข้อมูลต่างๆ</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                  <span><?php echo number_format($newsCount); ?> รายการ</span>
                </div>
              </a>

              <a href="manage_rooms.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                <div class="manage-card-title">ห้องพัก</div>
                <div class="manage-card-desc">จัดการห้องพัก ประเภทห้อง และอัตราค่าเช่า</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                  <span><?php echo number_format($roomCount); ?> ห้อง</span>
                </div>
              </a>

              <a href="manage_tenants.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <div class="manage-card-title">ผู้เช่า</div>
                <div class="manage-card-desc">จัดการข้อมูลผู้เช่า เพิ่ม แก้ไข ลบ</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                  <span><?php echo number_format($tenantCount); ?> คน</span>
                </div>
              </a>

              <a href="manage_booking.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                <div class="manage-card-title">จองห้องพัก</div>
                <div class="manage-card-desc">จัดการการจองห้องพัก อนุมัติ ยกเลิก</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                  <span><?php echo number_format($bookingCount); ?> จองแล้ว</span>
                </div>
              </a>

              <a href="manage_contracts.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                <div class="manage-card-title">สัญญาเช่า</div>
                <div class="manage-card-desc">จัดการสัญญาเช่า ต่อสัญญา และสิ้นสุดสัญญา</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                  <span><?php echo number_format($contractCount); ?> สัญญาที่ใช้งาน (ทั้งหมด <?php echo number_format($contractTotalCount); ?>)</span>
                </div>
              </a>

              <a href="manage_payments.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
                <div class="manage-card-title">การชำระเงิน</div>
                <div class="manage-card-desc">จัดการการชำระค่าเช่า ตรวจสอบ อนุมัติ</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                  <span><?php echo number_format($paymentPendingCount); ?> รอตรวจสอบ</span>
                </div>
              </a>

              <a href="report_utility.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
                <div class="manage-card-title">บิลค่าน้ำค่าไฟ</div>
                <div class="manage-card-desc">จัดการบันทึกมิเตอร์ น้ำ ไฟ รายเดือน</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                  <span><?php echo number_format($utilityCount); ?> รายการ</span>
                </div>
              </a>

              <a href="manage_expenses.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                <div class="manage-card-title">ค่าใช้จ่าย</div>
                <div class="manage-card-desc">บันทึกและจัดการค่าใช้จ่ายต่างๆ</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                  <span>รายการค่าใช้จ่าย</span>
                </div>
              </a>

              <a href="manage_repairs.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
                <div class="manage-card-title">แจ้งซ่อม</div>
                <div class="manage-card-desc">จัดการการแจ้งซ่อม ติดตามสถานะ</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                  <span><?php echo number_format($repairCount); ?> รอดำเนินการ</span>
                </div>
              </a>

              <a href="system_settings.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                <div class="manage-card-title">ตั้งค่าระบบ</div>
                <div class="manage-card-desc">ปรับแต่งระบบ ธีม สี และการตั้งค่าต่างๆ</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                  <span>การตั้งค่า</span>
                </div>
              </a>

              <a href="qr_codes.php" class="manage-card">
                <span class="manage-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
                <div class="manage-card-title">QR Code ผู้เช่า</div>
                <div class="manage-card-desc">สร้าง QR Code สำหรับผู้เช่าเข้าระบบ</div>
                <div class="manage-card-count">
                  <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span>
                  <span><?php echo number_format($qrCodeCount); ?> ห้องที่มี QR</span>
                </div>
              </a>
            </div>
          </section>
        </div>
      </main>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
  </body>
</html>
