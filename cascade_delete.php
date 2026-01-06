<?php
/**
 * Cascade Delete - Delete expense and all related data (contract, booking, tenant)
 * Usage: cascade_delete.php?expense_id=123
 */

declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

// Check if admin is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('❌ Unauthorized - Admin access required');
}

$expenseId = isset($_GET['expense_id']) ? (int)$_GET['expense_id'] : 0;

if ($expenseId === 0) {
    die('❌ Error: expense_id is required');
}

echo "🗑️ เริ่มกระบวนการลบข้อมูล Expense ID: {$expenseId}\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Get expense details
    $expenseStmt = $pdo->prepare("SELECT * FROM expense WHERE exp_id = ?");
    $expenseStmt->execute([$expenseId]);
    $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        throw new Exception("ไม่พบ Expense ID: {$expenseId}");
    }
    
    $contractId = $expense['ctr_id'];
    echo "✓ พบ Expense ID: {$expenseId}\n";
    echo "  Contract ID: {$contractId}\n\n";
    
    // 2. Get contract details
    $contractStmt = $pdo->prepare("SELECT * FROM contract WHERE ctr_id = ?");
    $contractStmt->execute([$contractId]);
    $contract = $contractStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        throw new Exception("ไม่พบ Contract ID: {$contractId}");
    }
    
    $tenantId = $contract['tnt_id'];
    $roomId = $contract['room_id'];
    echo "✓ พบ Contract ID: {$contractId}\n";
    echo "  Tenant ID: {$tenantId}\n";
    echo "  Room ID: {$roomId}\n\n";
    
    // 3. Get tenant details
    $tenantStmt = $pdo->prepare("SELECT tnt_name FROM tenant WHERE tnt_id = ?");
    $tenantStmt->execute([$tenantId]);
    $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
    $tenantName = $tenant['tnt_name'] ?? 'ไม่ทราบชื่อ';
    echo "✓ พบ Tenant: {$tenantName}\n\n";
    
    // 4. Delete related payments
    $paymentStmt = $pdo->prepare("DELETE FROM payment WHERE exp_id = ?");
    $paymentStmt->execute([$expenseId]);
    $deletedPayments = $paymentStmt->rowCount();
    echo "🗑️ ลบ Payment: {$deletedPayments} รายการ\n";
    
    // 5. Delete expense
    $deleteExpense = $pdo->prepare("DELETE FROM expense WHERE exp_id = ?");
    $deleteExpense->execute([$expenseId]);
    echo "🗑️ ลบ Expense ID: {$expenseId}\n";
    
    // 6. Check if contract has other expenses
    $otherExpenseStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ?");
    $otherExpenseStmt->execute([$contractId]);
    $otherExpenses = (int)$otherExpenseStmt->fetchColumn();
    
    if ($otherExpenses > 0) {
        echo "⚠️  Contract ID {$contractId} ยังมี Expense อื่นอยู่ ({$otherExpenses} รายการ) - ไม่ลบ Contract\n";
    } else {
        // 7. Delete contract
        $deleteContract = $pdo->prepare("DELETE FROM contract WHERE ctr_id = ?");
        $deleteContract->execute([$contractId]);
        echo "🗑️ ลบ Contract ID: {$contractId}\n";
        
        // 8. Find and delete related bookings
        $bookingStmt = $pdo->prepare("SELECT bkg_id FROM booking WHERE tnt_id = ? AND room_id = ?");
        $bookingStmt->execute([$tenantId, $roomId]);
        $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bookings as $booking) {
            $deleteBooking = $pdo->prepare("DELETE FROM booking WHERE bkg_id = ?");
            $deleteBooking->execute([$booking['bkg_id']]);
            echo "🗑️ ลบ Booking ID: {$booking['bkg_id']}\n";
        }
        
        // 9. Check if tenant has other contracts or bookings
        $otherContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE tnt_id = ?");
        $otherContractsStmt->execute([$tenantId]);
        $otherContracts = (int)$otherContractsStmt->fetchColumn();
        
        $otherBookingsStmt = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE tnt_id = ? AND bkg_status IN ('1','2')");
        $otherBookingsStmt->execute([$tenantId]);
        $otherBookings = (int)$otherBookingsStmt->fetchColumn();
        
        if ($otherContracts > 0 || $otherBookings > 0) {
            echo "⚠️  Tenant ID {$tenantId} ยังมี Contract/Booking อื่นอยู่ - ไม่ลบ Tenant\n";
        } else {
            // 10. Delete tenant
            $deleteTenant = $pdo->prepare("DELETE FROM tenant WHERE tnt_id = ?");
            $deleteTenant->execute([$tenantId]);
            echo "🗑️ ลบ Tenant ID: {$tenantId} ({$tenantName})\n";
        }
        
        // 11. Update room status to vacant
        $updateRoom = $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = ?");
        $updateRoom->execute([$roomId]);
        echo "🔄 อัพเดท Room ID {$roomId} เป็น 'ว่าง'\n";
    }
    
    $pdo->commit();
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ ลบข้อมูลสำเร็จทั้งหมด!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
    echo "🔄 ยกเลิกการลบข้อมูลทั้งหมด (Rollback)\n";
}
