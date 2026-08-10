<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    require_once '../../../../includes/connection.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $tenant_id = $data['tenant_id'] ?? null;
    $plan_id = $data['plan_id'] ?? null;
    $billing_cycle = $data['billing_cycle'] ?? 'monthly';
    $amount = $data['amount'] ?? null;
    $start_date = $data['start_date'] ?? date('Y-m-d');
    $end_date = $data['end_date'] ?? null;
    $status = $data['status'] ?? 'active';
    $license_key = $data['license_key'] ?? null;
    
    if (empty($tenant_id) || empty($plan_id) || empty($amount) || empty($license_key)) {
        echo json_encode(['success' => false, 'message' => 'Tenant, Plan, Amount and License Key are required']);
        exit;
    }
    
    // Get plan pricing for reference
    $planStmt = $pdo->prepare("SELECT monthly_price, annual_price FROM fuelingsys_public.plans WHERE id = ?");
    $planStmt->execute([$plan_id]);
    $plan = $planStmt->fetch();
    
    if (!$plan) {
        echo json_encode(['success' => false, 'message' => 'Plan not found']);
        exit;
    }
    
    // Use provided amount or fall back to plan pricing
    $monthly_price = $billing_cycle === 'monthly' ? $amount : $plan['monthly_price'];
    $annual_price = $billing_cycle === 'annual' ? $amount : $plan['annual_price'];
    
    // Calculate end date and next billing date
    if (!$end_date) {
        $end_date = $billing_cycle === 'annual' 
            ? date('Y-m-d', strtotime($start_date . ' +1 year'))
            : date('Y-m-d', strtotime($start_date . ' +1 month'));
    }
    
    $next_billing_date = $end_date;
    
    $stmt = $pdo->prepare("INSERT INTO fuelingsys_public.subscriptions 
        (tenant_id, plan_id, monthly_price, annual_price, billing_cycle, start_date, end_date, next_billing_date, status, license_key) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$tenant_id, $plan_id, $monthly_price, $annual_price, $billing_cycle, $start_date, $end_date, $next_billing_date, $status, $license_key])) {
        echo json_encode(['success' => true, 'message' => 'Subscription added successfully', 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add subscription']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
