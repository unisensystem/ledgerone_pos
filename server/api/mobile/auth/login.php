<?php
// Mobile app login (LedgerOne Mobile). Issues a long-lived opaque bearer
// token per device instead of the web's 1-hour PHP session cookie. Does NOT
// touch $_SESSION or any session-based behavior — this is a fully separate,
// additive auth path alongside server/api/auth/login.php, which is untouched.
require_once '../../../../includes/connection.php';
require_once '../../../../includes/connection_public.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$email = trim($input['email'] ?? '');
$password = (string) ($input['password'] ?? '');
$deviceUuid = trim($input['device_uuid'] ?? '');
$deviceName = trim($input['device_name'] ?? '') ?: null;
$platform = strtolower(trim($input['platform'] ?? ''));
$pushToken = trim($input['push_token'] ?? '') ?: null;

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}
if ($deviceUuid === '') {
    echo json_encode(['success' => false, 'message' => 'device_uuid is required']);
    exit;
}
if (!in_array($platform, ['ios', 'android'], true)) {
    echo json_encode(['success' => false, 'message' => "platform must be 'ios' or 'android'"]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, tenant_id, email, password_hash, full_name, language_code, timezone, is_active
        FROM users WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    if (!$user['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Account suspended']);
        exit;
    }

    // Tenant + subscription status come from the public DB, same check the
    // web login performs — an expired/suspended tenant can't log in on
    // mobile either.
    $stmt = $pdoPublic->prepare("SELECT id, business_name, base_currency, status FROM tenants WHERE id = ?");
    $stmt->execute([$user['tenant_id']]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant || $tenant['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Account suspended']);
        exit;
    }

    $stmt = $pdoPublic->prepare("
        SELECT status, end_date, trial_ends_at, billing_type
        FROM subscriptions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$user['tenant_id']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        echo json_encode(['success' => false, 'message' => 'Please complete your subscription']);
        exit;
    }

    $today = date('Y-m-d');
    $subscriptionStatus = $subscription['status'];
    $billingType = $subscription['billing_type'];
    if ($billingType === 'one_time' || $billingType === 'lifetime') {
        if ($subscriptionStatus !== 'active' && $subscriptionStatus !== 'lifetime') {
            $subscriptionStatus = 'active';
        }
    } else {
        if ($subscription['status'] === 'trialing' && $subscription['trial_ends_at'] && $subscription['trial_ends_at'] < $today) {
            $subscriptionStatus = 'expired';
        } elseif (in_array($subscription['status'], ['active', 'expired'], true) && $subscription['end_date'] && $subscription['end_date'] < $today) {
            $subscriptionStatus = 'expired';
        }
    }
    if (in_array($subscriptionStatus, ['expired', 'canceled'], true)) {
        echo json_encode(['success' => false, 'message' => 'Subscription expired']);
        exit;
    }

    // Register/refresh this device, then issue a fresh token for it. Any
    // previously active token for this exact device is revoked first, so a
    // device only ever holds one live token at a time.
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO mobile_devices (tenant_id, user_id, device_uuid, device_name, platform, push_token, last_seen_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            device_name = VALUES(device_name),
            platform = VALUES(platform),
            push_token = VALUES(push_token),
            revoked_at = NULL,
            last_seen_at = NOW()
    ");
    $stmt->execute([$user['tenant_id'], $user['id'], $deviceUuid, $deviceName, $platform, $pushToken]);

    $stmt = $pdo->prepare("SELECT id FROM mobile_devices WHERE tenant_id = ? AND user_id = ? AND device_uuid = ?");
    $stmt->execute([$user['tenant_id'], $user['id'], $deviceUuid]);
    $deviceId = $stmt->fetchColumn();

    $pdo->prepare("UPDATE mobile_auth_tokens SET revoked_at = NOW() WHERE device_id = ? AND revoked_at IS NULL")
        ->execute([$deviceId]);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    // Sliding 90-day expiry — refreshed forward on every authenticated
    // request (see includes/mobile_auth.php), so an actively-used device
    // never gets logged out but a lost/abandoned one eventually does.
    $stmt = $pdo->prepare("
        INSERT INTO mobile_auth_tokens (tenant_id, user_id, device_id, token_hash, expires_at, last_used_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY), NOW())
    ");
    $stmt->execute([$user['tenant_id'], $user['id'], $deviceId, $tokenHash]);

    $pdo->commit();

    // Permissions, via the actual mechanism this app checks against
    // (includes/permissions.php's hasPermission()) — role_permissions keyed
    // by role_id + category + form_name + tenant_id, not the unrelated
    // "permissions" table server/api/auth/login.php's getUserPermissions()
    // references (that table doesn't exist in this schema; the web login
    // silently swallows the resulting error and always returns an empty
    // permissions array — not something worth copying here).
    $stmt = $pdo->prepare("
        SELECT rp.category, rp.form_name, rp.sub_permission, rp.allowed
        FROM role_permissions rp
        JOIN user_roles ur ON ur.role_id = rp.role_id AND ur.tenant_id = rp.tenant_id
        WHERE ur.user_id = ? AND ur.is_active = 1 AND rp.tenant_id = ? AND rp.allowed = 1
    ");
    $stmt->execute([$user['id'], $user['tenant_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, company_name AS name, company_code AS code FROM companies WHERE tenant_id = ? AND is_active = 1");
    $stmt->execute([$user['tenant_id']]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'token' => $rawToken,
        'user' => [
            'id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'language_code' => $user['language_code'],
            'timezone' => $user['timezone'],
        ],
        'tenant' => [
            'id' => $tenant['id'],
            'business_name' => $tenant['business_name'],
            'base_currency' => $tenant['base_currency'],
        ],
        'permissions' => $permissions,
        'companies' => $companies,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'debug' => $e->getMessage()]);
}
