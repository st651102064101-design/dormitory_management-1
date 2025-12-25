<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ซิงก์สถานะห้องอัตโนมัติจากสัญญาและการจอง
// room_status: 0 = ว่าง, 1 = ไม่ว่าง (มีจอง หรือ มีสัญญา)
try {
  // ตั้งค่าห้องทั้งหมดเป็นว่าง (0) ก่อน
  $pdo->exec("UPDATE room SET room_status = '0'");
  
  // ปรับสถานะเป็น ไม่ว่าง (1) สำหรับห้องที่มีสัญญาที่ใช้งาน (ctr_status 0/2 = มีผู้เช่า)
  $pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (SELECT 1 FROM contract c WHERE c.room_id = room.room_id AND c.ctr_status IN ('0','2'))");
  
  // ปรับสถานะเป็น ไม่ว่าง (1) สำหรับห้องที่มีการจองที่ยังไม่ยกเลิก (bkg_status 1/2 = จองแล้ว หรือ เข้าพักแล้ว)
  $pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (SELECT 1 FROM booking b WHERE b.room_id = room.room_id AND b.bkg_status IN ('1','2'))");
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
    SELECT r.room_id, r.room_number, r.room_status, r.room_image, r.type_id, rt.type_name, rt.type_price
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
} catch (PDOException $e) {}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการห้องพัก</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="../Assets/Css/datatable-modern.css" />
    <style>
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
      
      .room-card-actions .btn-edit {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
      }
      
      .room-card-actions .btn-edit:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(37,99,235,0.35);
      }
      
      .room-card-actions .btn-delete {
        background: rgba(239,68,68,0.1);
        color: #f87171;
        border: 1px solid rgba(239,68,68,0.2);
      }
      
      .room-card-actions .btn-delete:hover {
        background: rgba(239,68,68,0.2);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239,68,68,0.2);
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
      
      .add-type-row { display:flex; align-items:center; gap:0.5rem; }
      
      .add-type-btn {
        padding: 0.9rem 1.1rem;
        border-radius: 12px;
        border: 1px dashed rgba(96,165,250,0.4);
        background: rgba(59,130,246,0.08);
        color: #60a5fa;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.25s ease;
      }
      
      .add-type-btn:hover {
        background: rgba(59,130,246,0.15);
        border-color: rgba(96,165,250,0.6);
        transform: translateY(-1px);
      }
      
      .delete-type-btn {
        border-color: rgba(248,113,113,0.4);
        color: #f87171;
        background: rgba(239,68,68,0.08);
      }
      
      .delete-type-btn:hover {
        background: rgba(239,68,68,0.15);
        border-color: rgba(248,113,113,0.6);
      }
      
      .room-form-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
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
      
      .booking-form-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.75rem;
      }
      
      .btn-submit {
        flex: 1;
        padding: 1rem 1.5rem;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        border-radius: 14px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      
      .btn-submit:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        box-shadow: 0 10px 30px rgba(37,99,235,0.4);
        transform: translateY(-2px);
      }
      
      .btn-cancel {
        flex: 1;
        padding: 1rem 1.5rem;
        background: rgba(100,116,139,0.15);
        color: #94a3b8;
        border: 1px solid rgba(148,163,184,0.2);
        border-radius: 14px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      
      .btn-cancel:hover {
        background: rgba(100,116,139,0.25);
        border-color: rgba(148,163,184,0.4);
      }
      
      /* View Toggle */
      .view-toggle-btn {
        padding: 0.7rem 1.3rem;
        background: rgba(255,255,255,0.05);
        color: #94a3b8;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .view-toggle-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #f8fafc;
        border-color: rgba(255,255,255,0.2);
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
      
      /* Load More Button */
      .load-more-container {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.06);
      }
      
      .load-more-btn {
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
      }
      
      .load-more-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #f8fafc;
        border-color: rgba(255,255,255,0.2);
        transform: translateY(-2px);
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
    </style>
  </head>
  <body class="reports-page" data-disable-edit-modal="true">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
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
              <div class="room-stat-card">
                <h3>ห้องทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($totalRooms); ?></div>
              </div>
              <div class="room-stat-card">
                <h3>ห้องว่าง</h3>
                <div class="stat-value"><?php echo number_format($vacant); ?></div>
              </div>
              <div class="room-stat-card">
                <h3>มีผู้เข้าพัก</h3>
                <div class="stat-value"><?php echo number_format($occupied); ?></div>
              </div>
            </div>
          </section>

          <!-- Toggle button for room form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleRoomFormBtn" style="white-space:nowrap;padding:0.85rem 1.5rem;cursor:pointer;font-size:0.95rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#94a3b8;border-radius:12px;transition:all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);font-weight:600;display:inline-flex;align-items:center;gap:0.5rem;" onclick="toggleRoomForm()" onmouseover="this.style.background='rgba(255,255,255,0.1)';this.style.borderColor='rgba(255,255,255,0.2)';this.style.color='#f8fafc'" onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='#94a3b8'">
              <span id="toggleRoomFormIcon">▼</span> <span id="toggleRoomFormText">ซ่อนฟอร์ม</span>
            </button>
          </div>

          <section class="manage-panel" id="addRoomSection">
            <div class="section-header">
              <div>
                <h1>เพิ่มห้องพัก</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">สร้างห้องพัก</p>
              </div>
            </div>
            <form id="addRoomForm" enctype="multipart/form-data">
              <div class="room-form">
                <div class="room-form-group">
                  <label for="room_number">หมายเลขห้อง <span style="color:#f87171;">*</span></label>
                  <input type="text" id="room_number" name="room_number" required maxlength="2" placeholder="เช่น 01, 02, ..." value="<?php echo htmlspecialchars($nextRoomNumber); ?>" />
                </div>
                <div class="room-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                  <div>
                    <div class="add-type-row">
                      <label for="type_id" style="margin:0;">ประเภทห้อง <span style="color:#f87171;">*</span></label>
                      <button type="button" class="add-type-btn" id="addTypeBtn">+ เพิ่มประเภทห้อง</button>
                      <button type="button" class="add-type-btn delete-type-btn" id="deleteTypeBtn">ลบประเภทห้อง</button>
                    </div>
                    <select id="type_id" name="type_id" required>
                      <?php foreach ($roomTypes as $index => $type): ?>
                        <option value="<?php echo $type['type_id']; ?>" <?php echo ($index === 0 ? 'selected' : ''); ?>>
                          <?php echo htmlspecialchars($type['type_name']); ?> (<?php echo number_format($type['type_price']); ?> บาท/เดือน)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                   <div>
                     <label style="display:block;">สถานะห้อง</label>
                     <div style="padding:0.9rem 0.85rem; border-radius:10px; border:1px dashed rgba(255,255,255,0.25); color:#cbd5e1; font-size:0.9rem; line-height:1.5;">
                       <strong>ระบบจะปรับสถานะอัตโนมัติ</strong><br>
                       <span style="display:block; margin-top:0.4rem;">
                         <span style="color:#22c55e;">✓ ว่าง (0)</span> - ไม่มีใครจอง หรือเช่า<br>
                         <span style="color:#ef4444;">✗ ไม่ว่าง (1)</span> - มีคนจองอยู่ หรือ มีคนเช่าอยู่
                       </span>
                     </div>
                   </div>
                </div>
                <div class="room-form-group">
                  <label for="room_image">รูปภาพห้อง</label>
                  <input type="file" id="room_image" name="room_image" accept="image/*" />
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
                <p style="color:#94a3b8;margin-top:0.2rem;">ห้องพักและข้อมูลทั้งหมด</p>
              </div>
              <div style="display:flex;gap:0.75rem;align-items:center;">
                <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.05);color:#f5f8ff;font-size:0.95rem;cursor:pointer;">
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
                  <div class="room-card <?php echo $index >= 5 ? 'hidden-card' : ''; ?>" data-room-id="<?php echo $room['room_id']; ?>">
                    <div class="room-card-image">
                      <?php if (!empty($room['room_image'])): ?>
                        <img src="../Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" />
                      <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                          <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                          <path d="M2 16v-2" />
                          <path d="M22 16v-2" />
                        </svg>
                      <?php endif; ?>
                    </div>
                    <div class="room-card-content">
                      <h3 class="room-card-number">ห้อง <?php echo htmlspecialchars($room['room_number']); ?></h3>
                      <div class="room-card-meta">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <?php echo htmlspecialchars($room['type_name'] ?? '-'); ?> • <?php echo number_format($room['type_price'] ?? 0); ?> บาท/เดือน
                      </div>
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
                        <div style="font-size: 0.75rem; margin-bottom: 0.75rem; color: #fbbf24; opacity: 0.8;">
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
                        </button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <?php if (count($rooms) > 5): ?>
              <div class="load-more-container">
                <button type="button" class="load-more-btn" id="loadMoreBtn" onclick="loadMoreRooms()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  <span id="loadMoreText">โหลดเพิ่มเติม (<span id="remainingCount"><?php echo count($rooms) - 5; ?></span> ห้อง)</span>
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
                      <tr class="room-row <?php echo $index >= 5 ? 'hidden-row' : ''; ?>" data-index="<?php echo $index; ?>" data-room-id="<?php echo $room['room_id']; ?>">
                        <td>
                          <div class="room-image-small">
                            <?php if (!empty($room['room_image'])): ?>
                              <img src="../Assets/Images/Rooms/<?php echo htmlspecialchars($room['room_image']); ?>" alt="ห้อง <?php echo htmlspecialchars($room['room_number']); ?>" />
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
                        <td style="font-weight:600;color:#f5f8ff;">
                          ห้อง <?php echo htmlspecialchars($room['room_number']); ?><br>
                          <span style="font-size:0.85rem;color:#cbd5e1;font-weight:normal;">
                            <?php echo number_format($room['type_price'] ?? 0); ?> บาท<br>
                            ประเภท: <?php echo htmlspecialchars($room['type_name'] ?? '-'); ?>
                          </span>
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
                            <div style="font-size: 0.85rem; margin-top: 0.3rem; color: #fbbf24;">
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
        <form id="editForm" method="POST" action="../Manage/update_room.php" enctype="multipart/form-data">
          <input type="hidden" name="room_id" id="edit_room_id">
          
          <div class="booking-form-group">
            <label>หมายเลขห้อง: <span style="color: red;">*</span></label>
            <input type="text" name="room_number" id="edit_room_number" required maxlength="2">
          </div>
          
          <div class="booking-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <div class="add-type-row" style="margin-bottom:0.35rem;">
                <label style="margin:0;">ประเภทห้อง: <span style="color: red;">*</span></label>
                <button type="button" class="add-type-btn" id="addTypeBtnEdit">+ เพิ่มประเภทห้อง</button>
                <button type="button" class="add-type-btn delete-type-btn" id="deleteTypeBtnEdit">ลบประเภทห้อง</button>
              </div>
              <select name="type_id" id="edit_type_id" required>
                <option value="">-- เลือกประเภทห้อง --</option>
                <?php foreach ($roomTypes as $type): ?>
                  <option value="<?php echo $type['type_id']; ?>">
                    <?php echo htmlspecialchars($type['type_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          
          <div class="booking-form-group">
            <label>รูปภาพห้อง:</label>
            <input type="file" name="room_image" id="edit_room_image" accept="image/*">
            <input type="hidden" name="delete_image" id="delete_image" value="0">
            <div id="edit_image_preview" style="margin-top:0.5rem; color:#94a3b8; font-size:0.85rem;"></div>
            <div id="delete_image_btn_container" style="margin-top:0.75rem; display:none;">
              <button type="button" id="deleteImageBtn" onclick="deleteCurrentImage()" style="padding:0.6rem 1.2rem; background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.3); border-radius:10px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:0.4rem; transition:all 0.25s ease;">
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

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4" defer></script>
    <script>
      // Toggle room form visibility
      function toggleRoomForm() {
        const section = document.getElementById('addRoomSection');
        const icon = document.getElementById('toggleRoomFormIcon');
        const text = document.getElementById('toggleRoomFormText');
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
      }

      // Hide animate-ui modal overlays for this page
      document.addEventListener('DOMContentLoaded', () => {
        const overlays = document.querySelectorAll('.animate-ui-modal-overlay');
        overlays.forEach(el => {
          el.style.display = 'none';
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

      function editRoom(roomId) {
        const room = roomsData.find(r => r.room_id == roomId);
        if (!room) return;
        
        document.getElementById('edit_room_id').value = room.room_id;
        document.getElementById('edit_room_number').value = room.room_number;
        document.getElementById('edit_type_id').value = room.type_id;
        document.getElementById('delete_image').value = '0';
        
        const preview = document.getElementById('edit_image_preview');
        const deleteBtn = document.getElementById('delete_image_btn_container');
        
        if (room.room_image) {
          preview.innerHTML = `<div style="margin-top:0.5rem;">
            <img src="/Dormitory_Management/Assets/Images/Rooms/${room.room_image}" alt="Room Image" style="max-width:100%; height:auto; border-radius:12px; max-height:200px;">
            <div style="color:#22c55e; margin-top:0.5rem;">✓ ${room.room_image}</div>
          </div>`;
          deleteBtn.style.display = 'block';
        } else {
          preview.innerHTML = '<div style="color:#94a3b8;">ไม่มีรูปภาพ</div>';
          deleteBtn.style.display = 'none';
        }
        
        document.getElementById('editModal').style.display = 'flex';
      }
      window.editRoom = editRoom;
      
      function deleteCurrentImage() {
        document.getElementById('delete_image').value = '1';
        document.getElementById('edit_image_preview').innerHTML = '<div style="color:#f87171; padding:0.75rem; background:rgba(239,68,68,0.1); border-radius:10px; border:1px dashed rgba(239,68,68,0.3);"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; vertical-align:middle; margin-right:0.4rem;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> รูปภาพจะถูกลบเมื่อกดบันทึก</div>';
        document.getElementById('delete_image_btn_container').style.display = 'none';
      }
      window.deleteCurrentImage = deleteCurrentImage;
      
      function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('editForm').reset();
        document.getElementById('delete_image').value = '0';
      }
      
      // Handle Edit Form Submit via AJAX
      const editForm = document.getElementById('editForm');
      if (editForm) {
        editForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          
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
      }
      
      // Function to update room in display without page reload
      function updateRoomInDisplay(room) {
        const roomId = room.room_id;
        
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
            if (room.room_image) {
              imageDiv.innerHTML = `<img src="../Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />`;
            } else {
              imageDiv.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                <path d="M2 16v-2" />
                <path d="M22 16v-2" />
              </svg>`;
            }
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
              ${room.type_name || '-'} • ${new Intl.NumberFormat('th-TH').format(room.type_price || 0)} บาท/เดือน`;
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
                imgSmall.innerHTML = `<img src="../Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />`;
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
            cells[1].innerHTML = `ห้อง ${room.room_number}<br>
              <span style="font-size:0.85rem;color:#cbd5e1;font-weight:normal;">
                ${new Intl.NumberFormat('th-TH').format(room.type_price || 0)} บาท<br>
                ประเภท: ${room.type_name || '-'}
              </span>`;
            cells[1].style.fontWeight = '600';
            cells[1].style.color = '#f5f8ff';
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
        
        addRoomForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          
          const roomNumberInput = document.getElementById('room_number');
          if (!roomNumberInput || !roomNumberInput.value.trim()) {
            showErrorToast('กรุณากรอกหมายเลขห้อง');
            roomNumberInput?.focus();
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
      }
      
      // Function to add room to display
      function addRoomToDisplay(room) {
        // Remove empty state if exists
        const emptyState = document.querySelector('.room-empty');
        if (emptyState) {
          emptyState.remove();
        }
        
        // เพิ่มลงในการ์ดมุมมอง (grid view)
        const roomsGrid = document.getElementById('roomsGrid');
        if (roomsGrid) {
          const cardHTML = `
            <div class="room-card" data-room-id="${room.room_id}">
              <div class="room-card-image">
                ${room.room_image ? 
                  `<img src="../Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />` :
                  `<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                    <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                    <path d="M2 16v-2" />
                    <path d="M22 16v-2" />
                  </svg>`
                }
              </div>
              <div class="room-card-content">
                <h3 class="room-card-number">ห้อง ${room.room_number}</h3>
                <div class="room-card-meta">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                  ${room.type_name || '-'} • ${new Intl.NumberFormat('th-TH').format(room.type_price || 0)} บาท/เดือน
                </div>
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
              <tr class="room-row" data-room-id="${room.room_id}">
                <td>
                  <div class="room-image-small">
                    ${room.room_image ?
                      `<img src="../Assets/Images/Rooms/${room.room_image}" alt="ห้อง ${room.room_number}" />` :
                      `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 12h16a2 2 0 0 1 2 2v4H2v-4a2 2 0 0 1 2-2Z" />
                        <path d="M6 12V7a2 2 0 0 1 2-2h2" />
                        <path d="M2 16v-2" />
                        <path d="M22 16v-2" />
                      </svg>`
                    }
                  </div>
                </td>
                <td style="font-weight:600;color:#f5f8ff;">
                  ห้อง ${room.room_number}<br>
                  <span style="font-size:0.85rem;color:#cbd5e1;font-weight:normal;">
                    ${new Intl.NumberFormat('th-TH').format(room.type_price || 0)} บาท<br>
                    ประเภท: ${room.type_name || '-'}
                  </span>
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
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
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
      let visibleRooms = 5;
      const ROOMS_PER_LOAD = 5;

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
    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
    <script>
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
