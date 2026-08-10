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
    <title>LedgerOne ERP - Attendance Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/attendance/attendance.css">
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Attendance Entry Form</h1>
            <div class="date-display">
                <i class="fas fa-calendar-alt"></i> <span id="currentDate">Loading...</span>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Main Form Card -->
        <div class="card">
            <h2 class="card-title">Employee Attendance Entry</h2>

            <!-- Employee Details Section -->
            <div class="form-section">
                <h3>Employee Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="employeeId" class="required">Employee ID</label>
                        <div class="custom-dropdown">
                            <input type="text" id="employeeId" placeholder="Scan or type employee ID" autocomplete="off">
                            <button type="button" class="btn-scan" id="scanBarcodeBtn">
                                <i class="fas fa-camera"></i> Scan Barcode
                            </button>
                            <div class="dropdown-list" id="employeeDropdown"></div>
                        </div>
                        <div class="error-text" id="employeeIdError"></div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="autoAttendance" checked>
                            Auto Attendance
                        </label>
                        <div class="helper-text">Automatically check-in/out when employee is selected</div>
                    </div>



                    <div class="form-group">
                        <label for="department" class="required">Department</label>
                        <select id="department">
                            <option value="">Select Department</option>
                        </select>
                        <div class="error-text" id="departmentError"></div>
                    </div>
                </div>
            </div>

            <!-- Time Entry Section -->
            <div class="form-section">
                <h3>Attendance Time Entry</h3>
                <div class="time-entry-section">
                    <!-- Check-in Card -->
                    <div class="time-card">
                        <h3><i class="fas fa-sign-in-alt"></i> Check-in</h3>
                        <div class="time-display" id="checkInTime">--:-- --</div>
                        <div>
                            <label for="checkInInput">Check-in Time</label>
                            <input type="time" id="checkInInput">
                            <div class="helper-text">Expected time: 9:00 AM</div>
                            <div class="error-text" id="checkInError"></div>
                        </div>
                        <div class="time-status status-pending" id="checkInStatus">Pending</div>
                    </div>

                    <!-- Check-out Card -->
                    <div class="time-card">
                        <h3><i class="fas fa-sign-out-alt"></i> Check-out</h3>
                        <div class="time-display" id="checkOutTime">--:-- --</div>
                        <div>
                            <label for="checkOutInput">Check-out Time</label>
                            <input type="time" id="checkOutInput">
                            <div class="helper-text">Expected time: 5:00 PM</div>
                            <div class="error-text" id="checkOutError"></div>
                        </div>
                        <div class="time-status status-pending" id="checkOutStatus">Pending</div>
                    </div>
                </div>

                <!-- Attendance Status -->
                <div class="form-group">
                    <label for="attendanceStatus">Attendance Status</label>
                    <select id="attendanceStatus">
                        <option value="present">Present</option>
                        <option value="late">Late Arrival</option>
                        <option value="halfday">Half Day</option>
                        <option value="absent">Absent</option>
                    </select>
                    <div class="helper-text">Select the overall attendance status for the day</div>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" rows="3"
                        placeholder="Add any notes or remarks regarding attendance"></textarea>
                    <div class="helper-text">Optional: Add any remarks about late arrival, early departure, etc.</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="button-group">
                <button class="btn btn-success" id="checkInBtn">
                    <i class="fas fa-clock"></i> Check-in Now
                </button>
                <button class="btn btn-warning" id="checkOutBtn">
                    <i class="fas fa-clock"></i> Check-out Now
                </button>
                <button class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
                <button class="btn btn-secondary" id="clearBtn">
                    <i class="fas fa-redo"></i> Clear Form
                </button>
            </div>
        </div>

        <!-- Attendance History -->
        <div class="card attendance-history">
            <h2 class="card-title">Attendance Records</h2>
            
            <!-- Search Filters -->
            <div class="form-row" style="margin-bottom: var(--spacing-lg);">
                <div class="form-group">
                    <label for="filterDate">Date</label>
                    <input type="date" id="filterDate">
                </div>
                <div class="form-group">
                    <label for="filterEmployee">Employee</label>
                    <input type="text" id="filterEmployee" placeholder="Search by ID or name">
                </div>
                <div class="form-group">
                    <label for="filterDepartment">Department</label>
                    <select id="filterDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <!-- Records will be populated by JavaScript -->
                </tbody>
            </table>
            <div class="helper-text" style="margin-top: var(--spacing-md);">
                Showing today's attendance records. Use the form above to add new entries.
            </div>
        </div>
    </div>

    <script src="../../../assets/js/hrm/attendance/attendance.js"></script>
</body>

</html>