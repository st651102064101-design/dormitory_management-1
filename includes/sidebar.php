<?php
// Expect session already started and $adminName set in including script
$adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';

// ดึงชื่อระบบและการตั้งค่าจาก database
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#0f172a';
$fontSize = '1';
$adminGoogleLinked = false;
$adminGoogleEmail = '';
$adminRecoveryEmail = '';
$wizardIncompleteCount = 0;
$pendingPaymentReviewCount = 0;
$expenseStatusBadgeCounts = [
  'unpaid' => 0,
  'pending' => 0,
  'partial' => 0,
  'paid' => 0,
];
$paymentStatusBadgeCounts = [
  'unpaid' => 0,
  'pending' => 0,
  'paid' => 0,
];
$utilityStatusBadgeCounts = [
  'water' => 0,
  'electric' => 0,
];
$repairStatusBadgeCounts = [
  'pending' => 0,
  'inprogress' => 0,
  'done' => 0,
  'cancelled' => 0,
];
$bookingStatusBadgeCounts = [
  'reserved' => 0,
  'checkedin' => 0,
  'cancelled' => 0,
];
$sidebarDataLoadedFromDb = false;
$sidebarCacheTtlSeconds = 20;
$sidebarCacheKey = '__sidebar_snapshot_v1';
$sidebarSnapshot = $_SESSION[$sidebarCacheKey] ?? null;
$currentAdminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
$canUseSidebarSnapshot = is_array($sidebarSnapshot)
  && isset($sidebarSnapshot['ts'], $sidebarSnapshot['admin_id'], $sidebarSnapshot['data'])
  && (time() - (int)$sidebarSnapshot['ts'] <= $sidebarCacheTtlSeconds)
  && (int)$sidebarSnapshot['admin_id'] === $currentAdminId;

$sidebarAccountFlashSuccess = (string)($_SESSION['sidebar_account_flash_success'] ?? '');
$sidebarAccountFlashError = (string)($_SESSION['sidebar_account_flash_error'] ?? '');
$sidebarAccountModalUsername = (string)($_SESSION['sidebar_account_old_username'] ?? ($_SESSION['admin_username'] ?? ''));
$sidebarAccountHasOldRecoveryEmail = array_key_exists('sidebar_account_old_recovery_email', $_SESSION);
$sidebarAccountModalRecoveryEmail = (string)($_SESSION['sidebar_account_old_recovery_email'] ?? '');
$sidebarAccountAutoOpen = ($sidebarAccountFlashError !== '');
unset(
  $_SESSION['sidebar_account_flash_success'],
  $_SESSION['sidebar_account_flash_error'],
  $_SESSION['sidebar_account_old_username'],
  $_SESSION['sidebar_account_old_recovery_email']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sidebar_account_update'])) {
  $redirectTo = (string)($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
  $redirectTo = str_replace(["\r", "\n"], '', $redirectTo);
  if ($redirectTo === '') {
    $redirectTo = 'dashboard.php';
  }

  $submittedUsername = trim((string)($_POST['new_admin_username'] ?? ''));
  $submittedRecoveryEmail = trim((string)($_POST['recovery_email'] ?? ''));
  $currentPassword = (string)($_POST['current_admin_password'] ?? '');
  $newPassword = (string)($_POST['new_admin_password'] ?? '');
  $confirmPassword = (string)($_POST['confirm_admin_password'] ?? '');
  $accountError = '';

  if ($currentAdminId <= 0) {
    $accountError = 'ไม่พบข้อมูลผู้ดูแลระบบในเซสชัน';
  } elseif ($submittedUsername === '') {
    $accountError = 'กรุณากรอกชื่อผู้ใช้';
  } elseif ($currentPassword === '') {
    $accountError = 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันตัวตน';
  } elseif ($submittedRecoveryEmail !== '' && !filter_var($submittedRecoveryEmail, FILTER_VALIDATE_EMAIL)) {
    $accountError = 'รูปแบบอีเมลกู้คืนไม่ถูกต้อง';
  } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
    $accountError = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
  } elseif ($newPassword !== $confirmPassword) {
    $accountError = 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
  }

  if ($accountError === '') {
    try {
      require_once __DIR__ . '/../ConnectDB.php';
      $pdoAccount = connectDB();

      $currentAdminStmt = $pdoAccount->prepare('SELECT admin_id, admin_username, admin_password FROM admin WHERE admin_id = ? LIMIT 1');
      $currentAdminStmt->execute([$currentAdminId]);
      $currentAdminRow = $currentAdminStmt->fetch(PDO::FETCH_ASSOC);

      if (!$currentAdminRow) {
        $accountError = 'ไม่พบบัญชีผู้ดูแลระบบ';
      } else {
        $storedPassword = (string)($currentAdminRow['admin_password'] ?? '');
        $passwordOk = false;
        if ($storedPassword !== '' && password_verify($currentPassword, $storedPassword)) {
          $passwordOk = true;
        } elseif ($currentPassword === $storedPassword) {
          $passwordOk = true;
        }

        if (!$passwordOk) {
          $accountError = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        } else {
          $dupStmt = $pdoAccount->prepare('SELECT admin_id FROM admin WHERE admin_username = ? AND admin_id <> ? LIMIT 1');
          $dupStmt->execute([$submittedUsername, $currentAdminId]);
          if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
            $accountError = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
          } else {
            if ($newPassword !== '') {
              $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
              $updateStmt = $pdoAccount->prepare('UPDATE admin SET admin_username = ?, admin_password = ? WHERE admin_id = ?');
              $updateStmt->execute([$submittedUsername, $newPasswordHash, $currentAdminId]);
            } else {
              $updateStmt = $pdoAccount->prepare('UPDATE admin SET admin_username = ? WHERE admin_id = ?');
              $updateStmt->execute([$submittedUsername, $currentAdminId]);
            }

            $recoverySettingKey = 'admin_recovery_email_' . $currentAdminId;
            if ($submittedRecoveryEmail === '') {
              $deleteRecoveryStmt = $pdoAccount->prepare('DELETE FROM system_settings WHERE setting_key = ?');
              $deleteRecoveryStmt->execute([$recoverySettingKey]);
            } else {
              $updateRecoveryStmt = $pdoAccount->prepare('UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?');
              $updateRecoveryStmt->execute([$submittedRecoveryEmail, $recoverySettingKey]);
              if ($updateRecoveryStmt->rowCount() === 0) {
                $insertRecoveryStmt = $pdoAccount->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())');
                $insertRecoveryStmt->execute([$recoverySettingKey, $submittedRecoveryEmail]);
              }
            }

            $_SESSION['admin_username'] = $submittedUsername;
            if ($newPassword !== '' && $submittedRecoveryEmail !== '') {
              $_SESSION['sidebar_account_flash_success'] = 'อัปเดตชื่อผู้ใช้ รหัสผ่าน และอีเมลกู้คืนเรียบร้อยแล้ว';
            } elseif ($newPassword !== '') {
              $_SESSION['sidebar_account_flash_success'] = 'อัปเดตชื่อผู้ใช้และรหัสผ่านเรียบร้อยแล้ว';
            } elseif ($submittedRecoveryEmail !== '') {
              $_SESSION['sidebar_account_flash_success'] = 'อัปเดตชื่อผู้ใช้และอีเมลกู้คืนเรียบร้อยแล้ว';
            } else {
              $_SESSION['sidebar_account_flash_success'] = 'อัปเดตชื่อผู้ใช้เรียบร้อยแล้ว';
            }
            unset($_SESSION['__sidebar_snapshot_v1']);
            header('Location: ' . $redirectTo);
            exit;
          }
        }
      }
    } catch (Throwable $e) {
      $accountError = 'ไม่สามารถบันทึกข้อมูลได้ในขณะนี้';
    }
  }

  $_SESSION['sidebar_account_flash_error'] = $accountError;
  $_SESSION['sidebar_account_old_username'] = $submittedUsername;
  $_SESSION['sidebar_account_old_recovery_email'] = $submittedRecoveryEmail;
  header('Location: ' . $redirectTo);
  exit;
}

if ($canUseSidebarSnapshot) {
    $cached = (array)$sidebarSnapshot['data'];
    $siteName = (string)($cached['siteName'] ?? $siteName);
    $logoFilename = (string)($cached['logoFilename'] ?? $logoFilename);
    $themeColor = (string)($cached['themeColor'] ?? $themeColor);
    $fontSize = (string)($cached['fontSize'] ?? $fontSize);
    $adminGoogleLinked = !empty($cached['adminGoogleLinked']);
    $adminGoogleEmail = (string)($cached['adminGoogleEmail'] ?? $adminGoogleEmail);
    $adminRecoveryEmail = (string)($cached['adminRecoveryEmail'] ?? $adminRecoveryEmail);
    $wizardIncompleteCount = (int)($cached['wizardIncompleteCount'] ?? $wizardIncompleteCount);
    $pendingPaymentReviewCount = (int)($cached['pendingPaymentReviewCount'] ?? $pendingPaymentReviewCount);
    $expenseStatusBadgeCounts = (array)($cached['expenseStatusBadgeCounts'] ?? $expenseStatusBadgeCounts);
    $paymentStatusBadgeCounts = (array)($cached['paymentStatusBadgeCounts'] ?? $paymentStatusBadgeCounts);
    $utilityStatusBadgeCounts = (array)($cached['utilityStatusBadgeCounts'] ?? $utilityStatusBadgeCounts);
    $repairStatusBadgeCounts = (array)($cached['repairStatusBadgeCounts'] ?? $repairStatusBadgeCounts);
    $bookingStatusBadgeCounts = (array)($cached['bookingStatusBadgeCounts'] ?? $bookingStatusBadgeCounts);
    if (empty($_SESSION['admin_picture']) && !empty($cached['adminPicture'])) {
        $_SESSION['admin_picture'] = (string)$cached['adminPicture'];
    }
} else {
try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();
    
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'font_size', 'default_view_mode')");
    $sidebarSettings = [];
    foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
        $sidebarSettings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    $siteName = $sidebarSettings['site_name'] ?? $siteName;
    $logoFilename = $sidebarSettings['logo_filename'] ?? $logoFilename;
    $themeColor = $sidebarSettings['theme_color'] ?? $themeColor;
    $fontSize = $sidebarSettings['font_size'] ?? $fontSize;
    $defaultViewMode = isset($sidebarSettings['default_view_mode']) && strtolower($sidebarSettings['default_view_mode']) === 'list' ? 'list' : 'grid';
    
    // ตรวจสอบว่า admin เชื่อม Google หรือยัง
    if (!empty($_SESSION['admin_id'])) {
        $googleCheckStmt = $pdo->prepare("SELECT provider_email, picture FROM admin_oauth WHERE admin_id = ? AND provider = 'google'");
        $googleCheckStmt->execute([$_SESSION['admin_id']]);
        $googleData = $googleCheckStmt->fetch(PDO::FETCH_ASSOC);
        if ($googleData) {
            $adminGoogleLinked = true;
            $adminGoogleEmail = $googleData['provider_email'];
            // อัพเดท picture ใน session ถ้ายังไม่มี
            if (empty($_SESSION['admin_picture']) && !empty($googleData['picture'])) {
                $_SESSION['admin_picture'] = $googleData['picture'];
            }
        }
    }

      if ($currentAdminId > 0) {
        $recoveryEmailStmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $recoveryEmailStmt->execute(['admin_recovery_email_' . $currentAdminId]);
        $adminRecoveryEmail = (string)($recoveryEmailStmt->fetchColumn() ?: '');
      }

    // ดึงจำนวนผู้เช่าที่ยังไม่ครบ 5 ขั้นตอน ใน wizard
    $wizardCountStmt = $pdo->query("
        SELECT COUNT(*) as incomplete_count FROM booking b
        LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
        WHERE b.bkg_status != '0'
          AND (tw.id IS NULL OR tw.completed = 0)
    ");
    $wizardCountResult = $wizardCountStmt->fetch(PDO::FETCH_ASSOC);
    $wizardIncompleteCount = (int)($wizardCountResult['incomplete_count'] ?? 0);

    // ดึงจำนวนรายการชำระเงินที่ "รอตรวจสอบ"
    // รวมทั้งรายการจาก payment และ booking_payment (มัดจำจากขั้นตอนจอง)
    $paymentPendingStmt = $pdo->query("\n        SELECT\n            (SELECT COUNT(*) FROM payment WHERE COALESCE(pay_status, '0') = '0')\n          + (SELECT COUNT(*) FROM booking_payment WHERE COALESCE(bp_status, '0') = '0')\n          AS pending_count\n    ");
    $paymentPendingResult = $paymentPendingStmt->fetch(PDO::FETCH_ASSOC);
    $pendingPaymentReviewCount = (int)($paymentPendingResult['pending_count'] ?? 0);

    // ดึงจำนวนค่าใช้จ่ายแยกตามสถานะเพื่อแสดง alert badge ในเมนู
    $expenseStatusStmt = $pdo->query("\n        SELECT\n          SUM(CASE\n                WHEN COALESCE(pay.pending_count, 0) > 0 THEN 1\n                ELSE 0\n              END) AS pending_count,\n          SUM(CASE\n                WHEN COALESCE(pay.pending_count, 0) = 0\n                 AND COALESCE(pay.approved_amount, 0) >= COALESCE(e.exp_total, 0)\n                THEN 1\n                ELSE 0\n              END) AS paid_count,\n          SUM(CASE\n                WHEN COALESCE(pay.pending_count, 0) = 0\n                 AND COALESCE(pay.approved_amount, 0) > 0\n                 AND COALESCE(pay.approved_amount, 0) < COALESCE(e.exp_total, 0)\n                THEN 1\n                ELSE 0\n              END) AS partial_count,\n          SUM(CASE\n                WHEN COALESCE(pay.pending_count, 0) = 0\n                 AND COALESCE(pay.approved_amount, 0) <= 0\n                THEN 1\n                ELSE 0\n              END) AS unpaid_count\n        FROM expense e\n        INNER JOIN contract c\n          ON e.ctr_id = c.ctr_id\n         AND c.ctr_status = '0'\n        LEFT JOIN tenant_workflow tw\n          ON tw.ctr_id = c.ctr_id\n        LEFT JOIN (\n          SELECT\n            exp_id,\n            SUM(CASE\n                  WHEN pay_status = '0'\n                   AND pay_proof IS NOT NULL\n                   AND pay_proof <> ''\n                   AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'\n                  THEN 1\n                  ELSE 0\n                END) AS pending_count,\n            SUM(CASE\n                  WHEN pay_status = '1'\n                   AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'\n                  THEN pay_amount\n                  ELSE 0\n                END) AS approved_amount\n          FROM payment\n          GROUP BY exp_id\n        ) pay ON pay.exp_id = e.exp_id\n        WHERE (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)\n          AND YEAR(e.exp_month) = YEAR(CURDATE())\n          AND MONTH(e.exp_month) = MONTH(CURDATE())\n          AND NOT (DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(c.ctr_start, '%Y-%m'))\n    ");
    $expenseStatusResult = $expenseStatusStmt ? $expenseStatusStmt->fetch(PDO::FETCH_ASSOC) : [];
    $expenseStatusBadgeCounts = [
      'unpaid' => (int)($expenseStatusResult['unpaid_count'] ?? 0),
      'pending' => (int)($expenseStatusResult['pending_count'] ?? 0),
      'partial' => (int)($expenseStatusResult['partial_count'] ?? 0),
      'paid' => (int)($expenseStatusResult['paid_count'] ?? 0),
    ];

    // ดึงจำนวนรายการการชำระเงินแยกสถานะเพื่อแสดง alert badge ที่เมนูการชำระเงิน
    $paymentStatusStmt = $pdo->query("\n        SELECT\n          (SELECT COUNT(*) FROM payment WHERE COALESCE(pay_status, '0') = '0')\n          + (SELECT COUNT(*) FROM booking_payment WHERE COALESCE(bp_status, '0') = '0')\n          AS pending_count,\n\n          (SELECT COUNT(*) FROM payment WHERE COALESCE(pay_status, '0') = '1')\n          + (SELECT COUNT(*) FROM booking_payment WHERE COALESCE(bp_status, '0') = '1' AND bp_id <> 770043117)\n          AS paid_count,\n\n          (SELECT COUNT(*)\n           FROM expense e\n           INNER JOIN contract c ON e.ctr_id = c.ctr_id\n           LEFT JOIN payment p ON p.exp_id = e.exp_id\n           WHERE c.ctr_status = '0'\n             AND p.exp_id IS NULL\n             AND DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')\n             AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m'))\n          AS unpaid_count\n    ");
    $paymentStatusResult = $paymentStatusStmt ? $paymentStatusStmt->fetch(PDO::FETCH_ASSOC) : [];
    $paymentStatusBadgeCounts = [
      'unpaid' => (int)($paymentStatusResult['unpaid_count'] ?? 0),
      'pending' => (int)($paymentStatusResult['pending_count'] ?? 0),
      'paid' => (int)($paymentStatusResult['paid_count'] ?? 0),
    ];

    // ดึงจำนวนห้องที่ยังไม่ได้จดมิเตอร์เดือนปัจจุบัน แยกน้ำ/ไฟ
    // ใช้เกณฑ์เดียวกับหน้า manage_utility (ห้องที่มีผู้เช่า/สัญญา active ล่าสุดต่อห้อง)
    $utilityStatusStmt = $pdo->query("\n        SELECT\n          SUM(CASE WHEN u.utl_id IS NULL OR u.utl_water_end IS NULL THEN 1 ELSE 0 END) AS water_count,\n          SUM(CASE WHEN u.utl_id IS NULL OR u.utl_elec_end IS NULL THEN 1 ELSE 0 END) AS electric_count\n        FROM (\n          SELECT c.ctr_id\n          FROM contract c\n          INNER JOIN (\n            SELECT room_id, MAX(ctr_id) AS ctr_id\n            FROM contract\n            WHERE ctr_status = '0'\n            GROUP BY room_id\n          ) lc ON lc.ctr_id = c.ctr_id\n        ) active_ctr\n        LEFT JOIN utility u\n          ON u.ctr_id = active_ctr.ctr_id\n         AND MONTH(u.utl_date) = MONTH(CURDATE())\n         AND YEAR(u.utl_date) = YEAR(CURDATE())\n    ");
    $utilityStatusResult = $utilityStatusStmt ? $utilityStatusStmt->fetch(PDO::FETCH_ASSOC) : [];
    $utilityStatusBadgeCounts = [
      'water' => (int)($utilityStatusResult['water_count'] ?? 0),
      'electric' => (int)($utilityStatusResult['electric_count'] ?? 0),
    ];

    // ดึงจำนวนสถานะงานซ่อมเพื่อแสดงที่เมนูแจ้งซ่อม
    $repairStatusStmt = $pdo->query("\n        SELECT\n          SUM(CASE WHEN COALESCE(repair_status, '0') = '0' THEN 1 ELSE 0 END) AS pending_count,\n          SUM(CASE WHEN COALESCE(repair_status, '0') = '1' THEN 1 ELSE 0 END) AS inprogress_count,\n          SUM(CASE WHEN COALESCE(repair_status, '0') = '2' THEN 1 ELSE 0 END) AS done_count,\n          SUM(CASE WHEN COALESCE(repair_status, '0') = '3' THEN 1 ELSE 0 END) AS cancelled_count\n        FROM repair\n    ");
    $repairStatusResult = $repairStatusStmt ? $repairStatusStmt->fetch(PDO::FETCH_ASSOC) : [];
    $repairStatusBadgeCounts = [
      'pending' => (int)($repairStatusResult['pending_count'] ?? 0),
      'inprogress' => (int)($repairStatusResult['inprogress_count'] ?? 0),
      'done' => (int)($repairStatusResult['done_count'] ?? 0),
      'cancelled' => (int)($repairStatusResult['cancelled_count'] ?? 0),
    ];

    // ดึงจำนวนสถานะการจองเพื่อแสดงที่เมนูการจองห้อง
    $bookingStatusStmt = $pdo->query("\n        SELECT\n          SUM(CASE WHEN COALESCE(b.bkg_status, '0') = '1' AND active_ctr.ctr_id IS NULL THEN 1 ELSE 0 END) AS reserved_count,\n          SUM(CASE WHEN COALESCE(b.bkg_status, '0') = '2' THEN 1 ELSE 0 END) AS checkedin_count,\n          SUM(CASE WHEN COALESCE(b.bkg_status, '0') = '0' THEN 1 ELSE 0 END) AS cancelled_count\n        FROM booking b\n        LEFT JOIN contract active_ctr\n          ON active_ctr.room_id = b.room_id\n         AND active_ctr.ctr_status = '0'\n    ");
    $bookingStatusResult = $bookingStatusStmt ? $bookingStatusStmt->fetch(PDO::FETCH_ASSOC) : [];
    $bookingStatusBadgeCounts = [
      'reserved' => (int)($bookingStatusResult['reserved_count'] ?? 0),
      'checkedin' => (int)($bookingStatusResult['checkedin_count'] ?? 0),
      'cancelled' => (int)($bookingStatusResult['cancelled_count'] ?? 0),
    ];

    // คงไว้เพื่อ compatibility กับโค้ดเดิม
    $pendingPaymentReviewCount = $paymentStatusBadgeCounts['pending'];
    $sidebarDataLoadedFromDb = true;
} catch (Exception $e) {
    // ใช้ค่า default ถ้า database error
}
  }

  $expenseStatusBadgeTotal = array_sum($expenseStatusBadgeCounts);
  $paymentStatusBadgeTotal = array_sum($paymentStatusBadgeCounts);
  $utilityStatusBadgeTotal = array_sum($utilityStatusBadgeCounts);
  $repairStatusBadgeTotal = array_sum($repairStatusBadgeCounts);
  $bookingStatusBadgeTotal = array_sum($bookingStatusBadgeCounts);
  $bookingActionBadgeTotal = (int)$bookingStatusBadgeCounts['reserved'];
  $utilityActionBadgeTotal = (int)$utilityStatusBadgeCounts['water'] + (int)$utilityStatusBadgeCounts['electric'];
  $expenseActionBadgeTotal = (int)$expenseStatusBadgeCounts['unpaid'] + (int)$expenseStatusBadgeCounts['pending'] + (int)$expenseStatusBadgeCounts['partial'];
  $paymentActionBadgeTotal = (int)$paymentStatusBadgeCounts['unpaid'] + (int)$paymentStatusBadgeCounts['pending'];
  $repairActionBadgeTotal = (int)$repairStatusBadgeCounts['pending'] + (int)$repairStatusBadgeCounts['inprogress'];
  $todoBadgeTotal = $wizardIncompleteCount + $bookingActionBadgeTotal + $utilityActionBadgeTotal + $expenseActionBadgeTotal + $paymentActionBadgeTotal + $repairActionBadgeTotal;

