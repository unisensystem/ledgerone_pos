<?php
header('Content-Type: application/json');
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$id) {
        throw new Exception('Container ID is required');
    }

    // Verify container exists for this tenant (grab full row for the Activity Log)
    $checkQuery = "SELECT * FROM containers WHERE id = ? AND tenant_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id, $tenant_id]);
    $containerRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$containerRow) {
        throw new Exception('Container not found');
    }

    $query = "DELETE FROM containers WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($query);

    if (!$stmt->execute([$id, $tenant_id])) {
        throw new Exception('Failed to delete container');
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Container', $id, $containerRow['container_code'],
        "Deleted container \"{$containerRow['container_name']}\" ({$containerRow['container_code']})",
        $containerRow, null
    );

    echo json_encode([
        'success' => true,
        'message' => 'Container deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
