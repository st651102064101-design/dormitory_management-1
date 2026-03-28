<?php
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

echo "🔧 Updating Database Triggers - Allow UPDATE\n";
echo "=============================================\n\n";

try {
    // Trigger 1: ตรวจสอบก่อนสร้างสัญญาใหม่ (INSERT) - BLOCK if duplicate
    echo "1️⃣  Dropping old INSERT trigger...\n";
    $pdo->exec("DROP TRIGGER IF EXISTS trg_contract_before_insert");
    
    echo "2️⃣  Creating new INSERT trigger (block duplicates)...\n";
    $pdo->exec(
        "CREATE TRIGGER trg_contract_before_insert 
         BEFORE INSERT ON contract 
         FOR EACH ROW 
         BEGIN
           -- ห้องนี้ไม่มี active contract อื่น
           IF (SELECT COUNT(*) FROM contract 
               WHERE room_id = NEW.room_id 
               AND ctr_status IN ('0','2')) > 0 THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate: Room already has active contract';
           END IF;
           
           -- ผู้เช่านี้ไม่มี active contract อื่น
           IF (SELECT COUNT(*) FROM contract 
               WHERE tnt_id = NEW.tnt_id 
               AND ctr_status IN ('0','2')) > 0 THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate: Tenant already has active contract';
           END IF;
         END"
    );
    echo "   ✓ INSERT trigger: Blocks new contracts if duplicate\n";
    
    // Trigger 2: Allow UPDATE - don't check on UPDATE
    echo "\n3️⃣  Dropping old UPDATE trigger...\n";
    $pdo->exec("DROP TRIGGER IF EXISTS trg_contract_before_update");
    
    echo "4️⃣  Creating new UPDATE trigger (allow updates, set status to active)...\n";
    $pdo->exec(
        "CREATE TRIGGER trg_contract_before_update 
         BEFORE UPDATE ON contract 
         FOR EACH ROW 
         BEGIN
           -- Allow all updates - no blocking on UPDATE
           -- When status changes to active (0 or 2), that's fine
         END"
    );
    echo "   ✓ UPDATE trigger: Allows all updates\n";
    
    echo "\n✅ Triggers Updated Successfully!\n";
    echo "\n📋 Summary:\n";
    echo "  • INSERT: Blocks if room has active contract\n";
    echo "  • INSERT: Blocks if tenant has active contract\n";
    echo "  • UPDATE: Allowed (app logic handles validation)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
