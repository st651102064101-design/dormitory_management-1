<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

$exp_id = (int)($_POST['exp_id'] ?? 0);

if (!$exp_id) {
    die(json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']));
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dormitory_db;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    $pdo->beginTransaction();
    
    // Get expense and contract details
    $expenseStmt = $pdo->prepare("SELECT * FROM expense WHERE exp_id = ?");
    $expenseStmt->execute([$exp_id]);
    $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        throw new Exception("ไม่พบรายการค่าใช้จ่าย");
    }
    
    $contractId = $expense['ctr_id'];
    
    // Get contract details
    $contractStmt = $pdo->prepare("SELECT * FROM contract WHERE ctr_id = ?");
    $contractStmt->execute([$contractId]);
    $contract = $contractStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        throw new Exception("ไม่พบสัญญา");
    }
    
    $tenantId = $contract['tnt_id'];
    $roomId = $contract['room_id'];
    
    // 1. Delete related payments
    $deletePayments = $pdo->prepare("DELETE FROM payment WHERE exp_id = ?");
    $deletePayments->execute([$exp_id]);
    
    // 2. Delete expense
    $deleteExpense = $pdo->prepare("DELETE FROM expense WHERE exp_id = ?");
    $deleteExpense->execute([$exp_id]);
    
    // 3. Check if contract has other expenses
    $otherExpenseStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ?");
    $otherExpenseStmt->execute([$contractId]);
    $otherExpenses = (int)$otherExpenseStmt->fetchColumn();
    
    $deletedItems = ['expense' => 1, 'payments' => $deletePayments->rowCount()];
    
    if ($otherExpenses === 0) {
        // No other expenses, delete contract
        $deleteContract = $pdo->prepare("DELETE FROM contract WHERE ctr_id = ?");
        $deleteContract->execute([$contractId]);
        $deletedItems['contract'] = 1;
        
        // 4. Find and delete related bookings
        $bookingStmt = $pdo->prepare("SELECT bkg_id FROM booking WHERE tnt_id = ? AND room_id = ?");
        $bookingStmt->execute([$tenantId, $roomId]);
        $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deletedBookings = 0;
        foreach ($bookings as $booking) {
            $deleteBooking = $pdo->prepare("DELETE FROM booking WHERE bkg_id = ?");
            $deleteBooking->execute([$booking['bkg_id']]);
            $deletedBookings++;
        }
        $deletedItems['bookings'] = $deletedBookings;
        
        // 5. Check if tenant has other contracts or bookings
        $otherContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE tnt_id = ?");
        $otherContractsStmt->execute([$tenantId]);
        $otherContracts = (int)$otherContractsStmt->fetchColumn();
        
        $otherBookingsStmt = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE tnt_id = ? AND bkg_status IN ('1','2')");
        $otherBookingsStmt->execute([$tenantId]);
        $otherBookings = (int)$otherBookingsStmt->fetchColumn();
        
        if ($otherContracts === 0 && $otherBookings === 0) {
            // No other contracts/bookings, delete tenant
            $deleteTenant = $pdo->prepare("DELETE FROM tenant WHERE tnt_id = ?");
            $deleteTenant->execute([$tenantId]);
            $deletedItems['tenant'] = 1;
        }
        
        // 6. Update room status to vacant
        $updateRoom = $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = ?");
        $updateRoom->execute([$roomId]);
        $deletedItems['room_updated'] = 1;
    }
    
    $pdo->commit();
    
    $message = 'ลบข้อมูลเรียบร้อย: ' . implode(', ', array_map(function($k, $v) {
        return "{$v} {$k}";
    }, array_keys($deletedItems), $deletedItems));
    
    echo json_encode(['success' => true, 'message' => $message, 'deleted' => $deletedItems]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
