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

    // Shared identity fields - one entry, saved into both customers and suppliers.
    if (empty($input['partyName'])) {
        throw new Exception('Party Name is required');
    }
    if (empty($input['companyId'])) {
        throw new Exception('Company is required');
    }

    $partyName = trim($input['partyName']);
    $address = !empty($input['address']) ? trim($input['address']) : null;
    $primaryPhone = !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null;
    $secondaryPhone = !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null;
    $identityCard = !empty($input['identityCard']) ? trim($input['identityCard']) : null;
    $email = !empty($input['email']) ? trim($input['email']) : null;
    $companyId = (int)$input['companyId'];
    $projectId = !empty($input['projectId']) ? (int)$input['projectId'] : null;
    $isBlacklisted = isset($input['blacklist']) ? (int)$input['blacklist'] : 0;

    $pdo->beginTransaction();

    // ============================================================
    // CUSTOMER SIDE
    // ============================================================

    $stmt = $pdo->prepare("SELECT customer_code FROM customers WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id]);
    $lastCustomer = $stmt->fetch();
    $nextCustomerNum = $lastCustomer ? ((int)substr($lastCustomer['customer_code'], 5) + 1) : 1;
    $customer_code = 'CUST-' . str_pad($nextCustomerNum, 5, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM customers FOR UPDATE");
    $stmt->execute();
    $customer_id = $stmt->fetch()['new_id'];

    $customerData = [
        'customer_type_id' => !empty($input['customerTypeId']) ? (int)$input['customerTypeId'] : null,
        'customer_category_id' => !empty($input['customerCategoryId']) ? (int)$input['customerCategoryId'] : null,
        'brand_id' => !empty($input['brandId']) ? (int)$input['brandId'] : null,
        'shop_name' => !empty($input['shopName']) ? trim($input['shopName']) : null,
        'po_box_no' => !empty($input['poBoxNo']) ? trim($input['poBoxNo']) : null,
        'license_no' => !empty($input['licenseNo']) ? trim($input['licenseNo']) : null,
        'country_id' => !empty($input['countryId']) ? (int)$input['countryId'] : null,
        'region_id' => !empty($input['regionId']) ? (int)$input['regionId'] : null,
        'city_id' => !empty($input['cityId']) ? (int)$input['cityId'] : null,
        'city_zone_id' => !empty($input['cityZoneId']) ? (int)$input['cityZoneId'] : null,
        'area_id' => !empty($input['areaId']) ? (int)$input['areaId'] : null,
        'associated_sales_officer_id' => !empty($input['salesOfficerId']) ? (int)$input['salesOfficerId'] : null,
        'supplier_man_id' => !empty($input['supplierManId']) ? (int)$input['supplierManId'] : null,
        'is_sales_tax_registered' => isset($input['isSalesTaxRegistered']) ? (int)$input['isSalesTaxRegistered'] : 0,
        'strn' => !empty($input['strn']) ? trim($input['strn']) : null,
        'is_filer' => isset($input['isFiler']) ? (int)$input['isFiler'] : 0,
        'ntn' => !empty($input['ntn']) ? trim($input['ntn']) : null,
        'advance_income_tax_percentage' => floatval($input['advanceIncomeTax'] ?? 0),
        'default_discount_percentage' => floatval($input['defaultDiscount'] ?? 0),
        'opening_debit_amount' => floatval($input['customerOpeningDebit'] ?? 0),
        'opening_credit_amount' => floatval($input['customerOpeningCredit'] ?? 0),
        'credit_limit' => floatval($input['balanceLimit'] ?? 0),
        'credit_period_limit_days' => intval($input['balancePeriodLimit'] ?? 0),
        'is_wholesaler' => isset($input['isWholesaler']) ? (int)$input['isWholesaler'] : 0,
        'is_out_station' => isset($input['outStation']) ? (int)$input['outStation'] : 0,
    ];

    $sql = "INSERT INTO customers (
                id, tenant_id, company_id, customer_type_id, customer_category_id, brand_id,
                customer_code, customer_name, shop_name, address, po_box_no, primary_phone,
                secondary_phone, identity_card_no, license_no, email, country_id, region_id,
                city_id, city_zone_id, area_id, associated_sales_officer_id, supplier_man_id,
                project_id, is_sales_tax_registered, strn, is_filer, ntn,
                advance_income_tax_percentage, default_discount_percentage, opening_debit_amount,
                opening_credit_amount, credit_limit, credit_period_limit_days, is_wholesaler,
                is_blacklisted, is_out_station, is_both, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $customer_id, $tenant_id, $companyId, $customerData['customer_type_id'], $customerData['customer_category_id'],
        $customerData['brand_id'], $customer_code, $partyName, $customerData['shop_name'], $address,
        $customerData['po_box_no'], $primaryPhone, $secondaryPhone, $identityCard, $customerData['license_no'],
        $email, $customerData['country_id'], $customerData['region_id'], $customerData['city_id'],
        $customerData['city_zone_id'], $customerData['area_id'], $customerData['associated_sales_officer_id'],
        $customerData['supplier_man_id'], $projectId, $customerData['is_sales_tax_registered'], $customerData['strn'],
        $customerData['is_filer'], $customerData['ntn'], $customerData['advance_income_tax_percentage'],
        $customerData['default_discount_percentage'], $customerData['opening_debit_amount'],
        $customerData['opening_credit_amount'], $customerData['credit_limit'], $customerData['credit_period_limit_days'],
        $customerData['is_wholesaler'], $isBlacklisted, $customerData['is_out_station'], $user_id, $user_id
    ]);

    // Customer opening balance ledger entries (mirrors customers/customer-add.php)
    if ($customerData['opening_debit_amount'] > 0) {
        $openingInvoices = $input['openingInvoices'] ?? [];
        if (!empty($openingInvoices)) {
            foreach ($openingInvoices as $invoice) {
                if (!empty($invoice['debit']) && $invoice['debit'] > 0) {
                    $stmt = $pdo->prepare("INSERT INTO opening_balance_invoices (tenant_id, customer_id, distribution_id, employee_id, invoice_number, debit, invoice_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $tenant_id, $customer_id,
                        !empty($invoice['distribution']) ? $invoice['distribution'] : null,
                        !empty($invoice['salesOfficer']) ? $invoice['salesOfficer'] : null,
                        $invoice['invoiceNumber'] ?? '', $invoice['debit'],
                        !empty($invoice['invoiceDate']) ? $invoice['invoiceDate'] : date('Y-m-d')
                    ]);

                    $supplier_name_for_desc = 'N/A';
                    $employee_name_for_desc = 'N/A';
                    if (!empty($invoice['distribution'])) {
                        $s = $pdo->prepare("SELECT supplier_name FROM suppliers WHERE id = ? AND tenant_id = ?");
                        $s->execute([$invoice['distribution'], $tenant_id]);
                        $row = $s->fetch();
                        if ($row) $supplier_name_for_desc = $row['supplier_name'];
                    }
                    if (!empty($invoice['salesOfficer'])) {
                        $e = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
                        $e->execute([$invoice['salesOfficer'], $tenant_id]);
                        $row = $e->fetch();
                        if ($row) $employee_name_for_desc = $row['full_name'];
                    }

                    $description = 'Opening invoice: ' . ($invoice['invoiceNumber'] ?? 'N/A') .
                                 ' - Distribution: ' . $supplier_name_for_desc .
                                 ' - Sales Officer: ' . $employee_name_for_desc;
                    $invoice_date = !empty($invoice['invoiceDate']) ? $invoice['invoiceDate'] : date('Y-m-d');

                    $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($ledger_sql);
                    $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, $invoice_date, $description, $invoice['debit'], 0]);
                    $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, $invoice_date, $description, 0, $invoice['debit']]);
                }
            }
        } else {
            $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
            $stmt = $pdo->prepare($ledger_sql);
            $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening debit balance', $customerData['opening_debit_amount'], 0]);
            $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening debit balance', 0, $customerData['opening_debit_amount']]);
        }
    }

    if ($customerData['opening_credit_amount'] > 0) {
        $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
        $stmt = $pdo->prepare($ledger_sql);
        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening credit balance', $customerData['opening_credit_amount'], 0]);
        $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening credit balance', 0, $customerData['opening_credit_amount']]);
    }

    // Customer sub accounts
    $customerSubAccounts = $input['customerSubAccounts'] ?? [];
    if (!empty($customerSubAccounts)) {
        $stmt = $pdo->prepare("INSERT INTO customer_sub_accounts (tenant_id, customer_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)");
        foreach ($customerSubAccounts as $sub) {
            if (!empty($sub['sub_account_name'])) {
                $stmt->execute([$tenant_id, $customer_id, $sub['sub_account_name'], floatval($sub['debit'] ?? 0), floatval($sub['credit'] ?? 0)]);
            }
        }
    }

    // ============================================================
    // SUPPLIER SIDE
    // ============================================================

    $stmt = $pdo->prepare("SELECT supplier_code FROM suppliers WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id]);
    $lastSupplier = $stmt->fetch();
    $nextSupplierNum = $lastSupplier ? ((int)substr($lastSupplier['supplier_code'], 5) + 1) : 1;
    $supplier_code = 'SUPP-' . str_pad($nextSupplierNum, 5, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM suppliers FOR UPDATE");
    $stmt->execute();
    $supplier_id = $stmt->fetch()['new_id'];

    $supplierData = [
        'salesman_id' => !empty($input['salesmanId']) ? trim($input['salesmanId']) : null,
        'city' => !empty($input['supplierCity']) ? trim($input['supplierCity']) : null,
        'brand_name' => !empty($input['brandNameSupplier']) ? trim($input['brandNameSupplier']) : null,
        'opening_debit_amount' => floatval($input['supplierOpeningDebit'] ?? 0),
        'opening_credit_amount' => floatval($input['supplierOpeningCredit'] ?? 0),
        'ait_percent' => floatval($input['aitPercent'] ?? 0),
        'credit_days' => isset($input['creditDays']) && $input['creditDays'] !== '' ? (int)$input['creditDays'] : 0,
        'party_type' => !empty($input['partyType']) ? trim($input['partyType']) : null,
    ];

    $sql = "INSERT INTO suppliers (
                id, tenant_id, company_id, salesman_id, project_id, supplier_code, supplier_name,
                brand_name, address, city, primary_phone, secondary_phone, identity_card_no, email,
                opening_debit_amount, opening_credit_amount, ait_percent, credit_days, party_type,
                is_blacklisted, is_both, linked_customer_id, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $supplier_id, $tenant_id, $companyId, $supplierData['salesman_id'], $projectId, $supplier_code,
        $partyName, $supplierData['brand_name'], $address, $supplierData['city'], $primaryPhone,
        $secondaryPhone, $identityCard, $email, $supplierData['opening_debit_amount'],
        $supplierData['opening_credit_amount'], $supplierData['ait_percent'], $supplierData['credit_days'],
        $supplierData['party_type'], $isBlacklisted, $customer_id, $user_id, $user_id
    ]);

    // Supplier opening balance ledger entries (mirrors suppliers/supplier-add.php)
    if ($supplierData['opening_debit_amount'] > 0) {
        $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
        $stmt = $pdo->prepare($ledgerSql);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, 'Supplier Opening Debit - ' . $partyName, $supplierData['opening_debit_amount'], 0]);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, 'Supplier Opening Debit - ' . $partyName, 0, $supplierData['opening_debit_amount']]);
    }

    if ($supplierData['opening_credit_amount'] > 0) {
        $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
        $stmt = $pdo->prepare($ledgerSql);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, 'Supplier Opening Credit - ' . $partyName, $supplierData['opening_credit_amount'], 0]);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, 'Supplier Opening Credit - ' . $partyName, 0, $supplierData['opening_credit_amount']]);
    }

    // Supplier sub accounts
    $supplierSubAccounts = $input['supplierSubAccounts'] ?? [];
    if (!empty($supplierSubAccounts)) {
        $stmt = $pdo->prepare("INSERT INTO supplier_sub_accounts (tenant_id, supplier_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)");
        foreach ($supplierSubAccounts as $sub) {
            if (!empty(trim($sub['name'] ?? ''))) {
                $stmt->execute([$tenant_id, $supplier_id, trim($sub['name']), floatval($sub['debit'] ?? 0), floatval($sub['credit'] ?? 0)]);
            }
        }
    }

    // Associated companies
    if (!empty($input['associatedCompanyIds'])) {
        $assocIds = array_filter(array_map('intval', explode(',', $input['associatedCompanyIds'])));
        if (!empty($assocIds)) {
            $assocSql = "INSERT INTO supplier_associated_companies (tenant_id, supplier_id, company_id) VALUES (?, ?, ?)";
            $assocStmt = $pdo->prepare($assocSql);
            foreach ($assocIds as $cid) {
                $assocStmt->execute([$tenant_id, $supplier_id, $cid]);
            }
        }
    }

    // Link the customer row back to its paired supplier row
    $pdo->prepare("UPDATE customers SET linked_supplier_id = ? WHERE id = ? AND tenant_id = ?")
        ->execute([$supplier_id, $customer_id, $tenant_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Customer and Supplier created successfully',
        'customer_code' => $customer_code,
        'customer_id' => $customer_id,
        'supplier_code' => $supplier_code,
        'supplier_id' => $supplier_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
