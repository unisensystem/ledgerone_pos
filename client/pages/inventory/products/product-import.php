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
    <title>LedgerOne ERP - Import Products</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        :root {
            --border-default: #e5e7eb;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --text-main: #111827;
            --text-muted: #6b7280;
            --bg-page: #eef1f6;
            --bg-card: #ffffff;
            --success: #16a34a;
            --danger: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 28px 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            border-top: 4px solid var(--primary);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
        }

        .card-title i {
            color: var(--primary);
        }

        .btn {
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-sm {
            padding: 8px 16px;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: var(--text-main);
            border: 1px solid var(--border-default);
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            padding: 10px 20px;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .helper-text {
            background: #f9fafb;
            border: 1px solid var(--border-default);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .helper-text i {
            margin-top: 2px;
            color: var(--primary);
        }

        .helper-text a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: underline;
        }

        .form-grid {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .form-section {
            border-top: 1px solid var(--border-default);
            padding-top: 20px;
        }

        .form-section:first-child {
            border-top: none;
            padding-top: 0;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 16px 0;
        }

        .section-title i {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 6px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .form-group label i {
            color: var(--primary);
            font-size: 12px;
        }

        .form-group label.required::after {
            content: " *";
            color: var(--danger);
        }

        .col-6 {
            max-width: 420px;
        }

        .col-8 {
            max-width: 100%;
        }

        select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border-default);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: #fff;
            color: var(--text-main);
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%236b7280' viewBox='0 0 16 16'><path d='M8 11L2 5h12z'/></svg>");
            background-repeat: no-repeat;
            background-position: right 14px center;
        }

        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        input[type="file"] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-default);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: #fff;
            color: var(--text-muted);
        }

        input[type="file"]::file-selector-button {
            border: 1px solid var(--border-default);
            background: #f3f4f6;
            color: var(--text-main);
            padding: 7px 14px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            margin-right: 12px;
        }

        input[type="file"]::file-selector-button:hover {
            background: #e5e7eb;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border-default);
        }

        .form-actions .btn {
            padding: 11px 22px;
            font-size: 14px;
        }

        #resultBox {
            background: #fafafa;
        }

        .notification {
            position: fixed;
            top: 24px;
            right: 24px;
            display: none;
            align-items: center;
            gap: 12px;
            background: #fff;
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-left: 4px solid var(--success);
            min-width: 280px;
            z-index: 999;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(22, 163, 74, 0.12);
            color: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-title {
            font-weight: 700;
            font-size: 14px;
        }

        .notification-message {
            font-size: 13px;
            color: var(--text-muted);
        }

        .notification-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            margin-left: auto;
        }

        .urdu-guide {
            direction: rtl;
            text-align: right;
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Inter', sans-serif;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }

        .urdu-guide h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
            font-weight: 700;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }

        .urdu-guide ul {
            margin: 0;
            padding-right: 20px;
            font-size: 14px;
            line-height: 1.9;
            color: #78350f;
        }

        .urdu-guide li {
            margin-bottom: 2px;
        }

        .urdu-guide b {
            color: #b45309;
        }
    </style>
</head>

<body class="light-mode">
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--border-default);">
                <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;">
                    <i class="fas fa-file-import"></i>
                    Import Products (Excel/CSV)
                </h2>
                <a href="product-list.php" class="btn btn-secondary btn-sm" style="padding: 0 16px; height: 40px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <div class="urdu-guide">
                <h4><i class="fas fa-info-circle"></i> رہنمائی</h4>
                <ul>
                    <li><b>Product Name</b> اور <b>Default Unit</b> بھرنا لازمی ہے، باقی تمام کالمز اختیاری (optional) ہیں۔</li>
                    <li><b>Product Type</b> از خود (by default) <b>Physical</b> پر سیٹ ہے &mdash; اگر پروڈکٹ سروس ہے تو اسے <b>Service</b> میں تبدیل کریں۔</li>
                    <li><b>Inventory Account</b> از خود (by default) <b>Trading Inventory</b> پر سیٹ ہے &mdash; صرف مختلف اکاؤنٹ ہونے کی صورت میں تبدیل کریں۔</li>
                    <li>جن کالمز میں ڈراپ ڈاؤن (dropdown) موجود ہے، وہاں صرف لسٹ میں سے ہی ویلیو منتخب کریں، ٹائپ نہ کریں۔</li>
                    <li>یہ امپورٹ صرف <b>Default Unit</b> والی پروڈکٹس سپورٹ کرتا ہے۔ UOM Group پروڈکٹس اور برانچ کے مطابق اوپننگ اسٹاک اس میں شامل نہیں &mdash; وہ بعد میں خود شامل کریں۔</li>
                </ul>
            </div>

            <div class="helper-text" style="margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i>
                This import only supports <strong>Default Unit</strong> products (single unit of measure). UOM Group products and Branch-wise Opening Stock are not part of this import &mdash; add those manually after import if needed.
            </div>

            <form id="importForm">
                <div class="form-grid">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-id-card"></i> Step 1 &mdash; Select Company (optional)
                        </h3>
                        <div class="form-group col-6">
                            <label for="company">
                                <i class="fas fa-building"></i> Company
                            </label>
                            <select id="company">
                                <option value="">No Company (leave blank)</option>
                            </select>
                            <div class="helper-text" style="margin-top: 8px;">
                                <i class="fas fa-info-circle"></i>
                                If selected, all imported products will be linked to this company
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
                            <div class="helper-text" style="margin-top: 8px;">
                                <i class="fas fa-info-circle"></i>
                                Don't have the file ready? <a href="../../../../server/api/inventory/products/product-template.php">Download the template</a> first.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-file-import"></i> Import Products
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
    <script src="../../../assets/js/inventory/products/product-import.js"></script>
    <script>
        // If the Company dropdown ends up with only ONE actual company
        // (i.e. just "No Company" + 1 real option), auto-select that company
        // instead of leaving "No Company" selected by default.
        // product-import.js fills #company asynchronously (after an AJAX call),
        // so we watch it with a MutationObserver instead of running this on
        // page load directly.
        (function () {
            const companySelect = document.getElementById('company');
            if (!companySelect) return;

            function autoSelectIfSingleCompany() {
                // Real companies = all options except the blank "No Company" one
                const realOptions = Array.from(companySelect.options).filter(opt => opt.value !== '');
                if (realOptions.length === 1 && companySelect.value === '') {
                    companySelect.value = realOptions[0].value;
                    // Let select2 (and any other listeners) know the value changed
                    if (window.jQuery) {
                        jQuery(companySelect).trigger('change');
                    } else {
                        companySelect.dispatchEvent(new Event('change'));
                    }
                }
            }

            // Run once in case options are already there (e.g. server-rendered)
            autoSelectIfSingleCompany();

            // Watch for options being added dynamically by product-import.js
            const observer = new MutationObserver(autoSelectIfSingleCompany);
            observer.observe(companySelect, { childList: true });
        })();
    </script>
</body>

</html>