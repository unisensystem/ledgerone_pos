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
// ADJUST table/column names below if they differ from your actual schema.
// Each lookup is wrapped in safeFetchColumn() so a missing/misnamed table never
// crashes the whole template - that column just becomes free-text (no dropdown)
// instead, and a note is added to the Notes sheet below.
$missingLookups = [];

function safeFetchColumn(PDO $pdo, string $sql, array $params, string $label, array &$missingLookups): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $missingLookups[] = $label;
        return [];
    }
}

$companies = safeFetchColumn($pdo, "SELECT company_name FROM companies WHERE tenant_id = ? ORDER BY company_name", [$tenant_id], 'Company (table: companies)', $missingLookups);
$projects = safeFetchColumn($pdo, "SELECT CONCAT(project_code, ' - ', project_name) FROM projects WHERE tenant_id = ? ORDER BY project_name", [$tenant_id], 'Project (table: projects)', $missingLookups);
$customerGroups = safeFetchColumn($pdo, "SELECT type_name FROM customer_types WHERE tenant_id = 0 OR tenant_id = ? ORDER BY type_name", [$tenant_id], 'Customer Group (table: customer_types)', $missingLookups);
$customerCategories = safeFetchColumn($pdo, "SELECT category_name FROM customer_categories WHERE tenant_id = 0 OR tenant_id = ? ORDER BY category_name", [$tenant_id], 'Customer Category (table: customer_categories)', $missingLookups);
$brands = safeFetchColumn($pdo, "SELECT brand_name FROM brands WHERE tenant_id = 0 OR tenant_id = ? ORDER BY brand_name", [$tenant_id], 'Brand Name (table: brands)', $missingLookups);
$employees = safeFetchColumn($pdo, "SELECT full_name FROM employees WHERE tenant_id = ? AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name", [$tenant_id], 'Sales Officer / Supplier Man / Salesman (table: employees)', $missingLookups);
$supplierCategories = safeFetchColumn($pdo, "SELECT category_name FROM supplier_categories WHERE tenant_id = 0 OR tenant_id = ? ORDER BY category_name", [$tenant_id], 'Supplier Category (table: supplier_categories)', $missingLookups);

$partyTypes = ['Registered Company', 'Unregistered'];
$yesNo = ['Yes', 'No'];

// ---- Build workbook ----
$spreadsheet = new Spreadsheet();

// ===== Sheet 1: Both (the sheet the user fills in) =====
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Both');

$headers = [
    'A' => 'Company',
    'B' => 'Party Name',
    'C' => 'Project',
    'D' => 'Address',
    'E' => 'Primary Phone',
    'F' => 'Secondary Phone',
    'G' => 'Email',
    'H' => 'Identity Card No',
    'I' => 'Blacklist',
    'J' => 'Customer Group',
    'K' => 'Customer Category',
    'L' => 'Brand Name',
    'M' => 'Shopkeeper Name',
    'N' => 'P.O. Box No',
    'O' => 'License No',
    'P' => 'Sales Officer',
    'Q' => 'Supplier Man',
    'R' => 'Out Station',
    'S' => 'Country',
    'T' => 'Region',
    'U' => 'City',
    'V' => 'City Zone',
    'W' => 'Area',
    'X' => 'Is Sales Tax Registered',
    'Y' => 'STRN',
    'Z' => 'Is Filer',
    'AA' => 'NTN',
    'AB' => 'Advance Income Tax %',
    'AC' => 'Default Discount %',
    'AD' => 'Credit Limit',
    'AE' => 'Credit Period Limit (Days)',
    'AF' => 'Is Wholesaler',
    'AG' => 'Customer Opening Debit',
    'AH' => 'Customer Opening Credit',
    'AI' => 'Associated Companies',
    'AJ' => 'Salesman',
    'AK' => 'Supplier Category',
    'AL' => "Supplier's Brand Name",
    'AM' => 'Supplier City',
    'AN' => 'Party Type',
    'AO' => 'AIT %',
    'AP' => 'Credit Days',
    'AQ' => 'Supplier Opening Debit',
    'AR' => 'Supplier Opening Credit',
];

