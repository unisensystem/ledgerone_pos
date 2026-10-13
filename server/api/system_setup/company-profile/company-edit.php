<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
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
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get company by ID
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Company ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$company) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Company not found']);
            exit;
        }
        
        echo json_encode(['success' => true, 'company' => $company]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        // Update company with file upload support
        $id = $_POST['id'] ?? null;
        
        if (!$id || empty($_POST['company_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Company ID and name are required']);
            exit;
        }
        
        $logoFileName = null;
        
        // Handle logo upload if provided
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
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
            
            // Get current logo to delete old file
            $currentStmt = $pdo->prepare("SELECT logo_url FROM companies WHERE id = ? AND tenant_id = ?");
            $currentStmt->execute([$id, $tenant_id]);
            $currentLogo = $currentStmt->fetch();
            
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($fileExtension, ['webp', 'avif', 'pdf'])) {
                $logoFileName = uniqid('logo_') . '.' . $fileExtension;
                $targetPath = $uploadDir . $logoFileName;
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new Exception('Failed to upload file');
                }
            } else {
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
            
            // Delete old logo file
            if ($currentLogo && $currentLogo['logo_url'] && file_exists($uploadDir . $currentLogo['logo_url'])) {
                unlink($uploadDir . $currentLogo['logo_url']);
            }
        }
        
        // Snapshot old values for the Activity Log (before mutation)
        $oldStmt = $pdo->prepare("SELECT company_code, company_name, email, phone, mobile_no, website, address, city, is_active FROM companies WHERE id = ? AND tenant_id = ?");
        $oldStmt->execute([$id, $tenant_id]);
        $oldCompany = $oldStmt->fetch(PDO::FETCH_ASSOC);

        // Build SQL based on whether logo is being updated
        if ($logoFileName) {
            $sql = "UPDATE companies SET 
                    company_name = ?, legal_name = ?, email = ?, phone = ?, mobile_no = ?, website = ?,
                    address = ?, country_id = ?, country = ?, state = ?, city = ?, zipcode = ?, po_box_no = ?,
                    industry_type = ?, registration_number = ?, tax_identification_number = ?,
                    sales_tax_number = ?, logo_url = ?, language_code = ?, timezone = ?,
                    currency_code = ?, inventory_valuation_method = ?, is_active = ?
                    WHERE id = ? AND tenant_id = ?";
            
            $params = [
                $_POST['company_name'],
                $_POST['legal_name'] ?? null,
                $_POST['email'] ?? null,
                $_POST['phone'] ?? null,
                $_POST['mobile_no'] ?? null,
                $_POST['website'] ?? null,
                $_POST['address'] ?? null,
                !empty($_POST['country_id']) ? (int)$_POST['country_id'] : null,
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
                isset($_POST['is_active']) ? ($_POST['is_active'] === 'true' ? 1 : 0) : 1,
                $id,
                $tenant_id
            ];
        } else {
            $sql = "UPDATE companies SET 
                    company_name = ?, legal_name = ?, email = ?, phone = ?, mobile_no = ?, website = ?,
                    address = ?, country_id = ?, country = ?, state = ?, city = ?, zipcode = ?, po_box_no = ?,
                    industry_type = ?, registration_number = ?, tax_identification_number = ?,
                    sales_tax_number = ?, language_code = ?, timezone = ?,
                    currency_code = ?, inventory_valuation_method = ?, is_active = ?
                    WHERE id = ? AND tenant_id = ?";
            
            $params = [
                $_POST['company_name'],
                $_POST['legal_name'] ?? null,
                $_POST['email'] ?? null,
                $_POST['phone'] ?? null,
                $_POST['mobile_no'] ?? null,
                $_POST['website'] ?? null,
                $_POST['address'] ?? null,
                !empty($_POST['country_id']) ? (int)$_POST['country_id'] : null,
                $_POST['country'] ?? null,
                $_POST['state'] ?? null,
                $_POST['city'] ?? null,
                $_POST['zipcode'] ?? null,
                $_POST['po_box_no'] ?? null,
                $_POST['industry_type'] ?? null,
                $_POST['registration_number'] ?? null,
                $_POST['tax_identification_number'] ?? null,
                $_POST['sales_tax_number'] ?? null,
                $_POST['language_code'] ?? 'en',
                $_POST['timezone'] ?? 'UTC',
                $_POST['currency_code'] ?? 'USD',
                $_POST['inventory_valuation_method'] ?? 'FIFO',
                isset($_POST['is_active']) ? ($_POST['is_active'] === 'true' ? 1 : 0) : 1,
                $id,
                $tenant_id
            ];
        }
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            if ($oldCompany) {
                $newCompany = [
                    'company_code' => $oldCompany['company_code'], 'company_name' => $_POST['company_name'],
                    'email' => $_POST['email'] ?? null, 'phone' => $_POST['phone'] ?? null, 'mobile_no' => $_POST['mobile_no'] ?? null,
                    'website' => $_POST['website'] ?? null, 'address' => $_POST['address'] ?? null, 'city' => $_POST['city'] ?? null,
                    'is_active' => isset($_POST['is_active']) ? ($_POST['is_active'] === 'true' ? 1 : 0) : 1
                ];
                $changeDescriptions = buildChangeDescription($oldCompany, $newCompany, [
                    'company_name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'mobile_no' => 'Mobile',
                    'website' => 'Website', 'address' => 'Address', 'city' => 'City', 'is_active' => 'Active'
                ]);
                if ($logoFileName) $changeDescriptions[] = 'Logo updated';
                $editDescription = !empty($changeDescriptions)
                    ? "Updated company \"{$_POST['company_name']}\" ({$oldCompany['company_code']}): " . implode('; ', $changeDescriptions)
                    : "Updated company \"{$_POST['company_name']}\" ({$oldCompany['company_code']}) - no tracked field changes";
                logActivity($pdo, $tenant_id, $user_id, 'update', 'Company', $id, $oldCompany['company_code'], $editDescription, $oldCompany, $newCompany);
            }

            echo json_encode(['success' => true, 'message' => 'Company updated successfully']);
        } else {
            throw new Exception('Failed to update company');
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    // Clean up uploaded file if update fails
    if (isset($logoFileName) && $logoFileName && file_exists($uploadDir . $logoFileName)) {
        unlink($uploadDir . $logoFileName);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>