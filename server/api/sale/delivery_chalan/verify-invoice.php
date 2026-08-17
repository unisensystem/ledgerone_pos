<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$invoiceNo = $_GET['invoice'] ?? null;
$token = $_GET['token'] ?? null;

if (!$invoiceNo || !$token) {
    echo json_encode(['success' => false, 'message' => 'Invoice number and token are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            si.bill_no,
            si.sale_date,
            si.net_amount,
            si.status,
            c.customer_name,
            b.branch_name,
            cur.symbol as currency_symbol
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN branches b ON si.branch_id = b.id
        LEFT JOIN fuelingsys_public.currencies cur ON si.currency_id = cur.id
        WHERE si.bill_no = ? AND si.status = 'Posted'
    ");
    $stmt->execute([$invoiceNo]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        // Verify token
        $expectedToken = substr(md5($invoice['bill_no'] . 'LEDGERONE_SECRET_KEY'), 0, 16);
        
        if ($token !== $expectedToken) {
            echo json_encode(['success' => false, 'message' => 'Invalid verification token']);
            exit;
        }
        
        echo json_encode(['success' => true, 'invoice' => $invoice]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invoice not found or not posted']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error verifying invoice']);
}
