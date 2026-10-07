<?php
/**
 * Resolves the current request's auth context (user_id, tenant_id) from
 * EITHER the existing PHP session (web) OR an `Authorization: Bearer <token>`
 * header (mobile app). Endpoints that need to serve both web and mobile call
 * this instead of reading $_SESSION directly; endpoints that are web-only
 * stay untouched. Returns null if neither auth method is present/valid.
 */
function resolveMobileAuth(PDO $pdo)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id'])) {
        return [
            'user_id' => (int) $_SESSION['user_id'],
            'tenant_id' => (int) $_SESSION['tenant_id'],
            'via' => 'session',
        ];
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
        return null;
    }

    return resolveMobileToken($pdo, trim($matches[1]));
}

/**
 * Validates a raw mobile bearer token directly against the DB, with no
 * fallback to (and no regard for) any existing PHP session — unlike
 * resolveMobileAuth(), which prefers an existing web session when present.
 * Used where a specific token must win even if the browser happens to
 * already be carrying an unrelated session cookie (e.g. print-bridge.php
 * exchanging a token for a session: on a shared/kiosk device, trusting
 * whatever session cookie already existed instead of the token the person
 * is actually presenting would authenticate the wrong person).
 */
function resolveMobileToken(PDO $pdo, string $token)
{
    if ($token === '') {
        return null;
    }

    $tokenHash = hash('sha256', $token);

    // Token must be unrevoked and unexpired; its device must still be
    // registered (not revoked from a future "manage devices" screen); and
    // the user it belongs to must still be active — so deactivating a user
    // from Admin Panel cuts off their mobile access immediately, with no
    // separate step needed to also revoke their tokens.
    $stmt = $pdo->prepare("
        SELECT t.id, t.tenant_id, t.user_id, t.device_id
        FROM mobile_auth_tokens t
        JOIN mobile_devices d ON d.id = t.device_id AND d.revoked_at IS NULL
        JOIN users u ON u.id = t.user_id AND u.is_active = 1
        WHERE t.token_hash = ?
          AND t.revoked_at IS NULL
          AND (t.expires_at IS NULL OR t.expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    // Best-effort activity timestamps + sliding expiry (pushed another 90
    // days forward on every authenticated request). Never let this block
    // the actual request.
    try {
        $pdo->prepare("UPDATE mobile_auth_tokens SET last_used_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY) WHERE id = ?")
            ->execute([$row['id']]);
        $pdo->prepare("UPDATE mobile_devices SET last_seen_at = NOW() WHERE id = ?")
            ->execute([$row['device_id']]);
    } catch (Exception $e) {
        // ignore
    }

    return [
        'user_id' => (int) $row['user_id'],
        'tenant_id' => (int) $row['tenant_id'],
        'via' => 'token',
    ];
}
