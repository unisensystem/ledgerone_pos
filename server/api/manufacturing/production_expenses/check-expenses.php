<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['error' => 'No tenant_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM production_expenses WHERE tenant_id = ? LIMIT 5");
    $stmt->execute([$tenant_id]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'expenses' => $expenses
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
