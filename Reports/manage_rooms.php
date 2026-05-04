<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/room_price_migration.php';
$pdo = connectDB();
ensureRoomPriceColumn($pdo);

// ซิงก์สถานะห้องอัตโนมัติจากสัญญาและการจอง
// room_status: 0 = ว่าง, 1 = ไม่ว่าง (มีจอง หรือ มีสัญญา)
try {
  // ตั้งค่าห้องทั้งหมดเป็นว่าง (0) ก่อน
  $pdo->exec("UPDATE room SET room_status = '0'");
  
  // NOTE: ห้องจะเป็น "ไม่ว่าง" (1) หากมีสัญญาเช่า active หรือมีการจองที่ยังไม่ได้ยกเลิก
  // ข้ามการจองที่มีสัญญาเดิมสำหรับผู้เช่ารายนั้นในห้องเดียวกัน
  $pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (
    SELECT 1 FROM contract c
    LEFT JOIN termination t ON t.ctr_id = c.ctr_id
    WHERE c.room_id = room.room_id
      AND (
        c.ctr_status = '0'
        OR (c.ctr_status = '2' AND (t.term_date IS NULL OR t.term_date >= CURDATE()))
      )
  ) OR EXISTS (
    SELECT 1 FROM booking b
    WHERE b.room_id = room.room_id AND b.bkg_status = '1'
      AND NOT EXISTS (
        SELECT 1 FROM contract c2
        WHERE c2.room_id = b.room_id AND c2.tnt_id = b.tnt_id
      )
  )");
} catch (PDOException $e) {
  // ถ้าซิงก์ไม่สำเร็จ ให้ไปต่อแต่แสดงสถานะตามข้อมูลเดิม
}


// หากมีการส่งฟอร์มเพิ่มห้องจากหน้านี้ ให้บันทึกลงฐานข้อมูล (สถานะกำหนดอัตโนมัติ)
// หมายเหตุ: เปลี่ยนเป็นใช้ AJAX แล้ว ดูใน Manage/add_room.php


// รับค่า sort จาก query parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'ORDER BY r.room_id DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'ORDER BY r.room_id ASC';
    break;
  case 'room_number':
    $orderBy = 'ORDER BY CAST(r.room_number AS UNSIGNED) ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'ORDER BY r.room_id DESC';
}

