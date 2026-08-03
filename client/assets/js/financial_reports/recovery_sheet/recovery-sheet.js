document.addEventListener('DOMContentLoaded', function () {

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

    function renderTable(rows) {
        const tbody = document.querySelector('tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!rows.length) {
tbody.innerHTML = '<tr><td colspan="11" style="text-align:center">No records found</td></tr>';
            return;
        }
        rows.forEach((row, i) => {
            const bal     = parseFloat(row.current_balance);
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

    function updateSummary(summary) {
        const vals = document.querySelectorAll('.summary-value');
        if (!vals[0]) return;
        const bal = parseFloat(summary.total_balance);
        vals[0].textContent = (bal < 0 ? '-' : '') + currencySymbol + Math.abs(bal).toFixed(2);
        vals[0].className   = 'summary-value ' + (bal >= 0 ? 'balance-positive' : 'balance-negative');
    }

    function exportToCSV() {
const rows = [['S#', 'Bill No', 'Invoice Date', 'Days Overdue', 'Customer Name', 'Address', 'Primary Phone No', 'Balance', 'Received Amount', 'Sales Return', 'Signature']];        document.querySelectorAll('tbody tr').forEach(tr => {
            const cols = tr.querySelectorAll('td');
            const r = [];
            for (let i = 0; i < cols.length - 1; i++) r.push(cols[i].textContent.trim());
            r.push('');
            rows.push(r);
        });
        const blob = new Blob([rows.map(r => r.join(',')).join('\n')], { type: 'text/csv' });
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'recovery-sheet.csv' });
        a.click();
    }

    // ── Init ────────────────────────────────────────────────────────
    loadCompanies().then(() => loadRecoveryData());
});
