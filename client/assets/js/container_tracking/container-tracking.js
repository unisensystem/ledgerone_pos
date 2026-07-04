let timelineChart = null, summaryChart = null;
const API = '../../../../server/api/inventory/container_tracking/container-tracking.php';

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    document.getElementById('date-from').valueAsDate = new Date(today.getFullYear(), 0, 1);
    document.getElementById('date-to').valueAsDate = today;
    document.getElementById('ftr-date').textContent = today.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});

    loadContainers();

    document.getElementById('btn-apply').addEventListener('click', () => {
        const cid = document.getElementById('sel-container').value;
        if (cid) loadDetail(cid);
        else showCards();
    });

    document.getElementById('btn-reset').addEventListener('click', () => {
        document.getElementById('sel-container').value = '';
        document.getElementById('sel-status').value = '';
        showCards();
    });

    document.getElementById('btn-back').addEventListener('click', showCards);

    document.getElementById('tabs').addEventListener('click', e => {
        const btn = e.target.closest('.tab');
        if (!btn) return;
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

async function loadContainers() {
    const data = await callApi('');
    if (!data.success) return;

    const sel = document.getElementById('sel-container');
    data.containers.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.container_code + ' — ' + c.container_name;
        sel.appendChild(o);
    });

    renderCards(data.containers);
}

function renderCards(list) {
    document.getElementById('container-count').textContent = list.length;
    const grid = document.getElementById('cards-grid');

    if (!list.length) {
        grid.innerHTML = '<p style="color:var(--muted);padding:20px;">No containers found.</p>';
        return;
    }

    grid.innerHTML = '';

    list.forEach(c => {
        const div = document.createElement('div');
        const status = c.is_active ? 'active' : 'inactive';
        div.className = 'container-card status-' + status;
        div.style.cursor = 'pointer';
        div.innerHTML =
            '<div class="cc-code">' + c.container_code + '</div>' +
            '<div class="cc-name">' + c.container_name + '</div>' +
            '<div class="cc-meta">' +
                '<span><i class="fas fa-box"></i> <strong>' + (c.container_code||'—') + '</strong></span>' +
                '<span><i class="fas fa-circle-info"></i> <strong>' + (c.is_active ? 'Active' : 'Inactive') + '</strong></span>' +
            '</div>' +
            '<div class="cc-footer">' + badge(c.is_active ? 'active' : 'inactive') + '<span class="cc-docs"><i class="fas fa-file-alt"></i> Track</span></div>';

        div.addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('sel-container').value = c.id;
            loadDetail(c.id);
        });

        grid.appendChild(div);
    });
}

