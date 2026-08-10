<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET - Fetch overhead rates
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM overhead_rates
            WHERE tenant_id = ?
            ORDER BY rate_type
        ");
        $stmt->execute([$tenant_id]);
        $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $rates]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST - Create/Update overhead rate
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $rate_type = $input['rate_type'] ?? null; // 'per_unit', 'per_hour', 'percentage_of_material'
        $rate_value = $input['rate_value'] ?? null;
        $description = $input['description'] ?? null;
        
        if (!$rate_type || !$rate_value) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        // Check if rate exists
        $checkStmt = $pdo->prepare("
            SELECT id FROM overhead_rates
            WHERE tenant_id = ? AND rate_type = ?
        ");
        $checkStmt->execute([$tenant_id, $rate_type]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE overhead_rates
                SET rate_value = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$rate_value, $description, $existing['id']]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO overhead_rates
                (tenant_id, rate_type, rate_value, description, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$tenant_id, $rate_type, $rate_value, $description]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Overhead rate saved']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
