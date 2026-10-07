<?php
/*
|--------------------------------------------------------------------------
| Biometric Device Push Endpoint
|--------------------------------------------------------------------------
| Fingerprint / face-recognition machines POST punch records here.
| Authentication is via a per-device API token (no PHP session required).
|
| Token delivery (either works):
|   Header : X-Device-Token: <token>
|   Query  : ?token=<token>
|
| Single-punch request body:
|   {
|     "machine_employee_id": "5",
|     "punch_time":          "2026-10-06 09:15:00",   -- or ISO 8601
|     "punch_type":          0   -- 0/in = check-in, 1/out = check-out, omit to auto-detect
|   }
|
| Batch request body (sync multiple records at once):
|   { "records": [ {...}, {...} ] }
|
| Punch-type values accepted:
|   0, "0", "in",  "check-in",  "checkin"  -> check-in
|   1, "1", "out", "check-out", "checkout" -> check-out
|   anything else / omitted                -> auto-detect from existing record
*/

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

// Only POST is accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ── Authenticate device ──────────────────────────────────────────────────────

$token = trim($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? $_GET['token'] ?? '');

if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Missing device token']);
    exit;
}

$devStmt = $pdo->prepare("
    SELECT id, tenant_id, device_name
    FROM biometric_devices
    WHERE api_token = ?
      AND is_active = 1
    LIMIT 1
");
$devStmt->execute([$token]);
$device = $devStmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or inactive device token']);
    exit;
}

$device_id = (int)$device['id'];
$tenant_id = (int)$device['tenant_id'];
$device_name = $device['device_name'];

// Update last_sync timestamp
$pdo->prepare("UPDATE biometric_devices SET last_sync = NOW() WHERE id = ?")
    ->execute([$device_id]);

// ── Parse body ───────────────────────────────────────────────────────────────

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

// Normalise to an array of records
if (isset($body['records']) && is_array($body['records'])) {
    $records = $body['records'];
} else {
    $records = [$body]; // single record
}

