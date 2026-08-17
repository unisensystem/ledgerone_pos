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

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . '/ledgerone_erp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit of Measure Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/unit_measurement/list.css">
</head>
<body>
    <div class="main-content">
        <div class="list-container">
            <div id="successMessage" style="display:none;" class="success-message">
                <i class="las la-check-circle"></i>
                <span>UOM saved successfully!</span>
            </div>
            <div class="card">
                <div class="list-header">
                    <h2>Unit of Measure Management</h2>
                    <button class="btn btn-primary" onclick="openAddModal()"><i class="las la-plus"></i> Add New UOM</button>
                </div>

                <div class="filter-bar">
                    <div class="search-wrapper">
                        <i class="las la-search"></i>
                        <input type="search" placeholder="Search UOM..." id="searchInput">
                    </div>
                    <div class="filter-group">
                        <select class="filter-select" id="typeFilter">
                            <option value="all">All types</option>
                            <option value="Quantity">Quantity</option>
                            <option value="Count">Count</option>
                            <option value="Weight">Weight</option>
                            <option value="Length">Length</option>
                            <option value="Area">Area</option>
                            <option value="Volume">Volume</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Time">Time</option>
                        </select>
                        <select class="filter-select" id="baseFilter">
                            <option value="all">All units</option>
                            <option value="1">Base units only</option>
                            <option value="0">Derived units only</option>
                        </select>
                        <button class="btn btn-secondary" id="resetFiltersBtn"><i class="las la-redo-alt"></i> Reset</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>UOM Name</th>
                                <th>Type</th>
                                <th>Base Unit</th>
                                <th>Conversion Factor</th>
                                <th>Is Base Unit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="6" style="text-align:center; padding:32px;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="uomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New UOM</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="uomId">
                <div class="field-group">
                    <label class="form-label">UOM Name <span style="color:#E34F4F;">*</span></label>
                    <input type="text" class="form-control" id="uomName" required>
                </div>
                <div class="field-group">
                    <label class="form-label">UOM Type <span style="color:#E34F4F;">*</span></label>
                    <div style="display:flex; gap:8px;">
                        <select class="form-control" id="uomType" required style="flex:1;">
                            <option value="">Select Type</option>
                            <option value="Quantity">Quantity</option>
                            <option value="Count">Count</option>
                            <option value="Weight">Weight</option>
                            <option value="Length">Length</option>
                            <option value="Area">Area</option>
                            <option value="Volume">Volume</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Time">Time</option>
                            <option value="__custom__">+ Add New Type</option>
                        </select>
                    </div>
                    <input type="text" class="form-control" id="customType" placeholder="Enter new type" style="display:none; margin-top:8px;">
                </div>
                <div class="field-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="isBaseUnit">
                        <label for="isBaseUnit">Is Base Unit?</label>
                    </div>
                    <div class="helper-text">Check if this is the primary unit for this type</div>
                </div>
                <div class="field-group" id="baseUnitGroup" style="display:none;">
                    <label class="form-label">Base Unit <span style="color:#E34F4F;">*</span></label>
                    <select class="form-control" id="baseUnitId">
                        <option value="">Select Base Unit</option>
                    </select>
                </div>
                <div class="field-group" id="conversionGroup" style="display:none;">
                    <label class="form-label">Conversion Factor <span style="color:#E34F4F;">*</span></label>
                    <input type="number" class="form-control" id="conversionFactor" step="0.000001" value="1">
                    <div class="helper-text">1 current unit = factor × base unit</div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="saveUOM()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>UOM Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="../../../assets/js/manufacturing/unit_measurement/list.js"></script>
</body>
</html>
