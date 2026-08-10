<?php
require_once '../../../../includes/connection.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.business_name,
            t.subdomain,
            t.owner_user_id,
            t.contact_email,
            t.contact_phone,
            s.billing_cycle,
            s.monthly_price,
            s.annual_price,
            s.currency,
            s.start_date,
            s.end_date,
            s.license_key,
            p.name as plan_name
        FROM ledgerone_public.tenants t
        LEFT JOIN ledgerone_public.subscriptions s ON t.id = s.tenant_id AND s.status = 'active'
        LEFT JOIN ledgerone_public.plans p ON s.plan_id = p.id
        WHERE t.id = ?
    ");
    
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch();
    
    if ($row) {
        $subscription_type = ($row['annual_price'] > 0) ? 'Yearly' : (($row['monthly_price'] > 0) ? 'Monthly' : 'Lifetime');
        $amount = ($row['annual_price'] > 0) ? $row['annual_price'] : $row['monthly_price'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'tenant_id' => $row['id'],
                'business_name' => $row['business_name'],
                'domain' => 'https://' . $row['subdomain'] . '.innova-tech.link',
                'owner_user_id' => $row['owner_user_id'],
                'contact_email' => $row['contact_email'],
                'contact_phone' => $row['contact_phone'],
                'license_key' => $row['license_key'] ?? 'N/A',
                'subscription_type' => $subscription_type,
                'subscription_amount' => number_format($amount ?? 0, 2),
                'start_date' => $row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : 'N/A',
                'end_date' => $subscription_type === 'Lifetime' ? '' : ($row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : 'N/A'),
                'plan_name' => $row['plan_name'] ?? 'N/A'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tenant not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}