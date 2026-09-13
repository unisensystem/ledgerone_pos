document.addEventListener('DOMContentLoaded', function () {
    const companySelect = document.getElementById('company');
    const dsrSelect = document.getElementById('dsr-select');
    const citySelect = document.getElementById('city-select');
    const areaSelect = document.getElementById('area-select');
    const customerNameInput = document.getElementById('customer-name');
    const fromDate = document.getElementById('from-date');
    const toDate = document.getElementById('to-date');
    const sortSelect = document.getElementById('sort-select');
    const loadBtn = document.getElementById('load-btn');
    const resetBtn = document.getElementById('reset-btn');
    const printBtn = document.getElementById('print-btn');
    const printBtnResults = document.getElementById('print-btn-results');
    const resultsCard = document.getElementById('results-card');
    const reportHead = document.getElementById('report-head');
    const outstandingBody = document.getElementById('outstanding-body');

    const symbol = window.currencySymbol || '';
    let lastResult = null;

    // Default to today for both dates (matches "current standing" use case)
    const today = new Date().toISOString().split('T')[0];
    toDate.value = today;

    loadCompanies();
    loadDsrList();
    loadCityList();
    loadAreaList();

    citySelect.addEventListener('change', function () {
        // Re-filter the area dropdown to only areas under the selected city
        loadAreaList(citySelect.value);
    });

    function loadCompanies() {
        fetch('../../../../server/api/financial_reports/customer_ledger/get-companies.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    companySelect.innerHTML = '<option value="">All Companies</option>';
                    data.companies.forEach(c => {
                        companySelect.innerHTML += `<option value="${c.id}">${c.company_name}</option>`;
                    });
                }
            })
            .catch(err => console.error('Error loading companies:', err));
    }

    async function loadDsrList() {
        try {
            const res = await fetch('../../../../server/api/financial_reports/outstanding/outstanding.php?type=dsr_list');
            const result = await res.json();
            if (result.success) {
                dsrSelect.innerHTML = '<option value="">Select DSR</option>';
                result.data.forEach(dsr => {
                    dsrSelect.innerHTML += `<option value="${dsr.id}">${dsr.full_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading DSR list:', error);
        }
    }

    async function loadCityList() {
        try {
            const res = await fetch('../../../../server/api/financial_reports/outstanding/outstanding.php?type=city_list');
            const result = await res.json();
            if (result.success) {
                citySelect.innerHTML = '<option value="">All Cities</option>';
                result.data.forEach(city => {
                    citySelect.innerHTML += `<option value="${city.id}">${city.city_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading city list:', error);
        }
    }

    async function loadAreaList(cityId) {
        try {
            const params = new URLSearchParams({ type: 'area_list' });
            if (cityId) params.append('city_id', cityId);
            const res = await fetch(`../../../../server/api/financial_reports/outstanding/outstanding.php?${params}`);
            const result = await res.json();
            if (result.success) {
                areaSelect.innerHTML = '<option value="">All Areas</option>';
                result.data.forEach(area => {
                    areaSelect.innerHTML += `<option value="${area.id}">${area.area_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading area list:', error);
        }
    }

    function buildFilterParams() {
        const params = new URLSearchParams({
            sales_officer_id: dsrSelect.value,
            to_date: toDate.value || today,
            sort: sortSelect.value || 'high_to_low'
        });
        if (fromDate.value) params.append('from_date', fromDate.value);
        if (companySelect.value) params.append('company_id', companySelect.value);
        if (citySelect.value) params.append('city_id', citySelect.value);
        if (areaSelect.value) params.append('area_id', areaSelect.value);
        if (customerNameInput.value.trim()) params.append('customer_name', customerNameInput.value.trim());
        return params;
    }

    async function loadOutstanding() {
        if (!dsrSelect.value) {
            alert('Please select a DSR first');
            return;
        }

        const params = buildFilterParams();

        try {
            const res = await fetch(`../../../../server/api/financial_reports/outstanding/outstanding.php?${params}`);
            const result = await res.json();

            if (!result.success) {
                alert(result.message || 'Error loading outstanding report');
                return;
            }

            lastResult = result;
            renderReport(result);
        } catch (error) {
            alert('Network error occurred');
        }
    }

    function fmt(amount) {
        const abs = Math.abs(amount).toFixed(2);
        return `${symbol}${abs}`;
    }

    function renderReport(result) {
        resultsCard.style.display = 'block';

        const dateLabel = result.from_date
            ? `FROM DATE ${result.from_date} TO ${result.to_date}`
            : `AS OF ${result.to_date}`;

        const filterBits = [];
        if (citySelect.value) filterBits.push(`City: ${citySelect.options[citySelect.selectedIndex].text}`);
        if (areaSelect.value) filterBits.push(`Area: ${areaSelect.options[areaSelect.selectedIndex].text}`);
        if (customerNameInput.value.trim()) filterBits.push(`Search: "${customerNameInput.value.trim()}"`);
        const filterLine = filterBits.length ? `<div>${filterBits.join(' &nbsp;|&nbsp; ')}</div>` : '';

        reportHead.innerHTML = `
            <div><strong>${dateLabel}</strong></div>
            <div>DSR:- ${result.dsr_name}</div>
            ${filterLine}
            <div style="margin-top: 12px; display: flex; gap: 8px;">
                <button class="btn btn-ghost" id="print-btn-thermal" style="font-size: 13px; padding: 6px 12px;">
                    <i class="fas fa-print"></i> Thermal Print
                </button>
                <button class="btn btn-ghost" id="print-btn-normal" style="font-size: 13px; padding: 6px 12px;">
                    <i class="fas fa-file-pdf"></i> Normal Print (PDF)
                </button>
            </div>
        `;

        outstandingBody.innerHTML = '';

        result.groups.forEach(group => {
            outstandingBody.innerHTML += `
                <tr class="area-header-row">
                    <td colspan="4"><strong>${group.area_name}</strong></td>
                </tr>
            `;

            group.customers.forEach(c => {
                outstandingBody.innerHTML += `
                    <tr>
                        <td>${c.s_no}</td>
                        <td>${c.shop_name}</td>
                        <td>${fmt(c.balance)}</td>
                        <td></td>
                    </tr>
                `;
            });

            outstandingBody.innerHTML += `
                <tr class="area-total-row">
                    <td colspan="2" style="text-align:right;"><strong>Total:</strong></td>
                    <td><strong>${fmt(group.subtotal)}</strong></td>
                    <td></td>
                </tr>
            `;
        });

        outstandingBody.innerHTML += `
            <tr class="grand-total-row">
                <td colspan="2" style="text-align:right;"><strong>Grand Total:</strong></td>
                <td><strong>${fmt(result.grand_total)}</strong></td>
                <td></td>
            </tr>
        `;

        // Attach print button event listeners after rendering
        document.getElementById('print-btn-thermal').addEventListener('click', function () {
            const params = buildFilterParams();
            window.open(`print-outstanding.php?${params}`, '_blank');
        });

        document.getElementById('print-btn-normal').addEventListener('click', function () {
            const params = buildFilterParams();
            window.open(`print-outstanding-normal.php?${params}`, '_blank');
        });
    }

    loadBtn.addEventListener('click', loadOutstanding);

    resetBtn.addEventListener('click', function () {
        companySelect.value = '';
        dsrSelect.value = '';
        citySelect.value = '';
        customerNameInput.value = '';
        fromDate.value = '';
        toDate.value = today;
        sortSelect.value = 'high_to_low';
        loadAreaList(); // reload full, unfiltered area list
        resultsCard.style.display = 'none';
        lastResult = null;
    });

    printBtn.addEventListener('click', function () {
        if (!lastResult) {
            alert('Please load the report first');
            return;
        }
        const params = buildFilterParams();
        // Show menu to choose print type
        const choice = confirm('Thermal Print?\n\nOK = Thermal Print\nCancel = Normal Print (PDF)');
        if (choice) {
            window.open(`print-outstanding.php?${params}`, '_blank');
        } else {
            window.open(`print-outstanding-normal.php?${params}`, '_blank');
        }
    });
});