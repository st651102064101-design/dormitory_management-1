<?php
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

try {
    echo "Confirmed payments (pay_status = '1'):\n";
    $stmt = $pdo->query("SELECT pay_id, pay_date, pay_amount, pay_proof, pay_status, exp_id FROM payment WHERE pay_status = '1' ORDER BY pay_date DESC, pay_id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sum = 0;
    foreach ($rows as $r) {
        printf("%4s | %10s | %8s | %s\n", $r['pay_id'], $r['pay_date'], $r['pay_amount'], $r['pay_proof']);
        $sum += (int)$r['pay_amount'];
    }
    echo "\nTotal confirmed amount: " . number_format($sum) . "\n\n";

    echo "Confirmed payments grouped by month:\n";
    $gstmt = $pdo->query("SELECT DATE_FORMAT(pay_date, '%Y-%m') AS ym, SUM(pay_amount) AS total FROM payment WHERE pay_status = '1' GROUP BY ym ORDER BY ym DESC");
    $groups = $gstmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($groups as $g) {
        echo $g['ym'] . " -> " . number_format((int)$g['total']) . "\n";
    }
} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}

?>