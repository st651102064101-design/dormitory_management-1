<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$q = "
        SELECT
          (SELECT COUNT(*) FROM contract WHERE ctr_status = '2') AS termination_requested,
          (
            SELECT COUNT(*)
            FROM contract c
            JOIN termination tm ON tm.term_id = (
                SELECT MAX(term_id) FROM termination WHERE ctr_id = c.ctr_id
            )
            WHERE c.ctr_status IN ('1', '2')
              AND COALESCE(c.ctr_deposit, 0) > 0
              AND tm.bank_account_number IS NOT NULL 
              AND TRIM(tm.bank_account_number) != ''
              AND NOT EXISTS (
                  SELECT 1 FROM deposit_refund dr
                  WHERE dr.ctr_id = c.ctr_id AND dr.refund_status = '1'
              )
          ) AS refund_pending
";
print_r($pdo->query($q)->fetch(PDO::FETCH_ASSOC));
