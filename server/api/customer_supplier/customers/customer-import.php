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

/**
 * Find an existing tenant-scoped location row (country/region/city/city_zone/area)
 * by name (case-insensitive) within its parent, or create it if it doesn't exist.
 * Caches lookups within this request so the same value isn't queried/inserted twice.
 *
 * @param PDO    $pdo
 * @param string $table      e.g. 'countries', 'regions', 'cities', 'city_zones', 'areas'
 * @param string $nameCol    e.g. 'country_name'
 * @param ?string $parentCol e.g. 'country_id' (null for the top-level 'countries' table)
 * @param mixed  $parentVal  id of the parent row (null when $parentCol is null)
 * @param int    $tenantId
 * @param string $name       raw value from the import row
 * @param array  &$cache     shared cache, keyed by table
 * @return ?int   resolved id, or null if $name is blank
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

/**
 * Resolves the full Country -> Region -> City -> City Zone -> Area chain for one row.
 * Each level is only resolved if the level above it was also provided/resolved -
 * e.g. a City with no Region given is ignored, since it can't be placed in the hierarchy.
 * Returns [countryId, regionId, cityId, cityZoneId, areaId] (any of which may be null).
 */
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

/**
 * Builds a "lowercased label => id" map for a lookup table, so an import row's
 * dropdown-picked text can be matched back to its id.
 */
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

/**
 * Resolves a dropdown-driven column's text to its id. Returns:
 *  - null if the cell was blank (nothing selected)
 *  - int id if it matched
 *  - false if text was given but does not match anything in $map (caller should error the row)
 */
function resolveLookupValue(array $map, string $value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    return $map[mb_strtolower($value)] ?? false;
}

function yesNoToInt(string $value): int
{
    return (mb_strtolower(trim($value)) === 'yes') ? 1 : 0;
}

