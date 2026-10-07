<?php
// TEMPORARY diagnostic — deleted immediately after use. Catches fatal
// errors (not just Exceptions) via a shutdown handler so a require_once
// failure on production (where display_errors is off) is actually visible.
header('Content-Type: application/json');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(200);
        echo json_encode(['fatal' => true, 'error' => $error]);
    }
});

$result = ['steps' => []];

try {
    require_once '../../../../includes/connection.php';
    $result['steps'][] = 'connection.php OK, pdo=' . (isset($pdo) ? 'set' : 'MISSING');
} catch (Throwable $e) {
    $result['steps'][] = 'connection.php THREW: ' . $e->getMessage();
}

try {
    require_once '../../../../includes/connection_public.php';
    $result['steps'][] = 'connection_public.php OK, pdoPublic=' . (isset($pdoPublic) ? 'set' : 'MISSING');
} catch (Throwable $e) {
    $result['steps'][] = 'connection_public.php THREW: ' . $e->getMessage();
}

if (isset($pdo)) {
    try {
        $pdo->query('SELECT 1 FROM mobile_devices LIMIT 1');
        $result['steps'][] = 'mobile_devices table OK';
    } catch (Throwable $e) {
        $result['steps'][] = 'mobile_devices query THREW: ' . $e->getMessage();
    }
    try {
        $pdo->query('SELECT 1 FROM mobile_auth_tokens LIMIT 1');
        $result['steps'][] = 'mobile_auth_tokens table OK';
    } catch (Throwable $e) {
        $result['steps'][] = 'mobile_auth_tokens query THREW: ' . $e->getMessage();
    }
}

if (isset($pdoPublic)) {
    try {
        $pdoPublic->query('SELECT 1 FROM tenants LIMIT 1');
        $result['steps'][] = 'pdoPublic tenants query OK';
    } catch (Throwable $e) {
        $result['steps'][] = 'pdoPublic tenants query THREW: ' . $e->getMessage();
    }
}

echo json_encode($result);
