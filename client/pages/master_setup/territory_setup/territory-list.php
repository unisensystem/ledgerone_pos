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
    <title>LedgerOne ERP | Territory List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="../../../assets/css/master_setup/territory_setup/territory-list.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Territory List</h1>
                <p>View and manage all territories</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="window.location.href='territory-add.php'">
                    <i class="fas fa-plus"></i> Add Territory
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <h3><i class="fas fa-filter"></i> Filters</h3>
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="searchInput" class="form-input" placeholder="Search by name...">
                </div>
                <div class="filter-group">
                    <label>Filter by Country</label>
                    <select id="filterCountry" class="form-input">
                        <option value="">All Countries</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Filter by Region</label>
                    <select id="filterRegion" class="form-input">
                        <option value="">All Regions</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Filter by City</label>
                    <select id="filterCity" class="form-input">
                        <option value="">All Cities</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Filter by Zone</label>
                    <select id="filterZone" class="form-input">
                        <option value="">All Zones</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Filter by Area</label>
                    <select id="filterArea" class="form-input">
                        <option value="">All Areas</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-secondary" id="clearFilters">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="countries">
                <i class="fas fa-globe"></i> Countries
            </button>
            <button class="tab-btn" data-tab="regions">
                <i class="fas fa-map"></i> Regions
            </button>
            <button class="tab-btn" data-tab="cities">
                <i class="fas fa-city"></i> Cities
            </button>
            <button class="tab-btn" data-tab="zones">
                <i class="fas fa-th-large"></i> Zones
            </button>
            <button class="tab-btn" data-tab="areas">
                <i class="fas fa-map-marker-alt"></i> Areas
            </button>
        </div>

        <!-- Content Sections -->
        <div class="content-card">
            <!-- Countries Tab -->
            <div class="tab-content active" id="countries-tab">
                <div class="table-header">
                    <h3>Countries</h3>
                    <span class="count-badge" id="countriesCount">0</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sr#</th>
                                <th>Country Name</th>
                                <th>Regions</th>
                                <th>Cities</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="countriesTable">
                            <tr class="loading-row">
                                <td colspan="5"><i class="fas fa-spinner fa-spin"></i> Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Regions Tab -->
            <div class="tab-content" id="regions-tab">
                <div class="table-header">
                    <h3>Regions</h3>
                    <span class="count-badge" id="regionsCount">0</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sr#</th>
                                <th>Region Name</th>
                                <th>Country</th>
                                <th>Cities</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="regionsTable">
                            <tr class="loading-row">
                                <td colspan="5"><i class="fas fa-spinner fa-spin"></i> Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cities Tab -->
            <div class="tab-content" id="cities-tab">
                <div class="table-header">
                    <h3>Cities</h3>
                    <span class="count-badge" id="citiesCount">0</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sr#</th>
                                <th>City Name</th>
                                <th>Region</th>
                                <th>Country</th>
                                <th>Zones</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="citiesTable">
                            <tr class="loading-row">
                                <td colspan="6"><i class="fas fa-spinner fa-spin"></i> Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Zones Tab -->
            <div class="tab-content" id="zones-tab">
                <div class="table-header">
                    <h3>Zones</h3>
                    <span class="count-badge" id="zonesCount">0</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sr#</th>
                                <th>Zone Name</th>
                                <th>City</th>
                                <th>Region</th>
                                <th>Areas</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="zonesTable">
                            <tr class="loading-row">
                                <td colspan="6"><i class="fas fa-spinner fa-spin"></i> Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Areas Tab -->
            <div class="tab-content" id="areas-tab">
                <div class="table-header">
                    <h3>Areas</h3>
                    <span class="count-badge" id="areasCount">0</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sr#</th>
                                <th>Area Name</th>
                                <th>Zone</th>
                                <th>City</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="areasTable">
                            <tr class="loading-row">
                                <td colspan="5"><i class="fas fa-spinner fa-spin"></i> Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Success!</span>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/master_setup/territory_setup/territory_list.js"></script>
</body>

</html>
