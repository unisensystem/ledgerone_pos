<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function convertToWebp($sourcePath, $targetPath) {
    $imageInfo = getimagesize($sourcePath);
    $mimeType = $imageInfo['mime'];
    
    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if ($image) {
        $result = imagewebp($image, $targetPath, 80);
        imagedestroy($image);
        return $result;
    }
    return false;
}

$profilePicture = null;

// Handle file upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/avif', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
        exit;
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    $uploadDir = '../../../assets/uploads/profile_picture/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = uniqid() . '_' . time();
    
    if ($file['type'] === 'image/webp') {
        $profilePicture = $fileName . '.webp';
        move_uploaded_file($file['tmp_name'], $uploadDir . $profilePicture);
    } else {
        $profilePicture = $fileName . '.webp';
        $targetPath = $uploadDir . $profilePicture;
        
        if (!convertToWebp($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to process image']);
            exit;
        }
    }
}

try {
    $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
    
    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, full_name, email, password_hash, phone, language_code, timezone, profile_picture, employee_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([
        $tenant_id,
        $_POST['full_name'],
        $_POST['email'],
        password_hash($_POST['password'], PASSWORD_DEFAULT),
        $_POST['phone'] ?? null,
        $_POST['language_code'] ?? 'en',
        $_POST['timezone'] ?? 'Asia/Karachi',
        $profilePicture,
        $employee_id
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $user_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}