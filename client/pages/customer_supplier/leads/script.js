document.addEventListener('DOMContentLoaded', function() {
    // Set current date with Karachi timezone
    const now = new Date();
    const karachiOffset = 5 * 60; // Karachi is UTC+5
    const localOffset = now.getTimezoneOffset();
    const karachiTime = new Date(now.getTime() + (karachiOffset + localOffset) * 60000);
    
    const leadDate = document.getElementById('leadDate');
    leadDate.value = karachiTime.toISOString().split('T')[0];
    
    const followupDate = document.getElementById('followupDate');
    followupDate.value = karachiTime.toISOString().split('T')[0];
    
    // Load next lead code from database
    loadNextLeadCode();
    
    // Form state
    let editingLeadId = null;
    let editingFollowupId = null;
    let currentLeadData = null;
    let allLeads = [];
    let leadFollowups = {};
    
    // Load initial data
    loadLeads();
    loadServices();
    
    // Search functionality
    const searchName = document.getElementById('searchName');
    const searchCompany = document.getElementById('searchCompany');
    const searchStatus = document.getElementById('searchStatus');
    const searchPriority = document.getElementById('searchPriority');
    const clearFilters = document.getElementById('clearFilters');
    
    [searchName, searchCompany, searchStatus, searchPriority].forEach(element => {
        element.addEventListener('input', filterLeads);
        element.addEventListener('change', filterLeads);
    });
    
    clearFilters.addEventListener('click', function() {
        searchName.value = '';
        searchCompany.value = '';
        searchStatus.value = '';
        searchPriority.value = '';
        displayLeads(allLeads);
    });
    
    // Toggle form visibility
    const toggleFormBtn = document.getElementById('toggleFormBtn');
    const leadFormCard = document.getElementById('leadFormCard');
    
    toggleFormBtn.addEventListener('click', function() {
        leadFormCard.style.display = leadFormCard.style.display === 'none' ? 'block' : 'none';
    });
    
    // Follow-up modal functionality
    const followupModal = document.getElementById('followupModal');
    const closeFollowupModal = document.getElementById('closeFollowupModal');
    const followupForm = document.getElementById('followupForm');
    
    closeFollowupModal.addEventListener('click', function() {
        followupModal.style.display = 'none';
        resetFollowupForm();
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === followupModal) {
            followupModal.style.display = 'none';
            resetFollowupForm();
        }
    });
    
    // Service type modal
    const serviceModal = document.getElementById('serviceModal');
    const addServiceBtn = document.getElementById('addServiceBtn');
    const closeModal = document.querySelector('#serviceModal .close');
    const saveServiceBtn = document.getElementById('saveServiceBtn');
    const newServiceInput = document.getElementById('newService');
    const serviceList = document.getElementById('serviceList');
    const serviceType = document.getElementById('serviceType');
    
    addServiceBtn.addEventListener('click', function() {
        serviceModal.style.display = 'flex';
    });
    
    closeModal.addEventListener('click', function() {
        serviceModal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === serviceModal) {
            serviceModal.style.display = 'none';
        }
    });
    
    // Add new service
    saveServiceBtn.addEventListener('click', async function() {
        const newService = newServiceInput.value.trim();
        if (newService) {
            try {
                const response = await fetch('api.php?action=service', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ service_name: newService })
                });
                
                if (response.ok) {
                    newServiceInput.value = '';
                    loadServices();
                    alert('Service added successfully!');
                } else {
                    alert('Error adding service');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    });
    
    // Delete service function
    async function deleteService(serviceId, serviceItem) {
        if (confirm('Are you sure you want to delete this service?')) {
            try {
                const response = await fetch(`api.php?action=service&id=${serviceId}`, {
                    method: 'DELETE'
                });
                
                if (response.ok) {
                    loadServices();
                    alert('Service deleted successfully!');
                } else {
                    alert('Error deleting service');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    }
    
    // Form validation for lead form
    const leadForm = document.getElementById('leadForm');
    
    leadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        let isValid = true;
        
        // Only validate required fields
        const requiredFields = ['leadDate', 'companyName'];
        
        requiredFields.forEach(field => {
            const element = document.getElementById(field);
            const errorElement = document.getElementById(field + 'Error');
            
            if (!element.value.trim()) {
                element.classList.add('input-error');
                errorElement.style.display = 'block';
                isValid = false;
            } else {
                element.classList.remove('input-error');
                errorElement.style.display = 'none';
            }
        });
        
        // Email validation (if provided)
        const email = document.getElementById('email');
        const emailError = document.getElementById('emailError');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email.value && !emailRegex.test(email.value)) {
            email.classList.add('input-error');
            emailError.style.display = 'block';
            isValid = false;
        } else {
            email.classList.remove('input-error');
            emailError.style.display = 'none';
        }
        
        // WhatsApp validation (if provided)
        const whatsapp = document.getElementById('whatsapp');
        const whatsappError = document.getElementById('whatsappError');
        
        if (whatsapp.value && !/^\+?[1-9]\d{1,14}$/.test(whatsapp.value)) {
            whatsapp.classList.add('input-error');
            whatsappError.style.display = 'block';
            isValid = false;
        } else {
            whatsapp.classList.remove('input-error');
            whatsappError.style.display = 'none';
        }
        
        if (isValid) {
            saveLead();
        }
    });
    
    // Form validation for follow-up form
    followupForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        let isValid = true;
        
        // Validate required fields
        const requiredFields = ['followupDate', 'eventType', 'accountManager', 'jobNo'];
        
        requiredFields.forEach(field => {
            const element = document.getElementById(field);
            const errorElement = document.getElementById(field + 'Error');
            
            if (!element.value.trim()) {
                element.classList.add('input-error');
                errorElement.style.display = 'block';
                isValid = false;
            } else {
                element.classList.remove('input-error');
                errorElement.style.display = 'none';
            }
        });
        
        if (isValid) {
            saveFollowup();
        }
    });
    
    // Reset form validation on input
    leadForm.addEventListener('input', function(e) {
        if (e.target.id) {
            const errorElement = document.getElementById(e.target.id + 'Error');
            if (errorElement) {
                e.target.classList.remove('input-error');
                errorElement.style.display = 'none';
            }
        }
    });
    
    followupForm.addEventListener('input', function(e) {
        if (e.target.id) {
            const errorElement = document.getElementById(e.target.id + 'Error');
            if (errorElement) {
                e.target.classList.remove('input-error');
                errorElement.style.display = 'none';
            }
        }
    });
    
    // Function to load the next lead code from database
    async function loadNextLeadCode() {
        try {
            const response = await fetch('api.php?action=next_lead_code');
            if (!response.ok) {
                throw new Error('API request failed');
            }
            const data = await response.json();
            
            if (data.lead_code) {
                const leadCode = document.getElementById('leadCode');
                leadCode.value = data.lead_code;
            } else {
                console.error('No lead_code in response:', data);
                document.getElementById('leadCode').value = 'LED001'; // Fallback
            }
        } catch (error) {
            console.error('Error loading lead code:', error);
            document.getElementById('leadCode').value = 'LED001'; // Fallback
        }
    }
    
    // API Functions
    async function loadLeads() {
        try {
            const response = await fetch('api.php?action=leads');
            const leads = await response.json();
            allLeads = leads;
            
            // Load follow-ups for all leads
            for (const lead of leads) {
                await loadFollowupsForLead(lead.id);
            }
            
            displayLeads(leads);
        } catch (error) {
            console.error('Error loading leads:', error);
        }
    }
    
    async function loadFollowupsForLead(leadId) {
        try {
            const response = await fetch(`api.php?action=followups&lead_id=${leadId}`);
            const followups = await response.json();
            leadFollowups[leadId] = followups;
            
            // Update the follow-ups display if the row is expanded
            const followupRow = document.getElementById(`followup-row-${leadId}`);
            if (followupRow && followupRow.classList.contains('show')) {
                const followupsContainer = document.getElementById(`followups-${leadId}`);
                if (followupsContainer) {
                    followupsContainer.innerHTML = renderFollowups(leadId);
                }
            }
            
            return followups;
        } catch (error) {
            console.error('Error loading followups for lead:', leadId, error);
            leadFollowups[leadId] = [];
            return [];
        }
    }
    
    function filterLeads() {
        const nameFilter = searchName.value.toLowerCase();
        const companyFilter = searchCompany.value.toLowerCase();
        const statusFilter = searchStatus.value;
        const priorityFilter = searchPriority.value;
        
        const filtered = allLeads.filter(lead => {
            const matchName = !lead.contact_name || lead.contact_name.toLowerCase().includes(nameFilter);
            const matchCompany = lead.company_name.toLowerCase().includes(companyFilter);
            const matchStatus = !statusFilter || lead.lead_status === statusFilter;
            const matchPriority = !priorityFilter || lead.priority === priorityFilter;
            
            return matchName && matchCompany && matchStatus && matchPriority;
        });
        
        displayLeads(filtered);
    }
    
    async function loadServices() {
        try {
            const response = await fetch('api.php?action=services');
            const services = await response.json();
            
            // Update dropdown
            serviceType.innerHTML = '<option value="">Select Service Type</option>';
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.service_name;
                option.textContent = service.service_name;
                serviceType.appendChild(option);
            });
            
            // Update modal list
            serviceList.innerHTML = '';
            services.forEach(service => {
                const li = document.createElement('li');
                li.className = 'service-item';
                li.innerHTML = `
                    <span>${service.service_name}</span>
                    <span class="delete-service" data-id="${service.id}"><i class="fas fa-trash"></i></span>
                `;
                serviceList.appendChild(li);
                
                li.querySelector('.delete-service').addEventListener('click', function() {
                    deleteService(this.dataset.id, li);
                });
            });
        } catch (error) {
            console.error('Error loading services:', error);
        }
    }
    
    async function saveLead() {
        const leadCode = document.getElementById('leadCode');
        const formData = {
            lead_code: leadCode.value,
            lead_source: document.getElementById('leadSource').value,
            lead_date: leadDate.value,
            contact_name: document.getElementById('contactName').value,
            company_name: document.getElementById('companyName').value,
            email: document.getElementById('email').value,
            whatsapp: document.getElementById('whatsapp').value,
            service_type: document.getElementById('serviceType').value,
            lead_status: document.getElementById('leadStatus').value,
            priority: document.getElementById('priority').value
        };
        
        try {
            const url = editingLeadId ? `api.php?action=lead&id=${editingLeadId}` : 'api.php?action=lead';
            const method = editingLeadId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            if (response.ok) {
                alert(editingLeadId ? 'Lead updated successfully!' : 'Lead saved successfully!');
                leadForm.reset();
                editingLeadId = null;
                
                loadNextLeadCode();
                leadDate.value = karachiTime.toISOString().split('T')[0];
                
                document.querySelector('#leadForm button[type="submit"]').textContent = 'Save Lead';
                loadLeads();
            } else {
                alert('Error saving lead');
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    async function saveFollowup() {
        if (!currentLeadData || !currentLeadData.id) {
            alert('No lead selected for follow-up');
            return;
        }
        
        const formData = {
            lead_id: currentLeadData.id,
            followup_date: document.getElementById('followupDate').value,
            event_type: document.getElementById('eventType').value,
            account_manager: document.getElementById('accountManager').value,
            job_no: document.getElementById('jobNo').value,
            remarks: document.getElementById('remarks').value
        };
        
        try {
            const url = editingFollowupId ? `api.php?action=followup&id=${editingFollowupId}` : 'api.php?action=followup';
            const method = editingFollowupId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            if (response.ok) {
                // Get updated follow-ups
                const updatedFollowups = await loadFollowupsForLead(currentLeadData.id);
                
                // Update the specific followup row without reloading the page
                const followupsContainer = document.getElementById(`followups-${currentLeadData.id}`);
                if (followupsContainer) {
                    followupsContainer.innerHTML = renderFollowups(currentLeadData.id);
                }
                
                alert(editingFollowupId ? 'Follow-up updated successfully!' : 'Follow-up saved successfully!');
                followupModal.style.display = 'none';
                resetFollowupForm();
            } else {
                const errorData = await response.json();
                alert('Error saving follow-up: ' + (errorData.error || 'Unknown error'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    function resetFollowupForm() {
        followupForm.reset();
        editingFollowupId = null;
        
        // Keep the current lead data for reference but reset the form
        followupDate.value = karachiTime.toISOString().split('T')[0];
        
        // Clear error states
        const errorElements = followupForm.querySelectorAll('.error-message');
        errorElements.forEach(el => el.style.display = 'none');
        
        const inputElements = followupForm.querySelectorAll('input, select, textarea');
        inputElements.forEach(el => el.classList.remove('input-error'));
    }
    
    function displayLeads(leads) {
        const tbody = document.getElementById('leadsList');
        tbody.innerHTML = '';
        
        leads.forEach(lead => {
            // Main lead row
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <a href="#" class="lead-code-clickable" data-id="${lead.id}">${lead.lead_code}</a>
                    <i class="fas fa-chevron-down collapse-arrow" data-id="${lead.id}" style="margin-left: 10px;"></i>
                </td>
                <td>${lead.contact_name || '-'}</td>
                <td>${lead.company_name}</td>
                <td>${lead.service_type || '-'}</td>
                <td><span class="status-badge status-${(lead.lead_status || '').toLowerCase().replace(' ', '-')}">${lead.lead_status || '-'}</span></td>
                <td class="priority-${(lead.priority || '').toLowerCase()}">${lead.priority || '-'}</td>
                <td>
                    <div class="action-buttons">
                     <button class="btn btn-info btn-sm view-lead" data-id="${lead.id}">
  <i class="fas fa-eye" style="font-size: 15px;"></i>
</button>
<button class="btn btn-warning btn-sm edit-lead" data-id="${lead.id}">
  <i class="fas fa-edit" style="color: #333333; font-size: 15px;"></i>
</button>
<button class="btn btn-danger btn-sm delete-lead" data-id="${lead.id}">
  <i class="fas fa-trash" style="font-size: 15px;"></i>
</button>

                    </div>
                </td>
            `;
            tbody.appendChild(row);
            
            // Follow-ups row
            const followupRow = document.createElement('tr');
            followupRow.className = 'followup-row';
            followupRow.id = `followup-row-${lead.id}`;
            followupRow.innerHTML = `
                <td colspan="7">
                    <div class="followup-content">
                        <div id="followups-${lead.id}">
                            ${renderFollowups(lead.id)}
                        </div>
                    </div>
                </td>
            `;
            tbody.appendChild(followupRow);
        });
        
        // Add event listeners to the newly created elements
        tbody.querySelectorAll('.lead-code-clickable').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                openFollowupModal(this.dataset.id);
            });
        });
        
        tbody.querySelectorAll('.collapse-arrow').forEach(arrow => {
            arrow.addEventListener('click', function() {
                toggleFollowups(this.dataset.id, this);
            });
        });
        
        tbody.querySelectorAll('.view-lead').forEach(btn => {
            btn.addEventListener('click', function() {
                viewLead(this.dataset.id);
            });
        });
        
        tbody.querySelectorAll('.edit-lead').forEach(btn => {
            btn.addEventListener('click', function() {
                editLead(this.dataset.id);
            });
        });
        
        tbody.querySelectorAll('.delete-lead').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteLead(this.dataset.id);
            });
        });
    }
    
    function renderFollowups(leadId) {
        const followups = leadFollowups[leadId] || [];
        
        if (followups.length === 0) {
            return '<p style="color: #888; font-style: italic;">No follow-ups yet.</p>';
        }
        
        let html = '';
        followups.forEach(followup => {
            html += `
                <div class="followup-item">
                    <div class="followup-details">
                        <strong>${followup.event_type}</strong> - ${followup.followup_date} <br>
                        <span style="color: #666;">Manager: ${followup.account_manager} | Job: ${followup.job_no}</span><br>
                        <span style="color: #888;">${followup.remarks || 'No remarks'}</span>
                    </div>
                    <div class="followup-actions">
                        <button class="btn btn-warning btn-sm edit-followup" data-id="${followup.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm delete-followup" data-id="${followup.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        // Add event listeners after updating the content
        setTimeout(() => {
            document.querySelectorAll('.edit-followup').forEach(btn => {
                btn.addEventListener('click', function() {
                    editFollowup(this.dataset.id);
                });
            });
            
            document.querySelectorAll('.delete-followup').forEach(btn => {
                btn.addEventListener('click', function() {
                    deleteFollowup(this.dataset.id);
                });
            });
        }, 0);
        
        return html;
    }
    
    // Global functions for DOM events
    window.toggleFollowups = function(leadId, arrowElement) {
        const followupRow = document.getElementById(`followup-row-${leadId}`);
        const isVisible = followupRow.classList.contains('show');
        
        if (isVisible) {
            followupRow.classList.remove('show');
            arrowElement.classList.remove('expanded');
        } else {
            followupRow.classList.add('show');
            arrowElement.classList.add('expanded');
            
            // Refresh follow-ups data when expanding
            loadFollowupsForLead(leadId);
        }
    };
    
    window.openFollowupModal = async function(leadId) {
        if (!leadId) {
            alert('Invalid lead ID');
            return;
        }
        
        try {
            const response = await fetch(`api.php?action=lead&id=${leadId}`);
            if (!response.ok) {
                throw new Error('Failed to fetch lead data');
            }
            const lead = await response.json();
            
            if (!lead || lead.error) {
                alert('Lead not found');
                return;
            }
            
            currentLeadData = lead;
            
            // Fill lead information in modal
            document.getElementById('modalLeadCode').textContent = lead.lead_code || '-';
            document.getElementById('modalContactName').textContent = lead.contact_name || '-';
            document.getElementById('modalCompanyName').textContent = lead.company_name || '-';
            document.getElementById('modalWhatsapp').textContent = lead.whatsapp || '-';
            
            // Reset form and show modal
            resetFollowupForm();
            followupModal.style.display = 'flex';
        } catch (error) {
            console.error('Error loading lead details:', error);
            alert('Error loading lead details: ' + error.message);
        }
    };
    
    window.editFollowup = async function(followupId) {
        try {
            const response = await fetch(`api.php?action=followup&id=${followupId}`);
            const followup = await response.json();
            
            if (followup.error) {
                alert('Follow-up not found');
                return;
            }
            
            // Load lead data
            const leadResponse = await fetch(`api.php?action=lead&id=${followup.lead_id}`);
            const lead = await leadResponse.json();
            currentLeadData = lead;
            
            // Fill modal with followup data
            editingFollowupId = followupId;
            document.getElementById('followupDate').value = followup.followup_date;
            document.getElementById('eventType').value = followup.event_type;
            document.getElementById('accountManager').value = followup.account_manager;
            document.getElementById('jobNo').value = followup.job_no;
            document.getElementById('remarks').value = followup.remarks || '';
            
            // Fill lead information
            document.getElementById('modalLeadCode').textContent = lead.lead_code;
            document.getElementById('modalContactName').textContent = lead.contact_name || '-';
            document.getElementById('modalCompanyName').textContent = lead.company_name;
            document.getElementById('modalWhatsapp').textContent = lead.whatsapp || '-';
            
            followupModal.style.display = 'flex';
        } catch (error) {
            alert('Error loading follow-up for editing');
        }
    };
    
    window.deleteFollowup = async function(followupId) {
        if (confirm('Are you sure you want to delete this follow-up?')) {
            try {
                // Find the lead ID for this followup
                let leadId = null;
                for (const [id, followups] of Object.entries(leadFollowups)) {
                    for (const followup of followups) {
                        if (followup.id == followupId) {
                            leadId = id;
                            break;
                        }
                    }
                    if (leadId) break;
                }
                
                const response = await fetch(`api.php?action=followup&id=${followupId}`, {
                    method: 'DELETE'
                });
                
                if (response.ok) {
                    // If we found the lead ID, update just that lead's followups
                    if (leadId) {
                        await loadFollowupsForLead(leadId);
                        const followupsContainer = document.getElementById(`followups-${leadId}`);
                        if (followupsContainer) {
                            followupsContainer.innerHTML = renderFollowups(leadId);
                        }
                    }
                    
                    alert('Follow-up deleted successfully!');
                } else {
                    alert('Error deleting follow-up');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    };
    
    // Action functions for leads
    window.viewLead = async function(id) {
        try {
            const response = await fetch(`api.php?action=lead&id=${id}`);
            const lead = await response.json();
            if (lead.error) {
                alert('Lead not found');
                return;
            }
            alert(`Lead Details:\n\nCode: ${lead.lead_code}\nContact: ${lead.contact_name || 'N/A'}\nCompany: ${lead.company_name}\nEmail: ${lead.email || 'N/A'}\nWhatsApp: ${lead.whatsapp || 'N/A'}\nService: ${lead.service_type || 'N/A'}\nStatus: ${lead.lead_status || 'N/A'}\nPriority: ${lead.priority || 'N/A'}`);
        } catch (error) {
            alert('Error loading lead details');
        }
    };
    
    window.editLead = async function(id) {
        try {
            const response = await fetch(`api.php?action=lead&id=${id}`);
            const lead = await response.json();
            if (lead.error) {
                alert('Lead not found');
                return;
            }
            
            editingLeadId = id;
            document.getElementById('leadCode').value = lead.lead_code;
            document.getElementById('leadSource').value = lead.lead_source || '';
            document.getElementById('leadDate').value = lead.lead_date.split(' ')[0];
            document.getElementById('contactName').value = lead.contact_name || '';
            document.getElementById('companyName').value = lead.company_name;
            document.getElementById('email').value = lead.email || '';
            document.getElementById('whatsapp').value = lead.whatsapp || '';
            document.getElementById('serviceType').value = lead.service_type || '';
            document.getElementById('leadStatus').value = lead.lead_status || '';
            document.getElementById('priority').value = lead.priority || '';
            
            leadFormCard.style.display = 'block';
            document.querySelector('#leadForm button[type="submit"]').textContent = 'Update Lead';
        } catch (error) {
            alert('Error loading lead for editing');
        }
    };
    
    window.deleteLead = async function(id) {
        if (confirm('Are you sure you want to delete this lead? This will also delete all associated follow-ups.')) {
            try {
                const response = await fetch(`api.php?action=lead&id=${id}`, {
                    method: 'DELETE'
                });
                
                if (response.ok) {
                    alert('Lead deleted successfully!');
                    loadLeads();
                } else {
                    alert('Error deleting lead');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    };
    
});