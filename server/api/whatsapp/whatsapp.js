        // DOM Elements
        const integrationToggle = document.getElementById('integrationToggle');
        const integrationContent = document.getElementById('integrationContent');
        const selectAllForms = document.getElementById('selectAllForms');
        const formCheckboxes = document.querySelectorAll('.form-list input[type="checkbox"]');
        const refreshQRBtn = document.getElementById('refreshQR');
        const saveSettingsBtn = document.getElementById('saveSettings');
        const testIntegrationBtn = document.getElementById('testIntegration');
        const disconnectBtn = document.getElementById('disconnect');
        const countdownElement = document.getElementById('countdown');
        const qrCodeElement = document.querySelector('.qr-code');
        
        const CLIENT_ID = 'web_client_1';
        const API_BASE = 'api.php';

        // Toggle Integration
        integrationToggle.addEventListener('change', function() {
            integrationContent.style.display = this.checked ? 'block' : 'none';
        });

        // Select All Forms
        selectAllForms.addEventListener('change', function() {
            formCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Individual checkbox logic
        formCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllForms.checked = false;
                } else {
                    const allChecked = Array.from(formCheckboxes).every(cb => cb.checked);
                    selectAllForms.checked = allChecked;
                }
            });
        });

        // QR Code Refresh
        let countdown = 59;
        let countdownInterval;

        function startCountdown() {
            countdown = 59;
            countdownElement.textContent = countdown;
            
            clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    refreshQRCode();
                }
            }, 1000);
        }

        async function refreshQRCode() {
            try {
                const response = await fetch(`${API_BASE}?action=generate_qr&client_id=${CLIENT_ID}`);
                const data = await response.json();
                
                if (data.status === 'pending') {
                    startCountdown();
                    pollForQR();
                } else {
                    alert('Failed to generate QR code');
                }
            } catch (error) {
                console.error('Error generating QR:', error);
                alert('Error connecting to WhatsApp service');
            }
        }
        
        async function pollForQR() {
            try {
                const response = await fetch(`${API_BASE}?action=get_qr&client_id=${CLIENT_ID}`);
                const data = await response.json();
                
                if (data.status === 'success' && data.qr_base64) {
                    qrCodeElement.innerHTML = `<img src="${data.qr_base64}" alt="QR Code" style="width: 140px; height: 140px;">`;
                } else if (data.status === 'waiting') {
                    setTimeout(pollForQR, 2000);
                }
            } catch (error) {
                console.error('Error fetching QR:', error);
            }
        }

        refreshQRBtn.addEventListener('click', refreshQRCode);
        startCountdown();

        // Save Settings
        saveSettingsBtn.addEventListener('click', function() {
            const selectedForms = [];
            formCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedForms.push(checkbox.nextElementSibling.textContent);
                }
            });
            
            alert(`Settings saved! WhatsApp integration is enabled for: ${selectedForms.join(', ')}`);
        });

        // Test Integration
        testIntegrationBtn.addEventListener('click', function() {
            showPhoneModal();
        });
        
        function showPhoneModal() {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal">
                    <div class="modal-header">
                        <h3>Test WhatsApp Integration</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <label>Phone Number (with country code):</label>
                        <input type="text" id="phoneInput" placeholder="e.g., 1234567890" />
                        <div class="loading" id="loadingDiv" style="display: none;">
                            <div class="spinner"></div>
                            <span>Sending message...</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                        <button class="btn btn-primary" id="sendBtn">Send Test Message</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const phoneInput = modal.querySelector('#phoneInput');
            const sendBtn = modal.querySelector('#sendBtn');
            const cancelBtn = modal.querySelector('#cancelBtn');
            const closeBtn = modal.querySelector('.modal-close');
            const loadingDiv = modal.querySelector('#loadingDiv');
            
            phoneInput.focus();
            
            function closeModal() {
                document.body.removeChild(modal);
            }
            
            closeBtn.onclick = closeModal;
            cancelBtn.onclick = closeModal;
            
            sendBtn.onclick = async function() {
                const testNumber = phoneInput.value.trim();
                if (!testNumber) {
                    showToast('Please enter a phone number', 'error');
                    return;
                }
                
                sendBtn.disabled = true;
                loadingDiv.style.display = 'flex';
                
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 35000); // 35 second timeout
                    
                    const response = await fetch(`${API_BASE}?action=send_message`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            client_id: CLIENT_ID,
                            number: testNumber,
                            message: 'Test message from your website integration!'
                        }),
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeoutId);
                    
                    const data = await response.json();
                    if (data.status === 'success') {
                        showToast('Test message sent successfully!', 'success');
                        closeModal();
                    } else {
                        showToast('Failed to send message: ' + (data.detail || data.error || 'Unknown error'), 'error');
                    }
                } catch (error) {
                    if (error.name === 'AbortError') {
                        showToast('Request timeout - message may still be processing', 'error');
                    } else {
                        showToast('Error connecting to WhatsApp service', 'error');
                    }
                } finally {
                    sendBtn.disabled = false;
                    loadingDiv.style.display = 'none';
                }
            };
        }
        
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        // Disconnect WhatsApp
        disconnectBtn.addEventListener('click', async function() {
            console.log('Disconnect button clicked');
            if (confirm('Are you sure you want to disconnect your WhatsApp account?')) {
                console.log('User confirmed disconnect');
                try {
                    const response = await fetch(`${API_BASE}?action=disconnect`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ client_id: CLIENT_ID })
                    });
                    
                    const data = await response.json();
                    if (data.status === 'success') {
                        integrationToggle.checked = false;
                        integrationContent.style.display = 'none';
                        showToast('WhatsApp account disconnected successfully', 'success');
                    } else {
                        showToast('Failed to disconnect: ' + data.message, 'error');
                    }
                } catch (error) {
                    showToast('Error disconnecting WhatsApp', 'error');
                }
            }
        });

        // Initialize the page
        window.addEventListener('DOMContentLoaded', function() {
            integrationContent.style.display = integrationToggle.checked ? 'block' : 'none';
            if (integrationToggle.checked) {
                refreshQRCode();
            }
        });