foreach ($headers as $col => $label) {
    $sheet->setCellValue($col . '1', $label);
}

// Required-field markers
$sheet->setCellValue('A1', 'Company *');
$sheet->setCellValue('B1', 'Party Name *');
$sheet->setCellValue('AI1', "Associated Companies\n(comma-separated names)");
$sheet->setCellValue('AJ1', "Salesman\n(comma-separated names)");

$headerRange = 'A1:AR1';
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
$sheet->getColumnDimension('D')->setWidth(28);
$sheet->getColumnDimension('AI')->setWidth(24);
$sheet->getColumnDimension('AJ')->setWidth(24);

$maxRow = 500;

// One example row so the expected format is clear
$sheet->fromArray([
    !empty($companies) ? $companies[0] : '', 'Faisal Rehman Traders',
    '', 'Bhakkar', '03001234567', '', '', '', 'No',
    !empty($customerGroups) ? $customerGroups[0] : '',
    !empty($customerCategories) ? $customerCategories[0] : '',
    '', 'Faisal', '', '',
    '', '', 'No',
    'Pakistan', 'Punjab', 'Bhakkar', '', '',
    'No', '', 'No', '', 0, 0, 0, 0, 'No', 0, 0,
    '', '',
    !empty($supplierCategories) ? $supplierCategories[0] : '',
    '', 'Bhakkar', 'Unregistered', 0, 0, 0, 0
], null, 'A2');

// Pre-fill Yes/No defaults on every blank data row (Blacklist, Out Station,
// Is Sales Tax Registered, Is Filer, Is Wholesaler all default to "No").
for ($row = 3; $row <= $maxRow; $row++) {
    $sheet->setCellValue('I' . $row, 'No');
    $sheet->setCellValue('R' . $row, 'No');
    $sheet->setCellValue('X' . $row, 'No');
    $sheet->setCellValue('Z' . $row, 'No');
    $sheet->setCellValue('AF' . $row, 'No');
}

// ===== Sheet 2: hidden "Lookups" sheet backing the dropdowns =====
$lookupSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Lookups');
$spreadsheet->addSheet($lookupSheet);

function writeLookupColumn($sheet, string $col, array $values): void
{
    foreach (array_values($values) as $idx => $value) {
        $sheet->setCellValue($col . ($idx + 1), $value);
    }
}

writeLookupColumn($lookupSheet, 'A', $companies);
writeLookupColumn($lookupSheet, 'B', $projects);
writeLookupColumn($lookupSheet, 'C', $customerGroups);
writeLookupColumn($lookupSheet, 'D', $customerCategories);
writeLookupColumn($lookupSheet, 'E', $brands);
writeLookupColumn($lookupSheet, 'F', $employees);
writeLookupColumn($lookupSheet, 'G', $yesNo);
writeLookupColumn($lookupSheet, 'H', $supplierCategories);
writeLookupColumn($lookupSheet, 'I', $partyTypes);

$lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

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