if ($sidebarDataLoadedFromDb) {
  $_SESSION[$sidebarCacheKey] = [
    'ts' => time(),
    'admin_id' => $currentAdminId,
    'data' => [
      'siteName' => $siteName,
      'logoFilename' => $logoFilename,
      'themeColor' => $themeColor,
      'fontSize' => $fontSize,
      'adminGoogleLinked' => $adminGoogleLinked,
      'adminGoogleEmail' => $adminGoogleEmail,
      'adminRecoveryEmail' => $adminRecoveryEmail,
      'adminPicture' => $_SESSION['admin_picture'] ?? '',
      'wizardIncompleteCount' => $wizardIncompleteCount,
      'pendingPaymentReviewCount' => $pendingPaymentReviewCount,
      'expenseStatusBadgeCounts' => $expenseStatusBadgeCounts,
      'paymentStatusBadgeCounts' => $paymentStatusBadgeCounts,
      'utilityStatusBadgeCounts' => $utilityStatusBadgeCounts,
      'repairStatusBadgeCounts' => $repairStatusBadgeCounts,
      'bookingStatusBadgeCounts' => $bookingStatusBadgeCounts,
    ],
  ];
}

if (!$sidebarAccountHasOldRecoveryEmail) {
  $sidebarAccountModalRecoveryEmail = $adminRecoveryEmail;
}
?>
<style>
  :root {
    --theme-bg-color: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>;
    --font-scale: <?php echo htmlspecialchars($fontSize, ENT_QUOTES, 'UTF-8'); ?>;
    --admin-font-scale: <?php echo htmlspecialchars($fontSize, ENT_QUOTES, 'UTF-8'); ?>;
  }

  /* Font scaling for admin pages */
  html { 
    font-size: calc(16px * var(--font-scale, 1)); 
  }
  
  body {
    font-size: calc(14px * var(--admin-font-scale, 1));
  }
  
  /* Scale all text elements */
  .app-main, .app-main *, .manage-panel, .manage-panel *,
  .card, .card *, .panel, .panel *, .stat-card, .stat-card *,
  .report-section, .report-section *, table, table * {
    font-size: inherit;
  }
  
  /* พื้นหลังหลัก - ใช้ theme color */
  html, body, .app-shell, .app-main, .reports-page {
    background: var(--theme-bg-color) !important;
  }

  /* Smooth animation when switching theme */
  html, body, .app-shell, .app-main, .reports-page,
  aside.app-sidebar, .manage-panel, .card, .panel, .stat-card,
  .report-section, .report-item, .chart-container, .settings-card,
  input, select, textarea, button {
    transition: background-color 0.35s ease, color 0.35s ease,
                border-color 0.35s ease, box-shadow 0.35s ease,
                font-size 0.2s ease;
  }

  @keyframes themeFadeIn {
    from { opacity: 0; filter: saturate(0.6); }
    to { opacity: 1; filter: saturate(1); }
  }

  body.theme-fade {
    animation: themeFadeIn 0.45s ease;
  }

  /* ===== Live DARK mode (no reload) - เมื่อเลือกสีเข้ม ===== */
  body.live-dark,
  body.live-dark .app-shell,
  body.live-dark .app-main,
  body.live-dark.reports-page,
  body.live-dark .reports-page {
    background: var(--theme-bg-color) !important;
    color: #f1f5f9 !important;
  }
  
  body.live-dark, body.live-dark * {
    color: #f1f5f9 !important;
  }
  
  body.live-dark .settings-card,
  body.live-dark .manage-panel,
  body.live-dark .card,
  body.live-dark .panel {
    background: rgba(255,255,255,0.05) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
  }
  
  body.live-dark input,
  body.live-dark select,
  body.live-dark textarea {
    background: rgba(255,255,255,0.08) !important;
    border: 1px solid rgba(255,255,255,0.15) !important;
    color: #f1f5f9 !important;
  }
  
  /* Exception: Color picker in Live-dark mode */
  body.live-dark .apple-color-option {
    background: unset !important;
  }

  /* ===== Live LIGHT mode (no reload) - เมื่อเลือกสีสว่าง ===== */
  body.live-light,
  body.live-light .app-shell,
  body.live-light .app-main,
  body.live-light.reports-page,
  body.live-light .reports-page {
    background: #ffffff !important;
    color: #111827 !important;
  }

  body.live-light aside.app-sidebar {
    background: #f9fafb !important;
    border-right: 1px solid #e5e7eb !important;
  }

  body.live-light .sidebar-header {
    background: #f9fafb !important;
  }

  body.live-light .sidebar-footer {
    background: #f9fafb !important;
    border-top: 1px solid #e5e7eb !important;
  }

  /* Light mode - User section in footer */
  body.live-light .sidebar-footer .user-row,
  body.live-light .sidebar-footer .user-meta,
  body.live-light .sidebar-footer .user-meta .name,
  body.live-light .sidebar-footer .user-meta .email {
    color: #374151 !important;
  }

  body.live-light .sidebar-footer .avatar svg {
    color: #6b7280 !important;
    background: transparent !important;
  }

  body.live-light .sidebar-footer .avatar svg path {
    fill: #6b7280 !important;
  }

  body.live-light .sidebar-footer .avatar,
  body.live-light .sidebar-footer .avatar img,
  body.live-light .sidebar-footer .avatar svg {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
  }

  /* Light mode - Sidebar navigation text and icons */
  body.live-light .app-nav a,
  body.live-light details summary,
  body.live-light .subitem,
  body.live-light .app-nav-label,
  body.live-light .summary-label,
  body.live-light .team-meta .name {
    color: #374151 !important;
  }

  body.live-light .app-nav a:hover,
  body.live-light details summary:hover {
    background: rgba(0, 0, 0, 0.05) !important;
  }

  body.live-light .app-nav a.active,
  body.live-light .app-nav a.subitem.active {
    background: rgba(59, 130, 246, 0.1) !important;
    border-left: 3px solid #3b82f6 !important;
    color: #2563eb !important;
  }

  body.live-light .app-nav-icon svg {
    stroke: #6b7280 !important;
  }

  body.live-light .app-nav a.active .app-nav-icon svg,
  body.live-light .app-nav a:hover .app-nav-icon svg {
    stroke: #2563eb !important;
  }

  body.live-light .chev {
    color: #6b7280 !important;
  }

  body.live-light .logout-btn {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
  }

  body.live-light .logout-btn:hover {
    background: #f3f4f6 !important;
  }

  body.live-light .logout-btn svg {
    stroke: #374151 !important;
  }

  body.live-light .sidebar-close-btn {
    background: rgba(0, 0, 0, 0.05) !important;
    border: 1px solid rgba(0, 0, 0, 0.1) !important;
  }

  body.live-light .sidebar-close-btn svg {
    stroke: #374151 !important;
  }

  body.live-light .sidebar-nav-area::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.7) !important;
  }

  body.live-light .sidebar-nav-area::-webkit-scrollbar-thumb:hover {
    background: rgba(15, 23, 42, 0.9) !important;
  }

  /* Ensure panel / header icons remain visible in light mode */
  body.live-light .panel-icon svg,
  html.light-theme .panel-icon svg,
  body.light-theme .panel-icon svg,
  .light-theme .panel-icon svg {
    stroke: #111827 !important;
    color: #111827 !important;
    fill: none !important;
  }

  /* Ensure filter buttons (active blue) show white icon/text in light mode */
  body.live-light a.filter-btn[style*="#60a5fa"],
  html.light-theme a.filter-btn[style*="#60a5fa"],
  body.light-theme a.filter-btn[style*="#60a5fa"],
  .light-theme a.filter-btn[style*="#60a5fa"],
  body.live-light .view-toggle-btn.active,
  html.light-theme .view-toggle-btn.active,
  body.light-theme .view-toggle-btn.active,
  .light-theme .view-toggle-btn.active {
    color: #ffffff !important;
  }

  body.live-light a.filter-btn[style*="#60a5fa"] svg,
  html.light-theme a.filter-btn[style*="#60a5fa"] svg,
  body.light-theme a.filter-btn[style*="#60a5fa"] svg,
  .light-theme a.filter-btn[style*="#60a5fa"] svg,
  body.live-light .view-toggle-btn.active svg,
  html.light-theme .view-toggle-btn.active svg,
  body.light-theme .view-toggle-btn.active svg,
  .light-theme .view-toggle-btn.active svg {
    stroke: #ffffff !important;
    color: #ffffff !important;
    fill: none !important;
  }

  /* More robust: force SVG children to follow currentColor (so explicit strokes are overridden) */
  a.filter-btn svg *,
  .view-toggle-btn.active svg *,
  .filter-btn svg * {
    stroke: currentColor !important;
    fill: currentColor !important;
  }

  /* If inline style sets color to #fff, ensure entire button (including numeric count) is white */
  a.filter-btn[style*="color:#fff"],
  a.filter-btn[style*="color: #fff"],
  .view-toggle-btn.active {
    color: #ffffff !important;
  }

  body.live-light, body.live-light * {
    color: #111827 !important;
  }

  /* Exception: Active nav items should have blue text */
  body.live-light .app-nav a.active,
  body.live-light .app-nav a.active * {
    color: #2563eb !important;
  }

  /* Exception: Logout button icon */
  body.live-light .logout-btn .app-nav-icon,
  body.live-light .logout-btn .app-nav-label {
    color: #374151 !important;
  }

  body.live-light .settings-card,
  body.live-light .manage-panel,
  body.live-light .card,
  body.live-light .panel,
  body.live-light .stat-card,
  body.live-light .report-section,
  body.live-light .report-item,
  body.live-light .chart-container,
  body.live-light .booking-stat-card,
  body.live-light .tenant-stat-card,
  body.live-light .expense-stat-card,
  body.live-light .news-stat-card,
  body.live-light .contract-stat-card,
  body.live-light .dashboard-grid .stat-card,
  body.live-light .report-grid .report-item,
  body.live-light .charts-row .chart-container {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
  }

  body.live-light input,
  body.live-light select,
  body.live-light textarea,
  body.live-light .form-control {
    background: #ffffff !important;
    color: #111827 !important;
    border: 1px solid #e5e7eb !important;
  }
  
  /* Exception: Color picker in Live-light mode */
  body.live-light .apple-color-option,
  .apple-color-option {
    background: unset !important;
  }

  /* User icon always white */
  .sidebar-footer .avatar svg *,
  .sidebar-footer .rail-user svg *,
  .sidebar-footer .rail-logout .app-nav-icon,
  .sidebar-footer .user-row .app-nav-icon {
    color: #ffffff !important;
    fill: currentColor !important;
  }

  /* Uniform icon sizing for nav, footer, and rails */
  .app-nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.8rem;
    height: 1.8rem;
    font-size: 1.1rem;
    line-height: 1;
    flex-shrink: 0;
    text-align: center;
  }
  
  /* SVG icons inside app-nav-icon */
  .app-nav-icon svg {
    width: 1.2rem;
    height: 1.2rem;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
    flex-shrink: 0;
  }
  
  /* Hover animation for SVG icons */
  .app-nav a:hover .app-nav-icon svg,
  details summary:hover .app-nav-icon svg {
    transform: scale(1.15);
    transition: transform 0.2s ease;
  }

  /* Animated utility icon (water/electric switch) */
  .app-nav-icon.utility-icon-toggle {
    position: relative;
    overflow: hidden;
  }
  .app-nav-icon.utility-icon-toggle .utility-icon {
    position: absolute;
    inset: 0;
    margin: auto;
    width: 1.2rem;
    height: 1.2rem;
    transform-origin: center;
    animation-duration: 2.4s;
    animation-iteration-count: infinite;
    animation-timing-function: ease-in-out;
  }
  .app-nav-icon.utility-icon-toggle .utility-icon.water {
    color: #38bdf8;
    animation-name: utilityWaterSwap;
  }
  .app-nav-icon.utility-icon-toggle .utility-icon.electric {
    color: #f59e0b;
    animation-name: utilityElecSwap;
  }

  @keyframes utilityWaterSwap {
    0%, 44% { opacity: 1; transform: scale(1) rotate(0deg); }
    50%, 100% { opacity: 0; transform: scale(0.88) rotate(-8deg); }
  }
  @keyframes utilityElecSwap {
    0%, 44% { opacity: 0; transform: scale(0.88) rotate(8deg); }
    50%, 94% { opacity: 1; transform: scale(1) rotate(0deg); }
    100% { opacity: 0; transform: scale(0.88) rotate(8deg); }
  }

  @media (prefers-reduced-motion: reduce) {
    .app-nav-icon.utility-icon-toggle .utility-icon {
      animation: none !important;
    }
    .app-nav-icon.utility-icon-toggle .utility-icon.electric {
      opacity: 0;
    }
  }
  
  /* Dashboard and Manage summary styling */
  #nav-dashboard > summary,
  #nav-management > summary {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.6rem 0.85rem;
    margin: 0.1rem 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    transition: all 0.3s ease;
    list-style: none;
  }
  
  #nav-dashboard > summary .summary-link,
  #nav-management > summary .summary-link {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
  }

  /* Reserve space for todo badge + chevron so they never overlap label */
  #nav-todo > summary .summary-link {
    padding-right: 5rem;
  }

  /* Place todo total badge just left of chevron */
  #nav-todo > summary .todo-total-badge {
    position: absolute;
    right: 2.8rem;
    top: 50%;
    transform: translateY(-50%);
    line-height: 1;
    z-index: 1;
  }

  /* Collapsed rail: render Todo as centered icon pill with corner badge */
  @media (min-width: 1025px) {
    aside.sidebar-collapsed #nav-todo > summary,
    .app-sidebar.collapsed #nav-todo > summary {
      position: relative !important;
      padding: 0.35rem 0 !important;
      min-height: 3rem !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
    }

    aside.sidebar-collapsed #nav-todo > summary .summary-link,
    .app-sidebar.collapsed #nav-todo > summary .summary-link {
      width: 2.45rem !important;
      height: 2.45rem !important;
      min-width: 2.45rem !important;
      border-radius: 0.75rem !important;
      padding: 0 !important;
      margin: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      background: rgba(148, 163, 184, 0.14) !important;
      border: 1px solid rgba(148, 163, 184, 0.32) !important;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03) !important;
    }

    aside.sidebar-collapsed #nav-todo > summary .summary-link.active,
    .app-sidebar.collapsed #nav-todo > summary .summary-link.active {
      background: rgba(59, 130, 246, 0.2) !important;
      border-color: rgba(59, 130, 246, 0.45) !important;
    }

    aside.sidebar-collapsed #nav-todo > summary .todo-total-badge,
    .app-sidebar.collapsed #nav-todo > summary .todo-total-badge {
      right: 0.38rem !important;
      top: 0.12rem !important;
      transform: none !important;
      min-width: 18px !important;
      height: 18px !important;
      padding: 0 5px !important;
      font-size: 10px !important;
      border: 1px solid rgba(255, 255, 255, 0.72) !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25) !important;
      z-index: 3 !important;
    }

    aside.sidebar-collapsed #nav-todo .wizard-nav-item,
    .app-sidebar.collapsed #nav-todo .wizard-nav-item {
      width: 2.45rem !important;
      height: 2.45rem !important;
      min-width: 2.45rem !important;
      padding: 0 !important;
      margin: 0.2rem auto !important;
      border-left: 0 !important;
      border-radius: 0.75rem !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      position: relative !important;
      background: rgba(148, 163, 184, 0.14) !important;
      border: 1px solid rgba(148, 163, 184, 0.32) !important;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03) !important;
      overflow: visible !important;
    }

    aside.sidebar-collapsed #nav-todo .wizard-nav-item.active,
    .app-sidebar.collapsed #nav-todo .wizard-nav-item.active {
      background: rgba(59, 130, 246, 0.2) !important;
      border-color: rgba(59, 130, 246, 0.45) !important;
    }

    aside.sidebar-collapsed #nav-todo .wizard-nav-item .app-nav-label,
    .app-sidebar.collapsed #nav-todo .wizard-nav-item .app-nav-label {
      display: none !important;
    }

    aside.sidebar-collapsed #nav-todo .wizard-nav-item .app-nav-icon,
    .app-sidebar.collapsed #nav-todo .wizard-nav-item .app-nav-icon {
      margin: 0 !important;
      width: 2rem !important;
      height: 2rem !important;
      min-width: 2rem !important;
      min-height: 2rem !important;
    }

    aside.sidebar-collapsed #nav-todo .wizard-nav-item > span[data-bs-toggle="tooltip"],
    .app-sidebar.collapsed #nav-todo .wizard-nav-item > span[data-bs-toggle="tooltip"] {
      top: 0.12rem !important;
      right: 0.2rem !important;
      transform: none !important;
      min-width: 18px !important;
      height: 18px !important;
      padding: 0 5px !important;
      font-size: 10px !important;
      border: 1px solid rgba(255, 255, 255, 0.72) !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25) !important;
      z-index: 3 !important;
    }
  }
  
  #nav-dashboard > summary .summary-link:hover,
  #nav-management > summary .summary-link:hover {
    opacity: 0.8;
  }
  
  /* Dashboard and Manage icons - ensure consistent sizing */
  #nav-dashboard .summary-link .app-nav-icon,
  #nav-management .summary-link .app-nav-icon {
    width: 1.8rem;
    height: 1.8rem;
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin: 0;
  }
  
  /* Add margin only when sidebar is NOT collapsed */
  aside.app-sidebar:not(.collapsed) .app-nav-icon {
    margin-right: 0.4rem;
  }

  /* Ensure icons stay square in collapsed rail just like user/logout */
  aside.sidebar-collapsed .app-nav-icon,
  .app-sidebar.collapsed .app-nav-icon {
    width: 2.25rem !important;
    height: 2.25rem !important;
    min-width: 2.25rem !important;
    min-height: 2.25rem !important;
    font-size: 1.1rem !important;
    margin: 0 !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
  }

  body.live-light .status-badge.time-fresh { background:#d1fae5 !important; color:#065f46 !important; }
  body.live-light .status-badge.time-warning { background:#fef3c7 !important; color:#92400e !important; }
  body.live-light .status-badge.time-danger { background:#fee2e2 !important; color:#b91c1c !important; }
  body.live-light .status-badge.time-neutral { background:#e5e7eb !important; color:#111827 !important; }

  /* Soft fade animation (no overlay) */
  @keyframes themeSoftFade {
    from { opacity: 0.6; filter: blur(1px) saturate(0.8); }
    to { opacity: 1; filter: blur(0) saturate(1); }
  }

  body.theme-softfade {
    animation: themeSoftFade 0.45s ease;
  }
  
  /* Sidebar - ใช้ theme color */
  aside.app-sidebar {
    background: var(--theme-bg-color) !important;
    scrollbar-width: none;
    -ms-overflow-style: none;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                min-width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                max-width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  aside.app-sidebar::-webkit-scrollbar { display: none; }
  
  /* ปรับสีตัวหนังสือตามความสว่างของพื้นหลัง */
  <?php
  // คำนวณความสว่างของสี
  $hex = ltrim($themeColor, '#');
  if (strlen($hex) === 6) {
      $r = hexdec(substr($hex, 0, 2));
      $g = hexdec(substr($hex, 2, 2));
      $b = hexdec(substr($hex, 4, 2));
      $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      $isLight = $brightness > 155;
  } else {
      $isLight = false;
  }
  
  if ($isLight): ?>
  /* ===== LIGHT MODE - พื้นหลังสว่าง ===== */
  
  /* พื้นหลังทั้งหมดเป็นสีขาว */
  html, body {
    background: #ffffff !important;
  }
  
  .app-shell,
  .app-main,
  .reports-page,
  body.reports-page {
    background: #ffffff !important;
  }
  
  /* Sidebar สีขาว */
  aside.app-sidebar {
    background: #f9fafb !important;
    border-right: 1px solid #e5e7eb !important;
  }
  
  .sidebar-header {
    background: #f9fafb !important;
  }

  /* Sidebar Close Button - Mobile */
  .sidebar-close-btn {
    display: none;
  }
  @media (max-width: 1024px) {
    .sidebar-close-btn {
      display: flex;
      position: absolute;
      top: 12px;
      right: 12px;
      z-index: 1001;
      width: 36px;
      height: 36px;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.1);
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .sidebar-close-btn:hover {
      background: rgba(0, 0, 0, 0.1);
    }
    .sidebar-close-btn svg {
      stroke: #374151;
    }
  }
  
  .sidebar-footer {
    background: #f9fafb !important;
    border-top: 1px solid #e5e7eb !important;
  }
  
  /* Sidebar Rail - hidden by default, shown when collapsed */
  .sidebar-rail {
    display: none;
  }
  
  /* Desktop: Show rail when sidebar is collapsed */
  @media (min-width: 1025px) {
    aside.app-sidebar.collapsed .sidebar-rail,
    aside.sidebar-collapsed .sidebar-rail {
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      gap: 0.5rem !important;
      padding: 0.5rem 0 !important;
    }
    
    aside.app-sidebar.collapsed .user-row,
    aside.app-sidebar.collapsed .logout-btn,
    aside.sidebar-collapsed .user-row,
    aside.sidebar-collapsed .logout-btn {
      display: none !important;
    }

    aside.app-sidebar.collapsed .google-link-btn,
    aside.sidebar-collapsed .google-link-btn {
      display: block !important;
      width: 40px !important;
      height: 40px !important;
      min-width: 40px !important;
      max-width: 40px !important;
      padding: 0 !important;
      gap: 0 !important;
      margin: 0 auto !important;
      border-radius: 10px !important;
      box-sizing: border-box !important;
      line-height: 40px !important;
      text-align: center !important;
      flex: 0 0 40px !important;
    }

    aside.app-sidebar.collapsed .google-link-btn .app-nav-label,
    aside.sidebar-collapsed .google-link-btn .app-nav-label {
      display: none !important;
    }

    aside.app-sidebar.collapsed .google-link-btn .google-icon,
    aside.sidebar-collapsed .google-link-btn .google-icon {
      margin: 0 !important;
      display: inline-block !important;
      vertical-align: middle !important;
      width: 16px !important;
      height: 16px !important;
      flex: 0 0 16px !important;
    }
    
    .sidebar-rail .rail-user,
    .sidebar-rail .rail-logout {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 48px;
      height: 48px;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s ease;
    }
    
    .sidebar-rail .rail-user:hover,
    .sidebar-rail .rail-logout button:hover {
      background: rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-rail .rail-logout button {
      background: transparent;
      border: none;
      padding: 0;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 48px;
      height: 48px;
      border-radius: 8px;
      transition: background 0.2s ease;
    }
    
    .sidebar-rail .app-nav-icon svg {
      width: 20px;
      height: 20px;
    }
  }
  
  /* Sidebar Navigation - Light Mode */
  .app-nav a,
  details summary,
  .subitem,
  .app-nav-label,
  .summary-label {
    position: relative;
    color: #374151 !important;
  }

  .app-nav a:hover,
  details summary:hover {
    background: rgba(0, 0, 0, 0.05) !important;
  }

  .app-nav a.active,
  .app-nav a.subitem.active {
    background: rgba(59, 130, 246, 0.1) !important;
    border-left: 3px solid #3b82f6 !important;
    color: #2563eb !important;
  }

  .app-nav-icon svg {
    stroke: #6b7280 !important;
  }

  .app-nav a.active .app-nav-icon svg,
  .app-nav a:hover .app-nav-icon svg {
    stroke: #2563eb !important;
  }

  .chev {
    color: #6b7280 !important;
  }

  .logout-btn {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
  }

  .logout-btn:hover {
    background: #f3f4f6 !important;
  }

  .logout-btn svg {
    stroke: #374151 !important;
  }
  
  /* Google Link Button - Light Mode */
  .google-link-btn {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
  }
  
  .google-link-btn:hover {
    background: #f3f4f6 !important;
  }
  
  .google-link-btn.linked {
    border-color: #34A853 !important;
  }

  .sidebar-nav-area::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.7) !important;
  }

  .sidebar-nav-area::-webkit-scrollbar-thumb:hover {
    background: rgba(15, 23, 42, 0.9) !important;
  }

  .expense-nav-item {
    position: relative;
    padding-right: 0.65rem !important;
  }

  .expense-nav-item,
  .payment-nav-item,
  .utility-nav-item,
  .booking-nav-item,
  .repair-nav-item {
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
  }

  .expense-nav-item .app-nav-label,
  .payment-nav-item .app-nav-label,
  .utility-nav-item .app-nav-label,
  .booking-nav-item .app-nav-label,
  .repair-nav-item .app-nav-label {
    flex: 1 !important;
    min-width: 0 !important;
  }

  .todo-action-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 700;
    line-height: 1;
    color: #ffffff !important;
    background: #f59e0b;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .expense-status-badges {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: nowrap;
  }

  .expense-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 700;
    line-height: 1;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .expense-status-badge.unpaid { background: #ef4444; }
  .expense-status-badge.pending { background: #f97316; }
  .expense-status-badge.partial { background: #f59e0b; }
  .expense-status-badge.paid { background: #22c55e; }

  .payment-nav-item {
    position: relative;
    padding-right: 0.65rem !important;
  }

  .payment-status-badges {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: nowrap;
  }

  .payment-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 700;
    line-height: 1;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .payment-status-badge.unpaid { background: #ef4444; }
  .payment-status-badge.pending { background: #f59e0b; }
  .payment-status-badge.paid { background: #22c55e; }

  .utility-nav-item {
    position: relative;
    padding-right: 0.65rem !important;
  }

  .utility-status-badges {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: nowrap;
  }

  .utility-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 700;
    line-height: 1;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .utility-status-badge.water { background: #0ea5e9; }
  .utility-status-badge.electric { background: #f59e0b; }

  .booking-nav-item {
    position: relative;
    padding-right: 0.65rem !important;
  }

  .booking-status-badges {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: nowrap;
  }

  .booking-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 700;
    line-height: 1;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .booking-status-badge.reserved { background: #f59e0b; }
  .booking-status-badge.checkedin { background: #22c55e; }
  .booking-status-badge.cancelled { background: #ef4444; }

  .repair-nav-item {
    position: relative;
    padding-right: 0.65rem !important;
  }

  .repair-status-badges {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: nowrap;
  }

  .repair-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 700;
    line-height: 1;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .repair-status-badge.pending { background: #f97316; }
  .repair-status-badge.inprogress { background: #60a5fa; }
  .repair-status-badge.done { background: #22c55e; }
  .repair-status-badge.cancelled { background: #ef4444; }

  .expense-status-badges,
  .payment-status-badges,
  .utility-status-badges,
  .booking-status-badges,
  .repair-status-badges {
    margin-left: auto !important;
    flex-shrink: 0 !important;
  }

  aside.sidebar-collapsed .expense-status-badges,
  .app-sidebar.collapsed .expense-status-badges,
  aside.sidebar-collapsed .payment-status-badges,
  .app-sidebar.collapsed .payment-status-badges,
  aside.sidebar-collapsed .booking-status-badges,
  .app-sidebar.collapsed .booking-status-badges,
  aside.sidebar-collapsed .utility-status-badges,
  .app-sidebar.collapsed .utility-status-badges,
  aside.sidebar-collapsed .repair-status-badges,
  .app-sidebar.collapsed .repair-status-badges {
    display: none !important;
  }

  /* User section in footer - Light Mode */
  .sidebar-footer .user-row,
  .sidebar-footer .user-meta,
  .sidebar-footer .user-meta .name,
  .sidebar-footer .user-meta .email {
    color: #374151 !important;
  }

  .sidebar-footer .avatar svg {
    color: #6b7280 !important;
    background: transparent !important;
  }

  .sidebar-footer .avatar svg path {
    fill: #6b7280 !important;
  }

  .sidebar-footer .avatar,
  .sidebar-footer .avatar img,
  .sidebar-footer .avatar svg {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
  }
  
  /* ตัวหนังสือทั้งหมดเป็นสีดำ */
  body, body *,
  .app-main, .app-main *,
  .reports-page, .reports-page *,
  h1, h2, h3, h4, h5, h6, p, span, div, label, a,
  .section-header h1,
  .settings-card h3,
  .settings-card label,
  .settings-card small,
  aside.app-sidebar,
  aside.app-sidebar *,
  nav a, .sidebar-nav a, details summary,
  .team-meta .name, .team-meta .role,
  .manage-panel *,
  .settings-card * {
    color: #111827 !important;
  }
  
  /* ===== Exception: Color picker options - ไม่ force สี ===== */
  .apple-color-option,
  .apple-color-grid .apple-color-option {
    background: inherit !important;
  }
  
  /* Cards และ Panels สีขาว/เทาอ่อน */
  .settings-card,
  .manage-panel,
  .card,
  .panel {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  }
  
  /* Stat Cards สีขาว */
  .booking-stat-card,
  .tenant-stat-card,
  .expense-stat-card,
  .news-stat-card,
  .contract-stat-card,
  .stat-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  }
  
  .booking-stat-card *,
  .tenant-stat-card *,
  .expense-stat-card *,
  .news-stat-card *,
  .contract-stat-card *,
  .stat-card * {
    color: #111827 !important;
  }
  
  /* Form Elements สีขาว */
  input[type="text"],
  input[type="email"],
  input[type="number"],
  input[type="date"],
  input[type="password"],
  input[type="file"],
  input[type="color"],
  select,
  textarea {
    background: #ffffff !important;
    border: 1px solid #d1d5db !important;
    color: #111827 !important;
  }
  
  input::placeholder,
  textarea::placeholder {
    color: #9ca3af !important;
  }
  
  /* Buttons */
  .quick-color,
  button:not(.btn-save):not([type="submit"]):not(.expenses-view-toggle):not(.payments-view-toggle):not(.meter-tab):not(.report-tab):not(.action-btn) {
    border: 1px solid #d1d5db !important;
  }
  
  /* Sidebar Navigation */
  nav a, 
  .sidebar-nav a,
  details summary {
    color: #374151 !important;
  }
  
  nav a:hover,
  .sidebar-nav a:hover,
  details summary:hover {
    background: #f3f4f6 !important;
    color: #111827 !important;
  }
  
  nav a.active,
  .sidebar-nav a.active {
    background: #dbeafe !important;
    color: #1e40af !important;
  }
  
  /* Tables */
  table {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  table thead {
    background: #f9fafb !important;
  }
  
  table thead th {
    color: #111827 !important;
    border-bottom: 1px solid #e5e7eb !important;
  }
  
  table tbody tr {
    background: #ffffff !important;
    border-bottom: 1px solid #f3f4f6 !important;
  }
  
  table tbody tr:hover {
    background: #f9fafb !important;
  }
  
  table tbody td {
    color: #111827 !important;
  }
  
  /* Header */
  header {
    background: #ffffff !important;
    border-bottom: 1px solid #e5e7eb !important;
  }
  
  header h2,
  header * {
    color: #111827 !important;
  }
  
  #sidebar-toggle {
    color: #111827 !important;
  }
  
  #sidebar-toggle svg {
    stroke: #111827 !important;
  }
  
  /* Modals */
  .modal-content,
  .booking-modal-content,
  .confirm-modal {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #111827 !important;
  }
  
  /* Logo Preview */
  .logo-preview,
  .preview-area {
    background: #f9fafb !important;
    border: 1px solid #e5e7eb !important;
  }
  
  /* Team Avatar Section - User Icon */
  .team-switcher,
  aside.app-sidebar .team-switcher {
    background: #ffffff !important;
    border-bottom: 1px solid #e5e7eb !important;
  }
  
  /* Simple avatar without border */
  .team-avatar,
  aside.app-sidebar .team-avatar,
  .app-sidebar .team-avatar {
    position: relative;
    width: 120px !important;
    height: 120px !important;
    border-radius: 12px !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 auto !important;
  }
  
  .team-avatar-img,
  aside.app-sidebar .team-avatar-img,
  .app-sidebar .team-avatar-img {
    width: 120px !important;
    height: 120px !important;
    border-radius: 12px !important;
    background: #ffffff !important;
    border: 1px solid rgba(0,0,0,0.08) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    object-fit: cover !important;
  }
  
  .team-meta .name,
  .team-meta .role {
    color: #111827 !important;
  }
  
  .team-info {
    background: #ffffff !important;
  }
  
  /* ปุ่มออกจากระบบ */
  .logout-btn,
  aside.app-sidebar .logout-btn,
  .app-sidebar .logout-btn {
    background: #ffffff !important;
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #111827 !important;
  }
  
  .logout-btn:hover,
  aside.app-sidebar .logout-btn:hover {
    background: #f3f4f6 !important;
    background-color: #f3f4f6 !important;
    color: #111827 !important;
  }
  
  .logout-btn .app-nav-icon,
  .logout-btn .app-nav-label,
  aside.app-sidebar .logout-btn .app-nav-icon,
  aside.app-sidebar .logout-btn .app-nav-label {
    color: #111827 !important;
  }
  
  /* Google Link Button - Light Mode Override */
  .google-link-btn,
  aside.app-sidebar .google-link-btn,
  .app-sidebar .google-link-btn {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
  }
  
  .google-link-btn:hover,
  aside.app-sidebar .google-link-btn:hover {
    background: #f3f4f6 !important;
  }
  
  /* Google Linked Info - Light Mode */
  .google-linked-info {
    background: rgba(52, 168, 83, 0.08) !important;
    border: 1px solid rgba(52, 168, 83, 0.4) !important;
    color: #374151 !important;
  }
  
  .google-linked-info .google-email {
    color: #374151 !important;
  }
  
  .google-unlink-btn {
    background: rgba(239, 68, 68, 0.1) !important;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
    color: #dc2626 !important;
  }
  
  .google-unlink-btn:hover {
    background: rgba(239, 68, 68, 0.2) !important;
    border-color: rgba(239, 68, 68, 0.6) !important;
    color: #b91c1c !important;
  }
  
  /* Apple-style Alert/Confirm Dialog - Light Mode */
  body.live-light .apple-alert-dialog {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(0, 0, 0, 0.1);
  }
  
  body.live-light .apple-alert-title {
    color: #1f2937;
  }
  
  body.live-light .apple-alert-message {
    color: #6b7280;
  }
  
  body.live-light .apple-alert-buttons {
    border-top: 1px solid rgba(0, 0, 0, 0.1);
  }
  
  body.live-light .apple-alert-button:not(:last-child) {
    border-right: 1px solid rgba(0, 0, 0, 0.1);
  }
  
  body.live-light .apple-alert-button:hover {
    background: rgba(0, 0, 0, 0.05);
  }
  
  body.live-light .apple-alert-button:active {
    background: rgba(0, 0, 0, 0.1);
  }

  /* Dashboard Cards */
  .dashboard-grid .card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  /* Color Preview */
  .color-preview {
    color: #111827 !important;
    border: 1px solid #d1d5db !important;
  }
  
  /* Expense Stats Cards */
  .expense-stats,
  .expense-stat-card,
  .booking-stats,
  .room-stats {
    background: #ffffff !important;
  }
  
  .expense-stat-card,
  .booking-stat-card,
  .room-stat-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  .expense-stat-card *,
  .booking-stat-card *,
  .room-stat-card * {
    color: #111827 !important;
  }
  
  /* Available Rooms Grid */
  .available-rooms-grid,
  .rooms-grid {
    background: transparent !important;
  }
  
  .room-card,
  .available-room-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #111827 !important;
  }
  
  .room-card *,
  .available-room-card * {
    color: #111827 !important;
  }
  
  /* Room Stats */
  .room-stats .stat-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  .room-stats .stat-value,
  .room-stats h3,
  .room-stats p {
    color: #111827 !important;
  }
  
  /* Dashboard Cards - เฉพาะเจาะจง */
  body.reports-page .dashboard-grid,
  body.reports-page .dashboard-grid > div,
  body.reports-page .dashboard-grid .card,
  body.reports-page .chart-container,
  body.reports-page .stat-overview,
  body.reports-page .overview-card,
  body.reports-page section,
  body.reports-page section > div {
    background: #ffffff !important;
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  body.reports-page .dashboard-grid *,
  body.reports-page .chart-container *,
  body.reports-page .stat-overview *,
  body.reports-page .overview-card *,
  body.reports-page section *,
  body.reports-page section h2,
  body.reports-page section h3,
  body.reports-page section h4,
  body.reports-page section p,
  body.reports-page section span {
    /* color removed per request */
  }
  
  /* Chart Cards */
  body.reports-page .chart-card,
  body.reports-page canvas {
    background: #ffffff !important;
    background-color: #ffffff !important;
  }
  
  /* Override gradient backgrounds */
  body.reports-page [style*="background: linear-gradient"],
  body.reports-page [style*="background:linear-gradient"],
  body.reports-page div[style*="background"],
  body.reports-page section[style*="background"] {
    background: #ffffff !important;
    background-color: #ffffff !important;
  }
  
  <?php endif; ?>
  
  <?php if ($isLight): ?>
  <script>
  // Force override inline styles for Light Mode
  document.addEventListener('DOMContentLoaded', function() {
    const allElements = document.querySelectorAll('[style*="background"], [style*="linear-gradient"]');
    allElements.forEach(el => {
      const style = el.getAttribute('style');
      if (style && (style.includes('background') || style.includes('linear-gradient'))) {
        el.style.setProperty('background', '#ffffff', 'important');
        el.style.setProperty('background-color', '#ffffff', 'important');
        el.style.setProperty('color', '#111827', 'important');
      }
    });
  });
  </script>
  <?php endif; ?>
  details summary {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.35rem;
    padding: 0.6rem 2.5rem 0.6rem 0.85rem !important;
    margin: 0 !important;
    transition: all 0.3s ease;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    position: relative;
    width: 100%;
    min-height: 2.25rem;
  }
  
  /* ลิงก์ภายใน summary */
  details summary .summary-link {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.35rem;
    text-decoration: none;
    color: inherit;
    padding: 0;
    margin: 0;
    flex: 1;
  }
  
  details summary .summary-link:hover {
    text-decoration: none;
    opacity: 0.8;
  }
  
  /* ข้อความธรรมดาใน summary */
  details summary .summary-text {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.35rem;
  }
  
  details summary .app-nav-icon {
    width: 1.8rem;
    height: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
  }
  
  /* Override for dashboard and management icons - force perfect centering (only when sidebar is open) */
  aside.app-sidebar:not(.collapsed) details summary .app-nav-icon {
    width: 1.8rem !important;
    height: 1.8rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
    padding: 0 !important;
    margin: 0 !important;
    line-height: 1 !important;
    border-radius: 12px !important;
    background: transparent !important;
    font-size: 1.1rem !important;
    flex-shrink: 0 !important;
  }
  details summary .chev {
    cursor: pointer;
    padding: 0.5rem 0.65rem;
    position: absolute;
    right: 0.35rem;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    min-height: 2rem;
  }
  
  details summary .summary-label {
    transition: opacity 0.3s ease, transform 0.3s ease;
    font-size: 0.95rem;
    white-space: nowrap;
    flex: 1;
  }
  details[open] summary .chev {
    transform: rotate(90deg);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s ease;
  }
  
  summary .chev {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s ease;
  }
  
  /* Hide all dropdown items by default - completely invisible */
  details > a {
    display: none !important;
    opacity: 0;
    pointer-events: none;
  }
  
  /* Show dropdown items only when details[open] */
  details[open] > a {
    display: block !important;
    opacity: 1;
    pointer-events: auto;
    animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    animation-fill-mode: both;
  }
  
  /* Staggered animations for each dropdown item */
  details[open] > a:nth-child(2) {
    animation-delay: 0.05s;
  }
  
  details[open] > a:nth-child(3) {
    animation-delay: 0.1s;
  }
  
  details[open] > a:nth-child(4) {
    animation-delay: 0.15s;
  }
  
  details[open] > a:nth-child(5) {
    animation-delay: 0.2s;
  }
  
  details[open] > a:nth-child(6) {
    animation-delay: 0.25s;
  }
  
  details[open] > a:nth-child(7) {
    animation-delay: 0.3s;
  }
  
  details[open] > a:nth-child(8) {
    animation-delay: 0.35s;
  }
  
  details[open] > a:nth-child(9) {
    animation-delay: 0.4s;
  }
  
  details[open] > a:nth-child(10) {
    animation-delay: 0.45s;
  }
  
  details[open] > a:nth-child(11) {
    animation-delay: 0.5s;
  }
  
  details[open] > a:nth-child(12) {
    animation-delay: 0.55s;
  }
  
  /* Animation keyframes for smooth slide-in */
  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  /* Animation keyframes for closing */
  @keyframes slideUp {
    from {
      opacity: 1;
      transform: translateY(0);
    }
    to {
      opacity: 0;
      transform: translateY(-8px);
    }
  }

  /* Sidebar Close Button - Dark mode default */
  .sidebar-close-btn {
    display: none;
  }
  @media (max-width: 1024px) {
    .sidebar-close-btn {
      display: flex;
      position: absolute;
      top: 12px;
      right: 12px;
      z-index: 1001;
      width: 36px;
      height: 36px;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .sidebar-close-btn:hover {
      background: rgba(255, 255, 255, 0.2);
    }
    .sidebar-close-btn svg {
      stroke: #e2e8f0;
    }
  }

  /* Closing animation - hide items immediately */
  details:not([open]) > a {
    display: none !important;
  }
  .team-switcher {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem !important;
    gap: 0.75rem;
    transition: all 0.3s ease;
  }
  .team-avatar {
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    overflow: hidden;
    margin: 0 auto !important;
    border-radius: 16px;
    background: transparent;
    box-shadow: none;
    transition: width 0.3s ease, height 0.3s ease, opacity 0.3s ease;
  }
  .team-avatar-img {
    width: 120px !important;
    height: 120px !important;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.12);
    object-fit: cover;
    background: #ffffff;
    border: 1px solid rgba(255,255,255,0.6);
    transition: all 0.3s ease;
  } 
  .team-meta {
    display: block;
    text-align: center;
    width: 100%;
    padding: 0.75rem 0.5rem 0 0.5rem;
    transition: opacity 0.3s ease, transform 0.3s ease;
  }
  .team-meta .name {
    font-size: 1rem;
    font-weight: 700;
    color: #e2e8f0;
    line-height: 1.4;
    margin-bottom: 0.25rem;
    transition: all 0.3s ease;
  }
  .subitem {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.6rem 0.85rem;
    margin: 0.1rem 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    transition: all 0.3s ease;
  }
  /* Ensure dark-mode nav text is visible */
  .app-nav a,
  details summary,
  .subitem {
    color: #e2e8f0;
  }
  /* Tighten nav vertical spacing */
  aside.app-sidebar {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.25rem;
    height: 100vh;
    overflow: hidden;
  }
  /* Header: Logo & Name - Fixed at top */
  .app-sidebar .sidebar-header {
    flex-shrink: 0;
    padding: 0.5rem;
    text-align: center;
  }
  /* Navigation area should scroll if content is too long */
  .app-sidebar .sidebar-nav-area {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    min-height: 0; /* Important for flex scroll */
  }
  /* Scrollbar styling for nav area */
  .app-sidebar .sidebar-nav-area::-webkit-scrollbar {
    width: 4px;
  }
  .app-sidebar .sidebar-nav-area::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.2);
  }
  .app-sidebar .sidebar-nav-area::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.7);
    border-radius: 4px;
  }
  .app-sidebar .sidebar-nav-area::-webkit-scrollbar-thumb:hover {
    background: rgba(15, 23, 42, 0.9);
  }
  /* Footer stays at bottom, never scrolls */
  .sidebar-footer {
    flex-shrink: 0;
    background: var(--theme-bg-color, #1e293b);
    padding: 0.75rem 0.5rem;
    border-top: 1px solid rgba(255,255,255,0.1);
  }
  
  /* Google Link Button - Base Styles */
  .google-link-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 12px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: rgba(255,255,255,0.85);
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
  }
  
  .google-link-btn:hover {
    background: rgba(255,255,255,0.12);
    border-color: rgba(255,255,255,0.2);
  }
  
  .google-link-btn .google-icon {
    flex-shrink: 0;
  }
  
  .google-link-btn .app-nav-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  /* Google Linked Info Section */
  .google-linked-info {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    padding: 6px 10px;
    background: rgba(52, 168, 83, 0.08);
    border: 1px solid rgba(52, 168, 83, 0.3);
    border-radius: 6px;
    font-size: 11px;
    color: rgba(255,255,255,0.75);
  }
  
  .google-linked-info .google-icon {
    flex-shrink: 0;
  }
  
  .google-linked-info .google-email {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
  }
  
  .google-unlink-btn {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 3px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 4px;
    color: rgba(239, 68, 68, 0.9);
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
  }
  
  .google-unlink-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.5);
    color: rgba(239, 68, 68, 1);
  }
  
  /* Apple-style Alert/Confirm Dialog - Dark Mode */
  .apple-alert-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
    opacity: 0;
    animation: fadeIn 0.2s ease forwards;
  }
  
  @keyframes fadeIn {
    to { opacity: 1; }
  }
  
  @keyframes fadeOut {
    to { opacity: 0; }
  }
  
  @keyframes scaleIn {
    from {
      transform: scale(1.1);
      opacity: 0;
    }
    to {
      transform: scale(1);
      opacity: 1;
    }
  }
  
  .apple-alert-dialog {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.12);
    border-radius: 14px;
    width: 90%;
    max-width: 320px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    animation: scaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  
  .apple-alert-content {
    padding: 24px 20px;
    text-align: center;
  }
  
  .apple-alert-title {
    font-size: 17px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 8px;
    line-height: 1.4;
  }
  
  .apple-alert-message {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
  }
  
  .apple-alert-buttons {
    display: flex;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
  }
  
  .apple-alert-button {
    flex: 1;
    padding: 14px 16px;
    background: transparent;
    border: none;
    color: #2563eb;
    font-size: 17px;
    font-weight: 400;
    cursor: pointer;
    transition: background 0.2s ease;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
  }
  
  .apple-alert-button:not(:last-child) {
    border-right: 1px solid rgba(0, 0, 0, 0.1);
  }
  
  .apple-alert-button:hover {
    background: rgba(0, 0, 0, 0.04);
  }
  
  .apple-alert-button:active {
    background: rgba(0, 0, 0, 0.08);
  }
  
  .apple-alert-button.primary {
    font-weight: 600;
    color: #2563eb;
  }
  
  .apple-alert-button.destructive {
    color: #ef4444;
    font-weight: 600;
  }
  
  .app-sidebar nav {
    margin: 0 !important;
    padding: 0 !important;
  }
  .app-nav {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    margin: 0 !important;
    padding: 0 !important;
    flex: 0 0 auto !important;
    width: 100% !important;
  }
  .app-sidebar nav + nav {
    margin-top: 0rem !important;
  }
  .app-nav .group {
    gap: 0.1rem;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
  }
  /* Dashboard button: tighter background around icon/text */
  .app-nav .group:first-child .subitem {
    padding: 0rem 0.6rem;
    border-radius: 12px;
    min-height: auto;
  }
  .app-nav .group:first-child .subitem .app-nav-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 12px;
    justify-content: center;
    align-items: center;
  }
  /* Center the gear (manage) and palette (system settings) icons */
  a[href="system_settings.php"] .app-nav-icon {
    width: 2.5rem;
    height: 2.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    margin: 0;
    line-height: 1;
    flex-shrink: 0;
    text-align: center;
    font-size: 1.25rem;
  }
  .subitem .app-nav-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  /* Base link styling for main nav items */
  .app-nav a {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.6rem 0.85rem;
    margin: 0.1rem 0;
    width: 100%;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    transition: all 0.3s ease;
  }

  .app-nav a .app-nav-label {
    flex: 1 1 auto;
    min-width: 0;
  }

  .app-nav a .expense-status-badges,
  .app-nav a .payment-status-badges,
  .app-nav a .booking-status-badges,
  .app-nav a .utility-status-badges,
  .app-nav a .repair-status-badges {
    margin-left: auto !important;
    flex-shrink: 0;
  }
  .group {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
  }
  
  /* Active menu item styles */
  .app-nav a.active,
  .app-nav a.subitem.active {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.1));
    border-left: 3px solid #3b82f6;
    color: #60a5fa;
    font-weight: 600;
  }
  
  .app-nav a.active .app-nav-icon,
  .app-nav a.subitem.active .app-nav-icon {
    transform: scale(1.1);
  }
  
  /* Sidebar collapsed state - icon centered */
  aside.sidebar-collapsed {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    text-align: center !important;
  }
  
  /* Desktop: Collapsed sidebar shows as narrow rail (for screens > 1024px) */
  aside.app-sidebar.collapsed,
  aside.sidebar-collapsed {
    width: 80px !important;
    min-width: 80px !important;
    max-width: 80px !important;
    display: flex !important;
    flex-direction: column !important;
    visibility: visible !important;
    transform: none !important;
    position: relative !important;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                min-width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                max-width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  /* Mobile: Override collapsed to full sidebar when open */
  @media (max-width: 1024px) {
    aside.app-sidebar.collapsed {
      width: 260px !important;
      min-width: 260px !important;
      max-width: 260px !important;
    }
  }
  
  aside.sidebar-collapsed * {
    text-align: center !important;
  }
  aside.sidebar-collapsed .team-switcher {
    width: auto !important;
      gap: 0.5rem !important;
    padding: 0.5rem 0 !important;
  }
  aside.sidebar-collapsed .team-avatar {
      width: 48px !important;
      height: 48px !important;
      padding: 0 !important;
      margin: 0 auto !important;
  }
    aside.sidebar-collapsed .team-avatar-img {
      width: 48px !important;
      height: 48px !important;
      object-fit: cover !important;
    }
  aside.sidebar-collapsed .team-meta {
    display: none !important;
  }
  
  /* Also apply to .app-sidebar.collapsed */
  .app-sidebar.collapsed .team-switcher {
    width: auto !important;
      gap: 0.5rem !important;
    padding: 0.5rem 0 !important;
  }
  .app-sidebar.collapsed .team-avatar {
      width: 48px !important;
      height: 48px !important;
      padding: 0 !important;
      margin: 0 auto !important;
  }
    .app-sidebar.collapsed .team-avatar-img {
      width: 48px !important;
      height: 48px !important;
      object-fit: cover !important;
    }
  .app-sidebar.collapsed .team-meta {
    display: none !important;
  }
  aside.sidebar-collapsed .group {
    width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
  }
  aside.sidebar-collapsed details {
    width: 100% !important;
  }
  
  /* Make details 100% width */
  details {
    width: 100% !important;
  }
  aside.sidebar-collapsed .subitem {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    height: auto !important;
    padding: 0.5rem 0 !important;
    margin: 0 !important;
    gap: 0 !important;
  }
  aside.sidebar-collapsed .subitem .app-nav-icon {
    width: auto !important;
    height: auto !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  aside.sidebar-collapsed .subitem .app-nav-icon {
    margin-left: auto !important;
    margin-right: auto !important;
  }
  aside.sidebar-collapsed .subitem .app-nav-label {
    display: none;
  }
  
  /* Hide dropdown content when sidebar is collapsed */
  aside.sidebar-collapsed details[open] > :not(summary) {
    display: none !important;
  }

  /* Normalize native details content slot in collapsed mode */
  aside.sidebar-collapsed details::details-content,
  .app-sidebar.collapsed details::details-content {
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
    background: transparent !important;
  }

  aside.sidebar-collapsed details[open]::details-content,
  .app-sidebar.collapsed details[open]::details-content {
    display: none !important;
  }

  aside.sidebar-collapsed #nav-todo[open]::details-content,
  .app-sidebar.collapsed #nav-todo[open]::details-content {
    display: block !important;
  }
  
  /* Hide all labels when sidebar is collapsed - but show on mobile */
  aside.sidebar-collapsed .app-nav-label,
  aside.sidebar-collapsed .summary-label {
    display: none !important;
  }
  
  .app-sidebar.collapsed .app-nav-label,
  .app-sidebar.collapsed .summary-label {
    display: none !important;
  }
  
  /* Show labels on mobile even if collapsed */
  @media (max-width: 1024px) {
    aside.sidebar-collapsed .app-nav-label,
    aside.sidebar-collapsed .summary-label,
    .app-sidebar.collapsed .app-nav-label,
    .app-sidebar.collapsed .summary-label {
      display: inline !important;
    }
  }
  
  /* Show dropdown items as icon-only in vertical column when sidebar is collapsed */
  aside.sidebar-collapsed details,
  .app-sidebar.collapsed details {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    width: 100% !important;
  }
  
  aside.sidebar-collapsed details > a,
  .app-sidebar.collapsed details > a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0.6rem 0 !important;
    margin: 0 !important;
    width: 100% !important;
    animation: none !important;
  }
  
  aside.sidebar-collapsed details > a .app-nav-icon,
  .app-sidebar.collapsed details > a .app-nav-icon {
    width: 2rem !important;
    height: 2rem !important;
    font-size: 1.2rem !important;
    margin: 0 !important;
  }
  
  aside.sidebar-collapsed details > a .app-nav-label,
  .app-sidebar.collapsed details > a .app-nav-label {
    display: none !important;
  }
  
  /* Reset summary styling when sidebar is collapsed */
  aside.sidebar-collapsed details summary {
    padding: 0.75rem 0 !important;
    width: 100% !important;
    display: block !important;
    position: relative !important;
    min-height: 3rem !important;
  }
  aside.sidebar-collapsed details summary .summary-link {
    display: block !important;
    width: 100% !important;
    position: relative !important;
    height: 2.5rem !important;
  }
  aside.sidebar-collapsed details summary .app-nav-icon {
    width: 2.5rem !important;
    height: 2.5rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: absolute !important;
    left: 50% !important;
    top: 50% !important;
    transform: translate(-50%, -50%) !important;
    font-size: 1.5rem !important;
  }
  .app-sidebar.collapsed details summary {
    pointer-events: auto !important;
    cursor: pointer !important;
    padding: 0.75rem 0.5rem !important;
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 0.5rem !important;
    min-height: 3rem !important;
  }
  .app-sidebar.collapsed details summary .summary-link {
    pointer-events: auto !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 1 !important;
    height: auto !important;
    position: static !important;
  }
  .app-sidebar.collapsed details summary .app-nav-icon {
    width: 2rem !important;
    height: 2rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: static !important;
    left: auto !important;
    top: auto !important;
    transform: none !important;
    font-size: 1.2rem !important;
  }
  .app-sidebar.collapsed details summary .chev {
    display: none !important;
  }

  /* Final override: all Todo child buttons use compact rail cards in collapsed mode */
  @media (min-width: 1025px) {
    /* Top-level summary items in collapsed rail */
    aside.sidebar-collapsed #nav-dashboard > summary,
    aside.sidebar-collapsed #nav-tenants > summary,
    aside.sidebar-collapsed #nav-settings > summary,
    .app-sidebar.collapsed #nav-dashboard > summary,
    .app-sidebar.collapsed #nav-tenants > summary,
    .app-sidebar.collapsed #nav-settings > summary {
      position: relative !important;
      padding: 0.35rem 0 !important;
      min-height: 3rem !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
    }

    aside.sidebar-collapsed #nav-dashboard > summary .summary-link,
    aside.sidebar-collapsed #nav-tenants > summary .summary-link,
    aside.sidebar-collapsed #nav-settings > summary .summary-link,
    .app-sidebar.collapsed #nav-dashboard > summary .summary-link,
    .app-sidebar.collapsed #nav-tenants > summary .summary-link,
    .app-sidebar.collapsed #nav-settings > summary .summary-link {
      width: 2.45rem !important;
      height: 2.45rem !important;
      min-width: 2.45rem !important;
      min-height: 2.45rem !important;
      margin: 0 auto !important;
      padding: 0 !important;
      border-radius: 0.75rem !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      background: rgba(148, 163, 184, 0.14) !important;
      border: 1px solid rgba(148, 163, 184, 0.32) !important;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03) !important;
    }

    aside.sidebar-collapsed #nav-dashboard > summary .summary-link.active,
    aside.sidebar-collapsed #nav-tenants > summary .summary-link.active,
    aside.sidebar-collapsed #nav-settings > summary .summary-link.active,
    .app-sidebar.collapsed #nav-dashboard > summary .summary-link.active,
    .app-sidebar.collapsed #nav-tenants > summary .summary-link.active,
    .app-sidebar.collapsed #nav-settings > summary .summary-link.active {
      background: rgba(59, 130, 246, 0.2) !important;
      border-color: rgba(59, 130, 246, 0.45) !important;
    }

    aside.sidebar-collapsed #nav-dashboard > summary .chev,
    aside.sidebar-collapsed #nav-tenants > summary .chev,
    aside.sidebar-collapsed #nav-settings > summary .chev,
    .app-sidebar.collapsed #nav-dashboard > summary .chev,
    .app-sidebar.collapsed #nav-tenants > summary .chev,
    .app-sidebar.collapsed #nav-settings > summary .chev {
      display: none !important;
    }

    aside.sidebar-collapsed #nav-dashboard > summary .app-nav-icon,
    aside.sidebar-collapsed #nav-tenants > summary .app-nav-icon,
    aside.sidebar-collapsed #nav-settings > summary .app-nav-icon,
    .app-sidebar.collapsed #nav-dashboard > summary .app-nav-icon,
    .app-sidebar.collapsed #nav-tenants > summary .app-nav-icon,
    .app-sidebar.collapsed #nav-settings > summary .app-nav-icon {
      width: 2rem !important;
      height: 2rem !important;
      min-width: 2rem !important;
      min-height: 2rem !important;
      margin: 0 !important;
    }

    aside.sidebar-collapsed #nav-todo > a,
    .app-sidebar.collapsed #nav-todo > a {
      width: 2.45rem !important;
      height: 2.45rem !important;
      min-width: 2.45rem !important;
      min-height: 2.45rem !important;
      margin: 0.2rem auto !important;
      padding: 0 !important;
      border-radius: 0.75rem !important;
      border-left: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      position: relative !important;
      background: rgba(148, 163, 184, 0.14) !important;
      border: 1px solid rgba(148, 163, 184, 0.32) !important;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03) !important;
      overflow: visible !important;
    }

    aside.sidebar-collapsed #nav-todo > a.active,
    .app-sidebar.collapsed #nav-todo > a.active {
      background: rgba(59, 130, 246, 0.2) !important;
      border-color: rgba(59, 130, 246, 0.45) !important;
    }

    aside.sidebar-collapsed #nav-todo > a .app-nav-label,
    .app-sidebar.collapsed #nav-todo > a .app-nav-label {
      display: none !important;
    }

    aside.sidebar-collapsed #nav-todo > a .app-nav-icon,
    .app-sidebar.collapsed #nav-todo > a .app-nav-icon {
      margin: 0 !important;
      width: 2rem !important;
      height: 2rem !important;
      min-width: 2rem !important;
      min-height: 2rem !important;
    }

    aside.sidebar-collapsed #nav-todo > a .booking-status-badges,
    aside.sidebar-collapsed #nav-todo > a .utility-status-badges,
    aside.sidebar-collapsed #nav-todo > a .expense-status-badges,
    aside.sidebar-collapsed #nav-todo > a .payment-status-badges,
    aside.sidebar-collapsed #nav-todo > a .repair-status-badges,
    .app-sidebar.collapsed #nav-todo > a .booking-status-badges,
    .app-sidebar.collapsed #nav-todo > a .utility-status-badges,
    .app-sidebar.collapsed #nav-todo > a .expense-status-badges,
    .app-sidebar.collapsed #nav-todo > a .payment-status-badges,
    .app-sidebar.collapsed #nav-todo > a .repair-status-badges {
      display: inline-flex !important;
      position: absolute !important;
      top: 0.12rem !important;
      right: 0.2rem !important;
      margin: 0 !important;
      z-index: 3 !important;
      pointer-events: auto !important;
    }

    aside.sidebar-collapsed #nav-todo > a .todo-action-badge,
    .app-sidebar.collapsed #nav-todo > a .todo-action-badge,
    aside.sidebar-collapsed #nav-todo > a > span[data-bs-toggle="tooltip"],
    .app-sidebar.collapsed #nav-todo > a > span[data-bs-toggle="tooltip"] {
      min-width: 18px !important;
      height: 18px !important;
      padding: 0 5px !important;
      font-size: 10px !important;
      line-height: 1 !important;
      border: 1px solid rgba(255, 255, 255, 0.72) !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25) !important;
      z-index: 3 !important;
    }

    aside.sidebar-collapsed #nav-todo > a > span[data-bs-toggle="tooltip"],
    .app-sidebar.collapsed #nav-todo > a > span[data-bs-toggle="tooltip"] {
      position: absolute !important;
      top: 0.12rem !important;
      right: 0.2rem !important;
      transform: none !important;
    }
  }
  
  /* Mobile Responsive */
  @media (max-width: 1024px) {
    html, body {
      width: 100%;
      overflow-x: hidden;
    }
    
    .app-shell {
      flex-direction: row !important;
      width: 100%;
      overflow-x: hidden;
    }
    
    /* Mobile: sidebar as fixed overlay with slide animation */
    .app-sidebar:not(.collapsed) {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      height: 100vh !important;
      width: 260px !important;
      z-index: 9999 !important;
      background: #f9fafb !important;
      transform: translateX(-100%) !important;
      transition: transform 0.35s ease, visibility 0s linear 0.35s !important;
      will-change: transform;
      box-shadow: 4px 0 24px rgba(0,0,0,0.6) !important;
      padding: 1.25rem 0.75rem !important;
      overflow: hidden !important;
      flex-shrink: 0 !important;
      margin: 0 !important;
      display: flex !important;
      flex-direction: column !important;
      visibility: hidden;
      pointer-events: none !important;
    }
    
    /* Mobile: collapsed sidebar is hidden completely */
    aside.app-sidebar.collapsed {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      height: 100vh !important;
      width: 260px !important;
      z-index: 9999 !important;
      background: #f9fafb !important;
      transform: translateX(-100%) !important;
      visibility: hidden;
      transition: transform 0.35s ease, visibility 0s linear 0.35s !important;
      pointer-events: none !important;
    }

    /* When sidebar is open - make it visible and slide in */
    .app-sidebar.mobile-open,
    body.sidebar-open .app-sidebar {
      transform: translateX(0) !important;
      visibility: visible !important;
      pointer-events: auto !important;
      transition-delay: 0s !important;
    }

    body.sidebar-open aside.app-sidebar.collapsed {
      transform: translateX(0) !important;
      visibility: visible !important;
      pointer-events: auto !important;
      transition-delay: 0s !important;
    }
    
    /* Mobile: header stays at top */
    .app-sidebar .sidebar-header {
      flex-shrink: 0 !important;
      background: #f9fafb !important;
      border-bottom: 1px solid #e5e7eb !important;
    }

    /* Light mode mobile sidebar */
    body.live-light .app-sidebar .sidebar-header,
    html.light-theme .app-sidebar .sidebar-header {
      background: #f9fafb !important;
    }

    body.live-light .app-sidebar .sidebar-footer,
    html.light-theme .app-sidebar .sidebar-footer {
      background: #f9fafb !important;
    }

    body.live-light .app-sidebar,
    html.light-theme .app-sidebar {
      background: #f9fafb !important;
    }

    body.live-light .app-sidebar.collapsed,
    html.light-theme .app-sidebar.collapsed {
      background: #f9fafb !important;
    }
    
    /* Mobile: nav area scrolls, footer stays */
    .app-sidebar .sidebar-nav-area {
      flex: 1 !important;
      overflow-y: auto !important;
      overflow-x: hidden !important;
      min-height: 0 !important;
    }
    
    .app-sidebar .sidebar-footer {
      flex-shrink: 0 !important;
      position: relative !important;
      background: #f9fafb !important;
      border-top: 1px solid #e5e7eb !important;
    }
    
    /* Show team avatar/logo on mobile/tablet */
    .team-switcher {
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      width: 100% !important;
      margin-bottom: 1rem !important;
    }
    
    .team-avatar {
      display: flex !important;
        width: 120px !important;
        height: 120px !important;
        margin: 0 auto !important;
    }
    
    .team-avatar-img {
      display: block !important;
        width: 120px !important;
        height: 120px !important;
        object-fit: cover !important;
        border-radius: 12px !important;
    }

    .team-meta {
      display: block !important;
      text-align: center !important;
      margin-top: 0.75rem !important;
      width: 100% !important;
    }

    .team-meta .name {
        font-size: 1rem !important;
        font-weight: 700 !important;
      color: #1f2937 !important;
        line-height: 1.4 !important;
    }
    
    /* When mobile-open class is applied, slide in from left */
    /* Ensure all elements inside open sidebar are clickable */
    .app-sidebar.mobile-open *,
    body.sidebar-open .app-sidebar * {
      pointer-events: auto !important;
    }

    .app-sidebar.mobile-open a,
    .app-sidebar.mobile-open button,
    .app-sidebar.mobile-open details,
    .app-sidebar.mobile-open summary,
    body.sidebar-open .app-sidebar a,
    body.sidebar-open .app-sidebar button,
    body.sidebar-open .app-sidebar details,
    body.sidebar-open .app-sidebar summary {
      pointer-events: auto !important;
      cursor: pointer !important;
    }
    
    /* Reset collapsed styles that might conflict */
    aside.app-sidebar.collapsed {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      height: 100vh !important;
      width: 260px !important;
      z-index: 9999 !important;
      background: #f9fafb !important;
      transform: translateX(-100%) !important;
      transition: transform 0.35s ease, visibility 0s linear 0.35s !important;
      visibility: hidden;
      will-change: transform;
      box-shadow: 4px 0 24px rgba(0,0,0,0.6) !important;
      padding: 1.25rem 0.75rem !important;
      overflow: auto !important;
      display: flex !important;
      flex-direction: column !important;
      gap: 0.5rem !important;
      margin: 0 !important;
      pointer-events: none !important;
    }
    
    aside.app-sidebar.collapsed.mobile-open,
    body.sidebar-open aside.app-sidebar.collapsed {
      transform: translateX(0) !important;
      visibility: visible !important;
      pointer-events: auto !important;
      transition-delay: 0s !important;
    }
    
    /* Content area takes remaining space */
    .app-main {
      margin: 0 !important;
      width: 100vw !important;
      height: auto !important;
      position: relative;
      z-index: 1 !important;
      flex: 1 1 auto !important;
      padding: 1rem !important;
      transition: all 0.3s ease !important;
      box-sizing: border-box !important;
      overflow-x: hidden;
    }
    
    /* Remove margins/padding that cause gaps */
    .app-main section {
      margin: 0 !important;
      padding: 1.25rem !important;
    }
    
    /* Header responsive */
    .app-main header {
      width: 100% !important;
      margin: 0 !important;
      padding: 0.5rem !important;
      box-sizing: border-box;
    }
    
    .app-main header h2 {
      font-size: 1rem !important;
      margin: 0 !important;
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    /* Text wrapping to prevent overflow */
    .app-main h1,
    .app-main h2,
    .app-main h3,
    .app-main p,
    .app-main .card,
    .app-main .manage-panel,
    .app-main .section-header,
    .app-main .chart-card,
    .app-main .small-card {
      word-break: break-word;
      overflow-wrap: break-word;
      max-width: 100%;
      margin: 0 !important;
      padding-right: 0 !important;
    }
    
    /* Global header styles */
    header {
      width: 100% !important;
      margin: 0 !important;
      padding: 0.5rem !important;
      box-sizing: border-box;
    }
    
    header h2 {
      font-size: 1rem !important;
      margin: 0 !important;
    }
    
    /* Mobile overlay - dark background when sidebar open */
    body.sidebar-open::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: 9998;
      pointer-events: auto;
    }

    /* Keep hamburger button clickable above overlay while sidebar is open */
    body.sidebar-open #sidebar-toggle {
      position: fixed !important;
      top: 12px !important;
      left: 12px !important;
      z-index: 10050 !important;
      pointer-events: auto !important;
    }

    /* Keep hamburger button always clickable - removed hiding behavior */
    /* User should be able to close sidebar by clicking hamburger */
  }
  
  @media (max-width: 480px) {
    .team-switcher {
      padding: 0.25rem !important;
    }
    .subitem {
      padding-left: 0.5rem;
      font-size: 0.9rem;
    }
    details summary {
      padding: 0.4rem 0.5rem !important;
      font-size: 0.9rem;
    }
  }</style>
