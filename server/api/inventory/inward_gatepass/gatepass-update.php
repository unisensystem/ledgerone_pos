<?php
session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['id'])) {
        throw new Exception('Invalid input data');
    }
    
    if (empty($input['date']) || empty($input['company_id']) || empty($input['supplier_id']) || empty($input['branch_id']) || empty($input['items'])) {
        throw new Exception('Missing required fields');
    }
    
    $container_id = $input['container_id'] ?? null;

    // Grab old values before anything changes, for the Activity Log diff
    $stmt = $pdo->prepare("SELECT gatepass_code, date, company_id, supplier_id, branch_id, container_id FROM inward_gatepass WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    $oldGatepass = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldGatepass) {
        throw new Exception('Gatepass not found');
    }
    $stmt = $pdo->prepare("SELECT product_id, unit_id, quantity FROM inward_gatepass_items WHERE gatepass_id = ? AND tenant_id = ? ORDER BY product_id, unit_id, id");
    $stmt->execute([$input['id'], $tenant_id]);
    $oldItemsSignature = json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE inward_gatepass
        SET date = ?, company_id = ?, supplier_id = ?, branch_id = ?, container_id = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([
        $input['date'],
        $input['company_id'],
        $input['supplier_id'],
        $input['branch_id'],
        $container_id,
        $input['id'],
        $tenant_id
    ]);
    
    // Check if gatepass exists
    $stmt = $pdo->prepare("SELECT id FROM inward_gatepass WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Gatepass not found');
    }
    
    // Delete existing items
    $stmt = $pdo->prepare("DELETE FROM inward_gatepass_items WHERE gatepass_id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    
    // Insert new items
    $stmt = $pdo->prepare("
        INSERT INTO inward_gatepass_items (tenant_id, gatepass_id, product_id, unit_id, quantity) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($input['items'] as $item) {
        $stmt->execute([
            $tenant_id,
            $input['id'],
            $item['product_id'],
            $item['unit_id'],
            $item['quantity']
        ]);
    }
    
    $newGatepass = [
        'gatepass_code' => $oldGatepass['gatepass_code'],
        'date' => $input['date'],
        'company_id' => $input['company_id'],
        'supplier_id' => $input['supplier_id'],
        'branch_id' => $input['branch_id'],
        'container_id' => $container_id
    ];
    $changeDescriptions = buildChangeDescription($oldGatepass, $newGatepass, [
        'date' => 'Date',
        'company_id' => 'Company',
        'supplier_id' => 'Supplier',
        'branch_id' => 'Branch',
        'container_id' => 'Container'
    ]);
    if (itemsChanged(json_decode($oldItemsSignature, true), $input['items'], ['product_id', 'unit_id', 'quantity'])) {
        $changeDescriptions[] = 'Items updated';
    }
    $editDescription = !empty($changeDescriptions)
        ? "Updated inward gatepass \"{$oldGatepass['gatepass_code']}\": " . implode('; ', $changeDescriptions)
        : "Updated inward gatepass \"{$oldGatepass['gatepass_code']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Inward Gatepass', $input['id'], $oldGatepass['gatepass_code'], $editDescription, $oldGatepass, $newGatepass);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Inward gatepass updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
