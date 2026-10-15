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
// Prevents any logged-in tenant user from pulling another specific
// employee's movement history by guessing/enumerating user_id (IDOR).
if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$officerUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$officerUserId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'user_id and a valid date (YYYY-MM-DD) are required']);
    exit;
}

/** Haversine distance in meters between two lat/lng points. */
function otDistanceMeters($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

try {
    $stmt = $pdo->prepare("
        SELECT latitude, longitude, accuracy_meters, recorded_at
        FROM officer_location_pings
        WHERE tenant_id = ? AND user_id = ? AND DATE(recorded_at) = ?
        ORDER BY recorded_at ASC
    ");
    $stmt->execute([$tenant_id, $officerUserId, $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'accuracy_meters' => $row['accuracy_meters'] !== null ? (float) $row['accuracy_meters'] : null,
            'recorded_at' => $row['recorded_at'],
        ];
    }, $rows);

    // Distance travelled + a rough "stop" count — a run of 3+ consecutive
    // pings each under 30m from the last counts as one stop (roughly a
    // minute or more stationary at the normal 30s ping interval), so brief
    // red-light pauses while driving don't get counted as stops.
    $distanceMeters = 0;
    $stops = 0;
    $stationaryRun = 0;
    for ($i = 1; $i < count($rows); $i++) {
        $d = otDistanceMeters($rows[$i - 1]['latitude'], $rows[$i - 1]['longitude'], $rows[$i]['latitude'], $rows[$i]['longitude']);
        $distanceMeters += $d;
        if ($d < 30) {
            $stationaryRun++;
            if ($stationaryRun === 3) $stops++;
        } else {
            $stationaryRun = 0;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'summary' => [
            'distance_km' => round($distanceMeters / 1000, 2),
            'stops' => $stops,
            'first_seen' => $rows[0]['recorded_at'] ?? null,
            'last_seen' => $rows[count($rows) - 1]['recorded_at'] ?? null,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
