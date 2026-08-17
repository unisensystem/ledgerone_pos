<?php
session_start();
include '../config/config.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch all roles
$role_stmt = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name");
$roles = $role_stmt->fetchAll(PDO::FETCH_ASSOC);

// Define form categories and their items with sub-permissions
$permissions = [
    'Setup' => [
        'company_setup' => [
            'name' => 'Company Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'chart_of_accounts' => [
            'name' => 'Chart of Accounts',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'bank_setup' => [
            'name' => 'Bank Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'customer_setup' => [
            'name' => 'Customer Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'transport_setup' => [
            'name' => 'Transport Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'warehouse_setup' => [
            'name' => 'Warehouse Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'zone_setup' => [
            'name' => 'Zone Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'item_setup' => [
            'name' => 'Item Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'item_category' => [
            'name' => 'Item Category',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'item_subcategory' => [
            'name' => 'Item Sub-Category',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'vendor_setup' => [
            'name' => 'Vendor Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'batch_setup' => [
            'name' => 'Batch Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'branch' => [
            'name' => 'Branch Setup',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ]
    ],
    'Entry' => [
        'sale_invoice' => [
            'name' => 'Sale Invoice',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'sale_return' => [
            'name' => 'Sale Return Invoice',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'pos_bill' => [
            'name' => 'POS Sales Bill',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'purchase_invoice' => [
            'name' => 'Purchase Invoice',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'purchase_return' => [
            'name' => 'Purchase Return Invoice',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'receive_voucher' => [
            'name' => 'Receive Voucher',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'receive_voucher2' => [
            'name' => 'Receive Voucher 2',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'payment_voucher' => [
            'name' => 'Payment Voucher',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'journal_voucher' => [
            'name' => 'Journal Voucher',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'expense_voucher' => [
            'name' => 'Expense Voucher',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'employee_entry' => [
            'name' => 'Employee Entry',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'production' => [
            'name' => 'Production',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ],
        'bom' => [
            'name' => 'Bill Of Material',
            'sub_permissions' => ['add_record', 'edit', 'delete']
        ]
    ],
    'Reports' => [
        'recovery_sheet' => [
            'name' => 'Recovery Sheet',
            'sub_permissions' => []
        ],
        'daily_sale' => [
            'name' => 'Daily Sale Report',
            'sub_permissions' => []
        ],
        'sale_report' => [
            'name' => 'Sale Report',
            'sub_permissions' => []
        ],
        'purchase_report' => [
            'name' => 'Purchase Report',
            'sub_permissions' => []
        ],
        'stock_report' => [
            'name' => 'Stock Report',
            'sub_permissions' => []
        ],
        'customer_ledger' => [
            'name' => 'Customer Ledger Report',
            'sub_permissions' => []
        ],
        'customer_individual' => [
            'name' => 'Customer Individual Ledger',
            'sub_permissions' => []
        ],
        'vendor_ledger' => [
            'name' => 'Vendor Ledger Report',
            'sub_permissions' => []
        ],
        'vendor_individual' => [
            'name' => 'Vendor Individual Ledger',
            'sub_permissions' => []
        ],
        'cashbook' => [
            'name' => 'Cashbook',
            'sub_permissions' => []
        ],
        'income_statement' => [
            'name' => 'Income Statement',
            'sub_permissions' => []
        ],
        'trial_balance' => [
            'name' => 'Trial Balance',
            'sub_permissions' => []
        ],
        'customer_aging' => [
            'name' => 'Customer Aging',
            'sub_permissions' => []
        ],
        'seller_aging' => [
            'name' => 'Seller Aging',
            'sub_permissions' => []
        ],
        'balance_sheet' => [
            'name' => 'Balance Sheet',
            'sub_permissions' => []
        ],
        'short_item_list' => [
            'name' => 'Short Item List',
            'sub_permissions' => []
        ],
        'vendor_aging' => [
            'name' => 'Vendor Aging',
            'sub_permissions' => []
        ],
        'daybook' => [
            'name' => 'DayBook',
            'sub_permissions' => []
        ]
    ]
];

// Handle form submission - Only process when saving permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $role_id = $_POST['role_id'];
    
    // Begin transaction
    $pdo->beginTransaction();
    try {
        // First delete existing permissions for this role
        $delete_stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $delete_stmt->execute([$role_id]);

        // Insert new permissions
        $insert_stmt = $pdo->prepare("
            INSERT INTO role_permissions (role_id, category, form_name, sub_permission, allowed) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($permissions as $category => $forms) {
            foreach ($forms as $form_key => $form_data) {
                $allowed = isset($_POST['permissions'][$category][$form_key]) ? 1 : 0;
                $insert_stmt->execute([$role_id, $category, $form_key, null, $allowed]);

                foreach ($form_data['sub_permissions'] as $sub_permission) {
                    $sub_allowed = isset($_POST['permissions'][$category][$form_key][$sub_permission]) ? 1 : 0;
                    $insert_stmt->execute([$role_id, $category, $form_key, $sub_permission, $sub_allowed]);
                }
            }
        }

        $pdo->commit();
        $success_message = "Permissions updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error updating permissions: " . $e->getMessage();
    }
}

// Fetch existing permissions if role is selected
$selected_role_id = $_GET['role_id'] ?? null;
$existing_permissions = [];

if ($selected_role_id) {
    $perm_stmt = $pdo->prepare("SELECT category, form_name, sub_permission, allowed FROM role_permissions WHERE role_id = ?");
    $perm_stmt->execute([$selected_role_id]);
    while ($row = $perm_stmt->fetch()) {
        if ($row['sub_permission']) {
            $existing_permissions[$row['category']][$row['form_name']]['sub_permissions'][$row['sub_permission']] = $row['allowed'];
        } else {
            $existing_permissions[$row['category']][$row['form_name']]['allowed'] = $row['allowed'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Access Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="role_access.css">
</head>
<body>
    <div class="container">
        <h1>Role Access Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="role-selector">
            <form method="GET" action="role_access.php" class="role-select-form">
                <label for="role_id">Select Role:</label>
                <select name="role_id" id="role_id" required onchange="this.form.submit()">
                    <option value="">Select a role...</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= $selected_role_id == $role['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_role_id): ?>
            <form method="POST" action="role_access.php">
                <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">
                <div class="permissions-container">
                    <div class="select-all-container">
                        <input type="checkbox" id="selectAll">
                        <label for="selectAll">Select All Permissions</label>
                    </div>
                    <?php foreach ($permissions as $category => $forms): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <?= htmlspecialchars($category) ?>
                            </div>
                            <?php foreach ($forms as $form_key => $form_data): ?>
                                <div class="permission-item">
                                    <input type="checkbox" 
                                           id="<?= $form_key ?>" 
                                           name="permissions[<?= $category ?>][<?= $form_key ?>]" 
                                           <?= (!empty($existing_permissions[$category][$form_key]['allowed'])) ? 'checked' : '' ?>>
                                    <label for="<?= $form_key ?>"><?= htmlspecialchars($form_data['name']) ?></label>
                                </div>
                                <div class="sub-permissions">
                                    <?php foreach ($form_data['sub_permissions'] as $sub_permission): ?>
                                        <div class="permission-item">
                                            <input type="checkbox" 
                                                   id="<?= $form_key ?>_<?= $sub_permission ?>" 
                                                   name="permissions[<?= $category ?>][<?= $form_key ?>][<?= $sub_permission ?>]" 
                                                   <?= (!empty($existing_permissions[$category][$form_key]['sub_permissions'][$sub_permission])) ? 'checked' : '' ?>>
                                            <label for="<?= $form_key ?>_<?= $sub_permission ?>"><?= htmlspecialchars(ucfirst($sub_permission)) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" name="save_permissions" class="btn btn-primary">Save Permissions</button>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="form-actions" style="margin-top: 2rem;">
            <a href="admin_panel.php" class="btn btn-secondary">Back to Admin Panel</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const allCheckboxes = document.querySelectorAll('.permission-item input[type="checkbox"]');
            const mainCheckboxes = document.querySelectorAll('.permission-item:not(.sub-permissions .permission-item) input[type="checkbox"]');
            
            // Handle main permission checkboxes
            mainCheckboxes.forEach(checkbox => {
                const subPermissions = checkbox.closest('.permission-item').nextElementSibling;
                
                // Set initial state
                if (checkbox.checked && subPermissions) {
                    subPermissions.classList.add('show');
                }

                // Handle checkbox change
                checkbox.addEventListener('change', function() {
                    if (subPermissions) {
                        if (this.checked) {
                            subPermissions.classList.add('show');
                        } else {
                            subPermissions.classList.remove('show');
                            // Uncheck all sub-permissions
                            subPermissions.querySelectorAll('input[type="checkbox"]').forEach(sub => {
                                sub.checked = false;
                            });
                        }
                    }
                });
            });

            // Update select all checkbox state
            function updateSelectAllCheckbox() {
                const allChecked = Array.from(allCheckboxes).every(checkbox => checkbox.checked);
                selectAllCheckbox.checked = allChecked;
            }

            // Handle select all checkbox
            selectAllCheckbox.addEventListener('change', function() {
                const checked = this.checked;
                allCheckboxes.forEach(checkbox => {
                    checkbox.checked = checked;
                    // Show/hide sub-permissions based on main checkbox state
                    const mainItem = checkbox.closest('.permission-item:not(.sub-permissions .permission-item)');
                    if (mainItem) {
                        const subPermissions = mainItem.nextElementSibling;
                        if (subPermissions) {
                            if (checked) {
                                subPermissions.classList.add('show');
                            } else {
                                subPermissions.classList.remove('show');
                            }
                        }
                    }
                });
            });

            // Handle individual checkboxes
            allCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectAllCheckbox);
            });
        });
    </script>
</body>
</html>
