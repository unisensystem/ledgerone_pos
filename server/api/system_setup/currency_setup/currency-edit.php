<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Currency ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT tc.id, tc.currency_id, tc.is_active, c.code, c.name, c.symbol
            FROM tenant_currencies tc 
            JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
            WHERE tc.id = ? AND tc.tenant_id = ?
        ");
        $stmt->execute([$id, $tenant_id]);
        $currency = $stmt->fetch();
        
        if (!$currency) {
            echo json_encode(['success' => false, 'message' => 'Currency not found']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $currency]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? '';
        $currency_id = $input['currency_id'] ?? '';
        $is_active = $input['is_active'] ?? 1;
        
        if (empty($id) || empty($currency_id)) {
            echo json_encode(['success' => false, 'message' => 'ID and currency are required']);
            exit;
        }
        
        // Check if currency exists for tenant (grab full row for the Activity Log)
        $stmt = $pdo->prepare("SELECT tc.currency_id, tc.is_active, c.code FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.id = ? AND tc.tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $oldCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldCurrency) {
            echo json_encode(['success' => false, 'message' => 'Currency not found']);
            exit;
        }

        // Update currency
        $stmt = $pdo->prepare("UPDATE tenant_currencies SET currency_id = ?, is_active = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$currency_id, $is_active ? 1 : 0, $id, $tenant_id]);

        $newCodeStmt = $pdo->prepare("SELECT code FROM ledgerone_public.currencies WHERE id = ?");
        $newCodeStmt->execute([$currency_id]);
        $newCode = $newCodeStmt->fetchColumn();

        $newCurrency = ['currency_id' => $currency_id, 'is_active' => $is_active ? 1 : 0, 'code' => $newCode];
        $changeDescriptions = buildChangeDescription($oldCurrency, $newCurrency, ['currency_id' => 'Currency', 'is_active' => 'Active']);
        $editDescription = !empty($changeDescriptions)
            ? "Updated currency \"{$oldCurrency['code']}\": " . implode('; ', $changeDescriptions)
            : "Updated currency \"{$oldCurrency['code']}\" - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Currency', $id, $oldCurrency['code'], $editDescription, $oldCurrency, $newCurrency);

        echo json_encode(['success' => true, 'message' => 'Currency updated successfully']);
    }
} catch (Exception $e) {
    error_log('Currency edit error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}