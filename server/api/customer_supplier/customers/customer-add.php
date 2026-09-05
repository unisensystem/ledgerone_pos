<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['customerName'])) {
        throw new Exception('Customer name is required');
    }
    
    if (empty($input['companyId'])) {
        throw new Exception('Company is required');
    }
    
    $pdo->beginTransaction();

    // Generate customer code tenant-wide with lock to prevent duplicates
    $stmt = $pdo->prepare("SELECT customer_code FROM customers WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id]);
    $lastCustomer = $stmt->fetch();

    if ($lastCustomer) {
        $lastNum = (int)substr($lastCustomer['customer_code'], 5);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }

    $customer_code = 'CUST-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

    // Prepare data
    $data = [
        'tenant_id' => $tenant_id,
        'company_id' => (int)$input['companyId'],
        'customer_type_id' => !empty($input['customerTypeId']) ? (int)$input['customerTypeId'] : null,
        'customer_category_id' => !empty($input['customerCategoryId']) ? (int)$input['customerCategoryId'] : null,
        'brand_id' => !empty($input['brandId']) ? (int)$input['brandId'] : null,
        'customer_code' => $customer_code,
        'customer_name' => trim($input['customerName']),
        'shop_name' => !empty($input['shopName']) ? trim($input['shopName']) : null,
        'address' => !empty($input['address']) ? trim($input['address']) : null,
        'po_box_no' => !empty($input['poBoxNo']) ? trim($input['poBoxNo']) : null,
        'primary_phone' => !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null,
        'secondary_phone' => !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null,
        'identity_card_no' => !empty($input['identityCard']) ? trim($input['identityCard']) : null,
        'license_no' => !empty($input['licenseNo']) ? trim($input['licenseNo']) : null,
        'email' => !empty($input['email']) ? trim($input['email']) : null,
        'country_id' => !empty($input['countryId']) ? (int)$input['countryId'] : null,
        'region_id' => !empty($input['regionId']) ? (int)$input['regionId'] : null,
        'city_id' => !empty($input['cityId']) ? (int)$input['cityId'] : null,
        'city_zone_id' => !empty($input['cityZoneId']) ? (int)$input['cityZoneId'] : null,
        'area_id' => !empty($input['areaId']) ? (int)$input['areaId'] : null,
        'associated_sales_officer_id' => !empty($input['salesOfficerId']) ? (int)$input['salesOfficerId'] : null,
        'supplier_man_id' => !empty($input['supplierManId']) ? (int)$input['supplierManId'] : null,
        'project_id' => !empty($input['projectId']) ? (int)$input['projectId'] : null,
        'is_sales_tax_registered' => isset($input['isSalesTaxRegistered']) ? (int)$input['isSalesTaxRegistered'] : 0,
        'strn' => !empty($input['strn']) ? trim($input['strn']) : null,
        'is_filer' => isset($input['isFiler']) ? (int)$input['isFiler'] : 0,
        'ntn' => !empty($input['ntn']) ? trim($input['ntn']) : null,
        'advance_income_tax_percentage' => floatval($input['advanceIncomeTax'] ?? 0),
        'default_discount_percentage' => floatval($input['defaultDiscount'] ?? 0),
        'opening_debit_amount' => floatval($input['openingDebit'] ?? 0),
        'opening_credit_amount' => floatval($input['openingCredit'] ?? 0),
        'credit_limit' => floatval($input['balanceLimit'] ?? 0),
        'credit_period_limit_days' => intval($input['balancePeriodLimit'] ?? 0),
        'is_wholesaler' => isset($input['isWholesaler']) ? (int)$input['isWholesaler'] : 0,
        'is_blacklisted' => isset($input['blacklist']) ? (int)$input['blacklist'] : 0,
        'is_out_station' => isset($input['outStation']) ? (int)$input['outStation'] : 0,
        'created_by' => $user_id,
        'updated_by' => $user_id
    ];
    
    // Generate ID - get max ID across all tenants to avoid conflicts
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM customers FOR UPDATE");
    $stmt->execute();
    $new_id = $stmt->fetch()['new_id'];
    $data['id'] = $new_id;
    
    // Insert customer
    $sql = "INSERT INTO customers (id, tenant_id, company_id, customer_type_id, customer_category_id, brand_id, customer_code, customer_name, shop_name, address, po_box_no, primary_phone, secondary_phone, identity_card_no, license_no, email, country_id, region_id, city_id, city_zone_id, area_id, associated_sales_officer_id, supplier_man_id, project_id, is_sales_tax_registered, strn, is_filer, ntn, advance_income_tax_percentage, default_discount_percentage, opening_debit_amount, opening_credit_amount, credit_limit, credit_period_limit_days, is_wholesaler, is_blacklisted, is_out_station, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['id'],
        $data['tenant_id'],
        $data['company_id'],
        $data['customer_type_id'],
        $data['customer_category_id'],
        $data['brand_id'],
        $data['customer_code'],
        $data['customer_name'],
        $data['shop_name'],
        $data['address'],
        $data['po_box_no'],
        $data['primary_phone'],
        $data['secondary_phone'],
        $data['identity_card_no'],
        $data['license_no'],
        $data['email'],
        $data['country_id'],
        $data['region_id'],
        $data['city_id'],
        $data['city_zone_id'],
        $data['area_id'],
        $data['associated_sales_officer_id'],
        $data['supplier_man_id'],
        $data['project_id'],
        $data['is_sales_tax_registered'],
        $data['strn'],
        $data['is_filer'],
        $data['ntn'],
        $data['advance_income_tax_percentage'],
        $data['default_discount_percentage'],
        $data['opening_debit_amount'],
        $data['opening_credit_amount'],
        $data['credit_limit'],
        $data['credit_period_limit_days'],
        $data['is_wholesaler'],
        $data['is_blacklisted'],
        $data['is_out_station'],
        $data['created_by'],
        $data['updated_by']
    ]);
    
    // Insert accounting ledger entries for opening balances
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
                $new_id,
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
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 2, $invoice_date, $description, $inv_debit, 0]);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 90, $invoice_date, $description, 0, $inv_debit]);
            } else {
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 90, $invoice_date, $description, $inv_credit, 0]);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 2, $invoice_date, $description, 0, $inv_credit]);
            }
        }
  } else {
    if ($data['opening_debit_amount'] > 0) {
        // Insert into opening_balance_invoices so it shows up in Aging / Recovery reports
        $invoice_sql = "INSERT INTO opening_balance_invoices (tenant_id, customer_id, distribution_id, employee_id, invoice_number, debit, credit, invoice_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($invoice_sql);
        $stmt->execute([
            $tenant_id,
            $new_id,
            null,
            null,
            'OB-' . $customer_code,
            $data['opening_debit_amount'],
            0,
            date('Y-m-d')
        ]);

        // Single opening debit entry
        $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
        $stmt = $pdo->prepare($ledger_sql);
        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 2, 'Customer opening debit balance', $data['opening_debit_amount'], 0]);
        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 90, 'Customer opening debit balance', 0, $data['opening_debit_amount']]);
    }

    if ($data['opening_credit_amount'] > 0) {
        // Insert into opening_balance_invoices as well (credit side)
        $invoice_sql = "INSERT INTO opening_balance_invoices (tenant_id, customer_id, distribution_id, employee_id, invoice_number, debit, credit, invoice_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($invoice_sql);
        $stmt->execute([
            $tenant_id,
            $new_id,
            null,
            null,
            'OB-' . $customer_code,
            0,
            $data['opening_credit_amount'],
            date('Y-m-d')
        ]);

        // Opening Balance Equity (90) = Debit, Trade Debtors (2) = Credit
        $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
        $stmt = $pdo->prepare($ledger_sql);
        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 90, 'Customer opening credit balance', $data['opening_credit_amount'], 0]);
        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $new_id, 2, 'Customer opening credit balance', 0, $data['opening_credit_amount']]);
    }
}
    
    // Insert sub accounts
    $sub_accounts = $input['subAccounts'] ?? [];
    if (!empty($sub_accounts)) {
        $sub_sql = "INSERT INTO customer_sub_accounts (tenant_id, customer_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sub_sql);
        foreach ($sub_accounts as $sub) {
            if (!empty($sub['sub_account_name'])) {
                $stmt->execute([
                    $tenant_id, 
                    $new_id, 
                    $sub['sub_account_name'],
                    floatval($sub['debit'] ?? 0),
                    floatval($sub['credit'] ?? 0)
                ]);
            }
        }
    }
    
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Customer created successfully',
        'customer_code' => $customer_code,
        'customer_id' => $new_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
