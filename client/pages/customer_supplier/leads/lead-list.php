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
    <title>LedgerOne ERP | Leads List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/leads/lead-list.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-users"></i> Leads Management</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" id="exportBtn">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-primary" id="addLeadBtn">
                    <i class="fas fa-plus"></i> Add New Lead
                </button>
            </div>
        </header>
        
        <main class="main-layout">
            <div class="card">
                <div class="card-title">
                    <span>Leads List</span>
                    <span id="totalLeads">Total: 8 leads</span>
                </div>
                
                <div class="table-controls">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" placeholder="Search leads...">
                    </div>
                    
                    <div class="filters">
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="New">New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Proposal Sent">Proposal Sent</option>
                        </select>
                        
                        <select id="priorityFilter">
                            <option value="">All Priority</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                        
                        <select id="sourceFilter">
                            <option value="">All Sources</option>
                            <option value="Website">Website</option>
                            <option value="Referral">Referral</option>
                            <option value="Social Media">Social Media</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="leadsTable">
                        <thead>
                            <tr>
                                <th>Lead Code</th>
                                <th>Contact Name</th>
                                <th>Business Name</th>
                                <th>Lead Source</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Product/Service</th>
                                <th>Lead Date</th>
                                <th>Follow-ups</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leadsTableBody">
                            <!-- Leads will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
        
        <!-- Follow-up Modal -->
        <div class="modal-overlay" id="followupModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 id="modalTitle">Add Follow-up</h3>
                    <button class="modal-close" id="modalClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <form id="followupForm" class="modal-form">
                        <input type="hidden" id="selectedLeadCode">
                        
                        <div class="form-group">
                            <label for="followupDate">Follow-up Date *</label>
                            <input type="date" id="followupDate" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="eventType">Event Type *</label>
                            <select id="eventType" required>
                                <option value="">Select type</option>
                                <option value="Call">Call</option>
                                <option value="WhatsApp">WhatsApp</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Site Visit">Site Visit</option>
                                <option value="Email">Email</option>
                                <option value="Follow-up Call">Follow-up Call</option>
                                <option value="Visit">Visit</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="employee">Follow-up By Employee *</label>
                            <select id="employee" required>
                                <option value="">Select employee</option>
                                <option value="John Smith">John Smith</option>
                                <option value="Sarah Johnson">Sarah Johnson</option>
                                <option value="Michael Chen">Michael Chen</option>
                                <option value="Emma Davis">Emma Davis</option>
                                <option value="Robert Wilson">Robert Wilson</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" placeholder="Enter follow-up remarks..."></textarea>
                            <div class="helper-text">Add any notes or details about this follow-up</div>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="modalCancel">Cancel</button>
                    <button class="btn btn-primary" id="modalSave">Save Follow-up</button>
                </div>
            </div>
        </div>
        
        <!-- Toast Notification -->
        <div class="toast" id="toast">
            <i class="fas fa-check-circle toast-icon"></i>
            <div class="toast-content">
                <div class="toast-message">Follow-up added successfully!</div>
                <div class="toast-details" id="toastDetails"></div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/customer_supplier/leads/lead-list.js"></script>
</body>
</html>