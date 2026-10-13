<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$id = $_POST['id'] ?? null;
if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

$stmt = $pdo->prepare("DELETE FROM item_companies WHERE id = ? AND tenant_id = ?");
$stmt->execute([$id, $tenant_id]);

echo json_encode(['success' => true]);