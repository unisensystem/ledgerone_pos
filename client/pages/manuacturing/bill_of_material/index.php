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
    <title>Bill of Materials (BOM) entry</title>
    <!-- Font & icons (Inter, plus simple line icons for +/- actions) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/bill_of_material/styles.css">
</head>
<body>
    <div class="main-content">
    <div class="bom-container">
        <!-- main card (BOM card) -->
        <div class="card">
            <div class="card-header">
                <h2>Bill of Materials (BOM) entry</h2>
                <!-- optional indicator: left title, right empty for symmetry -->
            </div>

            <!-- BOM HEADER SECTION (Finished good, version, active) -->
            <div class="form-section">
                <!-- left-aligned title, but we can omit extra title – header fields are self describing -->
                <div class="header-fields">
                    <div class="field-group">
                        <label class="form-label" for="bomCode">BOM Code <span style="color:#E34F4F;">*</span></label>
                        <input type="text" class="form-control" id="bomCode" readonly required>
                        <div class="helper-text">Auto-generated</div>
                    </div>

                    <div class="field-group">
                        <label class="form-label" for="finishedGood">Finished good <span style="color:#E34F4F;">*</span></label>
                        <input type="text" class="form-control" id="finishedGood" list="productList" placeholder="Search product..." required>
                        <datalist id="productList">
                        </datalist>
                        <div class="helper-text">Type to search</div>
                    </div>

                    <div class="field-group">
                        <label class="form-label" for="bomBaseQty">BOM Base Qty <span style="color:#E34F4F;">*</span></label>
                        <input type="number" class="form-control" id="bomBaseQty" value="1" step="any" min="0.01" required>
                    </div>

                    <!-- Version (default 1.0) -->
                    <div class="field-group">
                        <label class="form-label" for="version">Version <span style="color:#E34F4F;">*</span></label>
                        <input type="text" class="form-control" id="version" value="1.0" required>
                    </div>

                    <!-- Active checkbox (default checked) -->
                    <div class="field-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="active" checked>
                            <label for="active">Active</label>
                        </div>
                        <div class="helper-text"> </div> <!-- spacer for alignment -->
                    </div>

                    <div class="field-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="batchLocked">
                            <label for="batchLocked">Batch Locked</label>
                        </div>
                        <div class="helper-text"> </div>
                    </div>
                </div>

                <div class="field-group" style="margin-top: 16px;">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea class="form-control" id="remarks" rows="3" placeholder="Enter remarks..."></textarea>
                </div>
            </div>

            <!-- RAW MATERIALS SECTION with dynamic rows -->
            <div class="raw-title">Raw materials</div>

            <!-- container for dynamic rows -->
            <div id="materialList" class="material-list">
                <!-- initial row (example) will be injected via javascript -->
            </div>

            <!-- button to add new row (ghost style, plus icon) -->
            <div style="margin: 16px 0 8px;">
                <button class="btn btn-ghost" id="addMaterialBtn" type="button" style="padding-left: 8px;">
                    <i class="las la-plus" style="font-size: 1.5rem;"></i> Add raw material
                </button>
            </div>

            <!-- FORM ACTIONS (right-aligned, sticky footer optional) -->
            <div class="form-actions">
                <button class="btn btn-secondary" type="button" id="cancelBtn">Cancel</button>
                <button class="btn btn-primary" type="button" id="saveBtn">Save BOM</button>
            </div>

            <!-- subtle note: sticky footer can be enabled but we keep as is -->
        </div>
    </div>
    </div>
    <script src="../../../assets/js/manufacturing/bill_of_material/script.js"></script>
</body>
</html>