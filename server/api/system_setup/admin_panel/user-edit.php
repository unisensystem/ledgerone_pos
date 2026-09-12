<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $employee_id = !empty($input['employee_id']) ? $input['employee_id'] : null;

        $oldStmt = $pdo->prepare("SELECT full_name, email, is_active, employee_id FROM users WHERE id = ? AND tenant_id = ?");
        $oldStmt->execute([$input['id'], $tenant_id]);
        $oldUser = $oldStmt->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE users SET full_name = ?, email = ?, is_active = ?, employee_id = ? WHERE id = ? AND tenant_id = ?";
        $params = [$input['full_name'], $input['email'], $input['is_active'], $employee_id, $input['id'], $tenant_id];

        $passwordChanged = !empty($input['password']);
        if ($passwordChanged) {
            $sql = "UPDATE users SET full_name = ?, email = ?, is_active = ?, employee_id = ?, password_hash = ? WHERE id = ? AND tenant_id = ?";
            $params = [$input['full_name'], $input['email'], $input['is_active'], $employee_id, password_hash($input['password'], PASSWORD_DEFAULT), $input['id'], $tenant_id];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($oldUser) {
            $newUser = ['full_name' => $input['full_name'], 'email' => $input['email'], 'is_active' => $input['is_active'], 'employee_id' => $employee_id];
            $changeDescriptions = buildChangeDescription($oldUser, $newUser, [
                'full_name' => 'Name', 'email' => 'Email', 'is_active' => 'Active', 'employee_id' => 'Linked Employee'
            ]);
            if ($passwordChanged) $changeDescriptions[] = 'Password changed';
            $editDescription = !empty($changeDescriptions)
                ? "Updated user \"{$input['full_name']}\" ({$input['email']}): " . implode('; ', $changeDescriptions)
                : "Updated user \"{$input['full_name']}\" ({$input['email']}) - no tracked field changes";
            logActivity($pdo, $tenant_id, $user_id, 'update', 'User', $input['id'], $input['email'], $editDescription, $oldUser, $newUser);
        }

        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}