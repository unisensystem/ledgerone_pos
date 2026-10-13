<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
        
        // Check if customer exists and belongs to tenant (grab full row for the Activity Log)
        $stmt = $pdo->prepare("SELECT company_id, customer_code, customer_name, shop_name, address, primary_phone, secondary_phone, email, credit_limit, is_blacklisted FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);
        $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingCustomer) {
            throw new Exception('Customer not found');
        }

        $newCompanyId = !empty($input['companyId']) ? (int)$input['companyId'] : null;
        $customerCode = $existingCustomer['customer_code'];

        // Update customer
        $sql = "UPDATE customers SET
                company_id = ?,
                customer_code = ?,
                customer_type_id = ?, 
                customer_category_id = ?,
                brand_id = ?,
                customer_name = ?, 
                shop_name = ?,
                address = ?, 
                po_box_no = ?, 
                primary_phone = ?, 
                secondary_phone = ?, 
                identity_card_no = ?,
                license_no = ?,
                email = ?,
                country_id = ?, 
                region_id = ?, 
                city_id = ?, 
                city_zone_id = ?, 
                area_id = ?, 
                associated_sales_officer_id = ?, 
                supplier_man_id = ?, 
                project_id = ?, 
                is_sales_tax_registered = ?, 
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
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt->execute([
            $newCompanyId,
            $customerCode,
            !empty($input['customerTypeId']) ? (int)$input['customerTypeId'] : null,
            !empty($input['customerCategoryId']) ? (int)$input['customerCategoryId'] : null,
            !empty($input['brandId']) ? (int)$input['brandId'] : null,
            trim($input['customerName']),
            !empty($input['shopName']) ? trim($input['shopName']) : null,
            !empty($input['address']) ? trim($input['address']) : null,
            !empty($input['poBoxNo']) ? trim($input['poBoxNo']) : null,
            !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null,
            !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null,
            !empty($input['identityCard']) ? trim($input['identityCard']) : null,
            !empty($input['licenseNo']) ? trim($input['licenseNo']) : null,
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
        
        $opening_invoices = $input['openingInvoices'] ?? [];

        if (!empty($opening_invoices)) {
            // Insert individual invoice entries (each row is either Debit or Credit)
            foreach ($opening_invoices as $invoice) {
                $inv_debit = floatval($invoice['debit'] ?? 0);
                $inv_credit = floatval($invoice['credit'] ?? 0);
                if ($inv_debit <= 0 && $inv_credit <= 0) continue;

                // Insert into opening_balance_invoices table
                $invoice_sql = "INSERT INTO opening_balance_invoices (tenant_id, customer_id, distribution_id, employee_id, invoice_number, debit, credit, invoice_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($invoice_sql);
                $stmt->execute([
                    $tenant_id,
                    $customer_id,
                    !empty($invoice['distribution']) ? $invoice['distribution'] : null,
                    !empty($invoice['salesOfficer']) ? $invoice['salesOfficer'] : null,
                    $invoice['invoiceNumber'] ?? '',
                    $inv_debit,
                    $inv_credit,
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

                // Insert ledger entries (Trade Debtors = 2, Opening Balance Equity = 90)
                $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($ledger_sql);
                if ($inv_debit > 0) {
                    $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, $invoice_date, $description, $inv_debit, 0]);
                    $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, $invoice_date, $description, 0, $inv_debit]);
                } else {
                    $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, $invoice_date, $description, $inv_credit, 0]);
                    $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, $invoice_date, $description, 0, $inv_credit]);
                }
            }
        } else {
            if ($opening_debit > 0) {
                // Single opening debit entry
                $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
                $stmt = $pdo->prepare($ledger_sql);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening debit balance', $opening_debit, 0]);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening debit balance', 0, $opening_debit]);
            }

            if ($opening_credit > 0) {
                $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
                $stmt = $pdo->prepare($ledger_sql);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening credit balance', $opening_credit, 0]);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening credit balance', 0, $opening_credit]);
            }
        }
        
        // Upsert sub-accounts: update existing rows (by id), insert new ones,
        // delete only rows that were explicitly removed from the form.
        // Never DELETE-all + re-INSERT: that burns new auto-increment IDs and
        // orphans every transaction (sale_invoice, receive_voucher, etc.) that
        // holds a reference to the old IDs.
        $sub_accounts  = $input['subAccounts'] ?? [];
        $kept_ids      = [];

        $update_sub = $pdo->prepare(
            "UPDATE customer_sub_accounts
             SET sub_account_name = ?, debit = ?, credit = ?
             WHERE id = ? AND tenant_id = ? AND customer_id = ?"
        );
        $insert_sub = $pdo->prepare(
            "INSERT INTO customer_sub_accounts (tenant_id, customer_id, sub_account_name, debit, credit)
             VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($sub_accounts as $sub) {
            if (empty($sub['sub_account_name'])) continue;

            $name   = trim($sub['sub_account_name']);
            $debit  = floatval($sub['debit']  ?? 0);
            $credit = floatval($sub['credit'] ?? 0);

            if (!empty($sub['id'])) {
                // Existing row — update in place, keeping the same primary key
                $update_sub->execute([$name, $debit, $credit, (int)$sub['id'], $tenant_id, $customer_id]);
                $kept_ids[] = (int)$sub['id'];
            } else {
                // New row added via the form
                $insert_sub->execute([$tenant_id, $customer_id, $name, $debit, $credit]);
                $kept_ids[] = (int)$pdo->lastInsertId();
            }
        }

        // Delete only sub-accounts the user explicitly removed
        if (!empty($kept_ids)) {
            $ph = implode(',', array_fill(0, count($kept_ids), '?'));
            $del_stmt = $pdo->prepare(
                "DELETE FROM customer_sub_accounts
                 WHERE tenant_id = ? AND customer_id = ? AND id NOT IN ($ph)"
            );
            $del_stmt->execute(array_merge([$tenant_id, $customer_id], $kept_ids));
        } else {
            // User removed every sub-account
            $pdo->prepare("DELETE FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?")
                ->execute([$tenant_id, $customer_id]);
        }
        
        $newCustomer = [
            'company_id' => $newCompanyId, 'customer_code' => $customerCode, 'customer_name' => trim($input['customerName']),
            'shop_name' => !empty($input['shopName']) ? trim($input['shopName']) : null,
            'address' => !empty($input['address']) ? trim($input['address']) : null,
            'primary_phone' => !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null,
            'secondary_phone' => !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null,
            'email' => !empty($input['email']) ? trim($input['email']) : null,
            'credit_limit' => floatval($input['balanceLimit'] ?? 0),
            'is_blacklisted' => isset($input['blacklist']) ? (int)$input['blacklist'] : 0,
        ];
        $changeDescriptions = buildChangeDescription($existingCustomer, $newCustomer, [
            'customer_name' => 'Name', 'shop_name' => 'Shop Name', 'address' => 'Address', 'primary_phone' => 'Primary Phone',
            'secondary_phone' => 'Secondary Phone', 'email' => 'Email', 'credit_limit' => 'Credit Limit', 'is_blacklisted' => 'Blacklisted'
        ]);
        $editDescription = !empty($changeDescriptions)
            ? "Updated customer \"{$newCustomer['customer_name']}\" ($customerCode): " . implode('; ', $changeDescriptions)
            : "Updated customer \"{$newCustomer['customer_name']}\" ($customerCode) - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Customer', $customer_id, $customerCode, $editDescription, $existingCustomer, $newCustomer);

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
