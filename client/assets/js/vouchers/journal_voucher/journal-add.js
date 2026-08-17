        // Account data from database
        let accountOptions = [];
        let companies = [];

        // Initialize with current date
        document.addEventListener('DOMContentLoaded', async function() {
            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').value = today;
            
            // Fetch accounts and companies from database
            await fetchAccounts();
            await fetchCompanies();
            
            // Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');
            const reverseId = urlParams.get('reverse');
            
            if (editId) {
                await loadEditEntry(editId);
            } else if (reverseId) {
                await generateVoucherNumber();
                await loadReverseEntry(reverseId);
            } else {
                await generateVoucherNumber();
                // Add first entry row
                addEntryRow();
            }
            
            // Update totals
            updateTotals();
        });

        // Fetch companies from database
        async function fetchCompanies() {
            try {
                const response = await fetch('../../../../server/api/vouchers/journal_voucher/get-companies.php');
                const result = await response.json();
                
                if (response.ok && result.success) {
                    companies = result.data;
                    const companySelect = document.getElementById('company');
                    result.data.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.company_name;
                        companySelect.appendChild(option);
                    });
                    
                    // Auto-select if only one company
                    if (result.data.length === 1) {
                        companySelect.value = result.data[0].id;
                    }
                } else {
                    showValidationMessage('Failed to load companies', 'error');
                }
            } catch (error) {
                showValidationMessage('Error loading companies: ' + error.message, 'error');
            }
        }

        // Fetch accounts from database
        async function fetchAccounts() {
            try {
                const response = await fetch('../../../../server/api/vouchers/journal_voucher/get-accounts.php');
                const result = await response.json();
                
                if (response.ok && result.success) {
                    accountOptions = result.accounts;
                } else {
                    showValidationMessage('Failed to load accounts', 'error');
                }
            } catch (error) {
                showValidationMessage('Error loading accounts: ' + error.message, 'error');
            }
        }

        // Generate voucher number
        async function generateVoucherNumber() {
            try {
                const response = await fetch('../../../../server/api/vouchers/journal_voucher/get-next-voucher-number.php');
                const result = await response.json();
                
                if (response.ok && result.success) {
                    document.getElementById('voucher').value = result.voucherNumber;
                } else {
                    showValidationMessage('Failed to generate voucher number', 'error');
                }
            } catch (error) {
                showValidationMessage('Error generating voucher number: ' + error.message, 'error');
            }
        }

        // Load entry for editing
        async function loadEditEntry(id) {
            try {
                const response = await fetch(`../../../../server/api/vouchers/journal_voucher/get-voucher.php?id=${id}`);
                const result = await response.json();
                
                if (response.ok && result.success) {
                    const voucher = result.voucher;
                    document.getElementById('date').value = voucher.voucher_date;
                    document.getElementById('voucher').value = voucher.voucher_number;
                    document.getElementById('description').value = voucher.description;
                    
                    // Add entries
                    voucher.entries.forEach(entry => {
                        addEntryRow();
                        const currentRow = serialNumber - 1;
                        
                        // Set account
                        const accountInput = document.querySelector(`.account-input[data-row="${currentRow}"]`);
                        const accountIdInput = document.getElementById(`accountId-${currentRow}`);
                        accountInput.value = entry.account_name;
                        accountIdInput.value = entry.account_id;
                        
                        // Set debit and credit
                        const debitInput = document.querySelector(`.debit-input[data-row="${currentRow}"]`);
                        const creditInput = document.querySelector(`.credit-input[data-row="${currentRow}"]`);
                        debitInput.value = entry.debit > 0 ? entry.debit : '';
                        creditInput.value = entry.credit > 0 ? entry.credit : '';
                    });
                    
                    updateTotals();
                } else {
                    showValidationMessage('Failed to load voucher for editing', 'error');
                }
            } catch (error) {
                showValidationMessage('Error loading voucher: ' + error.message, 'error');
            }
        }

        // Load entry for reversing
        async function loadReverseEntry(id) {
            try {
                const response = await fetch(`../../../../server/api/vouchers/journal_voucher/get-voucher.php?id=${id}`);
                const result = await response.json();
                
                if (response.ok && result.success) {
                    const voucher = result.voucher;
                    document.getElementById('description').value = `Reversing entry for ${voucher.voucher_number} - ${voucher.description}`;
                    
                    // Add reversed entries (swap debit and credit)
                    voucher.entries.forEach(entry => {
                        addEntryRow();
                        const currentRow = serialNumber - 1;
                        
                        // Set account
                        const accountInput = document.querySelector(`.account-input[data-row="${currentRow}"]`);
                        const accountIdInput = document.getElementById(`accountId-${currentRow}`);
                        accountInput.value = entry.account_name;
                        accountIdInput.value = entry.account_id;
                        
                        // Swap debit and credit
                        const debitInput = document.querySelector(`.debit-input[data-row="${currentRow}"]`);
                        const creditInput = document.querySelector(`.credit-input[data-row="${currentRow}"]`);
                        debitInput.value = entry.credit > 0 ? entry.credit : '';
                        creditInput.value = entry.debit > 0 ? entry.debit : '';
                    });
                    
                    updateTotals();
                } else {
                    showValidationMessage('Failed to load voucher for reversing', 'error');
                }
            } catch (error) {
                showValidationMessage('Error loading voucher: ' + error.message, 'error');
            }
        }

        // Counter for serial numbers
        let serialNumber = 1;

        // Add a new entry row
        function addEntryRow() {
            const tableBody = document.getElementById('journalTableBody');
            const row = document.createElement('tr');
            row.id = `entryRow-${serialNumber}`;
            
            row.innerHTML = `
                <td>${serialNumber}</td>
                <td>
                    <div class="searchable-dropdown">
                        <div class="dropdown-input-container">
                            <input type="text" class="account-input" data-row="${serialNumber}" placeholder="Search account...">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <input type="hidden" class="account-id" id="accountId-${serialNumber}">
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="debit-input" data-row="${serialNumber}" placeholder="0.00">
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="credit-input" data-row="${serialNumber}" placeholder="0.00">
                </td>
                <td>
                    <input type="text" class="line-description" data-row="${serialNumber}" placeholder="Line description...">
                </td>
                <td>
                    <button class="table-action-btn remove" onclick="removeEntryRow(${serialNumber})" title="Remove Entry">
                        <i class="fas fa-minus"></i>
                    </button>
                </td>
            `;
            
            tableBody.appendChild(row);

            // Create dropdown and append to body to avoid table overflow clipping
            const dropdownEl = document.createElement('div');
            dropdownEl.className = 'dropdown-options';
            dropdownEl.id = `dropdown-${serialNumber}`;
            dropdownEl.innerHTML = accountOptions.map(account =>
                `<div class="dropdown-option" data-value="${account.id}" data-name="${account.name}">${account.name}</div>`
            ).join('');
            document.body.appendChild(dropdownEl);

            attachDropdownListeners(serialNumber);
            serialNumber++;
        }

        // Remove an entry row
        function removeEntryRow(rowNum) {
            const row = document.getElementById(`entryRow-${rowNum}`);
            const dropdown = document.getElementById(`dropdown-${rowNum}`);
            if (row) {
                row.remove();
                if (dropdown) dropdown.remove();
                
                // Recalculate serial numbers
                const rows = document.querySelectorAll('#journalTableBody tr');
                rows.forEach((row, index) => {
                    row.cells[0].textContent = index + 1;
                });
                
                serialNumber = rows.length + 1;
                updateTotals();
            }
        }

        // Position dropdown using fixed coordinates
        function positionDropdown(input, dropdown) {
            const rect = input.getBoundingClientRect();
            dropdown.style.top = rect.bottom + 'px';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
        }

        // Attach event listeners to dropdown
        function attachDropdownListeners(rowNum) {
            const input = document.querySelector(`.account-input[data-row="${rowNum}"]`);
            const dropdown = document.getElementById(`dropdown-${rowNum}`);
            const accountIdInput = document.getElementById(`accountId-${rowNum}`);
            
            input.addEventListener('focus', function() {
                positionDropdown(input, dropdown);
                dropdown.classList.add('show');
            });
            
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const options = dropdown.querySelectorAll('.dropdown-option');
                
                options.forEach(option => {
                    const text = option.textContent.toLowerCase();
                    option.style.display = text.includes(searchTerm) ? 'block' : 'none';
                });
                
                positionDropdown(input, dropdown);
                dropdown.classList.add('show');
            });
            
            // Handle option selection
            dropdown.querySelectorAll('.dropdown-option').forEach(option => {
                option.addEventListener('click', function() {
                    input.value = this.getAttribute('data-name');
                    accountIdInput.value = this.getAttribute('data-value');
                    dropdown.classList.remove('show');
                });
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!input.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });

            // Reposition on scroll
            window.addEventListener('scroll', function() {
                if (dropdown.classList.contains('show')) {
                    positionDropdown(input, dropdown);
                }
            }, true);
        }

        // Update totals and validation
        function updateTotals() {
            let totalDebit = 0;
            let totalCredit = 0;
            let isBalanced = false;
            
            // Calculate totals
            const debitInputs = document.querySelectorAll('.debit-input');
            const creditInputs = document.querySelectorAll('.credit-input');
            
            debitInputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                totalDebit += value;
            });
            
            creditInputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                totalCredit += value;
            });
            
            // Check if balanced (allow for small floating point differences)
            isBalanced = Math.abs(totalDebit - totalCredit) < 0.01;
            
            // Update UI
            document.getElementById('totalDebit').textContent = totalDebit.toFixed(2);
            document.getElementById('totalCredit').textContent = totalCredit.toFixed(2);
            
            const balanceStatus = document.getElementById('balanceStatus');
            const formStatus = document.getElementById('formStatus');
            
            if (isBalanced) {
                balanceStatus.textContent = "Balanced";
                balanceStatus.style.color = "#2FBF71"; // Success
                formStatus.textContent = "Journal entry is balanced and ready to post.";
                formStatus.style.color = "#2FBF71";
            } else {
                balanceStatus.textContent = `Unbalanced (Diff: ${Math.abs(totalDebit - totalCredit).toFixed(2)})`;
                balanceStatus.style.color = "#E34F4F"; // Error
                formStatus.textContent = "Journal entry is not balanced. Debits must equal credits.";
                formStatus.style.color = "#E34F4F";
            }
            
            return isBalanced;
        }

        // Validate form for accounting rules
        function validateForm() {
            const date = document.getElementById('date').value;
            
            // Check required fields
            if (!date) {
                showValidationMessage("Please select a date.", "error");
                return false;
            }
            
            const company = document.getElementById('company').value;
            if (!company) {
                showValidationMessage("Please select a company.", "error");
                return false;
            }
            
            // Check if we have at least 2 entries (double-entry accounting)
            const rows = document.querySelectorAll('#journalTableBody tr');
            if (rows.length < 2) {
                showValidationMessage("Journal entry must have at least 2 lines (double-entry accounting).", "error");
                return false;
            }
            
            // Check if all rows have accounts
            const accountInputs = document.querySelectorAll('.account-id');
            for (let i = 0; i < accountInputs.length; i++) {
                if (!accountInputs[i].value) {
                    showValidationMessage(`Row ${i+1} must have an account selected.`, "error");
                    return false;
                }
            }
            
            // Check if debit and credit values are valid
            const debitInputs = document.querySelectorAll('.debit-input');
            const creditInputs = document.querySelectorAll('.credit-input');
            
            let hasDebitOrCredit = false;
            for (let i = 0; i < debitInputs.length; i++) {
                const debit = parseFloat(debitInputs[i].value) || 0;
                const credit = parseFloat(creditInputs[i].value) || 0;
                
                // Check if at least one row has a value
                if (debit > 0 || credit > 0) {
                    hasDebitOrCredit = true;
                }
                
                // Check if a row has both debit and credit (not allowed)
                if (debit > 0 && credit > 0) {
                    showValidationMessage(`Row ${i+1} cannot have both debit and credit amounts.`, "error");
                    return false;
                }
                
                // Check if values are positive
                if (debit < 0 || credit < 0) {
                    showValidationMessage(`Row ${i+1} amounts must be positive values.`, "error");
                    return false;
                }
            }
            
            if (!hasDebitOrCredit) {
                showValidationMessage("At least one line must have a debit or credit amount.", "error");
                return false;
            }
            
            // Check if balanced
            if (!updateTotals()) {
                showValidationMessage("Debits must equal credits before posting.", "error");
                return false;
            }
            
            return true;
        }

        // Show validation message
        function showValidationMessage(message, type) {
            const validationDiv = document.getElementById('validationMessage');
            validationDiv.textContent = message;
            validationDiv.className = `validation-message ${type} show`;
            
            // Scroll to top to show message
            window.scrollTo(0, 0);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                validationDiv.classList.remove('show');
            }, 5000);
        }

        // Save voucher (draft or posted)
        async function saveVoucher(status) {
            if (!validateForm()) {
                return;
            }
            
            const btn = status === 'draft' ? document.getElementById('saveDraftBtn') : document.getElementById('postVoucherBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${status === 'draft' ? 'Saving...' : 'Posting...'}`;
            
            // Collect all entry data
            const entries = [];
            const rows = document.querySelectorAll('#journalTableBody tr');
            
            rows.forEach((row, index) => {
                const accountId = row.querySelector('.account-id').value;
                const debit = parseFloat(row.querySelector('.debit-input').value) || 0;
                const credit = parseFloat(row.querySelector('.credit-input').value) || 0;
                const lineDescription = row.querySelector('.line-description').value;
                
                entries.push({
                    accountId: parseInt(accountId),
                    debit,
                    credit,
                    lineDescription
                });
            });
            
            // Prepare voucher data
            const voucherData = {
                date: document.getElementById('date').value,
                company_id: document.getElementById('company').value,
                voucherNumber: document.getElementById('voucher').value,
                description: document.getElementById('description').value,
                entries: entries,
                totalDebit: parseFloat(document.getElementById('totalDebit').textContent),
                totalCredit: parseFloat(document.getElementById('totalCredit').textContent),
                status: status
            };
            
            try {
                const response = await fetch('../../../../server/api/vouchers/journal_voucher/journal-add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(voucherData)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    const message = status === 'draft' 
                        ? "Voucher saved as draft successfully!" 
                        : "Voucher posted successfully! Journal entry has been recorded.";
                    showValidationMessage(message, "success");
                    
                    // Generate new voucher number for next entry
                    await generateVoucherNumber();
                    
                    // Reset form but keep voucher number and date
                    document.getElementById('description').value = '';
                    
                    // Clear all entries and add one fresh row
                    const tableBody = document.getElementById('journalTableBody');
                    document.querySelectorAll('body > .dropdown-options').forEach(d => d.remove());
                    tableBody.innerHTML = '';
                    serialNumber = 1;
                    addEntryRow();
                    
                    updateTotals();
                } else {
                    showValidationMessage(result.error || 'Failed to save voucher', "error");
                }
            } catch (error) {
                showValidationMessage('Network error: ' + error.message, "error");
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        // Post voucher
        function postVoucher() {
            if (!validateForm()) {
                return;
            }
            document.getElementById('postConfirmModal').classList.add('show');
        }

        // Confirm post voucher
        async function confirmPostVoucher() {
            document.getElementById('postConfirmModal').classList.remove('show');
            await saveVoucher('posted');
        }

        // Save as draft
        async function saveDraft() {
            await saveVoucher('draft');
        }

        // Reset form
        function resetForm() {
            if (confirm("Are you sure you want to reset the form? All entered data will be lost.")) {
                document.getElementById('date').value = new Date().toISOString().split('T')[0];
                document.getElementById('description').value = '';
                
                // Clear all entries and add one fresh row
                const tableBody = document.getElementById('journalTableBody');
                document.querySelectorAll('body > .dropdown-options').forEach(d => d.remove());
                tableBody.innerHTML = '';
                serialNumber = 1;
                addEntryRow();
                
                updateTotals();
                
                showValidationMessage("Form has been reset.", "success");
            }
        }

        // Event listeners for dynamic inputs
        document.addEventListener('input', function(event) {
            // Update totals when debit/credit inputs change
            if (event.target.classList.contains('debit-input') || 
                event.target.classList.contains('credit-input')) {
                updateTotals();
                
                // Ensure only one field has value (debit OR credit)
                const rowNum = event.target.getAttribute('data-row');
                const debitInput = document.querySelector(`.debit-input[data-row="${rowNum}"]`);
                const creditInput = document.querySelector(`.credit-input[data-row="${rowNum}"]`);
                
                if (event.target.classList.contains('debit-input') && event.target.value && parseFloat(event.target.value) > 0) {
                    creditInput.value = '';
                }
                
                if (event.target.classList.contains('credit-input') && event.target.value && parseFloat(event.target.value) > 0) {
                    debitInput.value = '';
                }
            }
        });

        // Button event listeners
        document.getElementById('addEntryBtn').addEventListener('click', addEntryRow);
        document.getElementById('postVoucherBtn').addEventListener('click', postVoucher);
        document.getElementById('saveDraftBtn').addEventListener('click', saveDraft);
        document.getElementById('resetBtn').addEventListener('click', resetForm);
        document.getElementById('viewListBtn').addEventListener('click', function() {
            window.location.href = 'journal-list.php';
        });

        // Modal event listeners
        document.getElementById('closePostModal').addEventListener('click', function() {
            document.getElementById('postConfirmModal').classList.remove('show');
        });
        document.getElementById('cancelPostBtn').addEventListener('click', function() {
            document.getElementById('postConfirmModal').classList.remove('show');
        });
        document.getElementById('confirmPostBtn').addEventListener('click', confirmPostVoucher);

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('postConfirmModal');
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        });