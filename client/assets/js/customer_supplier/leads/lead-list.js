// Sample data for leads
const leadsData = [
    {
        id: 1,
        leadCode: "LD-2023-00127",
        contactName: "Alex Johnson",
        businessName: "Johnson Enterprises",
        leadSource: "Website",
        status: "New",
        priority: "High",
        productService: "Fuel Management",
        leadDate: "2023-10-15",
        email: "alex@johnsonent.com",
        phone: "+1 (555) 123-4567",
        followups: [
            { date: "2023-10-16", type: "Call", employee: "John Smith", remarks: "Initial contact made" },
            { date: "2023-10-18", type: "Email", employee: "Sarah Johnson", remarks: "Sent product brochure" }
        ]
    },
    {
        id: 2,
        leadCode: "LD-2023-00128",
        contactName: "Maria Garcia",
        businessName: "Garcia Logistics",
        leadSource: "Referral",
        status: "Contacted",
        priority: "Medium",
        productService: "Fleet Analytics",
        leadDate: "2023-10-12",
        email: "maria@garcialogistics.com",
        phone: "+1 (555) 987-6543",
        followups: [
            { date: "2023-10-13", type: "Call", employee: "Michael Chen", remarks: "Discussed requirements" }
        ]
    },
    {
        id: 3,
        leadCode: "LD-2023-00129",
        contactName: "David Wilson",
        businessName: "Wilson Transport",
        leadSource: "Social Media",
        status: "Qualified",
        priority: "High",
        productService: "ERP Integration",
        leadDate: "2023-10-10",
        email: "david@wilsontransport.com",
        phone: "+1 (555) 456-7890",
        followups: [
            { date: "2023-10-11", type: "Meeting", employee: "Emma Davis", remarks: "On-site meeting conducted" },
            { date: "2023-10-14", type: "Site Visit", employee: "Robert Wilson", remarks: "Client site visited" },
            { date: "2023-10-17", type: "Follow-up Call", employee: "John Smith", remarks: "Follow-up on proposal" }
        ]
    },
    {
        id: 4,
        leadCode: "LD-2023-00130",
        contactName: "Lisa Chen",
        businessName: "Chen Solutions",
        leadSource: "WhatsApp",
        status: "Proposal Sent",
        priority: "Medium",
        productService: "Custom Solution",
        leadDate: "2023-10-05",
        email: "lisa@chensolutions.com",
        phone: "+1 (555) 321-0987",
        followups: [
            { date: "2023-10-06", type: "WhatsApp", employee: "Sarah Johnson", remarks: "Initial inquiry" },
            { date: "2023-10-08", type: "Call", employee: "Michael Chen", remarks: "Requirements gathering" },
            { date: "2023-10-12", type: "Email", employee: "Emma Davis", remarks: "Proposal sent" }
        ]
    },
    {
        id: 5,
        leadCode: "LD-2023-00131",
        contactName: "James Miller",
        businessName: "Miller & Co.",
        leadSource: "Website",
        status: "New",
        priority: "Low",
        productService: "Inventory Control",
        leadDate: "2023-10-20",
        email: "james@millerco.com",
        phone: "+1 (555) 654-3210",
        followups: []
    },
    {
        id: 6,
        leadCode: "LD-2023-00132",
        contactName: "Sophia Williams",
        businessName: "Williams Industries",
        leadSource: "Referral",
        status: "Contacted",
        priority: "Medium",
        productService: "Fuel Management",
        leadDate: "2023-10-18",
        email: "sophia@williamsind.com",
        phone: "+1 (555) 789-0123",
        followups: [
            { date: "2023-10-19", type: "Call", employee: "John Smith", remarks: "Initial contact" }
        ]
    },
    {
        id: 7,
        leadCode: "LD-2023-00133",
        contactName: "Robert Brown",
        businessName: "Brown Logistics",
        leadSource: "Other",
        status: "Qualified",
        priority: "High",
        productService: "Fleet Analytics",
        leadDate: "2023-10-16",
        email: "robert@brownlogistics.com",
        phone: "+1 (555) 234-5678",
        followups: [
            { date: "2023-10-17", type: "Meeting", employee: "Emma Davis", remarks: "Requirements meeting" },
            { date: "2023-10-19", type: "Email", employee: "Sarah Johnson", remarks: "Sent additional info" }
        ]
    },
    {
        id: 8,
        leadCode: "LD-2023-00134",
        contactName: "Emily Taylor",
        businessName: "Taylor Transport",
        leadSource: "Website",
        status: "New",
        priority: "Medium",
        productService: "ERP Integration",
        leadDate: "2023-10-22",
        email: "emily@taylortransport.com",
        phone: "+1 (555) 876-5432",
        followups: []
    }
];

// Current expanded lead
let expandedLeadId = null;
let currentLeadCode = null;

