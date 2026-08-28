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
        $customer_id = $_GET['id'] ?? null;
        if (!$customer_id) {
            throw new Exception('Customer ID is required');
        }

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND tenant_id = ? AND is_both = 1");
        $stmt->execute([$customer_id, $tenant_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            throw new Exception('Customer + Supplier (Both) record not found');
        }

        $supplier = null;
        if (!empty($customer['linked_supplier_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$customer['linked_supplier_id'], $tenant_id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->prepare("SELECT id, sub_account_name, debit, credit FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        $customerSubAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $supplierSubAccounts = [];
        $associatedCompanyIds = [];
        if ($supplier) {
            $stmt = $pdo->prepare("SELECT id, sub_account_name, debit, credit FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ?");
            $stmt->execute([$tenant_id, $customer['linked_supplier_id']]);
            $supplierSubAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT company_id FROM supplier_associated_companies WHERE tenant_id = ? AND supplier_id = ?");
            $stmt->execute([$tenant_id, $customer['linked_supplier_id']]);
            $associatedCompanyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode([
            'success' => true,
            'customer' => $customer,
            'supplier' => $supplier,
            'customer_sub_accounts' => $customerSubAccounts,
            'supplier_sub_accounts' => $supplierSubAccounts,
            'associated_company_ids' => $associatedCompanyIds
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $customer_id = $input['customerId'] ?? null;

        if (!$customer_id || empty($input['partyName'])) {
            throw new Exception('Customer ID and Party Name are required');
        }

        $stmt = $pdo->prepare("SELECT id, linked_supplier_id FROM customers WHERE id = ? AND tenant_id = ? AND is_both = 1");
        $stmt->execute([$customer_id, $tenant_id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new Exception('Customer + Supplier (Both) record not found');
        }
        $supplier_id = $existing['linked_supplier_id'];

        $partyName = trim($input['partyName']);
        $address = !empty($input['address']) ? trim($input['address']) : null;
        $primaryPhone = !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null;
        $secondaryPhone = !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null;
        $identityCard = !empty($input['identityCard']) ? trim($input['identityCard']) : null;
        $email = !empty($input['email']) ? trim($input['email']) : null;
        $companyId = !empty($input['companyId']) ? (int)$input['companyId'] : null;
        $projectId = !empty($input['projectId']) ? (int)$input['projectId'] : null;
        $isBlacklisted = isset($input['blacklist']) ? (int)$input['blacklist'] : 0;

        $pdo->beginTransaction();

        // ============================================================
        // CUSTOMER SIDE UPDATE
        // ============================================================
        $sql = "UPDATE customers SET
                company_id = ?, customer_type_id = ?, customer_category_id = ?, brand_id = ?,
                customer_name = ?, shop_name = ?, address = ?, po_box_no = ?, primary_phone = ?,
                secondary_phone = ?, identity_card_no = ?, license_no = ?, email = ?, country_id = ?,
                region_id = ?, city_id = ?, city_zone_id = ?, area_id = ?, associated_sales_officer_id = ?,
                supplier_man_id = ?, project_id = ?, is_sales_tax_registered = ?, strn = ?, is_filer = ?,
                ntn = ?, advance_income_tax_percentage = ?, default_discount_percentage = ?,
                opening_debit_amount = ?, opening_credit_amount = ?, credit_limit = ?,
                credit_period_limit_days = ?, is_wholesaler = ?, is_blacklisted = ?, is_out_station = ?,
                updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $companyId,
            !empty($input['customerTypeId']) ? (int)$input['customerTypeId'] : null,
            !empty($input['customerCategoryId']) ? (int)$input['customerCategoryId'] : null,
            !empty($input['brandId']) ? (int)$input['brandId'] : null,
            $partyName,
            !empty($input['shopName']) ? trim($input['shopName']) : null,
            $address,
            !empty($input['poBoxNo']) ? trim($input['poBoxNo']) : null,
            $primaryPhone,
            $secondaryPhone,
            $identityCard,
            !empty($input['licenseNo']) ? trim($input['licenseNo']) : null,
            $email,
            !empty($input['countryId']) ? (int)$input['countryId'] : null,
            !empty($input['regionId']) ? (int)$input['regionId'] : null,
            !empty($input['cityId']) ? (int)$input['cityId'] : null,
            !empty($input['cityZoneId']) ? (int)$input['cityZoneId'] : null,
            !empty($input['areaId']) ? (int)$input['areaId'] : null,
            !empty($input['salesOfficerId']) ? (int)$input['salesOfficerId'] : null,
            !empty($input['supplierManId']) ? (int)$input['supplierManId'] : null,
            $projectId,
            isset($input['isSalesTaxRegistered']) ? (int)$input['isSalesTaxRegistered'] : 0,
            !empty($input['strn']) ? trim($input['strn']) : null,
            isset($input['isFiler']) ? (int)$input['isFiler'] : 0,
            !empty($input['ntn']) ? trim($input['ntn']) : null,
            floatval($input['advanceIncomeTax'] ?? 0),
            floatval($input['defaultDiscount'] ?? 0),
            floatval($input['customerOpeningDebit'] ?? 0),
            floatval($input['customerOpeningCredit'] ?? 0),
            floatval($input['balanceLimit'] ?? 0),
            intval($input['balancePeriodLimit'] ?? 0),
            isset($input['isWholesaler']) ? (int)$input['isWholesaler'] : 0,
            $isBlacklisted,
            isset($input['outStation']) ? (int)$input['outStation'] : 0,
            $user_id,
            $customer_id,
            $tenant_id
        ]);

        // Replace customer opening-balance ledger entries + opening invoices
        $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'customer_opening' AND reference_table = 'customers' AND reference_id = ?")
            ->execute([$tenant_id, $customer_id]);
        $pdo->prepare("DELETE FROM opening_balance_invoices WHERE tenant_id = ? AND customer_id = ?")
            ->execute([$tenant_id, $customer_id]);

        $customerOpeningDebit = floatval($input['customerOpeningDebit'] ?? 0);
        $customerOpeningCredit = floatval($input['customerOpeningCredit'] ?? 0);

        if ($customerOpeningDebit > 0) {
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
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening debit balance', $customerOpeningDebit, 0]);
                $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening debit balance', 0, $customerOpeningDebit]);
            }
        }

        if ($customerOpeningCredit > 0) {
            $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
            $stmt = $pdo->prepare($ledger_sql);
            $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, 'Customer opening credit balance', $customerOpeningCredit, 0]);
            $stmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, 'Customer opening credit balance', 0, $customerOpeningCredit]);
        }

        // Upsert customer sub-accounts (update by id, insert new, delete removed)
        $customerSubAccounts = $input['customerSubAccounts'] ?? [];
        $keptCustomerSubIds = [];
        $updateCustSub = $pdo->prepare("UPDATE customer_sub_accounts SET sub_account_name = ?, debit = ?, credit = ? WHERE id = ? AND tenant_id = ? AND customer_id = ?");
        $insertCustSub = $pdo->prepare("INSERT INTO customer_sub_accounts (tenant_id, customer_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)");
        foreach ($customerSubAccounts as $sub) {
            $name = trim($sub['sub_account_name'] ?? '');
            if ($name === '') continue;
            $debit = floatval($sub['debit'] ?? 0);
            $credit = floatval($sub['credit'] ?? 0);
            if (!empty($sub['id'])) {
                $updateCustSub->execute([$name, $debit, $credit, (int)$sub['id'], $tenant_id, $customer_id]);
                $keptCustomerSubIds[] = (int)$sub['id'];
            } else {
                $insertCustSub->execute([$tenant_id, $customer_id, $name, $debit, $credit]);
                $keptCustomerSubIds[] = (int)$pdo->lastInsertId();
            }
        }
        if (!empty($keptCustomerSubIds)) {
            $ph = implode(',', array_fill(0, count($keptCustomerSubIds), '?'));
            $pdo->prepare("DELETE FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ? AND id NOT IN ($ph)")
                ->execute(array_merge([$tenant_id, $customer_id], $keptCustomerSubIds));
        } else {
            $pdo->prepare("DELETE FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?")
                ->execute([$tenant_id, $customer_id]);
        }

        // ============================================================
        // SUPPLIER SIDE UPDATE
        // ============================================================
        if ($supplier_id) {
            $sql = "UPDATE suppliers SET
                    company_id = ?, salesman_id = ?, project_id = ?, supplier_name = ?, brand_name = ?,
                    address = ?, city = ?, primary_phone = ?, secondary_phone = ?, identity_card_no = ?,
                    email = ?, opening_debit_amount = ?, opening_credit_amount = ?, ait_percent = ?,
                    credit_days = ?, party_type = ?, is_blacklisted = ?, updated_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND tenant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $companyId,
                !empty($input['salesmanId']) ? trim($input['salesmanId']) : null,
                $projectId,
                $partyName,
                !empty($input['brandNameSupplier']) ? trim($input['brandNameSupplier']) : null,
                $address,
                !empty($input['supplierCity']) ? trim($input['supplierCity']) : null,
                $primaryPhone,
                $secondaryPhone,
                $identityCard,
                $email,
                floatval($input['supplierOpeningDebit'] ?? 0),
                floatval($input['supplierOpeningCredit'] ?? 0),
                floatval($input['aitPercent'] ?? 0),
                isset($input['creditDays']) && $input['creditDays'] !== '' ? (int)$input['creditDays'] : 0,
                !empty($input['partyType']) ? trim($input['partyType']) : null,
                $isBlacklisted,
                $user_id,
                $supplier_id,
                $tenant_id
            ]);

            $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'supplier_opening' AND reference_table = 'suppliers' AND reference_id = ?")
                ->execute([$tenant_id, $supplier_id]);

            $supplierOpeningDebit = floatval($input['supplierOpeningDebit'] ?? 0);
            $supplierOpeningCredit = floatval($input['supplierOpeningCredit'] ?? 0);

            if ($supplierOpeningDebit > 0) {
                $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
                $stmt = $pdo->prepare($ledgerSql);
                $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, 'Supplier Opening Debit - ' . $partyName, $supplierOpeningDebit, 0]);
                $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, 'Supplier Opening Debit - ' . $partyName, 0, $supplierOpeningDebit]);
            }
            if ($supplierOpeningCredit > 0) {
                $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
                $stmt = $pdo->prepare($ledgerSql);
                $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, 'Supplier Opening Credit - ' . $partyName, $supplierOpeningCredit, 0]);
                $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, 'Supplier Opening Credit - ' . $partyName, 0, $supplierOpeningCredit]);
            }

            // Upsert supplier sub-accounts
            $supplierSubAccounts = $input['supplierSubAccounts'] ?? [];
            $keptSupplierSubIds = [];
            $updateSuppSub = $pdo->prepare("UPDATE supplier_sub_accounts SET sub_account_name = ?, debit = ?, credit = ? WHERE id = ? AND tenant_id = ? AND supplier_id = ?");
            $insertSuppSub = $pdo->prepare("INSERT INTO supplier_sub_accounts (tenant_id, supplier_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)");
            foreach ($supplierSubAccounts as $sub) {
                $name = trim($sub['name'] ?? '');
                if ($name === '') continue;
                $debit = floatval($sub['debit'] ?? 0);
                $credit = floatval($sub['credit'] ?? 0);
                if (!empty($sub['id'])) {
                    $updateSuppSub->execute([$name, $debit, $credit, (int)$sub['id'], $tenant_id, $supplier_id]);
                    $keptSupplierSubIds[] = (int)$sub['id'];
                } else {
                    $insertSuppSub->execute([$tenant_id, $supplier_id, $name, $debit, $credit]);
                    $keptSupplierSubIds[] = (int)$pdo->lastInsertId();
                }
            }
            if (!empty($keptSupplierSubIds)) {
                $ph = implode(',', array_fill(0, count($keptSupplierSubIds), '?'));
                $pdo->prepare("DELETE FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ? AND id NOT IN ($ph)")
                    ->execute(array_merge([$tenant_id, $supplier_id], $keptSupplierSubIds));
            } else {
                $pdo->prepare("DELETE FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ?")
                    ->execute([$tenant_id, $supplier_id]);
            }

            // Replace associated companies
            $pdo->prepare("DELETE FROM supplier_associated_companies WHERE tenant_id = ? AND supplier_id = ?")
                ->execute([$tenant_id, $supplier_id]);
            if (!empty($input['associatedCompanyIds'])) {
                $assocIds = array_filter(array_map('intval', explode(',', $input['associatedCompanyIds'])));
                if (!empty($assocIds)) {
                    $assocStmt = $pdo->prepare("INSERT IGNORE INTO supplier_associated_companies (tenant_id, supplier_id, company_id) VALUES (?, ?, ?)");
                    foreach ($assocIds as $cid) {
                        $assocStmt->execute([$tenant_id, $supplier_id, $cid]);
                    }
                }
            }
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Customer + Supplier updated successfully']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
