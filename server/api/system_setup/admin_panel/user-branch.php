<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$current_user_id = $_SESSION['user_id'] ?? null;
$tenant_id       = $_SESSION['tenant_id'] ?? null;

if (!$current_user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ─── GET: fetch branch access for a user ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'user_id is required']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT ub.branch_id, b.branch_code, b.branch_name, b.branch_type,
                   pb.branch_name AS parent_branch_name
            FROM user_branches ub
            JOIN branches b  ON ub.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            WHERE ub.user_id = ? AND ub.tenant_id = ?
        ");
        $stmt->execute([$user_id, $tenant_id]);
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'branches' => $branches]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── POST: save (replace) branch access for a user ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id    = $data['user_id']    ?? null;
    $branch_ids = $data['branch_ids'] ?? [];   // array of branch IDs

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'user_id is required']);
        exit;
    }

    try {
        // Snapshot old branch access + target user info for the Activity Log
        $oldStmt = $pdo->prepare("SELECT branch_id FROM user_branches WHERE user_id = ? AND tenant_id = ? ORDER BY branch_id");
        $oldStmt->execute([$user_id, $tenant_id]);
        $oldBranchIds = array_map('intval', $oldStmt->fetchAll(PDO::FETCH_COLUMN));

        $targetStmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ? AND tenant_id = ?");
        $targetStmt->execute([$user_id, $tenant_id]);
        $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        // Remove existing branch access for this user
        $del = $pdo->prepare("DELETE FROM user_branches WHERE user_id = ? AND tenant_id = ?");
        $del->execute([$user_id, $tenant_id]);

        // Insert new branch access rows
        if (!empty($branch_ids)) {
            $ins = $pdo->prepare("INSERT INTO user_branches (tenant_id, user_id, branch_id) VALUES (?, ?, ?)");
            foreach ($branch_ids as $branch_id) {
                $ins->execute([$tenant_id, $user_id, (int)$branch_id]);
            }
        }

        $newBranchIds = array_values(array_unique(array_map('intval', $branch_ids)));
        sort($newBranchIds);
        $branchesChanged = $oldBranchIds !== $newBranchIds;

        if ($targetUser) {
            $editDescription = $branchesChanged
                ? "Updated branch access for user \"{$targetUser['full_name']}\" ({$targetUser['email']}): [" . implode(',', $oldBranchIds) . "] \u{2192} [" . implode(',', $newBranchIds) . "]"
                : "Updated branch access for user \"{$targetUser['full_name']}\" ({$targetUser['email']}) - no tracked field changes";
            logActivity($pdo, $tenant_id, $current_user_id, 'update', 'User', $user_id, $targetUser['email'], $editDescription, ['branch_ids' => $oldBranchIds], ['branch_ids' => $newBranchIds]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Branch access updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
