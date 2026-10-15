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

try {
    $sql = "
        SELECT p.user_id, u.full_name, u.email, DATE(p.recorded_at) AS ping_date,
               p.latitude, p.longitude, p.recorded_at
        FROM officer_location_pings p
        LEFT JOIN users u ON u.id = p.user_id AND u.tenant_id = p.tenant_id
        WHERE p.tenant_id = ? AND DATE(p.recorded_at) BETWEEN ? AND ?
    ";
    $params = [$tenant_id, $fromDate, $toDate];
    if ($filterUserId) {
        $sql .= " AND p.user_id = ?";
        $params[] = $filterUserId;
    }
    $sql .= " ORDER BY p.user_id, p.recorded_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by user + day, computing distance/stops the same way the
    // single-officer route view does, just once per (user, date) bucket.
    $groups = [];
    foreach ($rows as $row) {
        $key = $row['user_id'] . '|' . $row['ping_date'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'user_id' => (int) $row['user_id'],
                'name' => $row['full_name'] ?: $row['email'],
                'date' => $row['ping_date'],
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
            'first_seen' => $points[0]['at'],
            'last_seen' => $points[count($points) - 1]['at'],
            'ping_count' => count($points),
        ];
    }

    usort($data, fn($a, $b) => $a['date'] === $b['date'] ? strcmp($a['name'], $b['name']) : strcmp($b['date'], $a['date']));

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