async function loadDetail(cid) {
    const from = document.getElementById('date-from').value;
    const to = document.getElementById('date-to').value;
    const data = await callApi('container_id=' + cid + '&date_from=' + from + '&date_to=' + to);
    if (!data.success) { console.error('API error', data); return; }

    document.getElementById('view-cards').style.display = 'none';
    document.getElementById('view-detail').style.display = 'block';

    const c = data.container;
    document.getElementById('detail-title').innerHTML =
        '<i class="fas fa-box" style="color:var(--primary);margin-right:8px;"></i>' + c.container_name +
        ' <span style="font-size:13px;font-family:monospace;background:#eff2f7;padding:2px 10px;border-radius:4px;color:var(--muted)">' + c.container_code + '</span>' +
        ' ' + badge(c.is_active ? 'active' : 'inactive');

    renderKPIs(data.summary);
    renderCharts(data.timeline, data.summary);

    setTabCount('cnt-timeline', data.timeline_events.length);
    setTabCount('cnt-inbound', data.inbound_gatepass.length);
    setTabCount('cnt-outbound', data.outbound_gatepass.length);
    setTabCount('cnt-transfers', data.stock_transfers.length);
    setTabCount('cnt-sales', data.sale_invoices.length);
    setTabCount('cnt-purchases', data.purchase_invoices.length);
    setTabCount('cnt-received', data.receive_vouchers.length);
    setTabCount('cnt-paid', data.payment_vouchers.length);
    setTabCount('cnt-expenses', data.expenses.length);

    fillTable('tbl-timeline', data.timeline_events, r =>
        '<td>' + fd(r.event_date) + '</td><td>' + r.event_type + '</td><td>' + (r.from_location||'—') + '</td><td>' + (r.to_location||'—') + '</td>' +
        '<td>' + r.document_number + '</td><td>' + (r.quantity||0) + '</td><td>' + fc(r.amount) + '</td><td>' + badge(r.status) + '</td>'
    );
    fillFooter('foot-timeline', data.timeline_events, 'amount', data.timeline_events.length + ' events');

    fillTable('tbl-inbound', data.inbound_gatepass, r =>
        '<td>' + r.gatepass_code + '</td><td>' + fd(r.date) + '</td><td>' + (r.supplier_name||'—') + '</td><td>' + (r.branch_name||'—') + '</td>' +
<<<<<<< Updated upstream
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td>' + badge(r.status||'pending') + '</td>'
=======
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td><code>' + (r.engine_nos||'—') + '</code></td><td><code>' + (r.chassis_nos||'—') + '</code></td><td>' + badge(r.status||'pending') + '</td>'
>>>>>>> Stashed changes
    );
    fillFooter('foot-inbound', data.inbound_gatepass, 'total_qty', data.inbound_gatepass.length + ' gatepasses');

    fillTable('tbl-outbound', data.outbound_gatepass, r =>
        '<td>' + r.gatepass_code + '</td><td>' + fd(r.date) + '</td><td>' + (r.supplier_name||'—') + '</td><td>' + (r.branch_name||'—') + '</td>' +
<<<<<<< Updated upstream
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td>' + badge(r.status||'pending') + '</td>'
=======
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td><code>' + (r.engine_nos||'—') + '</code></td><td><code>' + (r.chassis_nos||'—') + '</code></td><td>' + badge(r.status||'pending') + '</td>'
>>>>>>> Stashed changes
    );
    fillFooter('foot-outbound', data.outbound_gatepass, 'total_qty', data.outbound_gatepass.length + ' gatepasses');

    fillTable('tbl-transfers', data.stock_transfers, r =>
        '<td>' + r.transfer_code + '</td><td>' + fd(r.date) + '</td><td>' + (r.from_location||'—') + '</td><td>' + (r.to_location||'—') + '</td>' +
<<<<<<< Updated upstream
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_items||0) + '</td><td>' + fc(r.total_amount) + '</td>'
=======
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_items||0) + '</td><td><code>' + (r.engine_nos||'—') + '</code></td><td><code>' + (r.chassis_nos||'—') + '</code></td><td>' + fc(r.total_amount) + '</td>'
>>>>>>> Stashed changes
    );
    fillFooter('foot-transfers', data.stock_transfers, 'total_amount', data.stock_transfers.length + ' transfers');

    fillTable('tbl-sales', data.sale_invoices, r =>
        '<td>' + r.bill_no + '</td><td>' + fd(r.sale_date) + '</td><td>' + (r.customer_name||'—') + '</td>' +
<<<<<<< Updated upstream
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td><strong>' + fc(r.net_amount) + '</strong></td><td>' + badge(r.status) + '</td>'
=======
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td><code>' + (r.engine_nos||'—') + '</code></td><td><code>' + (r.chassis_nos||'—') + '</code></td><td><strong>' + fc(r.net_amount) + '</strong></td><td>' + badge(r.status) + '</td>'
>>>>>>> Stashed changes
    );
    fillFooter('foot-sales', data.sale_invoices, 'net_amount', data.sale_invoices.length + ' invoices');

    fillTable('tbl-purchases', data.purchase_invoices, r =>
        '<td>' + r.bill_no + '</td><td>' + fd(r.purchase_date) + '</td><td>' + (r.supplier_name||'—') + '</td>' +
<<<<<<< Updated upstream
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td><strong>' + fc(r.net_amount) + '</strong></td><td>' + badge(r.status) + '</td>'
=======
        '<td>' + (r.item_count||0) + '</td><td>' + (r.total_qty||0) + '</td><td><code>' + (r.engine_nos||'—') + '</code></td><td><code>' + (r.chassis_nos||'—') + '</code></td><td><strong>' + fc(r.net_amount) + '</strong></td><td>' + badge(r.status) + '</td>'
>>>>>>> Stashed changes
    );
    fillFooter('foot-purchases', data.purchase_invoices, 'net_amount', data.purchase_invoices.length + ' invoices');

    fillTable('tbl-received', data.receive_vouchers, r =>
        '<td>' + r.voucher_number + '</td><td>' + fd(r.voucher_date) + '</td><td>' + (r.customer_name||'—') + '</td>' +
        '<td>' + (r.description||'—') + '</td><td style="color:var(--success)"><strong>' + fc(r.amount) + '</strong></td>'
    );
    fillFooter('foot-received', data.receive_vouchers, 'amount', data.receive_vouchers.length + ' vouchers');

    fillTable('tbl-paid', data.payment_vouchers, r =>
        '<td>' + r.voucher_number + '</td><td>' + fd(r.voucher_date) + '</td><td>' + (r.supplier_name||'—') + '</td>' +
        '<td>' + (r.description||'—') + '</td><td style="color:var(--danger)"><strong>' + fc(r.amount) + '</strong></td>'
    );
    fillFooter('foot-paid', data.payment_vouchers, 'amount', data.payment_vouchers.length + ' vouchers');

    fillTable('tbl-expenses', data.expenses, r =>
        '<td>' + r.voucher_no + '</td><td>' + fd(r.date) + '</td><td>' + (r.description||'—') + '</td>' +
        '<td style="color:var(--warning)"><strong>' + fc(r.total_amount) + '</strong></td>'
    );
    fillFooter('foot-expenses', data.expenses, 'total_amount', data.expenses.length + ' vouchers');

    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector('.tab[data-tab="t-timeline"]').classList.add('active');
    document.getElementById('t-timeline').classList.add('active');
}

