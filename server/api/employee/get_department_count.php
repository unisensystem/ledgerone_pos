<?php
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_GET['department'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Department parameter is required']);
    exit;
}

try {
    $department = $_GET['department'];
    $year = date('Y');
    
    // Get count of employees in the department for the current year
    $sql = "SELECT COUNT(*) as count FROM employees 
            WHERE department = ? 
            AND employee_id LIKE ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$department, $year . '-' . $department . '-%']);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['count' => (int)$result['count']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
