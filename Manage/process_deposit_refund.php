<?php
/**
 * Process Deposit Refund - จัดการคืนเงินมัดจำ
 */
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    $action = $_POST['action'] ?? '';
    $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;

    if ($ctr_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลสัญญาไม่ถูกต้อง']);
        exit;
    }

    $ctrStmt = $pdo->prepare('SELECT ctr_id, ctr_deposit, ctr_status FROM contract WHERE ctr_id = ?');
    $ctrStmt->execute([$ctr_id]);
    $contract = $ctrStmt->fetch(PDO::FETCH_ASSOC);
    if (!$contract) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลสัญญา']);
        exit;
    }

    switch ($action) {
        case 'create':
            $deduction_amount = max(0, (int)($_POST['deduction_amount'] ?? 0));
            $deduction_reason = trim($_POST['deduction_reason'] ?? '');
            $deposit_amount = (int)($contract['ctr_deposit'] ?? 0);

            if ($deposit_amount <= 0) {
                $bpStmt = $pdo->prepare("
                    SELECT bp.bp_amount FROM booking_payment bp
                    INNER JOIN tenant_workflow tw ON tw.bkg_id = bp.bkg_id
                    WHERE tw.ctr_id = ? AND bp.bp_status = '1'
                    ORDER BY bp.bp_id DESC LIMIT 1
                ");
                $bpStmt->execute([$ctr_id]);
                $bp = $bpStmt->fetch(PDO::FETCH_ASSOC);
                if ($bp) $deposit_amount = (int)$bp['bp_amount'];
            }

            if ($deposit_amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลเงินมัดจำ']);
                exit;
            }
            if ($deduction_amount > $deposit_amount) {
                echo json_encode(['success' => false, 'error' => 'ยอดหักเกินจำนวนเงินมัดจำ']);
                exit;
            }

            $refund_amount = $deposit_amount - $deduction_amount;

            $existStmt = $pdo->prepare('SELECT refund_id, refund_status FROM deposit_refund WHERE ctr_id = ? LIMIT 1');
            $existStmt->execute([$ctr_id]);
            $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['refund_status'] === '1') {
                    echo json_encode(['success' => false, 'error' => 'ไม่สามารถแก้ไขได้ เนื่องจากโอนคืนเงินแล้ว']);
                    exit;
                }
                $upd = $pdo->prepare("UPDATE deposit_refund SET deposit_amount=?, deduction_amount=?, deduction_reason=?, refund_amount=? WHERE refund_id=?");
                $upd->execute([$deposit_amount, $deduction_amount, $deduction_reason, $refund_amount, $existing['refund_id']]);
                $refundId = (int)$existing['refund_id'];
            } else {
                $ins = $pdo->prepare("INSERT INTO deposit_refund (ctr_id, deposit_amount, deduction_amount, deduction_reason, refund_amount) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$ctr_id, $deposit_amount, $deduction_amount, $deduction_reason, $refund_amount]);
                $refundId = (int)$pdo->lastInsertId();
            }

            echo json_encode([
                'success' => true,
                'message' => 'บันทึกข้อมูลคืนเงินมัดจำเรียบร้อย',
                'refund' => ['refund_id' => $refundId, 'deposit_amount' => $deposit_amount, 'deduction_amount' => $deduction_amount, 'refund_amount' => $refund_amount, 'refund_status' => '0']
            ]);
            break;

        case 'upload':
            if (empty($_FILES['refund_proof']) || $_FILES['refund_proof']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'กรุณาอัพโหลดหลักฐานการโอนเงิน']);
                exit;
            }
            $existStmt = $pdo->prepare('SELECT refund_id, refund_status FROM deposit_refund WHERE ctr_id = ? LIMIT 1');
            $existStmt->execute([$ctr_id]);
            $existing = $existStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                echo json_encode(['success' => false, 'error' => 'กรุณาบันทึกข้อมูลคืนเงินก่อน']);
                exit;
            }

            $file = $_FILES['refund_proof'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
            if (!in_array($ext, $allowed, true)) {
                echo json_encode(['success' => false, 'error' => 'ประเภทไฟล์ไม่รองรับ']);
                exit;
            }

            $uploadDir = __DIR__ . '/../Public/Assets/Images/Payments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newName = 'refund_' . $ctr_id . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัพโหลดไฟล์ได้']);
                exit;
            }

            $dbPath = 'dormitory_management/Public/Assets/Images/Payments/' . $newName;
            $pdo->prepare("UPDATE deposit_refund SET refund_proof=? WHERE refund_id=?")->execute([$dbPath, $existing['refund_id']]);

            echo json_encode(['success' => true, 'message' => 'อัพโหลดหลักฐานเรียบร้อย', 'proof_path' => $dbPath]);
            break;

        case 'confirm':
            $existStmt = $pdo->prepare('SELECT refund_id, refund_status, refund_proof, refund_amount FROM deposit_refund WHERE ctr_id = ? LIMIT 1');
            $existStmt->execute([$ctr_id]);
            $existing = $existStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลคืนเงินมัดจำ']);
                exit;
            }
            if ($existing['refund_status'] === '1') {
                echo json_encode(['success' => false, 'error' => 'คืนเงินมัดจำเรียบร้อยแล้ว']);
                exit;
            }
            if (empty($existing['refund_proof']) && (int)$existing['refund_amount'] > 0) {
                echo json_encode(['success' => false, 'error' => 'กรุณาอัพโหลดหลักฐานการโอนเงินก่อน']);
                exit;
            }

            // ต้องมีเลขบัญชีธนาคารในระบบก่อนยืนยันการคืนเงิน
            $bankStmt = $pdo->prepare('SELECT bank_account_number FROM termination WHERE ctr_id = ? ORDER BY term_id DESC LIMIT 1');
            $bankStmt->execute([$ctr_id]);
            $bankRow = $bankStmt->fetch(PDO::FETCH_ASSOC);
            if (!$bankRow || empty(trim($bankRow['bank_account_number'] ?? ''))) {
                echo json_encode(['success' => false, 'error' => 'ไม่สามารถยืนยันได้ เนื่องจากยังไม่พบเลขบัญชีธนาคารที่ต้องโอนเงินให้ผู้เช่า']);
                exit;
            }

            $pdo->prepare("UPDATE deposit_refund SET refund_status='1', refund_date=NOW() WHERE refund_id=?")->execute([$existing['refund_id']]);

            echo json_encode(['success' => true, 'message' => 'ยืนยันการคืนเงินมัดจำเรียบร้อย']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action ไม่ถูกต้อง']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
