<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_contracts.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $tnt_id = trim($_POST['tnt_id'] ?? '');
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $ctr_start = $_POST['ctr_start'] ?? '';
    $ctr_end = $_POST['ctr_end'] ?? '';
    $ctr_deposit = $_POST['ctr_deposit'] ?? '';
    $contractDuration = isset($_POST['contract_duration']) ? max(1, min(36, (int)$_POST['contract_duration'])) : 6;

    // Fallbacks if JS didn't populate dates
    if ($ctr_start === '') {
        $ctr_start = date('Y-m-d');
    }
    if ($ctr_end === '') {
        $ctr_end = date('Y-m-d', strtotime("+{$contractDuration} months", strtotime($ctr_start)));
    }

    if ($tnt_id === '' || $room_id <= 0 || $ctr_start === '' || $ctr_end === '') {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    if ($ctr_end < $ctr_start) {
        $_SESSION['error'] = 'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มสัญญา';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    $depositValue = ($ctr_deposit === '' ? 0 : (int)$ctr_deposit);

    // ตรวจสอบห้องพัก
    $roomStmt = $pdo->prepare('SELECT room_status FROM room WHERE room_id = ?');
    $roomStmt->execute([$room_id]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        $_SESSION['error'] = 'ไม่พบข้อมูลห้องพัก';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    // ตรวจสอบซ้ำ: ห้องนี้มีสัญญาที่ยังใช้อยู่หรือไม่
    $activeContractStmt = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE room_id = ? AND ctr_status IN ('0','2')");
    $activeContractStmt->execute([$room_id]);
    if ((int)$activeContractStmt->fetchColumn() > 0) {
        $_SESSION['error'] = 'ห้องนี้มีสัญญาอยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    // ตรวจสอบซ้ำ: ผู้เช่าคนนี้มีสัญญาที่ยังใช้อยู่หรือไม่
    $tenantActiveStmt = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE tnt_id = ? AND ctr_status IN ('0','2')");
    $tenantActiveStmt->execute([$tnt_id]);
    if ((int)$tenantActiveStmt->fetchColumn() > 0) {
        $_SESSION['error'] = 'ผู้เช่าคนนี้มีสัญญาอยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    // ตรวจสอบซ้ำ: วันที่ของสัญญาไม่ทับซ้อนกันสำหรับห้องนี้
    $overlapStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM contract 
         WHERE room_id = ? 
         AND ctr_status IN ('0','2')
         AND (
           (ctr_start <= ? AND ctr_end >= ?)
           OR (ctr_start <= ? AND ctr_end >= ?)
           OR (ctr_start >= ? AND ctr_end <= ?)
         )"
    );
    $overlapStmt->execute([
        $room_id,
        $ctr_end, $ctr_start,          // existing overlaps with new period
        $ctr_start, $ctr_start,        // existing starts before new ends
        $ctr_start, $ctr_end           // new period fully contains existing
    ]);
    if ((int)$overlapStmt->fetchColumn() > 0) {
        $_SESSION['error'] = 'วันที่ของสัญญาทับซ้อนกับสัญญาอื่นของห้องนี้';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    $pdo->beginTransaction();

    $insert = $pdo->prepare("INSERT INTO contract (ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id) VALUES (?, ?, ?, '0', ?, ?)");
    $insert->execute([$ctr_start, $ctr_end, $depositValue, $tnt_id, $room_id]);

    // ห้องที่มีสัญญาใหม่ให้เป็นไม่ว่าง (1)
    $updateRoom = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    $updateRoom->execute([$room_id]);

    // ผู้เช่าที่มีสัญญาใหม่ให้เป็นสถานะมีสัญญา/พักอาศัย (1)
    $updateTenant = $pdo->prepare("UPDATE tenant SET tnt_status = '1' WHERE tnt_id = ?");
    $updateTenant->execute([$tnt_id]);

    $pdo->commit();

    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'เพิ่มสัญญาเรียบร้อยแล้ว']);
        exit;
    }

    $_SESSION['success'] = 'เพิ่มสัญญาเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_contracts.php');
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // แปล database trigger errors เป็นภาษาไทย
    $rawErrorMsg = $e->getMessage();
    $errorMsg = 'เกิดข้อผิดพลาด: ' . $rawErrorMsg;
    
    // Extract MESSAGE_TEXT from trigger (format: "1644 Message Text")
    if (preg_match('/\b\d+\s+(.+)$/i', $rawErrorMsg, $matches)) {
        $triggerMsg = trim($matches[1]);
    } else {
        $triggerMsg = $rawErrorMsg;
    }
    
    // ตรวจสอบ trigger error messages และแปล
    if (stripos($triggerMsg, 'Room already has active contract') !== false) {
        $errorMsg = '❌ ห้องนี้มีสัญญาที่ยังใช้อยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
    } elseif (stripos($triggerMsg, 'Tenant already has active contract') !== false) {
        $errorMsg = '❌ ผู้เช่าคนนี้มีสัญญาที่ยังใช้อยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
    } elseif (stripos($triggerMsg, 'date overlap') !== false || stripos($triggerMsg, 'overlap') !== false) {
        $errorMsg = '❌ วันที่ของสัญญาทับซ้อนกับสัญญาอื่นของห้องนี้';
    } elseif (stripos($triggerMsg, 'Duplicate') !== false) {
        // Generic catch-all for any "Duplicate" message
        $errorMsg = '❌ สัญญาซ้ำ - ไม่สามารถสร้างสัญญาซ้ำสำหรับห้องหรือผู้เช่าเดียวกันได้';
    }
    
    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }
    
    $_SESSION['error'] = $errorMsg;
    header('Location: ../Reports/manage_contracts.php');
    exit;
}
