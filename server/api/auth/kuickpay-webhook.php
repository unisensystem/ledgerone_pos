<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_PUBLIC', $_ENV['DB_PUBLIC'] ?? 'ledgerone_public');

// KuickPay configuration
define('KUICKPAY_SECRET_KEY', $_ENV['KUICKPAY_SECRET_KEY'] ?? 'YOUR_SECRET_KEY');

// Get webhook payload
$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

// Log webhook
error_log('KuickPay Webhook: ' . $rawPayload);

// Verify signature
$receivedSignature = $_SERVER['HTTP_X_KUICKPAY_SIGNATURE'] ?? '';
$calculatedSignature = hash_hmac('sha256', $rawPayload, KUICKPAY_SECRET_KEY);

if ($receivedSignature !== $calculatedSignature) {
    error_log('Invalid webhook signature');
    http_response_code(401);
    exit('Invalid signature');
}

// Process payment
if ($payload['event'] === 'payment.success') {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_PUBLIC . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $orderId = $payload['order_id'];
        $transactionId = $payload['transaction_id'];
        $amount = $payload['amount'];
        $status = $payload['status'];

        // Extract tenant_id from order_id (format: SUB-{tenant_id}-{timestamp})
        preg_match('/SUB-(\d+)-/', $orderId, $matches);
        $tenantId = $matches[1] ?? null;

        if (!$tenantId) {
            error_log('Invalid order_id format: ' . $orderId);
            http_response_code(400);
            exit('Invalid order_id');
        }

        // Update subscription
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = 'active',
                payment_status = 'completed',
                start_date = CURDATE(),
                end_date = DATE_ADD(CURDATE(), INTERVAL 
                    CASE WHEN billing_cycle = 'yearly' THEN 1 YEAR ELSE 1 MONTH END),
                payment_details = JSON_SET(
                    COALESCE(payment_details, '{}'),
                    '$.transaction_id', ?,
                    '$.paid_at', NOW()
                ),
                updated_at = NOW()
            WHERE tenant_id = ? 
            AND payment_status = 'pending'
            AND payment_method = 'kuickpay'
        ");
        
        $stmt->execute([$transactionId, $tenantId]);

        error_log("Subscription activated for tenant: $tenantId");
        http_response_code(200);
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log('Webhook processing error: ' . $e->getMessage());
        http_response_code(500);
        exit('Processing error');
    }
} else {
    error_log('Unhandled webhook event: ' . ($payload['event'] ?? 'unknown'));
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Event ignored']);
}
