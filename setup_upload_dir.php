<?php
/**
 * Setup upload directory for room images
 */

$upload_dir = __DIR__ . '/Public/Assets/Images/Rooms';

echo "Checking upload directory...\n";
echo "Path: " . $upload_dir . "\n\n";

// Create parent directories if needed
$parent_dir = dirname($upload_dir);
if (!is_dir($parent_dir)) {
    echo "Creating parent directory...\n";
    mkdir($parent_dir, 0777, true);
}

// Create or fix upload directory
if (!is_dir($upload_dir)) {
    echo "Creating upload directory...\n";
    mkdir($upload_dir, 0777, true);
    echo "✓ Directory created\n";
} else {
    echo "✓ Directory exists\n";
}

// Fix permissions
chmod($upload_dir, 0777);
echo "✓ Permissions set to 777\n";

// Verify
if (is_dir($upload_dir) && is_writable($upload_dir)) {
    echo "\n✅ Upload directory is ready!\n";
    echo "Status: Writable\n";
    
    // Show some stats
    $perms = substr(sprintf('%o', fileperms($upload_dir)), -4);
    echo "Permissions: " . $perms . "\n";
    
    // Count existing images
    $images = glob($upload_dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    echo "Existing images: " . count($images) . "\n";
} else {
    echo "\n❌ Problem with upload directory!\n";
    echo "Is directory: " . (is_dir($upload_dir) ? 'Yes' : 'No') . "\n";
    echo "Is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "\n";
}
?>
