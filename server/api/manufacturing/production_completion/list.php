<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // List Completions
    if ($action === 'list') {
        $search = $_GET['search'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        $sql = "
            SELECT 
                pc.*,
                po.order_no
            FROM production_completions pc
            JOIN production_orders po ON pc.production_order_id = po.id
            WHERE pc.tenant_id = ?
        ";
        
        $params = [$tenant_id];
        
        if ($search) {
            $sql .= " AND (pc.completion_no LIKE ? OR po.order_no LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if ($dateFrom) {
            $sql .= " AND pc.complete_date >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND pc.complete_date <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY pc.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // View Completion
    if ($action === 'view' && isset($_GET['id'])) {
        $id = $_GET['id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                pc.*,
                po.order_no,
                b.branch_name,
                m.machine_name
            FROM production_completions pc
            JOIN production_orders po ON pc.production_order_id = po.id
            JOIN branches b ON pc.branch_id = b.id
            LEFT JOIN machines m ON pc.machine_id = m.id
            WHERE pc.id = ? AND pc.tenant_id = ?
        ");
        $stmt->execute([$id, $tenant_id]);
        $completion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($completion) {
            $stmt = $pdo->prepare("
                SELECT 
                    cp.*,
                    p.code as product_code,
                    p.name as product_name,
                    u.uom_name
                FROM completed_products cp
                JOIN products p ON cp.product_id = p.id
                LEFT JOIN uom u ON cp.uom_id = u.id
                WHERE cp.production_completion_id = ?
            ");
            $stmt->execute([$id]);
            $completion['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get consumed materials
            $stmt = $pdo->prepare("
                SELECT 
                    pcm.*,
                    p.code as material_code,
                    p.name as material_name,
                    u.uom_name
                FROM production_completion_materials pcm
                JOIN products p ON pcm.material_id = p.id
                LEFT JOIN uom u ON pcm.uom_id = u.id
                WHERE pcm.production_completion_id = ?
            ");
            $stmt->execute([$id]);
            $completion['materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $completion]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? null;
    
    if ($action === 'delete' && $id) {
        try {
            $pdo->beginTransaction();
            
            // Get completion details
            $stmt = $pdo->prepare("SELECT * FROM production_completions WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            $completion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$completion) {
                throw new Exception('Completion not found');
            }
            
            // Delete stock ledger entries (WIP OUT and Finished Goods IN)
            $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'production_completions' AND reference_id = ?");
            $stmt->execute([$id]);
            
            // Delete accounting ledger entries
            $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'production_completions' AND reference_id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            
            // Delete completion materials
            $stmt = $pdo->prepare("DELETE FROM production_completion_materials WHERE production_completion_id = ?");
            $stmt->execute([$id]);
            
            // Delete completed products
            $stmt = $pdo->prepare("DELETE FROM completed_products WHERE production_completion_id = ?");
            $stmt->execute([$id]);
            
            // Delete completion
            $stmt = $pdo->prepare("DELETE FROM production_completions WHERE id = ?");
            $stmt->execute([$id]);
            
            // Update production order status back
            $remainingStmt = $pdo->prepare("SELECT COUNT(*) FROM production_completions WHERE production_order_id = ? AND tenant_id = ?");
            $remainingStmt->execute([$completion['production_order_id'], $tenant_id]);
            $remainingCount = $remainingStmt->fetchColumn();
            
            $newStatus = $remainingCount > 0 ? 'In Progress' : 'Planned';
            $stmt = $pdo->prepare("UPDATE production_orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $completion['production_order_id']]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Completion deleted successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