// ดึงข้อมูลห้องพัก
$stmt = $pdo->query("
    SELECT r.room_id, r.room_number, r.room_status, r.room_image, r.type_id, r.room_features,
           r.room_price, rt.type_name, rt.type_price,
           COALESCE(NULLIF(r.room_price, 0), rt.type_price) AS display_price,
           CASE
             WHEN r.room_price IS NOT NULL AND r.room_price > 0 AND r.room_price != rt.type_price THEN 1
             ELSE 0
           END AS has_custom_price
    FROM room r
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    $orderBy
");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลเหตุผลที่ห้องไม่ว่าง (มีการเช่าหรือการจอง)
$occupationReasons = [];
try {
  // ดึงข้อมูลจากสัญญา (เช่า)
  $contractStmt = $pdo->query("
    SELECT DISTINCT c.room_id, CONCAT('มีการเช่าอยู่') as reason
    FROM contract c
    WHERE c.ctr_status IN ('0','2')
  ");
  $contractRooms = $contractStmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($contractRooms as $row) {
    $occupationReasons[$row['room_id']] = $row['reason'];
  }
  
  // ดึงข้อมูลจากการจอง (หากห้องยังไม่มีในรายการเช่า)
  $bookingStmt = $pdo->query("
    SELECT DISTINCT b.room_id, CONCAT('มีการจองอยู่') as reason
    FROM booking b
    WHERE b.bkg_status IN ('1','2')
  ");
  $bookingRooms = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($bookingRooms as $row) {
    if (!isset($occupationReasons[$row['room_id']])) {
      $occupationReasons[$row['room_id']] = $row['reason'];
    }
  }
} catch (PDOException $e) {
  // ถ้าดึงไม่สำเร็จ ให้ไปต่อโดยไม่มีเหตุผล
}

// เพิ่มเหตุผลเข้าไปในข้อมูลห้อง
foreach ($rooms as &$room) {
  $room['occupation_reason'] = $occupationReasons[$room['room_id']] ?? null;
}
unset($room);

// หาหมายเลขห้องถัดไป (padding 2 หลัก)
$maxRoomNumber = 0;
foreach ($rooms as $room) {
  $num = (int)($room['room_number'] ?? 0);
  if ($num > $maxRoomNumber) {
    $maxRoomNumber = $num;
  }
}
$nextRoomNumber = str_pad((string)($maxRoomNumber + 1), 2, '0', STR_PAD_LEFT);

// ดึงประเภทห้อง
$stmt = $pdo->query("SELECT type_id, type_name, type_price FROM roomtype ORDER BY type_name ASC");
$roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$defaultTypeId = $roomTypes[0]['type_id'] ?? '';

// นับสถานะห้อง
$vacant = 0;
$occupied = 0;
foreach ($rooms as $room) {
  if ($room['room_status'] === '0') {
    $vacant++;
  } else {
    $occupied++;
  }
}

$totalRooms = count($rooms);

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการห้องพัก</title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />
    <style>
      /* Apple UI System - Complete Implementation per ui-standard.md */
      :root {
        /* Light Mode */
        --font-apple: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", sans-serif;
        --bg-primary: #FFFFFF;
        --bg-secondary: #F2F2F7;
        --text-primary: #000000;
        --text-secondary: rgba(60, 60, 67, 0.6);
        --text-tertiary: rgba(60, 60, 67, 0.3);
        --system-blue: #007AFF;
        --system-red: #FF3B30;
        --system-green: #34C759;
        --separator: rgba(60, 60, 67, 0.3);
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.05);
        --shadow-subtle: 0 2px 8px rgba(0, 0, 0, 0.06);
      }
      
      @media (prefers-color-scheme: dark) {
        :root {
          --bg-primary: #000000;
          --bg-secondary: #1C1C1E;
          --text-primary: #FFFFFF;
          --text-secondary: rgba(235, 235, 245, 0.6);
          --text-tertiary: rgba(235, 235, 245, 0.3);
          --system-blue: #0A84FF;
          --system-red: #FF453A;
          --system-green: #30B0C0;
          --separator: rgba(84, 84, 88, 0.3);
          --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
          --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.3);
          --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.2);
          --shadow-subtle: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
      }
      
      /* Typography Foundation */
      * {
        font-family: var(--font-apple);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }
      
      /* ===== Apple-Style Modern Design ===== */
      
      /* Stats Cards - Glassmorphism */
      .rooms-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
        margin-top: 1rem;
      }
      
      .room-stat-card {
        background: rgba(255,255,255,0.03);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(255,255,255,0.08);
        position: relative;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      
      .room-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        opacity: 0;
        transition: opacity 0.3s;
      }
      
      .room-stat-card:hover {
        transform: translateY(-4px);
        border-color: rgba(255,255,255,0.15);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
      }
      
      .room-stat-card:hover::before {
        opacity: 1;
      }
      
      .room-stat-card h3 {
        margin: 0;
        font-size: 0.85rem;
        font-weight: 500;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }
      
      .room-stat-card .stat-value {
        font-size: 3rem;
        font-weight: 700;
        margin-top: 0.5rem;
        background: linear-gradient(135deg, #60a5fa, #a78bfa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1.1;
      }
      
      .room-stat-card:nth-child(2) .stat-value {
        background: linear-gradient(135deg, #34d399, #10b981);
        -webkit-background-clip: text;
        background-clip: text;
      }
      
      .room-stat-card:nth-child(3) .stat-value {
        background: linear-gradient(135deg, #f87171, #ef4444);
        -webkit-background-clip: text;
        background-clip: text;
      }
      
      /* Room Cards Grid */
      .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.25rem;
        margin-top: 1.25rem;
      }
      
      .room-card {
        background: rgba(255,255,255,0.02);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        cursor: pointer;
        position: relative;
      }
      
      .room-card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 20px;
        padding: 1px;
        background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s;
      }
      
      .room-card:hover {
        transform: translateY(-6px) scale(1.01);
        border-color: rgba(96,165,250,0.3);
        box-shadow: 0 25px 50px rgba(0,0,0,0.25), 0 0 0 1px rgba(96,165,250,0.1);
      }
      
      .room-card:hover::after {
        opacity: 1;
      }
      
      .room-card-image {
        width: 100%;
        height: 160px;
        background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.9));
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,0.2);
        font-size: 3.5rem;
        overflow: hidden;
        position: relative;
      }
      
      .room-card-image::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, transparent 60%, rgba(0,0,0,0.6));
        z-index: 1;
      }
      
      .room-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      
      .room-card:hover .room-card-image img {
        transform: scale(1.08);
      }

      /* Image Upload Overlay */
      .room-card-image-upload {
        transition: all 0.3s ease;
      }

      .image-upload-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        z-index: 2;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
      }

      .room-card-image-upload:hover .image-upload-overlay {
        opacity: 1;
      }

      .upload-icon {
        color: #ffffff;
        animation: uploadBounce 0.8s ease-in-out infinite;
      }

      .upload-text {
        color: #ffffff;
        font-size: 0.85rem;
        font-weight: 600;
        text-align: center;
      }

      .placeholder-upload-hint {
        color: #94a3b8;
      }

      .placeholder-upload-icon,
      .placeholder-upload-text {
        color: inherit;
      }

      .room-card-image-upload:hover .upload-icon,
      .room-card-image-upload:hover .upload-text,
      .room-card-image-upload:hover .placeholder-upload-icon,
      .room-card-image-upload:hover .placeholder-upload-text,
      .room-card-image-upload:hover .placeholder-upload-icon * {
        color: #ffffff !important;
        stroke: #ffffff !important;
      }

      @keyframes uploadBounce {
        0%, 100% {
          transform: translateY(0);
        }
        50% {
          transform: translateY(-4px);
        }
      }
      
      .room-card-content {
        padding: 1.25rem;
        position: relative;
      }
      
      .room-card-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #f8fafc;
        margin: 0 0 0.25rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .room-card-meta {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.5);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .room-card-meta svg {
        width: 14px;
        height: 14px;
        opacity: 0.6;
      }
      
      .room-card-status {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.9rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
      }
      
      .room-card-status.vacant {
        background: rgba(34,197,94,0.15);
        color: #4ade80;
        border: 1px solid rgba(34,197,94,0.2);
      }
      
      .room-card-status.occupied {
        background: rgba(239,68,68,0.15);
        color: #f87171;
        border: 1px solid rgba(239,68,68,0.2);
      }
      
      .room-card-status svg {
        width: 12px;
        height: 12px;
      }
      
      .room-card-actions {
        display: flex;
        gap: 0.5rem;
        position: relative;
        z-index: 10;
        pointer-events: auto;
      }
      
      .room-card-actions .btn {
        flex: 1;
        padding: 0.6rem 1rem;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
      }
      
      .room-card-actions .btn-edit,
      body.live-light .room-card-actions .btn-edit,
      html.light-theme .room-card-actions .btn-edit {
        background: var(--system-blue) !important;
        border: 1px solid var(--system-blue) !important;
        color: #FFFFFF !important;
      }

      .room-card-actions .btn-edit svg,
      body.live-light .room-card-actions .btn-edit svg,
      html.light-theme .room-card-actions .btn-edit svg {
        color: #FFFFFF !important;
        stroke: currentColor;
      }
      
      .room-card-actions .btn-edit:hover,
      body.live-light .room-card-actions .btn-edit:hover,
      html.light-theme .room-card-actions .btn-edit:hover {
        background: #0068CC !important;
        border-color: #0068CC !important;
        color: #FFFFFF !important;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 122, 255, 0.35);
      }
      
      .room-card-actions .btn-delete,
      body.live-light .room-card-actions .btn-delete,
      html.light-theme .room-card-actions .btn-delete {
        background: var(--system-red) !important;
        color: #FFFFFF !important;
        border: 1px solid var(--system-red) !important;
      }
      
      .room-card-actions .btn-delete svg,
      body.live-light .room-card-actions .btn-delete svg,
      html.light-theme .room-card-actions .btn-delete svg {
        color: #FFFFFF !important;
        stroke: currentColor;
      }
      
      .room-card-actions .btn-delete:hover,
      body.live-light .room-card-actions .btn-delete:hover,
      html.light-theme .room-card-actions .btn-delete:hover {
        background: #E63C34 !important;
        border-color: #E63C34 !important;
        color: #FFFFFF !important;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 59, 48, 0.35);
      }

      /* Keep action button colors stable even if global button styles load later */
      .rooms-grid .room-card-actions button.btn.btn-edit,
      .rooms-table .room-card-actions button.btn.btn-edit {
        background: var(--system-blue) !important;
        border: 1px solid var(--system-blue) !important;
        color: #FFFFFF !important;
      }

      .rooms-grid .room-card-actions button.btn.btn-delete,
      .rooms-table .room-card-actions button.btn.btn-delete {
        background: var(--system-red) !important;
        border: 1px solid var(--system-red) !important;
        color: #FFFFFF !important;
      }

      .rooms-grid .room-card-actions button.btn.btn-edit svg,
      .rooms-grid .room-card-actions button.btn.btn-delete svg,
      .rooms-table .room-card-actions button.btn.btn-edit svg,
      .rooms-table .room-card-actions button.btn.btn-delete svg {
        stroke: currentColor !important;
        color: currentColor !important;
      }
      
      /* Empty State */
      .room-empty {
        text-align: center;
        padding: 4rem 2rem;
        color: rgba(255,255,255,0.4);
      }
      
      .room-empty-icon {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
      }
      
      /* Form Styles */
      .room-form {
        display: grid;
        gap: 1.25rem;
        margin-top: 1.5rem;
      }
      
      .room-form-group label {
        color: rgba(255,255,255,0.7);
        font-weight: 600;
        font-size: 0.9rem;
        display: block;
        margin-bottom: 0.5rem;
        position: relative;
      }
      .room-form-group label span[style*="red"] {
        color: var(--system-red);
        margin-left: 4px;
        font-weight: 600;
      }
      
      .room-form-group input,
      .room-form-group select {
        width: 100%;
        padding: 0.9rem 1rem;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(0,0,0,0.3);
        color: #f5f8ff;
        font-family: inherit;
        font-size: 1rem;
        transition: all 0.25s ease;
      }
      
      .room-form-group input:focus,
      .room-form-group select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
        background: rgba(0,0,0,0.4);
      }
      .room-form-group input:invalid,
      .room-form-group select:invalid {
        border-color: var(--system-red);
        background: rgba(255, 59, 48, 0.08);
      }
      .room-form-group input:invalid:focus,
      .room-form-group select:invalid:focus {
        border-color: var(--system-red);
        box-shadow: 0 0 0 4px rgba(255, 59, 48, 0.15);
        background: rgba(255, 59, 48, 0.1);
      }

      /* Form Error & Hint Messages per ui-standard.md */
      .form-error-message {
        display: none;
        color: var(--system-red);
        font-size: 13px;
        margin-top: 6px;
        font-weight: 500;
        line-height: 1.4;
      }
      .room-form-group.has-error .form-error-message {
        display: block;
      }
      .booking-form-group.has-error .form-error-message {
        display: block;
      }
      .form-hint {
        color: var(--text-secondary);
        font-size: 13px;
        margin-top: 4px;
        line-height: 1.4;
        font-weight: 400;
      }
      
      .add-type-row { display:flex; align-items:center; gap:0.5rem; }
      
      .add-type-btn {
        padding: 0.9rem 1.1rem;
        border-radius: 11px;
        border: 1px dashed rgba(0, 122, 255, 0.4);
        background: rgba(0, 122, 255, 0.08);
        color: var(--system-blue);
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.25s ease;
      }
      
      .add-type-btn:hover {
        background: rgba(0, 122, 255, 0.15);
        border-color: rgba(0, 122, 255, 0.6);
        transform: translateY(-1px);
      }
      
      .delete-type-btn {
        border-color: rgba(255, 59, 48, 0.4);
        color: var(--system-red);
        background: rgba(255, 59, 48, 0.08);
      }
      
      .delete-type-btn:hover {
        background: rgba(255, 59, 48, 0.15);
        border-color: rgba(255, 59, 48, 0.6);
      }
      
      .room-form-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      #toggleRoomFormBtn {
        max-width: 100%;
      }

      .room-form-split,
      .booking-form-split {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
      }

      .room-list-toolbar {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
      }
      
      /* Modal Styles - Apple Sheet */
      .booking-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        padding: 1rem;
      }
      
      .booking-modal-content {
        background: rgba(20,25,35,0.95);
        backdrop-filter: blur(40px);
        -webkit-backdrop-filter: blur(40px);
        border-radius: 24px;
        padding: 2rem;
        width: 100%;
        max-width: 480px;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 25px 80px rgba(0,0,0,0.5);
        animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      }
      
      @keyframes modalSlideUp {
        from {
          opacity: 0;
          transform: translateY(30px) scale(0.97);
        }
        to {
          opacity: 1;
          transform: translateY(0) scale(1);
        }
      }
      
      .booking-modal-content h2 {
        margin: 0 0 1.5rem 0;
        color: #f8fafc;
        font-size: 1.5rem;
        font-weight: 700;
      }
      
      .booking-form-group {
        margin-bottom: 1.25rem;
      }
      
      .booking-form-group label {
        display: block;
        color: rgba(255,255,255,0.7);
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
        position: relative;
      }
      .booking-form-group label span[style*="red"] {
        color: var(--system-red);
        margin-left: 4px;
        font-weight: 600;
      }
      
      .booking-form-group input,
      .booking-form-group select,
      .booking-form-group textarea {
        width: 100%;
        padding: 0.9rem 1rem;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(0,0,0,0.3);
        color: #f5f8ff;
        font-family: inherit;
        font-size: 1rem;
        transition: all 0.25s ease;
      }
      
      .booking-form-group input:focus,
      .booking-form-group select:focus,
      .booking-form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
      }
      .booking-form-group input:invalid,
      .booking-form-group select:invalid,
      .booking-form-group textarea:invalid {
        border-color: var(--system-red);
        background: rgba(255, 59, 48, 0.08);
      }
      .booking-form-group input:invalid:focus,
      .booking-form-group select:invalid:focus,
      .booking-form-group textarea:invalid:focus {
        border-color: var(--system-red);
        box-shadow: 0 0 0 4px rgba(255, 59, 48, 0.15);
        background: rgba(255, 59, 48, 0.1);
      }
      
      .booking-form-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.75rem;
        position: sticky;
        bottom: 0;
        z-index: 2;
        margin-left: -2rem;
        margin-right: -2rem;
        padding: 1rem 2rem;
        background: linear-gradient(180deg, rgba(20, 25, 35, 0) 0%, rgba(20, 25, 35, 0.85) 35%, rgba(20, 25, 35, 0.98) 100%);
        border-top: 1px solid rgba(255, 255, 255, 0.08);
      }
      
      .btn-submit {
        flex: 1;
        padding: 1rem 1.5rem;
        background: var(--system-blue);
        color: white;
        border: none;
        border-radius: 11px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      
      .btn-submit:hover {
        background: #0068CC;
        box-shadow: 0 10px 30px rgba(0, 122, 255, 0.4);
        transform: translateY(-2px);
      }
      
      .btn-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      
      .btn-cancel {
        flex: 1;
        padding: 1rem 1.5rem;
        background: rgba(0, 122, 255, 0.1);
        color: var(--system-blue);
        border: 1.5px solid var(--system-blue);
        border-radius: 11px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      
      .btn-cancel:hover {
        background: rgba(0, 122, 255, 0.15);
        border-color: var(--system-blue);
      }
      
      .btn-cancel:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      
      /* View Toggle */
      .view-toggle-btn {
        padding: 0.7rem 1.3rem;
        background: rgba(0, 122, 255, 0.1);
        color: var(--system-blue);
        border: 1px solid rgba(0, 122, 255, 0.3);
        border-radius: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .view-toggle-btn:hover {
        background: rgba(0, 122, 255, 0.15);
        color: var(--system-blue);
        border-color: rgba(0, 122, 255, 0.5);
      }
      
      .view-toggle-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      
      /* Table View */
      .rooms-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
      }
      
      .rooms-table thead {
        background: rgba(255,255,255,0.03);
      }
      
      .rooms-table th {
        padding: 1rem 1.25rem;
        text-align: left;
        color: rgba(255,255,255,0.5);
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid rgba(255,255,255,0.06);
      }
      
      .rooms-table td {
        padding: 1rem 1.25rem;
        color: #e2e8f0;
        border-bottom: 1px solid rgba(255,255,255,0.04);
        font-size: 0.95rem;
      }
      
      .rooms-table tbody tr {
        transition: all 0.2s ease;
      }
      
      .rooms-table tbody tr:hover {
        background: rgba(255,255,255,0.03);
      }
      
      .rooms-table tbody tr.hidden-row {
        display: none;
      }
      
      .room-image-small {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        object-fit: cover;
        background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.9));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        overflow: hidden;
      }
      
      .room-image-small img {
        width: 100%;
        height: 100%;
        border-radius: 10px;
        object-fit: cover;
      }
      
      .reports-page .manage-panel {
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
        background: rgba(15,23,42,0.6);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 20px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
      }
      
      .reports-page .manage-panel:first-of-type {
        margin-top: 0.2rem;
      }
      
      .rooms-table .room-card-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-start;
      }
      
      .rooms-table-view { display: none; }
      .rooms-grid-view { display: grid; }

      .rooms-table-view {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .rooms-table {
        min-width: 720px;
      }
      
      /* Load More Button */
      .load-more-container {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.06);
        pointer-events: auto !important;
        position: relative;
        z-index: 9999;
      }
      
      .load-more-btn,
      body.live-light .load-more-btn,
      html.light-theme .load-more-btn {
        padding: 0.9rem 2.5rem;
        background: rgba(255,255,255,0.05);
        color: #94a3b8;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        pointer-events: auto !important;
        position: relative;
        z-index: 9999;
      }

      body.live-light .load-more-btn,
      html.light-theme .load-more-btn {
        background: var(--system-blue) !important;
        color: #ffffff !important;
        border: 1px solid var(--system-blue) !important;
      }
      
      .load-more-btn:hover,
      body.live-light .load-more-btn:hover,
      html.light-theme .load-more-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #f8fafc;
        border-color: rgba(255,255,255,0.2);
        transform: translateY(-2px);
      }

      body.live-light .load-more-btn:hover,
      html.light-theme .load-more-btn:hover {
        background: #0068CC !important;
        border-color: #0068CC !important;
        color: #ffffff !important;
        box-shadow: 0 10px 30px rgba(0, 122, 255, 0.35);
      }

      .load-more-container:has(.load-more-btn.hidden) {
        display: none;
      }
      
      .load-more-btn.hidden {
        display: none;
      }

      /* Hidden card for grid lazy-load */
      .hidden-card { display: none; }
      
      /* Responsive */
      @media (max-width: 768px) {
        .rooms-stats {
          grid-template-columns: 1fr;
        }
        
        .rooms-grid {
          grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
        
        .room-stat-card .stat-value {
          font-size: 2.5rem;
        }
      }

      @media (max-width: 1024px) {
        .reports-page .manage-panel {
          margin-top: 1rem;
          margin-bottom: 1rem;
        }

        .section-header h1 {
          font-size: 1.3rem;
        }

        .room-form-split,
        .booking-form-split {
          grid-template-columns: 1fr !important;
        }

        .add-type-row {
          flex-wrap: wrap;
          align-items: stretch;
        }

        .add-type-btn {
          padding: 0.75rem 0.9rem;
          font-size: 0.9rem;
        }

        .room-form-actions,
        .booking-form-actions {
          flex-direction: column;
        }

        .room-form-actions > button,
        .booking-form-actions > button {
          width: 100%;
        }

        .room-list-toolbar {
          width: 100%;
        }

        #sortSelect,
        .view-toggle-btn {
          flex: 1 1 220px;
        }
      }

      @media (max-width: 768px) {
        .reports-page .manage-panel {
          border-radius: 16px;
          padding-right: 0.85rem !important;
        }

        #toggleRoomFormBtn {
          width: 100%;
          justify-content: center;
        }

        .room-list-toolbar {
          display: grid;
          grid-template-columns: 1fr;
          width: 100%;
        }

        #sortSelect,
        .view-toggle-btn {
          width: 100%;
        }

        .rooms-grid {
          grid-template-columns: 1fr;
          gap: 1rem;
          padding-right: 0.1rem;
        }

        .room-card-image {
          height: 140px;
        }

        .room-card-content {
          padding: 1rem;
        }

        .room-card-actions {
          flex-direction: column;
        }

        .room-card-actions .btn {
          width: 100%;
        }

        .load-more-btn {
          width: 100%;
          justify-content: center;
        }

        .rooms-table {
          min-width: 640px;
        }

        .rooms-table th,
        .rooms-table td {
          padding: 0.75rem 0.85rem;
          font-size: 0.86rem;
        }
      }

      .page-header-bar {
        margin-top: 1rem !important;
      }

      /* ===== SaaS Light Theme Overrides ===== */
      .reports-page .manage-panel {
        background: #ffffff !important;
        border: 1px solid #f1f5f9 !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05) !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
      }

      .rooms-stats {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-top: 0;
      }

      .room-stat-card {
        background: #ffffff !important;
        border: 1px solid #f1f5f9 !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05) !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
      }

      .room-stat-card::before {
        display: none;
      }

      .room-stat-card:hover {
        transform: translateY(-2px);
        border-color: #e2e8f0 !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1) !important;
      }

      .room-stat-card h3 {
        color: #64748b !important;
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 0.2rem;
      }

      .room-stat-card .stat-value {
        background: none !important;
        -webkit-text-fill-color: initial !important;
        color: #0f172a !important;
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        margin-top: 0.35rem;
      }

      .room-stat-card:nth-child(2) .stat-value {
        color: #059669 !important;
      }

      .room-stat-card:nth-child(3) .stat-value {
        color: #dc2626 !important;
      }

      .room-card {
        background: #ffffff !important;
        border: 1px solid #f1f5f9 !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05) !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
      }

      .room-card::after {
        display: none;
      }

      .room-card:hover {
        transform: translateY(-2px);
        border-color: #e2e8f0 !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1) !important;
      }

      .room-card-image {
        background: linear-gradient(135deg, #f1f5f9, #e2e8f0) !important;
        color: #94a3b8 !important;
      }

      .room-card-image::before {
        background: linear-gradient(180deg, transparent 50%, rgba(15, 23, 42, 0.08));
      }

      .image-upload-overlay {
        background: rgba(15, 23, 42, 0.58);
      }

      .room-card-number {
        color: #0f172a !important;
        font-size: 1.35rem;
      }

      .room-card-meta {
        color: #64748b !important;
      }

      .room-card-meta svg {
        color: #94a3b8;
        opacity: 1;
      }

      .room-card-status {
        border-radius: 999px;
        text-transform: none;
        letter-spacing: 0;
        font-size: 0.78rem;
      }

      .room-card-status.vacant {
        background: #ecfdf3 !important;
        border: 1px solid #bbf7d0 !important;
        color: #15803d !important;
      }

      .room-card-status.occupied {
        background: #fef2f2 !important;
        border: 1px solid #fecaca !important;
        color: #b91c1c !important;
      }

      .room-card-actions .btn {
        border-radius: 8px;
        font-size: 0.83rem;
        padding: 0.6rem 0.9rem;
      }

      .room-card-actions .btn-edit,
      body.live-light .room-card-actions .btn-edit,
      html.light-theme .room-card-actions .btn-edit,
      .rooms-grid .room-card-actions button.btn.btn-edit,
      .rooms-table .room-card-actions button.btn.btn-edit {
        background: #e0f2fe !important;
        border: 1px solid #bae6fd !important;
        color: #0369a1 !important;
      }

      .room-card-actions .btn-edit svg,
      body.live-light .room-card-actions .btn-edit svg,
      html.light-theme .room-card-actions .btn-edit svg {
        color: #0369a1 !important;
      }

      .room-card-actions .btn-edit:hover,
      body.live-light .room-card-actions .btn-edit:hover,
      html.light-theme .room-card-actions .btn-edit:hover {
        background: #bae6fd !important;
        border-color: #7dd3fc !important;
        color: #075985 !important;
        transform: translateY(-1px);
        box-shadow: none !important;
      }

      .room-card-actions .btn-delete,
      body.live-light .room-card-actions .btn-delete,
      html.light-theme .room-card-actions .btn-delete,
      .rooms-grid .room-card-actions button.btn.btn-delete,
      .rooms-table .room-card-actions button.btn.btn-delete {
        background: #fee2e2 !important;
        border: 1px solid #fecaca !important;
        color: #b91c1c !important;
      }

      .room-card-actions .btn-delete svg,
      body.live-light .room-card-actions .btn-delete svg,
      html.light-theme .room-card-actions .btn-delete svg {
        color: #b91c1c !important;
      }

      .room-card-actions .btn-delete:hover,
      body.live-light .room-card-actions .btn-delete:hover,
      html.light-theme .room-card-actions .btn-delete:hover {
        background: #fecaca !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
        transform: translateY(-1px);
        box-shadow: none !important;
      }

      .room-empty {
        color: #64748b !important;
      }

      .room-empty-icon {
        opacity: 0.45;
      }

      .room-form-group label,
      .booking-form-group label {
        color: #475569 !important;
        font-size: 0.85rem;
        letter-spacing: 0.025em;
      }

      .room-form-group input,
      .room-form-group select,
      .booking-form-group input,
      .booking-form-group select,
      .booking-form-group textarea {
        border: 1px solid #e2e8f0 !important;
        background: #ffffff !important;
        color: #334155 !important;
        border-radius: 8px !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      }

      .room-form-group input:focus,
      .room-form-group select:focus,
      .booking-form-group input:focus,
      .booking-form-group select:focus,
      .booking-form-group textarea:focus {
        border-color: #7dd3fc !important;
        box-shadow: 0 0 0 3px rgba(125, 211, 252, 0.3) !important;
        background: #ffffff !important;
      }

      .add-type-btn {
        border: 1px dashed #bae6fd !important;
        background: #f0f9ff !important;
        color: #0369a1 !important;
        border-radius: 8px !important;
      }

      .add-type-btn:hover {
        background: #e0f2fe !important;
        border-color: #7dd3fc !important;
      }

      .delete-type-btn {
        border-color: #fecaca !important;
        color: #b91c1c !important;
        background: #fef2f2 !important;
      }

      .delete-type-btn:hover {
        background: #fee2e2 !important;
        border-color: #fca5a5 !important;
      }

      #toggleRoomFormBtn {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        color: #334155 !important;
        border-radius: 8px !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
      }

      #toggleRoomFormBtn:hover {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
      }

      .manage-panel .section-header p {
        color: #64748b !important;
      }

      #sortSelect {
        padding: 0.6rem 2.5rem 0.6rem 1rem !important;
        border-radius: 8px !important;
        border: 1px solid #e2e8f0 !important;
        background: #f8fafc url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/></svg>") no-repeat right 0.75rem center/16px 16px !important;
        color: #334155 !important;
        font-size: 0.95rem !important;
        font-weight: 500 !important;
        appearance: none;
        -webkit-appearance: none;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      }

      .view-toggle-btn {
        background: #ffffff !important;
        color: #334155 !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
      }

      .view-toggle-btn:hover {
        background: #f8fafc !important;
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
      }

      .rooms-table thead {
        background: #f8fafc !important;
      }

      .rooms-table th {
        color: #64748b !important;
        border-bottom: 1px solid #e2e8f0 !important;
      }

      .rooms-table td {
        color: #334155 !important;
        border-bottom: 1px solid #f1f5f9 !important;
      }

      .rooms-table tbody tr:hover {
        background: #f8fafc !important;
      }

      .room-image-small {
        background: #f8fafc !important;
        border: 1px solid #e2e8f0;
      }

      .booking-modal {
        background: rgba(15, 23, 42, 0.45) !important;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
      }

      .booking-modal-content {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 16px !important;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.16) !important;
      }

      .booking-modal-content h2 {
        color: #0f172a !important;
      }

      .btn-submit {
        background: #60a5fa !important;
        color: #ffffff !important;
        border-radius: 8px !important;
      }

      .btn-submit:hover {
        background: #3b82f6 !important;
        box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4) !important;
      }

      .btn-cancel {
        background: #ffffff !important;
        color: #475569 !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
      }

      .btn-cancel:hover {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
      }

      .load-more-container {
        border-top: 1px solid #f1f5f9 !important;
      }

      .load-more-btn,
      body.live-light .load-more-btn,
      html.light-theme .load-more-btn {
        background: #f8fafc !important;
        color: #0369a1 !important;
        border: 1px solid #bae6fd !important;
        border-radius: 8px !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
      }

      .load-more-btn:hover,
      body.live-light .load-more-btn:hover,
      html.light-theme .load-more-btn:hover {
        background: #e0f2fe !important;
        color: #075985 !important;
        border-color: #7dd3fc !important;
        box-shadow: none !important;
      }

      .reports-page .datatable-wrapper {
        margin-top: 1rem;
      }

      .reports-page .datatable-top,
      .reports-page .datatable-bottom {
        border: none;
        padding: 0;
        margin-bottom: 0.75rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
      }

      .reports-page .datatable-dropdown label,
      .reports-page .datatable-search label,
      .reports-page .datatable-info {
        color: #64748b;
        font-size: 0.85rem;
      }

      .reports-page .datatable-selector,
      .reports-page .datatable-input {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #ffffff;
        color: #334155;
      }

      .reports-page .datatable-selector {
        padding: 0.35rem 0.65rem;
      }

      .reports-page .datatable-input {
        padding: 0.5rem 0.75rem;
      }

      .reports-page .datatable-input:focus,
      .reports-page .datatable-selector:focus {
        outline: none;
        border-color: #7dd3fc;
        box-shadow: 0 0 0 3px rgba(125, 211, 252, 0.3);
      }

      .reports-page .datatable-pagination a {
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
        border-radius: 8px;
      }

      .reports-page .datatable-pagination li.active a {
        background: #e0f2fe;
        border-color: #7dd3fc;
        color: #0369a1;
      }

      .reports-page .datatable-pagination li.disabled a {
        opacity: 0.5;
      }

      @media (max-width: 1024px) {
        .reports-page .manage-panel {
          margin-top: 1rem;
          margin-bottom: 1rem;
        }
      }

      @media (max-width: 768px) {
        .reports-page .manage-panel {
          border-radius: 16px;
          padding-right: 0.85rem !important;
        }

        .rooms-grid {
          grid-template-columns: 1fr;
          gap: 1rem;
          padding-right: 0.1rem;
        }

        .load-more-btn {
          width: 100%;
          justify-content: center;
        }
      }
    </style>
  



