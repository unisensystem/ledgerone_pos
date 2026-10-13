<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Return tenant's own categories + system categories (tenant_id = 0)
        $stmt = $pdo->prepare("SELECT id, category_name, tenant_id FROM supplier_categories WHERE tenant_id = ? OR tenant_id = 0 ORDER BY tenant_id ASC, category_name ASC");
        $stmt->execute([$tenant_id]);
        $categories = $stmt->fetchAll();

        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['category_name'] ?? '');

        if ($name === '') {
            throw new Exception('Category name is required');
        }

        $stmt = $pdo->prepare("INSERT INTO supplier_categories (tenant_id, category_name, created_by, updated_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $name, $user_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Category added successfully', 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['category_name'] ?? '');

        if (!$id || $name === '') {
            throw new Exception('Category id and name are required');
        }

        // Prevent editing system categories
        $stmt = $pdo->prepare("SELECT tenant_id FROM supplier_categories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception('Category not found');
        if ((int)$row['tenant_id'] === 0) throw new Exception('System categories cannot be edited');
        if ((int)$row['tenant_id'] !== (int)$tenant_id) throw new Exception('Unauthorized');

        $stmt = $pdo->prepare("UPDATE supplier_categories SET category_name = ?, updated_by = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$name, $user_id, $id, $tenant_id]);

        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
        exit;
    }

    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if (!$id) throw new Exception('Category id is required');

        $stmt = $pdo->prepare("SELECT tenant_id FROM supplier_categories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception('Category not found');
        if ((int)$row['tenant_id'] === 0) throw new Exception('System categories cannot be deleted');
        if ((int)$row['tenant_id'] !== (int)$tenant_id) throw new Exception('Unauthorized');

        // Unlink suppliers using this category before deleting
        $stmt = $pdo->prepare("UPDATE suppliers SET supplier_category_id = NULL WHERE supplier_category_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        $stmt = $pdo->prepare("DELETE FROM supplier_categories WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>