<style>
  /* Final overrides to ensure active filter/view icons and counts are white in light modes. */
  body.live-light a.filter-btn[style*="#60a5fa"],
  body.live-light a.filter-btn[style*="background: #60a5fa"],
  body.live-light a.filter-btn[style*="background:#60a5fa"],
  body.live-light a.filter-btn[style*="color:#fff"],
  body.live-light .view-toggle-btn.active,
  html.light-theme a.filter-btn[style*="#60a5fa"],
  html.light-theme .view-toggle-btn.active,
  html.live-light a.filter-btn[style*="#60a5fa"],
  html.live-light .view-toggle-btn.active,
  .live-light a.filter-btn[style*="#60a5fa"],
  .live-light .view-toggle-btn.active {
    color: #ffffff !important;
  }

  body.live-light a.filter-btn svg,
  body.live-light a.filter-btn svg *,
  body.live-light .view-toggle-btn.active svg,
  body.live-light .view-toggle-btn.active svg *,
  html.light-theme a.filter-btn svg,
  html.light-theme a.filter-btn svg *,
  html.light-theme .view-toggle-btn.active svg,
  html.light-theme .view-toggle-btn.active svg *,
  html.live-light a.filter-btn svg,
  html.live-light a.filter-btn svg *,
  html.live-light .view-toggle-btn.active svg,
  html.live-light .view-toggle-btn.active svg *,
  .live-light a.filter-btn svg,
  .live-light a.filter-btn svg *,
  .live-light .view-toggle-btn.active svg,
  .live-light .view-toggle-btn.active svg * {
    stroke: #ffffff !important;
    color: #ffffff !important;
    fill: #ffffff !important;
  }

  /* Also ensure any .filter-btn with inline background blue shows its child count white */
  body.live-light a.filter-btn span,
  body.live-light a.filter-btn b,
  body.live-light a.filter-btn strong {
    color: #ffffff !important;
  }

  /* Final override: collapsed Google button must be icon-only, centered, non-flex */
  .google-link-wrap {
    margin-top: 0.5rem;
    margin-bottom: 0.3rem;
  }

  .app-sidebar.collapsed .google-link-wrap,
  aside.sidebar-collapsed .google-link-wrap {
    text-align: center !important;
  }

  .app-sidebar.collapsed .google-link-wrap .google-link-btn,
  aside.sidebar-collapsed .google-link-wrap .google-link-btn {
    display: inline-block !important;
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    max-width: 40px !important;
    padding: 0 !important;
    margin: 0 !important;
    line-height: 40px !important;
    text-align: center !important;
    vertical-align: middle !important;
    box-sizing: border-box !important;
  }

  .app-sidebar.collapsed .google-link-wrap .google-link-btn .app-nav-label,
  aside.sidebar-collapsed .google-link-wrap .google-link-btn .app-nav-label {
    display: none !important;
  }

  .app-sidebar.collapsed .google-link-wrap .google-link-btn .google-icon,
  aside.sidebar-collapsed .google-link-wrap .google-link-btn .google-icon {
    display: inline-block !important;
    width: 16px !important;
    height: 16px !important;
    margin: 0 !important;
    vertical-align: middle !important;
  }

  /* Bootstrap 5.3 tooltip styles (component-only to avoid global CSS side effects). */
  .tooltip {
    --bs-tooltip-zindex: 1080;
    --bs-tooltip-max-width: 200px;
    --bs-tooltip-padding-x: 0.5rem;
    --bs-tooltip-padding-y: 0.25rem;
    --bs-tooltip-margin: 0;
    --bs-tooltip-font-size: 0.875rem;
    --bs-tooltip-color: #f8fafc;
    --bs-tooltip-bg: #0f172a;
    --bs-tooltip-border-radius: 0.375rem;
    --bs-tooltip-opacity: 0.9;
    --bs-tooltip-arrow-width: 0.8rem;
    --bs-tooltip-arrow-height: 0.4rem;
    z-index: var(--bs-tooltip-zindex);
    display: block;
    margin: var(--bs-tooltip-margin);
    font-family: var(--bs-font-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji");
    font-style: normal;
    font-weight: 400;
    line-height: 1.5;
    text-align: left;
    text-decoration: none;
    text-shadow: none;
    text-transform: none;
    letter-spacing: normal;
    word-break: normal;
    white-space: normal;
    word-spacing: normal;
    line-break: auto;
    font-size: var(--bs-tooltip-font-size);
    word-wrap: break-word;
    opacity: 0;
  }

  .tooltip.show {
    opacity: var(--bs-tooltip-opacity);
  }

  .tooltip .tooltip-arrow {
    display: block;
    width: var(--bs-tooltip-arrow-width);
    height: var(--bs-tooltip-arrow-height);
  }

  .tooltip .tooltip-arrow::before {
    position: absolute;
    content: "";
    border-color: transparent;
    border-style: solid;
  }

  .bs-tooltip-top .tooltip-arrow,
  .bs-tooltip-auto[data-popper-placement^="top"] .tooltip-arrow {
    bottom: calc(-1 * var(--bs-tooltip-arrow-height));
  }

  .bs-tooltip-top .tooltip-arrow::before,
  .bs-tooltip-auto[data-popper-placement^="top"] .tooltip-arrow::before {
    top: -1px;
    border-width: var(--bs-tooltip-arrow-height) calc(var(--bs-tooltip-arrow-width) * 0.5) 0;
    border-top-color: var(--bs-tooltip-bg);
  }

  .bs-tooltip-end .tooltip-arrow,
  .bs-tooltip-auto[data-popper-placement^="right"] .tooltip-arrow {
    left: calc(-1 * var(--bs-tooltip-arrow-height));
    width: var(--bs-tooltip-arrow-height);
    height: var(--bs-tooltip-arrow-width);
  }

  .bs-tooltip-end .tooltip-arrow::before,
  .bs-tooltip-auto[data-popper-placement^="right"] .tooltip-arrow::before {
    right: -1px;
    border-width: calc(var(--bs-tooltip-arrow-width) * 0.5) var(--bs-tooltip-arrow-height) calc(var(--bs-tooltip-arrow-width) * 0.5) 0;
    border-right-color: var(--bs-tooltip-bg);
  }

  .bs-tooltip-bottom .tooltip-arrow,
  .bs-tooltip-auto[data-popper-placement^="bottom"] .tooltip-arrow {
    top: calc(-1 * var(--bs-tooltip-arrow-height));
  }

  .bs-tooltip-bottom .tooltip-arrow::before,
  .bs-tooltip-auto[data-popper-placement^="bottom"] .tooltip-arrow::before {
    bottom: -1px;
    border-width: 0 calc(var(--bs-tooltip-arrow-width) * 0.5) var(--bs-tooltip-arrow-height);
    border-bottom-color: var(--bs-tooltip-bg);
  }

  .bs-tooltip-start .tooltip-arrow,
  .bs-tooltip-auto[data-popper-placement^="left"] .tooltip-arrow {
    right: calc(-1 * var(--bs-tooltip-arrow-height));
    width: var(--bs-tooltip-arrow-height);
    height: var(--bs-tooltip-arrow-width);
  }

  .bs-tooltip-start .tooltip-arrow::before,
  .bs-tooltip-auto[data-popper-placement^="left"] .tooltip-arrow::before {
    left: -1px;
    border-width: calc(var(--bs-tooltip-arrow-width) * 0.5) 0 calc(var(--bs-tooltip-arrow-width) * 0.5) var(--bs-tooltip-arrow-height);
    border-left-color: var(--bs-tooltip-bg);
  }

  .tooltip-inner {
    max-width: var(--bs-tooltip-max-width);
    padding: var(--bs-tooltip-padding-y) var(--bs-tooltip-padding-x);
    color: var(--bs-tooltip-color) !important;
    text-align: center;
    background-color: var(--bs-tooltip-bg) !important;
    border-radius: var(--bs-tooltip-border-radius);
    box-shadow: 0 8px 22px rgba(2, 6, 23, 0.35);
    border: 1px solid rgba(148, 163, 184, 0.28);
  }

  .tooltip,
  .tooltip .tooltip-inner,
  .tooltip .tooltip-inner * {
    color: #f8fafc !important;
  }

  .user-row-clickable {
    position: relative;
    cursor: pointer;
    border-radius: 10px;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
  }

  .user-row-clickable::after {
    content: "✎";
    position: absolute;
    top: 6px;
    right: 8px;
    font-size: 0.72rem;
    opacity: 0.72;
  }

  .user-row-clickable:hover,
  .user-row-clickable:focus-visible {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.25);
    background: rgba(255, 255, 255, 0.08) !important;
    outline: none;
  }

  .user-meta .edit-hint {
    font-size: 0.68rem;
    opacity: 0.82;
    margin-top: 2px;
  }

  .sidebar-account-flash {
    margin-bottom: 0.5rem;
    border-radius: 10px;
    font-size: 0.74rem;
    line-height: 1.35;
    padding: 0.45rem 0.55rem;
  }

  .sidebar-account-flash.success {
    background: rgba(16, 185, 129, 0.18);
    color: #d1fae5 !important;
    border: 1px solid rgba(16, 185, 129, 0.45);
  }

  .sidebar-account-flash.error {
    background: rgba(239, 68, 68, 0.18);
    color: #fee2e2 !important;
    border: 1px solid rgba(239, 68, 68, 0.45);
  }

  .sidebar-account-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 1rem;
  }

  .sidebar-account-modal-backdrop[hidden] {
    display: none !important;
  }

  .sidebar-account-modal {
    width: min(100%, 420px);
    border-radius: 14px;
    background: #ffffff;
    border: 1px solid rgba(148, 163, 184, 0.35);
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.25);
    overflow: hidden;
  }

  .sidebar-account-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.24);
  }

  .sidebar-account-modal-header h3 {
    margin: 0;
    font-size: 1rem;
    color: #0f172a !important;
  }

  .sidebar-account-modal-close {
    width: 36px;
    height: 36px;
    border: 1px solid rgba(148, 163, 184, 0.45);
    border-radius: 8px;
    background: #f8fafc;
    color: #334155 !important;
    font-size: 0;
    line-height: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.18s ease, border-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
  }

  .sidebar-account-modal-close svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0;
  }

  .sidebar-account-modal-close:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);
  }

  .sidebar-account-modal-close:active {
    transform: scale(0.96);
  }

  .sidebar-account-modal-close:focus-visible {
    outline: 2px solid #60a5fa;
    outline-offset: 2px;
  }

  .sidebar-account-modal-body {
    padding: 1rem;
    color: #0f172a !important;
  }

  .sidebar-account-modal-body label {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.8rem;
    color: #0f172a !important;
  }

  .sidebar-account-modal-body input {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, 0.4);
    border-radius: 10px;
    padding: 0.55rem 0.65rem;
    background: #f8fafc;
    color: #0f172a !important;
    margin-bottom: 0.75rem;
  }

  .sidebar-account-modal-body input::placeholder {
    color: rgba(100, 116, 139, 0.8);
  }

  .sidebar-account-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 0.4rem;
  }

  .sidebar-account-actions button {
    border: 0;
    border-radius: 10px;
    padding: 0.5rem 0.85rem;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
  }

  .sidebar-account-actions .cancel-btn {
    background: #e2e8f0;
    color: #0f172a !important;
  }

  .sidebar-account-actions .save-btn {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #ffffff !important;
  }

  body.sidebar-account-modal-open {
    overflow: hidden;
  }

  body.live-light .user-row-clickable:hover,
  body.live-light .user-row-clickable:focus-visible {
    background: rgba(37, 99, 235, 0.08) !important;
  }

  body.live-light .sidebar-account-modal {
    background: #ffffff;
  }
