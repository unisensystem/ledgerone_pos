<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$id = $_POST['id'] ?? null;
$name = trim($_POST['itemCompanyName'] ?? '');
if (!$id || $name === '') { echo json_encode(['success' => false, 'message' => 'Invalid data']); exit; }

$stmt = $pdo->prepare("UPDATE item_companies SET company_name = ? WHERE id = ? AND tenant_id = ?");
$stmt->execute([$name, $id, $tenant_id]);

echo json_encode(['success' => true, 'item_company' => ['id' => $id, 'name' => $name]]);