<?php
// Test file to check contracts data
require_once 'ConnectDB.php';
$conn = connectDB();

echo "<h2>Test Contracts Query</h2>";

try {
    $stmt = $conn->prepare("SELECT c.*, 
      t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_status,
      r.room_number, r.room_status,
      rt.type_name
      FROM contract c
      LEFT JOIN tenant t ON t.tnt_id = c.tnt_id
      LEFT JOIN room r ON c.room_id = r.room_id
      LEFT JOIN roomtype rt ON r.type_id = rt.type_id
      ORDER BY c.ctr_start DESC");
    $stmt->execute();
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found <strong>" . count($contracts) . "</strong> contracts</p>";
    
    if (count($contracts) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>ctr_id</th><th>tnt_name</th><th>room_number</th><th>ctr_start</th><th>ctr_end</th><th>ctr_status</th></tr>";
        
        foreach($contracts as $contract) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($contract['ctr_id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($contract['tnt_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($contract['room_number'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($contract['ctr_start'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($contract['ctr_end'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($contract['ctr_status'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No contracts found in database!</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
