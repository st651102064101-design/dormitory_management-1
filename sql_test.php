<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->query("SELECT c.ctr_id, r.room_number, c.ctr_status, t.tnt_name, term.term_date, term.bank_name 
FROM contract c 
JOIN room r ON c.room_id=r.room_id
LEFT JOIN tenant t ON c.tnt_id = t.tnt_id 
LEFT JOIN (SELECT ctr_id, term_date, bank_name FROM termination WHERE term_id IN (SELECT MAX(term_id) FROM termination GROUP BY ctr_id)) as term ON c.ctr_id = term.ctr_id
WHERE c.ctr_status IN ('1', '2')
");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
