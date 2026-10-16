<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/role-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-6 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int) $_GET['user_id'] : null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'from_date/to_date must be YYYY-MM-DD']);
    exit;
}

/** Haversine distance in meters between two lat/lng points. */
function otrDistanceMeters($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Same tenant timezone the dashboard clock uses — see get-live-locations.php. */
function otrTenantTimezone($pdo, $tenantId)
{
    $stmt = $pdo->prepare("SELECT timezone FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tenantId]);
    $tz = $stmt->fetchColumn();
    return $tz ?: 'UTC';
}

function otrToIso($mysqlDateTime)
{
    return $mysqlDateTime ? str_replace(' ', 'T', $mysqlDateTime) . 'Z' : null;
}

try {
    $tenantTz = new DateTimeZone(otrTenantTimezone($pdo, $tenant_id));
    $utc = new DateTimeZone('UTC');

    // Convert the requested tenant-calendar date range into a UTC bound —
    // `DATE(p.recorded_at) BETWEEN ? AND ?` would compare against the DB
    // server's own system date (UTC on the production VPS), not the
    // tenant's, which both shifts which pings are included near midnight
    // and — more visibly — mis-groups them into the wrong calendar day.
    $rangeStart = new DateTime($fromDate, $tenantTz);
    $rangeStart->setTimezone($utc);
    $rangeEnd = new DateTime($toDate, $tenantTz);
    $rangeEnd->modify('+1 day');
    $rangeEnd->setTimezone($utc);

    $sql = "
        SELECT p.user_id, u.full_name, u.email, p.latitude, p.longitude, p.recorded_at
        FROM officer_location_pings p
        LEFT JOIN users u ON u.id = p.user_id AND u.tenant_id = p.tenant_id
        WHERE p.tenant_id = ? AND p.recorded_at >= ? AND p.recorded_at < ?
    ";
    $params = [$tenant_id, $rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s')];
    if ($filterUserId) {
        $sql .= " AND p.user_id = ?";
        $params[] = $filterUserId;
    }
    $sql .= " ORDER BY p.user_id, p.recorded_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by user + tenant-calendar day, computing distance/stops the
    // same way the single-officer route view does, just once per
    // (user, date) bucket. The day each ping belongs to is derived by
    // converting its UTC recorded_at into the tenant's own timezone —
    // not a SQL DATE(), for the same reason as the query bound above.
    $groups = [];
    foreach ($rows as $row) {
        $recordedAtUtc = new DateTime($row['recorded_at'], $utc);
        $recordedAtUtc->setTimezone($tenantTz);
        $pingDate = $recordedAtUtc->format('Y-m-d');

        $key = $row['user_id'] . '|' . $pingDate;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'user_id' => (int) $row['user_id'],
                'name' => $row['full_name'] ?: $row['email'],
                'date' => $pingDate,
                'points' => [],
            ];
        }
        $groups[$key]['points'][] = ['lat' => (float) $row['latitude'], 'lng' => (float) $row['longitude'], 'at' => $row['recorded_at']];
    }

    $data = [];
    foreach ($groups as $g) {
        $points = $g['points'];
        $distanceMeters = 0;
        $stops = 0;
        $stationaryRun = 0;
        for ($i = 1; $i < count($points); $i++) {
            $d = otrDistanceMeters($points[$i - 1]['lat'], $points[$i - 1]['lng'], $points[$i]['lat'], $points[$i]['lng']);
            $distanceMeters += $d;
            if ($d < 30) {
                $stationaryRun++;
                if ($stationaryRun === 3) $stops++;
            } else {
                $stationaryRun = 0;
            }
        }

        $data[] = [
            'user_id' => $g['user_id'],
            'name' => $g['name'],
            'date' => $g['date'],
            'distance_km' => round($distanceMeters / 1000, 2),
            'stops' => $stops,
            'first_seen' => otrToIso($points[0]['at']),
            'last_seen' => otrToIso($points[count($points) - 1]['at']),
            'ping_count' => count($points),
        ];
    }

    usort($data, fn($a, $b) => $a['date'] === $b['date'] ? strcmp($a['name'], $b['name']) : strcmp($b['date'], $a['date']));

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
