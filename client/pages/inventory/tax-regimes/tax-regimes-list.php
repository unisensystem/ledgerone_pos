<?php
require_once '../../../../includes/dashboard.php';

session_start();
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
    <title>LedgerOne ERP - Tax Regimes</title>
    <link rel="stylesheet" href="../../../assets/css/inventory/tax-regimes/tax-regimes.css">
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 style="margin: 0;">Tax Regimes Management</h1>
            <button type="button" class="btn btn-primary" id="addNewBtn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px;">
                    <path d="M8 1V15M1 8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Add Tax Regime
            </button>
        </div>

        <div class="card search-section">
            <div class="search-filters">
                <div class="form-group">
                    <input type="text" id="searchInput" placeholder="Search by regime name or code..." class="search-input">
                </div>
                <div class="form-group">
                    <select id="countryFilter" class="filter-select">
                        <option value="">All Countries</option>
                    </select>
                </div>
                <div class="form-group">
                    <select id="taxAuthorityFilter" class="filter-select">
                        <option value="">All Authorities</option>
                        <option value="FBR">FBR</option>
                        <option value="SRB">SRB</option>
                        <option value="PRA">PRA</option>
                        <option value="KPRA">KPRA</option>
                        <option value="BRA">BRA</option>
                        <option value="OTHER">OTHER</option>
                    </select>
                </div>
                <div class="form-group">
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="button" class="btn btn-secondary" id="resetFilters">Reset</button>
            </div>
        </div>

        <div class="card">
            <div id="loadingSpinner" style="text-align: center; display: none;">
                <p>Loading tax regimes...</p>
            </div>
            <table id="taxRegimesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Regime Name</th>
                        <th>Code</th>
                        <th>Country</th>
                        <th>Authority</th>
                        <th>Tax Base</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Effective From</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                </tbody>
            </table>
            
            <div id="paginationContainer" class="pagination-container">
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="taxRegimeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Tax Regime</h2>
                <button type="button" class="modal-close" id="closeModal">&times;</button>
            </div>
            <form id="taxRegimeForm" class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Regime Name</label>
                        <input type="text" id="regimeName" name="regime_name" required placeholder="e.g., Standard GST Regime">
                    </div>
                    <div class="form-group">
                        <label class="required">Regime Code</label>
                        <input type="text" id="regimeCode" name="regime_code" required placeholder="e.g., STANDARD_GST">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Country</label>
                        <select id="countryId" name="country_id" required>
                            <option value="">Select Country</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Tax Authority</label>
                        <select id="taxAuthority" name="tax_authority" required>
                            <option value="">Select Authority</option>
                            <option value="FBR">FBR</option>
                            <option value="SRB">SRB</option>
                            <option value="PRA">PRA</option>
                            <option value="KPRA">KPRA</option>
                            <option value="BRA">BRA</option>
                            <option value="OTHER">OTHER</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Application Level</label>
                        <select id="applicationLevel" name="application_level" required>
                            <option value="item">Item</option>
                            <option value="invoice">Invoice</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Tax Base</label>
                        <select id="taxBase" name="tax_base" required>
                            <option value="">Select Tax Base</option>
                            <option value="trade_price">Trade Price</option>
                            <option value="mrp">MRP</option>
                            <option value="import_value">Import Value</option>
                            <option value="turnover">Turnover</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Legal Reference</label>
                        <input type="text" id="legalReference" name="legal_reference" placeholder="e.g., Sec 3(1) Sales Tax Act 1990">
                    </div>
                    <div class="form-group">
                        <label>Finance Act Year</label>
                        <input type="number" id="financeActYear" name="finance_act_year" min="2000" value="2024">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Formula Template</label>
                        <input type="text" id="formulaTemplate" name="formula_template" required placeholder="e.g., {trade_price} * {rate} / 100">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Applies At Stage</label>
                        <select id="appliesAtStage" name="applies_at_stage" required>
                            <option value="all">All</option>
                            <option value="manufacturer">Manufacturer</option>
                            <option value="importer">Importer</option>
                            <option value="distributor">Distributor</option>
                            <option value="retailer">Retailer</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Effective From</label>
                        <input type="date" id="effectiveFrom" name="effective_from" required>
                    </div>
                    <div class="form-group">
                        <label>Effective To</label>
                        <input type="date" id="effectiveTo" name="effective_to">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_tax_inclusive"> Is Tax Inclusive
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_single_stage"> Is Single Stage
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="downstream_exempt"> Downstream Exempt
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_adjustable" checked> Is Adjustable
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_refundable"> Is Refundable
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_final_tax"> Is Final Tax
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" checked> Is Active
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Tax regime description"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../../assets/js/inventory/tax-regimes/tax-regimes-list.js?v=<?php echo time(); ?>"></script>
</body>
</html>
