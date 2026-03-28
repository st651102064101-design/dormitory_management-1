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

    // ค้นหาสัญญาเดิมของห้องนี้ + ผู้เช่า
    $existingContractStmt = $pdo->prepare(
        "SELECT ctr_id, ctr_status FROM contract 
         WHERE room_id = ? AND tnt_id = ? 
         ORDER BY ctr_id DESC LIMIT 1"
    );
    $existingContractStmt->execute([$room_id, $tnt_id]);
    $existingContract = $existingContractStmt->fetch(PDO::FETCH_ASSOC);
    
    $isUpdate = false;
    $ctrId = null;
    
    if ($existingContract) {
        // พบสัญญาเดิม
        $existingStatus = $existingContract['ctr_status'];
        
        // ถ้าสัญญาเดิมเป็นสถานะ ยกเลิก (1) สามารถสร้างใหม่ได้
        if ($existingStatus === '1') {
            // สัญญาเก่าถูกยกเลิกแล้ว สามารถสร้างใหม่ได้
        } else {
            // สัญญาเดิมยังอยู่ (status 0 หรือ 2) → UPDATE แทน INSERT
            $isUpdate = true;
            $ctrId = (int)$existingContract['ctr_id'];
        }
    }
    
    // ถ้าเป็น UPDATE → ตรวจสอบเฉพาะว่าไม่มี contract อื่นของห้องนี้
    if (!$isUpdate) {
        $otherContractsStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM contract 
             WHERE room_id = ? AND ctr_status IN ('0','2') 
             AND (tnt_id != ? OR ctr_id != ?)"
        );
        $otherContractsStmt->execute([$room_id, $tnt_id, $ctrId ?? 0]);
        if ((int)$otherContractsStmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'ห้องนี้มีสัญญาห้องอื่นอยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
            header('Location: ../Reports/manage_contracts.php');
            exit;
        }
        
        $otherTenantStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM contract 
             WHERE tnt_id = ? AND ctr_status IN ('0','2') 
             AND (room_id != ? OR ctr_id != ?)"
        );
        $otherTenantStmt->execute([$tnt_id, $room_id, $ctrId ?? 0]);
        if ((int)$otherTenantStmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'ผู้เช่าคนนี้มีสัญญาอื่นอยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
            header('Location: ../Reports/manage_contracts.php');
            exit;
        }
    }

    $pdo->beginTransaction();

    if ($isUpdate) {
        // UPDATE สัญญาเดิม
        $updateContract = $pdo->prepare(
            "UPDATE contract 
             SET ctr_start = ?, ctr_end = ?, ctr_deposit = ?, ctr_status = '0'
             WHERE ctr_id = ?"
        );
        $updateContract->execute([$ctr_start, $ctr_end, $depositValue, $ctrId]);
        $actionMsg = 'อัปเดตสัญญาเรียบร้อยแล้ว';
    } else {
        // INSERT สัญญาใหม่
        $insert = $pdo->prepare("INSERT INTO contract (ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id) VALUES (?, ?, ?, '0', ?, ?)");
        $insert->execute([$ctr_start, $ctr_end, $depositValue, $tnt_id, $room_id]);
        $actionMsg = 'เพิ่มสัญญาเรียบร้อยแล้ว';
    }

    // ห้องที่มีสัญญาให้เป็นไม่ว่าง (1)
    $updateRoom = $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?");
    $updateRoom->execute([$room_id]);

    // ผู้เช่าที่มีสัญญาให้เป็นสถานะมีสัญญา/พักอาศัย (1)
    $updateTenant = $pdo->prepare("UPDATE tenant SET tnt_status = '1' WHERE tnt_id = ?");
    $updateTenant->execute([$tnt_id]);

    $pdo->commit();

    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $actionMsg]);
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