</style>
<script>
  // ====== Global Admin Font Scale Sync ======
  // โหลด font scale จาก localStorage ทันทีก่อน DOM render
  (function() {
    const savedScale = localStorage.getItem('adminFontScale');
    if (savedScale && !isNaN(parseFloat(savedScale))) {
      document.documentElement.style.setProperty('--font-scale', savedScale);
      document.documentElement.style.setProperty('--admin-font-scale', savedScale);
    }
    
    // Listen สำหรับ font scale change จากหน้าอื่น (เช่น Settings)
    window.addEventListener('storage', function(e) {
      if (e.key === 'adminFontScale' && e.newValue) {
        document.documentElement.style.setProperty('--font-scale', e.newValue);
        document.documentElement.style.setProperty('--admin-font-scale', e.newValue);
      }
    });
  })();
  
  // Force reset collapsed on mobile IMMEDIATELY before CSS applies
  // Use a safer media-query injection so desktop collapsed styles are not overridden
  if (window.innerWidth <= 1024) {
     document.write('<style>@media (max-width:1024px){ .app-sidebar.collapsed { width: 240px !important; } .app-sidebar.collapsed .app-nav-label, .app-sidebar.collapsed .summary-label, .app-sidebar.collapsed .chev { display: revert !important; } .app-sidebar.collapsed .team-avatar { width: 120px !important; height: 120px !important; padding: 0 !important; margin: 0 auto !important; } .app-sidebar.collapsed .team-avatar-img { width: 120px !important; height: 120px !important; object-fit: cover !important; } .app-sidebar.collapsed .team-meta { display: block !important; text-align: center !important; padding-top: 0.75rem !important; } }</style>');
  }