if (!empty($companies)) {
    applyListDropdown($sheet, 'A', 2, $maxRow, 'Lookups!$A$1:$A$' . count($companies), true);
}
if (!empty($projects)) {
    applyListDropdown($sheet, 'C', 2, $maxRow, 'Lookups!$B$1:$B$' . count($projects));
}
if (!empty($customerGroups)) {
    applyListDropdown($sheet, 'J', 2, $maxRow, 'Lookups!$C$1:$C$' . count($customerGroups));
}
if (!empty($customerCategories)) {
    applyListDropdown($sheet, 'K', 2, $maxRow, 'Lookups!$D$1:$D$' . count($customerCategories));
}
if (!empty($brands)) {
    applyListDropdown($sheet, 'L', 2, $maxRow, 'Lookups!$E$1:$E$' . count($brands));
}
if (!empty($employees)) {
    applyListDropdown($sheet, 'P', 2, $maxRow, 'Lookups!$F$1:$F$' . count($employees)); // Sales Officer
    applyListDropdown($sheet, 'Q', 2, $maxRow, 'Lookups!$F$1:$F$' . count($employees)); // Supplier Man
    // Associated Companies (AI) and Salesman (AJ) are free-text (comma-separated,
    // multi-value) - Excel dropdowns don't support multi-select, so no validation
    // is applied to those two columns; type exact names separated by commas.
}
if (!empty($supplierCategories)) {
    applyListDropdown($sheet, 'AK', 2, $maxRow, 'Lookups!$H$1:$H$' . count($supplierCategories));
}
applyListDropdown($sheet, 'AN', 2, $maxRow, 'Lookups!$I$1:$I$' . count($partyTypes));

// Yes/No dropdowns
foreach (['I', 'R', 'X', 'Z', 'AF'] as $col) {
    applyListDropdown($sheet, $col, 2, $maxRow, 'Lookups!$G$1:$G$2');
}

// ===== Sheet 3: Instructions =====
$infoSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Instructions');
$spreadsheet->addSheet($infoSheet);
$infoLines = [
    ['LedgerOne ERP - Customer + Supplier (Both) Import Template'],
    [''],
];

if (!empty($missingLookups)) {
    $infoLines[] = ['⚠ NOTE: The following dropdown lists could not be loaded (table not found in'];
    $infoLines[] = ['  your database) - these columns are free-text in this file. Fill them in'];
    $infoLines[] = ['  exactly as they should be saved, or ask your developer to fix the table'];
    $infoLines[] = ['  name in both-template.php / both-import.php:'];
    foreach ($missingLookups as $ml) {
        $infoLines[] = ['    - ' . $ml];
    }
    $infoLines[] = [''];
}

$infoLines = array_merge($infoLines, [
    ['1. Fill data in the "Both" sheet. Do not change the header row.'],
    ['2. Company and Party Name are required. Every other field is optional.'],
    ['3. Each row creates ONE linked record in both the Customer list and the'],
    ['   Supplier list (marked "Both") - the same Party Name, Address, Phone,'],
    ['   Email and Identity Card apply to both sides.'],
    ['4. Columns like Company, Project, Customer Group, Customer Category, Brand'],
    ['   Name, Sales Officer, Supplier Man, Supplier Category, Party Type, and the'],
    ['   Yes/No columns show a dropdown arrow - click the cell and pick a value'],
    ['   from the list instead of typing, so the import matches it correctly to'],
    ['   your system records.'],
    ['5. Blacklist, Out Station, Is Sales Tax Registered, Is Filer and Is Wholesaler'],
    ['   default to "No" on every row - change only where needed.'],
    ['6. Country / Region / City / City Zone / Area can be typed freely - new ones'],
    ['   are created automatically if they do not already exist.'],
    ['7. Associated Companies and Salesman accept MULTIPLE values - type the exact'],
    ['   names separated by commas (e.g. "ABC Traders, XYZ Company"). These two'],
    ['   columns do not have a dropdown since Excel cannot multi-select a cell.'],
    ['8. Supplier City is free text (not linked to Country/Region/City above) -'],
    ['   matches how the Supplier side stores city on the main Both form.'],
    ['9. Opening Balance Invoices and Sub Accounts are NOT part of this import -'],
    ['   only the opening balance TOTALS (Customer/Supplier Opening Debit/Credit).'],
    ['   Add itemized invoices or sub accounts manually after import if needed.'],
    ['10. Duplicate rows (same Party Name + same Company as an existing record)'],
    ['    are skipped automatically, not overwritten.'],
]);
$infoSheet->fromArray($infoLines, null, 'A1');
$infoSheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$infoSheet->getColumnDimension('A')->setWidth(95);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="customer_supplier_both_import_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;