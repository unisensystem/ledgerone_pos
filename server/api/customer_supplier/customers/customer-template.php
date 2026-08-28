<?php
require_once '../../../../includes/connection.php';
require_once '../../../../vendor/autoload.php'; // composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
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
// (Same source data used by the Customer Entry form's dropdowns.)

$stmt = $pdo->prepare("SELECT type_name FROM customer_types WHERE tenant_id = 0 OR tenant_id = ? ORDER BY type_name");
$stmt->execute([$tenant_id]);
$customerGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT category_name FROM customer_categories WHERE tenant_id = 0 OR tenant_id = ? ORDER BY category_name");
$stmt->execute([$tenant_id]);
$customerCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT full_name FROM employees WHERE tenant_id = ? AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name");
$stmt->execute([$tenant_id]);
$employees = $stmt->fetchAll(PDO::FETCH_COLUMN); // used for both Sales Officer and Supplier Man

$stmt = $pdo->prepare("SELECT CONCAT(project_code, ' - ', project_name) FROM projects WHERE tenant_id = ? ORDER BY project_name");
$stmt->execute([$tenant_id]);
$projects = $stmt->fetchAll(PDO::FETCH_COLUMN);

$yesNo = ['Yes', 'No'];

// ---- Build workbook ----
$spreadsheet = new Spreadsheet();

// ===== Sheet 1: Customers (the sheet the user fills in) =====
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Customers');

$headers = [
    'A' => 'Customer Name',
    'B' => 'Shopkeeper Name',
    'C' => 'Address',
    'D' => 'P.O. Box No',
    'E' => 'Primary Phone',
    'F' => 'Secondary Phone',
    'G' => 'Email',
    'H' => 'Identity Card No',
    'I' => 'License No',
    'J' => 'Customer Group',
    'K' => 'Customer Category',
    'L' => 'Sales Officer',
    'M' => 'Supplier Man',
    'N' => 'Project',
    'O' => 'Country',
    'P' => 'Region',
    'Q' => 'City',
    'R' => 'City Zone',
    'S' => 'Area',
    'T' => 'Is Sales Tax Registered',
    'U' => 'STRN',
    'V' => 'Is Filer',
    'W' => 'NTN',
    'X' => 'Advance Income Tax %',
    'Y' => 'Default Discount %',
    'Z' => 'Credit Limit',
    'AA' => 'Credit Period Limit (Days)',
    'AB' => 'Is Wholesaler',
    'AC' => 'Blacklist',
    'AD' => 'Out Station',
];

foreach ($headers as $col => $label) {
    $sheet->setCellValue($col . '1', $label);
}

// Required-field marker + header styling
$sheet->getStyle('A1')->getFont()->setBold(true);
$sheet->setCellValue('A1', 'Customer Name *');

$headerRange = 'A1:AD1';
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
$sheet->getColumnDimension('C')->setWidth(28);

// One example row so the expected format is clear
$sheet->fromArray([
    'Faisal Rehman Traders', 'Faisal', 'Bhakkar', '', '03001234567', '', '', '', '',
    !empty($customerGroups) ? $customerGroups[0] : '',
    !empty($customerCategories) ? $customerCategories[0] : '',
    '', '', '',
    'Pakistan', 'Punjab', 'Bhakkar', '', '',
    'No', '', 'No', '', 0, 0, 0, 0, 'No', 'No', 'No'
], null, 'A2');

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

writeLookupColumn($lookupSheet, 'A', $customerGroups);
writeLookupColumn($lookupSheet, 'B', $customerCategories);
writeLookupColumn($lookupSheet, 'C', $employees); // Sales Officer + Supplier Man share this list
writeLookupColumn($lookupSheet, 'D', $projects);
writeLookupColumn($lookupSheet, 'E', $yesNo);

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

$maxRow = 500; // rows available for data entry

if (!empty($customerGroups)) {
    applyListDropdown($sheet, 'J', 2, $maxRow, 'Lookups!$A$1:$A$' . count($customerGroups));
}
if (!empty($customerCategories)) {
    applyListDropdown($sheet, 'K', 2, $maxRow, 'Lookups!$B$1:$B$' . count($customerCategories));
}
if (!empty($employees)) {
    applyListDropdown($sheet, 'L', 2, $maxRow, 'Lookups!$C$1:$C$' . count($employees)); // Sales Officer
    applyListDropdown($sheet, 'M', 2, $maxRow, 'Lookups!$C$1:$C$' . count($employees)); // Supplier Man
}
if (!empty($projects)) {
    applyListDropdown($sheet, 'N', 2, $maxRow, 'Lookups!$D$1:$D$' . count($projects));
}

// Yes/No dropdowns
foreach (['T', 'V', 'AB', 'AC', 'AD'] as $col) {
    applyListDropdown($sheet, $col, 2, $maxRow, 'Lookups!$E$1:$E$2');
}

// ===== Sheet 3: Instructions =====
$infoSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Instructions');
$spreadsheet->addSheet($infoSheet);
$infoSheet->fromArray([
    ['LedgerOne ERP - Customer Import Template'],
    [''],
    ['1. Fill data in the "Customers" sheet. Do not change the header row.'],
    ['2. Customer Name is required. All other fields are optional.'],
    ['3. Columns like Customer Group, Customer Category, Sales Officer, Supplier Man, Project'],
    ['   and all Yes/No columns show a dropdown arrow - click the cell and pick a value from'],
    ['   the list instead of typing, so the import matches it correctly to your system records.'],
    ['4. Country / Region / City / City Zone / Area can be typed freely - new ones are created'],
    ['   automatically if they do not already exist.'],
    ['5. Opening balance is not part of this import - add it separately from the customer list.'],
], null, 'A1');
$infoSheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$infoSheet->getColumnDimension('A')->setWidth(90);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="customer_import_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;