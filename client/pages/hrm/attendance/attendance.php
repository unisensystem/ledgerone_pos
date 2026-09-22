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
    <style>
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 6px 10px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            color: var(--primary, #3498db);
            background-color: rgba(52, 152, 219, 0.1);
        }

        .btn-edit:hover {
            background-color: rgba(52, 152, 219, 0.2);
            transform: scale(1.1);
        }

        .btn-delete {
            color: var(--error, #e74c3c);
            background-color: rgba(231, 76, 60, 0.1);
        }

        .btn-delete:hover {
            background-color: rgba(231, 76, 60, 0.2);
            transform: scale(1.1);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--bg-secondary, #fff);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border, #e0e0e0);
            padding-bottom: 15px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary, #666);
        }

        .close-btn:hover {
            color: var(--text, #333);
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text, #333);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border, #ddd);
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary, #3498db);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-modal-primary {
            background-color: var(--primary, #3498db);
            color: white;
        }


        .date-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

.date-display input[type="date"] {
    background: transparent;
    border: 1px solid var(--border, #ddd);
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 14px;
    color: inherit;
    font-family: inherit;
    cursor: pointer;
}

.date-display input[type="date"]:focus {
    outline: none;
    border-color: var(--primary, #3498db);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

        .btn-modal-primary:hover {
            background-color: var(--primary-dark, #2980b9);
        }

        .btn-modal-secondary {
            background-color: var(--border, #e0e0e0);
            color: var(--text, #333);
        }

        .btn-modal-secondary:hover {
            background-color: var(--border-dark, #d0d0d0);
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Attendance Entry Form</h1>
           <div class="date-display">
    <i class="fas fa-calendar-alt"></i>
    <input type="date" id="currentDate" title="Select attendance date">
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
                <div class="form-row">
                    <div class="form-group">
                        <label for="attendanceStatus">Attendance Status</label>
                        <select id="attendanceStatus">
                            <option value="present">Present</option>
                            <option value="late">Late Arrival</option>
                            <option value="halfday">Half Day</option>
                            <option value="absent">Absent</option>
                        </select>
                        <div class="helper-text">Auto-set from check-in time vs. shift start + grace period below</div>
                    </div>

                    <div class="form-group">
                        <label for="gracePeriod">Late Arrival Grace Period (minutes)</label>
                        <input type="number" id="gracePeriod" min="0" max="120" step="1" value="15">
                        <div class="helper-text">Leeway for traffic etc. — check-in within this many minutes of shift start still counts as Present</div>
                    </div>
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
                        <th>Actions</th>
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

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Attendance Record</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" id="editEmployeeId" readonly>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="text" id="editDate" readonly>
                </div>
                <div class="form-group">
                    <label for="editCheckInTime">Check-in Time</label>
                    <input type="time" id="editCheckInTime">
                </div>
                <div class="form-group">
                    <label for="editCheckOutTime">Check-out Time</label>
                    <input type="time" id="editCheckOutTime">
                </div>
                <div class="form-group">
                    <label for="editStatus">Status</label>
                    <select id="editStatus">
                        <option value="present">Present</option>
                        <option value="late">Late Arrival</option>
                        <option value="halfday">Half Day</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editNotes">Notes</label>
                    <textarea id="editNotes" rows="3" placeholder="Add any notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-secondary" onclick="closeEditModal()">Cancel</button>
                <button class="btn-modal btn-modal-primary" onclick="saveEditedRecord()">Save Changes</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/hrm/attendance/attendance.js"></script>
</body>

</html>