</script>

<!-- Persist admin default view mode for all pages -->
<script>
(function(){
  try {
    var mode = <?php echo json_encode(isset($defaultViewMode) ? $defaultViewMode : 'grid'); ?>;
    if (mode !== 'grid' && mode !== 'list') { mode = 'grid'; }
    localStorage.setItem('adminDefaultViewMode', mode);
  } catch (e) {}
})();
</script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const tooltipSelector = '[data-bs-toggle="tooltip"]';

    function initTooltips() {
      if (!window.bootstrap || !window.bootstrap.Tooltip) {
        return;
      }

      document.querySelectorAll(tooltipSelector).forEach(function(el) {
        if (!window.bootstrap.Tooltip.getInstance(el)) {
          new window.bootstrap.Tooltip(el, {
            container: 'body'
          });
        }
      });
    }

    if (window.bootstrap && window.bootstrap.Tooltip) {
      initTooltips();
      return;
    }

    const existingBundle = document.querySelector('script[data-bootstrap-tooltip-bundle="true"]');
    if (existingBundle) {
      existingBundle.addEventListener('load', initTooltips, { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
    script.defer = true;
    script.dataset.bootstrapTooltipBundle = 'true';
    script.addEventListener('load', initTooltips, { once: true });
    document.head.appendChild(script);
  });
</script>
<aside class="app-sidebar">
  <!-- Mobile Close Button -->
  <button type="button" id="sidebar-close-btn" class="sidebar-close-btn" aria-label="ปิด Sidebar" onclick="closeSidebarMobile()">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="18" y1="6" x2="6" y2="18"/>
      <line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
  </button>
  <!-- Header: Logo & Name - Fixed at top -->
  <div class="sidebar-header">
    <div class="team-avatar" >
      <!-- Project logo from database -->
      <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="team-avatar-img"  />
    </div>
    <div class="team-meta">
      <div class="name"><?php echo htmlspecialchars($siteName); ?></div>
    </div>
  </div>

  <!-- Navigation area - Scrollable -->
  <div class="sidebar-nav-area">
  <nav class="app-nav" aria-label="Main navigation" >
    <div class="group" >
      <details id="nav-dashboard" open>
        <summary>
          <a href="dashboard.php" class="summary-link">
            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
            <span class="summary-label">แดชบอร์ด</span>
          </a>
          <span class="chev" style="font-size: 1.5rem;">›</span>
        </summary>
        <a class="" href="report_tenants.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="app-nav-label">รายงานผู้เช่า</span></a>
        <a class="" href="report_reservations.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg></span><span class="app-nav-label">รายงานการจอง</span></a>
        <a class="" href="manage_stay.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span><span class="app-nav-label">รายงานการเข้าพัก</span></a>
        <a class="" href="report_utility.php"><span class="app-nav-icon utility-icon-toggle" aria-hidden="true"><svg class="utility-icon water" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg><svg class="utility-icon electric" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span><span class="app-nav-label" style="font-size: 0.8rem;">รายงานสาธารณูปโภค</span></a>
        <a class="" href="manage_revenue.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><span class="app-nav-label">รายงานรายรับ</span></a>
        <a class="" href="report_rooms.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span><span class="app-nav-label">รายงานห้องพัก</span></a>
        <a class="" href="report_payments.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span><span class="app-nav-label">รายงานชำระเงิน</span></a>
        <a class="" href="report_invoice.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="app-nav-label">รายงานใบแจ้ง</span></a>
        <a class="" href="report_repairs.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span><span class="app-nav-label">รายงานแจ้งซ่อม</span></a>
        <a class="" href="report_news.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/></svg></span><span class="app-nav-label">รายงานข่าวสาร</span></a>
        <a class="" href="print_contract.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></span><span class="app-nav-label">พิมพ์สัญญา</span></a>
        <!-- Removed link to Public/booking_status.php per request -->
      </details>
    </div>
  </nav>

  <nav class="app-nav" aria-label="Todo navigation">
    <div class="group">
      <details id="nav-todo" open>
        <summary>
          <a href="http://project.3bbddns.com:36140/dormitory_management/Reports/todo_tasks.php#wizard" class="summary-link">
            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
            <span class="summary-label">งานที่ต้องทำ</span>
          </a>
          <?php if ($todoBadgeTotal > 0): ?><span class="todo-total-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="งานรอดำเนินการ <?php echo $todoBadgeTotal; ?> รายการ" style="background:#f59e0b;color:white;border-radius:999px;min-width:20px;height:20px;padding:0 5px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;pointer-events:auto;"><?php echo $todoBadgeTotal > 99 ? '99+' : $todoBadgeTotal; ?></span><?php endif; ?>
          <span class="chev chev-toggle" data-target="nav-todo" style="cursor:pointer;font-size: 1.5rem;">›</span>
        </summary>
        <a class="wizard-nav-item" href="tenant_wizard.php" style="position: relative; padding-right: 2.5rem; border-left: 4px solid #3b82f6; margin: 0; border-radius: 8px; overflow: visible;">
            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/><circle cx="12" cy="12" r="10" opacity="0.3"/><path d="M12 5l-2 2M14 5l2 2M12 19l-2-2M14 19l2-2"/></svg></span>
            <span class="app-nav-label" style="font-weight: 600; color: #60a5fa;">ตัวช่วยผู้เช่า</span>
            <?php if ($wizardIncompleteCount > 0): ?>
            <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="มีรายการค้างในตัวช่วยผู้เช่า <?php echo $wizardIncompleteCount; ?> รายการ" style="position: absolute; top: 6px; right: 6px; transform: none; background: #f59e0b; color: white; border-radius: 999px; min-width: 22px; height: 22px; padding: 0 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; line-height: 1; font-weight: bold; pointer-events: auto; cursor: help; z-index: 2;">
              <?php echo $wizardIncompleteCount > 99 ? '99+' : $wizardIncompleteCount; ?>
            </span>
            <?php endif; ?>
        </a>
        <a class="booking-nav-item" href="manage_booking.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/></svg></span><span class="app-nav-label">การจองห้อง</span><?php if ($bookingActionBadgeTotal > 0): ?><span class="booking-status-badges" aria-label="สถานะการจองค้าง"><span class="todo-action-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ต้องจัดการ: รอเข้าพัก/รอยืนยัน"><?php echo $bookingActionBadgeTotal > 99 ? '99+' : $bookingActionBadgeTotal; ?></span></span><?php endif; ?></a>
        <a class="utility-nav-item" href="manage_utility.php"><span class="app-nav-icon utility-icon-toggle" aria-hidden="true"><svg class="utility-icon water" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg><svg class="utility-icon electric" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span><span class="app-nav-label">จดมิเตอร์น้ำไฟ</span><?php if ($utilityActionBadgeTotal > 0): ?><span class="utility-status-badges" aria-label="สถานะจดมิเตอร์น้ำไฟค้าง"><span class="todo-action-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ต้องจัดการ: ห้องที่ยังไม่จดมิเตอร์"><?php echo $utilityActionBadgeTotal > 99 ? '99+' : $utilityActionBadgeTotal; ?></span></span><?php endif; ?></a>
                <a class="expense-nav-item" href="manage_expenses.php">
          <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
          <span class="app-nav-label">ค่าใช้จ่าย</span>
          <?php if ($expenseActionBadgeTotal > 0): ?>
          <span class="expense-status-badges" aria-label="สถานะค่าใช้จ่าย">
            <span class="todo-action-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ต้องจัดการ: รอชำระ/รอตรวจสอบ/ชำระไม่ครบ"><?php echo $expenseActionBadgeTotal > 99 ? '99+' : $expenseActionBadgeTotal; ?></span>
          </span>
          <?php endif; ?>
        </a>
        <a class="payment-nav-item" href="manage_payments.php">
          <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
          <span class="app-nav-label">การชำระเงิน</span>
          <?php if ($paymentActionBadgeTotal > 0): ?>
          <span class="payment-status-badges" aria-label="สถานะการชำระเงิน">
            <span class="todo-action-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ต้องจัดการ: รอชำระ/รอตรวจสอบ"><?php echo $paymentActionBadgeTotal > 99 ? '99+' : $paymentActionBadgeTotal; ?></span>
          </span>
          <?php endif; ?>
        </a>
        <a class="repair-nav-item" href="manage_repairs.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span><span class="app-nav-label">แจ้งซ่อม</span><?php if ($repairActionBadgeTotal > 0): ?><span class="repair-status-badges" aria-label="สถานะแจ้งซ่อมค้าง"><span class="todo-action-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ต้องจัดการ: รอซ่อม/กำลังซ่อม"><?php echo $repairActionBadgeTotal > 99 ? '99+' : $repairActionBadgeTotal; ?></span></span><?php endif; ?></a>
      </details>
    </div>
  </nav>

  <!-- ═══ Group 3: ข้อมูลผู้เช่า ═══ -->
  <nav class="app-nav" aria-label="Tenants navigation">
    <div class="group">
      <details id="nav-tenants">
        <summary>
          <a href="manage_tenants.php" class="summary-link">
            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="summary-label">ข้อมูลผู้เช่า</span>
          </a>
          <span class="chev chev-toggle" data-target="nav-tenants" style="cursor:pointer;font-size: 1.5rem;">›</span>
        </summary>
        <a class="" href="manage_contracts.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span><span class="app-nav-label">จัดการสัญญา</span></a>
        <a class="" href="qr_codes.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/><rect x="18" y="14" width="3" height="3"/><rect x="14" y="18" width="3" height="3"/><rect x="18" y="18" width="3" height="3"/></svg></span><span class="app-nav-label">QR Code ผู้เช่า</span></a>
      </details>
    </div>
  </nav>

  <!-- ═══ Group 4: ตั้งค่า ═══ -->
  <nav class="app-nav" aria-label="Settings navigation">
    <div class="group">
      <details id="nav-settings">
        <summary>
          <a href="system_settings.php" class="summary-link">
            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
            <span class="summary-label">ตั้งค่า</span>
          </a>
          <span class="chev chev-toggle" data-target="nav-settings" style="cursor:pointer;font-size: 1.5rem;">›</span>
        </summary>
        <a class="" href="manage_rooms.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg></span><span class="app-nav-label">ห้องพัก</span></a>
        <a class="" href="manage_news.php"><span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/></svg></span><span class="app-nav-label">ข่าวประชาสัมพันธ์</span></a>
      </details>
    </div>
  </nav>

  </div><!-- end sidebar-nav-area -->

  <div class="sidebar-footer">
    <?php if ($sidebarAccountFlashSuccess !== ''): ?>
      <div class="sidebar-account-flash success"><?php echo htmlspecialchars($sidebarAccountFlashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($sidebarAccountFlashError !== ''): ?>
      <div class="sidebar-account-flash error"><?php echo htmlspecialchars($sidebarAccountFlashError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="user-row user-row-clickable" id="sidebarAccountTrigger" role="button" tabindex="0" aria-label="จัดการบัญชีผู้ใช้" aria-haspopup="dialog" aria-controls="sidebarAccountModal" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="คลิกเพื่อเปลี่ยนชื่อผู้ใช้/รหัสผ่าน">
      <div class="avatar">
        <?php if (!empty($_SESSION['admin_picture'])): ?>
          <!-- Google avatar -->
          <img src="<?php echo htmlspecialchars($_SESSION['admin_picture'], ENT_QUOTES, 'UTF-8'); ?>" 
               alt="<?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?>" 
               style="width: 36px; height: 36px; border-radius: 6px; object-fit: cover;" 
               onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display: none;">
            <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="currentColor" />
            <path d="M2 20c0-3.314 2.686-6 6-6h8c3.314 0 6 2.686 6 6v1H2v-1z" fill="currentColor" />
          </svg>
        <?php else: ?>
          <!-- Default user svg icon -->
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="currentColor" />
            <path d="M2 20c0-3.314 2.686-6 6-6h8c3.314 0 6 2.686 6 6v1H2v-1z" fill="currentColor" />
          </svg>
        <?php endif; ?>
      </div>
      <div class="user-meta">
        <div class="name"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="email"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="edit-hint">คลิกเพื่อจัดการชื่อผู้ใช้ รหัสผ่าน และอีเมลกู้คืน</div>
      </div>
    </div>

    <div class="sidebar-account-modal-backdrop" id="sidebarAccountModal" <?php echo $sidebarAccountAutoOpen ? '' : 'hidden'; ?> data-auto-open="<?php echo $sidebarAccountAutoOpen ? '1' : '0'; ?>">
      <div class="sidebar-account-modal" role="dialog" aria-modal="true" aria-labelledby="sidebarAccountModalTitle">
        <div class="sidebar-account-modal-header">
          <h3 id="sidebarAccountModalTitle">จัดการบัญชีเข้าสู่ระบบ</h3>
          <button type="button" class="sidebar-account-modal-close" data-close-account-modal aria-label="ปิด">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6L18 18"></path>
              <path d="M18 6L6 18"></path>
            </svg>
          </button>
        </div>
        <div class="sidebar-account-modal-body">
          <form method="post" action="">
            <input type="hidden" name="sidebar_account_update" value="1">

            <label for="sidebarNewAdminUsername">ชื่อผู้ใช้ (Username)</label>
            <input id="sidebarNewAdminUsername" name="new_admin_username" type="text" value="<?php echo htmlspecialchars($sidebarAccountModalUsername, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="sidebarCurrentAdminPassword">รหัสผ่านปัจจุบัน (ต้องกรอกทุกครั้ง)</label>
            <input id="sidebarCurrentAdminPassword" name="current_admin_password" type="password" autocomplete="current-password" required>

            <label for="sidebarRecoveryEmail">อีเมลสำหรับกู้คืนรหัสผ่าน</label>
            <input id="sidebarRecoveryEmail" name="recovery_email" type="email" autocomplete="email" placeholder="example@email.com" value="<?php echo htmlspecialchars($sidebarAccountModalRecoveryEmail, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="sidebarNewAdminPassword">รหัสผ่านใหม่ (ไม่บังคับ)</label>
            <input id="sidebarNewAdminPassword" name="new_admin_password" type="password" autocomplete="new-password" placeholder="อย่างน้อย 6 ตัวอักษร">

            <label for="sidebarConfirmAdminPassword">ยืนยันรหัสผ่านใหม่</label>
            <input id="sidebarConfirmAdminPassword" name="confirm_admin_password" type="password" autocomplete="new-password" placeholder="กรอกอีกครั้งให้ตรงกัน">

            <div class="sidebar-account-actions">
              <button type="button" class="cancel-btn" data-close-account-modal>ยกเลิก</button>
              <button type="submit" class="save-btn">บันทึกข้อมูล</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Google Link/Unlink Button -->
    <div class="google-link-wrap">
      <?php if ($adminGoogleLinked): ?>
        <div class="google-linked-info">
          <svg class="google-icon" viewBox="0 0 24 24" width="14" height="14">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          <span class="google-email" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php echo htmlspecialchars($adminGoogleEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($adminGoogleEmail, ENT_QUOTES, 'UTF-8'); ?></span>
          <button type="button" class="google-unlink-btn" title="ถอนการเชื่อมต่อบัญชี Google" onclick="handleGoogleUnlink(event)" style="background: none; border: none; padding: 4px; cursor: pointer;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              <line x1="10" y1="11" x2="10" y2="17"></line>
              <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
          </button>
        </div>
      <?php else: ?>
        <a href="/dormitory_management/link_google.php?action=link" class="google-link-btn">
          <svg class="google-icon" viewBox="0 0 24 24" width="16" height="16">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          <span class="app-nav-label">เชื่อมบัญชี Google</span>
        </a>
      <?php endif; ?>
    </div>
    
    <div style="margin-top:0.4rem">
      <form action="../logout.php" method="post" data-allow-submit>
        <button type="submit" class="logout-btn" aria-label="Log out">
          <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
          <span class="app-nav-label">ออกจากระบบ</span>
        </button>
      </form>
    </div>

    <!-- Rail shown only when sidebar is collapsed: icon-only controls -->
    <div class="sidebar-rail">
      <div class="rail-user" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="<?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($_SESSION['admin_picture'])): ?>
          <!-- Google avatar for rail -->
          <img src="<?php echo htmlspecialchars($_SESSION['admin_picture'], ENT_QUOTES, 'UTF-8'); ?>" 
               alt="<?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?>" 
               style="width: 20px; height: 20px; border-radius: 4px; object-fit: cover;" 
               onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
          <span class="app-nav-icon" aria-hidden="true" style="display: none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="currentColor" />
              <path d="M2 20c0-3.314 2.686-6 6-6h8c3.314 0 6 2.686 6 6v1H2v-1z" fill="currentColor" />
            </svg>
          </span>
        <?php else: ?>
          <span class="app-nav-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="currentColor" />
              <path d="M2 20c0-3.314 2.686-6 6-6h8c3.314 0 6 2.686 6 6v1H2v-1z" fill="currentColor" />
            </svg>
          </span>
        <?php endif; ?>
      </div>
      <form action="../logout.php" method="post" class="rail-logout" data-allow-submit>
        <button type="submit" class="app-nav-icon" aria-label="Log out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button>
      </form>
    </div>
  </div>
</aside>

<script>
  // Fade-in animation on theme change/page load
  document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('theme-fade');
    setTimeout(() => document.body.classList.remove('theme-fade'), 500);
  });
</script>
<script>
// Apple-style Alert Function (global — must be outside IIFE so onclick handlers can access)
function appleAlert(message, title = 'project.3bbddns.com:36140 บอกว่า') {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'apple-alert-overlay';
    overlay.innerHTML = `
      <div class="apple-alert-dialog">
        <div class="apple-alert-content">
          <div class="apple-alert-title">${title}</div>
          <div class="apple-alert-message">${message}</div>
        </div>
        <div class="apple-alert-buttons">
          <button class="apple-alert-button primary">ตกลง</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    const button = overlay.querySelector('.apple-alert-button');
    button.addEventListener('click', () => {
      overlay.style.animation = 'fadeOut 0.2s ease forwards';
      setTimeout(() => { overlay.remove(); resolve(); }, 200);
    });
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.style.animation = 'fadeOut 0.2s ease forwards';
        setTimeout(() => { overlay.remove(); resolve(); }, 200);
      }
    });
  });
}

// Apple-style Confirm Function (global)
function appleConfirm(message, title = 'project.3bbddns.com:36140 บอกว่า') {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'apple-alert-overlay';
    overlay.innerHTML = `
      <div class="apple-alert-dialog">
        <div class="apple-alert-content">
          <div class="apple-alert-title">${title}</div>
          <div class="apple-alert-message">${message}</div>
        </div>
        <div class="apple-alert-buttons">
          <button class="apple-alert-button">ยกเลิก</button>
          <button class="apple-alert-button destructive">ตกลง</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    const buttons = overlay.querySelectorAll('.apple-alert-button');
    buttons[0].addEventListener('click', () => {
      overlay.style.animation = 'fadeOut 0.2s ease forwards';
      setTimeout(() => { overlay.remove(); resolve(false); }, 200);
    });
    buttons[1].addEventListener('click', () => {
      overlay.style.animation = 'fadeOut 0.2s ease forwards';
      setTimeout(() => { overlay.remove(); resolve(true); }, 200);
    });
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.style.animation = 'fadeOut 0.2s ease forwards';
        setTimeout(() => { overlay.remove(); resolve(false); }, 200);
      }
    });
  });
}

