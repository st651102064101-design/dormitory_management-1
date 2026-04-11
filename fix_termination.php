<?php
$files = ['Tenant/index.php', 'Tenant/report_contract.php', 'Tenant/termination.php'];

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

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Remove the old unpaid count logic
    $content = preg_replace('/\$unpaidCountForTermination\s*=\s*0;.*?catch\s*\([^\)]+\)\s*\{\s*error_log\([^\)]+\);\s*\}/s', $phpBlock, $content);
    if ($file === 'Tenant/index.php') {
        $content = preg_replace('/<\?php if \(\(\$contract\[\'ctr_status\'\] \?\? \'0\'\) !== \'1\' && \$unpaidCountForTermination > 0\): \?>/s', "<?php if ((\$contract['ctr_status'] ?? '0') !== '1' && !\$terminationAllowed): ?>", $content);
        $content = preg_replace('/alert\(\'ไม่สามารถแจ้งยกเลิกสัญญาได้ เนื่องจากยังมีบิลค้างชำระ <\?php echo \$unpaidCountForTermination; \?> รายการ กรุณาชำระค่าห้องให้ครบก่อน\'\)/s', "alert('<?= htmlspecialchars(\$terminationReason, ENT_QUOTES, \\'UTF-8\\') ?>')", $content);
    } elseif ($file === 'Tenant/report_contract.php') {
        $content = preg_replace('/<\?php if \(\$unpaidCountForTermination > 0\): \?>/s', "<?php if (!\$terminationAllowed): ?>", $content);
        $content = preg_replace('/alert\(\'ไม่สามารถแจ้งยกเลิกสัญญาได้ เนื่องจากยังมีบิลค้างชำระ <\?php echo \$unpaidCountForTermination; \?> รายการ กรุณาชำระค่าห้องให้ครบก่อน\'\)/s', "alert('<?= htmlspecialchars(\$terminationReason, ENT_QUOTES, \\'UTF-8\\') ?>')", $content);
    } elseif ($file === 'Tenant/termination.php') {
        $content = preg_replace('/\$unpaidCheckStmt = \$pdo->prepare.*?if \(\$unpaidCount > 0\) \{[^\}]+}/s', "if (!\$terminationAllowed) {\n                \$error = \$terminationReason;\n            }", $content);
        $content = preg_replace('/\} else \{\s*\/\/\s*Insert termination request/s', "} else {\n            // Insert termination request", $content);
    }
    file_put_contents($file, $content);
    echo "Processed $file\n";
}
