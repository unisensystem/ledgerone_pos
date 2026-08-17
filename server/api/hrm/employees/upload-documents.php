<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $documentType = $_POST['document_type'] ?? '';
    $allowedTypes = ['profile_picture', 'employment_agreement', 'id_proof', 'resume', 'certificates', 'medical_certificate'];
    
    if (!in_array($documentType, $allowedTypes)) {
        throw new Exception('Invalid document type');
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    $file = $_FILES['file'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 10MB limit');
    }
    
    $allowedFormats = ['png', 'jpg', 'jpeg', 'gif', 'avif', 'webp', 'pdf'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedFormats)) {
        throw new Exception('Invalid file format. Allowed: ' . implode(', ', $allowedFormats));
    }
    
    // Create upload directory
    $uploadDir = "../../../../client/assets/uploads/employees/{$documentType}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileName = uniqid() . '_' . time();
    $finalExt = ($fileExt === 'pdf') ? 'pdf' : 'webp';
    $finalFileName = $fileName . '.' . $finalExt;
    $filePath = $uploadDir . $finalFileName;
    
    // Convert to WebP if not PDF
    if ($fileExt === 'pdf') {
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save PDF file');
        }
    } else {
        // Convert image to WebP
        $image = null;
        switch ($fileExt) {
            case 'png':
                $image = imagecreatefrompng($file['tmp_name']);
                break;
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'gif':
                $image = imagecreatefromgif($file['tmp_name']);
                break;
            case 'webp':
                $image = imagecreatefromwebp($file['tmp_name']);
                break;
            case 'avif':
                if (function_exists('imagecreatefromavif')) {
                    $image = imagecreatefromavif($file['tmp_name']);
                } else {
                    throw new Exception('AVIF format not supported');
                }
                break;
        }
        
        if (!$image) {
            throw new Exception('Failed to process image');
        }
        
        if (!imagewebp($image, $filePath, 80)) {
            throw new Exception('Failed to convert to WebP');
        }
        
        imagedestroy($image);
    }
    
    echo json_encode([
        'success' => true,
        'filename' => $finalFileName,
        'path' => "../../../assets/uploads/employees/{$documentType}/{$finalFileName}"
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}