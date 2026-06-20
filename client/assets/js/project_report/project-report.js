let trendChart = null, summaryChart = null;
const API = '../../../server/api/project_report/project-report.php';

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    document.getElementById('date-from').valueAsDate = new Date(today.getFullYear(), 0, 1);
    document.getElementById('date-to').valueAsDate   = today;
    document.getElementById('ftr-date').textContent  = today.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});

    loadProjects();

    document.getElementById('btn-apply').addEventListener('click', () => {
        const pid = document.getElementById('sel-project').value;
        if (pid) loadDetail(pid);
        else showCards();
    });

    document.getElementById('btn-reset').addEventListener('click', () => {
        document.getElementById('sel-project').value = '';
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

async function loadProjects() {
    const data = await callApi('');
    if (!data.success) return;

    const sel = document.getElementById('sel-project');
    data.projects.forEach(p => {
        const o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.project_code + ' — ' + p.project_name;
        sel.appendChild(o);
    });

    renderCards(data.projects);
}

function renderCards(list) {
    document.getElementById('proj-count').textContent = list.length;
    const grid = document.getElementById('cards-grid');

    if (!list.length) {
        grid.innerHTML = '<p style="color:var(--muted);padding:20px;">No projects found.</p>';
        return;
    }

    grid.innerHTML = '';

    list.forEach(p => {
        const div = document.createElement('div');
        div.className = 'proj-card status-' + (p.status||'').toLowerCase().replace(/\s/g,'_');
        div.style.cursor = 'pointer';
        div.innerHTML =
            '<div class="pc-code">' + p.project_code + '</div>' +
            '<div class="pc-name">' + p.project_name + '</div>' +
            '<div class="pc-meta">' +
                '<span><i class="fas fa-building"></i> <strong>' + (p.company_name||'—') + '</strong></span>' +
                '<span><i class="fas fa-map-marker-alt"></i> <strong>' + (p.city_name||'—') + '</strong></span>' +
                '<span><i class="fas fa-user-tie"></i> <strong>' + (p.manager_name||'—') + '</strong></span>' +
                '<span><i class="fas fa-calendar"></i> <strong>' + fd(p.start_date) + '</strong></span>' +
            '</div>' +
            '<div class="pc-footer">' + badge(p.status) + '<span class="pc-team"><i class="fas fa-users"></i> ' + p.team_count + ' members</span></div>';

        div.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('Card clicked:', p.project_code, p.id);
            document.getElementById('sel-project').value = p.id;
            loadDetail(p.id);
        });

        grid.appendChild(div);
    });
}

