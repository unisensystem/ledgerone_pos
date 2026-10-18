<?php
// server/api/dashboard_pos/meta.php
// Dashboard POS ko chahiye: Cash Customer id, branches, base currency, company, user name.
require_once '../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

try {
    $s = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND LOWER(TRIM(customer_name)) = 'cash customer' LIMIT 1");
    $s->execute([$tenant_id]);
    $cashCustomerId = $s->fetchColumn() ?: null;

    $s = $pdo->prepare("SELECT id, branch_name FROM branches WHERE tenant_id = ? ORDER BY id");
    $s->execute([$tenant_id]);
    $branches = $s->fetchAll(PDO::FETCH_ASSOC);

    $s = $pdo->prepare("
        SELECT c.id, c.symbol FROM tenant_currencies tc
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1 LIMIT 1");
    $s->execute([$tenant_id]);
    $cur = $s->fetch(PDO::FETCH_ASSOC) ?: ['id' => null, 'symbol' => 'Rs'];

    $s = $pdo->prepare("SELECT id FROM companies WHERE tenant_id = ? ORDER BY id LIMIT 1");
    $s->execute([$tenant_id]);
    $companyId = $s->fetchColumn() ?: null;

    $s = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $s->execute([$user_id]);

    echo json_encode([
        'success' => true,
        'cash_customer_id' => $cashCustomerId,
        'branches' => $branches,
        'currency_id' => $cur['id'],
        'currency_symbol' => $cur['symbol'],
        'company_id' => $companyId,
        'user_id' => (int) $user_id,
        'user_name' => $s->fetchColumn() ?: ''
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}