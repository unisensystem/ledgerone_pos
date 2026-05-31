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
    $stmt = $pdo->prepare("SELECT country_id FROM regions WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$region_id, $tenant_id]);
    $region = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$region) {
        echo json_encode(['success' => false, 'message' => 'Region not found']);
        exit;
    }

    echo json_encode(['success' => true, 'hierarchy' => ['country_id' => $region['country_id']]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