<style>
/* Fix margins all report pages v2 */
main > div:first-of-type,
.app-main > div:first-of-type, 
.main-content > div:first-of-type, 
.reports-container > div:first-of-type {
    max-width: 1280px !important;
    margin: 0 auto !important;
    padding: 20px !important;
    box-sizing: border-box;
}
@media (max-width: 768px) {
    main > div:first-of-type,
    .app-main > div:first-of-type, 
    .main-content > div:first-of-type, 
    .reports-container > div:first-of-type {
        padding: 10px !important;
    }
}
</style>

</head>
  <body class="reports-page" data-disable-edit-modal="true">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div style="max-width: 1280px; margin: 0 auto; width: 100%;">
          <?php 
            $pageTitle = 'จัดการห้องพัก';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <?php if (isset($_SESSION['success'])): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showSuccessToast('<?php echo addslashes($_SESSION['success']); ?>');
              });
            </script>
            <?php unset($_SESSION['success']); ?>
          <?php endif; ?>
          <?php if (isset($_SESSION['error'])): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showErrorToast('<?php echo addslashes($_SESSION['error']); ?>');
              });
            </script>
            <?php unset($_SESSION['error']); ?>
          <?php endif; ?>

          <section class="manage-panel">
            <div class="rooms-stats">
              <div class="room-stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <h3>ห้องทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($totalRooms); ?></div>
              </div>
              <div class="room-stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <h3>ห้องว่าง</h3>
                <div class="stat-value"><?php echo number_format($vacant); ?></div>
              </div>
              <div class="room-stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <h3>มีผู้เข้าพัก</h3>
                <div class="stat-value"><?php echo number_format($occupied); ?></div>
              </div>
            </div>
          </section>

          <!-- Toggle button for room form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleRoomFormBtn" style="white-space:nowrap;padding:0.85rem 1.5rem;cursor:pointer;font-size:0.95rem;background:#ffffff;border:1px solid #e2e8f0;color:#334155;border-radius:8px;transition:all 0.2s;font-weight:600;display:inline-flex;align-items:center;gap:0.5rem;" onclick="toggleRoomForm()" onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1';this.style.color='#0f172a'" onmouseout="this.style.background='#ffffff';this.style.borderColor='#e2e8f0';this.style.color='#334155'">
              <span id="toggleRoomFormIcon">▼</span> <span id="toggleRoomFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <section class="manage-panel" id="addRoomSection">
            <div class="section-header">
              <div>
                <h1>เพิ่มห้องพัก</h1>
                <p style="margin-top:0.25rem;color:#64748b;">สร้างห้องพัก</p>
              </div>
            </div>
            <form id="addRoomForm" enctype="multipart/form-data" novalidate>
              <div class="room-form">
                <div class="room-form-group">
                  <label for="room_number">หมายเลขห้อง <span style="color:#f87171;">*</span></label>
                  <input type="text" id="room_number" name="room_number" required maxlength="2" placeholder="เช่น 01, 02, ..." value="<?php echo htmlspecialchars($nextRoomNumber); ?>" aria-required="true" aria-describedby="room_number_error room_number_hint" />
                  <p class="form-hint" id="room_number_hint">ตัวเลข 1-2 หลัก เช่น 01, 02</p>
                  <p class="form-error-message" id="room_number_error">กรุณากรอกหมายเลขห้อง</p>
                </div>
                <div class="room-form-group room-form-split" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                  <div>
                    <div class="add-type-row">
                      <label for="type_id" style="margin:0;">ประเภทห้อง <span style="color:#f87171;">*</span></label>
                      <button type="button" class="add-type-btn" id="addTypeBtn">+ เพิ่มประเภทห้อง</button>
                      <button type="button" class="add-type-btn delete-type-btn" id="deleteTypeBtn">ลบประเภทห้อง</button>
                    </div>
                    <select id="type_id" name="type_id" required aria-required="true" aria-describedby="type_id_error">
                      <?php foreach ($roomTypes as $index => $type): ?>
                        <option value="<?php echo $type['type_id']; ?>" <?php echo ($index === 0 ? 'selected' : ''); ?>>
                          <?php echo htmlspecialchars($type['type_name']); ?> (<?php echo number_format($type['type_price']); ?> บาท/เดือน)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <p class="form-error-message" id="type_id_error">กรุณาเลือกประเภทห้อง</p>
                  </div>
                   <div>
                     <label style="display:block;">สถานะห้อง</label>
                     <div style="padding:0.9rem 0.85rem; border-radius:10px; border:1px dashed #cbd5e1; background:#f8fafc; color:#475569; font-size:0.9rem; line-height:1.5;">
                       <strong>ระบบจะปรับสถานะอัตโนมัติ</strong><br>
                       <span style="display:block; margin-top:0.4rem;">
                         <span style="color:#16a34a;">✓ ว่าง (0)</span> - ไม่มีใครจอง หรือเช่า<br>
                         <span style="color:#dc2626;">✗ ไม่ว่าง (1)</span> - มีคนจองอยู่ หรือ มีคนเช่าอยู่
                       </span>
                     </div>
                   </div>
                </div>
                <div class="room-form-group">
                  <label for="room_price">ราคาห้องพิเศษ (บาท/เดือน)</label>
                  <input type="number" id="room_price" name="room_price" min="0" step="1" placeholder="เว้นว่าง = ใช้ราคาตามประเภทห้อง" aria-describedby="room_price_hint" />
                  <p class="form-hint" id="room_price_hint">ใช้สำหรับลดราคาเฉพาะห้องในช่วงปิดเทอม</p>
                </div>
                <div class="room-form-group">
                  <label>สิ่งอำนวยความสะดวก:</label>
                  <div id="add_features_checkboxes" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.35rem;" aria-describedby="room_features_hint">
                    <?php
                    $addAllFeatures = ['ไฟฟ้า', 'น้ำประปา', 'WiFi', 'เฟอร์นิเจอร์', 'แอร์', 'ตู้เย็น'];
                    foreach ($addAllFeatures as $f):
                    ?>
                    <label style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.4rem 0.75rem;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;cursor:pointer;font-size:0.85rem;transition:all 0.2s;">
                      <input type="checkbox" name="room_features[]" value="<?php echo htmlspecialchars($f); ?>" checked style="accent-color:#6366f1;">
                      <?php echo htmlspecialchars($f); ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <p class="form-hint" id="room_features_hint">เลือกสิ่งอำนวยความสะดวกที่มีในห้อง</p>
                  <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                    <input type="text" id="add_custom_feature_input" placeholder="เพิ่มสิ่งอำนวยความสะดวกเพิ่มเติม..." maxlength="40" style="flex:1;padding:0.4rem 0.65rem;border:1px solid rgba(99,102,241,0.3);border-radius:8px;font-size:0.85rem;outline:none;">
                    <button type="button" onclick="addCustomFeature('add')" style="padding:0.4rem 0.9rem;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:0.85rem;cursor:pointer;white-space:nowrap;">+ เพิ่ม</button>
                  </div>
                </div>
                <div class="room-form-group">
                  <label for="room_image">รูปภาพห้อง</label>
                  <input type="file" id="room_image" name="room_image" accept="image/*" aria-describedby="room_image_hint" />
                  <p class="form-hint" id="room_image_hint">รูปภาพ JPG, PNG หรือ GIF (ไม่เกิน 5MB)</p>
                </div>
                <div class="room-form-actions">
                  <button type="submit" class="animate-ui-add-btn" data-allow-submit="true" data-animate-ui-skip="true" data-no-modal="true" style="flex:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    เพิ่มห้องพัก
                  </button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1>รายการห้องพัก</h1>
                <p style="color:#64748b;margin-top:0.2rem;">ห้องพักและข้อมูลทั้งหมด</p>
              </div>
              <div class="room-list-toolbar" style="display:flex;gap:0.75rem;align-items:center;">
                <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 2.5rem 0.6rem 1rem;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;color:#334155;font-size:0.95rem;cursor:pointer;appearance:none;-webkit-appearance:none;">
                  <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>>เพิ่มล่าสุด</option>
                  <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>>เพิ่มเก่าสุด</option>
                  <option value="room_number" <?php echo ($sortBy === 'room_number' ? 'selected' : ''); ?>>หมายเลขห้อง</option>
                </select>
                <button type="button" class="view-toggle-btn" id="viewToggleBtn" onclick="toggleView()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                  <span id="viewToggleText">มุมมองแถว</span>
                </button>
              </div>
            </div>
            
            <?php if (empty($rooms)): ?>
              <div class="room-empty">
                <div class="room-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:56px;height:56px;"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg></div>
                <h3>ยังไม่มีห้องพัก</h3>
                <p>เริ่มต้นเพิ่มห้องพัก</p>
              </div>
            <?php else: ?>
              <!-- Grid View -->
              <div class="rooms-grid rooms-grid-view" id="roomsGrid">
                <?php foreach ($rooms as $index => $room): ?>
                  <div class="room-card <?php echo $index >= 20 ? 'hidden-card' : ''; ?>" data-room-id="<?php echo $room['room_id']; ?>" data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>">
                    <div class="room-card-image room-card-image-upload" onclick="triggerImageUpload(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>')" style="cursor: pointer; position: relative;" title="คลิกเพื่ออัปโหลดรูปภาพ">
                      <?php if (!empty($room['room_image'])): ?>
                        <img src="/dormitory_management/Public/Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" />
                      <?php else: ?>
                        <div class="placeholder-upload-hint" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; flex-direction: column; gap: 0.5rem;">
                          <svg class="placeholder-upload-icon" xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                            <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                            <path d="M2 16v-2" />
                            <path d="M22 16v-2" />
                          </svg>
                          <span class="placeholder-upload-text" style="font-size: 0.75rem; text-align: center;">คลิกเพื่ออัปโหลด</span>
                        </div>
                      <?php endif; ?>
                      <!-- Hover Overlay with Upload Icon -->
                      <div class="image-upload-overlay">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="upload-icon">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                          <polyline points="17 8 12 3 7 8" />
                          <line x1="12" y1="3" x2="12" y2="15" />
                        </svg>
                        <span class="upload-text">อัปโหลดรูป</span>
                      </div>
                      <input type="file" id="imageInput_<?php echo $room['room_id']; ?>" accept="image/*" style="display: none;" onchange="uploadRoomImage(<?php echo $room['room_id']; ?>, this)">
                    </div>
                    <div class="room-card-content">
                      <h3 class="room-card-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></h3>
                      <div class="room-card-meta">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <?php echo htmlspecialchars($room['type_name'] ?? '-'); ?> • <?php echo number_format((int)($room['display_price'] ?? 0)); ?> บาท/เดือน
                      </div>
                      <?php if (!empty($room['has_custom_price'])): ?>
                        <div class="room-price-note" style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.5rem;">ปกติ <?php echo number_format((int)($room['type_price'] ?? 0)); ?> บาท</div>
                      <?php endif; ?>
                      <div class="room-card-status <?php echo $room['room_status'] === '0' ? 'vacant' : 'occupied'; ?>">
                        <?php if ($room['room_status'] === '0'): ?>
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                          ว่าง
                        <?php else: ?>
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                          ไม่ว่าง
                        <?php endif; ?>
                      </div>
                      <?php if ($room['room_status'] !== '0' && !empty($room['occupation_reason'])): ?>
                        <div style="font-size: 0.75rem; margin-bottom: 0.75rem; color: #d97706; opacity: 0.9;">
                          <?php echo htmlspecialchars($room['occupation_reason']); ?>
                        </div>
                      <?php endif; ?>
                      <div class="room-card-actions">
                        <button type="button" class="btn btn-edit" data-room-id="<?php echo $room['room_id']; ?>" onclick="editRoom(<?php echo $room['room_id']; ?>)">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          แก้ไข
                        </button>
                        <button type="button" class="btn btn-delete" onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars(addslashes($room['room_number'])); ?>')">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                          ลบ
                        </button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <?php if (count($rooms) > 20): ?>
              <div class="load-more-container">
                <button type="button" class="load-more-btn" id="loadMoreBtn" onclick="loadMoreRooms()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  <span id="loadMoreText">โหลดเพิ่มเติม (<span id="remainingCount"><?php echo count($rooms) - 20; ?></span> ห้อง)</span>
                </button>
              </div>
              <?php endif; ?>
              
              <!-- Table View -->
              <div class="rooms-table-view" id="roomsTable">
                <table class="rooms-table">
                  <thead>
                    <tr>
                      <th>รูปภาพ</th>
                      <th>หมายเลขห้อง</th>
                      <th>สถานะ</th>
                      <th>จัดการ</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rooms as $index => $room): ?>
                      <tr class="room-row <?php echo $index >= 20 ? 'hidden-row' : ''; ?>" data-index="<?php echo $index; ?>" data-room-id="<?php echo $room['room_id']; ?>" data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>">
                        <td>
                          <div class="room-image-small">
                            <?php if (!empty($room['room_image'])): ?>
                              <img src="/dormitory_management/Public/Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" />
                            <?php else: ?>
                              <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                                <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                                <path d="M2 16v-2" />
                                <path d="M22 16v-2" />
                              </svg>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td style="font-weight:600;color:#0f172a;">
                          ห้อง <?php echo htmlspecialchars($room['room_number']); ?><br>
                          <span style="font-size:0.85rem;color:#64748b;font-weight:normal;">
                            <?php echo number_format((int)($room['display_price'] ?? 0)); ?> บาท<br>
                            ประเภท: <?php echo htmlspecialchars($room['type_name'] ?? '-'); ?>
                          </span>
                          <?php if (!empty($room['has_custom_price'])): ?>
                            <div class="room-price-note" style="font-size:0.75rem;color:#94a3b8;margin-top:0.25rem;">ปกติ <?php echo number_format((int)($room['type_price'] ?? 0)); ?> บาท</div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="room-card-status <?php echo $room['room_status'] === '0' ? 'vacant' : 'occupied'; ?>">
                            <?php if ($room['room_status'] === '0'): ?>
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                              ว่าง
                            <?php else: ?>
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                              ไม่ว่าง
                            <?php endif; ?>
                          </span>
                          <?php if ($room['room_status'] !== '0' && !empty($room['occupation_reason'])): ?>
                            <div style="font-size: 0.85rem; margin-top: 0.3rem; color: #d97706;">
                              <?php echo htmlspecialchars($room['occupation_reason']); ?>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="room-card-actions">
                            <button type="button" class="btn btn-edit" data-room-id="<?php echo $room['room_id']; ?>" onclick="editRoom(<?php echo $room['room_id']; ?>)">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                              แก้ไข
                            </button>
                            <button type="button" class="btn btn-delete" onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars(addslashes($room['room_number'])); ?>')">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                              ลบ
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php /* load-more moved to grid view above */ ?>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>

    <!-- Edit Modal -->
    <div class="booking-modal" id="editModal" style="display:none;">
      <div class="booking-modal-content" style="max-width:600px;">
        <h2>แก้ไขห้องพัก</h2>
        <form id="editForm" method="POST" action="../Manage/update_room.php" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="room_id" id="edit_room_id">
          
          <div class="booking-form-group">
            <label for="edit_room_number">หมายเลขห้อง <span style="color: red;">*</span></label>
            <input type="text" name="room_number" id="edit_room_number" required maxlength="2" aria-required="true" aria-describedby="edit_room_number_error edit_room_number_hint">
            <p class="form-hint" id="edit_room_number_hint">ตัวเลข 1-2 หลัก เช่น 01, 02</p>
            <p class="form-error-message" id="edit_room_number_error">กรุณากรอกหมายเลขห้อง</p>
          </div>
          
          <div class="booking-form-group booking-form-split" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <div class="add-type-row" style="margin-bottom:0.35rem;">
                <label for="edit_type_id" style="margin:0;">ประเภทห้อง <span style="color: red;">*</span></label>
                <button type="button" class="add-type-btn" id="addTypeBtnEdit">+ เพิ่มประเภทห้อง</button>
                <button type="button" class="add-type-btn delete-type-btn" id="deleteTypeBtnEdit">ลบประเภทห้อง</button>
              </div>
              <select name="type_id" id="edit_type_id" required aria-required="true" aria-describedby="edit_type_id_error">
                <option value="">-- เลือกประเภทห้อง --</option>
                <?php foreach ($roomTypes as $type): ?>
                  <option value="<?php echo $type['type_id']; ?>">
                    <?php echo htmlspecialchars($type['type_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="form-error-message" id="edit_type_id_error">กรุณาเลือกประเภทห้อง</p>
            </div>
          </div>

          <div class="booking-form-group">
            <label for="edit_room_price">ราคาห้องพิเศษ (บาท/เดือน)</label>
            <input type="number" name="room_price" id="edit_room_price" min="0" step="1" placeholder="เว้นว่าง = ใช้ราคาตามประเภทห้อง" aria-describedby="edit_room_price_hint">
            <p class="form-hint" id="edit_room_price_hint">ใช้สำหรับลดราคาเฉพาะห้องในช่วงปิดเทอม</p>
          </div>
          
          <div class="booking-form-group">
            <label>สิ่งอำนวยความสะดวก:</label>
            <div id="edit_features_checkboxes" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.35rem;" aria-describedby="edit_room_features_hint">
              <?php
              $allFeatures = ['ไฟฟ้า', 'น้ำประปา', 'WiFi', 'เฟอร์นิเจอร์', 'แอร์', 'ตู้เย็น'];
              foreach ($allFeatures as $f):
              ?>
              <label style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.4rem 0.75rem;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;cursor:pointer;font-size:0.85rem;transition:all 0.2s;">
                <input type="checkbox" name="room_features[]" value="<?php echo htmlspecialchars($f); ?>" class="feature-checkbox" style="accent-color:#6366f1;">
                <?php echo htmlspecialchars($f); ?>
              </label>
              <?php endforeach; ?>
            </div>
            <p class="form-hint" id="edit_room_features_hint">เลือกสิ่งอำนวยความสะดวกที่มีในห้อง</p>
            <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
              <input type="text" id="edit_custom_feature_input" placeholder="เพิ่มสิ่งอำนวยความสะดวกเพิ่มเติม..." maxlength="40" style="flex:1;padding:0.4rem 0.65rem;border:1px solid rgba(99,102,241,0.3);border-radius:8px;font-size:0.85rem;outline:none;">
              <button type="button" onclick="addCustomFeature('edit')" style="padding:0.4rem 0.9rem;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:0.85rem;cursor:pointer;white-space:nowrap;">+ เพิ่ม</button>
            </div>
          </div>

          <div class="booking-form-group">
            <label>รูปภาพห้อง:</label>
            <input type="file" name="room_image" id="edit_room_image" accept="image/*">
            <input type="hidden" name="delete_image" id="delete_image" value="0">
            <div id="edit_image_preview" style="margin-top:0.5rem; color:#64748b; font-size:0.85rem;"></div>
            <div id="delete_image_btn_container" style="margin-top:0.75rem; display:none;">
              <button type="button" id="deleteImageBtn" onclick="deleteCurrentImage()" style="padding:0.6rem 1.2rem; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:8px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:0.4rem; transition:all 0.2s;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                ลบรูปภาพนี้
              </button>
            </div>
          </div>
          
          <div class="booking-form-actions">
            <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
            <button type="button" class="btn-cancel" onclick="closeEditModal()">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      // Toggle room form visibility
      function toggleRoomForm() {
        try {
          const section = document.getElementById('addRoomSection');
          const icon = document.getElementById('toggleRoomFormIcon');
          const text = document.getElementById('toggleRoomFormText');
          if (!section || !icon || !text) return;
          const isHidden = section.style.display === 'none';
          
          if (isHidden) {
            section.style.display = '';
            icon.textContent = '▼';
            text.textContent = 'ซ่อนฟอร์ม';
            localStorage.setItem('roomFormVisible', 'true');
          } else {
            section.style.display = 'none';
            icon.textContent = '▶';
            text.textContent = 'แสดงฟอร์ม';
            localStorage.setItem('roomFormVisible', 'false');
          }
        } catch (e) {
          console.error('Error toggling form:', e);
        }
      }

      // Hide animate-ui modal overlays for this page
      document.addEventListener('DOMContentLoaded', () => {
        const overlays = document.querySelectorAll('.animate-ui-modal-overlay');
        overlays.forEach(el => {
          el.style.display = 'none';
          el.style.pointerEvents = 'none';
          el.style.visibility = 'hidden';
          el.remove();
        });

        // Restore form visibility from localStorage
        const isFormVisible = localStorage.getItem('roomFormVisible') !== 'false';
        const section = document.getElementById('addRoomSection');
        const icon = document.getElementById('toggleRoomFormIcon');
        const text = document.getElementById('toggleRoomFormText');
        if (!isFormVisible) {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = 'แสดงฟอร์ม';
        }

        // Initialize DataTable for rooms table
        const roomsTableEl = document.querySelector('.rooms-table');
        if (roomsTableEl && window.simpleDatatables) {
          try {
            const dt = new simpleDatatables.DataTable(roomsTableEl, {
              searchable: true,
              fixedHeight: false,
              perPage: 6,
              perPageSelect: [6, 10, 25, 50, 100],
              labels: {
                placeholder: 'ค้นหา...',
                perPage: '{select} แถวต่อหน้า',
                noRows: 'ไม่มีข้อมูล',
                info: 'แสดง {start}–{end} จาก {rows} รายการ'
              },
              columns: [
                { select: 3, sortable: false }
              ]
            });
            window.__roomsDataTable = dt;
          } catch (err) {
            console.error('Failed to init rooms table', err);
          }
        }
      });

      const roomsData = <?php echo json_encode($rooms); ?>;
      window.roomsData = roomsData;

      function getRoomPriceInfo(room) {
        const typePrice = parseFloat(room.type_price || 0);
        const customPrice = parseFloat(room.room_price || 0);
        const useCustom = customPrice > 0 && customPrice !== typePrice;
        const displayPrice = useCustom ? customPrice : typePrice;
        return { displayPrice, useCustom, typePrice };
      }

      function editRoom(roomId) {
        const room = roomsData.find(r => r.room_id == roomId);
        if (!room) return;
        
        document.getElementById('edit_room_id').value = room.room_id;
        document.getElementById('edit_room_number').value = room.room_number;
        document.getElementById('edit_type_id').value = room.type_id;
        document.getElementById('delete_image').value = '0';
        const editRoomPriceInput = document.getElementById('edit_room_price');
        if (editRoomPriceInput) {
          const rawPrice = parseFloat(room.room_price || 0);
          editRoomPriceInput.value = rawPrice > 0 ? rawPrice : '';
        }
        
        // ตั้งค่า features checkboxes
        var roomFeats = (room.room_features || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
        // ลบ custom features ที่เพิ่มเข้ามาก่อนหน้านี้ (data-custom="1") ออกก่อน
        document.querySelectorAll('#edit_features_checkboxes label[data-custom="1"]').forEach(function(l){ l.remove(); });
        var presetVals = [];
        document.querySelectorAll('.feature-checkbox').forEach(function(cb){ presetVals.push(cb.value); });
        // ตุ๊ก preset และเพิ่ม custom features
        document.querySelectorAll('.feature-checkbox').forEach(function(cb){
          cb.checked = roomFeats.indexOf(cb.value) !== -1;
        });
        roomFeats.forEach(function(f){
          if (presetVals.indexOf(f) === -1) {
            addCustomFeatureLabel('edit', f, true);
          }
        });
        
        const preview = document.getElementById('edit_image_preview');
        const deleteBtn = document.getElementById('delete_image_btn_container');
        
        if (room.room_image) {
          preview.innerHTML = `<div style="margin-top:0.5rem;">
            <img src="/dormitory_management/Public/Assets/Images/Rooms/${room.room_image}" alt="Room Image" style="max-width:100%; height:auto; border-radius:12px; max-height:200px;">
            <div style="color:#22c55e; margin-top:0.5rem;">✓ ${room.room_image}</div>
          </div>`;
          deleteBtn.style.display = 'block';
        } else {
          preview.innerHTML = '<div style="color:#64748b;">ไม่มีรูปภาพ</div>';
          deleteBtn.style.display = 'none';
        }
        
        document.getElementById('editModal').style.display = 'flex';
      }
      window.editRoom = editRoom;
      
      // เพิ่ม custom feature label
      function addCustomFeatureLabel(ctx, val, checked) {
        var containerId = ctx === 'edit' ? 'edit_features_checkboxes' : 'add_features_checkboxes';
        var container = document.getElementById(containerId);
        if (!container) return;
        // ไม่เพิ่มซ้ำ
        var exists = Array.from(container.querySelectorAll('input[type=checkbox]')).some(function(cb){ return cb.value.trim() === val.trim(); });
        if (exists) {
          Array.from(container.querySelectorAll('input[type=checkbox]')).forEach(function(cb){ if (cb.value.trim() === val.trim()) cb.checked = true; });
          return;
        }
        var lbl = document.createElement('label');
        lbl.setAttribute('data-custom', '1');
        lbl.style.cssText = 'display:inline-flex;align-items:center;gap:0.3rem;padding:0.4rem 0.75rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:8px;cursor:pointer;font-size:0.85rem;transition:all 0.2s;';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = 'room_features[]';
        cb.value = val;
        cb.checked = checked !== false;
        cb.className = 'feature-checkbox';
        cb.style.accentColor = '#10b981';
        var rm = document.createElement('span');
        rm.textContent = '×';
        rm.title = 'ลบ';
        rm.style.cssText = 'margin-left:0.25rem;color:#ef4444;font-weight:700;cursor:pointer;';
        rm.onclick = function(e){ e.preventDefault(); lbl.remove(); };
        lbl.appendChild(cb);
        lbl.appendChild(document.createTextNode(' ' + val + ' '));
        lbl.appendChild(rm);
        container.appendChild(lbl);
      }

      function addCustomFeature(ctx) {
        var inputId = ctx === 'edit' ? 'edit_custom_feature_input' : 'add_custom_feature_input';
        var input = document.getElementById(inputId);
        if (!input) return;
        var val = input.value.trim();
        if (!val) return;
        addCustomFeatureLabel(ctx, val, true);
        input.value = '';
        input.focus();
      }

      // Enter key on custom input
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && (e.target.id === 'edit_custom_feature_input' || e.target.id === 'add_custom_feature_input')) {
          e.preventDefault();
          addCustomFeature(e.target.id === 'edit_custom_feature_input' ? 'edit' : 'add');
        }
      });

      window.addCustomFeature = addCustomFeature;
      window.addCustomFeatureLabel = addCustomFeatureLabel;
      function deleteCurrentImage() {
        document.getElementById('delete_image').value = '1';
        document.getElementById('edit_image_preview').innerHTML = '<div style="color:#b91c1c; padding:0.75rem; background:#fef2f2; border-radius:8px; border:1px dashed #fecaca;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; vertical-align:middle; margin-right:0.4rem;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> รูปภาพจะถูกลบเมื่อกดบันทึก</div>';
        document.getElementById('delete_image_btn_container').style.display = 'none';
      }
      window.deleteCurrentImage = deleteCurrentImage;
      
      function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('editForm').reset();
        document.getElementById('delete_image').value = '0';
        // ลบ custom features ที่เพิ่มไว้
        document.querySelectorAll('#edit_features_checkboxes label[data-custom="1"]').forEach(function(l){ l.remove(); });
        // Clear error states
        document.getElementById('editForm').querySelectorAll('.booking-form-group').forEach(group => {
          group.classList.remove('has-error');
          const input = group.querySelector('input, select');
          if (input) input.setAttribute('aria-invalid', 'false');
        });
      }
      
      // Handle Edit Form Submit via AJAX
      const editForm = document.getElementById('editForm');
      if (editForm) {
        // Form validation function per ui-standard.md
        function validateEditRoomForm(form) {
          let isValid = true;
          const roomNumber = form.querySelector('[name="room_number"]');
          const typeId = form.querySelector('[name="type_id"]');
          
          // Validate room_number
          const roomNumberGroup = roomNumber.closest('.booking-form-group');
          if (!roomNumber.value.trim()) {
            roomNumberGroup.classList.add('has-error');
            roomNumber.setAttribute('aria-invalid', 'true');
            isValid = false;
          } else if (!/^\d{1,2}$/.test(roomNumber.value.trim())) {
            roomNumberGroup.classList.add('has-error');
            roomNumber.setAttribute('aria-invalid', 'true');
            isValid = false;
          } else {
            roomNumberGroup.classList.remove('has-error');
            roomNumber.setAttribute('aria-invalid', 'false');
          }
          
          // Validate type_id
          const typeIdGroup = typeId.closest('.booking-form-group');
          if (!typeId.value) {
            typeIdGroup.classList.add('has-error');
            typeId.setAttribute('aria-invalid', 'true');
            isValid = false;
          } else {
            typeIdGroup.classList.remove('has-error');
            typeId.setAttribute('aria-invalid', 'false');
          }
          
          return isValid;
        }
        
        editForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          
          // Validate form before submission
          if (!validateEditRoomForm(editForm)) {
            showErrorToast('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            return;
          }
          
          const roomNumber = document.getElementById('edit_room_number').value.trim();
          if (!roomNumber) {
            toast('กรุณากรอกหมายเลขห้อง');
            return;
          }
          
          const formData = new FormData(editForm);
          
          try {
            const response = await fetch('../Manage/update_room.php', {
              method: 'POST',
              body: formData
            });
            
            const data = await response.json();
            
            if (response.ok) {
              showSuccessToast(data.success || 'บันทึกสำเร็จ');
              closeEditModal();
              
              // อัปเดต UI โดยไม่ต้องรีหน้า
              if (data.room) {
                updateRoomInDisplay(data.room);
              }
            } else {
              showErrorToast('เกิดข้อผิดพลาด: ' + (data.error || 'ไม่ทราบสาเหตุ'));
            }
          } catch (error) {
            showErrorToast('เกิดข้อผิดพลาด: ' + error.message);
          }
        });
        
        // Handle edit form reset to clear error states
        editForm.addEventListener('reset', (e) => {
          editForm.querySelectorAll('.booking-form-group').forEach(group => {
            group.classList.remove('has-error');
            const input = group.querySelector('input, select');
            if (input) input.setAttribute('aria-invalid', 'false');
          });
        });
      }
      
      // Function to update room in display without page reload
      function updateRoomInDisplay(room) {
        const roomId = room.room_id;
        const priceInfo = getRoomPriceInfo(room);
        const priceText = new Intl.NumberFormat('th-TH').format(priceInfo.displayPrice || 0);
        const basePriceText = new Intl.NumberFormat('th-TH').format(priceInfo.typePrice || 0);
        
        // อัปเดตข้อมูลใน roomsData array
        const index = roomsData.findIndex(r => r.room_id == roomId);
        if (index !== -1) {
          roomsData[index] = { ...roomsData[index], ...room };
        }
        
        // อัปเดต Grid View Card
        const gridCard = document.querySelector(`.room-card[data-room-id="${roomId}"]`);
        if (gridCard) {
          // อัปเดตรูปภาพ
          const imageDiv = gridCard.querySelector('.room-card-image');
          if (imageDiv) {
            imageDiv.classList.add('room-card-image-upload');
            imageDiv.setAttribute('onclick', `triggerImageUpload(${roomId})`);
            imageDiv.setAttribute('title', 'คลิกเพื่ออัปโหลดรูปภาพ');
            imageDiv.style.cursor = 'pointer';
            imageDiv.style.position = 'relative';

            imageDiv.innerHTML = `${room.room_image ?
              `<img src="/dormitory_management/Public/Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />` :
              `<div class="placeholder-upload-hint" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; flex-direction: column; gap: 0.5rem;">
                <svg class="placeholder-upload-icon" xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                  <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                  <path d="M2 16v-2" />
                  <path d="M22 16v-2" />
                </svg>
                <span class="placeholder-upload-text" style="font-size: 0.75rem; text-align: center;">คลิกเพื่ออัปโหลด</span>
              </div>`
            }
            <div class="image-upload-overlay">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="upload-icon">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <span class="upload-text">อัปโหลดรูป</span>
            </div>
            <input type="file" id="imageInput_${roomId}" accept="image/*" style="display: none;" onchange="uploadRoomImage(${roomId}, this)">`;
          }
          
          // อัปเดตหมายเลขห้อง
          const numberEl = gridCard.querySelector('.room-card-number');
          if (numberEl) {
            numberEl.textContent = `ห้อง ${room.room_number}`;
          }
          
          // อัปเดต meta (ประเภท + ราคา)
          const metaEl = gridCard.querySelector('.room-card-meta');
          if (metaEl) {
            metaEl.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              ${room.type_name || '-'} • ${priceText} บาท/เดือน`;
            const noteEl = gridCard.querySelector('.room-price-note');
            if (priceInfo.useCustom) {
              if (noteEl) {
                noteEl.textContent = `ปกติ ${basePriceText} บาท`;
              } else {
                const note = document.createElement('div');
                note.className = 'room-price-note';
                note.style.cssText = 'font-size:0.75rem;color:#94a3b8;margin-bottom:0.5rem;';
                note.textContent = `ปกติ ${basePriceText} บาท`;
                metaEl.insertAdjacentElement('afterend', note);
              }
            } else if (noteEl) {
              noteEl.remove();
            }
          }
        }
        
        // อัปเดต Table View Row
        const tableRow = document.querySelector(`tr[data-room-id="${roomId}"]`);
        if (tableRow) {
          const cells = tableRow.querySelectorAll('td');
          
          // อัปเดตรูปภาพ (cell 0)
          if (cells[0]) {
            const imgSmall = cells[0].querySelector('.room-image-small');
            if (imgSmall) {
              if (room.room_image) {
                imgSmall.innerHTML = `<img src="/dormitory_management/Public/Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />`;
              } else {
                imgSmall.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                  <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                  <path d="M2 16v-2" />
                  <path d="M22 16v-2" />
                </svg>`;
              }
            }
          }
          
          // อัปเดตข้อมูลห้อง (cell 1)
          if (cells[1]) {
            const tablePriceNote = priceInfo.useCustom
              ? `<div class="room-price-note" style="font-size:0.75rem;color:#94a3b8;margin-top:0.25rem;">ปกติ ${basePriceText} บาท</div>`
              : '';
            cells[1].innerHTML = `ห้อง ${room.room_number}<br>
              <span style="font-size:0.85rem;color:#64748b;font-weight:normal;">
                ${priceText} บาท<br>
                ประเภท: ${room.type_name || '-'}
              </span>
              ${tablePriceNote}`;
            cells[1].style.fontWeight = '600';
            cells[1].style.color = '#0f172a';
          }
        }
      }
      
      // Handle image preview for edit form
      const editRoomImageInput = document.getElementById('edit_room_image');
      if (editRoomImageInput) {
        editRoomImageInput.addEventListener('change', (e) => {
          const file = e.target.files[0];
          const preview = document.getElementById('edit_image_preview');
          
          if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
              preview.innerHTML = `<div style="margin-top:0.5rem;">
                <img src="${event.target.result}" alt="Preview" style="max-width:100%; height:auto; border-radius:8px; max-height:200px;">
                <div style="color:#22c55e; margin-top:0.5rem;">✓ ${file.name}</div>
              </div>`;
            };
            reader.readAsDataURL(file);
          }
        });
      }
      
      async function deleteRoom(roomId, roomNumber) {
        const confirmed = await showConfirmDialog(
          'ยืนยันการลบห้อง',
          `คุณต้องการลบห้อง <strong>"${roomNumber}"</strong> หรือไม่?<br><br>การดำเนินการนี้ไม่สามารถย้อนกลับได้`
        );
        
        if (!confirmed) return;
        
        try {
          const formData = new FormData();
          formData.append('room_id', roomId);
          
          const response = await fetch('../Manage/delete_room.php', {
            method: 'POST',
            body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
            // ลบแถว HTML ของห้องออก (card view)
            const card = document.querySelector(`.room-card[data-room-id="${roomId}"]`);
            if (card) {
              card.remove();
            }
            
            // ลบแถว HTML ของห้องออก (table view)
            const row = document.querySelector(`tr[data-room-id="${roomId}"]`);
            if (row) {
              row.remove();
            }
            
            // refresh หมายเลขห้องถัดไป
            updateNextRoomNumber();
            
            // แสดง toast สำเร็จ
            showSuccessToast(data.message);
          } else {
            showErrorToast(data.message);
          }
        } catch (error) {
          console.error('Error:', error);
          showErrorToast('เกิดข้อผิดพลาดในการลบห้อง');
        }
      }

      // Add new room type (inline prompt + AJAX)
      function addRoomTypeFlow() {
        const name = prompt('ชื่อประเภทห้อง');
        if (!name) return;
        const priceRaw = prompt('ราคา/เดือน (ตัวเลขเท่านั้น)');
        if (!priceRaw) return;
        const price = priceRaw.replace(/[^0-9]/g, '');
        if (!price) { showErrorToast('กรุณากรอกราคาเป็นตัวเลข'); return; }

        const formData = new FormData();
        formData.append('type_name', name.trim());
        formData.append('type_price', price);

        fetch('../Manage/add_room_type.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (!data.success) throw new Error(data.message || 'เพิ่มประเภทห้องไม่สำเร็จ');
          const { id, label } = data;
          const addOption = (selectId) => {
            const sel = document.getElementById(selectId);
            if (!sel) return;
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = label;
            sel.appendChild(opt);
            sel.value = id;
          };
          addOption('type_id');
          addOption('edit_type_id');
          showSuccessToast('เพิ่มประเภทห้องเรียบร้อยแล้ว');
        })
        .catch(err => {
          console.error(err);
          showErrorToast(err.message || 'เพิ่มประเภทห้องไม่สำเร็จ');
        });
      }

      // Delete room type (current selection)
      async function deleteRoomTypeFlow(selectId) {
        const sel = document.getElementById(selectId);
        if (!sel || !sel.value) { showErrorToast('เลือกประเภทห้องที่จะลบก่อน'); return; }
        const opt = sel.options[sel.selectedIndex];
        
        const confirmed = await showConfirmDialog(
          'ยืนยันการลบประเภทห้อง',
          `คุณต้องการลบประเภทห้อง <strong>"${opt.text}"</strong> หรือไม่?<br><br>การดำเนินการนี้ไม่สามารถย้อนกลับได้`
        );
        
        if (!confirmed) return;

        const formData = new FormData();
        formData.append('type_id', sel.value);

        fetch('../Manage/delete_room_type.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (!data.success) throw new Error(data.message || 'ลบประเภทห้องไม่สำเร็จ');
          // remove from both selects
          ['type_id', 'edit_type_id'].forEach(id => {
            const s = document.getElementById(id);
            if (!s) return;
            [...s.options].forEach((o, idx) => {
              if (o.value === sel.value) s.remove(idx);
            });
            s.value = '';
          });
          showSuccessToast('ลบประเภทห้องเรียบร้อยแล้ว');
        })
        .catch(err => {
          console.error(err);
          showErrorToast(err.message || 'ลบประเภทห้องไม่สำเร็จ');
        });
      }

      document.getElementById('addTypeBtn')?.addEventListener('click', addRoomTypeFlow);
      document.getElementById('addTypeBtnEdit')?.addEventListener('click', addRoomTypeFlow);
      document.getElementById('deleteTypeBtn')?.addEventListener('click', () => deleteRoomTypeFlow('type_id'));
      document.getElementById('deleteTypeBtnEdit')?.addEventListener('click', () => deleteRoomTypeFlow('edit_type_id'));
      
      // Wire up edit buttons - highest priority capture to block animate-ui
      const invokeEdit = (btn, evt) => {
        if (!btn) return;
        // Remove any animate-ui modal overlays immediately
        document.querySelectorAll('.animate-ui-modal-overlay').forEach(el => {
          el.style.display = 'none';
          el.remove();
        });
        try { evt?.stopImmediatePropagation?.(); } catch (err) {}
        try { evt?.stopPropagation?.(); } catch (err) {}
        evt?.preventDefault?.();
        const id = btn.getAttribute('data-room-id');
        if (id) {
          try { editRoom(id); } catch (err) { console.error('editRoom failed', err); }
        }
      };
      // Use capture phase to intercept before animate-ui
      document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.animate-ui-action-btn.edit');
        if (!btn) return;
        invokeEdit(btn, e);
      }, { capture: true });
      // Also bind directly on each button
      document.querySelectorAll('.animate-ui-action-btn.edit').forEach(btn => {
        btn.addEventListener('click', (evt) => invokeEdit(btn, evt), { capture: true });
      });
      // Toast helper for inline flows (fallbacks to alert if CSS/JS not ready)
      const toast = (msg) => {
        if (typeof window.showToast === 'function') {
          try { window.showToast('แจ้งเตือน', msg); return; } catch (err) { console.warn(err); }
        }
        const existing = document.querySelector('.animate-ui-toast');
        if (existing) existing.remove();
        const el = document.createElement('div');
        el.className = 'animate-ui-toast';
        el.textContent = msg;
        document.body.appendChild(el);
        const hide = () => {
          el.style.animation = 'toastOut 260ms ease forwards';
          setTimeout(() => {
            if (el.parentNode) el.parentNode.removeChild(el);
          }, 260);
        };
        el.addEventListener('click', hide);
        setTimeout(hide, 3000);
      };
      
      // Handle add room form with AJAX
      const addRoomForm = document.getElementById('addRoomForm');
      if (addRoomForm) {
        const defaultTypeId = '<?php echo $defaultTypeId; ?>';
        const typeSelectEl = document.getElementById('type_id');
        if (typeSelectEl && defaultTypeId && !typeSelectEl.value) {
          typeSelectEl.value = defaultTypeId;
        }
        
        // Form validation function per ui-standard.md
        function validateRoomForm(form) {
          let isValid = true;
          const roomNumber = form.querySelector('[name="room_number"]');
          const typeId = form.querySelector('[name="type_id"]');
          
          // Validate room_number
          const roomNumberGroup = roomNumber.closest('.room-form-group');
          if (!roomNumber.value.trim()) {
            roomNumberGroup.classList.add('has-error');
            roomNumber.setAttribute('aria-invalid', 'true');
            isValid = false;
          } else if (!/^\d{1,2}$/.test(roomNumber.value.trim())) {
            roomNumberGroup.classList.add('has-error');
            roomNumber.setAttribute('aria-invalid', 'true');
            isValid = false;
          } else {
            roomNumberGroup.classList.remove('has-error');
            roomNumber.setAttribute('aria-invalid', 'false');
          }
          
          // Validate type_id
          const typeIdGroup = typeId.closest('.room-form-group');
          if (!typeId.value) {
            typeIdGroup.classList.add('has-error');
            typeId.setAttribute('aria-invalid', 'true');
            isValid = false;
          } else {
            typeIdGroup.classList.remove('has-error');
            typeId.setAttribute('aria-invalid', 'false');
          }
          
          return isValid;
        }
        
        addRoomForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          
          // Validate form before submission
          if (!validateRoomForm(addRoomForm)) {
            showErrorToast('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            return;
          }
          
          try {
            const formData = new FormData(addRoomForm);
            const response = await fetch('../Manage/add_room.php', {
              method: 'POST',
              body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
              showSuccessToast(data.message);
              
              // ปิด modal ถ้ามี
              const modal = addRoomForm.closest('[class*="modal"]');
              if (modal) modal.remove();
              document.querySelectorAll('.animate-ui-modal-overlay').forEach(el => el.remove());
              
              // รีข้อมูลฟอร์ม
              addRoomForm.reset();
              // Clear error states
              addRoomForm.querySelectorAll('.room-form-group').forEach(group => {
                group.classList.remove('has-error');
                const input = group.querySelector('input, select');
                if (input) input.setAttribute('aria-invalid', 'false');
              });
              
              // รีหมายเลขห้องถัดไป - ตั้งค่าใหม่หลังจาก reset()
              const roomNumberInputAfterReset = document.getElementById('room_number');
              if (roomNumberInputAfterReset && data.nextRoomNumber) {
                roomNumberInputAfterReset.value = data.nextRoomNumber;
              }
              
              // เพิ่มห้องใหม่ลงในตารางและการ์ด
              if (data.room) {
                addRoomToDisplay(data.room);
              }
            } else {
              showErrorToast(data.message);
            }
          } catch (error) {
            console.error('Error:', error);
            showErrorToast('เกิดข้อผิดพลาดในการเพิ่มห้อง');
          }
        });
        
        // Handle form reset to clear error states
        addRoomForm.addEventListener('reset', (e) => {
          addRoomForm.querySelectorAll('.room-form-group').forEach(group => {
            group.classList.remove('has-error');
            const input = group.querySelector('input, select');
            if (input) input.setAttribute('aria-invalid', 'false');
          });
        });
      }
      
      // Function to add room to display
      function addRoomToDisplay(room) {
        // Remove empty state if exists
        const emptyState = document.querySelector('.room-empty');
        if (emptyState) {
          emptyState.remove();
        }

        const priceInfo = getRoomPriceInfo(room);
        const priceText = new Intl.NumberFormat('th-TH').format(priceInfo.displayPrice || 0);
        const basePriceText = new Intl.NumberFormat('th-TH').format(priceInfo.typePrice || 0);
        const priceNote = priceInfo.useCustom
          ? `<div class="room-price-note" style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.5rem;">ปกติ ${basePriceText} บาท</div>`
          : '';
        
        // เพิ่มลงในการ์ดมุมมอง (grid view)
        const roomsGrid = document.getElementById('roomsGrid');
        if (roomsGrid) {
          const cardHTML = `
            <div class="room-card" data-room-id="${room.room_id}" data-room-number="${room.room_number}">
              <div class="room-card-image room-card-image-upload" onclick="triggerImageUpload(${room.room_id})" style="cursor: pointer; position: relative;" title="คลิกเพื่ออัปโหลดรูปภาพ">
                ${room.room_image ? 
                  `<img src="/dormitory_management/Public/Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />` :
                  `<div class="placeholder-upload-hint" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; flex-direction: column; gap: 0.5rem;">
                    <svg class="placeholder-upload-icon" xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                      <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                      <path d="M2 16v-2" />
                      <path d="M22 16v-2" />
                    </svg>
                    <span class="placeholder-upload-text" style="font-size: 0.75rem; text-align: center;">คลิกเพื่ออัปโหลด</span>
                  </div>`
                }
                <div class="image-upload-overlay">
                  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="upload-icon">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                  </svg>
                  <span class="upload-text">อัปโหลดรูป</span>
                </div>
                <input type="file" id="imageInput_${room.room_id}" accept="image/*" style="display: none;" onchange="uploadRoomImage(${room.room_id}, this)">
              </div>
              <div class="room-card-content">
                <h3 class="room-card-number">ห้อง ${room.room_number}</h3>
                <div class="room-card-meta">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                  ${room.type_name || '-'} • ${priceText} บาท/เดือน
                </div>
                ${priceNote}
                <div class="room-card-status vacant">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  ว่าง
                </div>
                <div class="room-card-actions">
                  <button type="button" class="btn btn-edit" onclick="editRoom(${room.room_id})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    แก้ไข
                  </button>
                  <button type="button" class="btn btn-delete" onclick="deleteRoom(${room.room_id}, '${room.room_number}')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    ลบ
                  </button>
                </div>
              </div>
            </div>
          `;
          roomsGrid.insertAdjacentHTML('afterbegin', cardHTML);
        }
        
        // เพิ่มลงในตารางมุมมอง (table view)
        const roomsTable = document.getElementById('roomsTable');
        if (roomsTable) {
          const tableBody = roomsTable.querySelector('tbody');
          if (tableBody) {
            const rowHTML = `
              <tr class="room-row" data-room-id="${room.room_id}" data-room-number="${room.room_number}">
                <td>
                  <div class="room-image-small">
                    ${room.room_image ?
                      `<img src="/dormitory_management/Public/Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />` :
                      `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                        <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                        <path d="M2 16v-2" />
                        <path d="M22 16v-2" />
                      </svg>`
                    }
                  </div>
                </td>
                <td style="font-weight:600;color:#0f172a;">
                  ห้อง ${room.room_number}<br>
                  <span style="font-size:0.85rem;color:#64748b;font-weight:normal;">
                    ${priceText} บาท<br>
                    ประเภท: ${room.type_name || '-'}
                  </span>
                  ${priceInfo.useCustom ? `<div class="room-price-note" style="font-size:0.75rem;color:#94a3b8;margin-top:0.25rem;">ปกติ ${basePriceText} บาท</div>` : ''}
                </td>
                <td>
                  <span class="room-card-status vacant">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    ว่าง
                  </span>
                </td>
                <td>
                  <div class="room-card-actions">
                    <button type="button" class="btn btn-edit" onclick="editRoom(${room.room_id})">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      แก้ไข
                    </button>
                    <button type="button" class="btn btn-delete" onclick="deleteRoom(${room.room_id}, '${room.room_number}')">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      ลบ
                    </button>
                  </div>
                </td>
              </tr>
            `;
            tableBody.insertAdjacentHTML('afterbegin', rowHTML);
          }
        }
      }

      
      // Close modal when clicking outside
      document.getElementById('editModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
      });

      // View Toggle Function
      let currentView = 'grid'; // 'grid' or 'table'
      
      function toggleView() {
        const gridView = document.getElementById('roomsGrid');
        const tableView = document.getElementById('roomsTable');
        const toggleText = document.getElementById('viewToggleText');
        const toggleBtn = document.getElementById('viewToggleBtn');
        
        if (currentView === 'grid') {
          // Switch to table view
          gridView.style.display = 'none';
          tableView.style.display = 'block';
          toggleText.textContent = 'มุมมองการ์ด';
          toggleBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg><span id="viewToggleText">มุมมองการ์ด</span>`;
          currentView = 'table';
          try { localStorage.setItem('roomsView', 'table'); } catch (e) {}
        } else {
          // Switch to grid view
          gridView.style.display = 'grid';
          tableView.style.display = 'none';
          toggleText.textContent = 'มุมมองแถว';
          toggleBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span id="viewToggleText">มุมมองแถว</span>`;
          currentView = 'grid';
          try { localStorage.setItem('roomsView', 'grid'); } catch (e) {}
        }
      }

      function changeSortBy(sortValue) {
        // ใช้ pushState เพื่ออัปเดต URL เงียบๆ ไม่รีเฟรชหน้า
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.history.pushState({}, '', url.toString());

        const sortComparer = (a, b) => {
          if (sortValue === 'newest') {
            return parseInt(b.dataset.roomId || 0) - parseInt(a.dataset.roomId || 0);
          } else if (sortValue === 'oldest') {
            return parseInt(a.dataset.roomId || 0) - parseInt(b.dataset.roomId || 0);
          } else if (sortValue === 'room_number') {
            return String(a.dataset.roomNumber || '').localeCompare(String(b.dataset.roomNumber || ''), undefined, {numeric: true});
          }
          return 0;
        };

        // จัดเรียงมุมมองการ์ด
        const grid = document.getElementById('roomsGrid');
        if (grid) {
          const cards = Array.from(grid.querySelectorAll('.room-card'));
          cards.sort(sortComparer);
          
          cards.forEach((card, index) => {
            if (index < visibleRooms) {
              card.classList.remove('hidden-card');
            } else {
              card.classList.add('hidden-card');
            }
            grid.appendChild(card);
          });
        }

        // จัดเรียงมุมมองตาราง
        const roomsTable = document.getElementById('roomsTable');
        if (roomsTable) {
          const tableBody = roomsTable.querySelector('tbody');
          if (tableBody) {
            const rows = Array.from(tableBody.querySelectorAll('tr.room-row'));
            rows.sort(sortComparer);
            
            rows.forEach((row, index) => {
              if (index < visibleRooms) {
                row.classList.remove('hidden-row');
              } else {
                row.classList.add('hidden-row');
              }
              tableBody.appendChild(row);
            });
          }
        }
      }
      
      // Restore saved view on page load
      document.addEventListener('DOMContentLoaded', () => {
        try {
          const savedView = localStorage.getItem('roomsView');
          if (savedView === 'table' && currentView === 'grid') {
            toggleView();
          }
        } catch (e) {}
      });

      // Update next room number after delete
      function updateNextRoomNumber() {
        const roomNumberInput = document.getElementById('room_number');
        if (!roomNumberInput) return;

        // ดึงหมายเลขห้องทั้งหมดจากหน้า (card view และ table view)
        const roomNumbers = [];
        
        // จากการ์ด
        document.querySelectorAll('.room-card[data-room-id]').forEach(card => {
          const numberText = card.querySelector('.room-card-number')?.textContent || '';
          const match = numberText.match(/(\d+)/);
          if (match) roomNumbers.push(parseInt(match[1]));
        });
        
        // จากตาราง
        document.querySelectorAll('tr[data-room-id]').forEach(row => {
          const cells = row.querySelectorAll('td');
          if (cells[1]) {
            const match = cells[1].textContent.match(/(\d+)/);
            if (match) roomNumbers.push(parseInt(match[1]));
          }
        });

        // หา max room number
        const maxRoomNumber = roomNumbers.length > 0 ? Math.max(...roomNumbers) : 0;
        const nextRoomNumber = String(maxRoomNumber + 1).padStart(2, '0');
        
        roomNumberInput.value = nextRoomNumber;
      }

      // Load More Rooms Function (supports both grid and table views)
      let visibleRooms = 20;
      const ROOMS_PER_LOAD = 10;

      function loadMoreRooms() {
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        const remainingCountEl = document.getElementById('remainingCount');

        if (currentView === 'grid') {
          const hiddenCards = document.querySelectorAll('.room-card.hidden-card');
          const totalCards = document.querySelectorAll('.room-card').length;
          let shown = 0;
          hiddenCards.forEach(card => {
            if (shown < ROOMS_PER_LOAD) {
              card.classList.remove('hidden-card');
              shown++;
              visibleRooms++;
            }
          });

          const remaining = totalCards - visibleRooms;
          if (remainingCountEl) remainingCountEl.textContent = remaining;
          if (remaining <= 0) loadMoreBtn?.classList.add('hidden');
        } else {
          // table view
          const hiddenRows = document.querySelectorAll('.room-row.hidden-row');
          const totalRows = document.querySelectorAll('.room-row').length;
          let showCount = 0;
          hiddenRows.forEach((row) => {
            if (showCount < ROOMS_PER_LOAD) {
              row.classList.remove('hidden-row');
              showCount++;
              visibleRooms++;
            }
          });

          const remaining = totalRows - visibleRooms;
          if (remainingCountEl) remainingCountEl.textContent = remaining;
          if (remaining <= 0) loadMoreBtn?.classList.add('hidden');
        }

        // Save count
        try { localStorage.setItem('visibleRoomsCount', visibleRooms); } catch (e) {}
      }

      // Restore visible rooms count on page load for both views
      document.addEventListener('DOMContentLoaded', () => {
        try {
          const savedVisibleCount = localStorage.getItem('visibleRoomsCount');
          if (savedVisibleCount) {
            const targetCount = parseInt(savedVisibleCount);
            visibleRooms = targetCount;

            // Restore grid cards
            const totalCards = document.querySelectorAll('.room-card').length;
            if (totalCards > 0) {
              const toShow = Math.max(0, targetCount - 5);
              const hiddenCards = document.querySelectorAll('.room-card.hidden-card');
              let shown = 0;
              hiddenCards.forEach(card => {
                if (shown < toShow) { card.classList.remove('hidden-card'); shown++; }
              });
              const remaining = totalCards - visibleRooms;
              const remainingCountEl = document.getElementById('remainingCount');
              const loadMoreBtn = document.getElementById('loadMoreBtn');
              if (remainingCountEl) remainingCountEl.textContent = remaining;
              if (remaining <= 0) loadMoreBtn?.classList.add('hidden');
            }

            // Restore table rows
            const totalRows = document.querySelectorAll('.room-row').length;
            if (totalRows > 0) {
              const toShowRows = Math.max(0, targetCount - 5);
              const hiddenRows = document.querySelectorAll('.room-row.hidden-row');
              let shownRows = 0;
              hiddenRows.forEach(row => {
                if (shownRows < toShowRows) { row.classList.remove('hidden-row'); shownRows++; }
              });
              const remaining = totalRows - visibleRooms;
              const remainingCountEl = document.getElementById('remainingCount');
              const loadMoreBtn = document.getElementById('loadMoreBtn');
              if (remainingCountEl) remainingCountEl.textContent = remaining;
              if (remaining <= 0) loadMoreBtn?.classList.add('hidden');
            }
          }
        } catch (e) {}
      });
    </script>
    <script src="/dormitory_management/Public/Assets/Javascript/toast-notification.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/confirm-modal.js"></script>
    <script>
      // Image Upload Functions
      function triggerImageUpload(roomId, roomNumber) {
        const fileInput = document.getElementById('imageInput_' + roomId);
        if (fileInput) {
          fileInput.click();
        }
      }

      function uploadRoomImage(roomId, fileInput) {
        const file = fileInput.files[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('image/')) {
          showErrorToast('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
          return;
        }

        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
          showErrorToast('ขนาดไฟล์ต้องไม่เกิน 5MB');
          return;
        }

        const formData = new FormData();
        formData.append('room_id', roomId);
        formData.append('room_image', file);

        // Show loading state
        const imageDiv = fileInput.parentElement;
        const originalContent = imageDiv.innerHTML;
        imageDiv.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: #64748b;"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2-8.83"/></svg></div>';

        fetch('../Manage/upload_room_image.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (!data.success) {
            imageDiv.innerHTML = originalContent;
            showErrorToast(data.error || 'เกิดข้อผิดพลาดในการอัปโหลด');
            return;
          }
          
          // Update the image without reloading page
          showSuccessToast('อัปโหลดรูปภาพสำเร็จ');
          
          // Create new image element
          const newImageUrl = '/dormitory_management/Public/Assets/Images/Rooms/' + data.filename + '?t=' + Date.now();
          const newImg = document.createElement('img');
          newImg.src = newImageUrl;
          newImg.style.width = '100%';
          newImg.style.height = '100%';
          newImg.style.objectFit = 'cover';
          newImg.style.transition = 'transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
          
          // Replace image content
          imageDiv.innerHTML = '';
          imageDiv.appendChild(newImg);

          imageDiv.classList.add('room-card-image-upload');
          imageDiv.setAttribute('onclick', `triggerImageUpload(${roomId})`);
          imageDiv.setAttribute('title', 'คลิกเพื่ออัปโหลดรูปภาพ');
          imageDiv.style.cursor = 'pointer';
          imageDiv.style.position = 'relative';
          
          // Re-add the overlay and file input
          const overlay = document.createElement('div');
          overlay.className = 'image-upload-overlay';
          overlay.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="upload-icon">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="17 8 12 3 7 8" />
              <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
            <span class="upload-text">อัปโหลดรูป</span>
          `;
          imageDiv.appendChild(overlay);

          const newFileInput = document.createElement('input');
          newFileInput.type = 'file';
          newFileInput.id = 'imageInput_' + roomId;
          newFileInput.accept = 'image/*';
          newFileInput.style.display = 'none';
          newFileInput.onchange = function() { uploadRoomImage(roomId, this); };
          imageDiv.appendChild(newFileInput);
          
          // Reset file input
          fileInput.value = '';
        })
        .catch(err => {
          imageDiv.innerHTML = originalContent;
          console.error('Upload error:', err);
          showErrorToast('เกิดข้อผิดพลาดในการอัปโหลด');
        });

        // Reset file input
        fileInput.value = '';
      }

      // CSS for spinner animation
      const style = document.createElement('style');
      style.textContent = `
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `;
      document.head.appendChild(style);

      // Restore all details elements state on this page
      (function() {
        // Restore immediately
        document.querySelectorAll('details').forEach(function(details) {
          const id = details.id || details.querySelector('summary')?.textContent?.trim();
          if (id) {
            const key = 'details_' + id.replace(/[^a-zA-Z0-9]/g, '_');
            const savedState = localStorage.getItem(key);
            if (savedState === 'open') {
              details.setAttribute('open', '');
            } else if (savedState === 'closed') {
              details.removeAttribute('open');
            }
          }
        });
      })();
    </script>
  </body>
</html>
