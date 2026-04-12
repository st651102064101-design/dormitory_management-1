<?php
/**
 * Tenant Repair - แจ้งซ่อมอุปกรณ์ภายในห้อง
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';
require_once __DIR__ . '/../includes/repair_spam_check.php';

$auth = checkTenantAuth();
$pdo = $auth['pdo'];
$token = $auth['token'];
$contract = $auth['contract'];
$settings = getSystemSettings($pdo);
$repairPageUrl = 'repair.php?token=' . urlencode((string)$token);

$success = '';
$error = '';
$newRepairData = null;

if (!empty($_SESSION['tenant_repair_flash_success'])) {
    $success = (string)$_SESSION['tenant_repair_flash_success'];
    unset($_SESSION['tenant_repair_flash_success']);
}
if (!empty($_SESSION['tenant_repair_flash_error'])) {
    $error = (string)$_SESSION['tenant_repair_flash_error'];
    unset($_SESSION['tenant_repair_flash_error']);
}

$repairStatusMap = [
    '0' => ['label' => 'รอซ่อม', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.2)'],
    '1' => ['label' => 'กำลังซ่อม', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.2)'],
    '2' => ['label' => 'ซ่อมเสร็จ', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)']
];

$hasScheduleColumns = false;
try {
    $checkScheduleColumn = $pdo->query("SHOW COLUMNS FROM repair LIKE 'scheduled_date'");
    $hasScheduleColumns = $checkScheduleColumn && $checkScheduleColumn->rowCount() > 0;
} catch (Throwable $e) {
    $hasScheduleColumns = false;
}

$isAjaxRequest = $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'cancel_repair') {
    try {
        $repairId = (int)($_POST['repair_id'] ?? 0);
        if ($repairId <= 0) {
            throw new Exception('ไม่พบรายการที่ต้องการยกเลิก');
        }

        $checkStmt = $pdo->prepare(" 
            SELECT r.repair_id, r.repair_image, r.repair_status
            FROM repair r
            INNER JOIN contract c ON c.ctr_id = r.ctr_id
            WHERE r.repair_id = ? AND c.tnt_id = ?
            LIMIT 1
        ");
        $checkStmt->execute([$repairId, $contract['tnt_id']]);
        $targetRepair = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetRepair) {
            throw new Exception('ไม่พบรายการแจ้งซ่อมนี้ในสิทธิ์ของคุณ');
        }

        if ((string)($targetRepair['repair_status'] ?? '') !== '0') {
            throw new Exception('ยกเลิกได้เฉพาะรายการที่รอซ่อมเท่านั้น');
        }

        $deleteStmt = $pdo->prepare('DELETE FROM repair WHERE repair_id = ? LIMIT 1');
        $deleteStmt->execute([$repairId]);

        $repairImage = basename((string)($targetRepair['repair_image'] ?? ''));
        if ($repairImage !== '') {
            $imagePath = dirname(__DIR__) . '/Public/Assets/Images/Repairs/' . $repairImage;
            if (is_file($imagePath)) {
                @unlink($imagePath);
            }
        }

        if ($isAjaxRequest) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => true,
                'message' => 'ยกเลิกรายการแจ้งซ่อมเรียบร้อยแล้ว',
                'repair_id' => $repairId,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $_SESSION['tenant_repair_flash_success'] = 'ยกเลิกรายการแจ้งซ่อมเรียบร้อยแล้ว';
        header('Location: ' . $repairPageUrl);
        exit;
    } catch (Exception $e) {
        if ($isAjaxRequest) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $_SESSION['tenant_repair_flash_error'] = $e->getMessage();
        header('Location: ' . $repairPageUrl);
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') !== 'cancel_repair') {
    try {
        $repair_desc = trim($_POST['repair_desc'] ?? '');
        $scheduled_date = trim((string)($_POST['scheduled_date'] ?? ''));
        $scheduled_time_start = trim((string)($_POST['scheduled_time_start'] ?? ''));
        $scheduled_time_end = trim((string)($_POST['scheduled_time_end'] ?? ''));
        $technician_name = trim((string)($_POST['technician_name'] ?? ''));
        $technician_phone = trim((string)($_POST['technician_phone'] ?? ''));
        
        if (empty($repair_desc)) {
            $error = 'กรุณาระบุรายละเอียดการแจ้งซ่อม';
        } else {
            if (!$hasScheduleColumns) {
                throw new Exception('ระบบยังไม่รองรับการบันทึกนัดหมาย กรุณาแจ้งผู้ดูแลระบบ');
            }

            if ($scheduled_date === '' || $scheduled_time_start === '' || $scheduled_time_end === '' || $technician_name === '' || $technician_phone === '') {
                throw new Exception('กรุณากรอกข้อมูลนัดหมายให้ครบ: วันที่ เวลา ชื่อช่าง และเบอร์โทร');
            }

            $scheduledDateObj = DateTime::createFromFormat('Y-m-d', $scheduled_date);
            if (!$scheduledDateObj || $scheduledDateObj->format('Y-m-d') !== $scheduled_date) {
                throw new Exception('รูปแบบวันที่นัดหมายไม่ถูกต้อง');
            }

            $startTimeObj = DateTime::createFromFormat('H:i', $scheduled_time_start);
            $endTimeObj = DateTime::createFromFormat('H:i', $scheduled_time_end);
            if (!$startTimeObj || $startTimeObj->format('H:i') !== $scheduled_time_start || !$endTimeObj || $endTimeObj->format('H:i') !== $scheduled_time_end) {
                throw new Exception('รูปแบบเวลานัดหมายไม่ถูกต้อง');
            }
            if ((int)$startTimeObj->format('Hi') >= (int)$endTimeObj->format('Hi')) {
                throw new Exception('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น');
            }

            $technicianPhoneDigits = preg_replace('/\D+/', '', $technician_phone);
            if (strlen((string)$technicianPhoneDigits) < 8) {
                throw new Exception('กรุณาระบุเบอร์โทรช่างให้ถูกต้อง');
            }

            // AI spam check — server-side gate
            $aiResult = scoreRepairText($repair_desc);
            if ($aiResult['label'] === 'spam') {
                $error = '⚠️ ' . ($aiResult['message'] ?: 'รายละเอียดไม่สมเหตุสมผล กรุณาอธิบายปัญหาให้ชัดเจน');
            } else {
            // ป้องกันการส่งซ้ำในช่วงเวลาสั้น ๆ (เช่น รีเฟรช/กดย้ำ)
            $duplicateStmt = $pdo->prepare(" 
                SELECT repair_id
                FROM repair
                WHERE ctr_id = ?
                  AND repair_desc = ?
                  AND repair_status = '0'
                  AND repair_date >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                ORDER BY repair_id DESC
                LIMIT 1
            ");
            $duplicateStmt->execute([(int)$contract['ctr_id'], $repair_desc]);
            if ($duplicateStmt->fetchColumn()) {
                throw new Exception('ระบบตรวจพบการส่งแจ้งซ่อมซ้ำในช่วงเวลาสั้น ๆ กรุณารอสักครู่');
            }

            $repair_image = null;
            
            // Handle image upload
            if (!empty($_FILES['repair_image']['name'])) {
                $file = $_FILES['repair_image'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $uploadErrorMap = [
                        UPLOAD_ERR_INI_SIZE => 'ไฟล์รูปภาพใหญ่เกินค่าที่ระบบรองรับ',
                        UPLOAD_ERR_FORM_SIZE => 'ไฟล์รูปภาพใหญ่เกินค่าที่ฟอร์มกำหนด',
                        UPLOAD_ERR_PARTIAL => 'ไฟล์รูปภาพถูกอัปโหลดไม่สมบูรณ์',
                        UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวของระบบ',
                        UPLOAD_ERR_CANT_WRITE => 'ระบบไม่สามารถเขียนไฟล์ชั่วคราวได้',
                        UPLOAD_ERR_EXTENSION => 'อัปโหลดรูปภาพถูกยกเลิกโดยส่วนเสริมของ PHP',
                    ];
                    throw new Exception($uploadErrorMap[$uploadError] ?? 'ไม่สามารถอัปโหลดรูปภาพได้ (code: ' . $uploadError . ')');
                }
                
                if ($file['size'] > $maxFileSize) {
                    throw new Exception('ไฟล์รูปภาพใหญ่เกินไป (ไม่เกิน 5MB)');
                }
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mimeType, $allowedMimes)) {
                    throw new Exception('ประเภทไฟล์ไม่ถูกต้อง (สนับสนุน JPG, PNG, WebP)');
                }
                
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    throw new Exception('นามสกุลไฟล์ไม่ถูกต้อง');
                }
                
                $uploadsDir = dirname(__DIR__) . '/Public/Assets/Images/Repairs';
                if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
                    throw new Exception('ไม่สามารถสร้างโฟลเดอร์เก็บรูปภาพได้');
                }

                if (!is_writable($uploadsDir)) {
                    @chmod($uploadsDir, 0777);
                    clearstatcache(true, $uploadsDir);
                }

                if (!is_writable($uploadsDir)) {
                    throw new Exception('โฟลเดอร์เก็บรูปภาพไม่มีสิทธิ์เขียน (Public/Assets/Images/Repairs)');
                }

                if (!is_uploaded_file((string)$file['tmp_name'])) {
                    throw new Exception('ไม่พบไฟล์อัปโหลดชั่วคราว');
                }
                
                $filename = 'repair_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $targetPath = $uploadsDir . '/' . $filename;
                
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $lastErr = error_get_last();
                    $detail = $lastErr['message'] ?? 'unknown error';
                    throw new Exception('ไม่สามารถอัปโหลดรูปภาพได้ (' . $detail . ')');
                }
                $repair_image = $filename;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO repair (repair_desc, repair_date, repair_time, repair_status, repair_image, ctr_id)
                VALUES (?, NOW(), CURTIME(), '0', ?, ?)
            ");
            $stmt->execute([$repair_desc, $repair_image, $contract['ctr_id']]);

            $repairId = (int)$pdo->lastInsertId();
            if ($hasScheduleColumns) {
                $scheduleStmt = $pdo->prepare(" 
                    UPDATE repair
                    SET scheduled_date = ?,
                        scheduled_time_start = ?,
                        scheduled_time_end = ?,
                        technician_name = ?,
                        technician_phone = ?
                    WHERE repair_id = ?
                    LIMIT 1
                ");
                $scheduleStmt->execute([$scheduled_date, $scheduled_time_start, $scheduled_time_end, $technician_name, $technician_phone, $repairId]);
            }

            $selectFields = "repair_id, repair_date, repair_time, repair_desc, repair_status, repair_image";
            if ($hasScheduleColumns) {
                $selectFields .= ", scheduled_date, scheduled_time_start, scheduled_time_end, technician_name, technician_phone, schedule_note";
            }

            $rowStmt = $pdo->prepare("SELECT {$selectFields} FROM repair WHERE repair_id = ? LIMIT 1");
            $rowStmt->execute([$repairId]);
            $insertedRepair = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $statusCode = (string)($insertedRepair['repair_status'] ?? '0');
            $statusInfo = $repairStatusMap[$statusCode] ?? $repairStatusMap['0'];
            $scheduledDateValue = '';
            $scheduledDateDisplay = '';
            $scheduledTimeStartValue = '';
            $scheduledTimeEndValue = '';
            $scheduledTimeRange = '';
            $technicianNameValue = '';
            $technicianPhoneValue = '';
            $scheduleNoteValue = '';
            if ($hasScheduleColumns) {
                $scheduledDateValue = (string)($insertedRepair['scheduled_date'] ?? $scheduled_date);
                $scheduledTimeStartValue = substr((string)($insertedRepair['scheduled_time_start'] ?? $scheduled_time_start), 0, 5);
                $scheduledTimeEndValue = substr((string)($insertedRepair['scheduled_time_end'] ?? $scheduled_time_end), 0, 5);
                $technicianNameValue = trim((string)($insertedRepair['technician_name'] ?? $technician_name));
                $technicianPhoneValue = trim((string)($insertedRepair['technician_phone'] ?? $technician_phone));
                $scheduleNoteValue = trim((string)($insertedRepair['schedule_note'] ?? ''));
                if ($scheduledDateValue !== '') {
                    $scheduledDateDisplay = thaiDate($scheduledDateValue);
                }
                if ($scheduledTimeStartValue !== '' && $scheduledTimeEndValue !== '') {
                    $scheduledTimeRange = $scheduledTimeStartValue . ' - ' . $scheduledTimeEndValue . ' น.';
                } elseif ($scheduledTimeStartValue !== '') {
                    $scheduledTimeRange = $scheduledTimeStartValue . ' น.';
                }
            }
            $newRepairData = [
                'repair_id' => (int)($insertedRepair['repair_id'] ?? $repairId),
                'repair_date' => (string)($insertedRepair['repair_date'] ?? ''),
                'repair_time' => substr((string)($insertedRepair['repair_time'] ?? ''), 0, 5),
                'repair_desc' => (string)($insertedRepair['repair_desc'] ?? $repair_desc),
                'repair_status' => $statusCode,
                'status_label' => (string)$statusInfo['label'],
                'status_color' => (string)$statusInfo['color'],
                'status_bg' => (string)$statusInfo['bg'],
                'repair_image' => basename((string)($insertedRepair['repair_image'] ?? $repair_image ?? '')),
                'scheduled_date' => $scheduledDateValue,
                'scheduled_date_display' => $scheduledDateDisplay,
                'scheduled_time_start' => $scheduledTimeStartValue,
                'scheduled_time_end' => $scheduledTimeEndValue,
                'scheduled_time_range' => $scheduledTimeRange,
                'technician_name' => $technicianNameValue,
                'technician_phone' => $technicianPhoneValue,
                'schedule_note' => $scheduleNoteValue,
            ];
            
            $success = 'แจ้งซ่อมเรียบร้อยแล้ว';

            // ส่งแจ้งเตือนการแจ้งซ่อมใหม่เข้า LINE OA
            require_once __DIR__ . '/../LineHelper.php';
            try {
                $stmtRoom = $pdo->prepare("SELECT r.room_number, t.tnt_name FROM room r JOIN contract c ON r.room_id = c.room_id JOIN tenant t ON c.tnt_id = t.tnt_id WHERE c.ctr_id = ?");
                $stmtRoom->execute([$contract['ctr_id']]);
                $row = $stmtRoom->fetch(PDO::FETCH_ASSOC);
                $roomName = $row ? $row['room_number'] : 'ไม่ทราบห้อง';
                $tenantName = $row ? $row['tnt_name'] : 'ผู้เช่า';
                
                $msg = "🛠️ มีการแจ้งซ่อมใหม่จากห้อง {$roomName}\n";
                $msg .= "👤 ผู้แจ้ง: {$tenantName}\n";
                $msg .= "รายการ: " . mb_substr($repair_desc, 0, 50) . (mb_strlen($repair_desc) > 50 ? '...' : '') . "\n";
                if ($scheduledDateDisplay !== '') {
                    $msg .= "📅 วันที่นัด: {$scheduledDateDisplay}\n";
                }
                if ($scheduledTimeRange !== '') {
                    $msg .= "⏰ เวลา: {$scheduledTimeRange}\n";
                }
                if ($technicianNameValue !== '') {
                    $msg .= "🔧 ช่าง: {$technicianNameValue}\n";
                }
                if ($technicianPhoneValue !== '') {
                    $msg .= "📞 โทร: {$technicianPhoneValue}\n";
                }
                $msg .= "สถานะ: รอคนรับเรื่อง";
                
                if (function_exists('sendLineToContract')) {
                    sendLineToContract($pdo, (int)$contract['ctr_id'], $msg);
                }
            } catch (Exception $e) {
                // Ignore LINE error
            }
            } // end spam-check else
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=UTF-8');
        if ($error !== '') {
            echo json_encode([
                'success' => false,
                'message' => $error,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => $success,
            'repair' => $newRepairData,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // กัน browser refresh แล้วยิง POST ซ้ำด้วย Post/Redirect/Get
    if ($error !== '') {
        $_SESSION['tenant_repair_flash_error'] = $error;
    } elseif ($success !== '') {
        $_SESSION['tenant_repair_flash_success'] = $success;
    }
    header('Location: ' . $repairPageUrl);
    exit;
}

// Get repair history
$repairs = [];
try {
    // ค้นหาประวัติซ่อมจาก ctr_id ปัจจุบัน รวมถึงสัญญาเดิมทั้งหมดของผู้เช่าคนนี้
    // (ป้องกันกรณีต่อสัญญาใหม่ แต่ repair ผูกกับสัญญาเดิม)
    $stmt = $pdo->prepare("
        SELECT r.*
        FROM repair r
        WHERE r.ctr_id IN (
            SELECT ctr_id FROM contract WHERE tnt_id = ?
        )
        ORDER BY r.repair_id DESC, r.repair_date DESC, r.repair_time DESC
    ");
    $stmt->execute([$contract['tnt_id']]);
    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[repair.php] PDO Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แจ้งซ่อม - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($settings['logo_filename']); ?>">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/confirm-modal.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Prompt', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            padding-bottom: 80px;
        }
        .header {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .header-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-size: 1.5rem;
            padding: 0.5rem;
        }
        .header-title {
            font-size: 1.1rem;
            color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
        }
        .form-section {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1rem;
            color: #f8fafc;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
        }
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .repair-schedule-label {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #cbd5e1;
        }
        .repair-schedule-label svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            flex-shrink: 0;
        }
        .repair-schedule-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .repair-schedule-input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .repair-schedule-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .repair-schedule-row .form-group {
            margin-bottom: 1rem;
        }
        @media (max-width: 520px) {
            .repair-schedule-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
        .file-upload {
            position: relative;
            width: 100%;
            height: 120px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .file-upload:hover {
            border-color: #3b82f6;
        }
        .file-upload input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .file-upload-text {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        .repair-history {
            margin-top: 2rem;
        }
        .repair-item {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .repair-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        .repair-date {
            font-size: 0.75rem;
            color: #64748b;
        }
        .repair-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .repair-desc {
            font-size: 0.9rem;
            color: #e2e8f0;
            line-height: 1.5;
        }
        .repair-image {
            margin-top: 0.75rem;
            border-radius: 8px;
            overflow: hidden;
        }
        .repair-image img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            cursor: zoom-in;
        }
        .repair-image-viewer {
            position: fixed;
            inset: 0;
            z-index: 1000000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: transparent;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease;
        }
        .repair-image-viewer.is-visible {
            opacity: 1;
            pointer-events: auto;
        }
        .repair-image-viewer.hover-mode {
            pointer-events: none;
        }
        .repair-image-viewer img {
            max-width: calc(100vw - 2rem);
            max-height: calc(100vh - 2rem);
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 0;
            box-shadow: none;
            user-select: none;
            -webkit-user-drag: none;
        }
        .repair-actions {
            margin-top: 0.75rem;
            display: flex;
            justify-content: flex-end;
        }
        .btn-cancel-repair {
            border: 1px solid rgba(239, 68, 68, 0.45);
            background: rgba(239, 68, 68, 0.16);
            color: #fca5a5;
            border-radius: 999px;
            padding: 0.45rem 0.95rem;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-cancel-repair:hover {
            background: rgba(239, 68, 68, 0.24);
            color: #fecaca;
            transform: translateY(-1px);
        }
        .btn-cancel-repair:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.98);
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 0.5rem;
            backdrop-filter: blur(10px);
        }
        .bottom-nav-content {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            transition: color 0.2s;
        }
        .nav-item.active, .nav-item:hover { color: #3b82f6; }
        .nav-icon { font-size: 1.3rem; margin-bottom: 0.25rem; }
        .nav-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .nav-badge {
            position: absolute;
            top: -2px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .nav-item {
            position: relative;
        }
        .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .section-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .alert-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        .file-upload-icon svg {
            width: 32px;
            height: 32px;
            stroke: #64748b;
            stroke-width: 2;
            fill: none;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        .btn-icon svg {
            width: 18px;
            height: 18px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }
        .empty-state-icon svg {
            width: 48px;
            height: 48px;
            stroke: #64748b;
            stroke-width: 1.5;
            fill: none;
        }
        .date-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .date-icon svg {
            width: 12px;
            height: 12px;
            stroke: #64748b;
            stroke-width: 2;
            fill: none;
            margin-right: 4px;
        }
        /* Confirm modal override for this page: light card + stronger blurred backdrop */
        .confirm-overlay {
            background: rgba(15, 23, 42, 0.28) !important;
            backdrop-filter: blur(10px) saturate(120%);
            -webkit-backdrop-filter: blur(10px) saturate(120%);
        }
        .confirm-modal {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.97), rgba(241, 245, 249, 0.95)) !important;
            border: 1px solid rgba(148, 163, 184, 0.35) !important;
            box-shadow: 0 20px 45px rgba(2, 6, 23, 0.25) !important;
        }
        .confirm-message {
            color: #334155 !important;
        }
        .confirm-btn-cancel {
            background: rgba(148, 163, 184, 0.14) !important;
            color: #334155 !important;
            border: 1px solid rgba(148, 163, 184, 0.35) !important;
        }
        .confirm-btn-cancel:hover {
            background: rgba(148, 163, 184, 0.24) !important;
        }
        #preview-container { display: none; margin-top: 0.5rem; }
        #preview-container img { max-width: 100%; max-height: 150px; border-radius: 8px; }
        
        /* Schedule Info Styles */
        .schedule-info {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(168, 85, 247, 0.4);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 0.75rem;
        }
        .schedule-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #a78bfa;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
        }
        .schedule-header svg {
            width: 18px;
            height: 18px;
            stroke: #a78bfa;
        }
        .schedule-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #e2e8f0;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }
        .schedule-row:last-child {
            margin-bottom: 0;
        }
        .schedule-row svg {
            width: 16px;
            height: 16px;
            stroke: #a78bfa;
            flex-shrink: 0;
        }
        .schedule-label {
            color: #94a3b8;
            min-width: 60px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .schedule-value {
            color: #f8fafc;
            font-weight: 600;
            flex: 1;
        }
        .schedule-value a {
            color: #f8fafc;
            text-decoration: none;
            font-weight: 600;
        }
        .schedule-note {
            background: rgba(168, 85, 247, 0.15);
            border-left: 3px solid #a78bfa;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            margin-top: 0.75rem;
            font-size: 0.85rem;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
    <?php if (($settings['public_theme'] ?? '') === 'light'): ?>
    <link rel="stylesheet" href="tenant-light-theme.css">
    <?php endif; ?>
</head>
<body class="<?= ($settings['public_theme'] ?? '') === 'light' ? 'light-theme' : '' ?>">
    <header class="header">
        <div class="header-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="back-btn">←</a>
            <h1 class="header-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span> แจ้งซ่อม</h1>
            <?php if (!empty($_SESSION['tenant_logged_in'])): ?>
            <div style="margin-left: auto; display: flex; gap: 0.5rem;">
                <?php if (!empty($contract['line_user_id'])): ?>
                <a href="../tenant_logout.php" style="padding: 0.5rem 1rem; background: rgba(239, 68, 68, 0.2); color: #f87171; border-radius: 8px; text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    ออกจากระบบ
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <div class="container">
        <div id="repair-alert-container">
            <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="form-section">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span> แจ้งซ่อมใหม่</div>
            <form id="repair-form" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>รายละเอียดอุปกรณ์ที่ต้องการซ่อม *</label>
                    <textarea name="repair_desc" id="repair_desc_input" placeholder="เช่น พัดลมเพดานไม่หมุน, ก๊อกน้ำรั่ว, หลอดไฟเสีย ฯลฯ" required oninput="scheduleAiCheck()"></textarea>
                    <!-- AI quality indicator -->
                    <div id="ai_feedback" style="display:none;margin-top:0.5rem;padding:0.6rem 0.85rem;border-radius:8px;font-size:0.82rem;display:flex;align-items:center;gap:0.5rem;transition:all 0.3s;"></div>
                </div>
                <div class="form-group">
                    <label for="scheduled_date_input" class="repair-schedule-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        วันที่นัดหมาย *
                    </label>
                    <input type="date" name="scheduled_date" id="scheduled_date_input" class="repair-schedule-input" required>
                </div>
                <div class="repair-schedule-row">
                    <div class="form-group">
                        <label for="scheduled_time_start_input" class="repair-schedule-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            เวลาเริ่ม *
                        </label>
                        <input type="time" name="scheduled_time_start" id="scheduled_time_start_input" class="repair-schedule-input" required>
                    </div>
                    <div class="form-group">
                        <label for="scheduled_time_end_input" class="repair-schedule-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            เวลาสิ้นสุด *
                        </label>
                        <input type="time" name="scheduled_time_end" id="scheduled_time_end_input" class="repair-schedule-input" required>
                    </div>
                </div>
                <div class="repair-schedule-row">
                    <div class="form-group">
                        <label for="technician_name_input" class="repair-schedule-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            ชื่อช่าง *
                        </label>
                        <input type="text" name="technician_name" id="technician_name_input" class="repair-schedule-input" placeholder="เช่น ช่างเอก" required>
                    </div>
                    <div class="form-group">
                        <label for="technician_phone_input" class="repair-schedule-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            เบอร์โทรช่าง *
                        </label>
                        <input type="tel" name="technician_phone" id="technician_phone_input" class="repair-schedule-input" placeholder="0xx-xxx-xxxx" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>รูปภาพประกอบ (ถ้ามี)</label>
                    <div class="file-upload">
                        <input type="file" name="repair_image" id="repair_image" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                        <div class="file-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                        <div class="file-upload-text">แตะเพื่อเลือกรูปภาพ</div>
                    </div>
                    <div id="preview-container">
                        <img id="preview-image" src="" alt="Preview">
                    </div>
                </div>
                <button type="submit" id="repair_submit_btn" class="btn-submit"><span class="btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></span> ส่งแจ้งซ่อม</button>
            </form>
        </div>
        
        <div class="repair-history">
            <div class="section-title"><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> ประวัติการแจ้งซ่อม</div>
            <div id="repair-history-list">
            <?php if (empty($repairs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"/><path d="M5.5 5.1L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/></svg></div>
                <p>ยังไม่มีประวัติการแจ้งซ่อม</p>
            </div>
            <?php else: ?>
            <?php foreach ($repairs as $repair): ?>
            <?php 
                $hasSchedule = $hasScheduleColumns && !empty($repair['scheduled_date']);
                $scheduledDate = $repair['scheduled_date'] ?? '';
                $scheduledTimeStart = $repair['scheduled_time_start'] ?? '';
                $scheduledTimeEnd = $repair['scheduled_time_end'] ?? '';
                $technicianName = $repair['technician_name'] ?? '';
                $technicianPhone = $repair['technician_phone'] ?? '';
                $scheduleNote = $repair['schedule_note'] ?? '';
                
                // Format date for display
                $formattedDate = '';
                if ($scheduledDate) {
                    $formattedDate = thaiDate($scheduledDate);
                }
                
                // Format time range
                $timeRange = '';
                if ($scheduledTimeStart && $scheduledTimeEnd) {
                    $timeRange = substr($scheduledTimeStart, 0, 5) . ' - ' . substr($scheduledTimeEnd, 0, 5) . ' น.';
                } elseif ($scheduledTimeStart) {
                    $timeRange = substr($scheduledTimeStart, 0, 5) . ' น.';
                }
            ?>
            <div class="repair-item" data-repair-id="<?php echo (int)($repair['repair_id'] ?? 0); ?>" data-repair-status="<?php echo htmlspecialchars((string)($repair['repair_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="repair-header">
                    <div class="repair-date">
                        <span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> <?php echo $repair['repair_date'] ?? '-'; ?>
                        <?php if ($repair['repair_time']): ?>
                        <span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> <?php echo substr($repair['repair_time'], 0, 5); ?>
                        <?php endif; ?>
                    </div>
                    <span class="repair-status" style="background: <?php echo $repairStatusMap[$repair['repair_status'] ?? '0']['bg']; ?>; color: <?php echo $repairStatusMap[$repair['repair_status'] ?? '0']['color']; ?>">
                        <?php echo $repairStatusMap[$repair['repair_status'] ?? '0']['label']; ?>
                    </span>
                </div>
                <div class="repair-desc"><?php echo htmlspecialchars($repair['repair_desc'] ?? '-'); ?></div>
                <?php if (!empty($repair['repair_image'])): ?>
                <div class="repair-image">
                    <img src="/dormitory_management/Public/Assets/Images/Repairs/<?php echo htmlspecialchars(basename((string)$repair['repair_image']), ENT_QUOTES, 'UTF-8'); ?>" alt="Repair Image">
                </div>
                <?php endif; ?>

                <?php if (($repair['repair_status'] ?? '') === '0'): ?>
                <div class="repair-actions">
                    <button type="button" class="btn-cancel-repair" data-repair-id="<?php echo (int)($repair['repair_id'] ?? 0); ?>">ยกเลิก</button>
                </div>
                <?php endif; ?>
                
                <?php if ($hasSchedule): ?>
                <div class="schedule-info">
                    <div class="schedule-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        นัดหมายซ่อม
                    </div>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span class="schedule-label">วันที่</span>
                        <span class="schedule-value"><?php echo htmlspecialchars($formattedDate); ?></span>
                    </div>
                    <?php if ($timeRange): ?>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span class="schedule-label">เวลา</span>
                        <span class="schedule-value"><?php echo htmlspecialchars($timeRange); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($technicianName): ?>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="schedule-label">ช่าง</span>
                        <span class="schedule-value"><?php echo htmlspecialchars($technicianName); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($technicianPhone): ?>
                    <div class="schedule-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span class="schedule-label">โทร</span>
                        <span class="schedule-value"><a href="tel:<?php echo htmlspecialchars($technicianPhone); ?>"><?php echo htmlspecialchars($technicianPhone); ?></a></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($scheduleNote): ?>
                    <div class="schedule-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; width: 1.2em; height: 1.2em; margin-right: 0.5em; vertical-align: -0.15em;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg> <?php echo htmlspecialchars($scheduleNote); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    // นับรายการแจ้งซ่อมที่ยังไม่เสร็จ
    $repairCount = 0;
    try {
        $repairStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM repair
            WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE tnt_id = ?)
            AND repair_status = '0'
        ");
        $repairStmt->execute([$contract['tnt_id']]);
        $repairCount = (int)($repairStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }
    
    // นับรายการบิลที่ยังไม่ชำระ
    $homeBadgeCount = 0;
    try {
        $homeBadgeStmt = $pdo->prepare("
            SELECT 1 
            FROM contract c
            LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant'
            WHERE c.ctr_id = ? AND c.ctr_status != '1' AND sl.id IS NULL
              AND (
                  SELECT step_3_confirmed 
                  FROM tenant_workflow 
                  WHERE tnt_id = c.tnt_id 
                  ORDER BY id DESC LIMIT 1
              ) = 1
            LIMIT 1
        ");
        $homeBadgeStmt->execute([$contract['ctr_id'] ?? 0]);
        if ($homeBadgeStmt->fetchColumn()) {
            $homeBadgeCount = 1;
        }
    } catch (Exception $e) { error_log("Exception calculating home badge count in " . __FILE__ . ": " . $e->getMessage()); }

    if (function_exists('getTenantBillBadgeCount')) {
        $billCount = getTenantBillBadgeCount($pdo, $contract);
    } else {
        $billCount = 0;
    }
    ?>
    
    <nav class="bottom-nav">
        <div class="bottom-nav-content">
            <a href="index.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                                หน้าหลัก<?php if ($homeBadgeCount > 0): ?><span class="nav-badge">1</span><?php endif; ?>
            </a>
            <a href="report_bills.php?token=<?php echo urlencode($token); ?>&_ts=<?php echo time(); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg></div>
                บิล<?php if ($billCount > 0): ?><span class="nav-badge"><?php echo $billCount > 99 ? '99+' : $billCount; ?></span><?php endif; ?>
            </a>
            <a href="repair.php?token=<?php echo urlencode($token); ?>" class="nav-item active">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                แจ้งซ่อม<?php if ($repairCount > 0): ?><span class="nav-badge"><?php echo $repairCount > 99 ? '99+' : $repairCount; ?></span><?php endif; ?></a>
            <a href="profile.php?token=<?php echo urlencode($token); ?>" class="nav-item">
                <div class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                โปรไฟล์
            </a>
        </div>
    </nav>
    <script src="/dormitory_management/Public/Assets/Javascript/confirm-modal.js"></script>
    
    <script>
    function previewImage(input) {
        const container = document.getElementById('preview-container');
        const preview = document.getElementById('preview-image');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                container.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    let repairImageViewer = null;
    let repairImageViewerImg = null;
    let repairImageViewerMode = 'click';

    function ensureRepairImageViewer() {
        if (repairImageViewer) return;

        repairImageViewer = document.createElement('div');
        repairImageViewer.className = 'repair-image-viewer';
        repairImageViewer.setAttribute('aria-hidden', 'true');
        repairImageViewer.innerHTML = '<img alt="Repair Fullscreen" draggable="false">';
        repairImageViewerImg = repairImageViewer.querySelector('img');

        repairImageViewer.addEventListener('click', function() {
            closeRepairImageViewer();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeRepairImageViewer();
            }
        });

        document.body.appendChild(repairImageViewer);
    }

    function openRepairImageViewer(src, mode) {
        if (!src) return;
        ensureRepairImageViewer();

        repairImageViewerMode = mode === 'hover' ? 'hover' : 'click';
        repairImageViewerImg.src = src;
        repairImageViewer.classList.toggle('hover-mode', repairImageViewerMode === 'hover');
        repairImageViewer.classList.add('is-visible');
    }

    function closeRepairImageViewer() {
        if (!repairImageViewer) return;
        repairImageViewer.classList.remove('is-visible');
        repairImageViewer.classList.remove('hover-mode');
        repairImageViewerMode = 'click';
    }

    function bindRepairImageInteractions(rootElement) {
        const root = rootElement || document;
        const images = root.querySelectorAll('#repair-history-list .repair-image img');

        images.forEach(function(img) {
            if (img.dataset.viewerBound === '1') return;
            img.dataset.viewerBound = '1';

            img.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                openRepairImageViewer(img.src, 'click');
            });

            if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                img.addEventListener('mouseenter', function() {
                    openRepairImageViewer(img.src, 'hover');
                });

                img.addEventListener('mouseleave', function() {
                    if (repairImageViewerMode === 'hover') {
                        closeRepairImageViewer();
                    }
                });
            }
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(ch) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[ch] || ch;
        });
    }

    function showRepairAlert(message, type) {
        const alertContainer = document.getElementById('repair-alert-container');
        if (!alertContainer) return;

        const isSuccess = type === 'success';
        const icon = isSuccess
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

        alertContainer.innerHTML = '<div class="alert ' + (isSuccess ? 'alert-success' : 'alert-error') + '">' +
            '<span class="alert-icon">' + icon + '</span>' +
            '<span>' + escapeHtml(message) + '</span>' +
        '</div>';
    }

    function renderRepairItemHtml(item) {
        const statusStyles = {
            '0': { label: 'รอซ่อม', color: '#f59e0b', bg: 'rgba(245, 158, 11, 0.2)' },
            '1': { label: 'กำลังซ่อม', color: '#3b82f6', bg: 'rgba(59, 130, 246, 0.2)' },
            '2': { label: 'ซ่อมเสร็จ', color: '#10b981', bg: 'rgba(16, 185, 129, 0.2)' }
        };

        const itemStatus = String(item.repair_status || '0');
        const status = statusStyles[itemStatus] || statusStyles['0'];
        const imageHtml = item.repair_image
            ? '<div class="repair-image"><img src="/dormitory_management/Public/Assets/Images/Repairs/' + encodeURIComponent(item.repair_image) + '" alt="Repair Image"></div>'
            : '';
        const cancelButtonHtml = itemStatus === '0'
            ? '<div class="repair-actions"><button type="button" class="btn-cancel-repair" data-repair-id="' + Number(item.repair_id || 0) + '">ยกเลิก</button></div>'
            : '';
        const timeHtml = item.repair_time
            ? '<span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> ' + escapeHtml(item.repair_time)
            : '';
        const scheduledDateDisplay = item.scheduled_date_display || item.scheduled_date || '';
        const scheduledTimeRange = item.scheduled_time_range
            || ((item.scheduled_time_start && item.scheduled_time_end)
                ? String(item.scheduled_time_start).substring(0, 5) + ' - ' + String(item.scheduled_time_end).substring(0, 5) + ' น.'
                : (item.scheduled_time_start ? String(item.scheduled_time_start).substring(0, 5) + ' น.' : ''));
        const technicianName = item.technician_name || '';
        const technicianPhone = item.technician_phone || '';
        const technicianPhoneTel = String(technicianPhone).replace(/[^0-9+]/g, '');
        const scheduleNote = item.schedule_note || '';

        let scheduleRowsHtml = '';
        if (scheduledDateDisplay) {
            scheduleRowsHtml += '<div class="schedule-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' +
                '<span class="schedule-label">วันที่</span>' +
                '<span class="schedule-value">' + escapeHtml(scheduledDateDisplay) + '</span>' +
            '</div>';
        }
        if (scheduledTimeRange) {
            scheduleRowsHtml += '<div class="schedule-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                '<span class="schedule-label">เวลา</span>' +
                '<span class="schedule-value">' + escapeHtml(scheduledTimeRange) + '</span>' +
            '</div>';
        }
        if (technicianName) {
            scheduleRowsHtml += '<div class="schedule-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' +
                '<span class="schedule-label">ช่าง</span>' +
                '<span class="schedule-value">' + escapeHtml(technicianName) + '</span>' +
            '</div>';
        }
        if (technicianPhone) {
            scheduleRowsHtml += '<div class="schedule-row">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>' +
                '<span class="schedule-label">โทร</span>' +
                '<span class="schedule-value"><a href="tel:' + escapeHtml(technicianPhoneTel || technicianPhone) + '">' + escapeHtml(technicianPhone) + '</a></span>' +
            '</div>';
        }

        const scheduleNoteHtml = scheduleNote
            ? '<div class="schedule-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;width:1.2em;height:1.2em;margin-right:0.5em;vertical-align:-0.15em;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>' + escapeHtml(scheduleNote) + '</div>'
            : '';

        const scheduleHtml = scheduleRowsHtml
            ? '<div class="schedule-info">' +
                '<div class="schedule-header">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' +
                    'นัดหมายซ่อม' +
                '</div>' +
                scheduleRowsHtml +
                scheduleNoteHtml +
              '</div>'
            : '';

        return '<div class="repair-item" data-repair-id="' + Number(item.repair_id || 0) + '" data-repair-status="' + escapeHtml(itemStatus) + '">' +
            '<div class="repair-header">' +
                '<div class="repair-date">' +
                    '<span class="date-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> ' + escapeHtml(item.repair_date || '-') +
                    (timeHtml ? ' ' + timeHtml : '') +
                '</div>' +
                '<span class="repair-status" style="background: ' + status.bg + '; color: ' + status.color + '">' + status.label + '</span>' +
            '</div>' +
            '<div class="repair-desc">' + escapeHtml(item.repair_desc || '-') + '</div>' +
            imageHtml +
            cancelButtonHtml +
            scheduleHtml +
        '</div>';
    }

    function initScheduledDateInput() {
        const scheduledDateInput = document.getElementById('scheduled_date_input');
        const startTimeInput = document.getElementById('scheduled_time_start_input');
        const endTimeInput = document.getElementById('scheduled_time_end_input');
        if (!scheduledDateInput) return;

        const today = new Date();
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');
        scheduledDateInput.min = y + '-' + m + '-' + d;

        if (startTimeInput && !startTimeInput.value) {
            startTimeInput.value = '09:00';
        }
        if (endTimeInput && !endTimeInput.value) {
            endTimeInput.value = '12:00';
        }
    }

    function renderEmptyStateHtml() {
        return '<div class="empty-state">' +
            '<div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"/><path d="M5.5 5.1L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/></svg></div>' +
            '<p>ยังไม่มีประวัติการแจ้งซ่อม</p>' +
        '</div>';
    }

    function syncPendingRepairBadge() {
        const navItem = document.querySelector('.bottom-nav .nav-item.active');
        if (!navItem) return;

        const pendingCount = document.querySelectorAll('#repair-history-list .repair-item[data-repair-status="0"]').length;
        let badge = navItem.querySelector('.nav-badge');

        if (pendingCount <= 0) {
            if (badge) badge.remove();
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'nav-badge';
            navItem.appendChild(badge);
        }
        badge.textContent = pendingCount > 99 ? '99+' : String(pendingCount);
    }

    function prependRepairItem(item) {
        const list = document.getElementById('repair-history-list');
        if (!list) return;

        const emptyState = list.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = renderRepairItemHtml(item);
        if (wrapper.firstElementChild) {
            const insertedNode = wrapper.firstElementChild;
            list.prepend(insertedNode);
            bindRepairImageInteractions(insertedNode);
        }
    }

    function clearRepairFormUi() {
        const previewContainer = document.getElementById('preview-container');
        const previewImageEl = document.getElementById('preview-image');
        const feedback = document.getElementById('ai_feedback');

        if (previewImageEl) {
            previewImageEl.src = '';
        }
        if (previewContainer) {
            previewContainer.style.display = 'none';
        }
        if (feedback) {
            feedback.style.display = 'none';
            feedback.innerHTML = '';
        }
    }

    async function requestCancelRepair(repairId, buttonEl) {
        const confirmed = (typeof showConfirmDialog === 'function')
            ? await showConfirmDialog('ยืนยันการยกเลิก', 'คุณยืนยันยกเลิกรายการแจ้งซ่อมนี้?', 'warning')
            : window.confirm('ยืนยันยกเลิกรายการแจ้งซ่อมนี้?');
        if (!confirmed) return;

        if (buttonEl) {
            buttonEl.disabled = true;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'cancel_repair');
            formData.append('repair_id', String(repairId));

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            if (!response.ok || !data || data.success !== true) {
                throw new Error((data && data.message) ? data.message : 'ไม่สามารถยกเลิกรายการได้');
            }

            const row = document.querySelector('#repair-history-list .repair-item[data-repair-id="' + Number(repairId) + '"]');
            if (row) {
                row.remove();
            }

            const historyList = document.getElementById('repair-history-list');
            if (historyList && !historyList.querySelector('.repair-item')) {
                historyList.innerHTML = renderEmptyStateHtml();
            }

            syncPendingRepairBadge();
            showRepairAlert(data.message || 'ยกเลิกรายการแจ้งซ่อมเรียบร้อยแล้ว', 'success');
        } catch (err) {
            showRepairAlert((err && err.message) ? err.message : 'เกิดข้อผิดพลาดในการยกเลิกรายการ', 'error');
            if (buttonEl) {
                buttonEl.disabled = false;
            }
        }
    }

    // ── AI Repair Quality Checker ──────────────────────────────
    let _aiTimer = null;
    let _aiBlocked = false;

    function scheduleAiCheck() {
        clearTimeout(_aiTimer);
        _aiTimer = setTimeout(runAiCheck, 500);
    }

    async function runAiCheck() {
        const textarea = document.getElementById('repair_desc_input');
        const feedback = document.getElementById('ai_feedback');
        const submitBtn = document.getElementById('repair_submit_btn');
        if (!textarea || !feedback) return;

        const text = textarea.value.trim();
        if (text.length === 0) {
            feedback.style.display = 'none';
            _aiBlocked = false;
            submitBtn.disabled = false;
            submitBtn.style.opacity = '';
            return;
        }

        try {
            const res = await fetch('../Manage/ai_check_repair.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'text=' + encodeURIComponent(text)
            });
            const data = await res.json();
            showAiFeedback(feedback, submitBtn, data);
        } catch (e) {
            // On network error, allow submission silently
            feedback.style.display = 'none';
            _aiBlocked = false;
            submitBtn.disabled = false;
        }
    }

    function showAiFeedback(feedback, submitBtn, data) {
        feedback.style.display = 'flex';

        if (data.label === 'ok' || data.label === 'empty') {
            feedback.style.background  = 'rgba(34,197,94,0.12)';
            feedback.style.border      = '1px solid rgba(34,197,94,0.3)';
            feedback.style.color       = '#4ade80';
            feedback.innerHTML = '\u2705 \u0e23\u0e32\u0e22\u0e25\u0e30\u0e40\u0e2d\u0e35\u0e22\u0e14\u0e14\u0e39\u0e2a\u0e21\u0e40\u0e2b\u0e15\u0e38\u0e2a\u0e21\u0e1c\u0e25 \u0e2a\u0e32\u0e21\u0e32\u0e23\u0e16\u0e2a\u0e48\u0e07\u0e44\u0e14\u0e49';
            _aiBlocked = false;
            submitBtn.disabled = false;
            submitBtn.style.opacity = '';
        } else if (data.label === 'suspect') {
            feedback.style.background  = 'rgba(251,191,36,0.12)';
            feedback.style.border      = '1px solid rgba(251,191,36,0.35)';
            feedback.style.color       = '#fbbf24';
            feedback.innerHTML = '\u26a0\ufe0f ' + (data.message || '\u0e01\u0e23\u0e38\u0e13\u0e32\u0e2d\u0e18\u0e34\u0e1a\u0e32\u0e22\u0e1b\u0e31\u0e0d\u0e2b\u0e32\u0e43\u0e2b\u0e49\u0e0a\u0e31\u0e14\u0e40\u0e08\u0e19\u0e02\u0e36\u0e49\u0e19');
            _aiBlocked = false;
            submitBtn.disabled = false;
            submitBtn.style.opacity = '';
        } else {
            // spam
            feedback.style.background  = 'rgba(239,68,68,0.12)';
            feedback.style.border      = '1px solid rgba(239,68,68,0.35)';
            feedback.style.color       = '#f87171';
            feedback.innerHTML = '\u274c ' + (data.message || '\u0e23\u0e32\u0e22\u0e25\u0e30\u0e40\u0e2d\u0e35\u0e22\u0e14\u0e44\u0e21\u0e48\u0e2a\u0e21\u0e40\u0e2b\u0e15\u0e38\u0e2a\u0e21\u0e1c\u0e25 \u0e01\u0e23\u0e38\u0e13\u0e32\u0e23\u0e30\u0e1a\u0e38\u0e1b\u0e31\u0e0d\u0e2b\u0e32\u0e43\u0e2b\u0e49\u0e0a\u0e31\u0e14\u0e40\u0e08\u0e19');
            _aiBlocked = true;
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.45';
        }
    }

    const repairForm = document.getElementById('repair-form');
    if (repairForm) {
        repairForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const submitBtn = document.getElementById('repair_submit_btn');
            if (!submitBtn) return;

            const scheduledDateInput = document.getElementById('scheduled_date_input');
            const startTimeInput = document.getElementById('scheduled_time_start_input');
            const endTimeInput = document.getElementById('scheduled_time_end_input');
            const technicianNameInput = document.getElementById('technician_name_input');
            const technicianPhoneInput = document.getElementById('technician_phone_input');

            if (!scheduledDateInput || !startTimeInput || !endTimeInput || !technicianNameInput || !technicianPhoneInput) {
                showRepairAlert('ไม่พบช่องข้อมูลนัดหมายที่จำเป็น', 'error');
                return;
            }

            if (!scheduledDateInput.value || !startTimeInput.value || !endTimeInput.value || !technicianNameInput.value.trim() || !technicianPhoneInput.value.trim()) {
                showRepairAlert('กรุณากรอกข้อมูลนัดหมายให้ครบ: วันที่ เวลา ชื่อช่าง และเบอร์โทร', 'error');
                return;
            }

            if (startTimeInput.value >= endTimeInput.value) {
                showRepairAlert('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น', 'error');
                return;
            }

            const phoneDigits = technicianPhoneInput.value.replace(/\D+/g, '');
            if (phoneDigits.length < 8) {
                showRepairAlert('กรุณาระบุเบอร์โทรช่างให้ถูกต้อง', 'error');
                return;
            }

            if (_aiBlocked) {
                showRepairAlert('กรุณาปรับรายละเอียดการแจ้งซ่อมก่อนส่ง', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.55';

            try {
                const formData = new FormData(repairForm);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (parseErr) {
                    throw new Error('รูปแบบข้อมูลตอบกลับไม่ถูกต้อง');
                }

                if (!response.ok || !data || data.success !== true) {
                    throw new Error((data && data.message) ? data.message : 'ไม่สามารถส่งแจ้งซ่อมได้');
                }

                showRepairAlert(data.message || 'แจ้งซ่อมเรียบร้อยแล้ว', 'success');
                if (data.repair) {
                    prependRepairItem(data.repair);
                }
                syncPendingRepairBadge();
                repairForm.reset();
                clearRepairFormUi();
                initScheduledDateInput();
                _aiBlocked = false;
            } catch (err) {
                showRepairAlert((err && err.message) ? err.message : 'เกิดข้อผิดพลาดในการส่งข้อมูล', 'error');
            } finally {
                submitBtn.disabled = _aiBlocked;
                submitBtn.style.opacity = _aiBlocked ? '0.45' : '';
            }
        });
    }

    const repairHistoryList = document.getElementById('repair-history-list');
    if (repairHistoryList) {
        repairHistoryList.addEventListener('click', function(event) {
            const button = event.target.closest('.btn-cancel-repair');
            if (!button) return;

            const repairId = Number(button.getAttribute('data-repair-id') || '0');
            if (!repairId) return;

            requestCancelRepair(repairId, button);
        });
    }

    bindRepairImageInteractions(document);
    syncPendingRepairBadge();
    initScheduledDateInput();
    </script>
</body>
</html>
