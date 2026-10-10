<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/check_permission.php';

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!hasPermission($pdo, $user_id, 'System Setup', 'Admin Panel', 'View')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

        $target = (int)($_GET['user_id'] ?? 0);
        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'user_id required']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT uc.counter_id
            FROM user_counters uc
            JOIN users u ON u.id = uc.user_id
            WHERE uc.user_id = ? AND u.tenant_id = ?
        ");
        $stmt->execute([$target, $tenant_id]);
        echo json_encode(['success' => true, 'counters' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hasPermission($pdo, $user_id, 'System Setup', 'Admin Panel', 'Edit')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $target = (int)($input['user_id'] ?? 0);
        $requestedCounterIds = array_unique(array_map('intval', $input['counter_ids'] ?? []));

        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'user_id required']);
            exit;
        }

        // user must belong to this tenant
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
        $chk->execute([$target, $tenant_id]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // Only accept counter IDs that actually belong to this tenant — otherwise a
        // crafted request could assign another tenant's counter_id to this user.
        $counterIds = [];
        if (!empty($requestedCounterIds)) {
            $placeholders = implode(',', array_fill(0, count($requestedCounterIds), '?'));
            $validStmt = $pdo->prepare("SELECT id FROM counters WHERE tenant_id = ? AND id IN ({$placeholders})");
            $validStmt->execute(array_merge([$tenant_id], $requestedCounterIds));
            $counterIds = $validStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM user_counters WHERE user_id = ?")->execute([$target]);

        if (!empty($counterIds)) {
            $ins = $pdo->prepare("INSERT INTO user_counters (user_id, counter_id) VALUES (?, ?)");
            foreach ($counterIds as $cid) {
                $ins->execute([$target, $cid]);
            }
        }
        $pdo->commit();

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('User Counter Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'debug' => $e->getMessage()]);
}