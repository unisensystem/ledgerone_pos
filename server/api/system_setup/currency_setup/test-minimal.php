<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

try {
    session_start();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/connection.php';
    
    if (!$pdo_public) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Public database not available']);
        exit;
    }
    
    $stmt = $pdo_public->prepare("SELECT id, code, name, symbol FROM currencies WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to UTF-8
    array_walk_recursive($currencies, function(&$item) {
        if (is_string($item)) {
            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        }
    });
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $currencies]);
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'line' => $e->getLine()]);
} catch (Error $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}
exit;
