<?php
require_once '../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!$_SESSION['user_id'] ?? null) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../assets/css/rent/rent.css">
</head>
<body>
    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Issue Rent</h2>
                    <button class="btn btn-secondary" onclick="window.location.href='rent-list.php'">View List</button>
                </div>

                <div class="form-section">
                    <h3>Basic Information</h3>
                    <div class="form-grid">
                        <div class="field-group">
                            <label>Rent No <span class="required">*</span></label>
                            <input type="text" id="rentNo" class="form-control" readonly>
                        </div>
                        <div class="field-group">
                            <label>Branch <span class="required">*</span></label>
                            <select id="branch" class="form-control" required></select>
                        </div>
                        <div class="field-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" id="date" class="form-control" required>
                        </div>
                        <div class="field-group">
                            <label>Issue Date <span class="required">*</span></label>
                            <input type="date" id="issueDate" class="form-control" required>
                        </div>
                        <div class="field-group">
                            <label>Expected Return Date <span class="required">*</span></label>
                            <input type="date" id="expectedReturnDate" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Customer Details</h3>
                    <div class="form-grid">
                        <div class="field-group">
                            <label>Customer <span class="required">*</span></label>
                            <input type="text" id="customer" list="customerList" class="form-control" required>
                            <datalist id="customerList"></datalist>
                        </div>
                        <div class="field-group">
                            <label>Phone</label>
                            <input type="text" id="customerPhone" class="form-control">
                        </div>
                        <div class="field-group">
                            <label>Address</label>
                            <input type="text" id="customerAddress" class="form-control">
                        </div>
                        <div class="field-group">
                            <label>NIC No</label>
                            <input type="text" id="nicNo" class="form-control">
                        </div>
                        <div class="field-group">
                            <label>NIC Picture</label>
                            <input type="file" id="nicPicture" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Reference Details</h3>
                    <div class="form-grid">
                        <div class="field-group">
                            <label>Reference Name</label>
                            <input type="text" id="refName" class="form-control">
                        </div>
                        <div class="field-group">
                            <label>Reference Phone</label>
                            <input type="text" id="refPhone" class="form-control">
                        </div>
                        <div class="field-group">
                            <label>Reference NIC</label>
                            <input type="text" id="refNic" class="form-control">
                        </div>
                        <div class="field-group">
                            <label>Reference NIC Picture</label>
                            <input type="file" id="refNicPicture" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Item Details</h3>
                    <div id="itemList"></div>
                    <button class="btn btn-ghost" onclick="addItemRow()"><i class="las la-plus"></i> Add Item</button>
                </div>

                <div class="form-section">
                    <h3>Rent Details</h3>
                    <div class="form-grid">
                        <div class="field-group">
                            <label>Rent Type <span class="required">*</span></label>
                            <select id="rentType" class="form-control" onchange="calculateRent()">
                                <option value="Daily">Daily</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Rent Rate <span class="required">*</span></label>
                            <input type="number" id="rentRate" class="form-control" step="0.01" onchange="calculateRent()">
                        </div>
                        <div class="field-group">
                            <label>Total Days <span class="required">*</span></label>
                            <input type="number" id="totalDays" class="form-control" readonly>
                        </div>
                        <div class="field-group">
                            <label>Total Rent Amount</label>
                            <input type="number" id="totalRentAmount" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="field-group">
                        <label>Remarks</label>
                        <textarea id="remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="location.reload()">Cancel</button>
                    <button class="btn btn-primary" onclick="saveRent()">Issue Rent</button>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/js/rent/rent.js"></script>
</body>
</html>
