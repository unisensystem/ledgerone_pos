<?php
header('Content-Type: application/json');

session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$tenant_id) {
        throw new Exception('Tenant ID not found in session');
    }

    if (!$data || !isset($data['id'])) {
        throw new Exception('Missing rate ID');
    }

    $id = $data['id'];

    // Verify this rate belongs to current tenant (grab full row for the Activity Log)
    $verifyQuery = "SELECT * FROM project_product_rates WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($verifyQuery);
    $stmt->execute([$id, $tenant_id]);
    $rateRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rateRow) {
        throw new Exception('Rate not found or you do not have permission to delete it');
    }

    $query = "DELETE FROM project_product_rates WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($query);

    if ($stmt->execute([$id, $tenant_id])) {
        if ($stmt->rowCount() > 0) {
            logActivity(
                $pdo, $tenant_id, $user_id, 'delete', 'Project Product Rate', $id, "Project #{$rateRow['project_id']} / Product #{$rateRow['product_id']}",
                "Deleted project product rate (Project #{$rateRow['project_id']} / Product #{$rateRow['product_id']}, rate " . number_format($rateRow['rate'], 2) . ")",
                $rateRow, null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Rate deleted successfully'
            ]);
        } else {
            throw new Exception('Rate not found');
        }
    } else {
        throw new Exception('Failed to delete rate');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
