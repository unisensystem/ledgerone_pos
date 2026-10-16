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
// Employee GPS location is sensitive — admin-only, not just "logged in".
if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
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

/**
 * Same tenant timezone the dashboard clock uses (companies.timezone, see
 * server/api/get_company_info.php) — reused here rather than assuming
 * Asia/Karachi, so "today" always means the tenant's own calendar day, not
 * the DB/PHP server's.
 */
function otTenantTimezone($pdo, $tenantId)
{
    $stmt = $pdo->prepare("SELECT timezone FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tenantId]);
    $tz = $stmt->fetchColumn();
    return $tz ?: 'UTC';
}

/** recorded_at is stored as UTC (see ping.php) — mark it explicitly so the
 * browser's own Date/toLocaleString conversion (same approach the
 * dashboard clock uses) displays it correctly instead of assuming it's
 * already in the viewer's local time. */
function otToIso($mysqlDateTime)
{
    return str_replace(' ', 'T', $mysqlDateTime) . 'Z';
}

try {
    $tenantTz = new DateTimeZone(otTenantTimezone($pdo, $tenant_id));
    $utc = new DateTimeZone('UTC');

    // "Today" in the tenant's own timezone, converted to a UTC boundary for
    // the query — a plain `recorded_at >= CURDATE()` compares against the
    // DB server's own system date, which is wrong whenever that server
    // doesn't happen to be running in the tenant's timezone (it isn't, on
    // the production VPS — that box runs UTC while tenants are PKT).
    $todayStart = new DateTime('today', $tenantTz);
    $todayStart->setTimezone($utc);
    $todayStartUtc = $todayStart->format('Y-m-d H:i:s');

    // Pull every ping from today for the whole tenant in one go and group
    // in PHP — simpler than a per-user correlated subquery, and gives us
    // movement status + today's distance from the exact same rows instead
    // of a second query/endpoint.
    $stmt = $pdo->prepare("
        SELECT user_id, latitude, longitude, recorded_at
        FROM officer_location_pings
        WHERE tenant_id = ? AND recorded_at >= ?
        ORDER BY user_id, recorded_at ASC
    ");
    $stmt->execute([$tenant_id, $todayStartUtc]);
    $allPings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byUser = [];
    foreach ($allPings as $p) {
        $byUser[$p['user_id']][] = $p;
    }

    if (empty($byUser)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $userIds = array_keys($byUser);
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $nameStmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id IN ($placeholders)");
    $nameStmt->execute($userIds);
    $names = [];
    foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $names[$u['id']] = $u['full_name'] ?: $u['email'];
    }

    $settingsStmt = $pdo->prepare("
        SELECT user_id, admin_enabled, officer_enabled, last_status, last_status_at
        FROM officer_tracking_settings
        WHERE tenant_id = ? AND user_id IN ($placeholders)
    ");
    $settingsStmt->execute(array_merge([$tenant_id], $userIds));
    $settings = [];
    foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $settings[$s['user_id']] = $s;
    }

    $now = new DateTime('now', $utc);
    $data = [];

    foreach ($byUser as $uid => $pings) {
        $last = end($pings);

        $distanceMeters = 0;
        for ($i = 1; $i < count($pings); $i++) {
            $distanceMeters += otDistanceMeters(
                $pings[$i - 1]['latitude'], $pings[$i - 1]['longitude'],
                $pings[$i]['latitude'], $pings[$i]['longitude']
            );
        }

        // Moving vs stopped: look at cumulative movement over the last few
        // pings (~2 minutes at the normal 30s interval), not just the very
        // last pair. Comparing only the last two pings made a single quiet
        // 30s segment — a red light, a brief pause mid-route — read as
        // "stopped" even on an otherwise clearly-moving trip; a short
        // rolling window smooths that out while still catching someone
        // who's been genuinely stationary for a couple of minutes.
        $windowSize = min(5, count($pings));
        $window = array_slice($pings, -$windowSize);
        $windowMeters = 0;
        for ($i = 1; $i < count($window); $i++) {
            $windowMeters += otDistanceMeters(
                $window[$i - 1]['latitude'], $window[$i - 1]['longitude'],
                $window[$i]['latitude'], $window[$i]['longitude']
            );
        }
        $isStationary = count($window) < 2 || $windowMeters < 50;

        $s = $settings[$uid] ?? ['admin_enabled' => 1, 'officer_enabled' => 1, 'last_status' => null, 'last_status_at' => null];
        $lastSeen = new DateTime($last['recorded_at'], $utc);
        $minutesSinceLastPing = ($now->getTimestamp() - $lastSeen->getTimestamp()) / 60;

        // Precedence: admin/officer off > a reported device problem (only
        // if recent — an hour-old "GPS was off" shouldn't haunt the status
        // forever) > no recent ping at all > barely moved > moving.
        if (!$s['admin_enabled']) {
            $status = 'disabled_by_admin';
        } elseif (!$s['officer_enabled']) {
            $status = 'paused_by_officer';
        } elseif ($s['last_status'] && $s['last_status_at'] &&
                  (($now->getTimestamp() - (new DateTime($s['last_status_at'], $utc))->getTimestamp()) / 60) < 10) {
            $status = $s['last_status']; // 'gps_off' | 'permission_denied'
        } elseif ($minutesSinceLastPing > 5) {
            $status = 'offline';
        } elseif ($isStationary) {
            $status = 'stopped';
        } else {
            $status = 'moving';
        }

        $data[] = [
            'user_id' => (int) $uid,
            'name' => $names[$uid] ?? 'Unknown',
            'latitude' => (float) $last['latitude'],
            'longitude' => (float) $last['longitude'],
            'recorded_at' => otToIso($last['recorded_at']),
            'status' => $status,
            'distance_km_today' => round($distanceMeters / 1000, 2),
        ];
    }

    // Most recently active first.
    usort($data, fn($a, $b) => strcmp($b['recorded_at'], $a['recorded_at']));

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
