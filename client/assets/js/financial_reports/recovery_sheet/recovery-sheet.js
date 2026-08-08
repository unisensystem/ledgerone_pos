document.addEventListener('DOMContentLoaded', function () {

    let customerViewMode = false;
    let lastRecoveryData = [];

    // Toggle: Customer View
    const customerViewToggle = document.getElementById('customerViewToggle');
    const customerViewLabel  = document.getElementById('customerViewLabel');
    if (customerViewToggle) {
        customerViewToggle.addEventListener('change', function () {
            customerViewMode = this.checked;
            if (customerViewLabel) customerViewLabel.style.opacity = customerViewMode ? '1' : '0.5';
            console.log('[Recovery] customerViewMode =', customerViewMode, '| data rows =', lastRecoveryData.length);
            if (lastRecoveryData.length > 0) {
                renderTable(lastRecoveryData);
            } else {
                loadRecoveryData();
            }
        });
        if (customerViewLabel) customerViewLabel.style.opacity = '0.5';
    }

    // Set default dates to current month
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay  = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    const fmt = d => d.toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    if (dateInputs[0]) dateInputs[0].value = fmt(firstDay);
    if (dateInputs[1]) dateInputs[1].value = fmt(lastDay);

    // Initialize Select2
    $('#recoveryOfficer, #countryFilter, #regionFilter, #cityFilter, #cityZoneFilter, #areaFilter, #companyFilter').select2({
        width: '100%',
        placeholder: 'Select an option'
    });

    // ── Event bindings ──────────────────────────────────────────────

    // Date inputs
    dateInputs.forEach(input => input.addEventListener('change', loadRecoveryData));

    // Recovery agent
    $('#recoveryOfficer').on('change', loadRecoveryData);

    // Company
    $('#companyFilter').on('change', loadRecoveryData);

    // Country → load regions, then reload data
    $('#countryFilter').on('change', function () {
        const countryId = this.value;
        $('#regionFilter').empty().append('<option value="all">All Regions</option>').prop('disabled', true);
        $('#cityFilter').empty().append('<option value="all">All Cities</option>').prop('disabled', true);
        $('#cityZoneFilter').empty().append('<option value="all">All Zones</option>').prop('disabled', true);
        $('#areaFilter').empty().append('<option value="all">All Areas</option>').prop('disabled', true);

        if (countryId && countryId !== 'all') {
            fetch(`../../../../server/api/financial_reports/recovery_sheet/get-regions.php?country_id=${countryId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(r => $('#regionFilter').append(`<option value="${r.id}">${r.region_name}</option>`));
                        $('#regionFilter').prop('disabled', false);
                    }
                });
        }
        loadRecoveryData();
    });

    // Region → load cities, then reload data
    $('#regionFilter').on('change', function () {
        const regionId = this.value;
        $('#cityFilter').empty().append('<option value="all">All Cities</option>').prop('disabled', true);
        $('#cityZoneFilter').empty().append('<option value="all">All Zones</option>').prop('disabled', true);
        $('#areaFilter').empty().append('<option value="all">All Areas</option>').prop('disabled', true);

        if (regionId && regionId !== 'all') {
            fetch(`../../../../server/api/financial_reports/recovery_sheet/get-cities.php?region_id=${regionId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(c => $('#cityFilter').append(`<option value="${c.id}">${c.city_name}</option>`));
                        $('#cityFilter').prop('disabled', false);
                    }
                });
        }
        loadRecoveryData();
    });

    // City → load zones, then reload data
    $('#cityFilter').on('change', function () {
        const cityId = this.value;
        $('#cityZoneFilter').empty().append('<option value="all">All Zones</option>').prop('disabled', true);
        $('#areaFilter').empty().append('<option value="all">All Areas</option>').prop('disabled', true);

        if (cityId && cityId !== 'all') {
            fetch(`../../../../server/api/financial_reports/recovery_sheet/get-city-zones.php?city_id=${cityId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(z => $('#cityZoneFilter').append(`<option value="${z.id}">${z.city_zone_name}</option>`));
                        $('#cityZoneFilter').prop('disabled', false);
                    }
                });
        }
        loadRecoveryData();
    });

    // Zone → load areas, then reload data
    $('#cityZoneFilter').on('change', function () {
        const zoneId = this.value;
        $('#areaFilter').empty().append('<option value="all">All Areas</option>').prop('disabled', true);

        if (zoneId && zoneId !== 'all') {
            fetch(`../../../../server/api/financial_reports/recovery_sheet/get-areas.php?city_zone_id=${zoneId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(a => $('#areaFilter').append(`<option value="${a.id}">${a.area_name}</option>`));
                        $('#areaFilter').prop('disabled', false);
                    }
                });
        }
        loadRecoveryData();
    });

    // Area
    $('#areaFilter').on('change', loadRecoveryData);

    // Filter button
    const filterBtn = document.querySelector('.fa-filter')?.closest('.btn');
    if (filterBtn) filterBtn.addEventListener('click', loadRecoveryData);

    // Refresh button
    const refreshBtn = document.querySelector('.fa-sync-alt')?.closest('.btn');
    if (refreshBtn) refreshBtn.addEventListener('click', loadRecoveryData);

    // Export CSV
    const exportBtn = document.querySelector('.fa-file-export')?.closest('.btn');
    if (exportBtn) exportBtn.addEventListener('click', exportToCSV);

    // Print
    const printBtn = document.querySelector('.fa-print')?.closest('.btn');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            const dateFrom   = dateInputs[0]?.value;
            const dateTo     = dateInputs[1]?.value;
            const officer    = document.querySelector('#recoveryOfficer')?.value;
            const countryId  = document.querySelector('#countryFilter')?.value;
            const regionId   = document.querySelector('#regionFilter')?.value;
            const cityId     = document.querySelector('#cityFilter')?.value;
            const zoneId     = document.querySelector('#cityZoneFilter')?.value;
            const areaId     = document.querySelector('#areaFilter')?.value;
            const companyId  = document.querySelector('#companyFilter')?.value;

            let url = `print.php?date_from=${dateFrom}&date_to=${dateTo}`;
            if (officer   && officer   !== 'all') url += `&recovery_officer_id=${officer}`;
            if (countryId && countryId !== 'all') url += `&country_id=${countryId}`;
            if (regionId  && regionId  !== 'all') url += `&region_id=${regionId}`;
            if (cityId    && cityId    !== 'all') url += `&city_id=${cityId}`;
            if (zoneId    && zoneId    !== 'all') url += `&city_zone_id=${zoneId}`;
            if (areaId    && areaId    !== 'all') url += `&area_id=${areaId}`;
            if (companyId) url += `&company_id=${companyId}`;
            if (customerViewMode) url += `&view=customer`;
            window.open(url, '_blank');
        });
    }

    // Reset
    const resetBtn = document.querySelector('#resetFiltersBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            dateInputs[0].value = fmt(firstDay);
            dateInputs[1].value = fmt(lastDay);
            $('#recoveryOfficer').val('all').trigger('change.select2');
            $('#companyFilter').val('').trigger('change.select2');
            $('#countryFilter').val('all').trigger('change'); // triggers cascade + reload
        });
    }

    // ── Core functions ──────────────────────────────────────────────

    async function loadCompanies() {
        try {
            const res    = await fetch('../../../../server/api/financial_reports/recovery_sheet/get-companies.php');
            const result = await res.json();
            if (result.success) {
                $('#companyFilter').empty().append('<option value="">All Companies</option>');
                result.data.forEach(c => $('#companyFilter').append(`<option value="${c.id}">${c.company_name}</option>`));
                if (result.data.length === 1) {
                    $('#companyFilter').val(result.data[0].id).trigger('change.select2');
                }
            }
        } catch (e) {
            console.error('Error loading companies:', e);
        }
    }

    function loadRecoveryData() {
        const dateFrom  = dateInputs[0]?.value;
        const dateTo    = dateInputs[1]?.value;
        const officer   = document.querySelector('#recoveryOfficer')?.value;
        const countryId = document.querySelector('#countryFilter')?.value;
        const regionId  = document.querySelector('#regionFilter')?.value;
        const cityId    = document.querySelector('#cityFilter')?.value;
        const zoneId    = document.querySelector('#cityZoneFilter')?.value;
        const areaId    = document.querySelector('#areaFilter')?.value;
        const companyId = document.querySelector('#companyFilter')?.value;

        const btn = document.querySelector('.fa-sync-alt')?.closest('.btn');
        if (btn) { btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'; btn.disabled = true; }

        let url = `../../../../server/api/financial_reports/recovery_sheet/recovery-sheet.php?date_from=${dateFrom}&date_to=${dateTo}`;
        if (officer   && officer   !== 'all') url += `&recovery_officer_id=${officer}`;
        if (countryId && countryId !== 'all') url += `&country_id=${countryId}`;
        if (regionId  && regionId  !== 'all') url += `&region_id=${regionId}`;
        if (cityId    && cityId    !== 'all') url += `&city_id=${cityId}`;
        if (zoneId    && zoneId    !== 'all') url += `&city_zone_id=${zoneId}`;
        if (areaId    && areaId    !== 'all') url += `&area_id=${areaId}`;
        if (companyId) url += `&company_id=${companyId}`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    lastRecoveryData = data.data;
                    renderTable(data.data);
                    updateSummary(data.summary);
                } else {
                    console.error('API error:', data.message);
                }
            })
            .catch(e => console.error('Fetch error:', e))
            .finally(() => {
                if (btn) { btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Data'; btn.disabled = false; }
            });
    }

    function setTableHeaders(mode) {
        const tr = document.querySelector('#recoveryTable thead tr');
        if (!tr) return;
        if (mode === 'customer') {
            tr.innerHTML = `
                <th>S#</th>
                <th>Customer Name</th>
                <th>Address</th>
                <th>Primary Phone No</th>
                <th>Total Balance</th>
                <th>Received Amount</th>
                <th>Sales Return</th>
                <th>Days Overdue</th>
                <th>Signature</th>
            `;
        } else {
            tr.innerHTML = `
                <th>S#</th>
                <th>Bill No</th>
                <th>Invoice Date</th>
                <th>Customer Name</th>
                <th>Address</th>
                <th>Primary Phone No</th>
                <th>Balance</th>
                <th>Received Amount</th>
                <th>Sales Return</th>
                <th>Days Overdue</th>
                <th>Signature</th>
            `;
        }
    }

    function groupByCustomer(rows) {
        const map = {};
        rows.forEach(row => {
            const key = row.customer_name;
            if (!map[key]) {
                map[key] = {
                    customer_name: row.customer_name,
                    address: row.address,
                    primary_phone: row.primary_phone,
                    total_balance: 0,
                    total_received: parseFloat(row.total_received) || 0,
                    total_returns: parseFloat(row.total_returns) || 0,
                    unallocated_received: parseFloat(row.unallocated_received) || 0,
                    bills: []
                };
            }
            map[key].total_balance += parseFloat(row.current_balance) || 0;
            map[key].bills.push({
                bill_no: row.bill_no,
                invoice_date: row.invoice_date,
                current_balance: parseFloat(row.current_balance) || 0,
                days_overdue: parseInt(row.days_overdue) || 0
            });
        });

        Object.values(map).forEach(c => {
            c.bills.sort((a, b) => new Date(a.invoice_date) - new Date(b.invoice_date));
        });

        return Object.values(map).sort((a, b) => a.customer_name.localeCompare(b.customer_name));
    }

    function simulateAutoClear(bills, totalReceived) {
        let remaining = totalReceived;
        return bills.map(bill => {
            const cleared = Math.min(remaining, bill.current_balance);
            remaining = Math.max(0, remaining - cleared);
            return {
                ...bill,
                cleared_amount: cleared,
                is_fully_cleared: cleared >= bill.current_balance - 0.001
            };
        });
    }

    function renderTable(rows) {
        try {
            if (customerViewMode) {
                renderTableCustomerView(rows);
            } else {
                renderTableBillView(rows);
            }
        } catch (e) {
            console.error('[Recovery] renderTable error:', e);
        }
    }

    function renderTableBillView(rows) {
        const tbody = document.querySelector('#recoveryTable tbody');
        if (!tbody) return;
        setTableHeaders('bill');
        tbody.innerHTML = '';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="11" style="text-align:center">No records found</td></tr>';
            return;
        }
        rows.forEach((row, i) => {
            const bal      = parseFloat(row.current_balance);
            const balClass = bal >= 0 ? 'balance-positive' : 'balance-negative';
            const balText  = (bal < 0 ? '-' : '') + currencySymbol + Math.abs(bal).toFixed(2);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${i + 1}</td>
                <td>${row.bill_no || ''}</td>
                <td>${row.invoice_date || ''}</td>
                <td>${row.customer_name || ''}</td>
                <td>${row.address || ''}</td>
                <td>${row.primary_phone || ''}</td>
                <td class="${balClass}">${balText}</td>
                <td></td>
                <td></td>
                <td>${row.days_overdue ?? ''}</td>
                <td><div class="signature-cell"></div></td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTableCustomerView(rows) {
        const tbody = document.querySelector('#recoveryTable tbody');
        if (!tbody) { console.error('[Recovery] #recoveryTable tbody not found'); return; }
        setTableHeaders('customer');
        tbody.innerHTML = '';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center">No records found</td></tr>';
            return;
        }

        const grouped = groupByCustomer(rows);

        grouped.forEach((customer, i) => {
            const bal      = customer.total_balance - customer.unallocated_received;
            const balClass = bal > 0 ? 'balance-positive' : bal < 0 ? 'balance-negative' : '';
            const balText    = (bal < 0 ? '-' : '') + currencySymbol + Math.abs(bal).toFixed(2);
            const oldestDays = customer.bills[0]?.days_overdue ?? '';

            const tr = document.createElement('tr');
            tr.className = 'customer-row';
            tr.innerHTML = `
                <td>${i + 1}</td>
                <td><i class="fas fa-chevron-right expand-icon"></i> ${customer.customer_name || ''}</td>
                <td>${customer.address || ''}</td>
                <td>${customer.primary_phone || ''}</td>
                <td class="${balClass}">${balText}</td>
                <td></td>
                <td></td>
                <td>${oldestDays}</td>
                <td><div class="signature-cell"></div></td>
            `;
            tbody.appendChild(tr);

            // Build bill-breakdown expand row
            const billsWithClear = simulateAutoClear(customer.bills, customer.total_received);
            const breakdownRows = billsWithClear.map((bill, j) => {
                const rowClass = bill.is_fully_cleared ? 'bill-cleared'
                    : bill.cleared_amount > 0 ? 'bill-partial'
                    : 'bill-pending';
                const icon = bill.is_fully_cleared
                    ? `<i class="fas fa-check-circle" style="color:var(--success)"></i>`
                    : bill.cleared_amount > 0
                        ? `<i class="fas fa-adjust" style="color:var(--warning)"></i>`
                        : `<i class="fas fa-clock" style="color:var(--subtext)"></i>`;
                const clearedText = bill.cleared_amount > 0
                    ? currencySymbol + bill.cleared_amount.toFixed(2)
                    : '—';
                return `
                    <tr class="${rowClass}">
                        <td>${j + 1}</td>
                        <td>${icon} ${bill.bill_no || 'N/A'}</td>
                        <td>${bill.invoice_date || ''}</td>
                        <td>${currencySymbol}${bill.current_balance.toFixed(2)}</td>
                        <td>${clearedText}</td>
                        <td>${bill.days_overdue}</td>
                    </tr>
                `;
            }).join('');

            const totalRecText = currencySymbol + customer.total_received.toFixed(2);

            const expandTr = document.createElement('tr');
            expandTr.className = 'expand-row';
            expandTr.style.display = 'none';
            expandTr.innerHTML = `
                <td colspan="9">
                    <div class="bills-breakdown">
                        <div class="bills-breakdown-title">
                            Auto-Clear Breakdown &mdash; Total Received: ${totalRecText} (oldest bill cleared first)
                        </div>
                        <table class="bills-breakdown-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Bill No</th>
                                    <th>Invoice Date</th>
                                    <th>Outstanding</th>
                                    <th>Auto-Cleared</th>
                                    <th>Days Overdue</th>
                                </tr>
                            </thead>
                            <tbody>${breakdownRows}</tbody>
                        </table>
                        <div class="bills-breakdown-legend">
                            <span><i class="fas fa-check-circle" style="color:var(--success)"></i> Fully Cleared</span>
                            <span><i class="fas fa-adjust" style="color:var(--warning)"></i> Partially Cleared</span>
                            <span><i class="fas fa-clock" style="color:var(--subtext)"></i> Pending</span>
                        </div>
                    </div>
                </td>
            `;
            tbody.appendChild(expandTr);

            tr.addEventListener('click', function () {
                const isExpanded = expandTr.style.display !== 'none';
                expandTr.style.display = isExpanded ? 'none' : 'table-row';
                const icon = tr.querySelector('.expand-icon');
                if (icon) icon.style.transform = isExpanded ? '' : 'rotate(90deg)';
            });
        });
    }

    function updateSummary(summary) {
        const vals = document.querySelectorAll('.summary-value');
        if (!vals[0]) return;
        const bal = parseFloat(summary.total_balance);
        vals[0].textContent = (bal < 0 ? '-' : '') + currencySymbol + Math.abs(bal).toFixed(2);
        vals[0].className   = 'summary-value ' + (bal >= 0 ? 'balance-positive' : 'balance-negative');
    }

    function exportToCSV() {
        let rows;
        if (customerViewMode) {
            rows = [['S#', 'Customer Name', 'Address', 'Primary Phone No', 'Total Balance', 'Received Amount', 'Sales Return', 'Days Overdue']];
            groupByCustomer(lastRecoveryData).forEach((c, i) => {
                const netBal = c.total_balance - c.unallocated_received;
                rows.push([
                    i + 1,
                    c.customer_name,
                    c.address,
                    c.primary_phone,
                    (netBal < 0 ? '-' : '') + Math.abs(netBal).toFixed(2),
                    c.total_received.toFixed(2),
                    c.total_returns.toFixed(2),
                    c.bills[0]?.days_overdue || ''
                ]);
            });
        } else {
            rows = [['S#', 'Bill No', 'Invoice Date', 'Customer Name', 'Address', 'Primary Phone No', 'Balance', 'Received Amount', 'Sales Return', 'Days Overdue', 'Signature']];
            document.querySelectorAll('#recoveryTable tbody tr').forEach(tr => {
                const cols = tr.querySelectorAll('td');
                if (!cols.length) return;
                const r = [];
                for (let i = 0; i < cols.length - 1; i++) r.push(cols[i].textContent.trim());
                r.push('');
                rows.push(r);
            });
        }
        const blob = new Blob([rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n')], { type: 'text/csv' });
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'recovery-sheet.csv' });
        a.click();
    }

    // ── Init ────────────────────────────────────────────────────────
    loadCompanies().then(() => loadRecoveryData());
});
