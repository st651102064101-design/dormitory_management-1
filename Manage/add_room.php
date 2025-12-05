<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$room_number = str_pad(trim($_POST['หมายเลขห้อง'] ?? ''), 2, '0', STR_PAD_LEFT);
$type_id = (int)($_POST['ประเภท'] ?? 0);
$room_status = (int)($_POST['สถานะ'] ?? 0);

if (empty($room_number) || $type_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Handle file upload: validate, rename, store filename only
$room_image = null;
if (isset($_FILES['รูปภาพ']) && $_FILES['รูปภาพ']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../Assets/Images/Rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Basic validation: file size (max 5MB) and image MIME
    $maxBytes = 5 * 1024 * 1024;
    if ($_FILES['รูปภาพ']['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['error' => 'ขนาดไฟล์ใหญ่เกินไป (สูงสุด 5MB)']);
        exit;
    }

    // Determine MIME type with graceful fallbacks
    $mime = null;
    if (class_exists('finfo')) {
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['รูปภาพ']['tmp_name']);
        } catch (Throwable $e) {
            $mime = null;
        }
    }
    if ($mime === null && function_exists('mime_content_type')) {
        $mime = mime_content_type($_FILES['รูปภาพ']['tmp_name']);
    }
    if ($mime === null) {
        // Last resort, trust the browser-provided type (less secure)
        $mime = $_FILES['รูปภาพ']['type'] ?? '';
    }
    if (strpos($mime, 'image/') !== 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ไฟล์ไม่ใช่รูปภาพ']);
        exit;
    }

    // Determine extension and build safe filename: room_<room>_<ts>_<rand>.<ext>
    $ext = strtolower(pathinfo($_FILES['รูปภาพ']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed, true)) {
        // try to infer ext from mime
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $ext = $map[$mime] ?? $ext;
        if (!in_array($ext, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'นามสกุลไฟล์ไม่รองรับ']);
            exit;
        }
    }

    $timestamp = time();
    try {
        $rand = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $rand = substr(uniqid('', true), -12);
    }
    $safeRoom = preg_replace('/[^0-9A-Za-z_-]/', '', $room_number ?: 'room');
    $fileName = sprintf('room_%s_%s_%s.%s', $safeRoom, $timestamp, $rand, $ext);
    $uploadFile = $uploadDir . $fileName;

    // Attempt to move uploaded file; if it fails, provide diagnostics to help debug
    $tmpName = $_FILES['รูปภาพ']['tmp_name'];
    $moveOk = false;
    if (is_uploaded_file($tmpName)) {
        if (is_writable($uploadDir) || (!file_exists($uploadDir) && is_writable(dirname($uploadDir)))) {
            $moveOk = @move_uploaded_file($tmpName, $uploadFile);
        } else {
            $moveOk = false;
        }
    }
    if (!$moveOk) {
        // Gather diagnostics (avoid leaking sensitive paths in production)
        $tmpExists = file_exists($tmpName) ? 'yes' : 'no';
        $isUploaded = is_uploaded_file($tmpName) ? 'yes' : 'no';
        $dirWritable = is_writable($uploadDir) ? 'yes' : 'no';
        $uploadTmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $maxUpload = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        http_response_code(500);
        echo json_encode([
            'error' => 'ไม่สามารถอัปโหลดรูปภาพได้',
            'details' => [
                'tmp_exists' => $tmpExists,
                'is_uploaded_file' => $isUploaded,
                'upload_tmp_dir' => $uploadTmpDir,
                'upload_max_filesize' => $maxUpload,
                'post_max_size' => $postMax,
                'target_dir_writable' => $dirWritable
            ]
        ]);
        exit;
    }

    // Optional: set permissions
    @chmod($uploadFile, 0644);

    // Store only the filename in DB; display code expects filename only
    $room_image = $fileName;
}

// Check if room_number already exists
// $stmt = $pdo->prepare("SELECT COUNT(*) FROM room WHERE room_number = ?");
// $stmt->execute([$room_number]);
// if ($stmt->fetchColumn() > 0) {
//     http_response_code(400);
//     echo json_encode(['error' => 'หมายเลขห้องนี้มีอยู่แล้ว']);
//     exit;
// }

try {
    $stmt = $pdo->prepare("INSERT INTO room (room_number, type_id, room_status, room_image) VALUES (?, ?, ?, ?)");
    $stmt->execute([$room_number, $type_id, $room_status, $room_image]);
    echo json_encode(['success' => true, 'message' => 'เพิ่มห้องพักเรียบร้อยแล้ว']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>