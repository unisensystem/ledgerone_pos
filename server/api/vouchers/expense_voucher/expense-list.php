<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || $tenant_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $project_id = $_GET['project_id'] ?? null;
    
    $stmt = $pdo->prepare("
        SELECT 
            ev.id,
            ev.voucher_no,
            ev.date,
            ev.description,
            ev.total_amount,
            ev.total_items,
            ev.company_id,
            ev.project_id,
            p.project_name
        FROM expense_voucher ev
        LEFT JOIN projects p ON ev.project_id = p.id
        WHERE ev.tenant_id = ?
        " . ($project_id ? "AND ev.project_id = ?" : "") . "
        ORDER BY ev.id DESC
    ");
    
    if ($project_id) {
        $stmt->execute([$tenant_id, $project_id]);
    } else {
        $stmt->execute([$tenant_id]);
    }
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $vouchers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch expense vouchers']);
}
