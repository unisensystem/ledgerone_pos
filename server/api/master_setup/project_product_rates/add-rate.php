<?php
header('Content-Type: application/json');

session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$tenant_id) {
        throw new Exception('Tenant ID not found in session');
    }

    // Handle both single rate and multiple rates
    $rates = [];
    
    if (isset($data['rates']) && is_array($data['rates'])) {
        // Multiple rates
        $rates = $data['rates'];
    } elseif (isset($data['project_id']) && isset($data['product_id']) && isset($data['rate'])) {
        // Single rate
        $rates = [$data];
    } else {
        throw new Exception('Missing required fields: rates or (project_id, product_id, rate)');
    }

    if (empty($rates)) {
        throw new Exception('No rates provided');
    }

    // Validate all rates
    foreach ($rates as $index => $rate) {
        if (!isset($rate['project_id']) || !isset($rate['product_id']) || !isset($rate['rate'])) {
            throw new Exception("Missing required fields in rate at index $index");
        }
        
        if (!is_numeric($rate['rate']) || $rate['rate'] < 0) {
            throw new Exception("Invalid rate value at index $index");
        }
    }

    // Start transaction
    $pdo->beginTransaction();

    $addedCount = 0;
    $errors = [];
    $insertedIds = [];

    foreach ($rates as $index => $rate) {
        $project_id = $rate['project_id'];
        $product_id = $rate['product_id'];
        $rate_value = (float)$rate['rate'];

        try {
            // Check if rate already exists for this project-product combination
            $checkQuery = "SELECT id FROM project_product_rates WHERE project_id = ? AND product_id = ? AND tenant_id = ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$project_id, $product_id, $tenant_id]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Row " . ($index + 1) . ": A rate already exists for this project-product combination";
                continue;
            }

            // Insert the rate
            $query = "INSERT INTO project_product_rates (tenant_id, project_id, product_id, rate, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($query);
            
            if ($stmt->execute([$tenant_id, $project_id, $product_id, $rate_value])) {
                $addedCount++;
                $newId = $pdo->lastInsertId();
                $insertedIds[] = $newId;
                logActivity(
                    $pdo, $tenant_id, $user_id, 'create', 'Project Product Rate', $newId, "Project #$project_id / Product #$product_id",
                    "Created project product rate: Project #$project_id, Product #$product_id, Rate " . number_format($rate_value, 2),
                    null,
                    ['project_id' => $project_id, 'product_id' => $product_id, 'rate' => $rate_value]
                );
            } else {
                $errors[] = "Row " . ($index + 1) . ": Failed to insert rate";
            }

        } catch (Exception $e) {
            $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
        }
    }

    // Commit transaction if at least one record was added
    if ($addedCount > 0) {
        $pdo->commit();
        
        $message = "Successfully added $addedCount rate(s)";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode("; ", $errors);
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'added' => $addedCount,
            'ids' => $insertedIds,
            'errors' => $errors
        ]);
    } else {
        // Rollback if no records were added
        $pdo->rollBack();
        
        throw new Exception('No rates were added. ' . implode('; ', $errors));
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
