<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$region_id = $_GET['region_id'] ?? null;

if (!$tenant_id || !$region_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT r.country_id
        FROM regions r
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$region_id, $tenant_id]);
    $region = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($region) {
        echo json_encode(['success' => true, 'hierarchy' => [
            'country_id' => $region['country_id'],
            'region_id' => $region_id
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Region not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
