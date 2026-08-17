<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $employee_id = $data['id'] ?? null;
    if (!$employee_id) {
        throw new Exception('Employee ID is required');
    }
    
    // Verify employee exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$employee_id, $tenant_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Employee not found');
    }
    
    // Validate required fields
    $required = ['firstName', 'gender', 'department', 'position', 'employmentType', 'baseSalary', 'salaryFrequency'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Check if email already exists for another employee
    if (!empty($data['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND tenant_id = ? AND id != ?");
        $stmt->execute([$data['email'], $tenant_id, $employee_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists');
        }
    }
    
    $fullName = trim(($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? ''));
    $probationEndDate = null;
    if (!empty($data['probation']) && $data['probation'] === 'yes' && !empty($data['probationMonths'])) {
        $probationEndDate = date('Y-m-d', strtotime($data['hireDate'] . ' +' . $data['probationMonths'] . ' months'));
    }
    
    $sql = "UPDATE employees SET 
        full_name = ?, date_of_birth = ?, gender = ?, nationality = ?, marital_status = ?,
        email = ?, phone_number = ?, emergency_contact_name = ?, address = ?, profile_photo_url = ?,
        employment_agreement_url = ?, id_proof_url = ?, resume_url = ?, certificates_url = ?, medical_certificate_url = ?,
        department_id = ?, position_id = ?, employment_type = ?, employee_category = ?, hire_date = ?,
        probation_end_date = ?, work_location = ?, shift_timing = ?, reporting_manager_id = ?,
        work_hours_per_week = ?,
        base_salary = ?, salary_currency = ?, salary_frequency = ?,
        housing_allowance = ?, transport_allowance = ?, medical_allowance = ?, meal_allowance = ?,
        communication_allowance = ?, education_allowance = ?, travel_allowance = ?, other_allowance = ?,
        annual_bonus_percentage = ?, provident_fund_percentage = ?, gratuity_eligibility = ?,
        health_insurance = ?, life_insurance = ?, paid_time_off = ?,
        leave_balance_paid = ?, leave_balance_sick = ?, leave_balance_unpaid = ?,
        leave_reset_period = ?, next_reset_date = ?, carry_forward_allowed = ?,
        notes = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND tenant_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $fullName,
        $data['dateOfBirth'] ?? null,
        $data['gender'] ?? null,
        $data['nationality'] ?? null,
        $data['maritalStatus'] ?? null,
        !empty($data['email']) ? $data['email'] : null,
        $data['phone'],
        $data['emergencyContact'] ?? null,
        $data['address'] ?? null,
        $data['profilePhotoUrl'] ?? null,
        $data['employmentAgreementUrl'] ?? null,
        $data['idProofUrl'] ?? null,
        $data['resumeUrl'] ?? null,
        $data['certificatesUrl'] ?? null,
        $data['medicalCertificateUrl'] ?? null,
        $data['department'],
        $data['position'],
        $data['employmentType'],
        $data['employeeType'] ?? null,
        !empty($data['hireDate']) ? $data['hireDate'] : null,
        $probationEndDate,
        $data['workLocation'] ?? null,
        $data['shift'] ?? null,
        !empty($data['manager']) ? $data['manager'] : null,
        !empty($data['workHours']) ? $data['workHours'] : null,
        $data['baseSalary'],
        strtoupper($data['currency'] ?? 'USD'),
        $data['salaryFrequency'],
        $data['housingAllowance'] ?? 0,
        $data['transportAllowance'] ?? 0,
        $data['medicalAllowance'] ?? 0,
        $data['mealAllowance'] ?? 0,
        $data['communicationAllowance'] ?? 0,
        $data['educationAllowance'] ?? 0,
        $data['travelAllowance'] ?? 0,
        $data['otherAllowance'] ?? 0,
        $data['annualBonus'] ?? 0,
        $data['providentFund'] ?? 0,
        ($data['gratuity'] === 'yes') ? 1 : 0,
        !empty($data['healthInsurance']) ? 1 : 0,
        !empty($data['lifeInsurance']) ? 1 : 0,
        !empty($data['pto']) ? 1 : 0,
        $data['paidLeaveBalance'] ?? 21,
        $data['sickLeaveBalance'] ?? 10,
        $data['unpaidLeaveBalance'] ?? 0,
        $data['leaveResetPeriod'] ?? 'yearly',
        $data['nextResetDate'] ?? date('Y-01-01', strtotime('+1 year')),
        !empty($data['carryForward']) ? 1 : 0,
        $data['notes'] ?? null,
        $user_id,
        $employee_id,
        $tenant_id
    ]);
    
    // Handle opening balance entries (only if provided and different from existing)
    $openingDebit = floatval($data['openingDebit'] ?? 0);
    $openingCredit = floatval($data['openingCredit'] ?? 0);
    
    // Delete existing opening balance entries for this employee
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'opening_balance' AND reference_table = 'employees' AND reference_id = ?");
    $stmt->execute([$tenant_id, $employee_id]);
    
    if ($openingDebit > 0) {
        // Employee owes company - Debit Advances & Loans, Credit Opening Balance Equity
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'opening_balance', 'employees', ?, 31, CURDATE(), ?, ?, 0)");
        $stmt->execute([$tenant_id, $employee_id, "Opening balance - {$fullName}", $openingDebit]);
        
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'opening_balance', 'employees', ?, 90, CURDATE(), ?, 0, ?)");
        $stmt->execute([$tenant_id, $employee_id, "Opening balance - {$fullName}", $openingDebit]);
    }
    
    if ($openingCredit > 0) {
        // Company owes employee - Debit Opening Balance Equity, Credit Salaries Payable
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'opening_balance', 'employees', ?, 90, CURDATE(), ?, ?, 0)");
        $stmt->execute([$tenant_id, $employee_id, "Opening balance - {$fullName}", $openingCredit]);
        
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'opening_balance', 'employees', ?, 28, CURDATE(), ?, 0, ?)");
        $stmt->execute([$tenant_id, $employee_id, "Opening balance - {$fullName}", $openingCredit]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Employee updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}