if (empty($records)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No records provided']);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Normalise punch_type to "in", "out", or "auto".
 */
function normalisePunchType($raw): string {
    if ($raw === null || $raw === '' || $raw === false) return 'auto';
    $v = strtolower((string)$raw);
    if (in_array($v, ['0', 'in', 'check-in', 'checkin'], true))  return 'in';
    if (in_array($v, ['1', 'out', 'check-out', 'checkout'], true)) return 'out';
    return 'auto';
}

/**
 * Calculate attendance status from employee shift_timing and check-in time.
 * shift_timing format: "HH:MM-HH:MM"  (e.g. "09:00-17:30")
 * Grace period: 15 minutes.
 */
function calcStatus(string $shiftTiming, string $checkInTime): string {
    if (empty($shiftTiming)) return 'present';

    $parts = explode('-', $shiftTiming);
    if (count($parts) < 2) return 'present';

    $shiftStart = strtotime(date('Y-m-d') . ' ' . trim($parts[0]));
    $grace      = 15 * 60; // 15 min in seconds
    $actualIn   = strtotime(date('Y-m-d') . ' ' . substr($checkInTime, 11, 5));

    if ($actualIn > ($shiftStart + $grace)) return 'late';
    return 'present';
}

// ── Process records ──────────────────────────────────────────────────────────

$results   = [];
$processed = 0;
$skipped   = 0;
$errors    = 0;

foreach ($records as $rec) {

    $machineEmpId = trim((string)($rec['machine_employee_id'] ?? ''));
    $punchTimeRaw = trim((string)($rec['punch_time'] ?? ''));
    $punchTypeRaw = $rec['punch_type'] ?? null;

    // ── Validate required fields ──────────────────────────────────────────
    if ($machineEmpId === '' || $punchTimeRaw === '') {
        $results[] = [
            'machine_employee_id' => $machineEmpId,
            'status'  => 'error',
            'message' => 'machine_employee_id and punch_time are required',
        ];
        $errors++;
        logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchTimeRaw, $punchTypeRaw, 'error', 'Missing required fields');
        continue;
    }

    // ── Parse punch_time ──────────────────────────────────────────────────
    $punchTs = strtotime($punchTimeRaw);
    if ($punchTs === false) {
        $results[] = [
            'machine_employee_id' => $machineEmpId,
            'status'  => 'error',
            'message' => 'Invalid punch_time format',
        ];
        $errors++;
        logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchTimeRaw, $punchTypeRaw, 'error', 'Invalid punch_time');
        continue;
    }

    $punchDatetime = date('Y-m-d H:i:s', $punchTs);
    $punchDate     = date('Y-m-d', $punchTs);
    $punchTime     = date('H:i', $punchTs);
    $punchType     = normalisePunchType($punchTypeRaw);

    // ── Resolve machine ID → ERP employee_id ─────────────────────────────
    $mapStmt = $pdo->prepare("
        SELECT employee_id
        FROM biometric_employee_mapping
        WHERE tenant_id = ?
          AND machine_employee_id = ?
        LIMIT 1
    ");
    $mapStmt->execute([$tenant_id, $machineEmpId]);
    $mapping = $mapStmt->fetch(PDO::FETCH_ASSOC);

    if (!$mapping) {
        $results[] = [
            'machine_employee_id' => $machineEmpId,
            'status'  => 'unmapped',
            'message' => "No ERP employee mapped to machine ID \"$machineEmpId\"",
        ];
        $skipped++;
        logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchDatetime, $punchTypeRaw, 'unmapped', 'No employee mapping found');
        continue;
    }

    $employee_id = $mapping['employee_id'];

    // ── Fetch employee shift timing ───────────────────────────────────────
    $empStmt = $pdo->prepare("
        SELECT shift_timing
        FROM employees
        WHERE employee_id = ?
          AND tenant_id = ?
        LIMIT 1
    ");
    $empStmt->execute([$employee_id, $tenant_id]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
    $shiftTiming = $employee['shift_timing'] ?? '';

    // ── Check for existing attendance record today ────────────────────────
    $existStmt = $pdo->prepare("
        SELECT id, check_in_time, check_out_time
        FROM attendance
        WHERE tenant_id = ?
          AND employee_id = ?
          AND attendance_date = ?
        LIMIT 1
    ");
    $existStmt->execute([$tenant_id, $employee_id, $punchDate]);
    $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

    // ── Determine effective punch type ────────────────────────────────────
    if ($punchType === 'auto') {
        // No record yet → check-in; record exists with no check-out → check-out
        if (!$existing) {
            $punchType = 'in';
        } elseif ($existing['check_out_time'] === null) {
            $punchType = 'out';
        } else {
            // Both already recorded — ignore duplicate
            $results[] = [
                'machine_employee_id' => $machineEmpId,
                'employee_id' => $employee_id,
                'punch_time'  => $punchDatetime,
                'status'      => 'skipped',
                'message'     => 'Both check-in and check-out already recorded for this date',
            ];
            $skipped++;
            logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchDatetime, $punchTypeRaw, 'processed', 'Skipped — already complete');
            continue;
        }
    }

    // ── Apply the punch ───────────────────────────────────────────────────
    try {
        if ($punchType === 'in') {

            if ($existing) {
                // Check-in already exists — skip to avoid overwrite
                $results[] = [
                    'machine_employee_id' => $machineEmpId,
                    'employee_id' => $employee_id,
                    'punch_time'  => $punchDatetime,
                    'status'      => 'skipped',
                    'message'     => 'Check-in already recorded for this date',
                ];
                $skipped++;
                logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchDatetime, $punchTypeRaw, 'processed', 'Skipped — check-in exists');
                continue;
            }

            $status = calcStatus($shiftTiming, $punchDatetime);

            $insStmt = $pdo->prepare("
                INSERT INTO attendance
                    (tenant_id, employee_id, check_in_time, attendance_date, status, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insStmt->execute([
                $tenant_id,
                $employee_id,
                $punchDatetime,
                $punchDate,
                $status,
                "Biometric: $device_name",
            ]);

            $newId = (int)$pdo->lastInsertId();

            logActivity($pdo, $tenant_id, null, 'create', 'Attendance', $newId,
                "$employee_id - $punchDate",
                "Biometric check-in via \"$device_name\" at $punchTime (status: $status)",
                null,
                ['employee_id' => $employee_id, 'check_in_time' => $punchDatetime, 'status' => $status]
            );

            $results[] = [
                'machine_employee_id' => $machineEmpId,
                'employee_id' => $employee_id,
                'punch_time'  => $punchDatetime,
                'punch_type'  => 'in',
                'status'      => 'processed',
                'attendance_status' => $status,
            ];
            $processed++;
            logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchDatetime, $punchTypeRaw, 'processed', null);

        } else { // check-out

            if (!$existing) {
                // No check-in on record — create a stub with check-out only
                $insStmt = $pdo->prepare("
                    INSERT INTO attendance
                        (tenant_id, employee_id, check_in_time, check_out_time, attendance_date, status, notes)
                    VALUES (?, ?, ?, ?, ?, 'present', ?)
                ");
                $insStmt->execute([
                    $tenant_id,
                    $employee_id,
                    $punchDatetime, // use punch time as placeholder check-in
                    $punchDatetime,
                    $punchDate,
                    "Biometric: $device_name (check-in time unknown)",
                ]);
                $newId = (int)$pdo->lastInsertId();

                logActivity($pdo, $tenant_id, null, 'create', 'Attendance', $newId,
                    "$employee_id - $punchDate",
                    "Biometric check-out (no prior check-in) via \"$device_name\" at $punchTime",
                    null,
                    ['employee_id' => $employee_id, 'check_out_time' => $punchDatetime]
                );
            } else {

                $updStmt = $pdo->prepare("
                    UPDATE attendance
                    SET check_out_time = ?
                    WHERE id = ?
                      AND tenant_id = ?
                ");
                $updStmt->execute([$punchDatetime, $existing['id'], $tenant_id]);

                logActivity($pdo, $tenant_id, null, 'update', 'Attendance', $existing['id'],
                    "$employee_id - $punchDate",
                    "Biometric check-out via \"$device_name\" at $punchTime",
                    ['check_out_time' => $existing['check_out_time']],
                    ['check_out_time' => $punchDatetime]
                );
            }

            $results[] = [
                'machine_employee_id' => $machineEmpId,
                'employee_id' => $employee_id,
                'punch_time'  => $punchDatetime,
                'punch_type'  => 'out',
                'status'      => 'processed',
            ];
            $processed++;
            logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchDatetime, $punchTypeRaw, 'processed', null);
        }

    } catch (Throwable $e) {
        error_log('Biometric push error: ' . $e->getMessage());
        $results[] = [
            'machine_employee_id' => $machineEmpId,
            'status'  => 'error',
            'message' => 'Database error — check server logs',
        ];
        $errors++;
        logPush($pdo, $device_id, $tenant_id, $machineEmpId, $punchDatetime, $punchTypeRaw, 'error', $e->getMessage());
    }
}

echo json_encode([
    'success'   => $errors === 0,
    'processed' => $processed,
    'skipped'   => $skipped,
    'errors'    => $errors,
    'results'   => $results,
]);
exit;

// ── Helper: write raw push log ────────────────────────────────────────────────

function logPush(PDO $pdo, int $deviceId, int $tenantId, string $machEmpId,
                 string $punchTime, $typeRaw, string $status, ?string $note): void
{
    try {
        $pdo->prepare("
            INSERT INTO biometric_push_log
                (device_id, tenant_id, machine_employee_id, punch_time, punch_type_raw, status, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$deviceId, $tenantId, $machEmpId, $punchTime, (string)$typeRaw, $status, $note]);
    } catch (Throwable $e) {
        error_log('Biometric push log failed: ' . $e->getMessage());
    }
}
