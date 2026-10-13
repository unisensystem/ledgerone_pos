<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Company ID required']);
        exit;
    }
    
    // Check if company exists and belongs to tenant (grab full row for the Activity Log)
    $checkStmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND tenant_id = ?");
    $checkStmt->execute([$id, $tenant_id]);
    $companyRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$companyRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Company not found']);
        exit;
    }

    // Delete company
    $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ? AND tenant_id = ?");
    $result = $stmt->execute([$id, $tenant_id]);

    if ($result) {
        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'Company', $id, $companyRow['company_code'],
            "Deleted company \"{$companyRow['company_name']}\" ({$companyRow['company_code']})",
            $companyRow, null
        );

        echo json_encode(['success' => true, 'message' => 'Company deleted successfully']);
    } else {
        throw new Exception('Failed to delete company');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>