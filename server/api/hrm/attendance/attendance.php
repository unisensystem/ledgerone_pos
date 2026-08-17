<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT a.id, a.employee_id, e.full_name, d.department_name,
               a.attendance_date,
               DATE_FORMAT(a.check_in_time, '%H:%i') as check_in,
               DATE_FORMAT(a.check_out_time, '%H:%i') as check_out,
               a.status, a.notes
        FROM attendance a
        LEFT JOIN employees e ON a.employee_id = e.employee_id AND e.tenant_id = ?
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE a.tenant_id = ? AND a.attendance_date = ?
        ORDER BY a.check_in_time DESC
    ");
    $stmt->execute([$tenant_id, $tenant_id, $date]);
    $records = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $records]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $employee_id = $data['employeeId'] ?? '';
    $check_in = $data['checkIn'] ?? '';
    $check_out = $data['checkOut'] ?? '';
    $status = $data['status'] ?? 'present';
    $notes = $data['notes'] ?? '';
    $date = $data['date'] ?? date('Y-m-d');
    $is_checkout = $data['isCheckout'] ?? false;
    
    if (empty($employee_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        exit;
    }
    
    // Check if record exists for today
    $checkStmt = $pdo->prepare("
        SELECT id FROM attendance 
        WHERE tenant_id = ? AND employee_id = ? AND attendance_date = ?
    ");
    $checkStmt->execute([$tenant_id, $employee_id, $date]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing && $is_checkout) {
        // Update existing record with check-out time
        $check_out_datetime = $date . ' ' . $check_out . ':00';
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET check_out_time = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$check_out_datetime, $status, $notes, $existing['id']]);
        error_log("Check-out update result: " . ($result ? 'success' : 'failed') . " for ID: " . $existing['id']);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Check-out saved successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save check-out']);
        }
    } else {
        // Insert new record with check-in
        if (empty($check_in) || empty($check_out)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Check-in and check-out times are required']);
            exit;
        }
        
        $check_in_datetime = $date . ' ' . $check_in . ':00';
        $check_out_datetime = $date . ' ' . $check_out . ':00';
        
        $stmt = $pdo->prepare("
            INSERT INTO attendance 
            (tenant_id, employee_id, check_in_time, check_out_time, attendance_date, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$tenant_id, $employee_id, $check_in_datetime, $check_out_datetime, $date, $status, $notes])) {
            echo json_encode(['success' => true, 'message' => 'Attendance saved successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save attendance']);
        }
    }
}