async function loadDetail(pid) {
    console.log('loadDetail called with project_id:', pid);
    const from = document.getElementById('date-from').value;
    const to   = document.getElementById('date-to').value;
    console.log('Date range:', from, 'to', to);
    const data = await callApi('project_id=' + pid + '&date_from=' + from + '&date_to=' + to);
    console.log('API response:', data);
    if (!data.success) { console.error('API error', data); return; }

    document.getElementById('view-cards').style.display  = 'none';
    document.getElementById('view-detail').style.display = 'block';

    const p = data.project;
    document.getElementById('detail-title').innerHTML =
        '<i class="fas fa-project-diagram" style="color:var(--primary);margin-right:8px;"></i>' + p.project_name +
        ' <span style="font-size:13px;font-family:monospace;background:#eff2f7;padding:2px 10px;border-radius:4px;color:var(--muted)">' + p.project_code + '</span>' +
        ' ' + badge(p.status);

    renderKPIs(data.summary);
    renderCharts(data.trend, data.summary);

    setTabCount('cnt-sales',    data.sale_invoices.length);
    setTabCount('cnt-sale-ret', data.sale_returns.length);
    setTabCount('cnt-purchase', data.purchase_invoices.length);
    setTabCount('cnt-purch-ret',data.purchase_returns.length);
    setTabCount('cnt-received', data.receive_vouchers.length);
    setTabCount('cnt-paid',     data.payment_vouchers.length);
    setTabCount('cnt-expense',  data.expenses.length);
    setTabCount('cnt-quot',     data.quotations.length);
    setTabCount('cnt-cust',     data.customers.length);
    setTabCount('cnt-supp',     data.suppliers.length);
    setTabCount('cnt-team',     data.team.length);

    fillTable('tbl-sales', data.sale_invoices, r =>
        '<td>' + r.bill_no + '</td><td>' + fd(r.sale_date) + '</td><td>' + (r.customer_name||'—') + '</td><td>' + (r.officer_name||'—') + '</td>' +
        '<td>' + (r.item_count||0) + ' items</td><td>' + fc(r.total_bill) + '</td><td style="color:var(--danger)">' + fc(r.total_discount_amount) + '</td>' +
        '<td><strong>' + fc(r.net_amount) + '</strong></td><td>' + badge(r.status) + '</td>'
    );
    fillFooter('foot-sales', data.sale_invoices, 'net_amount', data.sale_invoices.length + ' invoices');

    fillTable('tbl-sale-ret', data.sale_returns, r =>
        '<td>' + r.bill_no + '</td><td>' + fd(r.sale_date) + '</td><td>' + (r.customer_name||'—') + '</td>' +
        '<td>' + fc(r.total_bill) + '</td><td style="color:var(--danger)"><strong>' + fc(r.net_amount) + '</strong></td>'
    );
    fillFooter('foot-sale-ret', data.sale_returns, 'net_amount', data.sale_returns.length + ' returns');

    fillTable('tbl-purchase', data.purchase_invoices, r =>
        '<td>' + r.bill_no + '</td><td>' + fd(r.purchase_date) + '</td><td>' + (r.supplier_name||'—') + '</td>' +
        '<td>' + (r.item_count||0) + ' items</td><td>' + fc(r.total_bill) + '</td>' +
        '<td><strong>' + fc(r.net_amount) + '</strong></td><td>' + badge(r.status) + '</td>'
    );
    fillFooter('foot-purchase', data.purchase_invoices, 'net_amount', data.purchase_invoices.length + ' invoices');

    fillTable('tbl-purch-ret', data.purchase_returns, r =>
        '<td>' + r.bill_no + '</td><td>' + fd(r.purchase_date) + '</td><td>' + (r.supplier_name||'—') + '</td>' +
        '<td>' + fc(r.total_bill) + '</td><td style="color:var(--danger)"><strong>' + fc(r.net_amount) + '</strong></td>'
    );
    fillFooter('foot-purch-ret', data.purchase_returns, 'net_amount', data.purchase_returns.length + ' returns');

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

    fillTable('tbl-expense', data.expenses, r =>
        '<td>' + r.voucher_no + '</td><td>' + fd(r.date) + '</td><td>' + (r.description||'—') + '</td>' +
        '<td style="color:var(--warning)"><strong>' + fc(r.total_amount) + '</strong></td>'
    );
    fillFooter('foot-expense', data.expenses, 'total_amount', data.expenses.length + ' vouchers');

    fillTable('tbl-quot', data.quotations, r =>
        '<td>' + r.quotation_number + '</td><td>' + fd(r.quotation_date) + '</td><td>' + fd(r.valid_till) + '</td>' +
        '<td>' + (r.customer_name||'—') + '</td><td>' + (r.salesman_name||'—') + '</td><td>' + (r.item_count||0) + '</td>' +
        '<td>' + fc(r.subtotal) + '</td><td><strong>' + fc(r.grand_total) + '</strong></td><td>' + badge(r.status) + '</td>'
    );
    fillFooter('foot-quot', data.quotations, 'grand_total', data.quotations.length + ' quotations');

    fillTable('tbl-cust', data.customers, r =>
        '<td>' + r.customer_code + '</td><td>' + r.customer_name + '</td><td>' + (r.primary_phone||'—') + '</td>' +
        '<td>' + fc(r.current_balance) + '</td><td>' + badge(r.status) + '</td>'
    );

    fillTable('tbl-supp', data.suppliers, r =>
        '<td>' + r.supplier_code + '</td><td>' + r.supplier_name + '</td><td>' + (r.primary_phone||'—') + '</td>' +
        '<td>' + fc(r.current_balance) + '</td><td>' + badge(r.status) + '</td>'
    );

    fillTable('tbl-team', data.team, r =>
        '<td>' + r.employee_code + '</td><td>' + r.full_name + '</td><td>' + (r.designation||'—') + '</td>' +
        '<td>' + (r.phone||'—') + '</td><td>' + fd(r.added_at) + '</td>'
    );

    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector('.tab[data-tab="t-sales"]').classList.add('active');
    document.getElementById('t-sales').classList.add('active');
}

