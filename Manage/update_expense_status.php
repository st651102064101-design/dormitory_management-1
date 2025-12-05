<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Reports/manage_expenses.php');
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $exp_id = isset($_POST['exp_id']) ? (int)$_POST['exp_id'] : 0;
    $exp_status = $_POST['exp_status'] ?? '';

    if ($exp_id <= 0 || !in_array($exp_status, ['0', '1'], true)) {
        $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
        header('Location: ../Reports/manage_expenses.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT exp_status FROM expense WHERE exp_id = ?');
    $stmt->execute([$exp_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        $_SESSION['error'] = 'ไม่พบข้อมูลค่าใช้จ่าย';
        header('Location: ../Reports/manage_expenses.php');
        exit;
    }

    $update = $pdo->prepare('UPDATE expense SET exp_status = ? WHERE exp_id = ?');
    $update->execute([$exp_status, $exp_id]);

    $statusMessage = [
        '0' => 'ยกเลิกการชำระเงินแล้ว',
        '1' => 'แก้ไขการชำระเงินเรียบร้อยแล้ว',
    ];
    
    $_SESSION['success'] = $statusMessage[$exp_status] ?? 'แก้ไขสถานะเรียบร้อยแล้ว';
    header('Location: ../Reports/manage_expenses.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: ../Reports/manage_expenses.php');
    exit;
}
