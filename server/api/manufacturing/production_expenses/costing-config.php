<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET - Fetch costing configuration
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get costing method
        $methodStmt = $pdo->prepare("
            SELECT config_value 
            FROM system_config 
            WHERE tenant_id = ? AND config_key = 'costing_method'
        ");
        $methodStmt->execute([$tenant_id]);
        $method = $methodStmt->fetchColumn();
        
        if (!$method) {
            $method = 'actual'; // Default
        }
        
        // Get standard overhead rates
        $ratesStmt = $pdo->prepare("
            SELECT * FROM overhead_rates
            WHERE tenant_id = ?
            ORDER BY rate_type
        ");
        $ratesStmt->execute([$tenant_id]);
        $rates = $ratesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'costing_method' => $method,
                'overhead_rates' => $rates
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST - Update costing configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $costing_method = $input['costing_method'] ?? null; // 'actual' or 'standard'
        $overhead_rates = $input['overhead_rates'] ?? [];
        
        if (!$costing_method || !in_array($costing_method, ['actual', 'standard'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid costing method']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Update or insert costing method
        $checkStmt = $pdo->prepare("
            SELECT id FROM system_config 
            WHERE tenant_id = ? AND config_key = 'costing_method'
        ");
        $checkStmt->execute([$tenant_id]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $updateStmt = $pdo->prepare("
                UPDATE system_config 
                SET config_value = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$costing_method, $existing['id']]);
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO system_config (tenant_id, config_key, config_value, created_at)
                VALUES (?, 'costing_method', ?, NOW())
            ");
            $insertStmt->execute([$tenant_id, $costing_method]);
        }
        
        // Update overhead rates (if standard costing)
        if ($costing_method === 'standard' && !empty($overhead_rates)) {
            // Delete existing rates
            $deleteStmt = $pdo->prepare("DELETE FROM overhead_rates WHERE tenant_id = ?");
            $deleteStmt->execute([$tenant_id]);
            
            // Insert new rates
            $insertRateStmt = $pdo->prepare("
                INSERT INTO overhead_rates 
                (tenant_id, rate_type, rate_value, description, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            foreach ($overhead_rates as $rate) {
                if (isset($rate['rate_type']) && isset($rate['rate_value'])) {
                    $insertRateStmt->execute([
                        $tenant_id,
                        $rate['rate_type'],
                        $rate['rate_value'],
                        $rate['description'] ?? null
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Costing configuration updated successfully'
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
