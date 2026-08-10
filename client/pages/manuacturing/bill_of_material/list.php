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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOM List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/bill_of_material/list.css">
</head>
<body>
    <div class="main-content">
        <div class="list-container">
            <div id="successMessage" style="display:none;" class="success-message">
                <i class="las la-check-circle"></i>
                <span>BOM saved successfully!</span>
            </div>
            <div class="card">
                <div class="list-header">
                    <h2>Bill of Materials · List</h2>
                    <button class="btn btn-primary" onclick="window.location.href='index.php'"><i class="las la-plus"></i> New BOM</button>
                </div>

                <div class="filter-bar">
                    <div class="search-wrapper">
                        <i class="las la-search"></i>
                        <input type="search" placeholder="Search BOM..." id="searchInput">
                    </div>
                    <div class="filter-group">
                        <select class="filter-select" id="statusFilter">
                            <option value="all">All status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <select class="filter-select" id="fgFilter">
                            <option value="all">All finished goods</option>
                        </select>
                        <select class="filter-select" id="bomTypeFilter">
                            <option value="all">All BOM types</option>
                            <option value="BOM 1">BOM 1</option>
                            <option value="BOM 2">BOM 2</option>
                        </select>
                        <button class="btn btn-secondary" id="resetFiltersBtn"><i class="las la-redo-alt"></i> Reset</button>
                        <button class="btn btn-primary" id="printSelectedBtn" style="display:none;">
                            <i class="las la-print"></i> Print Selected (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="bomTable">
                        <thead>
                            <tr>
                                <th width="3%"><input type="checkbox" id="selectAllCheckbox" title="Select All"></th>
                                <th>BOM Code</th>
                                <th>Finished good</th>
                                <th>Version</th>
                                <th>BOM Type</th>
                                <th>Status</th>
                                <th>Raw materials</th>
                                <th>Last updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="9" style="text-align:center; padding:32px;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>BOM Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit BOM</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editBomId">
                <div class="field-group">
                    <label class="form-label">BOM Code</label>
                    <input type="text" class="form-control" id="editBomCode" readonly>
                </div>
                <div class="field-group">
                    <label class="form-label">Finished good</label>
                    <input type="text" class="form-control" id="editFinishedGood" readonly>
                </div>
                <div class="field-group">
                    <label class="form-label">BOM Base Qty</label>
                    <input type="number" class="form-control" id="editBomBaseQty" step="any" min="0.01">
                </div>
                <div class="field-group">
                    <label class="form-label">Version</label>
                    <input type="text" class="form-control" id="editVersion">
                </div>
                <div class="field-group">
                    <label class="form-label">BOM Type</label>
                    <select class="form-control" id="editBomType">
                        <option value="BOM 1">BOM 1</option>
                        <option value="BOM 2">BOM 2</option>
                    </select>
                </div>
                <div class="field-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="editActive">
                        <label for="editActive">Active</label>
                    </div>
                </div>
                <div class="field-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="editBatchLocked">
                        <label for="editBatchLocked">Batch Locked</label>
                    </div>
                </div>
                <div class="field-group">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" id="editRemarks" rows="3"></textarea>
                </div>
                <div class="raw-title">Raw materials</div>
                <div id="editMaterialList"></div>
                <button class="btn btn-ghost" id="editAddMaterialBtn"><i class="las la-plus"></i> Add material</button>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="updateBOM()">Update BOM</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/manufacturing/bill_of_material/list.js"></script>
</body>
</html>
