<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get customer by ID
        $customer_id = $_GET['id'] ?? null;
        if (!$customer_id) {
            throw new Exception('Customer ID is required');
        }
        
        $stmt = $pdo->prepare("SELECT c.*, cc.category_name as customer_category_name, s.brand_name, p.project_name, p.project_code, ci.city_name as project_city_name FROM customers c LEFT JOIN customer_categories cc ON c.customer_category_id = cc.id LEFT JOIN suppliers s ON c.brand_id = s.id LEFT JOIN projects p ON c.project_id = p.id LEFT JOIN cities ci ON ci.id = p.city_id WHERE c.id = ? AND c.tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            throw new Exception('Customer not found');
        }
        
        echo json_encode(['success' => true, 'customer' => $customer]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update customer
        $input = json_decode(file_get_contents('php://input'), true);
        $customer_id = $input['id'] ?? null;
        
        if (!$customer_id || empty($input['customerName'])) {
            throw new Exception('Customer ID and name are required');
        }
        
        // Check if customer exists and belongs to tenant
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Customer not found');
        }
        
        // Update customer
        $sql = "UPDATE customers SET 
                company_id = ?, 
                customer_type_id = ?, 
                customer_category_id = ?,
                brand_id = ?,
                customer_name = ?, 
                address = ?, 
                primary_phone = ?, 
                secondary_phone = ?, 
                identity_card_no = ?, 
                email = ?, 
                country_id = ?, 
                region_id = ?, 
                city_id = ?, 
                city_zone_id = ?, 
                area_id = ?, 
                associated_sales_officer_id = ?, 
                supplier_man_id = ?, 
                project_id = ?, 
                is_sales_tax_registered = ?,, 
                strn = ?, 
                is_filer = ?, 
                ntn = ?, 
                advance_income_tax_percentage = ?, 
                default_discount_percentage = ?, 
                opening_debit_amount = ?, 
                opening_credit_amount = ?, 
                credit_limit = ?, 
                credit_period_limit_days = ?, 
                is_wholesaler = ?, 
                is_blacklisted = ?, 
                is_out_station = ?, 
                updated_by = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND tenant_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            !empty($input['companyId']) ? (int)$input['companyId'] : null,
            !empty($input['customerTypeId']) ? (int)$input['customerTypeId'] : null,
            !empty($input['customerCategoryId']) ? (int)$input['customerCategoryId'] : null,
            !empty($input['brandId']) ? (int)$input['brandId'] : null,
            trim($input['customerName']),
            !empty($input['address']) ? trim($input['address']) : null,
            !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null,
            !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null,
            !empty($input['identityCard']) ? trim($input['identityCard']) : null,
            !empty($input['email']) ? trim($input['email']) : null,
            !empty($input['countryId']) ? (int)$input['countryId'] : null,
            !empty($input['regionId']) ? (int)$input['regionId'] : null,
            !empty($input['cityId']) ? (int)$input['cityId'] : null,
            !empty($input['cityZoneId']) ? (int)$input['cityZoneId'] : null,
            !empty($input['areaId']) ? (int)$input['areaId'] : null,
            !empty($input['salesOfficerId']) ? (int)$input['salesOfficerId'] : null,
            !empty($input['supplierManId']) ? (int)$input['supplierManId'] : null,
            !empty($input['projectId']) ? (int)$input['projectId'] : null,
            isset($input['isSalesTaxRegistered']) ? (int)$input['isSalesTaxRegistered'] : 0,
            !empty($input['strn']) ? trim($input['strn']) : null,
            isset($input['isFiler']) ? (int)$input['isFiler'] : 0,
            !empty($input['ntn']) ? trim($input['ntn']) : null,
            floatval($input['advanceIncomeTax'] ?? 0),
            floatval($input['defaultDiscount'] ?? 0),
            floatval($input['openingDebit'] ?? 0),
            floatval($input['openingCredit'] ?? 0),
            floatval($input['balanceLimit'] ?? 0),
            intval($input['balancePeriodLimit'] ?? 0),
            isset($input['isWholesaler']) ? (int)$input['isWholesaler'] : 0,
            isset($input['blacklist']) ? (int)$input['blacklist'] : 0,
            isset($input['outStation']) ? (int)$input['outStation'] : 0,
            $user_id,
            $customer_id,
            $tenant_id
        ]);
        
        // Delete existing opening balance ledger entries
        $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'customer_opening' AND reference_table = 'customers' AND reference_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        
        // Delete existing opening balance invoices
        $stmt = $pdo->prepare("DELETE FROM opening_balance_invoices WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        
        // Insert new opening balance ledger entries
        $opening_debit = floatval($input['openingDebit'] ?? 0);
        $opening_credit = floatval($input['openingCredit'] ?? 0);
        
        if ($opening_debit > 0) {
            // Check if opening invoices are provided
            $opening_invoices = $input['openingInvoices'] ?? [];
            
            if (!empty($opening_invoices)) {
                // Insert individual invoice entries
                foreach ($opening_invoices as $invoice) {
                    if (!empty($invoice['debit']) && $invoice['debit'] > 0) {
                        // Insert into opening_balance_invoices table
                        $invoice_sql = "INSERT INTO opening_balance_invoices (tenant_id, customer_id, distribution_id, employee_id, invoice_number, debit, invoice_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($invoice_sql);
                        $stmt->execute([
                            $tenant_id,
                            $customer_id,
                            !empty($invoice['distribution']) ? $invoice['distribution'] : null,
                            !empty($invoice['salesOfficer']) ? $invoice['salesOfficer'] : null,
                            $invoice['invoiceNumber'] ?? '',
                            $invoice['debit'],
                            !empty($invoice['invoiceDate']) ? $invoice['invoiceDate'] : date('Y-m-d')
                        ]);
                        
                        // Get supplier and employee names for ledger description
                        $supplier_name = 'N/A';
                        $employee_name = 'N/A';
                        
                        if (!empty($invoice['distribution'])) {
                            $stmt = $pdo->prepare("SELECT supplier_name FROM suppliers WHERE id = ? AND tenant_id = ?");
                            $stmt->execute([$invoice['distribution'], $tenant_id]);
                            $supplier = $stmt->fetch();
                            if ($supplier) $supplier_name = $supplier['supplier_name'];
                        }
                        
                        if (!empty($invoice['salesOfficer'])) {
                            $stmt = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
                            $stmt->execute([$invoice['salesOfficer'], $tenant_id]);
                            $employee = $stmt->fetch();
                            if ($employee) $employee_name = $employee['full_name'];
                        }
                        
                        $description = 'Opening invoice: ' . ($invoice['invoiceNumber'] ?? 'N/A') . 
                                     ' - Distribution: ' . $supplier_name . 
                                     ' - Sales Officer: ' . $employee_name;
                        
                        $invoice_date = !empty($invoice['invoiceDate']) ? $invoice['invoiceDate'] : date('Y-m-d');
                        
                        // Insert ledger entries
                        $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($ledger_sql);
                        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, $invoice_date, $description, $invoice['debit'], 0]);
                        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, $invoice_date, $description, 0, $invoice['debit']]);
                    }
                }
            } else {
                // Single opening debit entry
                $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
                $stmt = $pdo->prepare($ledger_sql);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening debit balance', $opening_debit, 0]);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening debit balance', 0, $opening_debit]);
            }
        }
        
        if ($opening_credit > 0) {
            $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
            $stmt = $pdo->prepare($ledger_sql);
            $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening credit balance', $opening_credit, 0]);
            $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening credit balance', 0, $opening_credit]);
        }
        
        // Delete existing sub accounts
        $stmt = $pdo->prepare("DELETE FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        
        // Insert new sub accounts
        $sub_accounts = $input['subAccounts'] ?? [];
        if (!empty($sub_accounts)) {
            $sub_sql = "INSERT INTO customer_sub_accounts (tenant_id, customer_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sub_sql);
            foreach ($sub_accounts as $sub) {
                if (!empty($sub['sub_account_name'])) {
                    $stmt->execute([
                        $tenant_id, 
                        $customer_id, 
                        $sub['sub_account_name'],
                        floatval($sub['debit'] ?? 0),
                        floatval($sub['credit'] ?? 0)
                    ]);
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>