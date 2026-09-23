<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$stmt = $pdo->prepare("SELECT id, company_name FROM item_companies WHERE tenant_id = ? ORDER BY company_name");
$stmt->execute([$tenant_id]);
echo json_encode(['success' => true, 'item_companies' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);