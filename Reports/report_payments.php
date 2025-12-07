<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// โหลดค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) {}

// Utility helpers
function renderCell(mixed $value): string {
  if ($value === null || $value === '') return '—';
  if (is_numeric($value)) return number_format((float)$value, 2);
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatThaiDate(?string $dateStr): string {
  if (!$dateStr) return '—';
  try {
    $dt = new DateTime($dateStr);
  } catch (Exception $e) {
    return htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8');
  }
  $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  $day = $dt->format('j');
  $month = $months[(int)$dt->format('n') - 1] ?? $dt->format('m');
  $year = ((int)$dt->format('Y')) + 543 - 2500; // ให้ได้รูปแบบ 2 หลักแบบ พ.ศ. เช่น 68
  return $day . ' ' . $month . ' ' . str_pad((string)$year, 2, '0', STR_PAD_LEFT);
}

function timeAgoThai(?string $dateStr): string {
  if (!$dateStr) return '';
  try {
    $dt = new DateTime($dateStr, new DateTimeZone('Asia/Bangkok'));
    $now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
  } catch (Exception $e) {
    return '';
  }

  $diff = $now->getTimestamp() - $dt->getTimestamp();
  if ($diff < 0) return '';

  $units = [
    ['sec', 60, 'วินาที'],
    ['min', 3600, 'นาที'],
    ['hour', 86400, 'ชม.'],
    ['day', 2592000, 'วัน'],
    ['month', 31104000, 'เดือน'],
    ['year', PHP_INT_MAX, 'ปี'],
  ];

  if ($diff < 60) {
    return $diff . ' วินาทีที่แล้ว';
  }
  if ($diff < 3600) {
    $m = floor($diff / 60);
    return $m . ' นาทีที่แล้ว';
  }
  if ($diff < 86400) {
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    return $h . ' ชม.' . ($m > 0 ? ' ' . $m . ' นาที' : '') . 'ที่แล้ว';
  }
  if ($diff < 2592000) {
    $d = floor($diff / 86400);
    return $d . ' วันที่แล้ว';
  }
  if ($diff < 31104000) {
    $mo = floor($diff / 2592000);
    return $mo . ' เดือนที่แล้ว';
  }
  $y = floor($diff / 31104000);
  return $y . ' ปีที่แล้ว';
}

$rows = [];
$errorMessage = '';
$hasPayDate = true;
$hasPayStatus = true;
$hasPayAmount = true;
$hasPayProof = true;
$hasCtr = false;
$hasTnt = false;
$hasRoom = true;  // แสดงคอลัมน์ห้องเสมอเพราะดึงจาก JOIN
$hasNote = false;

// mapping column ชื่อ → ภาษาไทย
$columnLabels = [
  'pay_id'    => 'รหัส',
  'ctr_id'    => 'สัญญา',
  'tnt_id'    => 'ผู้เช่า',
  'room_id'   => 'ห้อง',
  'pay_amount'=> 'ยอดชำระ',
  'pay_date'  => 'วันที่ชำระ',
  'pay_status'=> 'สถานะ',
  'pay_proof' => 'หลักฐาน',
  'pay_note'  => 'หมายเหตุ',
];

try {
  $stmt = $pdo->query("SHOW COLUMNS FROM payment");
  $existingCols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasPayDate = in_array('pay_date', $existingCols, true);
  $hasPayStatus = in_array('pay_status', $existingCols, true);
  $hasPayAmount = in_array('pay_amount', $existingCols, true);
  $hasPayProof = in_array('pay_proof', $existingCols, true);
  $hasCtr = false; // ไม่มีใน payment แต่จะดึงจาก expense
  $hasTnt = false; // ไม่มีใน payment
  $hasRoom = true;  // แสดงเสมอ - ดึงจาก JOIN
  $hasNote = in_array('pay_note', $existingCols, true);

  $order = $hasPayDate ? 'ORDER BY p.pay_date DESC' : '';
  $sql = "SELECT p.*, e.exp_id, e.ctr_id as exp_ctr_id, c.room_id as contract_room_id, r.room_number 
          FROM payment p 
          LEFT JOIN expense e ON p.exp_id = e.exp_id
          LEFT JOIN contract c ON e.ctr_id = c.ctr_id
          LEFT JOIN room r ON c.room_id = r.room_id 
          $order";
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Debug: แสดงข้อมูลแถวแรก
  if (!empty($rows)) {
    error_log("Sample row: " . print_r($rows[0], true));
  }
} catch (PDOException $e) {
  $errorMessage = $e->getMessage();
}

// สรุปสถานะและยอดรวม (หากมีคอลัมน์ที่เกี่ยวข้อง)
$summary = [
  'pending' => null,
  'verified' => null,
  'total' => null,
  'range' => null,
];
try {
  if ($hasPayStatus) {
    $summary['pending'] = (int)($pdo->query("SELECT COUNT(*) FROM payment WHERE pay_status = 0")->fetchColumn());
    $summary['verified'] = (int)($pdo->query("SELECT COUNT(*) FROM payment WHERE pay_status = 1")->fetchColumn());
  }
  if ($hasPayAmount) {
    $summary['total'] = (float)($pdo->query("SELECT SUM(pay_amount) FROM payment")->fetchColumn());
  }
  if ($hasPayDate) {
    $rangeStmt = $pdo->query("SELECT MIN(pay_date) as dmin, MAX(pay_date) as dmax FROM payment");
    $range = $rangeStmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($range['dmin']) && !empty($range['dmax'])) {
      $d1 = new DateTime($range['dmin']);
      $d2 = new DateTime($range['dmax']);
      $diffDays = (int)$d1->diff($d2)->format('%a') + 1;
      $summary['range'] = [
        'days' => $diffDays,
        'start' => $range['dmin'],
        'end' => $range['dmax'],
      ];
    }
  }
} catch (PDOException $e) {}

