<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost($input);
        break;
    case 'PUT':
        handlePut($input);
        break;
    case 'DELETE':
        handleDelete($input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// Get all partner accounts for the current tenant, with the account name joined in
function handleGet() {
    global $pdo, $tenant_id;

    try {
        $stmt = $pdo->prepare("SELECT pa.id, pa.account_id, pa.percentage, a.name AS account_name
                                FROM partner_accounts pa
                                INNER JOIN accounts a ON a.id = pa.account_id
                                WHERE pa.tenant_id = ?
                                ORDER BY pa.id ASC");
        $stmt->execute([$tenant_id]);
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $partners]);
    } catch (Exception $e) {
        error_log("Error in handleGet (partner-accounts): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Add one or more partner accounts at once
// Expected payload: { "partners": [ { "account_id": 5, "percentage": 25.5 }, ... ] }
function handlePost($input) {
    global $pdo, $tenant_id;

    $partners = $input['partners'] ?? [];

    if (empty($partners) || !is_array($partners)) {
        echo json_encode(['success' => false, 'message' => 'No partner data provided']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO partner_accounts (tenant_id, account_id, percentage) VALUES (?, ?, ?)");

        $insertedCount = 0;
        foreach ($partners as $partner) {
            $account_id = $partner['account_id'] ?? 0;
            $percentage = $partner['percentage'] ?? null;

            if (!$account_id || $percentage === null || $percentage === '') {
                continue;
            }

            $stmt->execute([$tenant_id, $account_id, $percentage]);
            $insertedCount++;
        }

        if ($insertedCount === 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'No valid partner rows to save']);
            return;
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Partner(s) added successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in handlePost (partner-accounts): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error adding partner(s): ' . $e->getMessage()]);
    }
}

// Update the percentage of an existing partner account row
function handlePut($input) {
    global $pdo, $tenant_id;

    $id = $input['id'] ?? 0;
    $percentage = $input['percentage'] ?? null;

    if (!$id || $percentage === null || $percentage === '') {
        echo json_encode(['success' => false, 'message' => 'ID and percentage are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE partner_accounts SET percentage = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$percentage, $id, $tenant_id]);

        echo json_encode(['success' => true, 'message' => 'Partner updated successfully']);
    } catch (Exception $e) {
        error_log("Error in handlePut (partner-accounts): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating partner: ' . $e->getMessage()]);
    }
}

// Remove a partner account row
function handleDelete($input) {
    global $pdo, $tenant_id;

    $id = $input['id'] ?? 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM partner_accounts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        echo json_encode(['success' => true, 'message' => 'Partner deleted successfully']);
    } catch (Exception $e) {
        error_log("Error in handleDelete (partner-accounts): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting partner: ' . $e->getMessage()]);
    }
}
?>