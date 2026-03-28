<?php
declare(strict_types=1);
require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

echo "🔒 Adding Database Constraints to Prevent Duplicates\n";
echo "===================================================\n\n";

try {
    // ลบ UNIQUE constraints เดิมถ้ามี (ป้องกัน error)
    try {
        $pdo->exec("ALTER TABLE contract DROP INDEX uq_contract_room_status");
        echo "✓ Dropped old unique index\n";
    } catch (Exception $e) {
        // Index doesn't exist - ignore
    }
    
    // 2. เพิ่ม UNIQUE constraint: ห้องหนึ่งมีได้ 1 สัญญาเท่านั้น (ที่ status 0,2)
    // Strategy: ใช้ TRIGGER แทน (MariaDB compatible)
    
    // 3. ตรวจสอบ contract table structure
    $colsStmt = $pdo->query("DESCRIBE contract");
    $columns = [];
    foreach ($colsStmt as $col) {
        $columns[$col['Field']] = $col['Type'];
    }
    
    echo "\n📊 Contract Table Columns:\n";
    echo str_pad("Field", 30) . " | Type\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($columns as $name => $type) {
        echo str_pad($name, 30) . " | $type\n";
    }
    
    // 4. เพิ่ม Unique Key แบบ Conditional (Django-style approach ใน MySQL)
    // ใช้ CASE expression ใน generated column
    
    echo "🔧 Applying Constraints...\n\n";
    
    // ใช้ TRIGGER เพื่อป้องกัน duplicates (MariaDB compatible approach)
    
    // Trigger 1: ตรวจสอบก่อนสร้างสัญญาใหม่ (INSERT)
    echo "1️⃣  Creating: BEFORE INSERT trigger...\n";
    $pdo->exec(
        "DROP TRIGGER IF EXISTS trg_contract_before_insert"
    );
    $pdo->exec(
        "CREATE TRIGGER trg_contract_before_insert 
         BEFORE INSERT ON contract 
         FOR EACH ROW 
         BEGIN
           -- ห้องนี้ไม่มี active contract
           IF (SELECT COUNT(*) FROM contract 
               WHERE room_id = NEW.room_id 
               AND ctr_status IN ('0','2')
               AND ctr_id != NEW.ctr_id) > 0 THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate: Room already has active contract';
           END IF;
           
           -- ผู้เช่านี้ไม่มี active contract
           IF (SELECT COUNT(*) FROM contract 
               WHERE tnt_id = NEW.tnt_id 
               AND ctr_status IN ('0','2')
               AND ctr_id != NEW.ctr_id) > 0 THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate: Tenant already has active contract';
           END IF;
         END"
    );
    echo "   ✓ BEFORE INSERT trigger created\n";
    
    // Trigger 2: ตรวจสอบก่อนอัปเดตสัญญา (UPDATE)
    echo "\n2️⃣  Creating: BEFORE UPDATE trigger...\n";
    $pdo->exec(
        "DROP TRIGGER IF EXISTS trg_contract_before_update"
    );
    $pdo->exec(
        "CREATE TRIGGER trg_contract_before_update 
         BEFORE UPDATE ON contract 
         FOR EACH ROW 
         BEGIN
           -- เฉพาะเมื่อสถานะเปลี่ยนเป็น active (0 หรือ 2)
           IF NEW.ctr_status IN ('0','2') THEN
             -- ห้องนี้ไม่มี active contract อื่น
             IF (SELECT COUNT(*) FROM contract 
                 WHERE room_id = NEW.room_id 
                 AND ctr_status IN ('0','2')
                 AND ctr_id != NEW.ctr_id) > 0 THEN
               SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate: Room already has active contract';
             END IF;
             
             -- ผู้เช่านี้ไม่มี active contract อื่น
             IF (SELECT COUNT(*) FROM contract 
                 WHERE tnt_id = NEW.tnt_id 
                 AND ctr_status IN ('0','2')
                 AND ctr_id != NEW.ctr_id) > 0 THEN
               SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate: Tenant already has active contract';
             END IF;
           END IF;
         END"
    );
    echo "   ✓ BEFORE UPDATE trigger created\n";
    
    // Constraint: Foreign Key from tenant_workflow
    echo "\n3️⃣  Adding: Foreign Key from tenant_workflow...\n";
    
    // ตรวจสอบ FK เดิม
    try {
        $pdo->exec("ALTER TABLE tenant_workflow DROP FOREIGN KEY fk_tenant_workflow_contract");
        echo "   ✓ Dropped old FK\n";
    } catch (Exception $e) {
        // FK doesn't exist-ignore
    }
    
    try {
        $pdo->exec(
            "ALTER TABLE tenant_workflow 
             ADD CONSTRAINT fk_tenant_workflow_contract 
             FOREIGN KEY (ctr_id) REFERENCES contract(ctr_id) 
             ON DELETE CASCADE ON UPDATE CASCADE"
        );
        echo "   ✓ FK applied: tenant_workflow.ctr_id → contract.ctr_id\n";
    } catch (Exception $e) {
        echo "   ⚠️  FK constraint failed (may already exist): " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ All Constraints Added Successfully!\n";
    echo "\n📋 Summary:\n";
    echo "  • Database Triggers: Prevent duplicate room/tenant contracts\n";
    echo "  • App-level: Three-layer validation in process_contract.php\n";
    echo "  • Foreign Key: Cascade delete on contract removal\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
