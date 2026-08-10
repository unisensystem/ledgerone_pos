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
    <title>FuelingSys ERP | Meter Opening Reading</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/sale/meter_invoice/invoice-add.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <div class="logo-icon">FS</div>
                <div class="logo-text">FuelingSys ERP</div>
            </div>
            <div class="header-actions">
                <!-- Placeholder for user menu or other actions -->
            </div>
        </header>
        
        <main>
            <h1 class="page-title">Meter Opening Reading</h1>
            <p class="page-description">Record the opening meter reading for fuel dispensing stations</p>
            
            <div class="form-card">
                <form id="meterReadingForm">
                    <div class="form-grid">
                        <div class="form-section">
                            <h3 class="section-heading">Basic Information</h3>
                            

                            
                            <div class="form-group">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" id="date" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Branch</label>
                                <div class="dropdown" id="branchDropdown">
                                    <div class="dropdown-toggle" id="branchToggle">
                                        Select Branch
                                    </div>
                                    <div class="dropdown-menu" id="branchMenu">
                                        <div class="dropdown-search">
                                            <input type="text" placeholder="Search branches..." id="branchSearch">
                                        </div>
                                        <div class="dropdown-items">
                                            <!-- Options will be populated by JS -->
                                        </div>
                                    </div>
                                </div>
                                <div class="form-error" id="branchError">Please select a branch</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Fueling Station</label>
                                <div class="dropdown" id="stationDropdown">
                                    <div class="dropdown-toggle" id="stationToggle">
                                        Select Station
                                    </div>
                                    <div class="dropdown-menu" id="stationMenu">
                                        <div class="dropdown-search">
                                            <input type="text" placeholder="Search stations..." id="stationSearch">
                                        </div>
                                        <div class="dropdown-items">
                                            <!-- Options will be populated by JS -->
                                        </div>
                                    </div>
                                </div>
                                <div class="form-error" id="stationError">Please select a fueling station</div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="section-heading">Product Details</h3>
                            
                            <div class="form-group">
                                <label class="form-label">Product Dispensed / Sold</label>
                                <div class="dropdown" id="productDropdown">
                                    <div class="dropdown-toggle" id="productToggle">
                                        Select Product
                                    </div>
                                    <div class="dropdown-menu" id="productMenu">
                                        <div class="dropdown-search">
                                            <input type="text" placeholder="Search products..." id="productSearch">
                                        </div>
                                        <div class="dropdown-items">
                                            <!-- Options will be populated by JS -->
                                        </div>
                                    </div>
                                </div>
                                <div class="form-error" id="productError">Please select a product</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Unit</label>
                                <div class="dropdown" id="unitDropdown">
                                    <div class="dropdown-toggle" id="unitToggle">
                                        Select Unit
                                    </div>
                                    <div class="dropdown-menu" id="unitMenu">
                                        <div class="dropdown-search">
                                            <input type="text" placeholder="Search units..." id="unitSearch">
                                        </div>
                                        <div class="dropdown-items">
                                            <!-- Options will be populated by JS -->
                                        </div>
                                    </div>
                                </div>
                                <div class="form-error" id="unitError">Please select a unit</div>
                            </div>
                            

                            
                            <div class="form-group">
                                <label for="openingReading" class="form-label">Opening Reading</label>
                                <input type="number" id="openingReading" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                                <div class="form-error" id="openingReadingError">Please enter a valid opening reading</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="resetBtn" class="btn btn-secondary">Reset Invoice</button>
                        <button type="submit" id="saveBtn" class="btn btn-primary">Save Invoice</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <div id="notification" class="notification"></div>
    
    <script src="../../../assets/js/sale/meter_invoice/invoice-add.js"></script>
</body>
</html>