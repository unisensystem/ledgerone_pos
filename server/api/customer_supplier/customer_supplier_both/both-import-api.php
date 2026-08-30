<?php
require_once '../../../../includes/connection.php';
require_once '../../../../vendor/autoload.php'; // composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

function buildLookupMap(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[mb_strtolower(trim($row['label']))] = (int)$row['id'];
    }
    return $map;
}

function resolveLookupValue(array $map, string $value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    return $map[mb_strtolower($value)] ?? false;
}

/**
 * Resolves a comma-separated cell of names (e.g. "Ali Khan, Sara Ahmed") against a
 * lookup map, used for Associated Companies / Salesman. Returns an array of ids,
 * or false if ANY name in the list didn't match - the whole row is invalid then.
 */
function resolveMultiLookupValue(array $map, string $value)
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }
    $ids = [];
    foreach (explode(',', $value) as $piece) {
        $piece = trim($piece);
        if ($piece === '') continue;
        $id = $map[mb_strtolower($piece)] ?? false;
        if ($id === false) {
            return false;
        }
        $ids[] = $id;
    }
    return $ids;
}

function yesNoToInt(string $value): int
{
    return (mb_strtolower(trim($value)) === 'yes') ? 1 : 0;
}

/**
 * Same findOrCreate location-hierarchy helper used by customer-import.php - resolves
 * or creates tenant-scoped Country/Region/City/CityZone/Area rows by name.
 */
function findOrCreateLocation(PDO $pdo, string $table, string $nameCol, ?string $parentCol, $parentVal, int $tenantId, string $name, array &$cache): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $cacheKey = $tenantId . '|' . ($parentVal ?? '0') . '|' . mb_strtolower($name);
    if (isset($cache[$table][$cacheKey])) {
        return $cache[$table][$cacheKey];
    }

    if ($parentCol !== null) {
        $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE tenant_id = ? AND `$parentCol` = ? AND LOWER(`$nameCol`) = LOWER(?) LIMIT 1");
        $stmt->execute([$tenantId, $parentVal, $name]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE tenant_id = ? AND LOWER(`$nameCol`) = LOWER(?) LIMIT 1");
        $stmt->execute([$tenantId, $name]);
    }
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $id = (int)$existing['id'];
    } else {
        if ($parentCol !== null) {
            $ins = $pdo->prepare("INSERT INTO `$table` (tenant_id, `$parentCol`, `$nameCol`) VALUES (?, ?, ?)");
            $ins->execute([$tenantId, $parentVal, $name]);
        } else {
            $ins = $pdo->prepare("INSERT INTO `$table` (tenant_id, `$nameCol`) VALUES (?, ?)");
            $ins->execute([$tenantId, $name]);
        }
        $id = (int)$pdo->lastInsertId();
    }

    $cache[$table][$cacheKey] = $id;
    return $id;
}

function resolveLocationHierarchy(PDO $pdo, int $tenantId, string $country, string $region, string $city, string $cityZone, string $area, array &$cache): array
{
    $countryId = $regionId = $cityId = $cityZoneId = $areaId = null;

    $country = trim($country);
    if ($country !== '') {
        $countryId = findOrCreateLocation($pdo, 'countries', 'country_name', null, null, $tenantId, $country, $cache);

        $region = trim($region);
        if ($region !== '') {
            $regionId = findOrCreateLocation($pdo, 'regions', 'region_name', 'country_id', $countryId, $tenantId, $region, $cache);

            $city = trim($city);
            if ($city !== '') {
                $cityId = findOrCreateLocation($pdo, 'cities', 'city_name', 'region_id', $regionId, $tenantId, $city, $cache);

                $cityZone = trim($cityZone);
                if ($cityZone !== '') {
                    $cityZoneId = findOrCreateLocation($pdo, 'city_zones', 'city_zone_name', 'city_id', $cityId, $tenantId, $cityZone, $cache);

                    $area = trim($area);
                    if ($area !== '') {
                        $areaId = findOrCreateLocation($pdo, 'areas', 'area_name', 'city_zone_id', $cityZoneId, $tenantId, $area, $cache);
                    }
                }
            }
        }
    }

    return [$countryId, $regionId, $cityId, $cityZoneId, $areaId];
}