document.addEventListener('DOMContentLoaded', function () {
    // Initialize leads table
    renderLeadsTable(leadsData);

    // Set today's date as default for followup date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('followupDate').value = today;

    // Add Lead button
    document.getElementById('addLeadBtn').addEventListener('click', function () {
        showToast('Redirecting to Lead Entry Form...', 'success');
        // In a real app, this would redirect to the lead entry form
        setTimeout(() => {
            window.location.href = 'leads-entry-form.html';
        }, 1000);
    });

    // Export button
    document.getElementById('exportBtn').addEventListener('click', function () {
        showToast('Exporting leads data...', 'success');
        // In a real app, this would trigger a CSV/Excel export
    });

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function () {
        filterLeads();
    });

    // Filter functionality
    document.getElementById('statusFilter').addEventListener('change', filterLeads);
    document.getElementById('priorityFilter').addEventListener('change', filterLeads);
    document.getElementById('sourceFilter').addEventListener('change', filterLeads);

    // Modal functionality
    document.getElementById('modalClose').addEventListener('click', closeModal);
    document.getElementById('modalCancel').addEventListener('click', closeModal);

    // Save follow-up
    document.getElementById('modalSave').addEventListener('click', function (e) {
        e.preventDefault();

        // Validate form
        const followupDate = document.getElementById('followupDate').value;
        const eventType = document.getElementById('eventType').value;
        const employee = document.getElementById('employee').value;

        if (!followupDate || !eventType || !employee) {
            showToast('Please fill in all required fields', 'error');
            return;
        }

        // Get form data
        const followupData = {
            date: followupDate,
            type: eventType,
            employee: employee,
            remarks: document.getElementById('remarks').value || 'No remarks'
        };

        // In a real app, this would be sent to a backend API
        console.log('Adding follow-up for lead:', currentLeadCode, followupData);

        // Add follow-up to the lead data
        const leadIndex = leadsData.findIndex(lead => lead.leadCode === currentLeadCode);
        if (leadIndex !== -1) {
            leadsData[leadIndex].followups.push(followupData);

            // Re-render the table to show new follow-up
            renderLeadsTable(leadsData);

            // If this lead was expanded, keep it expanded
            if (expandedLeadId === leadsData[leadIndex].id) {
                setTimeout(() => {
                    toggleFollowups(leadsData[leadIndex].id);
                }, 100);
            }
        }

        // Show success message
        showToast('Follow-up added successfully!', 'success',
            `Follow-up added to lead ${currentLeadCode}`);

        // Close modal and reset form
        closeModal();
        document.getElementById('followupForm').reset();
        document.getElementById('followupDate').value = today;
    });

    // Close modal when clicking outside
    document.getElementById('followupModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeModal();
        }
    });
});

