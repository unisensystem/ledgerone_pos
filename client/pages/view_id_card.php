<?php
require_once('config.php');
require_once('templates/id_card_template.php');

if (!isset($_GET['employee_id'])) {
    die('Employee ID not provided');
}

$employee_id = $_GET['employee_id'];

// Get employee details
$sql = "SELECT e.*, d.dept_name 
        FROM employees e 
        LEFT JOIN departments d ON e.department_id = d.id 
        WHERE e.employee_id = :employee_id";

$stmt = $pdo->prepare($sql);
$stmt->execute(['employee_id' => $employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die('Employee not found');
}

// Get company settings
$company_sql = "SELECT * FROM company_settings WHERE id = 1";
$company_stmt = $pdo->query($company_sql);
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die('Company settings not found');
}

// Instead of generating barcode image, we'll use JsBarcode in the template
$barcode_image = '';

// Generate and output the ID card
generateIdCard($employee, $company, $barcode_image);
?>
