<?php
/**
 * Git Auto Commit Helper
 * สำหรับ auto commit & push เมื่อมี file upload (เช่น หลักฐานการชำระเงิน)
 */

/**
 * Auto commit & push เมื่อมี image upload
 * @param string $message Commit message
 * @param string $filePath Optional specific file path to add
 * @return bool true if successful, false otherwise
 */
function autoGitCommitOnUpload(string $message = '', string $filePath = ''): bool {
    try {
        $projectRoot = dirname(dirname(__FILE__));
        $gitDir = $projectRoot . '/.git';
        
        // ตรวจสอบว่ามี .git directory
        if (!is_dir($gitDir)) {
            error_log('⚠️ Git helper: No .git directory found');
            return false;
        }
        
        // ถ้ากำหนด specific file มาให้ add เฉพาะไฟล์นั้น มิฉะนั้น add Public/Assets/Images
        $filesToAdd = $filePath ?: 'Public/Assets/Images/Payments/';
        
        // เปลี่ยน cd ไปที่ project root
        $cmd = "cd " . escapeshellarg($projectRoot) . " && ";
        
        // Add files
        $cmd .= "git add " . escapeshellarg($filesToAdd) . " && ";
        
        // Check if there are changes to commit
        $statusCmd = $cmd . "git status --short";
        $output = [];
        $statusCode = 0;
        exec($statusCmd, $output, $statusCode);
        
        if (empty($output) || $statusCode !== 0) {
            error_log('✓ Git helper: No changes to commit');
            return true; // No changes is not an error
        }
        
        // Default message if not provided
        if (empty($message)) {
            $message = '📸 Auto commit: Payment proof image uploaded at ' . date('Y-m-d H:i:s');
        }
        
        // Commit
        $commitCmd = $cmd . "git commit -m " . escapeshellarg($message);
        $commitOutput = [];
        $commitCode = 0;
        exec($commitCmd, $commitOutput, $commitCode);
        
        if ($commitCode !== 0) {
            error_log('❌ Git helper: Commit failed - ' . implode("\n", $commitOutput));
            return false;
        }
        
        error_log('✓ Git helper: Committed - ' . $message);
        
        // Try to push (ไม่ต้อง error หากไม่สำเร็จ)
        $pushCmd = $cmd . "git push 2>&1";
        $pushOutput = [];
        $pushCode = 0;
        exec($pushCmd, $pushOutput, $pushCode);
        
        if ($pushCode === 0) {
            error_log('✓ Git helper: Pushed successfully');
        } else {
            // อาจจะไม่มี internet หรือ auth issue - log ลงแต่ไม่ error
            error_log('⚠️ Git helper: Push failed (might be network issue) - ' . implode("\n", $pushOutput));
            // ยังคง return true เพราะ commit สำเร็จแล้ว
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log('❌ Git helper: Exception - ' . $e->getMessage());
        return false;
    }
}

/**
 * Auto commit for room/building images or other assets
 * @param string $imagePath Path to the image that was uploaded
 * @param string $type Type of image (payment, room, building, etc.)
 * @return bool
 */
function autoGitCommitImageUpload(string $imagePath, string $type = 'image'): bool {
    $typeLabels = [
        'payment' => '💰 Payment proof',
        'room' => '🏠 Room image',
        'building' => '🏢 Building image',
        'room_status' => '📊 Room status',
        'contract' => '📄 Contract',
        'other' => '📎 File'
    ];
    
    $label = $typeLabels[$type] ?? $typeLabels['other'];
    $message = '📸 ' . $label . ' uploaded: ' . basename($imagePath) . ' at ' . date('Y-m-d H:i:s');
    
    return autoGitCommitOnUpload($message, $imagePath);
}