function showCards() {
    document.getElementById('view-cards').style.display  = 'block';
    document.getElementById('view-detail').style.display = 'none';
}

function renderKPIs(s) {
    const kpis = [
        { l:'Total Sales',       v:fc(s.total_sales),    c:'green',  i:'fa-chart-line'          },
        { l:'Sale Returns',      v:fc(s.total_sale_ret), c:'red',    i:'fa-undo'                },
        { l:'Net Sales',         v:fc(s.total_sales - s.total_sale_ret), c:'green', i:'fa-check-circle' },
        { l:'Total Purchases',   v:fc(s.total_purchase), c:'orange', i:'fa-shopping-cart'       },
        { l:'Purchase Returns',  v:fc(s.total_purch_ret),c:'gray',   i:'fa-undo-alt'            },
        { l:'Net Purchases',     v:fc(s.total_purchase - s.total_purch_ret), c:'orange', i:'fa-box' },
        { l:'Total Received',    v:fc(s.total_received), c:'green',  i:'fa-hand-holding-usd'    },
        { l:'Total Paid',        v:fc(s.total_paid),     c:'red',    i:'fa-money-bill-wave'      },
        { l:'Total Expenses',    v:fc(s.total_expenses), c:'orange', i:'fa-file-invoice-dollar'  },
        { l:'Net Profit / Loss', v:fc(s.net_profit),     c:s.net_profit>=0?'green':'red', i:'fa-balance-scale' },
        { l:'Sale Invoices',     v:s.sale_count,         c:'',       i:'fa-receipt'             },
        { l:'Quotations',        v:s.quotation_count,    c:'purple', i:'fa-file-alt'            },
    ];
    document.getElementById('kpi-row').innerHTML = kpis.map(k =>
        '<div class="kpi ' + k.c + '">' +
        '<div class="kpi-lbl">' + k.l + '</div>' +
        '<div class="kpi-val">' + k.v + '</div>' +
        '<i class="fas ' + k.i + ' kpi-ico"></i></div>'
    ).join('');
}

function renderCharts(trend, s) {
    if (trendChart) trendChart.destroy();
    trendChart = new Chart(document.getElementById('ch-trend').getContext('2d'), {
        type: 'line',
        data: {
            labels: trend.map(t => t.mon),
            datasets: [{ label:'Sales', data: trend.map(t => parseFloat(t.sales)||0),
                borderColor:'#1f7bff', backgroundColor:'rgba(31,123,255,.1)',
                borderWidth:2.5, fill:true, tension:.4, pointRadius:4 }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false } },
            scales:{ y:{ beginAtZero:true, ticks:{ callback: v => SYM+fnum(v) } }, x:{ grid:{ display:false } } }
        }
    });

    if (summaryChart) summaryChart.destroy();
    summaryChart = new Chart(document.getElementById('ch-summary').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Net Sales','Net Purchases','Received','Paid','Expenses'],
            datasets: [{ data: [
                s.total_sales - s.total_sale_ret,
                s.total_purchase - s.total_purch_ret,
                parseFloat(s.total_received),
                parseFloat(s.total_paid),
                parseFloat(s.total_expenses)
            ], backgroundColor:['#2fbf71','#e8b23f','#1f7bff','#e34f4f','#946ce6'], borderRadius:6 }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false } },
            scales:{ y:{ beginAtZero:true, ticks:{ callback: v => SYM+fnum(v) } }, x:{ grid:{ display:false } } }
        }
    });
}

async function callApi(params) {
    const url = params ? API + '?' + params : API;
    console.log('Calling API:', url);
    try {
        const res  = await fetch(url);
        console.log('API response status:', res.status);
        const text = await res.text();
        console.log('API response text:', text.substring(0, 500));
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

function fc(v)   { return SYM + fnum(parseFloat(v||0)); }
function fnum(n) { return n.toLocaleString(undefined,{minimumFractionDigits:0,maximumFractionDigits:2}); }
function fd(d)   { if (!d) return '—'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }
function badge(s) {
    if (!s) return '<span class="badge b-inactive">—</span>';
    return '<span class="badge b-' + s.toLowerCase().replace(/\s+/g,'_') + '">' + s + '</span>';
}
