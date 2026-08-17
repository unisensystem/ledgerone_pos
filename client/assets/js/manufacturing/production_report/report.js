(function () {
    'use strict';

    const API = '../../../../server/api/manufacturing/production_report/index.php';

    // ── Palette ──────────────────────────────────────────────────────────────
    const C = {
        blue:   '#1F7BFF', blueA:   'rgba(31,123,255,0.18)',
        green:  '#22C55E', greenA:  'rgba(34,197,94,0.18)',
        amber:  '#F59E0B', amberA:  'rgba(245,158,11,0.18)',
        red:    '#EF4444', redA:    'rgba(239,68,68,0.18)',
        purple: '#8B5CF6', purpleA: 'rgba(139,92,246,0.18)',
        teal:   '#14B8A6',
        gray:   '#D1D5DB', grayA:   'rgba(209,213,219,0.5)',
    };
    const STATUS_COLOR = {
        'Planned':     C.blue,
        'In Progress': C.amber,
        'Completed':   C.green,
        'Cancelled':   C.gray,
    };

    // ── State ────────────────────────────────────────────────────────────────
    const state = {
        activeTab:    'overview',
        trendPeriod:  'week',
        statusFilter: 'all',
        tabLoaded:    { overview: false, materials: false, goods: false, costs: false },
        charts:       {},
        products:     [],
    };

    // ── Utilities ────────────────────────────────────────────────────────────
    const fmt    = (n, d = 2)  => parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    const fmtC   = (n)         => fmt(n, 2);
    const pct    = (a, b)      => b > 0 ? ((a / b) * 100).toFixed(1) + '%' : '0.0%';
    const fmtD   = (d)         => { if (!d) return '—'; return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); };
    const toISO  = (d)         => d.toISOString().split('T')[0];

    function wastageClass(p) {
        if (p < 5)  return 'waste-low';
        if (p < 15) return 'waste-med';
        return 'waste-high';
    }

    function statusBadge(s) {
        return `<span class="badge badge-${(s || '').toLowerCase().replace(' ', '-')}">${s || '—'}</span>`;
    }

    function formatTrendLabel(label, period) {
        if (!label) return '';
        if (period === 'month') {
            const [y, m] = label.split('-');
            return new Date(y, m - 1).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
        }
        if (period === 'week') {
            const d = new Date(label);
            const jan1 = new Date(d.getFullYear(), 0, 1);
            const wk   = Math.ceil(((d - jan1) / 86400000 + jan1.getDay() + 1) / 7);
            return `Wk ${wk}, ${d.toLocaleDateString('en-GB', { month: 'short' })}`;
        }
        return new Date(label).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    }

    // ── Filter helpers ───────────────────────────────────────────────────────
    function getFilters() {
        return {
            date_from:  document.getElementById('filterDateFrom').value,
            date_to:    document.getElementById('filterDateTo').value,
            branch_id:  document.getElementById('filterBranch').value,
            product_id: document.getElementById('filterProductId').value,
            status:     state.statusFilter === 'all' ? '' : state.statusFilter,
        };
    }

    function buildQuery(action, extra = {}) {
        const f   = { ...getFilters(), action, ...extra };
        const qs  = Object.entries(f).filter(([, v]) => v !== '').map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');
        return `${API}?${qs}`;
    }

    function setPreset(preset) {
        const today = new Date();
        let from, to;
        if (preset === 'today') {
            from = to = today;
        } else if (preset === 'week') {
            const day = today.getDay() || 7;
            from = new Date(today); from.setDate(today.getDate() - day + 1);
            to   = today;
        } else if (preset === 'month') {
            from = new Date(today.getFullYear(), today.getMonth(), 1);
            to   = today;
        } else if (preset === 'last_month') {
            from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            to   = new Date(today.getFullYear(), today.getMonth(), 0);
        } else if (preset === 'quarter') {
            const q = Math.floor(today.getMonth() / 3);
            from = new Date(today.getFullYear(), q * 3, 1);
            to   = today;
        }
        document.getElementById('filterDateFrom').value = toISO(from);
        document.getElementById('filterDateTo').value   = toISO(to);
        document.querySelectorAll('.preset-pill').forEach(p => p.classList.toggle('active', p.dataset.preset === preset));
    }

    function resetFilters() {
        setPreset('month');
        document.getElementById('filterBranch').value    = '';
        document.getElementById('filterProduct').value   = '';
        document.getElementById('filterProductId').value = '';
        state.statusFilter = 'all';
        document.querySelectorAll('.status-pill').forEach(p => p.classList.toggle('active', p.dataset.status === 'all'));
        applyFilters();
    }

    function applyFilters() {
        Object.keys(state.tabLoaded).forEach(k => state.tabLoaded[k] = false);
        loadTabData(state.activeTab);
    }

    // ── Chart helpers ─────────────────────────────────────────────────────────
    function destroyChart(key) {
        if (state.charts[key]) { state.charts[key].destroy(); delete state.charts[key]; }
    }

    function mkChart(key, ctx, config) {
        destroyChart(key);
        config.options = {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { font: { family: 'Inter', size: 12 }, color: '#2F3B4C' } } },
            scales: config.scales || undefined,
            ...config.options,
        };
        state.charts[key] = new Chart(ctx, config);
    }

    const CHART_DEFAULTS = {
        font:       { family: 'Inter', size: 12 },
        gridColor:  'rgba(0,0,0,0.05)',
        tickColor:  '#6B7280',
    };

    function axisDefaults() {
        return {
            grid: { color: CHART_DEFAULTS.gridColor },
            ticks: { color: CHART_DEFAULTS.tickColor, font: { family: 'Inter', size: 11 } },
        };
    }

    // ── Tab management ────────────────────────────────────────────────────────
    function switchTab(tab) {
        state.activeTab = tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === `tab-${tab}`));
        if (!state.tabLoaded[tab]) loadTabData(tab);
    }

    async function loadTabData(tab) {
        switch (tab) {
            case 'overview':  await loadOverview();   break;
            case 'materials': await loadMaterials();  break;
            case 'goods':     await loadGoods();      break;
            case 'costs':     await loadCosts();      break;
        }
        state.tabLoaded[tab] = true;
    }

    // ── Tab 1: Overview ───────────────────────────────────────────────────────
    async function loadOverview() {
        const [kpis, status, trend, table] = await Promise.all([
            fetch(buildQuery('overview_kpis')).then(r => r.json()),
            fetch(buildQuery('overview_chart_status')).then(r => r.json()),
            fetch(buildQuery('overview_chart_trend', { period: state.trendPeriod })).then(r => r.json()),
            fetch(buildQuery('overview_table')).then(r => r.json()),
        ]);
        if (kpis.success)    renderKPIs(kpis.data);
        if (status.success)  renderStatusChart(status.data);
        if (trend.success)   renderTrendChart(trend.data);
        if (table.success)   renderOverviewTable(table.data);
    }

    function renderKPIs(d) {
        const completionPct = d.total_ordered > 0 ? ((d.total_produced / d.total_ordered) * 100).toFixed(1) : '0.0';
        const totalWastage  = d.raw_wastage + d.fg_wastage;
        const wastagePct    = d.total_ordered > 0 ? ((totalWastage / d.total_ordered) * 100).toFixed(1) : '0.0';
        const cpUnit        = d.total_produced > 0 ? (d.total_cost / d.total_produced) : 0;

        document.getElementById('kpiGrid').innerHTML = `
            <div class="kpi-card">
                <div class="kpi-icon kpi-blue"><i class="las la-layer-group"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Production Orders</div>
                    <div class="kpi-value">${d.total_orders}</div>
                    <div class="kpi-subs">
                        <span class="ks ks-green">${d.cnt_completed} Completed</span>
                        <span class="ks ks-amber">${d.cnt_in_progress} In Progress</span>
                        <span class="ks ks-gray">${d.cnt_planned} Planned</span>
                    </div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-purple"><i class="las la-boxes"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Units Ordered</div>
                    <div class="kpi-value">${fmt(d.total_ordered, 0)}</div>
                    <div class="kpi-subs"><span class="ks ks-gray">Across ${d.total_orders} orders</span></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-green"><i class="las la-check-circle"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Units Produced</div>
                    <div class="kpi-value">${fmt(d.total_produced, 0)}</div>
                    <div class="kpi-subs">
                        <span class="ks ks-${completionPct >= 80 ? 'green' : completionPct >= 50 ? 'amber' : 'red'}">${completionPct}% completion rate</span>
                    </div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-amber"><i class="las la-exclamation-triangle"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Wastage</div>
                    <div class="kpi-value">${fmt(totalWastage, 0)}</div>
                    <div class="kpi-subs">
                        <span class="ks ks-gray">${fmt(d.raw_wastage, 0)} raw material</span>
                        <span class="ks ks-red">${fmt(d.fg_wastage, 0)} finished goods</span>
                        <span class="ks ks-${parseFloat(wastagePct) < 5 ? 'green' : parseFloat(wastagePct) < 15 ? 'amber' : 'red'}">${wastagePct}% of ordered</span>
                    </div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-teal"><i class="las la-coins"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Production Cost</div>
                    <div class="kpi-value">${fmtC(d.total_cost)}</div>
                    <div class="kpi-subs">
                        <span class="ks ks-blue">${fmtC(d.material_cost)} material</span>
                        <span class="ks ks-purple">${fmtC(d.overhead_cost)} overhead</span>
                        <span class="ks ks-gray">${fmtC(cpUnit)} / unit</span>
                    </div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-blue"><i class="las la-chart-line"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Production Value</div>
                    <div class="kpi-value">${fmtC(d.total_production_value)}</div>
                </div>
            </div>
        `;
    }

    function renderStatusChart(rows) {
        const labels = rows.map(r => r.status);
        const values = rows.map(r => parseInt(r.count));
        const colors = labels.map(l => STATUS_COLOR[l] || C.gray);

        mkChart('status', document.getElementById('chartStatus').getContext('2d'), {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Orders', data: values, backgroundColor: colors, borderRadius: 6, borderSkipped: false }] },
            options: {
                plugins: { legend: { display: false } },
                scales: { x: axisDefaults(), y: { ...axisDefaults(), beginAtZero: true, ticks: { ...axisDefaults().ticks, stepSize: 1 } } },
            },
        });
    }

    function renderTrendChart(rows) {
        const labels = rows.map(r => formatTrendLabel(r.period_label, state.trendPeriod));
        const values = rows.map(r => parseFloat(r.units_produced));

        mkChart('trend', document.getElementById('chartTrend').getContext('2d'), {
            type: 'line',
            data: { labels, datasets: [{
                label: 'Units Produced', data: values,
                borderColor: C.blue, backgroundColor: C.blueA,
                fill: true, tension: 0.4, pointBackgroundColor: C.blue, pointRadius: 4,
            }]},
            options: {
                plugins: { legend: { display: false } },
                scales: { x: axisDefaults(), y: { ...axisDefaults(), beginAtZero: true } },
            },
        });
    }

    function renderOverviewTable(rows) {
        document.getElementById('overviewCount').textContent = `${rows.length} order${rows.length !== 1 ? 's' : ''}`;
        const tbody = document.getElementById('overviewTableBody');

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="empty-cell">No production orders found for selected filters</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const totalCost    = parseFloat(r.material_cost) + parseFloat(r.overhead_cost);
            const totalWastage = parseFloat(r.raw_wastage) + parseFloat(r.fg_wastage);
            const compPct      = parseFloat(r.order_qty) > 0 ? ((r.completed_qty / r.order_qty) * 100).toFixed(1) : '0.0';
            const barClass     = parseFloat(compPct) >= 80 ? 'prog-green' : parseFloat(compPct) >= 50 ? 'prog-amber' : 'prog-red';

            return `<tr class="clickable-row" data-id="${r.id}">
                <td><strong>${r.order_no}</strong></td>
                <td>${r.product_name}</td>
                <td>${r.branch_name}</td>
                <td>${fmt(r.order_qty, 0)}</td>
                <td>${fmt(r.completed_qty, 0)}</td>
                <td>
                    <div class="progress-wrap">
                        <div class="progress-bar"><div class="progress-fill ${barClass}" style="width:${Math.min(compPct,100)}%"></div></div>
                        <span class="progress-label">${compPct}%</span>
                    </div>
                </td>
                <td>${fmt(totalWastage, 0)}</td>
                <td>${fmtC(totalCost)}</td>
                <td>${statusBadge(r.status)}</td>
                <td><button class="btn-icon-sm btn-drill" title="View details"><i class="las la-expand-arrows-alt"></i></button></td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', () => openDrillDown(row.dataset.id));
        });
    }

    // ── Tab 2: Material Usage ─────────────────────────────────────────────────
    async function loadMaterials() {
        const res = await fetch(buildQuery('material_usage')).then(r => r.json());
        if (!res.success) return;
        renderMaterialCharts(res.data);
        renderMaterialTable(res.data);
    }

    function renderMaterialCharts(rows) {
        const top10 = rows.slice(0, 10);

        // Horizontal bar: top 10 by consumption
        mkChart('matTop', document.getElementById('chartMatTop').getContext('2d'), {
            type: 'bar',
            data: {
                labels: top10.map(r => r.material_name),
                datasets: [{ label: 'Issued (WIP)', data: top10.map(r => parseFloat(r.issued_qty)), backgroundColor: C.blue, borderRadius: 4 }],
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: axisDefaults(), y: { ...axisDefaults(), ticks: { ...axisDefaults().ticks, font: { family: 'Inter', size: 10 } } } },
            },
        });

        // Stacked bar: required vs wasted (top 8)
        const top8 = rows.slice(0, 8);
        mkChart('matWaste', document.getElementById('chartMatWaste').getContext('2d'), {
            type: 'bar',
            data: {
                labels: top8.map(r => r.material_name),
                datasets: [
                    { label: 'Required', data: top8.map(r => parseFloat(r.required_qty)), backgroundColor: C.blueA, borderColor: C.blue, borderWidth: 1, borderRadius: 4 },
                    { label: 'Wasted',   data: top8.map(r => parseFloat(r.wasted_qty)),   backgroundColor: C.red,   borderRadius: 4 },
                ],
            },
            options: {
                plugins: { legend: { labels: { font: { family: 'Inter', size: 12 }, color: '#2F3B4C' } } },
                scales: { x: { ...axisDefaults(), stacked: false }, y: { ...axisDefaults(), beginAtZero: true } },
            },
        });
    }

    function renderMaterialTable(rows) {
        document.getElementById('materialsCount').textContent = `${rows.length} material${rows.length !== 1 ? 's' : ''}`;
        const tbody = document.getElementById('materialsTableBody');

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-cell">No material data found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const netConsumed = parseFloat(r.issued_qty) - parseFloat(r.wasted_qty);
            const wastePct    = parseFloat(r.required_qty) > 0 ? (parseFloat(r.wasted_qty) / parseFloat(r.required_qty)) * 100 : 0;
            const wClass      = wastageClass(wastePct);

            return `<tr>
                <td><strong>${r.material_code}</strong> — ${r.material_name}</td>
                <td>${r.uom_name}</td>
                <td>${fmt(r.required_qty)}</td>
                <td>${fmt(r.issued_qty)}</td>
                <td>${fmt(r.wasted_qty)}</td>
                <td>${fmt(Math.max(0, netConsumed))}</td>
                <td><span class="waste-badge ${wClass}">${wastePct.toFixed(1)}%</span></td>
                <td>${r.po_count}</td>
            </tr>`;
        }).join('');
    }

    // ── Tab 3: Finished Goods ─────────────────────────────────────────────────
    async function loadGoods() {
        const res = await fetch(buildQuery('finished_goods')).then(r => r.json());
        if (!res.success) return;
        renderGoodsCharts(res.data);
        renderGoodsTable(res.data);
    }

    function renderGoodsCharts(rows) {
        // Grouped bar: ordered vs produced
        const top8 = rows.slice(0, 8);
        mkChart('goodsBar', document.getElementById('chartGoodsBar').getContext('2d'), {
            type: 'bar',
            data: {
                labels: top8.map(r => r.product_name),
                datasets: [
                    { label: 'Ordered',  data: top8.map(r => parseFloat(r.total_ordered)),  backgroundColor: C.blueA,  borderColor: C.blue,  borderWidth: 1.5, borderRadius: 4 },
                    { label: 'Produced', data: top8.map(r => parseFloat(r.total_produced)), backgroundColor: C.greenA, borderColor: C.green, borderWidth: 1.5, borderRadius: 4 },
                ],
            },
            options: {
                plugins: { legend: { labels: { font: { family: 'Inter', size: 12 }, color: '#2F3B4C' } } },
                scales: { x: axisDefaults(), y: { ...axisDefaults(), beginAtZero: true } },
            },
        });

        // Doughnut: output efficiency
        const totalOrdered  = rows.reduce((s, r) => s + parseFloat(r.total_ordered), 0);
        const totalProduced = rows.reduce((s, r) => s + parseFloat(r.total_produced), 0);
        const totalFgWaste  = rows.reduce((s, r) => s + parseFloat(r.fg_wastage_qty), 0);
        const shortfall     = Math.max(0, totalOrdered - totalProduced - totalFgWaste);

        mkChart('goodsDoughnut', document.getElementById('chartGoodsDoughnut').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Produced', 'FG Wastage', 'Shortfall'],
                datasets: [{ data: [totalProduced, totalFgWaste, shortfall], backgroundColor: [C.green, C.red, C.gray], borderWidth: 2 }],
            },
            options: {
                cutout: '65%',
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 12 }, color: '#2F3B4C' } } },
            },
        });
    }

    function renderGoodsTable(rows) {
        document.getElementById('goodsCount').textContent = `${rows.length} product${rows.length !== 1 ? 's' : ''}`;
        const tbody = document.getElementById('goodsTableBody');

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-cell">No finished goods data found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const netOutput = parseFloat(r.total_produced) - parseFloat(r.fg_wastage_qty);
            const compPct   = parseFloat(r.total_ordered) > 0 ? (parseFloat(r.total_produced) / parseFloat(r.total_ordered)) * 100 : 0;
            const barClass  = compPct >= 80 ? 'prog-green' : compPct >= 50 ? 'prog-amber' : 'prog-red';

            return `<tr>
                <td><strong>${r.product_code}</strong> — ${r.product_name}</td>
                <td>${r.order_count}</td>
                <td>${fmt(r.total_ordered, 0)}</td>
                <td>${fmt(r.total_produced, 0)}</td>
                <td>${fmt(r.fg_wastage_qty, 0)}</td>
                <td>${fmt(Math.max(0, netOutput), 0)}</td>
                <td>
                    <div class="progress-wrap">
                        <div class="progress-bar"><div class="progress-fill ${barClass}" style="width:${Math.min(compPct,100)}%"></div></div>
                        <span class="progress-label">${compPct.toFixed(1)}%</span>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Tab 4: Cost Breakdown ─────────────────────────────────────────────────
    async function loadCosts() {
        const res = await fetch(buildQuery('cost_breakdown')).then(r => r.json());
        if (!res.success) return;
        renderCostCharts(res.data);
        renderCostTable(res.data);
    }

    function renderCostCharts(rows) {
        // Stacked bar: material vs overhead (top 10)
        const top10 = rows.slice(0, 10);
        mkChart('costBar', document.getElementById('chartCostBar').getContext('2d'), {
            type: 'bar',
            data: {
                labels: top10.map(r => r.order_no),
                datasets: [
                    { label: 'Material Cost', data: top10.map(r => parseFloat(r.material_cost)), backgroundColor: C.blue,   borderRadius: 4, stack: 'cost' },
                    { label: 'Overhead',      data: top10.map(r => parseFloat(r.overhead_cost)), backgroundColor: C.purple, borderRadius: 4, stack: 'cost' },
                ],
            },
            options: {
                plugins: { legend: { labels: { font: { family: 'Inter', size: 12 }, color: '#2F3B4C' } } },
                scales: { x: axisDefaults(), y: { ...axisDefaults(), beginAtZero: true, stacked: true } },
            },
        });

        // Doughnut: cost composition
        const totalMat = rows.reduce((s, r) => s + parseFloat(r.material_cost), 0);
        const totalOvh = rows.reduce((s, r) => s + parseFloat(r.overhead_cost), 0);
        mkChart('costDoughnut', document.getElementById('chartCostDoughnut').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Material Cost', 'Overhead'],
                datasets: [{ data: [totalMat, totalOvh], backgroundColor: [C.blue, C.purple], borderWidth: 2 }],
            },
            options: {
                cutout: '65%',
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 12 }, color: '#2F3B4C' } } },
            },
        });

        // Summary strip
        const totalUnits = rows.reduce((s, r) => s + parseFloat(r.units_produced), 0);
        const totalCost  = totalMat + totalOvh;
        document.getElementById('csMaterial').textContent = fmtC(totalMat);
        document.getElementById('csOverhead').textContent = fmtC(totalOvh);
        document.getElementById('csTotal').textContent    = fmtC(totalCost);
        document.getElementById('csPerUnit').textContent  = totalUnits > 0 ? fmtC(totalCost / totalUnits) : '—';
        document.getElementById('costSummaryStrip').style.display = rows.length ? 'flex' : 'none';
    }

    function renderCostTable(rows) {
        document.getElementById('costsCount').textContent = `${rows.length} order${rows.length !== 1 ? 's' : ''}`;
        const tbody = document.getElementById('costsTableBody');

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-cell">No cost data found. Ensure WIP and expenses are recorded.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const total  = parseFloat(r.material_cost) + parseFloat(r.overhead_cost);
            const cpu    = parseFloat(r.units_produced) > 0 ? total / r.units_produced : 0;

            return `<tr>
                <td><strong>${r.order_no}</strong></td>
                <td>${r.product_name}</td>
                <td>${r.branch_name}</td>
                <td>${fmtC(r.material_cost)}</td>
                <td>${fmtC(r.overhead_cost)}</td>
                <td><strong>${fmtC(total)}</strong></td>
                <td>${fmt(r.units_produced, 0)}</td>
                <td>${cpu > 0 ? fmtC(cpu) : '—'}</td>
            </tr>`;
        }).join('');
    }

    // ── Drill-down panel ──────────────────────────────────────────────────────
    async function openDrillDown(poId) {
        document.getElementById('drillTitle').textContent = 'Loading...';
        document.getElementById('drillStatusBadge').innerHTML = '';
        document.getElementById('drillBody').innerHTML = '<div style="padding:40px;text-align:center;color:#6B7280;">Loading details...</div>';
        document.getElementById('drillPanel').classList.add('open');
        document.getElementById('drillBackdrop').classList.add('open');

        const res = await fetch(`${API}?action=po_detail&id=${poId}`).then(r => r.json());
        if (!res.success) {
            document.getElementById('drillBody').innerHTML = `<div style="padding:40px;text-align:center;color:#E34F4F;">${res.message}</div>`;
            return;
        }
        renderDrillDown(res.data);
    }

    function renderDrillDown(d) {
        const o = d.order;
        document.getElementById('drillTitle').textContent    = o.order_no;
        document.getElementById('drillStatusBadge').innerHTML = statusBadge(o.status);

        const totalProduced = d.completions.reduce((s, c) => s + parseFloat(c.completed_qty), 0);
        const compPct       = parseFloat(o.order_qty) > 0 ? (totalProduced / o.order_qty * 100).toFixed(1) : '0.0';
        const barClass      = compPct >= 80 ? 'prog-green' : compPct >= 50 ? 'prog-amber' : 'prog-red';

        const totalRawWastage = d.wastage.reduce((s, w) => s + parseFloat(w.raw_wastage_total), 0);
        const totalFgWastage  = d.wastage.reduce((s, w) => s + parseFloat(w.fg_wastage_qty || 0), 0);

        const completionsHtml = d.completions.length
            ? `<table class="drill-table"><thead><tr><th>Completion No</th><th>Date</th><th>Qty</th><th>Cost</th></tr></thead><tbody>
                ${d.completions.map(c => `<tr><td>${c.completion_no}</td><td>${fmtD(c.complete_date)}</td><td>${fmt(c.completed_qty)}</td><td>${fmtC(c.total_cost)}</td></tr>`).join('')}
               </tbody></table>`
            : '<p class="drill-empty">No completions recorded yet.</p>';

        const materialsHtml = d.materials.length
            ? `<table class="drill-table"><thead><tr><th>Material</th><th>Required</th><th>Issued</th><th>Wasted</th><th>UOM</th></tr></thead><tbody>
                ${d.materials.map(m => {
                    const issuedPct = parseFloat(m.required_qty) > 0 ? (parseFloat(m.issued_qty) / parseFloat(m.required_qty) * 100).toFixed(0) : 0;
                    const issClass  = issuedPct >= 90 ? 'ks-green' : issuedPct >= 50 ? 'ks-amber' : 'ks-gray';
                    return `<tr>
                        <td><strong>${m.material_code}</strong> — ${m.material_name}</td>
                        <td>${fmt(m.required_qty)}</td>
                        <td>${fmt(m.issued_qty)} <span class="ks ${issClass}" style="font-size:11px;">${issuedPct}%</span></td>
                        <td>${fmt(m.wasted_qty)}</td>
                        <td>${m.uom_name}</td>
                    </tr>`;
                }).join('')}
               </tbody></table>`
            : '<p class="drill-empty">No materials in this order.</p>';

        const wastageHtml = (totalRawWastage > 0 || totalFgWastage > 0)
            ? `<div class="wastage-summary">
                <div class="wastage-row">
                    <span class="wastage-label">Raw Material Wastage</span>
                    <span class="wastage-val">${fmt(totalRawWastage)} units</span>
                </div>
                <div class="wastage-row">
                    <span class="wastage-label">Finished Good Wastage</span>
                    <span class="wastage-val ${totalFgWastage > 0 ? 'waste-val-red' : ''}">${fmt(totalFgWastage)} units</span>
                </div>
               </div>
               ${d.wastage.map(w => `<div class="wastage-entry-row"><i class="las la-file-alt"></i> ${w.wastage_no} &nbsp;·&nbsp; ${fmtD(w.wastage_date)} &nbsp;·&nbsp; Raw: ${fmt(w.raw_wastage_total)} &nbsp;·&nbsp; FG: ${fmt(w.fg_wastage_qty || 0)}</div>`).join('')}`
            : '<p class="drill-empty">No wastage recorded for this order.</p>';

        document.getElementById('drillBody').innerHTML = `
            <div class="drill-section">
                <div class="drill-section-title">Order Details</div>
                <div class="drill-detail-grid">
                    <div class="drill-detail-item"><div class="dd-label">Product</div><div class="dd-value">${o.product_code} — ${o.product_name}</div></div>
                    <div class="drill-detail-item"><div class="dd-label">Branch</div><div class="dd-value">${o.branch_name}</div></div>
                    <div class="drill-detail-item"><div class="dd-label">BOM</div><div class="dd-value">${o.bom_code || '—'}</div></div>
                    <div class="drill-detail-item"><div class="dd-label">Machine</div><div class="dd-value">${o.machine_name || '—'}</div></div>
                    <div class="drill-detail-item"><div class="dd-label">Order Qty</div><div class="dd-value">${fmt(o.order_qty)}</div></div>
                    <div class="drill-detail-item"><div class="dd-label">Start Date</div><div class="dd-value">${fmtD(o.start_date)}</div></div>
                    <div class="drill-detail-item"><div class="dd-label">End Date</div><div class="dd-value">${fmtD(o.end_date)}</div></div>
                    <div class="drill-detail-item"><div class="dd-label">Created</div><div class="dd-value">${fmtD(o.created_at)}</div></div>
                </div>
            </div>

            <div class="drill-section">
                <div class="drill-section-title">Completion Progress</div>
                <div class="progress-wrap" style="margin-bottom:14px;">
                    <div class="progress-bar progress-bar-lg"><div class="progress-fill ${barClass}" style="width:${Math.min(compPct,100)}%"></div></div>
                    <span class="progress-label">${fmt(totalProduced, 0)} / ${fmt(o.order_qty, 0)} units &nbsp;(${compPct}%)</span>
                </div>
                ${completionsHtml}
            </div>

            <div class="drill-section">
                <div class="drill-section-title">Material Usage</div>
                ${materialsHtml}
            </div>

            <div class="drill-section">
                <div class="drill-section-title">Wastage</div>
                ${wastageHtml}
            </div>

            <div class="drill-section">
                <div class="drill-section-title">Cost Summary</div>
                <div class="cost-blocks">
                    <div class="cost-block cost-block-blue">
                        <div class="cb-label">Material Cost</div>
                        <div class="cb-value">${fmtC(d.costs.material_cost)}</div>
                    </div>
                    <div class="cost-block cost-block-purple">
                        <div class="cb-label">Overhead</div>
                        <div class="cb-value">${fmtC(d.costs.overhead_cost)}</div>
                    </div>
                    <div class="cost-block cost-block-dark">
                        <div class="cb-label">Total Cost</div>
                        <div class="cb-value">${fmtC(d.costs.total_cost)}</div>
                    </div>
                    <div class="cost-block cost-block-teal">
                        <div class="cb-label">Cost / Unit</div>
                        <div class="cb-value">${d.costs.cost_per_unit > 0 ? fmtC(d.costs.cost_per_unit) : '—'}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function closeDrillDown() {
        document.getElementById('drillPanel').classList.remove('open');
        document.getElementById('drillBackdrop').classList.remove('open');
    }

    // ── Init & Events ─────────────────────────────────────────────────────────
    async function loadFilterData() {
        const res = await fetch(`${API}?action=filters_data`).then(r => r.json());
        if (!res.success) return;

        const brSelect = document.getElementById('filterBranch');
        res.branches.forEach(b => {
            const o = document.createElement('option');
            o.value = b.id; o.textContent = b.branch_name;
            brSelect.appendChild(o);
        });

        state.products = res.products;
        const dl = document.getElementById('productFilterList');
        res.products.forEach(p => {
            const o = document.createElement('option');
            o.value = `${p.code} — ${p.name}`; o.dataset.id = p.id;
            dl.appendChild(o);
        });
    }

    function bindEvents() {
        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn =>
            btn.addEventListener('click', () => switchTab(btn.dataset.tab))
        );

        // Preset pills
        document.querySelectorAll('.preset-pill').forEach(p =>
            p.addEventListener('click', () => setPreset(p.dataset.preset))
        );

        // Status pills
        document.querySelectorAll('.status-pill').forEach(p =>
            p.addEventListener('click', () => {
                state.statusFilter = p.dataset.status;
                document.querySelectorAll('.status-pill').forEach(x => x.classList.toggle('active', x.dataset.status === p.dataset.status));
            })
        );

        // Product datalist
        document.getElementById('filterProduct').addEventListener('input', function () {
            const match = state.products.find(p => `${p.code} — ${p.name}` === this.value);
            document.getElementById('filterProductId').value = match ? match.id : '';
        });

        // Filter bar
        document.getElementById('applyFiltersBtn').addEventListener('click', applyFilters);
        document.getElementById('resetFiltersBtn').addEventListener('click', resetFilters);

        // Trend toggle
        document.querySelectorAll('.period-btn').forEach(b =>
            b.addEventListener('click', () => {
                state.trendPeriod = b.dataset.period;
                document.querySelectorAll('.period-btn').forEach(x => x.classList.toggle('active', x.dataset.period === b.dataset.period));
                // Re-fetch only the trend chart
                fetch(buildQuery('overview_chart_trend', { period: state.trendPeriod }))
                    .then(r => r.json()).then(res => { if (res.success) renderTrendChart(res.data); });
            })
        );

        // Drill-down close
        document.getElementById('drillClose').addEventListener('click', closeDrillDown);
        document.getElementById('drillBackdrop').addEventListener('click', closeDrillDown);
    }

    async function init() {
        setPreset('month');
        await loadFilterData();
        bindEvents();
        await loadTabData('overview');
    }

    init();
})();
