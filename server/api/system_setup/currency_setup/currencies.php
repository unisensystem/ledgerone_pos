<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

try {
    session_start();
    require_once '../../../../includes/connection.php';
    
    $stmt = $pdo->prepare("SELECT id, code, name, symbol FROM ledgerone_public.currencies WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($currencies as &$currency) {
        if (isset($currency['symbol_hex'])) {
            $currency['symbol'] = hex2bin($currency['symbol_hex']);
            unset($currency['symbol_hex']);
        }
    }
    
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $currencies], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;