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
// Product Entry form's "Select Account" dropdown). Using the full `accounts`
// table pulled in unrelated accounts (Cash, Bank, Expense heads, etc.), which
// is what was showing up wrong in the template.
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
$yesNo = ['Yes', 'No'];

// Defaults requested for the template
$defaultProductType      = 'Physical';
$defaultInventoryAccount = 'Trading Inventory';

// ---- Build workbook ----
$spreadsheet = new Spreadsheet();

// ===== Sheet 1: Products (the sheet the user fills in) =====
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Products');

$headers = [
    'A' => 'Product Name',
    'B' => 'Product Type',
    'C' => 'Default Unit',
    'D' => 'Category',
    'E' => 'Subcategory',
    'F' => 'Vendor',
    'G' => 'Description',
    'H' => 'Unit Conversion Factor',
    'I' => 'Sales Tax Type',
    'J' => 'Inventory Account',
    'K' => 'Purchase Price',
    'L' => 'Trade Price',
    'M' => 'Wholesale Price',
    'N' => 'MRP',
    'O' => 'Default Discount %',
    'P' => 'Min Stock Level',
    'Q' => 'Max Stock Level',
    'R' => 'Manufacturing Date',
    'S' => 'Expiry Date',
    'T' => 'QR Code',
    'U' => 'Barcode',
    'V' => 'Is Active',
    'W' => 'Stock Affects',
    'X' => 'Invoice Affects',
    'Y' => 'Scheme Promo Qty',
    'Z' => 'Scheme Bonus Qty',
    'AA' => 'Scheme To Qty',
    'AB' => 'Scheme To Rs',
];

foreach ($headers as $col => $label) {
    $sheet->setCellValue($col . '1', $label);
}

// Required-field markers + header styling
$sheet->setCellValue('A1', 'Product Name *');
$sheet->setCellValue('C1', 'Default Unit *');

$headerRange = 'A1:AB1';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('2C3E50');
$sheet->getStyle($headerRange)->getAlignment()->setWrapText(true);
$sheet->getRowDimension(1)->setRowHeight(30);
$sheet->freezePane('A2');

foreach (array_keys($headers) as $col) {
    $sheet->getColumnDimension($col)->setWidth(18);
}
$sheet->getColumnDimension('G')->setWidth(28);

$maxRow = 500; // rows available for data entry

// One example row so the expected format is clear
$sheet->fromArray([
    'Blue Ballpoint Pen', $defaultProductType,
    !empty($units) ? $units[0] : '',
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

// Pre-fill Product Type and Inventory Account with the requested defaults for
// every blank data row, so the user only needs to change them if a product
// is actually different (e.g. a Service product, or a non-Trading account).
for ($row = 3; $row <= $maxRow; $row++) {
    $sheet->setCellValue('B' . $row, $defaultProductType);
    $sheet->setCellValue('J' . $row, $defaultInventoryAccount);
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

if (!empty($units)) {
    applyListDropdown($sheet, 'C', 2, $maxRow, 'Lookups!$B$1:$B$' . count($units), true);
}
if (!empty($categories)) {
    applyListDropdown($sheet, 'D', 2, $maxRow, 'Lookups!$C$1:$C$' . count($categories));
}
// Subcategory (E) is intentionally free-text - it depends on which Category was
// picked, and Excel can't easily drive a dependent dropdown off free-typed text.
// Type it exactly as it appears under that Category in the system.
if (!empty($vendors)) {
    applyListDropdown($sheet, 'F', 2, $maxRow, 'Lookups!$D$1:$D$' . count($vendors));
}
if (!empty($taxRegimes)) {
    applyListDropdown($sheet, 'I', 2, $maxRow, 'Lookups!$E$1:$E$' . count($taxRegimes));
}
if (!empty($accounts)) {
    applyListDropdown($sheet, 'J', 2, $maxRow, 'Lookups!$F$1:$F$' . count($accounts));
}

// Yes/No dropdowns
foreach (['V', 'W', 'X'] as $col) {
    applyListDropdown($sheet, $col, 2, $maxRow, 'Lookups!$G$1:$G$2');
}

// ===== Sheet 3: Instructions =====
$infoSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Instructions');
$spreadsheet->addSheet($infoSheet);
$infoSheet->fromArray([
    ['LedgerOne ERP - Product Import Template'],
    [''],
    ['1. Fill data in the "Products" sheet. Do not change the header row.'],
    ['2. Product Name and Default Unit are required. All other fields are optional.'],
    ['3. This import only supports DEFAULT UNIT products (single unit of measure).'],
    ['   UOM Group products are not supported - add those manually via Product Entry.'],
    ['4. Columns like Product Type, Default Unit, Category, Vendor, Sales Tax Type,'],
    ['   Inventory Account, and the Yes/No columns show a dropdown arrow - click the'],
    ['   cell and pick a value from the list instead of typing, so the import matches'],
    ['   it correctly to your system records.'],
    ['5. Subcategory is free text - type it exactly as it appears under the chosen'],
    ['   Category in the system. Leave blank if not applicable.'],
    ['6. Unit Conversion Factor is only required if the Default Unit you picked is a'],
    ['   product-specific (non-universal) unit that is not already a base unit.'],
    ['7. Branch-wise Opening Stock is NOT part of this import - add it separately'],
    ['   from the product list after import.'],
    ['8. Scheme columns (Promo Qty / Bonus Qty / To Qty / To Rs) are optional and,'],
    ['   if filled, create a single basic scheme against the Default Unit.'],
    ['9. Product Type defaults to "Physical" and Inventory Account defaults to'],
    ['   "Trading Inventory" on every row - change these only for Service products'],
    ['   or a different inventory account type.'],
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