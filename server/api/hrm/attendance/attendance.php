<?php
// Errors are LOGGED, never echoed, from an API endpoint. display_errors=1
// here was the cause of the intermittent "Error loading attendance records"
// error on the frontend: whenever PHP emitted any notice/warning (even a
// harmless one, e.g. a duplicate session_start(), or a PHP 8 deprecation
// notice), that warning text was printed into the response BEFORE the
// json_encode() output. The browser's response.json() then failed to parse
// the now-invalid JSON and the frontend fell into its catch block. Logging
// instead of displaying keeps every response valid JSON.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Wrapping the whole body in try/catch so that ANY unexpected error (a
// failed query, a missing column, etc.) still comes back as valid JSON
// with a message, instead of a half-printed PHP error breaking the
// frontend's response.json() call again in some other way later.
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $date = $_GET['date'] ?? date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT a.id, a.employee_id, e.full_name, d.department_name,
                   a.attendance_date,
                   DATE_FORMAT(a.check_in_time, '%H:%i') as check_in,
                   DATE_FORMAT(a.check_out_time, '%H:%i') as check_out,
                   a.status, a.notes, lt.name AS leave_type_name, lt.is_paid AS leave_is_paid
            FROM attendance a
            LEFT JOIN employees e ON a.employee_id = e.employee_id AND e.tenant_id = ?
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN leave_types lt ON lt.id = a.leave_type_id
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
            SELECT id, status as old_status, notes as old_notes FROM attendance
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

            if ($result) {
                logActivity(
                    $pdo, $tenant_id, $user_id, 'update', 'Attendance', $existing['id'], "$employee_id - $date",
                    "Recorded check-out for employee \"$employee_id\" on $date at $check_out (status: $status)",
                    ['status' => $existing['old_status'], 'notes' => $existing['old_notes'], 'check_out_time' => null],
                    ['status' => $status, 'notes' => $notes, 'check_out_time' => $check_out_datetime]
                );
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
                $newAttendanceId = $pdo->lastInsertId();
                logActivity(
                    $pdo, $tenant_id, $user_id, 'create', 'Attendance', $newAttendanceId, "$employee_id - $date",
                    "Recorded attendance for employee \"$employee_id\" on $date: $check_in - $check_out (status: $status)",
                    null,
                    ['employee_id' => $employee_id, 'attendance_date' => $date, 'check_in_time' => $check_in_datetime, 'check_out_time' => $check_out_datetime, 'status' => $status, 'notes' => $notes]
                );
                echo json_encode(['success' => true, 'message' => 'Attendance saved successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save attendance']);
            }
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    error_log('Attendance API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}