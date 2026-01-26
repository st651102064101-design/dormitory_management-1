<?php
/**
 * Test upload functionality
 */
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');

// Set admin session for testing
if (empty($_SESSION['admin_username'])) {
    $_SESSION['admin_username'] = 'admin01';
}

echo "<h2>Upload Test</h2>\n";

// Create test image
$test_image = '/tmp/test_room_image.jpg';
$img = imagecreatetruecolor(200, 150);
$color = imagecolorallocate($img, 100, 150, 200);
imagefill($img, 0, 0, $color);
imagejpeg($img, $test_image, 90);
imagedestroy($img);

echo "✓ Test image created: " . $test_image . "\n";
echo "  Size: " . filesize($test_image) . " bytes\n\n";

// Simulate file upload
$_FILES['room_image'] = [
    'name' => 'test_room.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $test_image,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($test_image)
];
$_POST['room_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Include the upload handler
require_once __DIR__ . '/Manage/upload_room_image.php';
?>
