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
 * Builds a "lowercased label => id" map for a lookup table, so an import row's
 * dropdown-picked text can be matched back to its id. Same helper pattern as
 * customer-import.php.
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
    // Company is OPTIONAL for products (unlike Customer import) - applies to the whole batch if given
    $company_id = !empty($_POST['companyId']) ? (int)$_POST['companyId'] : null;

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

    // Expected header order (row 1) - matches product-template.php:
    // Product Name | Product Type | Default Unit | Category | Subcategory | Vendor | Description |
    // Unit Conversion Factor | Sales Tax Type | Inventory Account |
    // Purchase Price | Trade Price | Wholesale Price | MRP | Default Discount % |
    // Min Stock Level | Max Stock Level | Manufacturing Date | Expiry Date |
    // QR Code | Barcode | Is Active | Stock Affects | Invoice Affects |
    // Scheme Promo Qty | Scheme Bonus Qty | Scheme To Qty | Scheme To Rs
    //
    // NOTE: This import only supports Default Unit products. UOM Group products
    // and Branch-wise Opening Stock are intentionally NOT part of this import.
    array_shift($rows); // drop header row

    if (empty($rows)) {
        throw new Exception('File has no data rows');
    }

    // ---- Preload existing products for this tenant (for duplicate check: name + company) ----
    $stmt = $pdo->prepare("SELECT LOWER(name) AS pn, company_id FROM products WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['pn'] . '|' . ($row['company_id'] ?? '')] = true;
    }

    // ---- Preload lookup maps for dropdown-driven columns ----
    // ADJUST table/column names below if they differ from your actual schema.
    $unitMap = buildLookupMap(
        $pdo,
        "SELECT id, uom_name AS label FROM uom WHERE tenant_id = 0 OR tenant_id = ?",
        [$tenant_id]
    );
    // Full unit records (need scope/base-unit info for conversion-factor validation)
    $unitDetails = [];
    $stmt = $pdo->prepare("SELECT id, uom_name, unit_scope, is_base_unit FROM uom WHERE tenant_id = 0 OR tenant_id = ?");
    $stmt->execute([$tenant_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $unitDetails[(int)$u['id']] = $u;
    }

    $categoryMap = buildLookupMap(
        $pdo,
        "SELECT id, category_name AS label FROM categories WHERE tenant_id = 0 OR tenant_id = ?",
        [$tenant_id]
    );
    $vendorMap = buildLookupMap(
        $pdo,
        "SELECT id, supplier_name AS label FROM suppliers WHERE tenant_id = ?",
        [$tenant_id]
    );
    $taxRegimeMap = buildLookupMap(
        $pdo,
        "SELECT id, regime_name AS label FROM tax_regimes WHERE tenant_id = 0 OR tenant_id = ?",
        [$tenant_id]
    );
    $accountMap = buildLookupMap(
        $pdo,
        "SELECT id, name AS label FROM accounts WHERE tenant_id = 0 OR tenant_id = ?",
        [$tenant_id]
    );

    // Subcategory map is built per-category on demand since the same subcategory
    // name could exist under different categories - cached within this request.
    $subcategoryMapCache = [];
    function resolveSubcategory(PDO $pdo, int $tenantId, int $categoryId, string $name, array &$cache)
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        if (!isset($cache[$categoryId])) {
            $stmt = $pdo->prepare("SELECT id, subcategory_name AS label FROM subcategories WHERE category_id = ? AND (tenant_id = 0 OR tenant_id = ?)");
            $stmt->execute([$categoryId, $tenantId]);
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[mb_strtolower(trim($row['label']))] = (int)$row['id'];
            }
            $cache[$categoryId] = $map;
        }
        return $cache[$categoryId][mb_strtolower($name)] ?? false;
    }

    $pdo->beginTransaction();

    // ---- Get starting product code (locks last row so concurrent imports/adds don't collide) ----
    $stmt = $pdo->prepare("SELECT code FROM products WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id]);
    $lastProduct = $stmt->fetch();
    $nextCodeNum = $lastProduct ? ((int)substr($lastProduct['code'], -6)) + 1 : 1;

    $insertSql = "INSERT INTO products
        (tenant_id, company_id, code, name, product_type, uom_type, default_unit_id, uom_group_id, product_conversion_factor,
         category_id, subcategory_id, inventory_account_id, vendor_id, parent_product_id, description, qr_code, barcode,
         purchase_price, trade_price, wholesale_price, mrp, price_entry_unit_id, trade_price_entry_value, mrp_entry_value,
         default_discount, trade_offer_discount, default_foc, carton_conversion, tax_regime_id,
         min_stock_level, max_stock_level, manufacturing_date, expiry_date, photo, is_active, stock_affects, invoice_affects, created_by)
        VALUES (?, ?, ?, ?, ?, 'unit', ?, NULL, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, ?, 0, 0, 0, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)";
    $insertStmt = $pdo->prepare($insertSql);

    $schemeStmt = $pdo->prepare("
        INSERT INTO product_schemes (tenant_id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $inserted = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $r) {
        $rowNum = $i + 2; // +2 because header row was row 1 and array is 0-indexed

        $name = trim((string)($r[0] ?? ''));
        if ($name === '') {
            continue; // silently skip fully blank rows
        }

        $productType    = strtolower(trim((string)($r[1] ?? 'physical'))) === 'service' ? 'service' : 'physical';
        $defaultUnitTxt = (string)($r[2] ?? '');
        $categoryTxt    = (string)($r[3] ?? '');
        $subcategoryTxt = (string)($r[4] ?? '');
        $vendorTxt      = (string)($r[5] ?? '');
        $description    = trim((string)($r[6] ?? '')) ?: null;
        $conversionFactorRaw = $r[7] ?? '';
        $taxRegimeTxt   = (string)($r[8] ?? '');
        $accountTxt     = (string)($r[9] ?? '');
        $purchasePrice  = $r[10] ?? 0;
        $tradePrice     = $r[11] ?? 0;
        $wholesalePrice = $r[12] ?? 0;
        $mrp            = $r[13] ?? 0;
        $defaultDiscount = $r[14] ?? 0;
        $minStock       = $r[15] ?? 0;
        $maxStock       = $r[16] ?? 0;
        $manufacturingDate = trim((string)($r[17] ?? '')) ?: null;
        $expiryDate     = trim((string)($r[18] ?? '')) ?: null;
        $qrCode         = trim((string)($r[19] ?? '')) ?: null;
        $barcode        = trim((string)($r[20] ?? '')) ?: null;
        $isActive       = (string)($r[21] ?? 'Yes');
        $stockAffects   = (string)($r[22] ?? 'Yes');
        $invoiceAffects = (string)($r[23] ?? 'Yes');
        $schemePromoQty = $r[24] ?? 0;
        $schemeBonusQty = $r[25] ?? 0;
        $schemeToQty    = $r[26] ?? 0;
        $schemeToRs     = $r[27] ?? 0;

        // Basic validation
        if (strlen($name) > 255) {
            $errors[] = ['row' => $rowNum, 'message' => 'Product name too long'];
            continue;
        }
        if (trim($defaultUnitTxt) === '') {
            $errors[] = ['row' => $rowNum, 'message' => 'Default Unit is required'];
            continue;
        }

        $purchasePrice   = is_numeric($purchasePrice) ? floatval($purchasePrice) : 0;
        $tradePrice      = is_numeric($tradePrice) ? floatval($tradePrice) : 0;
        $wholesalePrice  = is_numeric($wholesalePrice) ? floatval($wholesalePrice) : 0;
        $mrp             = is_numeric($mrp) ? floatval($mrp) : 0;
        $defaultDiscount = is_numeric($defaultDiscount) ? floatval($defaultDiscount) : 0;
        $minStock        = is_numeric($minStock) ? floatval($minStock) : 0;
        $maxStock        = is_numeric($maxStock) ? floatval($maxStock) : 0;
        if ($defaultDiscount < 0 || $defaultDiscount > 100) {
            $errors[] = ['row' => $rowNum, 'message' => 'Default Discount % must be between 0 and 100'];
            continue;
        }

        // Dropdown-driven fields
        $defaultUnitId = resolveLookupValue($unitMap, $defaultUnitTxt);
        if ($defaultUnitId === false || $defaultUnitId === null) {
            $errors[] = ['row' => $rowNum, 'message' => "Default Unit '{$defaultUnitTxt}' not found - please pick from the dropdown list"];
            continue;
        }
        $categoryId = resolveLookupValue($categoryMap, $categoryTxt);
        if ($categoryId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Category '{$categoryTxt}' not found - please pick from the dropdown list"];
            continue;
        }
        $subcategoryId = null;
        if ($categoryId !== null) {
            $subcategoryId = resolveSubcategory($pdo, $tenant_id, $categoryId, $subcategoryTxt, $subcategoryMapCache);
            if ($subcategoryId === false) {
                $errors[] = ['row' => $rowNum, 'message' => "Subcategory '{$subcategoryTxt}' not found under Category '{$categoryTxt}'"];
                continue;
            }
        } elseif (trim($subcategoryTxt) !== '') {
            $errors[] = ['row' => $rowNum, 'message' => "Subcategory given but Category is blank - please also pick a Category"];
            continue;
        }
        $vendorId = resolveLookupValue($vendorMap, $vendorTxt);
        if ($vendorId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Vendor '{$vendorTxt}' not found - please pick from the dropdown list"];
            continue;
        }
        $taxRegimeId = resolveLookupValue($taxRegimeMap, $taxRegimeTxt);
        if ($taxRegimeId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Sales Tax Type '{$taxRegimeTxt}' not found - please pick from the dropdown list"];
            continue;
        }
        $accountId = resolveLookupValue($accountMap, $accountTxt);
        if ($accountId === false) {
            $errors[] = ['row' => $rowNum, 'message' => "Inventory Account '{$accountTxt}' not found - please pick from the dropdown list"];
            continue;
        }

        // Conversion factor: required only if the chosen unit is per-product scope and not the base unit
        $unit = $unitDetails[$defaultUnitId] ?? null;
        $productConversionFactor = null;
        if ($unit && $unit['unit_scope'] === 'per_product' && (int)$unit['is_base_unit'] === 0) {
            if (!is_numeric($conversionFactorRaw) || floatval($conversionFactorRaw) <= 0) {
                $errors[] = ['row' => $rowNum, 'message' => "Unit Conversion Factor is required for unit '{$unit['uom_name']}'"];
                continue;
            }
            $productConversionFactor = floatval($conversionFactorRaw);
        }

        // Duplicate check: same name + same company already exists for this tenant
        $dupKey = strtolower($name) . '|' . ($company_id ?? '');
        if (isset($existing[$dupKey])) {
            $skipped++;
            continue;
        }

        try {
            $code = 'PROD-' . str_pad($nextCodeNum, 6, '0', STR_PAD_LEFT);

            $insertStmt->execute([
                $tenant_id,
                $company_id,
                $code,
                $name,
                $productType,
                $defaultUnitId,
                $productConversionFactor,
                $categoryId,
                $subcategoryId,
                $accountId,
                $vendorId,
                $description,
                $qrCode,
                $barcode,
                $purchasePrice,
                $tradePrice,
                $wholesalePrice,
                $mrp,
                $defaultDiscount,
                $taxRegimeId,
                $minStock,
                $maxStock,
                $manufacturingDate,
                $expiryDate,
                yesNoToInt($isActive),
                yesNoToInt($stockAffects),
                yesNoToInt($invoiceAffects),
                $user_id
            ]);

            $product_id = $pdo->lastInsertId();

            // Basic scheme (one per row) - only insert if at least one value was given
            $schemePromoQty = is_numeric($schemePromoQty) ? floatval($schemePromoQty) : 0;
            $schemeBonusQty = is_numeric($schemeBonusQty) ? floatval($schemeBonusQty) : 0;
            $schemeToQty    = is_numeric($schemeToQty) ? floatval($schemeToQty) : 0;
            $schemeToRs     = is_numeric($schemeToRs) ? floatval($schemeToRs) : 0;
            if ($schemePromoQty > 0 || $schemeBonusQty > 0 || $schemeToQty > 0 || $schemeToRs > 0) {
                $schemeStmt->execute([
                    $tenant_id,
                    $product_id,
                    $defaultUnitId,
                    $schemePromoQty,
                    $schemeBonusQty,
                    $schemeToQty,
                    $schemeToRs
                ]);
            }

            $existing[$dupKey] = true; // prevent duplicates within the same file too
            $inserted++;
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