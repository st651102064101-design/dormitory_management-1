<?php
require 'ConnectDB.php';
$conn = connectDB();
$completedFilter = 0;
$bookingFilterCondition = '';
$selectedBkgId = 0;

$firstBillPaidCondition = "
        EXISTS (
                SELECT 1
                FROM expense e_first
                WHERE e_first.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                    AND (
                        c.ctr_start IS NULL
                        OR DATE_FORMAT(e_first.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                    )
                    AND e_first.exp_month = (
                        SELECT MIN(e_min.exp_month)
                        FROM expense e_min
                        WHERE e_min.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                            AND (
                                c.ctr_start IS NULL
                                OR DATE_FORMAT(e_min.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                            )
                    )
                    AND e_first.exp_total > 0
                    AND COALESCE((
                        SELECT SUM(p.pay_amount)
                        FROM payment p
                        WHERE p.exp_id = e_first.exp_id
                          AND p.pay_status = '1'
                          AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                    ), 0) >= e_first.exp_total - 0.00001
        )
";

$meterRecordedCondition = "
EXISTS (
        SELECT 1
        FROM utility u
        WHERE u.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
        AND u.utl_water_end IS NOT NULL
        AND u.utl_elec_end IS NOT NULL
)
";

$latestBillPaidCondition = "
EXISTS (
        SELECT 1
        FROM expense e_latest
        WHERE e_latest.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
            AND (
                c.ctr_start IS NULL
                OR DATE_FORMAT(e_latest.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
            )
            AND e_latest.exp_month = (
                SELECT MAX(e_max.exp_month)
                FROM expense e_max
                WHERE e_max.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                    AND (
                        c.ctr_start IS NULL
                        OR DATE_FORMAT(e_max.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                    )
            )
            AND e_latest.exp_total > 0
            AND COALESCE((
                SELECT SUM(p.pay_amount)
                FROM payment p
                WHERE p.exp_id = e_latest.exp_id
                  AND p.pay_status = '1'
                  AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
            ), 0) >= e_latest.exp_total - 0.00001
)
";

$allStepsDoneCondition = "
c.ctr_status = '0'
AND cr.checkin_date IS NOT NULL
AND cr.checkin_date <> '0000-00-00'
AND $firstBillPaidCondition
AND $latestBillPaidCondition
AND $meterRecordedCondition
";

$completionCondition = " AND NOT ($allStepsDoneCondition) ";

$sql = "
SELECT
    b.bkg_id,
    r.room_id,
    r.room_number,
    tw.current_step,
    b.bkg_date
FROM booking b
INNER JOIN tenant t ON b.tnt_id = t.tnt_id
LEFT JOIN (
    SELECT tw1.* FROM tenant_workflow tw1 INNER JOIN (SELECT bkg_id, MAX(id) AS latest_workflow_id FROM tenant_workflow GROUP BY bkg_id) tw2 ON tw1.id = tw2.latest_workflow_id
) tw ON b.bkg_id = tw.bkg_id
LEFT JOIN room r ON b.room_id = r.room_id
LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
LEFT JOIN (
    SELECT cr1.*
    FROM checkin_record cr1
    INNER JOIN (
        SELECT ctr_id, MAX(checkin_id) AS latest_checkin_id
        FROM checkin_record
        GROUP BY ctr_id
    ) cr2 ON cr1.checkin_id = cr2.latest_checkin_id
) cr ON c.ctr_id = cr.ctr_id
WHERE b.bkg_status != '0' 
  AND COALESCE(b.bkg_status, '') <> '5'
  AND (c.ctr_id IS NULL OR c.ctr_status <> '1')
  AND NOT EXISTS (
      SELECT 1 FROM contract c3
      LEFT JOIN termination t3 ON c3.ctr_id = t3.ctr_id
      WHERE c3.room_id = b.room_id
        AND (
            (c3.ctr_status = '0' AND (c3.ctr_end IS NULL OR c3.ctr_end >= CURDATE()))
            OR (c3.ctr_status = '2' AND (t3.term_date IS NULL OR t3.term_date >= CURDATE()))
        )
        AND COALESCE(c3.tnt_id, '') <> COALESCE(b.tnt_id, '')
  )
" . $completionCondition . "
ORDER BY CAST(r.room_number AS UNSIGNED) ASC";

$stmt = $conn->query($sql);
$wizardTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deduped = [];
foreach ($wizardTenants as $t) {
    if (!isset($t['bkg_id'])) continue;
    $roomKey = isset($t['room_id']) && $t['room_id'] !== null ? 'r' . (int)$t['room_id'] : 'b' . (int)($t['bkg_id'] ?? 0);
    if (!isset($deduped[$roomKey])) {
        $deduped[$roomKey] = $t;
        continue;
    }
    $cur = $deduped[$roomKey];
    $curBkgId = (int)($cur['bkg_id'] ?? 0);
    $newBkgId = (int)($t['bkg_id'] ?? 0);
    if ($newBkgId > $curBkgId) {
        $deduped[$roomKey] = $t;
    }
}
echo "Total deduplicated matching the wizard logic exactly: " . count($deduped) . PHP_EOL;
