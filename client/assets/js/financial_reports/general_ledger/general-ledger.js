document.addEventListener('DOMContentLoaded', function () {
    const currentDate = new Date().toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    document.getElementById('currentDate').textContent = currentDate;

    let currentPage = 1;
    const itemsPerPage = 20;
    
    function loadCompanies() {
        fetch('../../../../server/api/financial_reports/general_ledger/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const companySelect = document.getElementById('company');
                    companySelect.innerHTML = '<option value="">All Companies</option>';
                    data.companies.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.company_name;
                        companySelect.appendChild(option);
                    });
                    
                    loadReport();
                }
            })
            .catch(error => console.error('Error loading companies:', error));
    }

    const toggleHelpBtn = document.getElementById('toggleHelp');
    const helpPanel = document.getElementById('helpPanel');

    toggleHelpBtn.addEventListener('click', function () {
        helpPanel.classList.toggle('active');
        toggleHelpBtn.innerHTML = helpPanel.classList.contains('active')
            ? '<i class="fas fa-times"></i> Hide Help'
            : '<i class="fas fa-question-circle"></i> Toggle Help';
    });

    const helpTriggers = document.querySelectorAll('.help-trigger');
    helpTriggers.forEach(trigger => {
        trigger.addEventListener('click', function () {
            if (!helpPanel.classList.contains('active')) {
                helpPanel.classList.add('active');
                toggleHelpBtn.innerHTML = '<i class="fas fa-times"></i> Hide Help';
            }

            const helpType = this.getAttribute('data-help');
            let helpMessage = '';

            switch (helpType) {
                case 'date-range':
                    helpMessage = "Select the period for your report. Financial reports are typically generated monthly, quarterly, or annually.";
                    break;
                case 'account-type':
                    helpMessage = "Assets = What you own | Liabilities = What you owe | Equity = Owner's stake | Revenue = Income | Expenses = Costs";
                    break;
                case 'transaction-type':
                    helpMessage = "Debit/Credit = Normal transactions | Adjusting = End-of-period corrections | Closing = Transfer to retained earnings";
                    break;
            }

            alert(`Help: ${helpMessage}`);
        });
    });

    const validateReportBtn = document.getElementById('validateReport');
    const validationSummary = document.getElementById('validationSummary');
    const validationList = document.getElementById('validationList');

    validateReportBtn.addEventListener('click', function () {
        const validationIssues = [];
        const totalDebits = parseFloat(document.querySelector('.col-3:nth-child(1) .card div:nth-child(2)').textContent.replace(/[^0-9.]/g, ''));
        const totalCredits = parseFloat(document.querySelector('.col-3:nth-child(2) .card div:nth-child(2)').textContent.replace(/[^0-9.]/g, ''));

        if (Math.abs(totalDebits - totalCredits) > 0.01) {
            validationIssues.push("Debits do not equal credits. Accounting equation is unbalanced.");
        }

        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        if (!startDate || !endDate) {
            validationIssues.push("Date range is not properly set. Please select both start and end dates.");
        }

        if (validationIssues.length === 0) {
            validationList.innerHTML = '<li style="color: var(--success);"><i class="fas fa-check-circle"></i> All accounting validations passed. Report is balanced and compliant.</li>';
            validationSummary.style.backgroundColor = 'rgba(47, 191, 113, 0.05)';
            validationSummary.style.borderColor = 'rgba(47, 191, 113, 0.2)';
            validationSummary.querySelector('.validation-title').innerHTML = '<i class="fas fa-check-circle"></i><span>Accounting Validation Passed</span>';
            validationSummary.querySelector('.validation-title').style.color = 'var(--success)';
        } else {
            validationList.innerHTML = '';
            validationIssues.forEach(issue => {
                const li = document.createElement('li');
                li.style.marginBottom = '8px';
                li.style.paddingLeft = '20px';
                li.style.position = 'relative';
                li.innerHTML = `<i class="fas fa-exclamation-triangle" style="position: absolute; left: 0; color: var(--error);"></i> ${issue}`;
                validationList.appendChild(li);
            });

            validationSummary.style.backgroundColor = 'rgba(227, 79, 79, 0.05)';
            validationSummary.style.borderColor = 'rgba(227, 79, 79, 0.2)';
            validationSummary.querySelector('.validation-title').innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Accounting Validation Issues Found</span>';
            validationSummary.querySelector('.validation-title').style.color = 'var(--error)';
        }

        validationSummary.classList.add('active');
        validationSummary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    const clearFiltersBtn = document.getElementById('clearFilters');
    clearFiltersBtn.addEventListener('click', function () {
        document.getElementById('company').selectedIndex = 0;
        document.getElementById('accountType').value = '';
        document.getElementById('accountNumber').value = '';
        document.getElementById('transactionType').value = '';
        document.getElementById('minAmount').value = '';
        document.getElementById('maxAmount').value = '';
        document.getElementById('searchText').value = '';
        const today = new Date();
        document.getElementById('startDate').value = today.getFullYear() + '-01-01';
        document.getElementById('endDate').value = today.toISOString().split('T')[0];
        currentPage = 1;
    });

    const searchInput = document.getElementById('searchText');
    let searchTimeout;
    
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadReport();
        }, 500);
    });

    function formatCurrency(amount) {
        return CURRENCY_SYMBOL + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
    }

    function loadReport() {
        const companyId = document.getElementById('company').value;
        
        const params = new URLSearchParams({
            company_id: companyId,
            startDate: document.getElementById('startDate').value,
            endDate: document.getElementById('endDate').value,
            accountNumber: document.getElementById('accountNumber').value,
            transactionType: document.getElementById('transactionType').value,
            minAmount: document.getElementById('minAmount').value,
            maxAmount: document.getElementById('maxAmount').value,
            searchText: document.getElementById('searchText').value
        });

        fetch(`../../../../server/api/financial_reports/general_ledger/general-ledger.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateSummary(data.summary);
                    updateTable(data.entries, data.summary);
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function updateSummary(summary) {
        document.querySelector('.col-3:nth-child(1) .card div:nth-child(2)').textContent = formatCurrency(summary.totalDebits);
        document.querySelector('.col-3:nth-child(2) .card div:nth-child(2)').textContent = formatCurrency(summary.totalCredits);
        document.querySelector('.col-3:nth-child(3) .card div:nth-child(2)').textContent = summary.transactionCount;
        document.querySelector('.col-3:nth-child(4) .card div:nth-child(2)').textContent = summary.activeAccounts;

        const badge1 = document.querySelector('.col-3:nth-child(1) .badge');
        const badge2 = document.querySelector('.col-3:nth-child(2) .badge');
        
        if (summary.isBalanced) {
            badge1.textContent = 'Balanced';
            badge1.className = 'badge badge-balanced';
            badge2.textContent = 'Balanced';
            badge2.className = 'badge badge-balanced';
        } else {
            badge1.textContent = 'Unbalanced';
            badge1.className = 'badge badge-warning';
            badge2.textContent = 'Unbalanced';
            badge2.className = 'badge badge-warning';
        }
    }

    function updateTable(entries, summary) {
        const tbody = document.querySelector('tbody');
        tbody.innerHTML = '';
        
        const totalEntries = entries.length;
        const totalPages = Math.ceil(totalEntries / itemsPerPage);
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage, totalEntries);
        const paginatedEntries = entries.slice(startIndex, endIndex);

        paginatedEntries.forEach(entry => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${entry.date}</td>
                <td>${entry.account_number || 'N/A'}</td>
                <td>${entry.account_name}</td>
                <td>${entry.description || ''}</td>
                <td>${entry.reference}</td>
                <td class="text-right">${entry.debit > 0 ? formatCurrency(entry.debit) : ''}</td>
                <td class="text-right">${entry.credit > 0 ? formatCurrency(entry.credit) : ''}</td>
                <td class="text-right">${entry.balance >= 0 ? 'Dr ' : 'Cr '}${formatCurrency(Math.abs(entry.balance))}</td>
                <td><span class="badge badge-balanced">Valid</span></td>
            `;
            
            row.addEventListener('dblclick', function () {
                const amount = entry.debit > 0 ? entry.debit : entry.credit;
                const type = entry.debit > 0 ? 'Debit' : 'Credit';
                alert(`Transaction Details:\n\nAccount: ${entry.account_number} - ${entry.account_name}\nAmount: ${formatCurrency(amount)} (${type})\nDate: ${entry.date}\nDescription: ${entry.description}\nReference: ${entry.reference}`);
            });
            
            tbody.appendChild(row);
        });

        const tfoot = document.querySelector('tfoot tr');
        tfoot.innerHTML = `
            <td colspan="5" class="text-right">Period Totals:</td>
            <td class="text-right">${formatCurrency(summary.totalDebits)}</td>
            <td class="text-right">${formatCurrency(summary.totalCredits)}</td>
            <td colspan="2" class="text-center">
                <span class="badge ${summary.isBalanced ? 'badge-balanced' : 'badge-warning'}">
                    ${summary.isBalanced ? 'DEBITS = CREDITS ✓' : 'UNBALANCED ✗'}
                </span>
            </td>
        `;

        document.querySelector('.card .d-flex.justify-between.align-center div:last-child').textContent = 
            `Showing ${startIndex + 1}-${endIndex} of ${totalEntries} entries`;
        
        updatePaginationButtons(totalPages);
    }
    
    function updatePaginationButtons(totalPages) {
        const prevBtn = document.querySelector('.btn-ghost:has(.fa-chevron-left)');
        const nextBtn = document.querySelector('.btn-ghost:has(.fa-chevron-right)');
        
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
        
        prevBtn.style.opacity = prevBtn.disabled ? '0.5' : '1';
        nextBtn.style.opacity = nextBtn.disabled ? '0.5' : '1';
        prevBtn.style.cursor = prevBtn.disabled ? 'not-allowed' : 'pointer';
        nextBtn.style.cursor = nextBtn.disabled ? 'not-allowed' : 'pointer';
    }

    document.querySelector('.btn-primary').addEventListener('click', () => {
        currentPage = 1;
        loadReport();
    });
    
    document.querySelector('.btn-ghost:has(.fa-chevron-left)').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadReport();
        }
    });
    
    document.querySelector('.btn-ghost:has(.fa-chevron-right)').addEventListener('click', function() {
        currentPage++;
        loadReport();
    });
    
    document.getElementById('printBtn').addEventListener('click', function() {
        const companyId = document.getElementById('company').value;
        
        const params = new URLSearchParams({
            company_id: companyId,
            startDate: document.getElementById('startDate').value,
            endDate: document.getElementById('endDate').value,
            accountNumber: document.getElementById('accountNumber').value,
            transactionType: document.getElementById('transactionType').value,
            minAmount: document.getElementById('minAmount').value,
            maxAmount: document.getElementById('maxAmount').value,
            searchText: document.getElementById('searchText').value
        });
        window.open(`print.php?${params}`, '_blank');
    });
    
    loadCompanies();
});