// AJAX สำหรับลบบัญชี Google (global — called from onclick)
async function handleGoogleUnlink(e) {
  if (e && e.preventDefault) { e.preventDefault(); e.stopPropagation(); }
  const unlinkBtn = (e && e.currentTarget) || document.querySelector('.google-unlink-btn');
  if (!unlinkBtn) return;
  console.log('\u2713 Unlink button clicked');
  const sidebar = document.querySelector('[role="complementary"]') || document.querySelector('.sidebar') || document.querySelector('#sidebar');
  if (sidebar) sidebar.classList.remove('collapsed');
  console.log('\u2713 Showing confirmation dialog');
  const confirmed = await appleConfirm('คุณต้องการถอนการเชื่อมต่อบัญชี Google นี้หรือไม่?');
  if (!confirmed) { console.log('\u2713 User cancelled unlink'); return; }
  console.log('\u2713 User confirmed, starting unlink process');
  try {
    unlinkBtn.style.opacity = '0.5';
    unlinkBtn.style.pointerEvents = 'none';
    const response = await fetch('/dormitory_management/unlink_google.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
    const result = await response.json();
    console.log('\u2713 Unlink result:', result);
    if (result.success) {
      if (sidebar) sidebar.classList.remove('collapsed');
      const avatarDiv = document.querySelector('.sidebar-footer .avatar');
      if (avatarDiv) { const img = avatarDiv.querySelector('img'); if (img) img.style.display='none'; const svg = avatarDiv.querySelector('svg'); if (svg) svg.style.display='block'; }
      const userRowAvatar = document.querySelector('.user-row .avatar');
      if (userRowAvatar) { const img = userRowAvatar.querySelector('img'); if (img) img.style.display='none'; const svg = userRowAvatar.querySelector('svg'); if (svg) svg.style.display='block'; }
      const railUser = document.querySelector('.rail-user');
      if (railUser) { const img = railUser.querySelector('img'); if (img) img.style.display='none'; const span = railUser.querySelector('span.app-nav-icon'); if (span) span.style.display='inline-block'; }
      const googleLinkWrap = unlinkBtn.closest('.google-link-wrap');
      if (googleLinkWrap) {
        googleLinkWrap.innerHTML = `
          <a href="/dormitory_management/link_google.php?action=link" class="google-link-btn">
            <svg class="google-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            <span class="app-nav-label">เชื่อมบัญชี Google</span>
          </a>
        `;
        if (window.AnimateUI && typeof window.AnimateUI.showNotification === 'function') {
          window.AnimateUI.showNotification('ถอนการเชื่อมต่อบัญชี Google สำเร็จ', 'success');
        }
      }
    } else {
      console.error('\u2717 Unlink failed:', result.message);
      if (window.AnimateUI && typeof window.AnimateUI.showNotification === 'function') {
        window.AnimateUI.showNotification(result.message, 'error');
      } else {
        await appleAlert('เกิดข้อผิดพลาด: ' + result.message);
      }
      unlinkBtn.style.opacity = '1';
      unlinkBtn.style.pointerEvents = 'auto';
    }
  } catch (error) {
    console.error('\u2717 Exception during unlink:', error);
    await appleAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
  }
}
</script>
<script>
(function() {
  const sidebar = document.querySelector('.app-sidebar');
  let isFreshLoginSession = false;

  // First page load after a new login session: close all dropdowns.
  try {
    const currentLoginSession = <?php echo json_encode((string)session_id()); ?>;
    const loginSessionKey = 'sidebar_login_session_id';
    const savedLoginSession = localStorage.getItem(loginSessionKey);
    if (savedLoginSession !== currentLoginSession) {
      isFreshLoginSession = true;
      localStorage.setItem(loginSessionKey, currentLoginSession);
    }
  } catch (e) {}

  window.__sidebarFreshLogin = isFreshLoginSession;
  
  // Restore sidebar state on page load (desktop only)
  // Note: Sidebar toggle handler is now managed by animate-ui.js
  const savedState = localStorage.getItem('sidebarCollapsed');
  if (savedState === 'true' && window.innerWidth > 1024) {
    sidebar.classList.add('collapsed');
    console.log('Sidebar state restored from localStorage');
  }
  
  // Set active menu item based on current page
  function setActiveMenu() {
    const currentPage = (window.location.pathname.split('/').pop() || '').split('?')[0];
    const menuLinks = document.querySelectorAll('.app-nav a');
    
    menuLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (!href) return;

      const normalizedHref = href.split('#')[0];
      const hrefFile = (normalizedHref.split('/').pop() || '').split('?')[0];
      if (hrefFile && hrefFile === currentPage) {
        link.classList.add('active');

        // Skip auto-open on the very first page after login.
        // Also skip auto-open when active link is a summary-link itself.
        const parentDetails = link.closest('details[id]');
        if (link.classList.contains('summary-link')) {
          if (parentDetails) {
            parentDetails.removeAttribute('open');
            parentDetails.open = false;
            try {
              localStorage.setItem('sidebar_details_' + parentDetails.id, 'closed');
            } catch (e) {}
          }
        } else if (!window.__sidebarFreshLogin) {
          if (parentDetails) {
            parentDetails.open = true;
            try {
              localStorage.setItem('sidebar_details_' + parentDetails.id, 'open');
            } catch (e) {}
          }
        }
      }
    });
  }
  
  // Run on page load
  setActiveMenu();

  // Ensure summary links navigate (แดชบอร์ด/จัดการ)
  document.querySelectorAll('summary .summary-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.stopPropagation(); // ป้องกันไม่ให้ toggle dropdown

      // คลิกเมนูหลักของ dropdown นี้ ให้จำสถานะเป็นปิดอัตโนมัติ
      const parentDetails = link.closest('details[id]');
      if (parentDetails) {
        parentDetails.removeAttribute('open');
        parentDetails.open = false;
        try {
          localStorage.setItem('sidebar_details_' + parentDetails.id, 'closed');
        } catch (err) {}
      }

      // ให้ลิงก์ทำงานทันที
      window.location.href = link.getAttribute('href');
    });
  });
  
  // Close sidebar when clicking overlay
  const toggleBtn = document.getElementById('sidebar-toggle');
  document.addEventListener('click', function(e) {
      if (window.innerWidth <= 1024 && 
        sidebar.classList.contains('mobile-open') && 
        !sidebar.contains(e.target) &&
        e.target !== toggleBtn) {
      sidebar.classList.remove('mobile-open');
      document.body.classList.remove('sidebar-open');
    }
  });
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
      sidebar.classList.remove('mobile-open');
      document.body.classList.remove('sidebar-open');
    } else {
      // On mobile, remove collapsed state
      sidebar.classList.remove('collapsed');
    }
  });
})();

