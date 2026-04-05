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
        $conflict = $pdo->prepare(
            "SELECT COUNT(*) FROM contract c\n" .
            "LEFT JOIN termination t ON c.ctr_id = t.ctr_id\n" .
            "WHERE c.room_id = ? AND c.ctr_id <> ? AND (\n" .
            "    (c.ctr_status = '0' AND (c.ctr_end IS NULL OR c.ctr_end >= CURDATE())) OR \n" .
            "    (c.ctr_status = '2' AND (t.term_date IS NULL OR t.term_date >= CURDATE()))\n" .
            ")"
        );
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

    // === ตรวจสอบการชำระเงินครบถ้วนก่อนยกเลิกสัญญา ===
    if ($ctr_status === '1') {
        $unpaidStmt = $pdo->prepare("
            SELECT e.exp_id, e.exp_month, e.exp_total,
                   COALESCE((
                       SELECT SUM(p.pay_amount)
                       FROM payment p
                       WHERE p.exp_id = e.exp_id
                         AND p.pay_status = '1'
                         AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                   ), 0) AS paid_amount
            FROM expense e
            WHERE e.ctr_id = ?
            HAVING paid_amount < e.exp_total
        ");
        $unpaidStmt->execute([$ctr_id]);
        $unpaidBills = $unpaidStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($unpaidBills)) {
            $unpaidCount = count($unpaidBills);
            $errorMsg = 'ไม่สามารถยกเลิกสัญญาได้ เนื่องจากยังมีบิลค้างชำระ ' . $unpaidCount . ' รายการ กรุณาชำระให้ครบก่อน';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errorMsg, 'unpaid_count' => $unpaidCount]);
                exit;
            }
            $_SESSION['error'] = $errorMsg;
            header('Location: ../Reports/manage_contracts.php');
            exit;
        }

        // === ตรวจสอบการคืนเงินมัดจำก่อนยกเลิกสัญญา ===
        $depositInfoStmt = $pdo->prepare("
            SELECT bp.bp_amount
            FROM booking_payment bp
            INNER JOIN tenant_workflow tw ON tw.bkg_id = bp.bkg_id
            WHERE tw.ctr_id = ?
            ORDER BY bp.bp_id DESC LIMIT 1
        ");
        $depositInfoStmt->execute([$ctr_id]);
        $depositInfo = $depositInfoStmt->fetch(PDO::FETCH_ASSOC);

        if ($depositInfo && floatval($depositInfo['bp_amount']) > 0) {
            $refundConfirmedStmt = $pdo->prepare("
                SELECT refund_id FROM deposit_refund
                WHERE ctr_id = ? AND refund_status = '1'
                LIMIT 1
            ");
            $refundConfirmedStmt->execute([$ctr_id]);
            $refundConfirmed = $refundConfirmedStmt->fetch(PDO::FETCH_ASSOC);

            if (!$refundConfirmed) {
                $errorMsg = 'ไม่สามารถยกเลิกสัญญาได้ เนื่องจากยังไม่ได้ดำเนินการคืนเงินมัดจำ กรุณาบันทึกและยืนยันการคืนเงินมัดจำก่อน';
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $errorMsg, 'need_refund' => true]);
                    exit;
                }
                $_SESSION['error'] = $errorMsg;
                header('Location: ../Reports/manage_contracts.php');
                exit;
            }
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
