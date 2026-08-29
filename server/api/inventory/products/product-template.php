<?php
require_once '../../../../includes/connection.php';
require_once '../../../../vendor/autoload.php'; // composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo 'Unauthorized - Please login again';
    exit;
}

// ---- Fetch this tenant's lookup values so the template can suggest them via dropdowns ----
// (Same source data used by the Product Entry form's dropdowns.)
// ADJUST table/column names below if they differ from your actual schema.

$stmt = $pdo->prepare("SELECT uom_name FROM uom WHERE tenant_id = 0 OR tenant_id = ? ORDER BY uom_name");
$stmt->execute([$tenant_id]);
$units = $stmt->fetchAll(PDO::FETCH_COLUMN);

// UOM Groups (e.g. "Standard") - ADJUST table/column name if different in your schema.
$stmt = $pdo->prepare("SELECT group_name FROM uom_groups WHERE tenant_id = 0 OR tenant_id = ? ORDER BY group_name");
$stmt->execute([$tenant_id]);
$uomGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT category_name FROM categories WHERE tenant_id = 0 OR tenant_id = ? ORDER BY category_name");
$stmt->execute([$tenant_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT supplier_name FROM suppliers WHERE tenant_id = ? ORDER BY supplier_name");
$stmt->execute([$tenant_id]);
$vendors = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT regime_name FROM tax_regimes WHERE tenant_id = 0 OR tenant_id = ? ORDER BY regime_name");
$stmt->execute([$tenant_id]);
$taxRegimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// "Inventory Account" on the Product form is NOT the full chart of accounts -
// it is the fixed inventory-account-type list (same options shown in the
// Product Entry form's "Select Account" dropdown).
$accounts = [
    'Finished Goods',
    'Packing Materials',
    'Raw Materials',
    'Semi-Finished Goods',
    'Trading Inventory',
    'WIP-Internal',
];
// If these values actually live in a table in your schema (e.g. an
// `inventory_account_types` table), replace the array above with a query like:
// $stmt = $pdo->prepare("SELECT type_name FROM inventory_account_types WHERE tenant_id = 0 OR tenant_id = ? ORDER BY type_name");
// $stmt->execute([$tenant_id]);
// $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

$productTypes = ['Physical', 'Service'];
$uomTypes = ['Default Unit', 'UOM Group'];
$yesNo = ['Yes', 'No'];

// Defaults pre-filled on every row
$defaultProductType      = 'Physical';
$defaultUomType          = 'Default Unit'; // standard switch value
$defaultUomGroup         = 'Standard';     // used only when UOM Type = "UOM Group"
$defaultInventoryAccount = 'Trading Inventory';

// ---- Build workbook ----
$spreadsheet = new Spreadsheet();

// ===== Sheet 1: Products (the sheet the user fills in) =====
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Products');

$headers = [
    'A' => 'Product Name',
    'B' => 'Product Type',
    'C' => 'UOM Type',
    'D' => 'Default Unit',
    'E' => 'UOM Group',
    'F' => 'Category',
    'G' => 'Subcategory',
    'H' => 'Vendor',
    'I' => 'Description',
    'J' => 'Unit Conversion Factor',
    'K' => 'Sales Tax Type',
    'L' => 'Inventory Account',
    'M' => 'Purchase Price',
    'N' => 'Trade Price',
    'O' => 'Wholesale Price',
    'P' => 'MRP',
    'Q' => 'Default Discount %',
    'R' => 'Min Stock Level',
    'S' => 'Max Stock Level',
    'T' => 'Manufacturing Date',
    'U' => 'Expiry Date',
    'V' => 'QR Code',
    'W' => 'Barcode',
    'X' => 'Is Active',
    'Y' => 'Stock Affects',
    'Z' => 'Invoice Affects',
    'AA' => 'Scheme Promo Qty',
    'AB' => 'Scheme Bonus Qty',
    'AC' => 'Scheme To Qty',
    'AD' => 'Scheme To Rs',
];

foreach ($headers as $col => $label) {
    $sheet->setCellValue($col . '1', $label);
}

// Required-field markers + header styling
$sheet->setCellValue('A1', 'Product Name *');
$sheet->setCellValue('D1', "Default Unit\n(if UOM Type = Default Unit)");
$sheet->setCellValue('E1', "UOM Group\n(if UOM Type = UOM Group)");

$headerRange = 'A1:AD1';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('2C3E50');
$sheet->getStyle($headerRange)->getAlignment()->setWrapText(true);
$sheet->getRowDimension(1)->setRowHeight(42);
$sheet->freezePane('A2');

foreach (array_keys($headers) as $col) {
    $sheet->getColumnDimension($col)->setWidth(18);
}
$sheet->getColumnDimension('I')->setWidth(28);

$maxRow = 500; // rows available for data entry

// Two example rows so the expected format is clear for BOTH switch positions
$sheet->fromArray([
    'Blue Ballpoint Pen', $defaultProductType, 'Default Unit',
    !empty($units) ? $units[0] : '', '', // UOM Group left blank - Default Unit mode
    !empty($categories) ? $categories[0] : '',
    '',
    !empty($vendors) ? $vendors[0] : '',
    '', '',
    !empty($taxRegimes) ? $taxRegimes[0] : '',
    $defaultInventoryAccount,
    0, 0, 0, 0, 0, 0, 0, '', '', '', '',
    'Yes', 'Yes', 'Yes',
    0, 0, 0, 0
], null, 'A2');

$sheet->fromArray([
    'Cooking Oil 5L Can', $defaultProductType, 'UOM Group',
    '', $defaultUomGroup, // Default Unit left blank - UOM Group mode
    !empty($categories) ? $categories[0] : '',
    '',
    !empty($vendors) ? $vendors[0] : '',
    '', '',
    !empty($taxRegimes) ? $taxRegimes[0] : '',
    $defaultInventoryAccount,
    0, 0, 0, 0, 0, 0, 0, '', '', '', '',
    'Yes', 'Yes', 'Yes',
    0, 0, 0, 0
], null, 'A3');

// Pre-fill Product Type, UOM Type, UOM Group and Inventory Account with the
// standard defaults for every blank data row from row 4 onward. UOM Type
// defaults to "Default Unit" - the user only changes it (and fills the "UOM
// Group" column instead of "Default Unit") for products that use a UOM
// Group. UOM Group defaults to "Standard" so it's already correct the moment
// someone switches UOM Type, without having to type it themselves.
for ($row = 4; $row <= $maxRow; $row++) {
    $sheet->setCellValue('B' . $row, $defaultProductType);
    $sheet->setCellValue('C' . $row, $defaultUomType);
    $sheet->setCellValue('E' . $row, $defaultUomGroup);
    $sheet->setCellValue('L' . $row, $defaultInventoryAccount);
}

// ===== Sheet 2: hidden "Lookups" sheet backing the dropdowns =====
$lookupSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Lookups');
$spreadsheet->addSheet($lookupSheet);

// IMPORTANT: fromArray() writes a flat (1D) array as a single row, left-to-right.
// These lists must go DOWN a column (one value per row) for the dropdown to see
// every item, so each list is written with an explicit loop instead.
function writeLookupColumn($sheet, string $col, array $values): void
{
    foreach (array_values($values) as $idx => $value) {
        $sheet->setCellValue($col . ($idx + 1), $value);
    }
}

writeLookupColumn($lookupSheet, 'A', $productTypes);
writeLookupColumn($lookupSheet, 'B', $units);
writeLookupColumn($lookupSheet, 'C', $categories);
writeLookupColumn($lookupSheet, 'D', $vendors);
writeLookupColumn($lookupSheet, 'E', $taxRegimes);
writeLookupColumn($lookupSheet, 'F', $accounts);
writeLookupColumn($lookupSheet, 'G', $yesNo);
writeLookupColumn($lookupSheet, 'H', $uomTypes);
writeLookupColumn($lookupSheet, 'I', $uomGroups);

$lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

// ---- Helper: apply a "pick from list" dropdown to a column for a range of rows ----
function applyListDropdown($sheet, string $col, int $firstRow, int $lastRow, string $lookupRange, bool $required = false): void
{
    for ($row = $firstRow; $row <= $lastRow; $row++) {
        $validation = $sheet->getCell($col . $row)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(!$required);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid selection');
        $validation->setError('Please pick a value from the dropdown list only.');
        $validation->setPromptTitle('Select from list');
        $validation->setPrompt('Choose one of the values suggested for this column.');
        $validation->setFormula1($lookupRange);
    }
}

applyListDropdown($sheet, 'B', 2, $maxRow, 'Lookups!$A$1:$A$' . count($productTypes), true);
applyListDropdown($sheet, 'C', 2, $maxRow, 'Lookups!$H$1:$H$' . count($uomTypes), true);

if (!empty($units)) {
    applyListDropdown($sheet, 'D', 2, $maxRow, 'Lookups!$B$1:$B$' . count($units));
}
if (!empty($uomGroups)) {
    applyListDropdown($sheet, 'E', 2, $maxRow, 'Lookups!$I$1:$I$' . count($uomGroups));
}
if (!empty($categories)) {
    applyListDropdown($sheet, 'F', 2, $maxRow, 'Lookups!$C$1:$C$' . count($categories));
}
// Subcategory (G) is intentionally free-text - it depends on which Category was
// picked, and Excel can't easily drive a dependent dropdown off free-typed text.
// Type it exactly as it appears under that Category in the system.
if (!empty($vendors)) {
    applyListDropdown($sheet, 'H', 2, $maxRow, 'Lookups!$D$1:$D$' . count($vendors));
}
if (!empty($taxRegimes)) {
    applyListDropdown($sheet, 'K', 2, $maxRow, 'Lookups!$E$1:$E$' . count($taxRegimes));
}
if (!empty($accounts)) {
    applyListDropdown($sheet, 'L', 2, $maxRow, 'Lookups!$F$1:$F$' . count($accounts));
}

// Yes/No dropdowns
foreach (['X', 'Y', 'Z'] as $col) {
    applyListDropdown($sheet, $col, 2, $maxRow, 'Lookups!$G$1:$G$2');
}

// ===== Sheet 3: Instructions =====
$infoSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Instructions');
$spreadsheet->addSheet($infoSheet);
$infoSheet->fromArray([
    ['LedgerOne ERP - Product Import Template'],
    [''],
    ['1. Fill data in the "Products" sheet. Do not change the header row.'],
    ['2. Product Name is required.'],
    ['3. UOM Type is a switch with two values:'],
    ['     - "Default Unit" -> fill the "Default Unit" column (Piece, Carton, etc.),'],
    ['       leave "UOM Group" blank.'],
    ['     - "UOM Group" -> fill the "UOM Group" column (defaults to "Standard"),'],
    ['       leave "Default Unit" blank.'],
    ['   Every row is pre-filled with UOM Type = "Default Unit" and UOM Group ='],
    ['   "Standard" - only change UOM Type to "UOM Group" for products that need it;'],
    ['   the UOM Group column is already correct once you do.'],
    ['4. Per-unit quantities (Piece/Carton/Dozen breakdowns) are NOT part of this'],
    ['   import, even for UOM Group products - only the group itself is assigned.'],
    ['5. Columns like Product Type, UOM Type, Default Unit, UOM Group, Category,'],
    ['   Vendor, Sales Tax Type, Inventory Account, and the Yes/No columns show a'],
    ['   dropdown arrow - click the cell and pick a value from the list instead of'],
    ['   typing, so the import matches it correctly to your system records.'],
    ['6. Subcategory is free text - type it exactly as it appears under the chosen'],
    ['   Category in the system. Leave blank if not applicable.'],
    ['7. Unit Conversion Factor only applies to Default Unit rows, and only when the'],
    ['   Default Unit picked is a product-specific (non-universal) unit that is not'],
    ['   already a base unit. It is ignored for UOM Group rows.'],
    ['8. Branch-wise Opening Stock is NOT part of this import - add it separately'],
    ['   from the product list after import.'],
    ['9. Scheme columns (Promo Qty / Bonus Qty / To Qty / To Rs) only apply to'],
    ['   Default Unit rows and, if filled, create a single basic scheme against that'],
    ['   unit. They are ignored for UOM Group rows.'],
    ['10. Product Type defaults to "Physical" and Inventory Account defaults to'],
    ['    "Trading Inventory" on every row - change these only for Service products'],
    ['    or a different inventory account type.'],
], null, 'A1');
$infoSheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$infoSheet->getColumnDimension('A')->setWidth(90);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="product_import_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;