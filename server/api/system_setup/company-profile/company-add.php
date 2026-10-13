<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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

function normalizeCompanyNameWords($name) {
    $name = strtolower($name);
    $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    $stopwords = ['the', 'and', 'of', 'co', 'company', 'corp', 'corporation', 'inc',
        'incorporated', 'ltd', 'limited', 'pvt', 'private', 'llc', 'llp', 'plc'];
    return array_values(array_diff($words, $stopwords));
}

function companyNameSimilarity($name1, $name2) {
    $words1 = normalizeCompanyNameWords($name1);
    $words2 = normalizeCompanyNameWords($name2);
    $smaller = min(count($words1), count($words2));
    if ($smaller === 0) return 0;
    $common = array_intersect($words1, $words2);
    return count($common) / $smaller;
}

function convertToWebP($sourcePath, $targetPath) {
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

$uploadDir = '../../../../client/assets/uploads/company_logo/';

try {
    // Validate required fields
    if (empty($_POST['company_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Company name is required']);
        exit;
    }

    $companyName = trim($_POST['company_name']);

    // Block exact duplicates (case-insensitive) outright
    $dupStmt = $pdo->prepare("SELECT id FROM companies WHERE tenant_id = ? AND LOWER(TRIM(company_name)) = LOWER(?)");
    $dupStmt->execute([$tenant_id, $companyName]);
    if ($dupStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "A company named \"$companyName\" already exists."]);
        exit;
    }

    // Warn (don't block) on near-duplicate names, e.g. "Z&S Foods" vs "Z & S Meal and Foods (Private) Limited"
    $confirmDuplicate = isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === '1';
    if (!$confirmDuplicate) {
        $existingStmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE tenant_id = ?");
        $existingStmt->execute([$tenant_id]);
        foreach ($existingStmt->fetchAll() as $existing) {
            if (companyNameSimilarity($companyName, $existing['company_name']) >= 0.6) {
                echo json_encode([
                    'success' => false,
                    'duplicate_warning' => true,
                    'message' => "This looks similar to an existing company \"{$existing['company_name']}\". Create it as a separate company anyway?",
                    'similar_company' => $existing
                ]);
                exit;
            }
        }
    }

    $logoFileName = null;
    
    // Handle logo upload
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['logo_file'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'application/pdf', 'image/webp', 'image/avif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type');
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds 5MB limit');
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($fileExtension, ['webp', 'avif', 'pdf'])) {
            // Keep original format for WebP, AVIF, and PDF
            $logoFileName = uniqid('logo_') . '.' . $fileExtension;
            $targetPath = $uploadDir . $logoFileName;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Failed to upload file');
            }
        } else {
            // Convert to WebP for other formats
            $logoFileName = uniqid('logo_') . '.webp';
            $targetPath = $uploadDir . $logoFileName;
            if (!function_exists('imagewebp') || !convertToWebP($file['tmp_name'], $targetPath)) {
                // Fallback: save original format if WebP conversion not supported
                $logoFileName = uniqid('logo_') . '.' . $fileExtension;
                $targetPath = $uploadDir . $logoFileName;
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new Exception('Failed to upload file');
                }
            }
        }
    }
    
    // Generate sequential company code
    $codeStmt = $pdo->prepare("SELECT company_code FROM companies WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $codeStmt->execute([$tenant_id]);
    $lastCompany = $codeStmt->fetch();
    
    if ($lastCompany) {
        $lastNumber = (int)substr($lastCompany['company_code'], 5); // Extract number after 'COMP-'
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $companyCode = 'COMP-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    
    // Insert company
    $sql = "INSERT INTO companies (
        tenant_id, company_code, company_name, legal_name, email, phone, mobile_no, website,
        address, country, state, city, zipcode, po_box_no, industry_type, registration_number,
        tax_identification_number, sales_tax_number, logo_url, language_code,
        timezone, currency_code, inventory_valuation_method, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $tenant_id,
        $companyCode,
        $companyName,
        $_POST['legal_name'] ?? null,
        $_POST['email'] ?? null,
        $_POST['phone'] ?? null,
        $_POST['mobile_no'] ?? null,
        $_POST['website'] ?? null,
        $_POST['address'] ?? null,
        $_POST['country'] ?? null,
        $_POST['state'] ?? null,
        $_POST['city'] ?? null,
        $_POST['zipcode'] ?? null,
        $_POST['po_box_no'] ?? null,
        $_POST['industry_type'] ?? null,
        $_POST['registration_number'] ?? null,
        $_POST['tax_identification_number'] ?? null,
        $_POST['sales_tax_number'] ?? null,
        $logoFileName,
        $_POST['language_code'] ?? 'en',
        $_POST['timezone'] ?? 'UTC',
        $_POST['currency_code'] ?? 'USD',
        $_POST['inventory_valuation_method'] ?? 'FIFO',
        isset($_POST['is_active']) ? ($_POST['is_active'] === 'true' ? 1 : 0) : 1
    ]);
    
    if ($result) {
        $newCompanyId = $pdo->lastInsertId();

        logActivity(
            $pdo, $tenant_id, $user_id, 'create', 'Company', $newCompanyId, $companyCode,
            "Created company \"$companyName\" ($companyCode)",
            null,
            ['company_code' => $companyCode, 'company_name' => $companyName, 'email' => $_POST['email'] ?? null, 'phone' => $_POST['phone'] ?? null]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Company created successfully',
            'company_id' => $newCompanyId
        ]);
    } else {
        throw new Exception('Failed to create company');
    }
    
} catch (Exception $e) {
    // Clean up uploaded file if database insert fails
    if (isset($logoFileName) && $logoFileName && file_exists($uploadDir . $logoFileName)) {
        unlink($uploadDir . $logoFileName);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>