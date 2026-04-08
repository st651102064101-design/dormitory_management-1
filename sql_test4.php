<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once 'ConnectDB.php';
$conn = connectDB();
$sql = "
    SELECT c.ctr_id, c.ctr_status, c.ctr_deposit,
            t.tnt_name, r.room_number,
            tm.term_date, tm.bank_name, tm.bank_account_name, tm.bank_account_number
    FROM contract c
    JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    JOIN termination tm ON tm.term_id = (
        SELECT MAX(term_id) FROM termination WHERE ctr_id = c.ctr_id
    )
    WHERE c.ctr_status IN ('1', '2')
        AND COALESCE(c.ctr_deposit, 0) > 0
";
$stmt = $conn->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
