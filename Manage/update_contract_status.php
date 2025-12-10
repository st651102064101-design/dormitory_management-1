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

    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
    $ctr_status = $_POST['ctr_status'] ?? '';

    if ($ctr_id <= 0 || !in_array($ctr_status, ['0', '1', '2'], true)) {
        $errorMsg = 'ข้อมูลไม่ครบถ้วน';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit;
        }
        $_SESSION['error'] = $errorMsg;
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT room_id, ctr_status, tnt_id FROM contract WHERE ctr_id = ?');
    $stmt->execute([$ctr_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contract) {
        $errorMsg = 'ไม่พบข้อมูลสัญญา';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit;
        }
        $_SESSION['error'] = $errorMsg;
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    if ($contract['ctr_status'] === $ctr_status) {
        $_SESSION['success'] = 'สถานะสัญญายังคงเหมือนเดิม';
        header('Location: ../Reports/manage_contracts.php');
        exit;
    }

    if ($ctr_status === '0') {
        $conflict = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE room_id = ? AND ctr_id <> ? AND ctr_status IN ('0','2')");
        $conflict->execute([(int)$contract['room_id'], $ctr_id]);
        if ((int)$conflict->fetchColumn() > 0) {
            $errorMsg = 'ไม่สามารถกลับเป็นสถานะปกติได้ เนื่องจากมีสัญญาอื่นที่ใช้งานอยู่ในห้องนี้';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            }
            $_SESSION['error'] = $errorMsg;
            header('Location: ../Reports/manage_contracts.php');
            exit;
        }
    }

    $pdo->beginTransaction();

    $updateCtr = $pdo->prepare('UPDATE contract SET ctr_status = ? WHERE ctr_id = ?');
    $updateCtr->execute([$ctr_status, $ctr_id]);

    $room_id = (int)$contract['room_id'];
    $tnt_id = $contract['tnt_id'] ?? '';
    if ($room_id > 0) {
        if ($ctr_status === '1') { // ยกเลิกสัญญา
            $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = ?")->execute([$room_id]);
            if ($tnt_id !== '') {
                // ผู้เช่า = ย้ายออก (0)
                $pdo->prepare("UPDATE tenant SET tnt_status = '0' WHERE tnt_id = ?")->execute([$tnt_id]);
            }
        } elseif ($ctr_status === '0' || $ctr_status === '2') { // ปกติ หรือ แจ้งยกเลิก
            $pdo->prepare("UPDATE room SET room_status = '1' WHERE room_id = ?")->execute([$room_id]);
            if ($tnt_id !== '') {
                // ผู้เช่า = พักอยู่ (1)
                $pdo->prepare("UPDATE tenant SET tnt_status = '1' WHERE tnt_id = ?")->execute([$tnt_id]);
            }
        }
    }

    $pdo->commit();

    $statusMessage = [
        '0' => 'แก้ไขสถานะสัญญาเป็นปกติแล้ว',
        '1' => 'แก้ไขสัญญาเป็นยกเลิกเรียบร้อยแล้ว',
        '2' => 'แก้ไขการแจ้งยกเลิกเรียบร้อยแล้ว',
    ];
    
    $message = $statusMessage[$ctr_status] ?? 'แก้ไขข้อมูลเรียบร้อยแล้ว';
    
    // ตรวจสอบว่าเป็น AJAX request หรือไม่
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }
    
    $_SESSION['success'] = $message;
    header('Location: ../Reports/manage_contracts.php');
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMsg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    
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
