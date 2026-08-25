<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Import Customers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customers/customer-add.css">
</head>

<body class="light-mode">
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--border-default);">
                <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;">
                    <i class="fas fa-file-import"></i>
                    Import Customers (Excel/CSV)
                </h2>
                <a href="customer-list.php" class="btn btn-secondary btn-sm" style="padding: 0 16px; height: 40px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <form id="importForm">
                <div class="form-grid">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-id-card"></i> Step 1 &mdash; Select Company
                        </h3>
                        <div class="form-group col-6">
                            <label for="company" class="required">
                                <i class="fas fa-building"></i> Company
                            </label>
                            <select id="company" required>
                                <option value="">Select Company</option>
                            </select>
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                All imported customers will be added under this company
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-excel"></i> Step 2 &mdash; Upload File
                        </h3>
                        <div class="form-group col-8">
                            <label for="importFile" class="required">
                                <i class="fas fa-upload"></i> Excel / CSV File
                            </label>
                            <input type="file" id="importFile" accept=".xlsx,.xls,.csv" required>
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Don't have the file ready? <a href="../../../../server/api/customer_supplier/customers/customer-template.php">Download the template</a> first.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-file-import"></i> Import Customers
                    </button>
                </div>
            </form>

            <div id="resultBox" style="display:none; margin-top: 24px; padding: 16px; border-radius: 10px; border: 1px solid var(--border-default);">
                <h3 style="margin-top:0;"><i class="fas fa-clipboard-check"></i> Import Result</h3>
                <div id="resultSummary" style="margin-bottom: 12px;"></div>
                <div id="resultErrors" style="max-height: 260px; overflow-y:auto; font-size: 13px;"></div>
            </div>
        </div>
    </div>

    <div class="notification" id="notification">
        <div class="notification-icon"><i class="fas fa-check"></i></div>
        <div class="notification-content">
            <div class="notification-title" id="notificationTitle">Success</div>
            <div class="notification-message" id="notificationMessage"></div>
        </div>
        <button class="notification-close" id="notificationClose"><i class="fas fa-times"></i></button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/customer_supplier/customers/customer-import.js"></script>
</body>

</html>