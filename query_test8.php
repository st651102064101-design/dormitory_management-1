<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
            SELECT e.exp_id, e.exp_month, e.exp_total, r.room_number,
                   COALESCE(SUM(CASE WHEN p.pay_status = '0' THEN p.pay_amount ELSE 0 END), 0) AS pending_amount,
                   MAX(CASE WHEN p.pay_status = '0' THEN p.pay_date END) AS pending_date,
                   MAX(p.pay_proof) as pay_proof
            FROM expense e
            JOIN contract c ON e.ctr_id = c.ctr_id
            JOIN room r ON c.room_id = r.room_id
            LEFT JOIN payment p ON p.exp_id = e.exp_id
              
            WHERE e.exp_id = ? AND e.ctr_id = ?
            GROUP BY e.exp_id, e.exp_month, e.exp_total, r.room_number
            HAVING SUM(CASE WHEN p.pay_status = '0' THEN p.pay_amount ELSE 0 END) > 0
");
$stmt->execute([775775590, 775775589]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
