<?php
$file = 'Tenant/termination.php';
$content = file_get_contents($file);

$phpBlock = <<<'PHP'
$terminationAllowed = false;
$terminationReason = '';

try {
    $termCheckStmt = $pdo->prepare("
        SELECT 
           (
              SELECT step_5_confirmed
              FROM tenant_workflow
              WHERE tnt_id = c.tnt_id
              ORDER BY id DESC LIMIT 1
           ) AS is_step5_complete,
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
           ) AS has_current_month_bill,
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND e.exp_total > COALESCE((
                    SELECT SUM(p.pay_amount) 
                    FROM payment p 
                    WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                ), 0)
           ) AS unpaid_bills_count,
           (
              SELECT COUNT(*)
              FROM payment p
              JOIN expense e ON p.exp_id = e.exp_id
              WHERE e.ctr_id = c.ctr_id AND p.pay_status = '0'
           ) AS unverified_payments_count
        FROM contract c
        WHERE c.ctr_id = ?
    ");
    $termCheckStmt->execute([$contract['ctr_id']]);
    $termData = $termCheckStmt->fetch(PDO::FETCH_ASSOC);

    if ($termData) {
        if ((int)$termData['is_step5_complete'] !== 1) {
            $terminationReason = 'รอให้เจ้าหน้าที่ดำเนินการข้อมูลการเข้าพักของคุณให้เสร็จสิ้น (ขั้นตอนที่ 5)';
        } elseif ((int)$termData['has_current_month_bill'] === 0) {
            $terminationReason = 'กรุณารอให้เจ้าหน้าที่จดมิเตอร์และออกบิลค่าใช้จ่ายของเดือนล่าสุดให้เรียบร้อยก่อนแจ้งยกเลิกสัญญา';
        } elseif ((int)$termData['unpaid_bills_count'] > 0) {
            $terminationReason = 'ไม่สามารถแจ้งยกเลิกสัญญาได้ เนื่องจากมียอดค้างชำระจำนวน ' . $termData['unpaid_bills_count'] . ' รายการ หรือมีบิลใหม่ที่เพิ่งออก กรุณาชำระค่าห้องให้ครบก่อน';
        } elseif ((int)$termData['unverified_payments_count'] > 0) {
            $terminationReason = 'มีสลิปการชำระเงินที่รอให้เจ้าหน้าที่ตรวจสอบ กรุณารอเจ้าหน้าที่ตรวจสอบความถูกต้องก่อนจึงจะสามารถแจ้งยกเลิกสัญญาได้';
        } else {
            $terminationAllowed = true;
        }
    }
} catch (PDOException $e) { 
    error_log("PDOException checking termination eligibility: " . $e->getMessage()); 
}
PHP;

$content = str_replace('$settings = getSystemSettings($pdo);', "\$settings = getSystemSettings(\$pdo);\n\n" . $phpBlock, $content);
file_put_contents($file, $content);
echo "Patched termination.php\n";