try {
    $company_id = isset($_POST['companyId']) ? (int)$_POST['companyId'] : 0;
    if (!$company_id) {
        throw new Exception('Company is required');
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $tmpPath = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception('Only .xlsx, .xls or .csv files are allowed');
    }

    // ---- Read the file ----
    $spreadsheet = IOFactory::load($tmpPath);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

    // Expected header order (row 1) - matches customer-template.php:
    // Customer Name | Shopkeeper Name | Address | P.O. Box No | Primary Phone | Secondary Phone | Email |
    // Identity Card No | License No | Customer Group | Customer Category | Sales Officer | Supplier Man | Project |
    // Country | Region | City | City Zone | Area |
    // Is Sales Tax Registered | STRN | Is Filer | NTN | Advance Income Tax % | Default Discount % |
    // Credit Limit | Credit Period Limit (Days) | Is Wholesaler | Blacklist | Out Station
    // NOTE: Opening balance is intentionally NOT part of import - it's added separately afterwards.
    array_shift($rows); // drop header row

    if (empty($rows)) {
        throw new Exception('File has no data rows');
    }

    // ---- Preload existing customers for this tenant (for duplicate check) ----
    $stmt = $pdo->prepare("SELECT LOWER(customer_name) AS cn, primary_phone FROM customers WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['cn'] . '|' . ($row['primary_phone'] ?? '')] = true;
    }

    // ---- Preload lookup maps for dropdown-driven columns (same source as customer-template.php) ----
    $customerGroupMap = buildLookupMap(
        $pdo,
        "SELECT id, type_name AS label FROM customer_types WHERE tenant_id = 0 OR tenant_id = ?",
        [$tenant_id]
    );
    $customerCategoryMap = buildLookupMap(
        $pdo,
        "SELECT id, category_name AS label FROM customer_categories WHERE tenant_id = 0 OR tenant_id = ?",
        [$tenant_id]
    );
    $employeeMap = buildLookupMap(
        $pdo,
        "SELECT id, full_name AS label FROM employees WHERE tenant_id = ?",
        [$tenant_id]
    ); // shared by Sales Officer and Supplier Man
    $projectMap = buildLookupMap(
        $pdo,
        "SELECT id, CONCAT(project_code, ' - ', project_name) AS label FROM projects WHERE tenant_id = ?",
        [$tenant_id]
    );

    // Shared cache for location lookups/creates across all rows in this import
    $locationCache = [];

    $pdo->beginTransaction();

    // ---- Get starting customer_code (locks last row so concurrent imports/adds don't collide) ----
    $stmt = $pdo->prepare("SELECT customer_code FROM customers WHERE tenant_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id, $company_id]);
    $lastCustomer = $stmt->fetch();
    $nextCodeNum = $lastCustomer ? ((int)substr($lastCustomer['customer_code'], 5)) + 1 : 1;

    // ---- Get starting id (global across tenants, matches existing add-customer logic) ----
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM customers FOR UPDATE");
    $stmt->execute();
    $nextId = (int)$stmt->fetch()['new_id'];

    $insertSql = "INSERT INTO customers
        (id, tenant_id, company_id, customer_code, customer_name, shop_name, address, po_box_no,
         primary_phone, secondary_phone, identity_card_no, license_no, email,
         country_id, region_id, city_id, city_zone_id, area_id,
         is_sales_tax_registered, strn, is_filer, ntn, advance_income_tax_percentage, default_discount_percentage,
         credit_limit, credit_period_limit_days, is_wholesaler,
         associated_sales_officer_id, customer_type_id, customer_category_id, supplier_man_id, project_id,
         is_blacklisted, is_out_station, status, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)";
    $insertStmt = $pdo->prepare($insertSql);

    $inserted = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $r) {
        $rowNum = $i + 2; // +2 because header row was row 1 and array is 0-indexed

        $customerName = trim((string)($r[0] ?? ''));
        if ($customerName === '') {
            continue; // silently skip fully blank rows
        }

        $shopName       = trim((string)($r[1] ?? '')) ?: null;
        $address        = trim((string)($r[2] ?? '')) ?: null;
        $poBoxNo        = trim((string)($r[3] ?? '')) ?: null;
        $primaryPhone   = trim((string)($r[4] ?? '')) ?: null;
        $secondaryPhone = trim((string)($r[5] ?? '')) ?: null;
        $email          = trim((string)($r[6] ?? '')) ?: null;
        $identityCard   = trim((string)($r[7] ?? '')) ?: null;
        $licenseNo      = trim((string)($r[8] ?? '')) ?: null;
        $customerGroup  = (string)($r[9] ?? '');
        $customerCat    = (string)($r[10] ?? '');
        $salesOfficer   = (string)($r[11] ?? '');
        $supplierMan    = (string)($r[12] ?? '');
        $project        = (string)($r[13] ?? '');
        $countryName    = (string)($r[14] ?? '');
        $regionName     = (string)($r[15] ?? '');
        $cityName       = (string)($r[16] ?? '');
        $cityZoneName   = (string)($r[17] ?? '');
        $areaName       = (string)($r[18] ?? '');
        $isSalesTaxReg  = (string)($r[19] ?? '');
        $strn           = trim((string)($r[20] ?? '')) ?: null;
        $isFiler        = (string)($r[21] ?? '');
        $ntn            = trim((string)($r[22] ?? '')) ?: null;
        $advanceIncTax  = $r[23] ?? 0;
        $defaultDiscount = $r[24] ?? 0;
        $creditLimit    = $r[25] ?? 0;
        $creditPeriod   = $r[26] ?? 0;
        $isWholesaler   = (string)($r[27] ?? '');
        $blacklist      = (string)($r[28] ?? '');
        $outStation     = (string)($r[29] ?? '');

        // Basic validation
        if (strlen($customerName) > 255) {
            $errors[] = ['row' => $rowNum, 'message' => 'Customer name too long'];
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
        $advanceIncTax   = is_numeric($advanceIncTax) ? floatval($advanceIncTax) : 0;
        $defaultDiscount = is_numeric($defaultDiscount) ? floatval($defaultDiscount) : 0;
        $creditLimit     = is_numeric($creditLimit) ? floatval($creditLimit) : 0;
        $creditPeriod    = is_numeric($creditPeriod) ? intval($creditPeriod) : 0;
        if ($advanceIncTax < 0 || $advanceIncTax > 100) {
            $errors[] = ['row' => $rowNum, 'message' => 'Advance Income Tax % must be between 0 and 100'];
            continue;
        }
        if ($defaultDiscount < 0 || $defaultDiscount > 100) {
            $errors[] = ['row' => $rowNum, 'message' => 'Default Discount % must be between 0 and 100'];
            continue;
        }

        // Dropdown-driven fields: must match one of the suggested values, if provided
        $customerTypeId = resolveLookupValue($customerGroupMap, $customerGroup);
        if ($customerTypeId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Customer Group '{$customerGroup}' not found - please pick from the dropdown list"];
            continue;
        }
        $customerCategoryId = resolveLookupValue($customerCategoryMap, $customerCat);
        if ($customerCategoryId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Customer Category '{$customerCat}' not found - please pick from the dropdown list"];
            continue;
        }
        $salesOfficerId = resolveLookupValue($employeeMap, $salesOfficer);
        if ($salesOfficerId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Sales Officer '{$salesOfficer}' not found - please pick from the dropdown list"];
            continue;
        }
        $supplierManId = resolveLookupValue($employeeMap, $supplierMan);
        if ($supplierManId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Supplier Man '{$supplierMan}' not found - please pick from the dropdown list"];
            continue;
        }
        $projectId = resolveLookupValue($projectMap, $project);
        if ($projectId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Project '{$project}' not found - please pick from the dropdown list"];
            continue;
        }

        // Duplicate check: same name + same phone already exists for this tenant
        $dupKey = strtolower($customerName) . '|' . ($primaryPhone ?? '');
        if (isset($existing[$dupKey])) {
            $skipped++;
            continue;
        }

        try {
            // Resolve/create Country -> Region -> City -> City Zone -> Area for this tenant.
            // Any level already present for the tenant is reused (skipped), not duplicated.
            [$countryId, $regionId, $cityId, $cityZoneId, $areaId] = resolveLocationHierarchy(
                $pdo, $tenant_id, $countryName, $regionName, $cityName, $cityZoneName, $areaName, $locationCache
            );

            $customerCode = 'CUST-' . str_pad($nextCodeNum, 5, '0', STR_PAD_LEFT);

            $insertStmt->execute([
                $nextId,
                $tenant_id,
                $company_id,
                $customerCode,
                $customerName,
                $shopName,
                $address,
                $poBoxNo,
                $primaryPhone,
                $secondaryPhone,
                $identityCard,
                $licenseNo,
                $email,
                $countryId,
                $regionId,
                $cityId,
                $cityZoneId,
                $areaId,
                yesNoToInt($isSalesTaxReg),
                $strn,
                yesNoToInt($isFiler),
                $ntn,
                $advanceIncTax,
                $defaultDiscount,
                $creditLimit,
                $creditPeriod,
                yesNoToInt($isWholesaler),
                $salesOfficerId,
                $customerTypeId,
                $customerCategoryId,
                $supplierManId,
                $projectId,
                yesNoToInt($blacklist),
                yesNoToInt($outStation),
                $user_id,
                $user_id
            ]);

            $existing[$dupKey] = true; // prevent duplicates within the same file too
            $inserted++;
            $nextId++;
            $nextCodeNum++;
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