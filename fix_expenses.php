<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "Fixing past booking deposit expenses...\n";

// Update all expenses that act as the first month and where the exp_total is incorrectly low.
// Only touch expenses where a deposit payment exists.
$stmt = $pdo->query("
    SELECT e.exp_id, e.room_price, e.exp_total, e.ctr_id, c.ctr_deposit
    FROM expense e
    INNER JOIN contract c ON e.ctr_id = c.ctr_id
    WHERE EXISTS (
        SELECT 1 FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_remark = 'มัดจำ'
    )
");

while ($row = $stmt->fetch()) {
    $exp_id = $row['exp_id'];
    $room_price = $row['room_price'];
    $exp_total = $row['exp_total'];
    $deposit = $row['ctr_deposit'];
    
    // Expected total: room_price + deposit. Note utilities are presumably 0 for the booking expense.
    $expected_total = $room_price + $deposit;
    if ($exp_total < $expected_total) {
        $update = $pdo->prepare("UPDATE expense SET exp_total = ? WHERE exp_id = ?");
        $update->execute([$expected_total, $exp_id]);
        echo "Fixed exp_id $exp_id for ctr_id {$row['ctr_id']}: changed $exp_total to $expected_total.\n";
    }
}
echo "Done.\n";
?>
