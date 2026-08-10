<?php
session_start();
require_once('config.php');
require_once('includes/barcode_generator.php');
require_once('templates/id_card_template.php');

if (!isset($_POST['employee_id'])) {
    exit('Employee ID not provided');
}

try {
    // Get employee details with department name
    $sql = "SELECT e.*, d.dept_name 
            FROM employees e 
            JOIN departments d ON e.department_id = d.id 
            WHERE e.employee_id = :employee_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $_POST['employee_id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        exit('Employee not found');
    }

    // Get company details
    $company_sql = "SELECT * FROM company_settings LIMIT 1";
    $company_stmt = $pdo->query($company_sql);
    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $company = [
            'company_name' => 'COMPANY NAME',
            'company_logo' => 'assets/images/company-logo.png',
            'address_line1' => '44 Shirley Ave. West',
            'city' => 'Chicago',
            'state' => 'IL',
            'postal_code' => '60185'
        ];
    }

    // Generate barcode image
    $barcode_image = generateBarcodeImage($employee['employee_id']);
    if (!$barcode_image) {
        throw new Exception('Failed to generate barcode');
    }

    // Generate ID card HTML
    $id_card_html = generateIdCard($employee, $company, $barcode_image);

    // Set response headers for PDF generation
    header('Content-Type: text/html; charset=utf-8');
    echo $id_card_html;

} catch (Exception $e) {
    error_log("Error generating ID card: " . $e->getMessage());
    exit('Error generating ID card: ' . $e->getMessage());
}
?>
