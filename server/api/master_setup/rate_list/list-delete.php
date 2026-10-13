<?php
session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $data = json_decode(file_get_contents('php://input'), true);
    $rate_list_id = $data['id'] ?? null;
    
    if (!$rate_list_id) {
        throw new Exception('Rate list ID is required');
    }
    
    // Grab full row before deletion, for the Activity Log
    $stOld = $pdo->prepare("SELECT * FROM rate_list WHERE id = ? AND tenant_id = ?");
    $stOld->execute([$rate_list_id, $tenant_id]);
    $listRow = $stOld->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    // Delete rate list items
    $stmt = $pdo->prepare("DELETE FROM rate_list_items WHERE rate_list_id = ? AND tenant_id = ?");
    $stmt->execute([$rate_list_id, $tenant_id]);

    // Delete rate list
    $stmt = $pdo->prepare("DELETE FROM rate_list WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$rate_list_id, $tenant_id]);

    if ($listRow) {
        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'Rate List', $rate_list_id, $listRow['list_code'],
            "Deleted rate list \"{$listRow['list_name']}\" ({$listRow['list_code']})",
            $listRow, null
        );
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Rate list deleted successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
