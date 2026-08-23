<?php
require_once '../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'] ?? null;
    $tenant_id = $_SESSION['tenant_id'] ?? null;

    if (!$user_id || !$tenant_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("
                SELECT id, heading, description, due_date, due_time, is_done, created_at
                FROM dashboard_todos
                WHERE tenant_id = ? AND user_id = ?
                ORDER BY is_done ASC, (due_date IS NULL), due_date ASC, (due_time IS NULL), due_time ASC, created_at DESC
            ");
            $stmt->execute([$tenant_id, $user_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'add':
            $input = json_decode(file_get_contents('php://input'), true);
            $heading = trim($input['heading'] ?? '');
            $description = trim($input['description'] ?? '');
            $dueDate = $input['due_date'] ?? null;
            $dueTime = $input['due_time'] ?? null;

            if ($heading === '') {
                echo json_encode(['success' => false, 'message' => 'Heading cannot be empty']);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO dashboard_todos (tenant_id, user_id, heading, description, due_date, due_time) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $user_id, $heading, $description ?: null, $dueDate ?: null, $dueTime ?: null]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'toggle':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($input['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE dashboard_todos SET is_done = NOT is_done WHERE id = ? AND tenant_id = ? AND user_id = ?");
            $stmt->execute([$id, $tenant_id, $user_id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($input['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM dashboard_todos WHERE id = ? AND tenant_id = ? AND user_id = ?");
            $stmt->execute([$id, $tenant_id, $user_id]);
            echo json_encode(['success' => true]);
            break;

        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM dashboard_todos WHERE tenant_id = ? AND user_id = ?");
            $stmt->execute([$tenant_id, $user_id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}