(function() {
  const trigger = document.getElementById('sidebarAccountTrigger');
  const modal = document.getElementById('sidebarAccountModal');
  if (!trigger || !modal) {
    return;
  }

  const firstInput = modal.querySelector('input[name="new_admin_username"]');
  const closeButtons = modal.querySelectorAll('[data-close-account-modal]');

  function openModal() {
    modal.hidden = false;
    document.body.classList.add('sidebar-account-modal-open');
    if (firstInput) {
      setTimeout(function() { firstInput.focus(); firstInput.select(); }, 0);
    }
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('sidebar-account-modal-open');
  }

  trigger.addEventListener('click', function(e) {
    // ❌ ไม่เปิด modal ถ้าคลิกปุ่ม unlink/link Google หรือ logout
    if (e.target.closest('.google-unlink-btn') ||
        e.target.closest('.google-link-btn') ||
        e.target.closest('.google-link-wrap') ||
        e.target.closest('.logout-btn')) {
      return;
    }
    openModal();
  });
  trigger.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openModal();
    }
  });

  closeButtons.forEach(function(btn) {
    btn.addEventListener('click', closeModal);
  });

  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });

  if (modal.dataset.autoOpen === '1') {
    openModal();
  }
})();

// Save and restore collapsible details state
(function() {
  let isInitializing = true;
  const shouldCloseAllOnLogin = !!window.__sidebarFreshLogin;
  
  // Function to restore state - ทำงาน FORCE เพื่อ override ทุกอย่าง
  function restoreDetailsState() {
    if (shouldCloseAllOnLogin) {
      document.querySelectorAll('details[id]').forEach(function(details) {
        details.removeAttribute('open');
        details.open = false;
        try {
          localStorage.setItem('sidebar_details_' + details.id, 'closed');
        } catch (e) {}
      });

      setTimeout(function() {
        isInitializing = false;
      }, 100);
      return;
    }

    document.querySelectorAll('details[id]').forEach(function(details) {
      const id = details.id;
      if (id) {
        const key = 'sidebar_details_' + id;
        const savedState = localStorage.getItem(key);
        
        // ใช้สถานะที่บันทึกไว้เสมอ ถ้ามี
        if (savedState === 'closed') {
          // ปิด dropdown - FORCE
          details.removeAttribute('open');
          details.open = false;
        } else if (savedState === 'open') {
          // เปิด dropdown - FORCE
          details.setAttribute('open', '');
          details.open = true;
        }
        // ถ้าไม่มีการบันทึก ใช้สถานะเริ่มต้นจาก HTML (ครั้งแรก)
      }
    });

    // Auto-open only the group for current page, and close all unrelated groups.
    const currentPage = (window.location.pathname.split('/').pop() || '').split('?')[0];
    const activeDetailIds = new Set();

    document.querySelectorAll('.app-nav a[href]').forEach(function(link) {
      const href = link.getAttribute('href');
      if (!href) return;
      const hrefFile = (href.split('#')[0].split('/').pop() || '').split('?')[0];
      if (hrefFile && hrefFile === currentPage) {
        link.classList.add('active');
        const parentDetails = link.closest('details[id]');
        if (!parentDetails) return;

        if (link.classList.contains('summary-link')) {
          parentDetails.removeAttribute('open');
          parentDetails.open = false;
          try {
            localStorage.setItem('sidebar_details_' + parentDetails.id, 'closed');
          } catch (e) {}
          return;
        }

        activeDetailIds.add(parentDetails.id);
        parentDetails.open = true;
        try {
          localStorage.setItem('sidebar_details_' + parentDetails.id, 'open');
        } catch (e) {}
      }
    });

    document.querySelectorAll('details[id]').forEach(function(details) {
      if (!activeDetailIds.has(details.id)) {
        details.removeAttribute('open');
        details.open = false;
        try {
          localStorage.setItem('sidebar_details_' + details.id, 'closed');
        } catch (e) {}
      }
    });
    
    // หลังจาก restore เสร็จ ให้เริ่มบันทึกการเปลี่ยนแปลง
    setTimeout(function() {
      isInitializing = false;
    }, 100);
  }
  
  // Save collapsible state on toggle
  document.addEventListener('toggle', function(e) {
    if (e.target.tagName === 'DETAILS' && e.target.id && !isInitializing) {
      const key = 'sidebar_details_' + e.target.id;
      const newState = e.target.open ? 'open' : 'closed';
      localStorage.setItem(key, newState);
      console.log('Saved:', key, '=', newState);
    }
  }, true);
  
  // Restore state when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(restoreDetailsState, 50);
    });
  } else {
    // ทำงานครั้งเดียวก็พอ เพื่อลดงานซ้ำตอนเปลี่ยนหน้า
    restoreDetailsState();
  }
})();

