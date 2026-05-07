<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
// Get exactly from manage_payments.php main query logic
// Check how they are returned from main query
$sql = "
    SELECT p.*,
           e.exp_id as e_exp_id, e.exp_month, e.exp_total, e.exp_status,
       c.ctr_id as c_ctr_id, c.room_id, c.ctr_status,
           t.tnt_id, t.tnt_name, t.tnt_phone,
           r.room_number,
           rt.type_name
    FROM payment p
    LEFT JOIN expense e ON p.exp_id = e.exp_id
    LEFT JOIN contract c ON e.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    WHERE p.exp_id = 777608161
    ORDER BY p.pay_id DESC
";
$stmt = $pdo->query($sql);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r(array_map(function($p) { return $p['pay_id'] . " ctr:" . $p['ctr_id'] . " exp:" . $p['exp_id']; }, $payments));
