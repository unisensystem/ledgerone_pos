<?php
// Place this file at: server/api/shortcuts/shortcuts.php
require_once '../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT id, key_combo, page_key, page_label FROM user_shortcuts WHERE user_id = ? AND tenant_id = ? ORDER BY id ASC");
        $stmt->execute([$user_id, $tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    } elseif ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $keyCombo = strtolower(trim($input['key_combo'] ?? ''));
        $pageKey = trim($input['page_key'] ?? '');
        $pageLabel = trim($input['page_label'] ?? '');

        if (!$keyCombo || !$pageKey || !$pageLabel) {
            echo json_encode(['success' => false, 'message' => 'Missing key combo or page']);
            exit();
        }

        // One page can only have one shortcut per user; one key combo can only map to one page per user
        $stmt = $pdo->prepare("DELETE FROM user_shortcuts WHERE user_id = ? AND tenant_id = ? AND (key_combo = ? OR page_key = ?)");
        $stmt->execute([$user_id, $tenant_id, $keyCombo, $pageKey]);

        $stmt = $pdo->prepare("INSERT INTO user_shortcuts (tenant_id, user_id, key_combo, page_key, page_label) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $user_id, $keyCombo, $pageKey, $pageLabel]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($input['id'] ?? 0);

        $stmt = $pdo->prepare("DELETE FROM user_shortcuts WHERE id = ? AND user_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $user_id, $tenant_id]);

        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}