// Chevron toggle for dropdowns with animation (separate from link navigation)
(function() {
  document.addEventListener('click', function(e) {
    const chev = e.target.closest('.chev, .chev-toggle');
    if (!chev) return;

    e.preventDefault();
    e.stopPropagation();

    const details = chev.closest('details');
    if (!details) return;

    const isOpening = !details.open;
    
    if (isOpening) {
      // Opening: set open then trigger animation
      details.open = true;
      
      // Force reflow to trigger animation
      void details.offsetHeight;
      
      const items = details.querySelectorAll(':scope > a');
      items.forEach((item, index) => {
        item.style.animation = 'none';
        void item.offsetHeight;
        item.style.animation = '';
        item.style.animationDelay = (0.05 * (index + 1)) + 's';
      });
    } else {
      // Closing: animate out then close
      const items = details.querySelectorAll(':scope > a');
      
      items.forEach((item, index) => {
        item.style.animation = 'slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards';
        item.style.animationDelay = (0.03 * (items.length - index - 1)) + 's';
      });
      
      // Close after animation completes
      setTimeout(() => {
        details.open = false;
      }, 300 + (items.length * 30));
    }

    const key = 'sidebar_details_' + details.id;
    localStorage.setItem(key, isOpening ? 'open' : 'closed');
  });
})();

// Legacy sidebar toggle - only runs if sidebar_toggle.php is not loaded
// This provides backward compatibility for pages that don't include sidebar_toggle.php
(function() {
  // Skip if new toggle system is already loaded
  if (window.__sidebarToggleReady) {
    console.debug('New sidebar toggle system loaded, skipping legacy handler');
    return;
  }
  
  function initLegacySidebarToggle() {
    const sidebar = document.querySelector('.app-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    
    if (!toggleBtn) {
      setTimeout(initLegacySidebarToggle, 50);
      return;
    }
    
    if (!sidebar) return;
    
    // Skip if already handled
    if (window.__sidebarToggleHandled) {
      return;
    }

    // Mark as handled by legacy system (prevents duplicate binds)
    window.__sidebarToggleHandled = true;
    
    // Load saved state (desktop only)
    if (window.innerWidth > 1024) {
      try {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
          sidebar.classList.add('collapsed');
        }
      } catch(e) {}
    }
    
    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      if (window.__sidebarToggleReady) return;
      
      if (window.innerWidth > 1024) {
        sidebar.classList.toggle('collapsed');
        try {
          localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        } catch(e) {}
      } else {
        var isOpen = sidebar.classList.toggle('mobile-open');
        document.body.classList.toggle('sidebar-open', isOpen);
      }
    });
    
    // Close on outside click (mobile)
    document.addEventListener('click', function(e) {
      if (window.innerWidth <= 1024 && sidebar.classList.contains('mobile-open')) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove('mobile-open');
          document.body.classList.remove('sidebar-open');
        }
      }
    });
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLegacySidebarToggle);
  } else {
    initLegacySidebarToggle();
  }
})();

// Global function สำหรับปิด sidebar บนมือถือ (เรียกจากปุ่ม X)
function closeSidebarMobile() {
  const sidebar = document.querySelector('.app-sidebar');
  if (sidebar) {
    sidebar.classList.remove('mobile-open');
    document.body.classList.remove('sidebar-open');
  }
}

// ปิด sidebar ด้วย ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeSidebarMobile();
    // ปิด alert dialog ถ้ามี
    const alertOverlay = document.querySelector('.apple-alert-overlay');
    if (alertOverlay) {
      alertOverlay.remove();
    }
  }
});

// ===============================================
// Google Link Button Handler - Open in popup (with event delegation)
// ===============================================
// ✅ ใช้ event delegation เพื่อให้ใช้ได้กับปุ่มที่สร้างใหม่
(function() {
  document.addEventListener('click', async (e) => {
    const linkBtn = e.target.closest('.google-link-btn');
    if (!linkBtn || !linkBtn.href.includes('link_google.php')) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    const width = 600;
    const height = 700;
    const left = window.outerWidth / 2 - width / 2;
    const top = window.outerHeight / 2 - height / 2;
    
    // เปิด popup สำหรับ Google OAuth
    const popup = window.open(
      linkBtn.href,
      'GoogleLinkPopup',
      `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
    );
    
    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
      await appleAlert('โปรแกรมบล็อก popup โปรดอนุญาตให้เปิด popup');
      return;
    }
    
    popup.focus();
    
    // ✅ ตัวข้อความมาจาก google_callback.php เมื่อ OAuth สำเร็จ หรือ เมื่อเกิดข้อผิดพลาด
    const messageHandler = async (event) => {
      if (!event.data || (!event.data.type)) return;
      
      // ✅ กรณี OAuth สำเร็จ
      if (event.data.type === 'google_link_success') {
        clearInterval(checkClosedInterval);
        window.removeEventListener('message', messageHandler);
        
        // รอสักครู่เพื่อให้ popup ปิดสมบูรณ์
        await new Promise(resolve => setTimeout(resolve, 500));
        
        try {
          // ตรวจสอบสถานะการเชื่อมผ่าน API
          const response = await fetch('/dormitory_management/api/check_google_link.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
          });
          
          const result = await response.json();
          
          if (result.success && result.linked) {
            // ✅ Google ถูกเชื่อมสำเร็จ
            window.location.reload();
          }
        } catch (error) {
          console.error('Error checking Google link:', error);
        }
      }
      
      // ❌ กรณี OAuth มีข้อผิดพลาด
      if (event.data.type === 'google_link_error') {
        clearInterval(checkClosedInterval);
        window.removeEventListener('message', messageHandler);
        
        if (window.AnimateUI && typeof window.AnimateUI.showNotification === 'function') {
          window.AnimateUI.showNotification(event.data.message || 'เกิดข้อผิดพลาดในการเชื่อมบัญชี Google', 'error');
        } else {
          await appleAlert('เกิดข้อผิดพลาด: ' + (event.data.message || 'ไม่ทราบสาเหตุ'));
        }
      }
    };
    
    window.addEventListener('message', messageHandler);
    
    // ตรวจสอบทุก 500ms ว่า popup ปิดแล้วหรือยัง (fallback)
    let checkClosedInterval = setInterval(async () => {
      if (popup.closed) {
        clearInterval(checkClosedInterval);
        window.removeEventListener('message', messageHandler);
        await new Promise(resolve => setTimeout(resolve, 1000));
        try {
          const response = await fetch('/dormitory_management/api/check_google_link.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
          });
          const result = await response.json();
          if (result.success) {
            if (result.linked) {
              window.location.reload();
            } else {
              console.log('User cancelled Google linking');
            }
          } else {
            console.error('Error checking Google link:', result.message);
          }
        } catch (error) {
          console.error('Error checking Google link status:', error);
        }
      }
    }, 500);
  });
})();
</script>
