<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ---------- GET: load one company ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Company not found']);
        exit;
    }
    echo json_encode(['success' => true, 'company' => $company]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ---------- POST: update ----------
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
$newLogo = null;

try {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Company not found']);
        exit;
    }

    $companyName = trim($_POST['company_name'] ?? '');
    if ($companyName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Company name is required']);
        exit;
    }
    $businessType = in_array($_POST['business_type'] ?? '', $allowedBT, true) ? $_POST['business_type'] : 'general';

    // exact duplicate (other companies only)
    $dup = $pdo->prepare("SELECT id FROM companies WHERE tenant_id = ? AND id != ? AND LOWER(TRIM(company_name)) = LOWER(?)");
    $dup->execute([$tenant_id, $id, $companyName]);
    if ($dup->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "A company named \"$companyName\" already exists."]);
        exit;
    }

    // near-duplicate warning only when the name actually changed
    $nameChanged = strtolower(trim($old['company_name'])) !== strtolower($companyName);
    if ($nameChanged && !(isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === '1')) {
        $ex = $pdo->prepare("SELECT id, company_name FROM companies WHERE tenant_id = ? AND id != ?");
        $ex->execute([$tenant_id, $id]);
        foreach ($ex->fetchAll() as $existing) {
            if (companyNameSimilarity($companyName, $existing['company_name']) >= 0.6) {
                echo json_encode([
                    'success' => false,
                    'duplicate_warning' => true,
                    'message' => "This looks similar to an existing company \"{$existing['company_name']}\". Save it with this name anyway?",
                    'similar_company' => $existing
                ]);
                exit;
            }
        }
    }

    // logo handling
    $logoFileName = $old['logo_url'];
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $file = $_FILES['logo_file'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'application/pdf', 'image/webp', 'image/avif'];
        if (!in_array($file['type'], $allowedTypes)) throw new Exception('Invalid file type');
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception('File size exceeds 5MB limit');

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['webp', 'avif', 'pdf'])) {
            $newLogo = uniqid('logo_') . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newLogo)) throw new Exception('Failed to upload file');
        } else {
            $newLogo = uniqid('logo_') . '.webp';
            if (!function_exists('imagewebp') || !convertToWebP($file['tmp_name'], $uploadDir . $newLogo)) {
                $newLogo = uniqid('logo_') . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newLogo)) throw new Exception('Failed to upload file');
            }
        }
        $logoFileName = $newLogo;
    } elseif (($_POST['remove_logo'] ?? '0') === '1') {
        $logoFileName = null;
    }

    $sql = "UPDATE companies SET
        company_name = ?, legal_name = ?, email = ?, phone = ?, mobile_no = ?, website = ?,
        address = ?, country = ?, state = ?, city = ?, zipcode = ?, po_box_no = ?,
        industry_type = ?, business_type = ?, registration_number = ?, tax_identification_number = ?,
        sales_tax_number = ?, logo_url = ?, language_code = ?, timezone = ?,
        inventory_valuation_method = ?, is_active = ?
        WHERE id = ? AND tenant_id = ?";

    $ok = $pdo->prepare($sql)->execute([
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
        $_POST['inventory_valuation_method'] ?? 'FIFO',
        ($_POST['is_active'] ?? 'true') === 'true' ? 1 : 0,
        $id,
        $tenant_id
    ]);

    if (!$ok) throw new Exception('Failed to update company');

    // delete the old logo file if it was replaced/removed
    if ($old['logo_url'] && $old['logo_url'] !== $logoFileName && file_exists($uploadDir . $old['logo_url'])) {
        @unlink($uploadDir . $old['logo_url']);
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Company', $id, $old['company_code'],
        "Updated company \"$companyName\" ({$old['company_code']})",
        ['company_name' => $old['company_name'], 'business_type' => $old['business_type'] ?? null,
         'email' => $old['email'], 'phone' => $old['phone']],
        ['company_name' => $companyName, 'business_type' => $businessType,
         'email' => $_POST['email'] ?? null, 'phone' => $_POST['phone'] ?? null]
    );

    echo json_encode(['success' => true, 'message' => 'Company updated successfully']);
} catch (Exception $e) {
    if ($newLogo && file_exists($uploadDir . $newLogo)) {
        @unlink($uploadDir . $newLogo);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>