try {
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $tmpPath = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception('Only .xlsx, .xls or .csv files are allowed');
    }

    $spreadsheet = IOFactory::load($tmpPath);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

    // Expected header order (row 1) - matches both-template.php:
    // Company | Party Name | Project | Address | Primary Phone | Secondary Phone | Email |
    // Identity Card No | Blacklist |
    // Customer Group | Customer Category | Brand Name | Shopkeeper Name | P.O. Box No | License No |
    // Sales Officer | Supplier Man | Out Station |
    // Country | Region | City | City Zone | Area |
    // Is Sales Tax Registered | STRN | Is Filer | NTN | Advance Income Tax % | Default Discount % |
    // Credit Limit | Credit Period Limit (Days) | Is Wholesaler |
    // Customer Opening Debit | Customer Opening Credit |
    // Associated Companies | Salesman | Supplier Category | Supplier's Brand Name | Supplier City |
    // Party Type | AIT % | Credit Days | Supplier Opening Debit | Supplier Opening Credit
    //
    // NOTE: Opening Balance Invoices and Sub Accounts are intentionally NOT part of
    // this import - only the opening balance TOTALS. Add invoices/sub accounts
    // manually after import if needed.
    array_shift($rows); // drop header row

    if (empty($rows)) {
        throw new Exception('File has no data rows');
    }

    // ---- Preload existing customers for this tenant (duplicate check: name + company) ----
    $stmt = $pdo->prepare("SELECT LOWER(customer_name) AS pn, company_id FROM customers WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['pn'] . '|' . ($row['company_id'] ?? '')] = true;
    }

    // ---- Preload lookup maps ----
    // ADJUST table/column names below if they differ from your actual schema.
    // Company is a required field on every row, so if that table is missing this
    // throws a clear top-level error (the import cannot work without it anyway).
    // The other lookups are optional columns - if their table is missing/misnamed,
    // that column is skipped for validation (value is ignored, not error'd) instead
    // of crashing the whole import.
    $missingTables = [];

    function safeBuildLookupMap(PDO $pdo, string $sql, array $params, string $key, array &$missingTables): array
    {
        try {
            return buildLookupMap($pdo, $sql, $params);
        } catch (PDOException $e) {
            $missingTables[$key] = true;
            return [];
        }
    }

    try {
        $companyMap = buildLookupMap(
            $pdo,
            "SELECT id, company_name AS label FROM companies WHERE tenant_id = ?",
            [$tenant_id]
        );
    } catch (PDOException $e) {
        throw new Exception("Could not read the Company lookup table ('companies') - please check the table name in both-import.php matches your schema.");
    }

    $projectMap = safeBuildLookupMap($pdo, "SELECT id, CONCAT(project_code, ' - ', project_name) AS label FROM projects WHERE tenant_id = ?", [$tenant_id], 'project', $missingTables);
    $customerGroupMap = safeBuildLookupMap($pdo, "SELECT id, type_name AS label FROM customer_types WHERE tenant_id = 0 OR tenant_id = ?", [$tenant_id], 'customerGroup', $missingTables);
    $customerCategoryMap = safeBuildLookupMap($pdo, "SELECT id, category_name AS label FROM customer_categories WHERE tenant_id = 0 OR tenant_id = ?", [$tenant_id], 'customerCategory', $missingTables);
    $brandMap = safeBuildLookupMap($pdo, "SELECT id, brand_name AS label FROM brands WHERE tenant_id = 0 OR tenant_id = ?", [$tenant_id], 'brand', $missingTables);
    $employeeMap = safeBuildLookupMap($pdo, "SELECT id, full_name AS label FROM employees WHERE tenant_id = ?", [$tenant_id], 'employee', $missingTables); // shared by Sales Officer, Supplier Man, and Salesman
    $supplierCategoryMap = safeBuildLookupMap($pdo, "SELECT id, category_name AS label FROM supplier_categories WHERE tenant_id = 0 OR tenant_id = ?", [$tenant_id], 'supplierCategory', $missingTables);

    $partyTypeMap = [
        'registered company' => 'registered_company',
        'unregistered' => 'unregistered',
    ];

    $locationCache = [];

    $pdo->beginTransaction();

    // ---- Get starting customer_code and supplier_code (locked, same pattern as both-add.php) ----
    $stmt = $pdo->prepare("SELECT customer_code FROM customers WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id]);
    $lastCustomer = $stmt->fetch();
    $nextCustomerNum = $lastCustomer ? ((int)substr($lastCustomer['customer_code'], 5)) + 1 : 1;

    $stmt = $pdo->prepare("SELECT supplier_code FROM suppliers WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id]);
    $lastSupplier = $stmt->fetch();
    $nextSupplierNum = $lastSupplier ? ((int)substr($lastSupplier['supplier_code'], 5)) + 1 : 1;

    $customerInsertSql = "INSERT INTO customers (
                tenant_id, company_id, customer_type_id, customer_category_id, brand_id,
                customer_code, customer_name, shop_name, address, po_box_no, primary_phone,
                secondary_phone, identity_card_no, license_no, email, country_id, region_id,
                city_id, city_zone_id, area_id, associated_sales_officer_id, supplier_man_id,
                project_id, is_sales_tax_registered, strn, is_filer, ntn,
                advance_income_tax_percentage, default_discount_percentage, opening_debit_amount,
                opening_credit_amount, credit_limit, credit_period_limit_days, is_wholesaler,
                is_blacklisted, is_out_station, is_both, linked_supplier_id, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
    $customerInsertStmt = $pdo->prepare($customerInsertSql);

    $supplierInsertSql = "INSERT INTO suppliers (
                tenant_id, company_id, salesman_id, project_id, supplier_code, supplier_name,
                brand_name, address, city, primary_phone, secondary_phone, identity_card_no, email,
                opening_debit_amount, opening_credit_amount, ait_percent, credit_days, party_type,
                is_blacklisted, is_both, linked_customer_id, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
    $supplierInsertStmt = $pdo->prepare($supplierInsertSql);

    $ledgerStmt = $pdo->prepare("
        INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)
    ");

    $assocCompanyStmt = $pdo->prepare("
        INSERT INTO supplier_associated_companies (tenant_id, supplier_id, company_id) VALUES (?, ?, ?)
    ");

    $inserted = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $r) {
        $rowNum = $i + 2;

        $companyTxt = trim((string)($r[0] ?? ''));
        $partyName  = trim((string)($r[1] ?? ''));
        if ($partyName === '') {
            continue; // silently skip fully blank rows
        }

        $projectTxt        = (string)($r[2] ?? '');
        $address            = trim((string)($r[3] ?? '')) ?: null;
        $primaryPhone       = trim((string)($r[4] ?? '')) ?: null;
        $secondaryPhone     = trim((string)($r[5] ?? '')) ?: null;
        $email              = trim((string)($r[6] ?? '')) ?: null;
        $identityCard       = trim((string)($r[7] ?? '')) ?: null;
        $blacklist          = (string)($r[8] ?? 'No');

        $customerGroupTxt   = (string)($r[9] ?? '');
        $customerCategoryTxt = (string)($r[10] ?? '');
        $brandTxt           = (string)($r[11] ?? '');
        $shopkeeperName     = trim((string)($r[12] ?? '')) ?: null;
        $poBoxNo            = trim((string)($r[13] ?? '')) ?: null;
        $licenseNo          = trim((string)($r[14] ?? '')) ?: null;
        $salesOfficerTxt    = (string)($r[15] ?? '');
        $supplierManTxt     = (string)($r[16] ?? '');
        $outStation         = (string)($r[17] ?? 'No');

        $countryTxt         = (string)($r[18] ?? '');
        $regionTxt          = (string)($r[19] ?? '');
        $cityTxt            = (string)($r[20] ?? '');
        $cityZoneTxt        = (string)($r[21] ?? '');
        $areaTxt            = (string)($r[22] ?? '');

        $isSalesTaxReg      = (string)($r[23] ?? 'No');
        $strn               = trim((string)($r[24] ?? '')) ?: null;
        $isFiler            = (string)($r[25] ?? 'No');
        $ntn                = trim((string)($r[26] ?? '')) ?: null;
        $advanceIncTax      = $r[27] ?? 0;
        $defaultDiscount    = $r[28] ?? 0;
        $creditLimit        = $r[29] ?? 0;
        $creditPeriod       = $r[30] ?? 0;
        $isWholesaler       = (string)($r[31] ?? 'No');

        $customerOpeningDebit  = $r[32] ?? 0;
        $customerOpeningCredit = $r[33] ?? 0;

        $assocCompaniesTxt  = (string)($r[34] ?? '');
        $salesmanTxt        = (string)($r[35] ?? '');
        $supplierCategoryTxt = (string)($r[36] ?? '');
        $supplierBrandName  = trim((string)($r[37] ?? '')) ?: null;
        $supplierCity       = trim((string)($r[38] ?? '')) ?: null;
        $partyTypeTxt       = (string)($r[39] ?? '');
        $aitPercent         = $r[40] ?? 0;
        $creditDays         = $r[41] ?? 0;
        $supplierOpeningDebit  = $r[42] ?? 0;
        $supplierOpeningCredit = $r[43] ?? 0;

        // Basic validation
        if (strlen($partyName) > 255) {
            $errors[] = ['row' => $rowNum, 'message' => 'Party Name too long'];
            continue;
        }
        if ($companyTxt === '') {
            $errors[] = ['row' => $rowNum, 'message' => 'Company is required'];
            continue;
        }
        if ($primaryPhone && !preg_match('/^[0-9]{1,15}$/', $primaryPhone)) {
            $errors[] = ['row' => $rowNum, 'message' => 'Invalid primary phone (numbers only, max 15 digits)'];
            continue;
        }
        if ($secondaryPhone && !preg_match('/^[0-9]{1,15}$/', $secondaryPhone)) {
            $errors[] = ['row' => $rowNum, 'message' => 'Invalid secondary phone (numbers only, max 15 digits)'];
            continue;
        }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['row' => $rowNum, 'message' => 'Invalid email address'];
            continue;
        }
        if ($identityCard && !preg_match('/^[0-9]{1,15}$/', $identityCard)) {
            $errors[] = ['row' => $rowNum, 'message' => 'Invalid identity card number (numbers only, max 15 digits)'];
            continue;
        }

        $advanceIncTax  = is_numeric($advanceIncTax) ? floatval($advanceIncTax) : 0;
        $defaultDiscount = is_numeric($defaultDiscount) ? floatval($defaultDiscount) : 0;
        $creditLimit    = is_numeric($creditLimit) ? floatval($creditLimit) : 0;
        $creditPeriod   = is_numeric($creditPeriod) ? intval($creditPeriod) : 0;
        $customerOpeningDebit  = is_numeric($customerOpeningDebit) ? floatval($customerOpeningDebit) : 0;
        $customerOpeningCredit = is_numeric($customerOpeningCredit) ? floatval($customerOpeningCredit) : 0;
        $aitPercent     = is_numeric($aitPercent) ? floatval($aitPercent) : 0;
        $creditDays     = is_numeric($creditDays) ? intval($creditDays) : 0;
        $supplierOpeningDebit  = is_numeric($supplierOpeningDebit) ? floatval($supplierOpeningDebit) : 0;
        $supplierOpeningCredit = is_numeric($supplierOpeningCredit) ? floatval($supplierOpeningCredit) : 0;

        if ($advanceIncTax < 0 || $advanceIncTax > 100) {
            $errors[] = ['row' => $rowNum, 'message' => 'Advance Income Tax % must be between 0 and 100'];
            continue;
        }
        if ($defaultDiscount < 0 || $defaultDiscount > 100) {
            $errors[] = ['row' => $rowNum, 'message' => 'Default Discount % must be between 0 and 100'];
            continue;
        }
        if ($aitPercent < 0 || $aitPercent > 100) {
            $errors[] = ['row' => $rowNum, 'message' => 'AIT % must be between 0 and 100'];
            continue;
        }

        // Dropdown-driven fields. If a lookup's table was missing (see
        // $missingTables above), that field is skipped entirely - the value is
        // ignored and the FK stays null, rather than erroring every row.
        $companyId = resolveLookupValue($companyMap, $companyTxt);
        if ($companyId === false || $companyId === null) {
            $errors[] = ['row' => $rowNum, 'message' => "Company '{$companyTxt}' not found - please pick from the dropdown list"];
            continue;
        }
        if (isset($missingTables['project'])) {
            $projectId = null;
        } else {
            $projectId = resolveLookupValue($projectMap, $projectTxt);
            if ($projectId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Project '{$projectTxt}' not found - please pick from the dropdown list"];
                continue;
            }
        }
        if (isset($missingTables['customerGroup'])) {
            $customerTypeId = null;
        } else {
            $customerTypeId = resolveLookupValue($customerGroupMap, $customerGroupTxt);
            if ($customerTypeId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Customer Group '{$customerGroupTxt}' not found - please pick from the dropdown list"];
                continue;
            }
        }
        if (isset($missingTables['customerCategory'])) {
            $customerCategoryId = null;
        } else {
            $customerCategoryId = resolveLookupValue($customerCategoryMap, $customerCategoryTxt);
            if ($customerCategoryId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Customer Category '{$customerCategoryTxt}' not found - please pick from the dropdown list"];
                continue;
            }
        }
        if (isset($missingTables['brand'])) {
            $brandId = null;
        } else {
            $brandId = resolveLookupValue($brandMap, $brandTxt);
            if ($brandId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Brand Name '{$brandTxt}' not found - please pick from the dropdown list"];
                continue;
            }
        }
        if (isset($missingTables['employee'])) {
            $salesOfficerId = null;
            $supplierManId = null;
        } else {
            $salesOfficerId = resolveLookupValue($employeeMap, $salesOfficerTxt);
            if ($salesOfficerId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Sales Officer '{$salesOfficerTxt}' not found - please pick from the dropdown list"];
                continue;
            }
            $supplierManId = resolveLookupValue($employeeMap, $supplierManTxt);
            if ($supplierManId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Supplier Man '{$supplierManTxt}' not found - please pick from the dropdown list"];
                continue;
            }
        }
        if (isset($missingTables['supplierCategory'])) {
            $supplierCategoryId = null;
        } else {
            $supplierCategoryId = resolveLookupValue($supplierCategoryMap, $supplierCategoryTxt);
            if ($supplierCategoryId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Supplier Category '{$supplierCategoryTxt}' not found - please pick from the dropdown list"];
                continue;
            }
        }

        $partyTypeValue = null;
        $partyTypeNormalized = mb_strtolower(trim($partyTypeTxt));
        if ($partyTypeNormalized !== '') {
            if (!isset($partyTypeMap[$partyTypeNormalized])) {
                $errors[] = ['row' => $rowNum, 'message' => "Party Type '{$partyTypeTxt}' not recognized - must be 'Registered Company' or 'Unregistered'"];
                continue;
            }
            $partyTypeValue = $partyTypeMap[$partyTypeNormalized];
        }

        // Associated Companies / Salesman - comma-separated names resolved to id lists
        $assocCompanyIds = resolveMultiLookupValue($companyMap, $assocCompaniesTxt);
        if ($assocCompanyIds === false) {
            $errors[] = ['row' => $rowNum, 'message' => "One or more names in Associated Companies not found - please use exact company names, comma-separated"];
            continue;
        }
        $salesmanIds = resolveMultiLookupValue($employeeMap, $salesmanTxt);
        if ($salesmanIds === false) {
            $errors[] = ['row' => $rowNum, 'message' => "One or more names in Salesman not found - please use exact employee names, comma-separated"];
            continue;
        }

        // Territory hierarchy (Country -> Region -> City -> City Zone -> Area) - typed
        // freely, auto-created if it doesn't already exist for this tenant.
        [$countryId, $regionId, $cityId, $cityZoneId, $areaId] = resolveLocationHierarchy(
            $pdo, $tenant_id, $countryTxt, $regionTxt, $cityTxt, $cityZoneTxt, $areaTxt, $locationCache
        );

        // Duplicate check: same party name + same company already exists for this tenant
        $dupKey = strtolower($partyName) . '|' . $companyId;
        if (isset($existing[$dupKey])) {
            $skipped++;
            continue;
        }

        try {
            $customer_code = 'CUST-' . str_pad($nextCustomerNum, 5, '0', STR_PAD_LEFT);
            $supplier_code = 'SUPP-' . str_pad($nextSupplierNum, 5, '0', STR_PAD_LEFT);

            $isBlacklisted = yesNoToInt($blacklist);

            $customerInsertStmt->execute([
                $tenant_id, $companyId, $customerTypeId, $customerCategoryId, $brandId,
                $customer_code, $partyName, $shopkeeperName, $address, $poBoxNo, $primaryPhone,
                $secondaryPhone, $identityCard, $licenseNo, $email, $countryId, $regionId,
                $cityId, $cityZoneId, $areaId, $salesOfficerId, $supplierManId,
                $projectId, yesNoToInt($isSalesTaxReg), $strn, yesNoToInt($isFiler), $ntn,
                $advanceIncTax, $defaultDiscount, $customerOpeningDebit,
                $customerOpeningCredit, $creditLimit, $creditPeriod, yesNoToInt($isWholesaler),
                $isBlacklisted, yesNoToInt($outStation), $supplier_id, $user_id, $user_id
            ]);
            $customer_id = (int)$pdo->lastInsertId(); // relies on AUTO_INCREMENT on customers.id

            $salesmanIdString = !empty($salesmanIds) ? implode(',', $salesmanIds) : null;

            $supplierInsertStmt->execute([
                $tenant_id, $companyId, $salesmanIdString, $projectId, $supplier_code, $partyName,
                $supplierBrandName, $address, $supplierCity, $primaryPhone, $secondaryPhone,
                $identityCard, $email, $supplierOpeningDebit, $supplierOpeningCredit,
                $aitPercent, $creditDays, $partyTypeValue, $isBlacklisted, $customer_id,
                $user_id, $user_id
            ]);
            $supplier_id = (int)$pdo->lastInsertId();

            // Link customer -> supplier now that both real ids exist
            $pdo->prepare("UPDATE customers SET linked_supplier_id = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$supplier_id, $customer_id, $tenant_id]);

            // Customer opening balance ledger (totals only, no itemized invoices - see NOTE above)
            if ($customerOpeningDebit > 0) {
                $desc = 'Customer opening debit balance (import)';
                $ledgerStmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, $desc, $customerOpeningDebit, 0]);
                $ledgerStmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, $desc, 0, $customerOpeningDebit]);
            }
            if ($customerOpeningCredit > 0) {
                $desc = 'Customer opening credit balance (import)';
                $ledgerStmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 90, $desc, $customerOpeningCredit, 0]);
                $ledgerStmt->execute([$tenant_id, 'customer_opening', 'customers', $customer_id, 2, $desc, 0, $customerOpeningCredit]);
            }

            // Supplier opening balance ledger
            if ($supplierOpeningDebit > 0) {
                $desc = 'Supplier Opening Debit - ' . $partyName . ' (import)';
                $ledgerStmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, $desc, $supplierOpeningDebit, 0]);
                $ledgerStmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, $desc, 0, $supplierOpeningDebit]);
            }
            if ($supplierOpeningCredit > 0) {
                $desc = 'Supplier Opening Credit - ' . $partyName . ' (import)';
                $ledgerStmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, $desc, $supplierOpeningCredit, 0]);
                $ledgerStmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, $desc, 0, $supplierOpeningCredit]);
            }

            // Associated companies
            foreach ($assocCompanyIds as $cid) {
                $assocCompanyStmt->execute([$tenant_id, $supplier_id, $cid]);
            }

            $existing[$dupKey] = true;
            $inserted++;
            $nextCustomerNum++;
            $nextSupplierNum++;
        } catch (Exception $rowEx) {
            $errors[] = ['row' => $rowNum, 'message' => $rowEx->getMessage()];
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'errors'   => $errors
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}