<?php
require_once '../../../../includes/dashboard.php';
require_once '../../../../includes/connection.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id and tenant_id from session
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Fetch employees for account managers
$stmt = $pdo->prepare("SELECT full_name FROM employees WHERE tenant_id = ? ORDER BY full_name");
$stmt->execute([$tenant_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Only adding minimal styles for new follow-up functionality */
        .lead-code-clickable {
            color: #007bff;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
        }

        .lead-code-clickable:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .collapse-arrow {
            cursor: pointer;
            transition: transform 0.3s ease;
            color: #007bff;
            margin-left: 10px;
        }

        .collapse-arrow.expanded {
            transform: rotate(180deg);
        }

        .followup-row {
            background: #f8f9fa;
            display: none;
        }

        .followup-row.show {
            display: table-row;
        }

        .followup-content {
            padding: 15px;
            border-left: 4px solid #007bff;
        }

        .followup-item {
            background: white;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .followup-details {
            flex: 1;
        }

        .followup-actions {
            display: flex;
            gap: 5px;
        }

        /* Follow-up modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 5px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .followup-modal-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .followup-form-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .followup-info-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 3px solid #007bff;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }

        .info-label {
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .followup-modal-content {
                grid-template-columns: 1fr;
            }
        }
        .with-legend-line {
            position: relative;
            padding-top: 35px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            background-color: white;
        }

        .legend-line-title {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background-color: white;
            padding: 0 15px;
            color: #007bff;
            font-weight: 600;
            font-size: 1.5rem;
            line-height: 1;
        }
    </style>
</head>
<body style="background-color: #ffffff;">
    <div class="container">
        <header>
            <h1 style="color:#4A90E2;">Leads Management System</h1>
            <button class="btn btn-primary" id="toggleFormBtn">
                Toggle Mode
            </button>
        </header>

        <div class="card" id="leadFormCard" style="
            position: relative;
            padding-top: 20px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            background: white;">
            <span style="
                position: absolute;
                top: -13px;
                left: 50%;
                transform: translateX(-50%);
                background: white;
                padding: 0 10px;
                color: #4A90E2;
                font-weight: 600;
                font-size: 1.3rem;
                font-weight: 600;
            ">Lead Entry Form</span>
            <form id="leadForm">
                <div class="form-container">
                    <div class="form-group">
                        <label for="leadCode">Lead Code</label>
                        <input type="text" id="leadCode" readonly>
                    </div>

                    <div class="form-group">
                        <label for="leadSource">Lead Source</label>
                        <select id="leadSource">
                            <option value="">Select Source</option>
                            <option value="Website">Website</option>
                            <option value="Referral">Referral</option>
                            <option value="Social Media">Social Media</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Other">Other</option>
                        </select>
                        <div class="error-message" id="leadSourceError">Please select a lead source</div>
                    </div>

                    <div class="form-group">
                        <label for="leadDate" class="required">Lead Date</label>
                        <input type="date" id="leadDate" required>
                        <div class="error-message" id="leadDateError">Please select a valid date</div>
                    </div>

                    <div class="form-group">
                        <label for="contactName">Contact Name</label>
                        <input type="text" id="contactName">
                        <div class="error-message" id="contactNameError">Please enter a contact name</div>
                    </div>

                    <div class="form-group">
                        <label for="companyName" class="required">Company Name</label>
                        <input type="text" id="companyName" required>
                        <div class="error-message" id="companyNameError">Please enter a company name</div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email">
                        <div class="error-message" id="emailError">Please enter a valid email address</div>
                    </div>

                    <div class="form-group">
                        <label for="whatsapp">WhatsApp</label>
                        <input type="text" id="whatsapp" placeholder="e.g. +1234567890">
                        <div class="error-message" id="whatsappError">Please enter a valid WhatsApp number</div>
                    </div>

                    <div class="form-group">
                        <label for="serviceType">Service Type</label>
                        <div class="service-actions">
                            <select id="serviceType">
                                <option value="">Select Service Type</option>
                                <option value="Web Development">Web Development</option>
                                <option value="SEO">SEO</option>
                                <option value="Social Media Marketing">Social Media Marketing</option>
                            </select>
                            <button type="button" class="btn btn-outline" id="addServiceBtn">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="error-message" id="serviceTypeError">Please select a service type</div>
                    </div>

                    <div class="form-group">
                        <label for="leadStatus">Lead Status</label>
                        <select id="leadStatus">
                            <option value="">Select Status</option>
                            <option value="New">New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Proposal Sent">Proposal Sent</option>
                        </select>
                        <div class="error-message" id="leadStatusError">Please select a lead status</div>
                    </div>

                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority">
                            <option value="">Select Priority</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                        <div class="error-message" id="priorityError">Please select a priority level</div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn btn-outline">Reset</button>
                        <button type="submit" class="btn btn-primary">Save Lead</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card" style="position: relative; padding-top: 35px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 25px; margin-bottom: 25px; background-color: white;">
            <h2 style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background-color: white; padding: 0 15px; color: #007bff; font-weight: 600; font-size: 1.5rem; line-height: 1;">
                Leads List
            </h2>
            <div class="search-filters">
                <input type="text" id="searchName" placeholder="Search by name...">
                <input type="text" id="searchCompany" placeholder="Search by company...">
                <select id="searchStatus">
                    <option value="">All Status</option>
                    <option value="New">New</option>
                    <option value="Contacted">Contacted</option>
                    <option value="Qualified">Qualified</option>
                    <option value="Proposal Sent">Proposal Sent</option>
                </select>
                <select id="searchPriority">
                    <option value="">All Priority</option>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
                <button class="btn btn-outline" id="clearFilters">Clear</button>
            </div>
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>Lead Code</th>
                        <th>Contact Name</th>
                        <th>Company</th>
                        <th>Service Type</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="leadsList">
                    <!-- Dynamic content will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Follow-up Modal -->
    <div class="modal" id="followupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Follow-up</h3>
                <span class="close" id="closeFollowupModal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="followupForm">
                    <div class="followup-modal-content">
                        <div class="followup-form-section">
                            <h4>Follow-up Details</h4>
                            <div class="form-group">
                                <label for="followupDate" class="required">Date</label>
                                <input type="date" id="followupDate" required>
                                <div class="error-message" id="followupDateError">Please select a date</div>
                            </div>

                            <div class="form-group">
                                <label for="eventType" class="required">Event Type</label>
                                <select id="eventType" required>
                                    <option value="">Select Event Type</option>
                                    <option value="Call">Call</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Meeting">Meeting</option>
                                    <option value="Site Visit">Site Visit</option>
                                    <option value="Email">Email</option>
                                    <option value="Follow-up Call">Follow-up Call</option>
                                    <option value="Visit">Visit</option>
                                </select>
                                <div class="error-message" id="eventTypeError">Please select an event type</div>
                            </div>

                            <div class="form-group">
                                <label for="accountManager" class="required">Account Manager</label>
                                <select id="accountManager" required>
                                    <option value="">Select Account Manager</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo htmlspecialchars($employee['full_name']); ?>">
                                            <?php echo htmlspecialchars($employee['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="error-message" id="accountManagerError">Please select an account manager</div>
                            </div>

                            <div class="form-group">
                                <label for="jobNo" class="required">Job No</label>
                                <input type="text" id="jobNo" required placeholder="Enter Job Number (e.g. JOB-001)">
                                <div class="error-message" id="jobNoError">Please enter a valid job number</div>
                            </div>

                            <div class="form-group">
                                <label for="remarks">Remarks</label>
                                <textarea id="remarks" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Enter follow-up remarks..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Submit Follow-up</button>
                        </div>

                        <div class="followup-info-section">
                            <h4>Lead Information</h4>
                            <div class="info-item">
                                <span class="info-label">Code:</span>
                                <span class="info-value" id="modalLeadCode">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Name:</span>
                                <span class="info-value" id="modalContactName">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Company:</span>
                                <span class="info-value" id="modalCompanyName">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">WhatsApp:</span>
                                <span class="info-value" id="modalWhatsapp">-</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Service Type Modal -->
    <div class="modal" id="serviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Manage Service Types</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Add, edit, or remove service types from the list.</p>
                
                <ul class="service-list" id="serviceList">
                    <li class="service-item">
                        <span>Web Development</span>
                        <span class="delete-service"><i class="fas fa-trash"></i></span>
                    </li>
                    <li class="service-item">
                        <span>SEO</span>
                        <span class="delete-service"><i class="fas fa-trash"></i></span>
                    </li>
                    <li class="service-item">
                        <span>Social Media Marketing</span>
                        <span class="delete-service"><i class="fas fa-trash"></i></span>
                    </li>
                </ul>
                
                <div class="modal-form">
                    <input type="text" id="newService" placeholder="Enter new service type">
                    <button class="btn btn-success" id="saveServiceBtn">Add Service</button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>