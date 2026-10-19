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
    $words = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
    $stopwords = ['the', 'and', 'of', 'co', 'company', 'corp', 'corporation', 'inc',
        'incorporated', 'ltd', 'limited', 'pvt', 'private', 'llc', 'llp', 'plc'];
    return array_values(array_diff($words, $stopwords));
}

function companyNameSimilarity($a, $b) {
    $w1 = normalizeCompanyNameWords($a);
    $w2 = normalizeCompanyNameWords($b);
    $smaller = min(count($w1), count($w2));
    if ($smaller === 0) return 0;
    return count(array_intersect($w1, $w2)) / $smaller;
}

function convertToWebP($sourcePath, $targetPath) {
    $info = getimagesize($sourcePath);
    if (!$info) return false;
    switch ($info['mime']) {
        case 'image/jpeg': $image = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $image = imagecreatefrompng($sourcePath); break;
        case 'image/gif':  $image = imagecreatefromgif($sourcePath); break;
        default: return false;
    }
    if ($image) {
        $ok = imagewebp($image, $targetPath, 80);
        imagedestroy($image);
        return $ok;
    }
    return false;
}

$uploadDir = '../../../../client/assets/uploads/company_logo/';
$allowedBT = ['restaurant', 'bakery', 'karyana', 'general', 'medical', 'mall'];

try {
    if (empty($_POST['company_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Company name is required']);
        exit;
    }
    $companyName = trim($_POST['company_name']);
    $businessType = in_array($_POST['business_type'] ?? '', $allowedBT, true) ? $_POST['business_type'] : 'general';

    $dup = $pdo->prepare("SELECT id FROM companies WHERE tenant_id = ? AND LOWER(TRIM(company_name)) = LOWER(?)");
    $dup->execute([$tenant_id, $companyName]);
    if ($dup->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "A company named \"$companyName\" already exists."]);
        exit;
    }

    if (!(isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === '1')) {
        $ex = $pdo->prepare("SELECT id, company_name FROM companies WHERE tenant_id = ?");
        $ex->execute([$tenant_id]);
        foreach ($ex->fetchAll() as $existing) {
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
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $file = $_FILES['logo_file'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'application/pdf', 'image/webp', 'image/avif'];
        if (!in_array($file['type'], $allowedTypes)) throw new Exception('Invalid file type');
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception('File size exceeds 5MB limit');

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['webp', 'avif', 'pdf'])) {
            $logoFileName = uniqid('logo_') . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $logoFileName)) throw new Exception('Failed to upload file');
        } else {
            $logoFileName = uniqid('logo_') . '.webp';
            if (!function_exists('imagewebp') || !convertToWebP($file['tmp_name'], $uploadDir . $logoFileName)) {
                $logoFileName = uniqid('logo_') . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $logoFileName)) throw new Exception('Failed to upload file');
            }
        }
    }

    $codeStmt = $pdo->prepare("SELECT company_code FROM companies WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $codeStmt->execute([$tenant_id]);
    $last = $codeStmt->fetch();
    $newNumber = $last ? ((int) substr($last['company_code'], 5)) + 1 : 1;
    $companyCode = 'COMP-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

    $sql = "INSERT INTO companies (
        tenant_id, company_code, company_name, legal_name, email, phone, mobile_no, website,
        address, country, state, city, zipcode, po_box_no, industry_type, business_type, registration_number,
        tax_identification_number, sales_tax_number, logo_url, language_code,
        timezone, currency_code, inventory_valuation_method, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
        $businessType,
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
            ['company_code' => $companyCode, 'company_name' => $companyName, 'business_type' => $businessType,
             'email' => $_POST['email'] ?? null, 'phone' => $_POST['phone'] ?? null]
        );
        echo json_encode(['success' => true, 'message' => 'Company created successfully', 'company_id' => $newCompanyId]);
    } else {
        throw new Exception('Failed to create company');
    }
} catch (Exception $e) {
    if (isset($logoFileName) && $logoFileName && file_exists($uploadDir . $logoFileName)) {
        unlink($uploadDir . $logoFileName);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>