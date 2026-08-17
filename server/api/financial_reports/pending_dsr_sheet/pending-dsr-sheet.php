<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

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

$companyId = $_GET['company_id'] ?? null;

try {
    $params = [$tenant_id];
    $companyFilter = '';
    
    if ($companyId) {
        $companyFilter = ' AND rv.company_id = ?';
        $params[] = $companyId;
    }
    
    $sql = "SELECT 
                rv.dsr_no as reference,
                e.full_name as salesOfficer,
                SUM(si.net_amount) as sheetAmount,
                SUM(rv.amount) as cashRecovery
            FROM receive_voucher rv
            LEFT JOIN sale_invoice si ON rv.bill_no = si.bill_no AND rv.tenant_id = si.tenant_id" . ($companyId ? " AND si.company_id = ?" : "") . "
            LEFT JOIN employees e ON si.sale_officer_id = e.id AND rv.tenant_id = e.tenant_id
            WHERE rv.tenant_id = ?{$companyFilter} AND rv.dsr_no IS NOT NULL AND rv.dsr_no != '' AND rv.payment_method_id != 6
            GROUP BY rv.dsr_no, e.full_name
            ORDER BY rv.dsr_no DESC";
    
    if ($companyId) {
        array_splice($params, 1, 0, [$companyId]);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get PDC amounts for all DSR numbers
    $pdcParams = [$tenant_id];
    $pdcCompanyFilter = '';
    
    if ($companyId) {
        $pdcCompanyFilter = ' AND rv.company_id = ? AND pdc.company_id = ?';
        $pdcParams[] = $companyId;
        $pdcParams[] = $companyId;
    }
    
    $pdcSql = "SELECT rv.dsr_no, SUM(pdc.amount) as pdcAmount
               FROM receive_voucher rv
               JOIN post_dated_cheques pdc ON rv.id = pdc.reference_id 
                   AND pdc.reference_table = 'receive_voucher'
                   AND pdc.transaction_type = 'Received'
                   AND pdc.status = 'Approved'
                   AND pdc.tenant_id = rv.tenant_id
               WHERE rv.tenant_id = ?{$pdcCompanyFilter} AND rv.dsr_no IS NOT NULL AND rv.dsr_no != ''
               GROUP BY rv.dsr_no";
    
    $pdcStmt = $pdo->prepare($pdcSql);
    $pdcStmt->execute($pdcParams);
    $pdcData = $pdcStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $result = array_map(function($item, $index) use ($pdcData) {
        $sheetAmount = floatval($item['sheetAmount'] ?? 0);
        $cashRecovery = floatval($item['cashRecovery'] ?? 0);
        $pdcRecovery = floatval($pdcData[$item['reference']] ?? 0);
        $recoveryAmount = $cashRecovery + $pdcRecovery;
        $remainingBalance = $sheetAmount - $recoveryAmount;
        
        $status = 'no-recovery';
        if ($recoveryAmount > 0) {
            $status = ($remainingBalance <= 0) ? 'completed' : 'pending';
        }
        
        return [
            'id' => $index + 1,
            'reference' => $item['reference'],
            'salesOfficer' => $item['salesOfficer'] ?? 'N/A',
            'sheetAmount' => $sheetAmount,
            'recoveryAmount' => $recoveryAmount,
            'status' => $status
        ];
    }, $data, array_keys($data));
    
    echo json_encode(['success' => true, 'data' => $result]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}