$statusLabels = [
  '0' => 'รอตรวจสอบ',
  '1' => 'ตรวจสอบแล้ว',
];
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายละเอียดใบแจ้งชำระเงิน</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'รายละเอียดใบแจ้งชำระเงิน';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>ใบแจ้งชำระเงินทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.25rem;">รายการชำระเงินทุกสถานะ พร้อมหลักฐานการชำระ</p>
              </div>
            </div>

            <?php if ($summary['pending'] !== null || $summary['verified'] !== null || $summary['total'] !== null || $summary['range'] !== null): ?>
            <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1.25rem;">
              <?php if ($summary['range'] !== null): ?>
              <div style="background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1rem;">
                <div style="color:#cbd5e1;font-size:0.95rem;">ช่วงการชำระ</div>
                <div style="font-size:1.4rem;font-weight:700;color:#e2e8f0;line-height:1.3;"><?php echo number_format($summary['range']['days']); ?> วัน</div>
                <div style="color:#e2e8f0;margin-top:0.35rem;font-size:1.05rem;">
                  <?php echo formatThaiDate($summary['range']['start']); ?> - <?php echo formatThaiDate($summary['range']['end']); ?>
                </div>
                <?php $agoStart = timeAgoThai($summary['range']['start']); $agoEnd = timeAgoThai($summary['range']['end']); ?>
                <?php if ($agoStart || $agoEnd): ?>
                  <div style="color:#94a3b8;font-size:0.95rem; margin-top:0.1rem;">
                    (<?php echo $agoStart ? 'เริ่ม ' . htmlspecialchars($agoStart, ENT_QUOTES, 'UTF-8') : ''; ?><?php echo ($agoStart && $agoEnd) ? ' · ' : ''; ?><?php echo $agoEnd ? 'ล่าสุด ' . htmlspecialchars($agoEnd, ENT_QUOTES, 'UTF-8') : ''; ?>)
                  </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));gap:0.75rem;">
                <?php if ($summary['pending'] !== null): ?>
                <div style="background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1rem;">
                  <div style="color:#cbd5e1;font-size:0.95rem;">รอตรวจสอบ</div>
                  <div style="font-size:1.4rem;font-weight:700;color:#e2e8f0;line-height:1.3;"><?php echo number_format($summary['pending']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($summary['verified'] !== null): ?>
                <div style="background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1rem;">
                  <div style="color:#cbd5e1;font-size:0.95rem;">ตรวจสอบแล้ว</div>
                  <div style="font-size:1.4rem;font-weight:700;color:#22c55e;line-height:1.3;"><?php echo number_format($summary['verified']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($summary['total'] !== null): ?>
                <div style="background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1rem;">
                  <div style="color:#cbd5e1;font-size:0.95rem;">ยอดชำระรวม</div>
                  <div style="font-size:1.4rem;font-weight:700;color:#e2e8f0;line-height:1.3;">฿<?php echo number_format($summary['total'], 2); ?></div>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
              <div class="alert" style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.35);color:#fecdd3;padding:0.85rem 1rem;border-radius:10px;">
                ไม่สามารถโหลดข้อมูลได้: <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php else: ?>
              <div class="report-table" style="margin-top:0.75rem;overflow:auto;">
                <table class="table--compact" id="table-payments">
                  <thead>
                    <tr>
                      <?php if ($hasRoom): ?>
                        <th><?php echo $columnLabels['room_id']; ?></th>
                      <?php endif; ?>
                      <?php if ($hasPayDate): ?>
                        <th><?php echo $columnLabels['pay_date']; ?></th>
                      <?php endif; ?>
                      <?php if ($hasPayAmount): ?>
                        <th><?php echo $columnLabels['pay_amount']; ?></th>
                      <?php endif; ?>
                      <?php if ($hasCtr): ?>
                        <th><?php echo $columnLabels['ctr_id']; ?></th>
                      <?php endif; ?>
                      <?php if ($hasTnt): ?>
                        <th><?php echo $columnLabels['tnt_id']; ?></th>
                      <?php endif; ?>
                      <?php if ($hasNote): ?>
                        <th><?php echo $columnLabels['pay_note']; ?></th>
                      <?php endif; ?>
                      <?php if ($hasPayProof): ?>
                        <th><?php echo $columnLabels['pay_proof']; ?></th>
                      <?php endif; ?>
                      <?php if ($hasPayStatus): ?>
                        <th><?php echo $columnLabels['pay_status']; ?></th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <?php if ($hasRoom): ?>
                        <td>
                          <?php 
                            $roomNum = $row['room_number'] ?? null;
                            $roomId = $row['contract_room_id'] ?? $row['room_id'] ?? null;
                            if ($roomNum && $roomId): ?>
                              <a href="manage_expenses.php?room_id=<?php echo htmlspecialchars((string)$roomId, ENT_QUOTES, 'UTF-8'); ?>" style="color:#3b82f6;text-decoration:none;font-weight:600;transition:all 0.2s cubic-bezier(0.32, 0.72, 0, 1);" onmouseover="this.style.color='#60a5fa';this.style.textDecoration='underline'" onmouseout="this.style.color='#3b82f6';this.style.textDecoration='none'">
                                <?php echo htmlspecialchars($roomNum, ENT_QUOTES, 'UTF-8'); ?>
                              </a>
                            <?php elseif ($roomNum): 
                              echo htmlspecialchars($roomNum, ENT_QUOTES, 'UTF-8');
                            else: 
                              echo renderCell($roomId);
                            endif; ?>
                        </td>
                      <?php endif; ?>
                      <?php if ($hasPayDate): ?>
                        <td>
                          <div><?php echo formatThaiDate($row['pay_date'] ?? null); ?></div>
                          <?php $ago = timeAgoThai($row['pay_date'] ?? null); ?>
                          <?php if ($ago): ?>
                            <div style="color:#94a3b8;font-size:0.9rem;">(<?php echo htmlspecialchars($ago, ENT_QUOTES, 'UTF-8'); ?>)</div>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                      <?php if ($hasPayAmount): ?>
                        <?php $val = $row['pay_amount'] ?? null; ?>
                        <td><?php echo is_numeric($val) ? number_format((float)$val, 2) : renderCell($val); ?></td>
                      <?php endif; ?>
                      <?php if ($hasCtr): ?>
                        <td><?php echo renderCell($row['ctr_id'] ?? null); ?></td>
                      <?php endif; ?>
                      <?php if ($hasTnt): ?>
                        <td><?php echo renderCell($row['tnt_id'] ?? null); ?></td>
                      <?php endif; ?>
                      <?php if ($hasNote): ?>
                        <td><?php echo renderCell($row['pay_note'] ?? null); ?></td>
                      <?php endif; ?>
                      <?php if ($hasPayProof): ?>
                        <?php $proofFile = $row['pay_proof'] ?? ''; ?>
                        <?php $safeName = $proofFile ? basename((string)$proofFile) : ''; ?>
                        <?php $proofPath = $safeName ? (__DIR__ . '/../Assets/Images/Payments/' . $safeName) : ''; ?>
                        <?php $proofUrl = $safeName ? ('../Assets/Images/Payments/' . rawurlencode($safeName)) : ''; ?>
                        <td>
                          <?php if ($safeName && file_exists($proofPath)): ?>
                            <button type="button" class="view-proof-btn" data-proof-url="<?php echo htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8'); ?>" style="background:linear-gradient(135deg, #3b82f6, #2563eb);color:#fff;border:none;padding:0.4rem 0.8rem;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600;">
                              📄 ดูหลักฐาน
                            </button>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                      <?php if ($hasPayStatus): ?>
                        <?php $statusVal = (string)($row['pay_status'] ?? ''); ?>
                        <td>
                          <?php if ($statusVal === '1'): ?>
                            <span class="tag tag-success">✓ ตรวจสอบแล้ว</span>
                          <?php elseif ($statusVal === '0'): ?>
                            <span class="tag tag-warning">⏳ รอตรวจสอบ</span>
                          <?php else: ?>
                            <span class="tag">ไม่ทราบสถานะ</span>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>

    <!-- Modal สำหรับดูหลักฐานการชำระเงิน -->
    <div id="proofModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0);z-index:9999;padding:2rem;box-sizing:border-box;transition:background 0.45s cubic-bezier(0.32, 0.72, 0, 1);opacity:0;">
      <div style="position:relative;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <button id="closeProofModal" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,0.15);color:#fff;border:none;width:3rem;height:3rem;border-radius:50%;cursor:pointer;font-size:1.5rem;font-weight:600;transition:all 0.4s cubic-bezier(0.32, 0.72, 0, 1);backdrop-filter:blur(20px) saturate(180%);transform:scale(0.7);opacity:0;box-shadow:0 8px 32px rgba(0,0,0,0.3);" onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.transform='scale(1.05)'" onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='scale(1)'">✕</button>
        <div id="proofContent" style="max-width:90%;max-height:90%;overflow:auto;background:rgba(15,23,42,0.75);border-radius:20px;padding:1.5rem;backdrop-filter:blur(40px) saturate(180%);box-shadow:0 25px 50px -12px rgba(0,0,0,0.6),0 0 1px rgba(255,255,255,0.1) inset;transition:all 0.5s cubic-bezier(0.32, 0.72, 0, 1);transform:scale(0.85) translateY(40px);opacity:0;border:1px solid rgba(255,255,255,0.08);">
          <img id="proofImage" src="" alt="หลักฐานการชำระเงิน" style="max-width:100%;height:auto;border-radius:12px;display:none;transition:opacity 0.5s cubic-bezier(0.32, 0.72, 0, 1),transform 0.5s cubic-bezier(0.32, 0.72, 0, 1);transform:scale(0.98);" />
          <embed id="proofEmbed" src="" type="application/pdf" style="width:80vw;height:80vh;border-radius:12px;display:none;transition:opacity 0.5s cubic-bezier(0.32, 0.72, 0, 1);" />
          <div id="proofError" style="display:none;color:#f87171;padding:2rem;text-align:center;font-size:1.1rem;transition:opacity 0.5s cubic-bezier(0.32, 0.72, 0, 1);">ไม่สามารถแสดงไฟล์ได้</div>
        </div>
      </div>
    </div>

    <script src="../Assets/Javascript/confirm-modal.js"></script>
    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/animate-ui.js"></script>
    <script src="../Assets/Javascript/main.js"></script>
    <script>
      // Modal สำหรับดูหลักฐาน
      const proofModal = document.getElementById('proofModal');
      const closeProofModal = document.getElementById('closeProofModal');
      const proofImage = document.getElementById('proofImage');
      const proofEmbed = document.getElementById('proofEmbed');
      const proofError = document.getElementById('proofError');

      document.querySelectorAll('.view-proof-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const url = this.getAttribute('data-proof-url');
          if (!url) return;

          // ซ่อนทุกอย่างก่อน
          proofImage.style.display = 'none';
          proofImage.style.opacity = '0';
          proofEmbed.style.display = 'none';
          proofEmbed.style.opacity = '0';
          proofError.style.display = 'none';
          proofError.style.opacity = '0';

          // แสดง modal
          proofModal.style.display = 'block';
          document.body.style.overflow = 'hidden';

          // เริ่ม transition แบบ Apple
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              proofModal.style.opacity = '1';
              proofModal.style.background = 'rgba(0,0,0,0.92)';
              proofContent.style.transform = 'scale(1) translateY(0)';
              proofContent.style.opacity = '1';
              closeProofModal.style.transform = 'scale(1)';
              closeProofModal.style.opacity = '1';
            });
          });

          // ตรวจสอบประเภทไฟล์
          const ext = url.split('.').pop().toLowerCase();
          if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            proofImage.src = url;
            proofImage.style.display = 'block';
            setTimeout(() => { 
              proofImage.style.opacity = '1';
              proofImage.style.transform = 'scale(1)';
            }, 400);
            proofImage.onerror = function() {
              proofImage.style.display = 'none';
              proofError.style.display = 'block';
              setTimeout(() => { proofError.style.opacity = '1'; }, 50);
            };
          } else if (ext === 'pdf') {
            proofEmbed.src = url;
            proofEmbed.style.display = 'block';
            setTimeout(() => { proofEmbed.style.opacity = '1'; }, 400);
          } else {
            proofError.textContent = 'ไม่รองรับไฟล์นามสกุล .' + ext;
            proofError.style.display = 'block';
            setTimeout(() => { proofError.style.opacity = '1'; }, 400);
          }
        });
      });

      closeProofModal.addEventListener('click', function() {
        // Fade out animation แบบ Apple - smooth และ graceful
        proofImage.style.opacity = '0';
        proofImage.style.transform = 'scale(0.98)';
        proofEmbed.style.opacity = '0';
        proofError.style.opacity = '0';
        
        setTimeout(() => {
          proofModal.style.opacity = '0';
          proofModal.style.background = 'rgba(0,0,0,0)';
          proofContent.style.transform = 'scale(0.85) translateY(40px)';
          proofContent.style.opacity = '0';
          closeProofModal.style.transform = 'scale(0.7)';
          closeProofModal.style.opacity = '0';
        }, 50);

        setTimeout(() => {
          proofModal.style.display = 'none';
          document.body.style.overflow = '';
          proofImage.src = '';
          proofEmbed.src = '';
        }, 500);
      });

      // ปิด modal เมื่อกดพื้นหลัง
      proofModal.addEventListener('click', function(e) {
        if (e.target === proofModal) {
          closeProofModal.click();
        }
      });

      // ปิด modal เมื่อกด ESC
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && proofModal.style.display === 'block') {
          closeProofModal.click();
        }
      });

      // ฟังก์ชันตรวจสอบและอัปเดตสถานะการชำระเงิน
      async function updatePaymentStatus(payId, newStatus) {
        try {
          const formData = new FormData();
          formData.append('pay_id', payId);
          formData.append('pay_status', newStatus);

          const response = await fetch('../Manage/update_payment_status.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const result = await response.json();
          if (result.success) {
            showSuccessToast(result.message || 'อัปเดตสถานะสำเร็จ');
            setTimeout(() => location.reload(), 1500);
          } else {
            showErrorToast(result.error || 'เกิดข้อผิดพลาด');
          }
        } catch (error) {
          console.error('Error:', error);
          showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
        }
      }

      // ปุ่มตรวจสอบ (เปลี่ยนสถานะเป็น 1)
      console.log('กำลังตั้งค่า event listeners...');
      const verifyBtns = document.querySelectorAll('.verify-payment-btn');
      console.log('พบปุ่มตรวจสอบ:', verifyBtns.length, 'ปุ่ม');
      
      verifyBtns.forEach((btn, index) => {
        console.log('กำลัง bind ปุ่มที่', index + 1, 'pay-id:', btn.getAttribute('data-pay-id'));
        btn.addEventListener('click', async function(e) {
          e.preventDefault();
          e.stopPropagation();
          console.log('คลิกปุ่มตรวจสอบ!!!');
          const payId = this.getAttribute('data-pay-id');
          console.log('Pay ID:', payId);
          
          if (!payId) {
            alert('ไม่พบ Pay ID');
            return;
          }

          // ตรวจสอบว่า showConfirmDialog มีหรือไม่
          if (typeof showConfirmDialog !== 'function') {
            alert('ฟังก์ชัน showConfirmDialog ไม่พบ');
            // ใช้ confirm ธรรมดาแทน
            if (confirm('คุณต้องการยืนยันว่าได้ตรวจสอบหลักฐานการชำระเงินนี้แล้ว?')) {
              updatePaymentStatus(payId, '1');
            }
            return;
          }

          const confirmed = await showConfirmDialog(
            'ยืนยันการตรวจสอบ',
            'คุณต้องการยืนยันว่าได้ตรวจสอบหลักฐานการชำระเงินนี้แล้ว?',
            'warning'
          );

          console.log('Confirmed:', confirmed);
          if (confirmed) {
            updatePaymentStatus(payId, '1');
          }
        });
      });

      // ปุ่มยกเลิก (เปลี่ยนสถานะกลับเป็น 0)
      const revertBtns = document.querySelectorAll('.revert-status-btn');
      console.log('พบปุ่มยกเลิก:', revertBtns.length, 'ปุ่ม');
      
      revertBtns.forEach((btn, index) => {
        console.log('กำลัง bind ปุ่มยกเลิกที่', index + 1, 'pay-id:', btn.getAttribute('data-pay-id'));
        btn.addEventListener('click', async function(e) {
          e.preventDefault();
          e.stopPropagation();
          console.log('คลิกปุ่มยกเลิก!!!');
          const payId = this.getAttribute('data-pay-id');
          console.log('Pay ID:', payId);
          
          if (!payId) {
            alert('ไม่พบ Pay ID');
            return;
          }

          // ตรวจสอบว่า showConfirmDialog มีหรือไม่
          if (typeof showConfirmDialog !== 'function') {
            alert('ฟังก์ชัน showConfirmDialog ไม่พบ');
            // ใช้ confirm ธรรมดาแทน
            if (confirm('คุณต้องการยกเลิกสถานะ "ตรวจสอบแล้ว" ของรายการนี้?')) {
              updatePaymentStatus(payId, '0');
            }
            return;
          }

          const confirmed = await showConfirmDialog(
            'ยกเลิกการตรวจสอบ',
            'คุณต้องการยกเลิกสถานะ "ตรวจสอบแล้ว" ของรายการนี้?',
            'delete'
          );

          console.log('Confirmed:', confirmed);
          if (confirmed) {
            updatePaymentStatus(payId, '0');
          }
        });
      });
    </script>
  </body>
</html>
