<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    $id = $_GET['id'] ?? null;
    
    if (empty($id)) {
        throw new Exception('Branch ID is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    $branchRow = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "DELETE FROM branches WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$id, $tenant_id]);

    if ($result && $stmt->rowCount() > 0) {
        if ($branchRow) {
            logActivity(
                $pdo, $tenant_id, $user_id, 'delete', 'Branch', $id, $branchRow['branch_code'],
                "Deleted branch \"{$branchRow['branch_name']}\" ({$branchRow['branch_code']})",
                $branchRow, null
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Branch deleted successfully'
        ]);
    } else {
        throw new Exception('Branch not found or could not be deleted');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}