<?php
session_start();
include '../config/config.php';

header('Content-Type: application/json');

// Check session and admin status
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Session expired or unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, is_active FROM users");
    if (!$stmt) {
        throw new Exception('Failed to fetch user statuses');
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statuses = [];
    
    foreach ($users as $user) {
        $statuses[$user['id']] = [
            'is_active' => $user['is_active']
        ];
    }
    
    echo json_encode($statuses);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
