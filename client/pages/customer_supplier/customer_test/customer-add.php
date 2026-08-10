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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne · Customer entry (light)</title>
    <!-- Font for clean body text (optional, system fallback) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customer_test/customer-add.css">
</head>

<body>
    <div class="form-card">
        <!-- Basic Information section (only one section as per spec) -->
        <h2 class="section-title">Basic Information</h2>

        <!-- 12‑column form grid -->
        <div class="form-grid">
            <!-- Customer Code (readonly) -->
            <div class="field">
                <label for="customerCode">Customer Code</label>
                <input type="text" id="customerCode" name="customerCode" readonly value="CUST-2410-01" tabindex="-1">
                <!-- no helper, but keep consistent -->
                <div class="helper-text"></div>
            </div>

            <!-- Customer Name (required) -->
            <div class="field" id="name-field">
                <label for="customerName">Customer Name <span class="required-star">*</span></label>
                <input type="text" id="customerName" name="customerName" placeholder="e.g. John Smith" required>
                <div class="helper-text" id="name-helper"></div>
            </div>

            <!-- Phone Number (number, required) -->
            <div class="field" id="phone-field">
                <label for="phone">Phone Number <span class="required-star">*</span></label>
                <input type="tel" id="phone" name="phone" placeholder="+1 (555) 123-4567" required>
                <div class="helper-text" id="phone-helper"></div>
            </div>

            <!-- Email (email, not required per spec, but we treat as optional) -->
            <div class="field" id="email-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="contact@example.com">
                <div class="helper-text" id="email-helper"></div>
            </div>
        </div> <!-- end grid -->

        <!-- subtle hint for required fields (can be considered subtext) -->
        <div class="req-hint"><i>*</i> Required field</div>

        <!-- Form actions (left title / right buttons) with sticky footer option (optional) -->
        <div class="form-actions">
            <div class="left-title">
                Customer details
                <span>entry form</span>
            </div>
            <div class="button-group">
                <button class="btn btn-secondary" type="reset" id="resetBtn">Reset</button>
                <button class="btn btn-primary" type="submit" id="submitBtn">Save customer</button>
            </div>
        </div>
    </div>

    <!-- lightweight JS for validation + light interactions -->
    <script src="../../../assets/js/customer_supplier/customer_test/customer-add.js"></script>
</body>

</html>