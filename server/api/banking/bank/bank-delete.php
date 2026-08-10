<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('Bank account ID is required');
    }
    
    $pdo->beginTransaction();
    
    // Get account_id and bank_logo_path before deleting
    $stmt = $pdo->prepare("SELECT account_id, bank_logo_path FROM bank_accounts WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    $account = $stmt->fetch();
    
    if (!$account) {
        throw new Exception('Bank account not found');
    }
    
    // Delete bank logo file if exists
    if ($account['bank_logo_path']) {
        try {
            $baseDir = realpath(__DIR__ . '/../../../../');
            if ($baseDir) {
                $logoPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $account['bank_logo_path']);
                if (file_exists($logoPath)) {
                    unlink($logoPath);
                }
            }
        } catch (Exception $e) {
            error_log('Error deleting bank logo: ' . $e->getMessage());
        }
    }
    
    // Delete from accounting_ledger
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'bank_accounts' AND reference_id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    
    // Delete from bank_accounts
    $stmt = $pdo->prepare("DELETE FROM bank_accounts WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    
    // Delete from accounts if account_id exists
    if ($account['account_id']) {
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$account['account_id'], $tenant_id]);
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Bank account deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}