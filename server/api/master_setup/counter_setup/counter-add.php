<?php
require_once '../../../../includes/connection.php';

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
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['counter_name']) || empty($input['branch_id'])) {
        throw new Exception('Counter name and branch are required');
    }

    // Prevent duplicate names in the same branch
    $dup = $pdo->prepare("SELECT id FROM counters WHERE tenant_id = ? AND branch_id = ? AND counter_name = ?");
    $dup->execute([$tenant_id, $input['branch_id'], trim($input['counter_name'])]);
    if ($dup->fetchColumn()) {
        throw new Exception('A counter with that name already exists in this branch');
    }

    $stmt = $pdo->prepare("
        INSERT INTO counters (tenant_id, company_id, branch_id, counter_name, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tenant_id,
        !empty($input['company_id']) ? intval($input['company_id']) : null,
        intval($input['branch_id']),
        trim($input['counter_name']),
        isset($input['is_active']) ? intval($input['is_active']) : 1,
        $user_id
    ]);

    echo json_encode([
        'success'    => true,
        'message'    => 'Counter added successfully',
        'counter_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
