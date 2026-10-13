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
    <title>LedgerOne ERP | Territory Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="../../../assets/css/master_setup/territory_setup/territory-add.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Territory Setup</h1>
                <p>Define geographical hierarchy for your organization</p>
            </div>
            <div>
                <button class="btn btn-secondary" onclick="window.location.href='territory-list.php'" style="margin-right: 10px;">
                    <i class="fas fa-list"></i> Go to List
                </button>
                <button class="btn btn-secondary" id="helpBtn">
                    <i class="fas fa-question-circle"></i> Help
                </button>
            </div>
        </div>

        <div class="form-container">
            <div class="card">
                <h2>Country Information</h2>
                <div class="form-group">
                    <label class="form-label" for="countryName">Country Name</label>
                    <input type="text" id="countryName" class="form-input" placeholder="e.g. Pakistan, United States">
                    <div class="helper-text">Enter the country name</div>
                </div>

                <button class="btn btn-primary" id="addCountryBtn">
                    <i class="fas fa-plus"></i> Add Country
                </button>
            </div>

            <div class="card">
                <h2>Region Information</h2>
                <div class="form-group">
                    <label class="form-label" for="regionName">Region Name</label>
                    <input type="text" id="regionName" class="form-input" placeholder="e.g. Sindh, Punjab">
                    <div class="helper-text">Enter the region/province/state name</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="selectCountry">Select Country</label>
                    <select id="selectCountry" class="form-input">
                        <option value="">-- Select a country --</option>
                        <!-- Options will be populated by JS -->
                    </select>
                    <div class="helper-text">Choose a country to add region under</div>
                </div>

                <button class="btn btn-primary" id="addRegionBtn">
                    <i class="fas fa-plus"></i> Add Region
                </button>
            </div>

            <div class="card">
                <h2>City Information</h2>
                <div class="form-group">
                    <label class="form-label" for="cityName">City Name</label>
                    <input type="text" id="cityName" class="form-input" placeholder="e.g. Karachi, Lahore">
                    <div class="helper-text">Enter the city name</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="selectRegion">Select Region</label>
                    <select id="selectRegion" class="form-input">
                        <option value="">-- Select a region --</option>
                        <!-- Options will be populated by JS -->
                    </select>
                    <div class="helper-text">Choose a region to add city under</div>
                </div>

                <button class="btn btn-primary" id="addCityBtn">
                    <i class="fas fa-plus"></i> Add City
                </button>
            </div>

            <div class="card hierarchy-section">
                <h2>Territory Hierarchy</h2>
                <p class="helper-text">Visual representation of your territory structure</p>

                <div class="hierarchy-container" id="hierarchyContainer">
                    <!-- Hierarchy will be dynamically generated here -->
                    <div class="empty-state" id="emptyHierarchy">
                        <i class="fas fa-layer-group"></i>
                        <p>No territories added yet. Start by adding a country.</p>
                    </div>
                </div>

                <div class="success-message" id="successMessage">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Territory added successfully!</strong>
                        <div>Your hierarchy has been updated.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>City Zones & Areas</h2>
                <div class="form-group">
                    <label class="form-label" for="zoneName">Zone Name</label>
                    <input type="text" id="zoneName" class="form-input" placeholder="e.g. Gulshan-e-Iqbal, Clifton">
                    <div class="helper-text">Enter the city zone/district name</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="selectCity">Select City</label>
                    <select id="selectCity" class="form-input">
                        <option value="">-- Select a city --</option>
                        <!-- Options will be populated by JS -->
                    </select>
                    <div class="helper-text">Choose a city to add zone under</div>
                </div>

                <button class="btn btn-primary" id="addZoneBtn">
                    <i class="fas fa-plus"></i> Add Zone
                </button>

                <div class="form-group" style="margin-top: var(--spacing-xl);">
                    <label class="form-label" for="areaName">Area Name</label>
                    <input type="text" id="areaName" class="form-input" placeholder="e.g. Block 13D, Sector 5">
                    <div class="helper-text">Enter the area/neighborhood name</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="selectZone">Select Zone</label>
                    <select id="selectZone" class="form-input">
                        <option value="">-- Select a zone --</option>
                        <!-- Options will be populated by JS -->
                    </select>
                    <div class="helper-text">Choose a zone to add area under</div>
                </div>

                <button class="btn btn-primary" id="addAreaBtn">
                    <i class="fas fa-plus"></i> Add Area
                </button>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-ghost" id="resetBtn">
                <i class="fas fa-redo"></i> Reset Form
            </button>
            <button class="btn btn-secondary" id="saveDraftBtn">
                <i class="fas fa-save"></i> Save Draft
            </button>
            <button class="btn btn-primary" id="submitBtn">
                <i class="fas fa-check-circle"></i> Complete Setup
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/master_setup/territory_setup/territory_add.js"></script>
</body>

</html>