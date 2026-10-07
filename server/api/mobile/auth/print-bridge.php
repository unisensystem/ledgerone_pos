<?php
// Lets the mobile app reuse web's EXACT print pages (invoice-print.php /
// thermal-print.php) instead of building a second print layout to maintain.
// Those pages' own JS calls session-authenticated endpoints with no bearer
// token support — this bridge exchanges a short-lived, single-use nonce
// (from print-nonce.php) for a real PHP session, then redirects into the
// real print page, which then "just works" unmodified.
//
// Takes a nonce, not the mobile bearer token itself — the token is
// long-lived (90-day sliding expiry), and this URL is a plain browser
// navigation (server access logs, browser history, and any Referer header
// sent to a subresource the print page loads would all have captured a raw
// token). The nonce is single-use and expires in 60s, so none of that
// matters if it leaks the same way.
//
// $id/$type are the only redirect inputs accepted (not a free-form URL), so
// this can't be used as an open redirect.
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Referrer-Policy: no-referrer');

$nonce = trim($_GET['nonce'] ?? '');
if ($nonce === '') {
    http_response_code(400);
    echo 'Missing nonce';
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$type = ($_GET['type'] ?? 'full') === 'thermal' ? 'thermal' : 'full';
if ($id <= 0) {
    http_response_code(400);
    echo 'Missing order id';
    exit;
}

$nonceHash = hash('sha256', $nonce);

try {
    $pdo->beginTransaction();

    // Lock the row first so two near-simultaneous requests with the same
    // nonce can't both pass the validity check before either marks it used.
    $stmt = $pdo->prepare("
        SELECT id, tenant_id, user_id, order_id
        FROM mobile_print_nonces
        WHERE nonce_hash = ? AND used_at IS NULL AND expires_at > NOW()
        FOR UPDATE
    ");
    $stmt->execute([$nonceHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int) $row['order_id'] !== $id) {
        $pdo->rollBack();
        http_response_code(401);
        echo 'This print link has expired — please tap Print again.';
        exit;
    }

    $pdo->prepare("UPDATE mobile_print_nonces SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Server error';
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Regenerate (not reuse) the session id so any stale session data for this
// browser — e.g. a different user's old web login still sitting in a
// cookie on a shared/kiosk device — is discarded, not merged with this
// nonce's identity.
session_regenerate_id(true);
session_unset();
$_SESSION['user_id'] = (int) $row['user_id'];
$_SESSION['tenant_id'] = (int) $row['tenant_id'];

$page = $type === 'thermal' ? 'thermal-print.php' : 'invoice-print.php';
// Relative, not absolute — avoids hardcoding the app's install path/domain,
// matching how the rest of the app redirects (see order-add.js's
// window.location.href = 'order-list.php').
header('Location: ../../../../client/pages/sale/sale_order/' . $page . '?id=' . $id);
exit;
