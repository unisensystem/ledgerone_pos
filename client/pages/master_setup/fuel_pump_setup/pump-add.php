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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FuelingSys ERP - Stations Setup</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="../../../assets/css/master_setup/fuel_pump_setup/pump-add.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Fueling Stations Setup</h1>
        </div>

        <div class="card">
            <h2 class="card-title">Station Information</h2>
            
            <form id="stationForm">
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="parentBranch" class="form-label required">Parent Branch</label>
                            <div class="searchable-select">
                                <input type="text" id="parentBranch" class="form-input" placeholder="Search branches..." autocomplete="off" required>
                                <input type="hidden" id="parentBranchId" name="parentBranchId">
                                <div class="dropdown-list" id="branchDropdown"></div>
                            </div>
                            <div class="form-helper">Select the parent branch for this station</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pumpName" class="form-label required">Fuel Pump Name / No</label>
                            <input type="text" id="pumpName" class="form-input" placeholder="e.g., Pump 1, Diesel Station" required>
                            <div class="form-helper">Enter the name or number for this fuel pump</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pumpType" class="form-label">Pump Type</label>
                            <div class="searchable-select">
                                <input type="text" id="pumpType" class="form-input" placeholder="Search pump types..." autocomplete="off">
                                <input type="hidden" id="pumpTypeId" name="pumpTypeId">
                                <div class="dropdown-list" id="pumpTypeDropdown"></div>
                            </div>
                            <div class="form-helper">Select the type of fuel dispensed</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="manufacturer" class="form-label">Manufacturer</label>
                            <input type="text" id="manufacturer" class="form-input" placeholder="e.g., Wayne, Gilbarco">
                            <div class="form-helper">Pump manufacturer name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" id="model" class="form-input" placeholder="e.g., Ovation, Encore">
                            <div class="form-helper">Pump model number</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="serialNumber" class="form-label">Serial Number</label>
                            <input type="text" id="serialNumber" class="form-input" placeholder="Enter serial number">
                            <div class="form-helper">Unique serial number</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="flowRate" class="form-label">Flow Rate (L/Min)</label>
                            <input type="number" id="flowRate" class="form-input" placeholder="e.g., 45" step="0.01">
                            <div class="form-helper">Maximum flow rate in liters per minute</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pumpStatus" class="form-label">Status</label>
                            <select id="pumpStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <div class="form-helper">Current operational status</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="installationDate" class="form-label">Installation Date</label>
                            <input type="date" id="installationDate" class="form-input">
                            <div class="form-helper">Date when pump was installed</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastService" class="form-label">Last Service Date</label>
                            <input type="date" id="lastService" class="form-input">
                            <div class="form-helper">Date of last maintenance service</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="locationDescription" class="form-label">Location Description</label>
                            <input type="text" id="locationDescription" class="form-input" placeholder="e.g., Bay 1, North Side">
                            <div class="form-helper">Physical location description</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Station</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../../assets/js/master_setup/fuel_pump_setup/pump-add.js"></script>
</body>
</html>