SELECT 
   (
      SELECT COUNT(*)
      FROM expense e
      WHERE e.ctr_id = c.ctr_id
        AND e.exp_month = DATE_FORMAT(CURDATE(), '%Y-%m-01')
   ) AS bill_created
FROM contract c
WHERE c.ctr_id = 775913211
