<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "<h2>Contracts:</h2>";
$stmt = $pdo->query("SELECT c.ctr_id, c.room_id, c.ctr_status, r.room_number, t.tnt_name 
                     FROM contract c 
                     JOIN room r ON c.room_id = r.room_id 
                     JOIN tenant t ON c.tnt_id = t.tnt_id 
                     ORDER BY c.ctr_id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($rows);
echo "</pre>";

echo "<h2>Active Contracts (status 0,1,2):</h2>";
$stmt2 = $pdo->query("SELECT c.ctr_id, c.room_id, c.ctr_status, r.room_number, t.tnt_name 
                      FROM contract c 
                      JOIN room r ON c.room_id = r.room_id 
                      JOIN tenant t ON c.tnt_id = t.tnt_id 
                      WHERE c.ctr_status IN ('0','1','2')
                      ORDER BY c.ctr_id");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($rows2);
echo "</pre>";
