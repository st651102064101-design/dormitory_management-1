<?php
require 'ConnectDB.php';
$pdo = connectDB();
$compStmt = $pdo->prepare("
            SELECT e.exp_id, e.exp_month, e.exp_total, r.room_number,
                   COALESCE(SUM(CASE WHEN p.pay_status = '0' THEN p.pay_amount ELSE 0 END), 0) AS pending_amount,
                   COALESCE(SUM(CASE WHEN p.pay_status = '1' THEN p.pay_amount ELSE 0 END), 0) AS paid_amount,
                   MAX(CASE WHEN p.pay_status IN ('0', '1') THEN p.pay_date END) AS last_pay_date,
                   (SELECT pay_proof FROM payment WHERE exp_id = e.exp_id AND pay_status IN ('0', '1') AND pay_proof IS NOT NULL ORDER BY pay_date DESC LIMIT 1) AS pay_proof
            FROM expense e
            JOIN contract c ON e.ctr_id = c.ctr_id
            JOIN room r ON c.room_id = r.room_id
            LEFT JOIN payment p ON p.exp_id = e.exp_id
            WHERE e.exp_id = ? AND e.ctr_id = ?
            GROUP BY e.exp_id, e.exp_month, e.exp_total, r.room_number
        ");
$compStmt->execute([775775590, 775775589]);
print_r($compStmt->fetch(PDO::FETCH_ASSOC));
