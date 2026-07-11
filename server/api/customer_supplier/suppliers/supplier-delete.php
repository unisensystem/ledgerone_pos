<?php
require_once '../../../../includes/connection.php';

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
    $supplier_id = $input['id'] ?? null;

    if (!$supplier_id) {
        throw new Exception('Supplier ID is required');
    }

    // Check if supplier exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT supplier_name FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$supplier_id, $tenant_id]);
    $supplier = $stmt->fetch();

    if (!$supplier) {
        throw new Exception('Supplier not found');
    }

    // Delete related accounting ledger entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'supplier_opening' AND reference_table = 'suppliers' AND reference_id = ?");
    $stmt->execute([$tenant_id, $supplier_id]);

    // Delete associated companies
    $stmt = $pdo->prepare("DELETE FROM supplier_associated_companies WHERE tenant_id = ? AND supplier_id = ?");
    $stmt->execute([$tenant_id, $supplier_id]);

    // Delete sub accounts
    $stmt = $pdo->prepare("DELETE FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ?");
    $stmt->execute([$tenant_id, $supplier_id]);

    // Hard delete supplier
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$supplier_id, $tenant_id]);
    echo json_encode([
        'success' => true,
        'message' => 'Supplier deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>