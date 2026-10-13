<?php
// Bulk-posts Draft sale orders (the ones the hybrid auto-post rule flagged
// for office review — stale offline price, or not enough stock to cover
// several field bookers' requests at once) straight to Posted, without
// requiring a full edit-and-resave of each one. A human explicitly choosing
// to finalize here is the actual review the Draft status was waiting for,
// so unlike order-add.php this does NOT re-run the price/stock conflict
// check — re-blocking a deliberate human decision would defeat the point.
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once '../../../../includes/mobile_auth.php';
require_once '../../../../includes/check_permission.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

// This endpoint approves exactly the thing the hybrid auto-post rule
// flagged — a booker's own stale price, or stock they oversold. Without a
// role check here, that same booker's phone (mobile token auth) could call
// this endpoint directly and wave their own flagged order through,
// defeating the entire review step. Two independent restrictions, not one:
// (1) web session only — the mobile token path can never reach this,
// mirroring who actually does office review in this system; (2) the
// 'Sale Order' + 'Edit' permission web's own page is built around, which a
// pure order-booker role typically won't have (only Add/View).
if ($auth['via'] !== 'session') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden — finalize this from the web app']);
    exit;
}
if (!hasPermission($pdo, $user_id, 'Sale', 'Sale Order', 'Edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to finalize draft orders']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ids = array_values(array_unique(array_filter(array_map('intval', $input['ids'] ?? []))));

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No order ids given']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Only ever touches rows that are actually still Draft for this tenant —
    // an id that's already Posted, deleted, or belongs to someone else's
    // tenant is silently skipped rather than erroring the whole batch.
    // created_by <> ? blocks self-approval even by a legitimately
    // Edit-permitted reviewer who happens to be the one who created the
    // order — review means a second person looks at it.
    $stmt = $pdo->prepare("SELECT id, bill_no FROM sale_order WHERE tenant_id = ? AND status = 'Draft' AND created_by <> ? AND id IN ($placeholders)");
    $stmt->execute([$tenant_id, $user_id, ...$ids]);
    $toFinalize = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($toFinalize)) {
        echo json_encode(['success' => true, 'finalized' => 0, 'bill_numbers' => []]);
        exit;
    }

    $finalizeIds = array_column($toFinalize, 'id');
    $finalizePlaceholders = implode(',', array_fill(0, count($finalizeIds), '?'));

    $pdo->beginTransaction();
    $updateStmt = $pdo->prepare("UPDATE sale_order SET status = 'Posted', updated_by = ? WHERE tenant_id = ? AND id IN ($finalizePlaceholders)");
    $updateStmt->execute([$user_id, $tenant_id, ...$finalizeIds]);
    $finalizedCount = $updateStmt->rowCount();

    foreach ($toFinalize as $order) {
        logActivity(
            $pdo, $tenant_id, $user_id, 'update', 'Sale Order', $order['id'], $order['bill_no'],
            "Finalized draft sale order \"{$order['bill_no']}\" (bulk review approval)",
            ['status' => 'Draft'], ['status' => 'Posted']
        );
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'finalized' => $finalizedCount,
        'bill_numbers' => array_column($toFinalize, 'bill_no'),
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
