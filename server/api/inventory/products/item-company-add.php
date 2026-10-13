<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$name = trim($_POST['itemCompanyName'] ?? '');
if ($name === '') { echo json_encode(['success' => false, 'message' => 'Name is required']); exit; }

$stmt = $pdo->prepare("INSERT INTO item_companies (tenant_id, company_name) VALUES (?, ?)");
$stmt->execute([$tenant_id, $name]);

echo json_encode(['success' => true, 'item_company' => ['id' => $pdo->lastInsertId(), 'name' => $name]]);