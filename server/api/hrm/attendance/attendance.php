<?php

// Errors are LOGGED, never echoed, from an API endpoint.
// This prevents PHP warnings/notices from corrupting JSON responses.
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
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | GET - FETCH ATTENDANCE RECORDS
    |--------------------------------------------------------------------------
    |
    | Supports, in priority order:
    |   ?date=YYYY-MM-DD                    -> a single day (kept for
    |                                          backward compatibility / the
    |                                          attendance-entry form's
    |                                          auto check-in/out lookup)
    |   ?date_from=YYYY-MM-DD&date_to=...   -> a date range (used by the
    |                                          history table's From/To
    |                                          filters). If only one side is
    |                                          given, the other is treated
    |                                          as the same date.
    |   ?month=YYYY-MM                      -> every record in that month
    |                                          (kept for backward
    |                                          compatibility)
    | If none are given, defaults to just today — so the history table
    | normally shows today's attendance until a range is picked.
    */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $date = $_GET['date'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $month = $_GET['month'] ?? null;

        $baseSelect = "
            SELECT
                a.id,
                a.employee_id,
                e.full_name,
                d.department_name,
                a.attendance_date,
                DATE_FORMAT(a.check_in_time, '%H:%i') AS check_in,
                DATE_FORMAT(a.check_out_time, '%H:%i') AS check_out,
                a.status,
                a.notes,
                lt.name AS leave_type_name,
                lt.is_paid AS leave_is_paid
            FROM attendance a
            LEFT JOIN employees e
                ON a.employee_id = e.employee_id
                AND e.tenant_id = ?
            LEFT JOIN departments d
                ON e.department_id = d.id
            LEFT JOIN leave_types lt
                ON lt.id = a.leave_type_id
        ";

        if ($date) {
            // Single-day lookup (e.g. attendance-entry form checking today's records)
            $stmt = $pdo->prepare($baseSelect . "
                WHERE a.tenant_id = ?
                  AND a.attendance_date = ?
                ORDER BY a.check_in_time DESC
            ");
            $stmt->execute([$tenant_id, $tenant_id, $date]);
        } elseif ($dateFrom || $dateTo) {
            // Date range lookup — used by the history table's From/To filters.
            $dateFrom = $dateFrom ?: $dateTo;
            $dateTo = $dateTo ?: $dateFrom;
            $stmt = $pdo->prepare($baseSelect . "
                WHERE a.tenant_id = ?
                  AND a.attendance_date BETWEEN ? AND ?
                ORDER BY a.attendance_date DESC, a.check_in_time DESC
            ");
            $stmt->execute([$tenant_id, $tenant_id, $dateFrom, $dateTo]);
        } elseif ($month) {
            // Month lookup (kept for backward compatibility)
            $stmt = $pdo->prepare($baseSelect . "
                WHERE a.tenant_id = ?
                  AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
                ORDER BY a.attendance_date DESC, a.check_in_time DESC
            ");
            $stmt->execute([$tenant_id, $tenant_id, $month]);
        } else {
            // Nothing given — default to today only.
            $today = date('Y-m-d');
            $stmt = $pdo->prepare($baseSelect . "
                WHERE a.tenant_id = ?
                  AND a.attendance_date = ?
                ORDER BY a.check_in_time DESC
            ");
            $stmt->execute([$tenant_id, $tenant_id, $today]);
        }

        $records = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $records
        ]);
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | POST - CREATE / CHECK-OUT ATTENDANCE
    |--------------------------------------------------------------------------
    */
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $data = [];
        }

        $employee_id = $data['employeeId'] ?? '';
        $check_in = $data['checkIn'] ?? '';
        $check_out = $data['checkOut'] ?? '';
        $status = $data['status'] ?? 'present';
        $notes = $data['notes'] ?? '';
        $date = $data['date'] ?? date('Y-m-d');
        $is_checkout = $data['isCheckout'] ?? false;

        if (empty($employee_id)) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Employee ID is required'
            ]);
            exit;
        }

        // Check if record already exists for this employee/date.
        $checkStmt = $pdo->prepare("
            SELECT
                id,
                status AS old_status,
                notes AS old_notes
            FROM attendance
            WHERE tenant_id = ?
              AND employee_id = ?
              AND attendance_date = ?
        ");

        $checkStmt->execute([
            $tenant_id,
            $employee_id,
            $date
        ]);

        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | CHECK-OUT EXISTING RECORD
        |--------------------------------------------------------------------------
        */
        if ($existing && $is_checkout) {

            if (empty($check_out)) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Check-out time is required'
                ]);
                exit;
            }

            $check_out_datetime = $date . ' ' . $check_out . ':00';

      $stmt = $pdo->prepare("
    UPDATE attendance
    SET
        check_out_time = ?,
        status = ?,
        notes = ?
    WHERE id = ?
      AND tenant_id = ?
");

$result = $stmt->execute([
    $check_out_datetime,
    $status,
    $notes,
    $existing['id'],
    $tenant_id
]);
            

            if ($result) {

                logActivity(
                    $pdo,
                    $tenant_id,
                    $user_id,
                    'update',
                    'Attendance',
                    $existing['id'],
                    "$employee_id - $date",
                    "Recorded check-out for employee \"$employee_id\" on $date at $check_out (status: $status)",
                    [
                        'status' => $existing['old_status'],
                        'notes' => $existing['old_notes'],
                        'check_out_time' => null
                    ],
                    [
                        'status' => $status,
                        'notes' => $notes,
                        'check_out_time' => $check_out_datetime
                    ]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Check-out saved successfully'
                ]);
            } else {

                http_response_code(500);

                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save check-out'
                ]);
            }

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE NEW ATTENDANCE RECORD
        |--------------------------------------------------------------------------
        */
        else {

            if (empty($check_in) || empty($check_out)) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Check-in and check-out times are required'
                ]);
                exit;
            }

            $check_in_datetime = $date . ' ' . $check_in . ':00';
            $check_out_datetime = $date . ' ' . $check_out . ':00';

            $stmt = $pdo->prepare("
                INSERT INTO attendance
                (
                    tenant_id,
                    employee_id,
                    check_in_time,
                    check_out_time,
                    attendance_date,
                    status,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $tenant_id,
                $employee_id,
                $check_in_datetime,
                $check_out_datetime,
                $date,
                $status,
                $notes
            ]);

            if ($result) {

                $newAttendanceId = $pdo->lastInsertId();

                logActivity(
                    $pdo,
                    $tenant_id,
                    $user_id,
                    'create',
                    'Attendance',
                    $newAttendanceId,
                    "$employee_id - $date",
                    "Recorded attendance for employee \"$employee_id\" on $date: $check_in - $check_out (status: $status)",
                    null,
                    [
                        'employee_id' => $employee_id,
                        'attendance_date' => $date,
                        'check_in_time' => $check_in_datetime,
                        'check_out_time' => $check_out_datetime,
                        'status' => $status,
                        'notes' => $notes
                    ]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Attendance saved successfully'
                ]);
            } else {

                http_response_code(500);

                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save attendance'
                ]);
            }

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | PUT - UPDATE ATTENDANCE RECORD
    |--------------------------------------------------------------------------
    */
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {
            $input = [];
        }

        $id = isset($input['id'])
            ? (int)$input['id']
            : 0;

        $checkIn = isset($input['checkIn'])
            ? trim($input['checkIn'])
            : '';

        $checkOut = isset($input['checkOut'])
            ? trim($input['checkOut'])
            : '';

        $status = isset($input['status'])
            ? trim($input['status'])
            : '';

        $notes = isset($input['notes'])
            ? trim($input['notes'])
            : '';

        if ($id <= 0) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid attendance record ID'
            ]);
            exit;
        }

        if ($checkIn === '' || $checkOut === '') {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Check-in and check-out times are required'
            ]);
            exit;
        }

        // Make sure this record belongs to the current tenant.
        $checkStmt = $pdo->prepare("
            SELECT id
            FROM attendance
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $id,
            $tenant_id
        ]);

        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingRecord) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'Attendance record not found'
            ]);
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE RECORD
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | The rest of this file uses check_in_time and check_out_time.
        | Therefore PUT also updates those same database columns.
        |
        */
        $updateStmt = $pdo->prepare("
            UPDATE attendance
            SET
                check_in_time = ?,
                check_out_time = ?,
                status = ?,
                notes = ?
            WHERE id = ?
              AND tenant_id = ?
        ");

        /*
        | Convert HH:MM to the same datetime format used by POST.
        */
        $recordStmt = $pdo->prepare("
            SELECT attendance_date
            FROM attendance
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ");

        $recordStmt->execute([
            $id,
            $tenant_id
        ]);

        $record = $recordStmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'Attendance record not found'
            ]);
            exit;
        }

        $attendanceDate = $record['attendance_date'];

        $checkInDateTime = $attendanceDate . ' ' . $checkIn . ':00';
        $checkOutDateTime = $attendanceDate . ' ' . $checkOut . ':00';

        $updated = $updateStmt->execute([
            $checkInDateTime,
            $checkOutDateTime,
            $status,
            $notes,
            $id,
            $tenant_id
        ]);

        if ($updated) {

            // Activity log
            try {

                logActivity(
                    $pdo,
                    $tenant_id,
                    $user_id,
                    'update',
                    'Attendance',
                    $id,
                    'Attendance record updated',
                    null,
                    [
                        'check_in_time' => $checkInDateTime,
                        'check_out_time' => $checkOutDateTime,
                        'status' => $status,
                        'notes' => $notes
                    ]
                );

            } catch (Throwable $logError) {

                error_log(
                    'Attendance update activity log error: ' .
                    $logError->getMessage()
                );
            }

            echo json_encode([
                'success' => true,
                'message' => 'Attendance record updated successfully'
            ]);

        } else {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Failed to update attendance record'
            ]);
        }

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE - DELETE ATTENDANCE RECORD
    |--------------------------------------------------------------------------
    */
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {
            $input = [];
        }

        $id = isset($input['id'])
            ? (int)$input['id']
            : 0;

        if ($id <= 0) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid attendance record ID'
            ]);
            exit;
        }

        // Make sure this record belongs to the current tenant.
        $checkStmt = $pdo->prepare("
            SELECT id
            FROM attendance
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $id,
            $tenant_id
        ]);

        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingRecord) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'Attendance record not found'
            ]);
            exit;
        }

        // Delete record.
        $deleteStmt = $pdo->prepare("
            DELETE FROM attendance
            WHERE id = ?
              AND tenant_id = ?
        ");

        $deleted = $deleteStmt->execute([
            $id,
            $tenant_id
        ]);

        if ($deleted && $deleteStmt->rowCount() > 0) {

            // Activity log.
            try {

                logActivity(
                    $pdo,
                    $tenant_id,
                    $user_id,
                    'delete',
                    'Attendance',
                    $id,
                    'Attendance record deleted',
                    null,
                    null
                );

            } catch (Throwable $logError) {

                error_log(
                    'Attendance delete activity log error: ' .
                    $logError->getMessage()
                );
            }

            echo json_encode([
                'success' => true,
                'message' => 'Attendance record deleted successfully'
            ]);

        } else {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete attendance record'
            ]);
        }

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | METHOD NOT ALLOWED
    |--------------------------------------------------------------------------
    */
    else {

        http_response_code(405);

        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);

        exit;
    }


} catch (Throwable $e) {

    error_log(
        'Attendance API Error: ' .
        $e->getMessage() .
        ' in ' .
        $e->getFile() .
        ':' .
        $e->getLine()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    exit;
}