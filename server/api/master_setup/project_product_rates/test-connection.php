<?php
header('Content-Type: application/json');

require_once '../../../../includes/connection.php';

try {
    // Test 1: Connection OK
    echo json_encode([
        'success' => true,
        'test_1_connection' => 'OK',
        'test_2_checking_tables' => 'Starting...'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
