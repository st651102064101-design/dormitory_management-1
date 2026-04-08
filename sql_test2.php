<?php
require_once 'ConnectDB.php';
$conn = connectDB();
$refundStmt = $conn->query("
    SELECT c.ctr_id, c.ctr_status, c.ctr_deposit,
            t.tnt_name, r.room_number,
            tm.term_date, tm.bank_name, tm.bank_account_name, tm.bank_account_number,
            (SELECT dr2.refund_status FROM deposit_refund dr2 WHERE dr2.ctr_id = c.ctr_id ORDER BY dr2.refund_id DESC LIMIT 1) AS refund_status
    FROM contract c
    JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    LEFT JOIN termination tm ON tm.ctr_id = c.ctr_id
    WHERE c.ctr_status IN ('1', '2')
        AND COALESCE(c.ctr_deposit, 0) > 0
        AND NOT EXISTS (
            SELECT 1 FROM deposit_refund dr
            WHERE dr.ctr_id = c.ctr_id AND dr.refund_status = '1'
            AND (tm.bank_account_number IS NOT NULL AND TRIM(tm.bank_account_number) != '')
        )
    ORDER BY tm.term_date ASC, c.ctr_id ASC
");
print_r($refundStmt->fetchAll(PDO::FETCH_ASSOC));
