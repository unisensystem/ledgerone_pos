<?php
require_once '../../../../includes/dashboard.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Redirect to login if no user_id in session
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Currency Setup</title>
    <link rel="stylesheet" href="../../../assets/css/system_setup/currency_setup/currency-add.css">
</head>

<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Currency Setup</h1>
        </div>

        <div class="card">
            <h2 class="card-title">Currency Information</h2>
            <form id="currencyForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="currencyName" class="required">Currency Name</label>
                        <input type="text" id="currencyName" placeholder="Search and select currency..." autocomplete="off" required>
                        <div id="currencyDropdown" class="dropdown-list"></div>
                        <input type="hidden" id="currencyId">
                        <div class="error" id="currencyNameError"></div>
                    </div>

                    <div class="form-group">
                        <label for="currencySymbol">Currency Symbol</label>
                        <input type="text" id="currencySymbol" readonly>
                        <div class="helper-text">Automatically populated based on currency selection</div>
                    </div>

                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <input type="checkbox" id="isBaseCurrency">
                            <label for="isBaseCurrency" class="checkbox-label">Set as Base Currency</label>
                        </div>
                        <div class="helper-text">This currency will be used as the primary currency for all transactions
                        </div>
                        <div class="error" id="baseCurrencyError"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-undo"></i>
                        Reset Form
                    </button> 
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save Currency</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/currency_setup/currency-add.js"></script>
</body>

</html>