<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$current_user_id = $_SESSION['user_id'] ?? null;
$tenant_id       = $_SESSION['tenant_id'] ?? null;

if (!$current_user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET: fetch company access for a user
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'user_id is required']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT uc.company_id, c.company_code, c.company_name
            FROM user_companies uc
            JOIN companies c ON uc.company_id = c.id
            WHERE uc.user_id = ? AND uc.tenant_id = ?
        ");
        $stmt->execute([$user_id, $tenant_id]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'companies' => $companies]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST: save (replace) company access for a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data        = json_decode(file_get_contents('php://input'), true);
    $user_id     = $data['user_id']     ?? null;
    $company_ids = $data['company_ids'] ?? [];

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'user_id is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $del = $pdo->prepare("DELETE FROM user_companies WHERE user_id = ? AND tenant_id = ?");
        $del->execute([$user_id, $tenant_id]);

        if (!empty($company_ids)) {
            $ins = $pdo->prepare("INSERT INTO user_companies (tenant_id, user_id, company_id) VALUES (?, ?, ?)");
            foreach ($company_ids as $company_id) {
                $ins->execute([$tenant_id, $user_id, (int)$company_id]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Company access updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
