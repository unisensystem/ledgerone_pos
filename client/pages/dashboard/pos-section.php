<?php
// ===== POS Business Section — included from dashboard.php =====
// Needs $pdo and $tenant_id from dashboard.php. Picks the POS mode from the company's business_type.
$allowedBT = ['restaurant', 'bakery', 'karyana', 'general', 'medical', 'mall'];
$posCompanyId = $_SESSION['company_id'] ?? null;
if ($posCompanyId) {
    $stmt = $pdo->prepare("SELECT business_type FROM companies WHERE tenant_id = ? AND id = ? LIMIT 1");
    $stmt->execute([$tenant_id, $posCompanyId]);
} else {
    $stmt = $pdo->prepare("SELECT business_type FROM companies WHERE tenant_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$tenant_id]);
}
$businessType = $stmt->fetchColumn();
if (!in_array($businessType, $allowedBT, true)) {
    $businessType = 'general';
}
?>
<style>
.pos-sec{margin:2rem 0}
.pos-top{margin-bottom:.7rem}
.pos-mode-tag{display:inline-block;padding:.25rem .85rem;background:#eff6ff;color:#1f7bff;border:1px solid #bfdbfe;border-radius:9999px;font-size:13px;font-weight:600}
.pos-wrap{display:grid;grid-template-columns:1fr 430px;gap:1.25rem;align-items:start}
.pos-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.8rem}
.pos-bar input,.pos-bar select,.pos-f{height:46px;padding:0 .9rem;border:1.5px solid #d6dbe4;border-radius:.6rem;font-size:15px;background:#fff;color:#1e293b;min-width:0}
.pos-bar input{flex:1;min-width:200px}
.pos-cats{display:flex;gap:.5rem;overflow-x:auto;margin-bottom:.9rem;padding-bottom:4px}
.pos-ct{padding:.6rem 1.15rem;background:#fff;border:1.5px solid #d6dbe4;border-radius:9999px;font-size:14px;font-weight:600;color:#334155;cursor:pointer;white-space:nowrap}
.pos-ct:hover{border-color:#1f7bff;color:#1f7bff}
.pos-ct.on{background:#1f7bff;border-color:#1f7bff;color:#fff}
.pos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.9rem;max-height:720px;overflow-y:auto;padding:2px}
.pos-it{position:relative;background:#fff;border:2px solid #e2e8f0;border-radius:.9rem;overflow:hidden;cursor:pointer;transition:all .15s;user-select:none}
.pos-it:hover{border-color:#1f7bff;transform:translateY(-2px);box-shadow:0 8px 20px rgba(59,130,246,.18)}
.pos-it:active{transform:scale(.97)}
.pos-im{height:130px;display:flex;align-items:center;justify-content:center;font-size:3rem;font-weight:700;color:#1f7bff;background:hsl(var(--h) 70% 93%);overflow:hidden}
.pos-im img{width:100%;height:100%;object-fit:cover;display:block}
.pos-in{padding:.65rem .8rem}
.pos-in b{display:block;font-size:14px;color:#0f172a;min-height:36px;line-height:1.3}
.pos-in span{color:#16a34a;font-weight:700;font-size:16px}
.pos-in small{color:#94a3b8;font-size:12px}
.pos-stk{position:absolute;top:8px;right:8px;background:rgba(255,255,255,.95);font-size:11px;font-weight:700;padding:3px 8px;border-radius:9999px;color:#16a34a;box-shadow:0 1px 4px rgba(0,0,0,.15)}
.pos-stk.low{color:#dc2626}
.pos-cart{background:#fff;border:2px solid #e2e8f0;border-radius:.9rem;padding:1.1rem}
.pos-cart h3{margin:0 0 .6rem;font-size:1.15rem;color:#0f172a;display:flex;justify-content:space-between;align-items:center}
.pos-cart h3 em{font-style:normal;font-size:12px;background:#1f7bff;color:#fff;border-radius:9999px;padding:2px 10px}
.pos-g2{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.5rem}
.pos-g2 .w{grid-column:1/-1}
.pos-g2 input,.pos-g2 select{height:40px;padding:0 .7rem;border:1.5px solid #d6dbe4;border-radius:.5rem;font-size:13.5px;background:#fff;color:#1e293b;width:100%;min-width:0}
.pos-held{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.5rem}
.pos-held button{border:1.5px dashed #e8b23f;background:#fff8e8;color:#92400e;border-radius:.5rem;padding:4px 10px;font-size:12px;font-weight:600;cursor:pointer}
.pos-lines{max-height:340px;overflow-y:auto;min-height:90px}
.pos-ln{display:flex;gap:.5rem;align-items:center;padding:.65rem 0;border-bottom:1px solid #f1f5f9;font-size:14px;color:#334155}
.pos-ln .n{flex:1;min-width:0}.pos-ln .n small{display:block;color:#94a3b8;font-size:12px}
.pos-q{display:flex;align-items:center;gap:6px}
.pos-q button,.pos-ic{width:30px;height:30px;border:1px solid #d6dbe4;border-radius:7px;background:#f8fafc;cursor:pointer;font-size:15px;color:#334155}
.pos-q button:hover,.pos-ic:hover{background:#eff6ff;border-color:#1f7bff}
.pos-ic.x:hover{background:#fef2f2;border-color:#dc2626;color:#dc2626}
.pos-row{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.3rem 0;font-size:14.5px;color:#334155}
.pos-row input{width:90px;height:34px;padding:0 .5rem;border:1.5px solid #d6dbe4;border-radius:6px;text-align:right}
.pos-tot{font-size:1.4rem;font-weight:800;color:#0f172a;border-top:2px dashed #e2e8f0;margin-top:.4rem;padding-top:.6rem}
.pos-cash{display:flex;gap:.4rem;flex-wrap:wrap;margin:.3rem 0 .5rem}
.pos-cash button{flex:1;min-width:60px;height:38px;border:1.5px solid #d6dbe4;background:#fff;border-radius:.5rem;font-weight:600;cursor:pointer;color:#334155}
.pos-cash button:hover{border-color:#1f7bff;color:#1f7bff}
.pos-btns{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.7rem}
.pos-btn{width:100%;height:46px;border:0;border-radius:.6rem;background:#1f7bff;color:#fff;font-weight:600;font-size:15px;cursor:pointer}
.pos-btn:hover{background:#1559b8}.pos-btn:disabled{opacity:.6;cursor:wait}
.pos-btn.full{grid-column:1/-1;height:54px;font-size:17px}
.pos-btn.g{background:#fff;color:#334155;border:1.5px solid #d6dbe4}.pos-btn.g:hover{background:#f8fafc}
.pos-btn.y{background:#e8b23f;color:#fff}
.pos-empty{text-align:center;color:#94a3b8;padding:2rem 0;font-size:14px}
.pos-md{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:10001;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px)}
.pos-md.on{display:flex}
.pos-box{background:#fff;border-radius:14px;padding:1.4rem;width:100%;max-width:460px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 50px -12px rgba(0,0,0,.25)}
.pos-box h3{margin:0 0 1rem;color:#0f172a}
.pos-box label.t{display:block;font-size:12px;color:#475569;text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin-top:.7rem}
.pos-box input.f{width:100%;height:44px;padding:0 .8rem;border:1.5px solid #d6dbe4;border-radius:.5rem;font-size:15px;margin-top:.3rem}
.pos-chips{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.4rem}
.pos-chips button{border:1.5px solid #d6dbe4;background:#fff;border-radius:9999px;padding:.4rem .8rem;font-size:13px;cursor:pointer;color:#334155}
.pos-chips button:hover{border-color:#1f7bff;color:#1f7bff}
.pos-kt{width:100%;border-collapse:collapse;font-size:14px;margin-top:.6rem}
.pos-kt td{padding:.4rem .3rem;border-bottom:1px solid #f1f5f9;color:#334155}
.pos-kt td:last-child{text-align:right;white-space:nowrap}
.pos-kb{min-width:140px;height:34px;border:1.5px solid #d6dbe4;background:#f8fafc;border-radius:.5rem;font-weight:700;font-size:13px;cursor:pointer;color:#1f7bff}
.pos-kb:hover{border-color:#1f7bff}
.pos-kb.cap{border-color:#e8b23f;background:#fff8e8;color:#92400e}
.pos-bills{margin-top:1.5rem;background:#fff;border:2px solid #e2e8f0;border-radius:.9rem;padding:1.1rem}
.pos-bills table{width:100%;border-collapse:collapse;font-size:14px}
.pos-bills th{text-align:left;padding:.7rem;font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:#475569;background:#f8fafc;border-bottom:2px solid #e2e8f0}
.pos-bills td{padding:.7rem;border-bottom:1px solid #f1f5f9;color:#334155}
.pos-bills .r{text-align:right}
.pos-bills .a button{border:1.5px solid #d6dbe4;background:#fff;border-radius:6px;padding:5px 10px;font-size:12.5px;font-weight:600;cursor:pointer;margin-right:4px;color:#334155}
.pos-bills .a button:hover{border-color:#1f7bff;color:#1f7bff}
.pos-bills .a button.d:hover{border-color:#dc2626;color:#dc2626}
.pos-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:.8rem 1.3rem;border-radius:.7rem;z-index:100001;font-size:14px;box-shadow:0 10px 25px rgba(0,0,0,.25)}
.pos-toast.err{background:#dc2626}
@media(max-width:1100px){.pos-wrap{grid-template-columns:1fr}.pos-grid{max-height:none}}
@media(max-width:480px){.pos-grid{grid-template-columns:repeat(2,1fr)}.pos-im{height:100px}.pos-bills{overflow-x:auto}}
</style>

<div class="pos-sec quick-actions">
    <!-- <h2 class="section-title">POS</h2> -->
    <div class="pos-top" id="posTop"></div>
    <div class="pos-wrap">
        <div>
            <div class="pos-bar">
                <input type="text" id="posSearch" placeholder="Search item / scan barcode (Enter)" autocomplete="off">
                <select id="posBranch" title="Branch"></select>
                <select id="posCounter" title="Counter"><option value="">-- Select Counter --</option></select>
                <button type="button" class="pos-btn g" id="posApBtn" title="Turn auto print on/off when saving" style="width:auto;height:46px;padding:0 14px;font-size:14px"></button>
                <button type="button" class="pos-btn g" id="posKeysBtn" title="Shortcut keys" style="width:auto;height:46px;padding:0 14px;font-size:14px">⌨ Shortcuts</button>
            </div>
            <div class="pos-cats" id="posCats"></div>
            <div class="pos-grid" id="posGrid"><div class="pos-empty">Loading products...</div></div>
        </div>
        <div class="pos-cart">
            <h3>Current Bill <em id="posCnt">0</em></h3>
            <div class="pos-g2">
                <input type="text" id="posCust" list="posCustList" placeholder="Customer (default: Cash Customer)" autocomplete="off">
                <datalist id="posCustList"></datalist>
                <select id="posOt"></select>
                <input type="text" id="posTbl" placeholder="Table / Token no." style="display:none">
            </div>
            <div class="pos-held" id="posHeld"></div>
            <div class="pos-lines" id="posLines"></div>
            <div class="pos-row"><span>Subtotal</span><b id="posSub"></b></div>
            <div class="pos-row"><span>Discount % / Amount</span><span><input type="number" id="posDp" min="0" max="100" step="0.1" value="0" style="width:64px"> <input type="number" id="posDa" min="0" step="1" value="0"></span></div>
            <div class="pos-row" id="posDelRow" style="display:none"><span>Delivery Charges</span><input type="number" id="posDel" min="0" step="1" value="0"></div>
            <div class="pos-row pos-tot"><span>Total</span><span id="posTot"></span></div>
            <div class="pos-g2" style="margin-top:.5rem">
                <select id="posPay"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option></select>
                <select id="posBank" style="display:none"></select>
                <input type="number" id="posTend" min="0" step="1" placeholder="Cash received (blank = exact)" class="w">
            </div>
            <div class="pos-cash" id="posQuick"></div>
            <div class="pos-row"><span>Change</span><b id="posChange">0</b></div>
            <div class="pos-btns">
                <button type="button" class="pos-btn full" id="posPlace">Save &amp; Print</button>
                <button type="button" class="pos-btn y" id="posHold">Hold</button>
                <button type="button" class="pos-btn g" id="posClear">Clear</button>
            </div>
        </div>
    </div>

    <div class="pos-bills">
        <h3 style="margin:0 0 .8rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">POS Bills
            <span style="display:flex;gap:.5rem;flex-wrap:wrap">
                <input type="date" id="posBf" class="pos-f" style="height:38px">
                <input type="date" id="posBt" class="pos-f" style="height:38px">
                <input type="text" id="posBq" class="pos-f" style="height:38px;width:170px" placeholder="Bill # / customer">
                <button type="button" class="pos-btn" id="posBgo" style="height:38px;width:auto;padding:0 18px">Show</button>
            </span></h3>
        <div style="overflow-x:auto"><table>
            <thead><tr><th>Bill #</th><th>Date</th><th>Customer</th><th class="r">Items</th><th class="r">Total</th><th class="r">Paid</th><th>Actions</th></tr></thead>
            <tbody id="posBills"><tr><td colspan="7" class="pos-empty">Loading...</td></tr></tbody>
        </table></div>
    </div>
</div>

<div class="pos-md" id="posPm"><div class="pos-box" id="posPb"></div></div>
<iframe id="posPrintFrame" style="position:fixed;width:0;height:0;border:0;right:0;bottom:0" title="print"></iframe>

<script>
(function () {
    const TENANT = <?= (int) $tenant_id ?>;
    const API = '../../../server/api/';
    const EP = {
        meta: API + 'dashboard_pos/meta.php', products: API + 'dashboard_pos/products.php', bills: API + 'dashboard_pos/invoices.php',
        save: API + 'sale/pos_invoice/pos-add.php', cats: API + 'inventory/products/category-list.php',
        custs: API + 'financial_reports/customer_ledger/customer-ledger.php?type=customers',
        counters: API + 'sale/pos_invoice/get-counters.php?branch_id=',
        banks: API + 'financial_reports/cash_flow/filter-options.php', grp: API + 'inventory/products/uom-group-units.php?group_id='
    };
    const PRINT_URL = '../sale/pos_invoice/thermal-print.php';
    const EDIT_URL = '../sale/pos_invoice/pos-add.php?edit=';
    const IMG = '../../assets/uploads/products/';
    const MODES = {
        restaurant: {i: '🍔', l: 'Restaurant', ot: ['Dine-in', 'Takeaway', 'Delivery'], chips: ['No Onion', 'No Mayo', 'Extra Cheese', 'Spicy', 'Less Spicy', 'Small', 'Medium', 'Large']},
        bakery: {i: '🥐', l: 'Bakery', ot: ['Walk-in', 'Delivery', 'Pre-order'], chips: ['Message on cake', 'Eggless', 'Less Sugar', 'Gift Pack']},
        karyana: {i: '🛒', l: 'Karyana Store', ot: ['Walk-in', 'Home Delivery'], chips: ['Packed', 'Loose'], weight: 1},
        general: {i: '🏪', l: 'General Store', ot: ['Walk-in', 'Home Delivery'], chips: []},
        medical: {i: '💊', l: 'Medical Store', ot: ['Walk-in', 'Home Delivery'], chips: ['Strip', 'Box', 'Loose'], weight: 1},
        mall: {i: '🏬', l: 'Mall / Retail', ot: ['Walk-in', 'Home Delivery'], chips: ['Size S', 'Size M', 'Size L', 'Size XL', 'Gift Wrap']}
    };
    // Mode is fixed by the company's business type (no switching)
    const BT = '<?= $businessType ?>';
    const mode = MODES[BT] ? BT : 'general';
    const CASH = 'Cash Customer';
    const ACT = {
        save: {l: 'Save Bill (follows the Auto print setting)', k: 'Ctrl+Enter'},
        saveNoPrint: {l: 'Save Bill - without printing', k: 'Ctrl+Shift+Enter'},
        savePrint: {l: 'Save Bill - with printing', k: 'Alt+Enter'},
        search: {l: 'Item search / barcode', k: 'Ctrl+K'},
        customer: {l: 'Customer field', k: 'F3'},
        discount: {l: 'Discount field', k: 'F4'},
        cash: {l: 'Cash received field', k: 'F6'},
        hold: {l: 'Hold bill', k: 'F7'},
        clear: {l: 'Clear bill', k: 'F8'},
        pay: {l: 'Switch payment Cash / Bank', k: 'Alt+B'},
        autoprint: {l: 'Auto print ON / OFF', k: 'Alt+P'},
        reprint: {l: 'Reprint last bill', k: 'Alt+R'},
        keys: {l: 'Open this Shortcuts box', k: 'Alt+K'}
    };
    const K = n => 'pos_' + n + '_' + TENANT;
    const LS = {get: (k, d) => { try { const v = localStorage.getItem(K(k)); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }, set: (k, v) => { try { localStorage.setItem(K(k), JSON.stringify(v)); } catch (e) {} }};
    const DEFKEYS = Object.fromEntries(Object.entries(ACT).map(([a, v]) => [a, v.k]));
    let M = {}, products = [], cats = [], custs = [], banks = [], cat = 'all', cart = [], holds = LS.get('hold', []), busy = false;
    let KEYS = Object.assign({}, DEFKEYS, LS.get('keys', {})), autoPrint = LS.get('autoprint', true), capturing = null, lastId = null;
    const $ = id => document.getElementById(id);
    const setCash = () => { if (M.cash_customer_id) $('posCust').value = CASH; };
    const sym = () => (typeof currencySymbol !== 'undefined' ? currencySymbol : (M.currency_symbol || 'Rs')) + ' ';
    const f = n => sym() + (Math.round(n * 100) / 100).toLocaleString('en-PK');
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const hue = s => [...String(s)].reduce((a, c) => a + c.charCodeAt(0) * 7, 0) % 360;
    const isW = p => (MODES[mode].weight && /^(kg|kilo|gram|gm|g|lb|pound|ltr|litre|liter|meter|mtr)/i.test(p.unit || ''));
    const num = v => parseFloat(v) || 0;
    function toast(m, err) { const t = document.createElement('div'); t.className = 'pos-toast' + (err ? ' err' : ''); t.textContent = m; document.body.appendChild(t); setTimeout(() => t.remove(), err ? 5000 : 2500); }
    async function jget(u) { const r = await fetch(u); return r.json(); }
    async function jpost(u, b) { const r = await fetch(u, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(b)}); return r.json(); }

    // ----- shortcuts + auto print -----
    function hints() {
        const b = $('posApBtn'); b.textContent = '🖨 Auto print: ' + (autoPrint ? 'ON' : 'OFF'); b.style.color = autoPrint ? '#16a34a' : '#dc2626';
        $('posPlace').textContent = (autoPrint ? 'Save & Print' : 'Save') + ' (' + KEYS.save + ')';
        $('posSearch').placeholder = 'Search item / scan barcode (Enter)   [' + KEYS.search + ']';
    }
    function setAp(v) { autoPrint = !!v; LS.set('autoprint', autoPrint); hints(); if ($('posPm').classList.contains('on') && $('posAp')) $('posAp').checked = autoPrint; }
    function combo(e) {
        if (['Control', 'Alt', 'Shift', 'Meta'].includes(e.key)) return '';
        const p = []; if (e.ctrlKey || e.metaKey) p.push('Ctrl'); if (e.altKey) p.push('Alt'); if (e.shiftKey) p.push('Shift');
        p.push(e.key === ' ' ? 'Space' : e.key.length === 1 ? e.key.toUpperCase() : e.key);
        return p.join('+');
    }
    function drawKeys() {
        $('posPb').style.maxWidth = '580px';
        $('posPb').innerHTML = `<h3>⌨ POS Shortcut Keys</h3>
            <label class="pos-row" style="cursor:pointer"><span><b>Automatically print when saving</b><br><small style="color:#94a3b8">When OFF, Save only saves the bill</small></span><input type="checkbox" id="posAp" ${autoPrint ? 'checked' : ''} style="width:20px;height:20px"></label>
            <table class="pos-kt">${Object.keys(ACT).map(a => `<tr><td>${esc(ACT[a].l)}</td><td><button type="button" class="pos-kb ${capturing === a ? 'cap' : ''}" data-k="${a}">${capturing === a ? 'Press keys...' : esc(KEYS[a])}</button></td></tr>`).join('')}</table>
            <p style="font-size:12px;color:#64748b;margin:.7rem 0 0">To change a key, click its button and then press the new key combination (with Ctrl, Alt or an F-key). Press Esc to cancel.</p>
            <div class="pos-btns"><button type="button" class="pos-btn g" id="posKreset">Reset to default</button><button type="button" class="pos-btn" id="posEc">Done</button></div>`;
    }
    function openKeys() { capturing = null; drawKeys(); $('posPm').classList.add('on'); }
    function holdBill() { if (!cart.length) return; holds.push({cart, cust: $('posCust').value}); LS.set('hold', holds); cart = []; resetCart(); }
    function run(a) {
        if ($('posPm').classList.contains('on') && a !== 'keys') return;
        const foc = el => { el.focus(); el.select && el.select(); };
        switch (a) {
            case 'save': place(); break;
            case 'saveNoPrint': place(false); break;
            case 'savePrint': place(true); break;
            case 'search': foc($('posSearch')); break;
            case 'customer': foc($('posCust')); break;
            case 'discount': foc($('posDa')); break;
            case 'cash': foc($('posTend')); break;
            case 'hold': holdBill(); break;
            case 'clear': resetCart(); break;
            case 'pay': $('posPay').value = $('posPay').value === 'cash' ? 'bank_transfer' : 'cash'; $('posPay').dispatchEvent(new Event('change')); break;
            case 'autoprint': setAp(!autoPrint); toast('Auto print ' + (autoPrint ? 'ON' : 'OFF')); break;
            case 'reprint': lastId ? printBill(lastId) : toast('No bill has been saved yet', 1); break;
            case 'keys': $('posPm').classList.contains('on') ? $('posPm').classList.remove('on') : openKeys(); break;
        }
    }

    // Small text tag only (no big cards / icons) - shows the company's business type
    function drawTop() {
        $('posTop').innerHTML = `<span class="pos-mode-tag">${esc(MODES[mode].l)}</span>`;
        $('posOt').innerHTML = MODES[mode].ot.map(x => `<option>${x}</option>`).join('');
        applyOt();
    }
    function applyOt() {
        const v = $('posOt').value;
        $('posTbl').style.display = /dine/i.test(v) ? '' : 'none';
        $('posDelRow').style.display = /deliver/i.test(v) ? '' : 'none';
        calc();
    }
    function drawCats() {
        const used = new Set(products.map(p => p.category_id));
        const list = [{id: 'all', category_name: 'All'}].concat(cats.filter(c => used.has(c.id)));
        if (products.some(p => !p.category_id)) list.push({id: 'none', category_name: 'Other'});
        $('posCats').innerHTML = list.map(c => `<button type="button" class="pos-ct ${String(c.id) === String(cat) ? 'on' : ''}" data-c="${c.id}">${esc(c.category_name)}</button>`).join('');
    }
    function filtered() {
        const s = $('posSearch').value.trim().toLowerCase();
        return products.filter(p => (cat === 'all' || (cat === 'none' ? !p.category_id : String(p.category_id) === String(cat))) &&
            (!s || (p.name || '').toLowerCase().includes(s) || (p.code || '').toLowerCase().includes(s) || (p.barcode || '').toLowerCase().includes(s)));
    }
    function drawItems() {
        const list = filtered(), shown = list.slice(0, 150);
        $('posGrid').innerHTML = shown.map(p => `
            <div class="pos-it" data-p="${p.id}">
                ${p.stock !== null ? `<span class="pos-stk ${p.stock <= 0 ? 'low' : ''}">${+p.stock.toFixed(2)}</span>` : ''}
                <div class="pos-im" style="--h:${hue(p.name)}">${p.photo ? `<img loading="lazy" src="${IMG + encodeURI(p.photo)}" alt="" data-i="${esc((p.name || '?').charAt(0).toUpperCase())}">` : esc((p.name || '?').charAt(0).toUpperCase())}</div>
                <div class="pos-in"><b>${esc(p.name)}</b><span>${f(p.price)}</span> <small>${p.unit ? '/ ' + esc(p.unit) : ''}</small></div></div>`).join('')
            + (list.length > 150 ? '<div class="pos-empty" style="grid-column:1/-1">Use search to find items (' + list.length + ' items)</div>' : '')
            || '<div class="pos-empty" style="grid-column:1/-1">No items found</div>';
    }

    // ----- cart -----
    function addItem(p, qty, note, price) {
        qty = qty || 1; note = note || ''; price = price == null ? p.price : price;
        const l = cart.find(x => x.pid === p.id && x.note === note && x.price === price);
        if (l) l.qty += qty; else cart.push({pid: p.id, name: p.name, price, qty, unit: p.unit, unitId: p.unit_id, grp: p.uom_group_id, sa: p.stock_affects, note, w: isW(p)});
        drawCart();
    }
    function pick(p) { if (isW(p)) return openEdit(null, p); addItem(p, 1); }
    function discount() {
        const sub = cart.reduce((a, l) => a + l.price * l.qty, 0);
        return {sub, d: Math.min(sub, num($('posDa').value))};
    }
    function calc() {
        const {sub, d} = discount(), del = $('posDelRow').style.display === 'none' ? 0 : num($('posDel').value), tot = sub - d + del;
        $('posSub').textContent = f(sub); $('posTot').textContent = f(tot); $('posCnt').textContent = cart.length;
        const t = $('posTend').value === '' ? tot : num($('posTend').value);
        $('posChange').textContent = f(Math.max(0, t - tot));
        const base = Math.ceil(tot / 100) * 100;
$('posQuick').innerHTML = tot > 0 ? `<button type="button" data-t="${tot}">Exact</button>` : '';
        return {sub, d, del, tot};
    }
    function drawCart() {
        $('posLines').innerHTML = cart.length ? cart.map((l, x) => `
            <div class="pos-ln"><div class="n">${esc(l.name)}<small>${l.note ? esc(l.note) + ' · ' : ''}${f(l.price)}${l.unit ? ' / ' + esc(l.unit) : ''}</small></div>
                <div class="pos-q"><button type="button" data-x="${x}" data-d="-1">−</button><b>${+l.qty.toFixed(3)}</b><button type="button" data-x="${x}" data-d="1">+</button></div>
                <b>${f(l.price * l.qty)}</b><button type="button" class="pos-ic" data-e="${x}" title="Qty / Price / Note">✎</button><button type="button" class="pos-ic x" data-r="${x}">×</button></div>`).join('')
            : '<div class="pos-empty">Click an item or scan a barcode</div>';
        $('posHeld').innerHTML = holds.map((h, x) => `<button type="button" data-h="${x}">⏸ #${x + 1} · ${h.cart.length} items</button>`).join('');
        calc();
    }
    function openEdit(idx, prod) {
        const l = idx == null ? null : cart[idx], p = l ? null : prod, w = l ? l.w : isW(p);
        const name = l ? l.name : p.name, price = l ? l.price : p.price, qty = l ? l.qty : 1, note = l ? l.note : '';
        $('posPb').style.maxWidth = '';
        $('posPb').innerHTML = `<h3>${esc(name)}</h3>
            <label class="t">${w ? 'Weight / Qty' + (l ? l.unit ? ' (' + esc(l.unit) + ')' : '' : p.unit ? ' (' + esc(p.unit) + ')' : '') : 'Qty'}</label><input class="f" id="posEq" type="number" min="0.001" step="${w ? 0.25 : 1}" value="${qty}">
            <label class="t">Price</label><input class="f" id="posEp" type="number" min="0" step="0.01" value="${price}">
            <label class="t">Note</label><input class="f" id="posEn" value="${esc(note)}" placeholder="optional">
            <div class="pos-chips">${MODES[mode].chips.map(c => `<button type="button" data-chip="${esc(c)}">${esc(c)}</button>`).join('')}</div>
            <div class="pos-btns"><button type="button" class="pos-btn full" id="posEok" data-i="${idx == null ? '' : idx}" data-pid="${p ? p.id : ''}">${l ? 'Update' : 'Add to Bill'}</button><button type="button" class="pos-btn g full" id="posEc">Cancel</button></div>`;
        $('posPm').classList.add('on'); setTimeout(() => $('posEq').select(), 50);
    }
    function saveEdit(btn) {
        const q = num($('posEq').value), pr = num($('posEp').value), nt = $('posEn').value.trim();
        if (q <= 0) return toast('Enter a valid quantity', 1);
        if (btn.dataset.i !== '') { const l = cart[+btn.dataset.i]; l.qty = q; l.price = pr; l.note = nt; }
        else addItem(products.find(p => p.id === +btn.dataset.pid), q, nt, pr);
        $('posPm').classList.remove('on'); drawCart();
    }
    function resetCart() { cart = []; $('posDp').value = 0; $('posDa').value = 0; $('posDel').value = 0; $('posTend').value = ''; $('posTbl').value = ''; $('posCust').value = M.cash_customer_id ? CASH : ''; drawCart(); }

    // ----- save -----
    function custId() {
        const v = $('posCust').value.trim(); if (!v || v.toLowerCase() === CASH.toLowerCase()) return M.cash_customer_id;
        const c = custs.find(c => (c.customer_code ? c.customer_code + ' - ' : '') + c.customer_name === v || c.customer_name === v);
        return c ? c.id : null;
    }
    async function place(pr) {
        if (busy) return;
        if (!cart.length) return toast('The bill is empty', 1);
        const cid = custId(), branch = +$('posBranch').value;
        if (!cid) return toast($('posCust').value.trim() ? 'Customer not found in the list' : 'Cash Customer not found - please select a customer', 1);
        if (!branch) return toast('Please select a branch', 1);
        const {sub, d, del, tot} = calc(), tend = $('posTend').value === '' ? tot : num($('posTend').value);
        const isCash = cid === M.cash_customer_id;
        if (tend < tot && isCash) return toast('Please select a customer for credit sales', 1);
        const bank = $('posPay').value === 'bank_transfer' ? $('posBank').value : null;
        if ($('posPay').value === 'bank_transfer' && !bank) return toast('Please select a bank account', 1);
        const doPrint = pr === undefined ? autoPrint : !!pr;
        busy = true; $('posPlace').disabled = true;
        try {
            for (const l of cart) if (!l.unitId && l.grp) {
                const r = await jget(EP.grp + l.grp), u = (r.units || []).find(u => u.is_base_unit == 1) || (r.units || [])[0];
                if (u) l.unitId = +u.id;
            }
            const bad = cart.find(l => !l.unitId); if (bad) throw new Error(bad.name + ': no unit set (set a unit on the product page)');
            const ot = $('posOt').value, tbl = $('posTbl').value.trim();
            const remarks = [ot + (tbl ? ' #' + tbl : '')].concat(cart.filter(l => l.note).map(l => `${+l.qty.toFixed(2)}x ${l.name}: ${l.note}`)).join(' | ');
            const body = {
                saleDate: new Date().toLocaleDateString('en-CA'), customerId: cid, branchId: branch, companyId: M.company_id, currencyId: M.currency_id, counterId: $('posCounter').value || null,
                invoiceType: tend >= tot ? 'Cash' : 'Credit', status: 'Posted', remarks, amountPaidAutoFill: 'no',
                totalBill: sub, totalDiscountPercent: num($('posDp').value), totalDiscountAmount: d, shippingFees: del, netAmount: tot,
                amountPaid: tend, amountReturned: Math.max(0, tend - tot), paymentMethod: $('posPay').value, bankAccountId: bank,
                items: cart.map(l => ({productId: l.pid, uomId: l.unitId, quantity: l.qty, salePrice: l.price, grossAmount: l.price * l.qty, discountPercent: 0, discountAmount: 0,
                    extraDiscountAmount: 0, tradeOfferPercent: 0, tradeOfferAmount: 0, taxPercent: 0, taxAmount: 0, focQty: 0, netAmount: l.price * l.qty, scheme: 'sale_on_tp', stockAffects: l.sa}))
            };
            const r = await jpost(EP.save, body);
            if (!r.success) throw new Error(r.message || 'Save failed');
            lastId = r.invoice_id;
            toast(doPrint ? 'Bill saved ✔ (printing)' : 'Bill saved ✔');
            if (doPrint) printBill(r.invoice_id);
            resetCart(); loadBills(); loadProducts();
        } catch (e) { toast(e.message, 1); }
        busy = false; $('posPlace').disabled = false;
    }
    function printBill(id) { $('posPrintFrame').src = PRINT_URL + '?id=' + id + '&autoprint=1&_=' + Date.now(); }

    // ----- bills list -----
    async function loadBills() {
        const q = new URLSearchParams({date_from: $('posBf').value, date_to: $('posBt').value, q: $('posBq').value.trim()});
        try {
            const r = await jget(EP.bills + '?' + q);
            if (!r.success) throw new Error(r.message);
            $('posBills').innerHTML = r.data.length ? r.data.map(b => `<tr><td><b>${esc(b.bill_no)}</b></td><td>${esc(b.sale_date)}</td><td>${esc(b.customer_name || '-')}</td><td class="r">${b.item_count}</td><td class="r">${f(+b.net_amount)}</td><td class="r">${f(+b.amount_paid)}</td>
                <td class="a"><button data-pr="${b.id}">Print</button><button data-ed="${b.id}">Edit</button>${b.can_delete ? `<button class="d" data-dl="${b.id}" data-bn="${esc(b.bill_no)}">Delete</button>` : ''}</td></tr>`).join('')
                : '<tr><td colspan="7" class="pos-empty">No bills for this date</td></tr>';
        } catch (e) { $('posBills').innerHTML = `<tr><td colspan="7" class="pos-empty" style="color:#dc2626">${esc(e.message)}</td></tr>`; }
    }
    async function delBill(id, bn) {
        if (!confirm('Delete bill ' + bn + '?\nStock and accounts will be reversed.')) return;
        const r = await jpost(EP.bills, {id});
        toast(r.message || (r.success ? 'Deleted' : 'Failed'), !r.success);
        if (r.success) { loadBills(); loadProducts(); }
    }

    // ----- counters -----
    async function loadCounters(branchId) {
        const sel = $('posCounter');
        sel.innerHTML = '<option value="">-- Select Counter --</option>';
        if (!branchId) return;
        try {
            const r = await jget(EP.counters + branchId);
            if (r.success && (r.counters || []).length) {
                sel.innerHTML += r.counters.map(c => `<option value="${c.id}">${esc(c.counter_name)}</option>`).join('');
                const saved = LS.get('counter_' + branchId, null);
                if (saved && r.counters.some(c => c.id == saved)) sel.value = saved;
            }
        } catch (e) { /* counters are optional */ }
    }

    // ----- load -----
    async function loadProducts() {
        try { const r = await jget(EP.products + '?branch_id=' + (+$('posBranch').value || 0)); if (!r.success) throw new Error(r.message); products = r.products; drawCats(); drawItems(); }
        catch (e) { $('posGrid').innerHTML = `<div class="pos-empty" style="grid-column:1/-1;color:#dc2626">${esc(e.message)}</div>`; }
    }
    async function init() {
        const today = new Date().toLocaleDateString('en-CA'); $('posBf').value = today; $('posBt').value = today;
        drawTop(); drawCart(); hints();
        try {
            M = await jget(EP.meta); if (!M.success) throw new Error(M.message);
            $('posBranch').innerHTML = M.branches.map(b => `<option value="${b.id}">${esc(b.branch_name)}</option>`).join('');
            const sb = LS.get('branch', null); if (sb && M.branches.some(b => b.id == sb)) $('posBranch').value = sb;
            setCash();
        } catch (e) { return toast('POS failed to load: ' + e.message, 1); }
        loadCounters(+$('posBranch').value); loadProducts(); loadBills();
        jget(EP.cats).then(r => { cats = r.categories || []; drawCats(); }).catch(() => {});
        jget(EP.custs).then(r => {
            custs = r.data || [];
            $('posCustList').innerHTML = custs.map(c => `<option value="${esc((c.customer_code ? c.customer_code + ' - ' : '') + c.customer_name)}">`).join('');
            // Fallback: meta.php did not find Cash Customer, but the customers list has it
            if (!M.cash_customer_id) {
                const cc = custs.find(c => (c.customer_name || '').trim().toLowerCase() === CASH.toLowerCase());
                if (cc) M.cash_customer_id = +cc.id;
            }
            if (!M.cash_customer_id) toast('No customer named "Cash Customer" was found - you must select a customer for each bill', 1);
            if (!$('posCust').value.trim()) setCash();
        }).catch(() => { if (!M.cash_customer_id) toast('No customer named "Cash Customer" was found - you must select a customer for each bill', 1); });
        jget(EP.banks).then(r => { banks = r.bank_accounts || []; $('posBank').innerHTML = '<option value="">Bank account...</option>' + banks.map(b => `<option value="${b.id}">${esc(b.bank_name)} - ${esc(b.account_title || '')}</option>`).join(''); }).catch(() => {});
    }

    document.addEventListener('error', e => {
        const im = e.target; if (im.tagName === 'IMG' && im.closest('.pos-im')) im.replaceWith(document.createTextNode(im.dataset.i || '?'));
    }, true);
    document.addEventListener('click', e => {
        const t = e.target, el = t.closest('[data-c],[data-p],[data-x],[data-e],[data-r],[data-h],[data-t],[data-chip],[data-pr],[data-ed],[data-dl],[data-k]');
        if (el) {
            const d = el.dataset;
            if (d.c) { cat = d.c; drawCats(); drawItems(); }
            else if (d.p) pick(products.find(p => p.id === +d.p));
            else if (d.x !== undefined) { const l = cart[+d.x], st = l.w ? 0.5 : 1; l.qty += +d.d * st; if (l.qty <= 0) cart.splice(+d.x, 1); drawCart(); }
            else if (d.e !== undefined) openEdit(+d.e);
            else if (d.r !== undefined) { cart.splice(+d.r, 1); drawCart(); }
            else if (d.h !== undefined) { const h = holds.splice(+d.h, 1)[0]; LS.set('hold', holds); cart = h.cart; $('posCust').value = h.cust || (M.cash_customer_id ? CASH : ''); drawCart(); }
            else if (d.t !== undefined) { $('posTend').value = d.t; calc(); }
            else if (d.chip) { const n = $('posEn'); n.value = n.value ? n.value + ', ' + d.chip : d.chip; }
            else if (d.pr) printBill(d.pr);
            else if (d.ed) window.open(EDIT_URL + d.ed, '_blank');
            else if (d.dl) delBill(+d.dl, d.bn);
            else if (d.k) { capturing = d.k; drawKeys(); }
            return;
        }
        if (t.id === 'posEok') saveEdit(t);
        else if (t.id === 'posEc' || t === $('posPm')) { capturing = null; $('posPm').classList.remove('on'); }
        else if (t.id === 'posPlace') place();
        else if (t.id === 'posClear') resetCart();
        else if (t.id === 'posBgo') loadBills();
        else if (t.id === 'posHold') holdBill();
        else if (t.id === 'posApBtn') setAp(!autoPrint);
        else if (t.id === 'posKeysBtn') openKeys();
        else if (t.id === 'posKreset') { KEYS = Object.assign({}, DEFKEYS); LS.set('keys', KEYS); capturing = null; drawKeys(); hints(); }
    });
    document.addEventListener('change', e => { if (e.target.id === 'posAp') setAp(e.target.checked); });
    $('posSearch').addEventListener('input', drawItems);
    $('posSearch').addEventListener('keydown', e => {
        if (e.key !== 'Enter') return; e.preventDefault();
        const v = e.target.value.trim().toLowerCase(); if (!v) return;
        const hit = products.find(p => (p.barcode || '').toLowerCase() === v || (p.code || '').toLowerCase() === v) || (filtered().length === 1 ? filtered()[0] : null);
        if (hit) { pick(hit); e.target.value = ''; drawItems(); } else toast('Item not found', 1);
    });
    ['posDp'].forEach(id => $(id).addEventListener('input', () => { $('posDa').value = Math.round(discount().sub * num($('posDp').value)) / 100; calc(); }));
    $('posDa').addEventListener('input', () => { const s = discount().sub; $('posDp').value = s ? Math.round(num($('posDa').value) / s * 10000) / 100 : 0; calc(); });
    $('posDel').addEventListener('input', calc); $('posTend').addEventListener('input', calc); $('posOt').addEventListener('change', applyOt);
    $('posPay').addEventListener('change', () => { $('posBank').style.display = $('posPay').value === 'bank_transfer' ? '' : 'none'; });
    $('posBranch').addEventListener('change', () => { LS.set('branch', $('posBranch').value); loadCounters(+$('posBranch').value); loadProducts(); });
    $('posCounter').addEventListener('change', () => { if ($('posBranch').value) LS.set('counter_' + $('posBranch').value, $('posCounter').value); });
    // Customer field: select text on focus (typing replaces "Cash Customer"), restore Cash Customer if left empty
    $('posCust').addEventListener('focus', e => e.target.select());
    $('posCust').addEventListener('blur', e => { if (!e.target.value.trim()) setCash(); });

    document.addEventListener('keydown', e => {
        const open = $('posPm').classList.contains('on');
        if (capturing && open) {
            e.preventDefault(); e.stopPropagation();
            if (e.key === 'Escape') { capturing = null; drawKeys(); return; }
            const c = combo(e); if (!c) return;
            if (!/^(Ctrl|Alt)\+/.test(c) && !/(^|\+)F\d{1,2}$/.test(c)) return toast('Use a combination with Ctrl, Alt or an F-key', 1);
            const dup = Object.keys(KEYS).find(a => a !== capturing && KEYS[a] === c);
            if (dup) return toast('This key is already used for "' + ACT[dup].l + '"', 1);
            KEYS[capturing] = c; LS.set('keys', KEYS); capturing = null; drawKeys(); hints(); return;
        }
        if (e.key === 'Escape') { $('posPm').classList.remove('on'); return; }
        const c = combo(e); if (!c) return;
        const a = Object.keys(KEYS).find(x => KEYS[x] === c);
        if (a) { e.preventDefault(); run(a); }
    }, true);
    document.addEventListener('DOMContentLoaded', init);
})();
</script>
<?php // ===== /POS Business Section ===== ?>
