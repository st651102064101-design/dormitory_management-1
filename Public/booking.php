<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

// ดึงค่าตั้งค่าระบบ
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#1e40af';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
    }
} catch (PDOException $e) {}

// ดึงห้องว่าง (room_status = '0' คือห้องว่าง)
$availableRooms = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, rt.type_name, rt.type_price 
        FROM room r 
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
        WHERE r.room_status = '0' 
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ");
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ถ้ามีการเลือกห้องมา
$selectedRoom = null;
if (!empty($_GET['room'])) {
    $roomId = (int)$_GET['room'];
    foreach ($availableRooms as $room) {
        if ($room['room_id'] == $roomId) {
            $selectedRoom = $room;
            break;
        }
    }
}

$success = false;
$error = '';

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $idCard = trim($_POST['id_card'] ?? '');
    $moveInDate = $_POST['move_in_date'] ?? '';
    $note = trim($_POST['note'] ?? '');
    
    // Validate
    if (!$roomId || !$name || !$phone) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        try {
            // ตรวจสอบว่าห้องยังว่างอยู่
            $stmt = $pdo->prepare("SELECT room_status FROM room WHERE room_id = ?");
            $stmt->execute([$roomId]);
            $roomStatus = $stmt->fetchColumn();
            
            if ($roomStatus !== '0') {
                $error = 'ขออภัย ห้องนี้ถูกจองไปแล้ว';
            } else {
                // บันทึกการจอง
                $stmt = $pdo->prepare("
                    INSERT INTO booking (room_id, bkg_name, bkg_phone, bkg_email, bkg_id_card, bkg_move_in_date, bkg_note, bkg_status, bkg_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$roomId, $name, $phone, $email, $idCard, $moveInDate, $note]);
                $success = true;
            }
        } catch (PDOException $e) {
            $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองห้องพัก - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #fff;
        }
        
        .header {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem 2rem;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
        }
        
        .logo { display: flex; align-items: center; gap: 1rem; text-decoration: none; }
        .logo img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
        .logo h1 { font-size: 1.25rem; color: #fff; }
        
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-links a {
            color: #94a3b8;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-links a:hover { color: #fff; background: #334155; }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 6rem 1rem 2rem;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        .page-title h2 { font-size: 2rem; margin-bottom: 0.5rem; }
        .page-title p { color: #94a3b8; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #60a5fa;
            text-decoration: none;
            margin-bottom: 2rem;
        }
        .back-link:hover { text-decoration: underline; }
        
        .booking-form {
            background: #1e293b;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #334155;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #94a3b8;
            font-weight: 500;
        }
        
        .form-group label .required {
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .selected-room {
            background: #334155;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .selected-room .room-info h4 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        
        .selected-room .room-info p {
            color: #94a3b8;
        }
        
        .selected-room .room-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #22c55e;
        }
        
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: <?php echo $themeColor; ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            opacity: 0.9;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: #22c55e20;
            border: 1px solid #22c55e;
            color: #22c55e;
        }
        .alert-error {
            background: #ef444420;
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .success-box {
            text-align: center;
            padding: 3rem;
        }
        .success-box .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .success-box h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .success-box p {
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }
        .success-box a {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: <?php echo $themeColor; ?>;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
        }
        
        .no-rooms {
            text-align: center;
            padding: 3rem;
        }
        .no-rooms p {
            color: #94a3b8;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; padding: 1rem; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .booking-form { padding: 1.5rem; }
            .selected-room { flex-direction: column; gap: 1rem; text-align: center; }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="../index.php" class="logo">
            <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
        </a>
        <nav class="nav-links">
            <a href="../index.php">🏠 หน้าแรก</a>
            <a href="rooms.php">🏠 ห้องพัก</a>
            <a href="news.php">📰 ข่าวสาร</a>
            <a href="booking.php">📅 จองห้อง</a>
        </nav>
    </header>

    <div class="container">
        <a href="../index.php" class="back-link">← กลับหน้าแรก</a>
        
        <div class="page-title">
            <h2>📅 จองห้องพัก</h2>
            <p>กรอกข้อมูลเพื่อจองห้องพัก</p>
        </div>
        
        <?php if ($success): ?>
        <div class="booking-form">
            <div class="success-box">
                <div class="icon">✅</div>
                <h3>จองห้องพักสำเร็จ!</h3>
                <p>ทางหอพักจะติดต่อกลับเพื่อยืนยันการจองภายใน 24 ชั่วโมง<br>กรุณารอการติดต่อจากเจ้าหน้าที่</p>
                <a href="../index.php">กลับหน้าแรก</a>
            </div>
        </div>
        
        <?php elseif (count($availableRooms) === 0): ?>
        <div class="booking-form">
            <div class="no-rooms">
                <p>😔 ขณะนี้ห้องพักเต็มทุกห้อง</p>
                <p>กรุณาติดต่อสอบถามหรือลองใหม่ภายหลัง</p>
                <a href="../index.php" style="display:inline-block;padding:0.75rem 1.5rem;background:<?php echo $themeColor; ?>;color:#fff;text-decoration:none;border-radius:8px;">กลับหน้าแรก</a>
            </div>
        </div>
        
        <?php else: ?>
        <div class="booking-form">
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>เลือกห้องพัก <span class="required">*</span></label>
                    <select name="room_id" required>
                        <option value="">-- เลือกห้องที่ต้องการจอง --</option>
                        <?php foreach ($availableRooms as $room): ?>
                        <option value="<?php echo $room['room_id']; ?>" <?php echo $selectedRoom && $selectedRoom['room_id'] == $room['room_id'] ? 'selected' : ''; ?>>
                            ห้อง <?php echo htmlspecialchars($room['room_number']); ?> - <?php echo htmlspecialchars($room['type_name'] ?? 'มาตรฐาน'); ?> (฿<?php echo number_format($room['type_price'] ?? 0); ?>/เดือน)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>ชื่อ-นามสกุล <span class="required">*</span></label>
                    <input type="text" name="name" required placeholder="กรอกชื่อ-นามสกุล">
                </div>
                
                <div class="form-group">
                    <label>เบอร์โทรศัพท์ <span class="required">*</span></label>
                    <input type="tel" name="phone" required placeholder="0812345678">
                </div>
                
                <div class="form-group">
                    <label>อีเมล</label>
                    <input type="email" name="email" placeholder="email@example.com">
                </div>
                
                <div class="form-group">
                    <label>เลขบัตรประชาชน</label>
                    <input type="text" name="id_card" maxlength="13" placeholder="1234567890123">
                </div>
                
                <div class="form-group">
                    <label>วันที่ต้องการเข้าพัก</label>
                    <input type="date" name="move_in_date" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>หมายเหตุ / ข้อความเพิ่มเติม</label>
                    <textarea name="note" placeholder="ระบุข้อความเพิ่มเติม (ถ้ามี)"></textarea>
                </div>
                
                <button type="submit" class="btn-submit">📅 ยืนยันการจองห้องพัก</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