// Render leads table
function renderLeadsTable(leads) {
    const tableBody = document.getElementById('leadsTableBody');
    tableBody.innerHTML = '';

    leads.forEach(lead => {
        const row = document.createElement('tr');
        row.setAttribute('data-lead-id', lead.id);

        // Determine if this row is expanded
        const isExpanded = expandedLeadId === lead.id;

        // Get event icon class based on type
        function getEventIconClass(type) {
            switch (type) {
                case 'Call': return 'fas fa-phone event-call';
                case 'WhatsApp': return 'fab fa-whatsapp event-whatsapp';
                case 'Meeting': return 'fas fa-handshake event-meeting';
                case 'Site Visit': return 'fas fa-building event-site-visit';
                case 'Email': return 'fas fa-envelope event-email';
                case 'Follow-up Call': return 'fas fa-phone-volume event-followup-call';
                case 'Visit': return 'fas fa-car event-visit';
                default: return 'fas fa-calendar-check';
            }
        }

        row.innerHTML = `
                    <td class="lead-code-cell">
                        <div class="lead-code ${isExpanded ? 'expanded' : ''}" onclick="toggleFollowups(${lead.id})" data-lead-code="${lead.leadCode}">
                            <i class="fas fa-chevron-right expand-icon"></i>
                            ${lead.leadCode}
                        </div>
                        
                        <!-- Follow-ups container -->
                        <div class="follow-ups-container ${isExpanded ? 'expanded' : ''}" id="followups-${lead.id}">
                            <div class="follow-ups-header">
                                <span>Follow-up History for ${lead.leadCode}</span>
                                <button class="btn btn-primary btn-sm" onclick="openFollowupModal('${lead.leadCode}')">
                                    <i class="fas fa-plus"></i> Add Follow-up
                                </button>
                            </div>
                            
                            ${lead.followups.length > 0 ? `
                                <table class="follow-ups-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Event Type</th>
                                            <th>Employee</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${lead.followups.map(followup => `
                                            <tr>
                                                <td>${formatDate(followup.date)}</td>
                                                <td>
                                                    <span class="event-icon">
                                                        <i class="${getEventIconClass(followup.type)}"></i>
                                                        ${followup.type}
                                                    </span>
                                                </td>
                                                <td>${followup.employee}</td>
                                                <td>${followup.remarks}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            ` : `
                                <div class="no-followups">
                                    <i class="fas fa-history" style="font-size: 24px; margin-bottom: 8px;"></i>
                                    <div>No follow-ups recorded yet</div>
                                    <button class="btn btn-primary btn-sm" onclick="openFollowupModal('${lead.leadCode}')" style="margin-top: 12px;">
                                        <i class="fas fa-plus"></i> Add First Follow-up
                                    </button>
                                </div>
                            `}
                        </div>
                    </td>
                    <td>${lead.contactName}</td>
                    <td>${lead.businessName}</td>
                    <td>${lead.leadSource}</td>
                    <td><span class="status-badge status-${lead.status.toLowerCase().replace(' ', '-')}">${lead.status}</span></td>
                    <td>
                        <span class="priority-indicator priority-${lead.priority.toLowerCase()}">
                            <span class="priority-dot"></span>
                            ${lead.priority}
                        </span>
                    </td>
                    <td>${lead.productService}</td>
                    <td>${formatDate(lead.leadDate)}</td>
                    <td><span class="status-badge">${lead.followups.length} follow-ups</span></td>
                    <td>
                        <button class="btn btn-ghost btn-sm" onclick="openFollowupModal('${lead.leadCode}')" title="Add Follow-up">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="editLead(${lead.id})" title="Edit Lead">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                `;

        tableBody.appendChild(row);
    });

    // Update total count
    document.getElementById('totalLeads').textContent = `Total: ${leads.length} leads`;
}

// Toggle follow-ups visibility
function toggleFollowups(leadId) {
    const leadCodeElement = document.querySelector(`[data-lead-id="${leadId}"] .lead-code`);
    const followupsContainer = document.getElementById(`followups-${leadId}`);

    if (expandedLeadId === leadId) {
        // Collapse if already expanded
        expandedLeadId = null;
        leadCodeElement.classList.remove('expanded');
        followupsContainer.classList.remove('expanded');
    } else {
        // Close any previously expanded lead
        if (expandedLeadId) {
            const prevLeadCode = document.querySelector(`[data-lead-id="${expandedLeadId}"] .lead-code`);
            const prevContainer = document.getElementById(`followups-${expandedLeadId}`);
            if (prevLeadCode) prevLeadCode.classList.remove('expanded');
            if (prevContainer) prevContainer.classList.remove('expanded');
        }

        // Expand this lead
        expandedLeadId = leadId;
        leadCodeElement.classList.add('expanded');
        followupsContainer.classList.add('expanded');
    }
}

// Open follow-up modal
function openFollowupModal(leadCode) {
    currentLeadCode = leadCode;
    document.getElementById('selectedLeadCode').value = leadCode;
    document.getElementById('modalTitle').textContent = `Add Follow-up for ${leadCode}`;
    document.getElementById('followupModal').classList.add('active');

    // Set today's date as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('followupDate').value = today;
}

// Close modal
function closeModal() {
    document.getElementById('followupModal').classList.remove('active');
    document.getElementById('followupForm').reset();

    // Reset to today's date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('followupDate').value = today;
}

// Filter leads based on search and filters
function filterLeads() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const priorityFilter = document.getElementById('priorityFilter').value;
    const sourceFilter = document.getElementById('sourceFilter').value;

    const filteredLeads = leadsData.filter(lead => {
        // Search term filter
        const matchesSearch = !searchTerm ||
            lead.leadCode.toLowerCase().includes(searchTerm) ||
            lead.contactName.toLowerCase().includes(searchTerm) ||
            lead.businessName.toLowerCase().includes(searchTerm) ||
            lead.productService.toLowerCase().includes(searchTerm);

        // Status filter
        const matchesStatus = !statusFilter || lead.status === statusFilter;

        // Priority filter
        const matchesPriority = !priorityFilter || lead.priority === priorityFilter;

        // Source filter
        const matchesSource = !sourceFilter || lead.leadSource === sourceFilter;

        return matchesSearch && matchesStatus && matchesPriority && matchesSource;
    });

    renderLeadsTable(filteredLeads);
}

// Edit lead function
function editLead(leadId) {
    const lead = leadsData.find(l => l.id === leadId);
    if (lead) {
        showToast(`Editing lead ${lead.leadCode}...`, 'success');
        // In a real app, this would open an edit form or redirect to edit page
    }
}

// Format date for display
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Toast notification function
function showToast(message, type = 'success', details = '') {
    const toast = document.getElementById('toast');
    const toastIcon = toast.querySelector('.toast-icon');
    const toastMessage = toast.querySelector('.toast-message');
    const toastDetails = document.getElementById('toastDetails');

    // Update toast content
    toastMessage.textContent = message;
    toastDetails.textContent = details;

    // Update toast type
    toast.className = 'toast';
    toast.classList.add(type);

    // Show toast
    toast.classList.add('show');

    // Hide toast after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
    }, 5000);
}