function showCards() {
    document.getElementById('view-cards').style.display = 'block';
    document.getElementById('view-detail').style.display = 'none';
}

function renderKPIs(s) {
    const kpis = [
        { l:'Total Inward', v:s.total_inbound_qty, c:'green', i:'fa-arrow-right' },
        { l:'Total Outward', v:s.total_outbound_qty, c:'orange', i:'fa-arrow-left' },
        { l:'Total Transfers', v:s.total_transfers, c:'purple', i:'fa-exchange-alt' },
        { l:'Sales Value', v:fc(s.total_sales), c:'green', i:'fa-receipt' },
        { l:'Purchase Value', v:fc(s.total_purchases), c:'orange', i:'fa-shopping-cart' },
        { l:'Expenses', v:fc(s.total_expenses), c:'warning', i:'fa-file-invoice-dollar' },
        { l:'Net P/L', v:fc(s.net_pl), c:s.net_pl>=0?'green':'red', i:'fa-balance-scale' },
    ];
    document.getElementById('kpi-row').innerHTML = kpis.map(k =>
        '<div class="kpi ' + k.c + '">' +
        '<div class="kpi-lbl">' + k.l + '</div>' +
        '<div class="kpi-val">' + k.v + '</div>' +
        '<i class="fas ' + k.i + ' kpi-ico"></i></div>'
    ).join('');
}

function renderCharts(timeline, s) {
    if (timelineChart) timelineChart.destroy();
    timelineChart = new Chart(document.getElementById('ch-timeline').getContext('2d'), {
        type: 'bar',
        data: {
            labels: timeline.map(t => t.event_type.replace('Inbound', 'Inward').replace('Outbound', 'Outward')),
            datasets: [{
                label:'Count',
                data: timeline.map(t => t.count || 0),
                backgroundColor:['#2fbf71','#e8b23f','#1f7bff','#e34f4f','#946ce6','#f97316'],
                borderRadius:6
            }]
        },
        options: {
            responsive:true,
            maintainAspectRatio:false,
            plugins:{ legend:{ display:false } },
            scales:{ y:{ beginAtZero:true }, x:{ grid:{ display:false } } }
        }
    });

    if (summaryChart) summaryChart.destroy();
    summaryChart = new Chart(document.getElementById('ch-summary').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Sales','Purchases','Expenses','Received','Paid'],
            datasets: [{
                data: [
                    s.total_sales,
                    s.total_purchases,
                    s.total_expenses,
                    s.total_received,
                    s.total_paid
                ],
                backgroundColor:['#2fbf71','#e8b23f','#f97316','#1f7bff','#e34f4f'],
                borderRadius:6
            }]
        },
        options: {
            responsive:true,
            maintainAspectRatio:false,
            plugins:{ legend:{ position:'bottom' } }
        }
    });
}

async function callApi(params) {
    const url = params ? API + '?' + params : API;
    try {
        const res = await fetch(url);
        const text = await res.text();
        return JSON.parse(text);
    } catch(e) {
        console.error('API Error:', e);
        return { success: false };
    }
}

function fillTable(id, rows, rowFn) {
    const tbody = document.querySelector('#' + id + ' tbody');
    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="20">No records found</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => '<tr>' + rowFn(r) + '</tr>').join('');
}

function fillFooter(id, rows, key, label) {
    const el = document.getElementById(id);
    if (!el) return;
    const total = rows.reduce((s, r) => s + (parseFloat(r[key])||0), 0);
    el.innerHTML = '<span>' + label + '</span><span class="tbl-total">Total: ' + fc(total) + '</span>';
}

function filterTable(id, q) {
    document.querySelectorAll('#' + id + ' tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}

function setTabCount(id, n) {
    const el = document.getElementById(id);
    if (el) el.textContent = n;
}

function fc(v) { return SYM + fnum(parseFloat(v||0)); }
function fnum(n) { return n.toLocaleString(undefined,{minimumFractionDigits:0,maximumFractionDigits:2}); }
function fd(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }
function badge(s) {
    if (!s) return '<span class="badge b-inactive">—</span>';
    return '<span class="badge b-' + (s.toLowerCase().replace(/\s+/g,'_')) + '">' + s + '</span>';
}
