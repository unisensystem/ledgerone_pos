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
.pos-bcb{position:absolute;top:8px;left:8px;z-index:2;width:30px;height:26px;display:flex;align-items:center;justify-content:center;border:1px solid #d6dbe4;border-radius:7px;background:rgba(255,255,255,.95);color:#334155;cursor:pointer;padding:0;box-shadow:0 1px 4px rgba(0,0,0,.15)}
.pos-bcb:hover{border-color:#1f7bff;color:#1f7bff}
.pos-bcs{border:1.5px solid #e2e8f0;border-radius:.6rem;padding:.7rem;margin-top:.7rem;text-align:center}
.pos-bcs b{display:block;font-size:13px;color:#0f172a}
.pos-bcs svg{width:100%;height:80px;margin:.4rem 0 .2rem;display:block;background:#fff}
.pos-bcsel{display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:14px;color:#334155;margin-top:.6rem}
.pos-bcs .pos-bcsel{margin-top:0;justify-content:center}
.pos-bcsel input{width:18px;height:18px}
.pos-bcs small{display:block;color:#475569;letter-spacing:1px;margin-bottom:.5rem}
.pos-cart{background:#fff;border:2px solid #e2e8f0;border-radius:.9rem;padding:1.1rem}
.pos-cart h3{margin:0 0 .6rem;font-size:1.15rem;color:#0f172a;display:flex;justify-content:space-between;align-items:center}
.pos-cart h3 em{font-style:normal;font-size:12px;background:#1f7bff;color:#fff;border-radius:9999px;padding:2px 10px}
.pos-g2{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.5rem}
.pos-g2 .w{grid-column:1/-1}
.pos-g2 input,.pos-g2 select{height:40px;padding:0 .7rem;border:1.5px solid #d6dbe4;border-radius:.5rem;font-size:13.5px;background:#fff;color:#1e293b;width:100%;min-width:0}
.pos-held{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.5rem}
.pos-held button{border:1.5px dashed #e8b23f;background:#fff8e8;color:#92400e;border-radius:.5rem;padding:4px 10px;font-size:12px;font-weight:600;cursor:pointer}
.pos-lines{max-height:340px;overflow-y:auto;min-height:90px}
.pos-ln{display:grid;grid-template-columns:1fr auto auto auto;gap:.5rem .45rem;align-items:center;padding:.7rem 0;border-bottom:1px solid #f1f5f9;font-size:14px;color:#334155}
.pos-ln .n{min-width:0;font-weight:600;color:#0f172a;word-break:break-word}.pos-ln .n small{display:block;color:#94a3b8;font-size:12px;font-weight:400;margin-top:2px}
.pos-ln .pos-line-total{white-space:nowrap;text-align:right}
.pos-ln .pos-unit{grid-column:1;width:100%;max-width:none;min-width:0}
.pos-ln .pos-q{grid-column:2/-1;justify-self:end}
.pos-q{display:flex;align-items:center;gap:6px}
.pos-q input{width:64px;height:30px;padding:0 4px;border:1px solid #d6dbe4;border-radius:6px;text-align:center}
.pos-unit{max-width:155px;height:32px;padding:0 5px;border:1px solid #d6dbe4;border-radius:6px;background:#fff;color:#334155}
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
.pos-box input.f,.pos-box select.f{width:100%;height:44px;padding:0 .8rem;border:1.5px solid #d6dbe4;border-radius:.5rem;font-size:15px;margin-top:.3rem;background:#fff;color:#1e293b}
.pos-ug{display:grid;gap:.5rem}
.pos-et{margin-top:.8rem;padding:.6rem .8rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.5rem;font-size:14px;color:#166534;font-weight:600}
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
        banks: API + 'financial_reports/cash_flow/filter-options.php', grp: API + 'inventory/products/uom-group-units.php?group_id=',
        conv: API + 'inventory/products/product-uom-conversions.php?product_id='
    };
    const PRINT_URL = '../sale/pos_invoice/thermal-print.php';
    const EDIT_URL = '../sale/pos_invoice/pos-add.php?edit=';
    const IMG = '../../assets/uploads/products/';
    const MODES = {
        restaurant: {i: '🍔', l: 'Restaurant', ot: ['Dine-in', 'Takeaway', 'Delivery'], chips: ['No Onion', 'No Mayo', 'Extra Cheese', 'Spicy', 'Less Spicy', 'Small', 'Medium', 'Large']},
        bakery: {i: '🥐', l: 'Bakery', ot: ['Walk-in', 'Delivery', 'Pre-order'], chips: ['Message on cake', 'Eggless', 'Less Sugar', 'Gift Pack']},
        karyana: {i: '🛒', l: 'Karyana Store', ot: ['Walk-in', 'Home Delivery'], chips: ['Packed', 'Loose']},
        general: {i: '🏪', l: 'General Store', ot: ['Walk-in', 'Home Delivery'], chips: []},
        medical: {i: '💊', l: 'Medical Store', ot: ['Walk-in', 'Home Delivery'], chips: ['Strip', 'Box', 'Loose']},
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
    let M = {}, products = [], cats = [], custs = [], banks = [], cat = 'all', cart = [], holds = LS.get('hold', []), busy = false, ED = null;
    let KEYS = Object.assign({}, DEFKEYS, LS.get('keys', {})), autoPrint = LS.get('autoprint', true), capturing = null, lastId = null;
    const $ = id => document.getElementById(id);
    const setCash = () => { if (M.cash_customer_id) $('posCust').value = CASH; };
    const sym = () => (typeof currencySymbol !== 'undefined' ? currencySymbol : (M.currency_symbol || 'Rs')) + ' ';
    const f = n => sym() + (Math.round(n * 100) / 100).toLocaleString('en-PK');
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const hue = s => [...String(s)].reduce((a, c) => a + c.charCodeAt(0) * 7, 0) % 360;
    const WRX = /^(kg|kilo|gram|gm|g\b|lb|pound|ltr|litre|liter|meter|mtr)/i;
    const baseOf = U => U.find(u => u.base) || U[U.length - 1];
    // Weight/loose item = base unit is Kg/Gram/Ltr/Meter... (works in every business type)
    const isW = p => { const b = p._u && p._u.length ? baseOf(p._u) : null; return WRX.test(b ? b.name : (p.unit || '')); };
    const num = v => parseFloat(v) || 0;
    function toast(m, err) { const t = document.createElement('div'); t.className = 'pos-toast' + (err ? ' err' : ''); t.textContent = m; document.body.appendChild(t); setTimeout(() => t.remove(), err ? 5000 : 2500); }
    async function jget(u) { const r = await fetch(u); return r.json(); }
    async function jpost(u, b) { const r = await fetch(u, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(b)}); return r.json(); }

    // ----- product units: cart quantities are converted to the product base unit at checkout -----
    async function getUnits(p) {
        if (p._u) return p._u;
        let u = [];
        if (p.uom_group_id) {
            const [g, c] = await Promise.all([jget(EP.grp + p.uom_group_id), jget(EP.conv + p.id)]);
            if (!g.success || !c.success) throw new Error(g.message || c.message || 'Could not load product units');
            const cv = {}; (c.conversions || []).forEach(x => cv[x.uom_id] = +x.conversion_factor);
            u = (g.units || []).map(x => ({
                id: +x.id, name: x.uom_name, base: x.is_base_unit == 1,
                factor: x.is_base_unit == 1 ? 1 : (x.unit_scope === 'per_product' ? (cv[x.id] || 0) : (+x.conversion_factor || 0))
            }));
        } else u = p.units || [];
        u = u.filter(x => x.factor > 0);
        if (!u.length && p.unit_id) u = [{id: p.unit_id, name: p.unit || '', factor: 1, base: true}];
        u.sort((a, b) => b.factor - a.factor);
        p._u = u;
        return u;
    }
    function sellingUnits(p) {
        const standard = p._u || [];
        const base = standard.find(u => u.base) || standard[standard.length - 1] || {id: p.unit_id, name: p.unit || '', factor: 1, base: true};
        const options = standard.map(u => ({
            key: 'u' + u.id, id: u.id, name: u.name, factor: +u.factor,
            price: p.price * +u.factor, barcode: +u.id === +p.barcode_unit_id ? (p.barcode || '') : '',
            base: !!u.base, unitId: +u.id, quantityFactor: 1
        }));
        (p.loose_units || []).forEach(pack => {
            const existing = options.find(u =>
                u.name.trim().toLowerCase() === String(pack.label).trim().toLowerCase() &&
                Math.abs(u.factor - +pack.qty_in_base) < 0.000001
            );
            const packPrice = +pack.price > 0 ? +pack.price : p.price * +pack.qty_in_base;
            if (existing) {
                existing.factor = +pack.qty_in_base;
                existing.price = packPrice;
                existing.barcode = pack.barcode || '';
                existing.packId = +pack.id;
            } else {
                options.push({
                    key: 's' + pack.id, id: base.id,
                    name: options.some(u => u.name.trim().toLowerCase() === String(pack.label).trim().toLowerCase()) ? pack.label + ' (custom)' : pack.label,
                    factor: +pack.qty_in_base,
                    price: packPrice, barcode: pack.barcode || '', packId: +pack.id,
                    unitId: base.id, quantityFactor: +pack.qty_in_base
                });
            }
        });
        const biggest = options.filter(u => u.factor > 1).sort((a, b) => b.factor - a.factor)[0];
        const baseOption = options.find(u => u.base && +u.factor === 1) || options.find(u => +u.id === +base.id && +u.factor === 1);
        if (biggest && baseOption) {
            options.push({
                key: 'mixed', id: baseOption.id, name: biggest.name + ' + ' + baseOption.name,
                factor: 1, price: p.price, barcode: '', base: true, mixed: true,
                components: [biggest, baseOption]
            });
        }
        p._sellUnits = options;
        return options;
    }
    function defaultSellingUnit(p) {
        const options = sellingUnits(p);
        return options.find(u => +u.id === +p.barcode_unit_id && !u.mixed) ||
            options.find(u => u.base && !u.mixed) || options[0];
    }
    function displayUnits(options) {
        const mixed = options.find(u => u.mixed);
        const components = mixed ? mixed.components : options.filter(u => !u.mixed);
        return components.filter(Boolean).map(u => ({
            name: u.name, factor: u.factor, base: !!u.base
        })).sort((a, b) => b.factor - a.factor);
    }
    // split a base qty into [n of biggest unit, ..., rest in smallest unit]
    function bd(U, q) {
        const r = []; let rem = Math.round(q * 10000) / 10000;
        U.forEach((u, i) => {
            if (i === U.length - 1) r.push(Math.round(rem / u.factor * 1000) / 1000);
            else { const n = Math.floor(rem / u.factor + 1e-9); r.push(n); rem = Math.round((rem - n * u.factor) * 10000) / 10000; }
        });
        return r;
    }
    function fmtQty(l) {
        const U = l.units || [];
        if (U.length < 2) return (+l.qty.toFixed(3)) + '';
        const b = bd(U, l.qty);
        return U.map((u, i) => b[i] ? b[i] + ' ' + u.name : '').filter(Boolean).join(' + ') || '0';
    }

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
    function openKeys() { capturing = null; ED = null; drawKeys(); $('posPm').classList.add('on'); }
    function holdBill() { if (!cart.length) return; holds.push({cart, cust: $('posCust').value}); LS.set('hold', holds); cart = []; resetCart(); }
    async function restoreHold(index) {
        const held = holds[index];
        if (!held) return;
        try {
            const restored = [];
            for (const oldLine of held.cart || []) {
                if (oldLine.selectedUnit) {
                    restored.push(oldLine);
                    continue;
                }
                const product = products.find(p => +p.id === +oldLine.pid);
                if (!product) throw new Error(`${oldLine.name}: product is no longer available`);
                await getUnits(product);
                const options = sellingUnits(product);
                let unit, qty = +oldLine.qty;
                if (oldLine.looseUnit) {
                    unit = options.find(option => +option.packId === +oldLine.looseUnit.id) ||
                        options.find(option => option.name === oldLine.looseUnit.label);
                    if (!unit) throw new Error(`${oldLine.name}: saved pack is no longer configured`);
                } else {
                    unit = options.find(option => option.base && !option.mixed) || options[0];
                    if (unit && oldLine.price != null) unit = {...unit, price: +oldLine.price};
                }
                if (!unit) throw new Error(`${oldLine.name}: no selling unit is configured`);
                const base = product._u.length ? baseOf(product._u) : {id: product.unit_id};
                if (!base || !base.id) throw new Error(`${oldLine.name}: no base unit is configured`);
                restored.push({
                    pid: product.id, name: product.name, qty, selectedUnit: {...unit}, sellingUnits: options,
                    unitId: base.id, units: displayUnits(options), grp: product.uom_group_id,
                    sa: product.stock_affects, note: oldLine.note || '', w: isW(product)
                });
            }
            cart = restored;
            $('posCust').value = held.cust || (M.cash_customer_id ? CASH : '');
            holds.splice(index, 1);
            LS.set('hold', holds);
            drawCart();
        } catch (e) {
            toast(e.message || 'Could not restore held bill', 1);
        }
    }
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
            (!s || (p.name || '').toLowerCase().includes(s) || (p.code || '').toLowerCase().includes(s) ||
                (p.barcode || '').toLowerCase().includes(s) ||
                (p.loose_units || []).some(u => (u.barcode || '').toLowerCase().includes(s) || (u.label || '').toLowerCase().includes(s)) ||
                (p.units || []).some(u => (u.name || '').toLowerCase().includes(s))));
    }
    function drawItems() {
        const list = filtered(), shown = list.slice(0, 150);
        $('posGrid').innerHTML = shown.map(p => `
            <div class="pos-it" data-p="${p.id}">
                <button type="button" class="pos-bcb" data-bc="${p.id}" title="Barcode"><svg width="16" height="14" viewBox="0 0 16 14" fill="currentColor" aria-hidden="true"><rect x="0" width="2" height="14"/><rect x="3" width="1" height="14"/><rect x="5" width="3" height="14"/><rect x="9" width="1" height="14"/><rect x="11" width="2" height="14"/><rect x="14" width="2" height="14"/></svg></button>
                ${p.stock !== null ? `<span class="pos-stk ${p.stock <= 0 ? 'low' : ''}">${+p.stock.toFixed(2)}</span>` : ''}
                <div class="pos-im" style="--h:${hue(p.name)}">${p.photo ? `<img loading="lazy" src="${IMG + encodeURI(p.photo)}" alt="" data-i="${esc((p.name || '?').charAt(0).toUpperCase())}">` : esc((p.name || '?').charAt(0).toUpperCase())}</div>
                <div class="pos-in"><b>${esc(p.name)}</b><span>${f(p.price)}</span> <small>${p.unit ? '/ ' + esc(p.unit) : ''}</small></div></div>`).join('')
            + (list.length > 150 ? '<div class="pos-empty" style="grid-column:1/-1">Use search to find items (' + list.length + ' items)</div>' : '')
            || '<div class="pos-empty" style="grid-column:1/-1">No items found</div>';
    }

    function lineTotal(l) {
        if (!l.selectedUnit.mixed) return l.selectedUnit.price * l.qty;
        const baseQty = lineQuantity(l), [pack, base] = l.selectedUnit.components;
        const packQty = Math.floor(baseQty / pack.factor + 1e-9);
        const remainingQty = Math.round((baseQty - packQty * pack.factor) * 10000) / 10000;
        return packQty * pack.price + remainingQty * base.price;
    }
    function lineQuantity(l) { return l.qty * l.selectedUnit.factor; }
    function invoiceItems(l) {
        const itemForUnit = (unit, quantity) => {
            const grossAmount = quantity * unit.price;
            const quantityFactor = unit.quantityFactor || 1;
            return {productId: l.pid, uomId: unit.unitId || l.unitId, quantity: quantity * quantityFactor, salePrice: unit.price / quantityFactor, grossAmount, discountPercent: 0, discountAmount: 0,
                extraDiscountAmount: 0, tradeOfferPercent: 0, tradeOfferAmount: 0, taxPercent: 0, taxAmount: 0, focQty: 0, netAmount: grossAmount,
                scheme: 'sale_on_tp', stockAffects: l.sa};
        };
        if (!l.selectedUnit.mixed) return [itemForUnit(l.selectedUnit, l.qty)];
        const [pack, base] = l.selectedUnit.components;
        const baseQty = lineQuantity(l), packQty = Math.floor(baseQty / pack.factor + 1e-9);
        const remainingQty = Math.round((baseQty - packQty * pack.factor) * 10000) / 10000;
        return [
            ...(packQty > 0 ? [itemForUnit(pack, packQty)] : []),
            ...(remainingQty > 0 ? [itemForUnit(base, remainingQty)] : [])
        ];
    }
    function displayQuantity(l) {
        return l.selectedUnit.mixed ? fmtQty({units: l.units, qty: lineQuantity(l)}) :
            (+l.qty.toFixed(3)) + ' ' + l.selectedUnit.name;
    }
    // ----- cart lines keep the selected selling unit; stock is sent in base units -----
    function addItem(p, unit, qty) {
        unit = unit || defaultSellingUnit(p);
        const U = p._u || [], b = U.length ? baseOf(U) : {id: p.unit_id, name: p.unit};
        if (!b || !b.id) {
            toast(p.name + ': configure a base unit before adding it', 1);
            return;
        }
        if (!unit || !unit.id || !(unit.factor > 0)) {
            toast(p.name + ': configure a selling unit before adding it', 1);
            return;
        }
        qty = qty || 1;
        const options = sellingUnits(p);
        const l = cart.find(x => x.pid === p.id && x.selectedUnit.key === unit.key && !x.note);
        if (l) l.qty = Math.round((l.qty + qty) * 10000) / 10000;
        else cart.push({
            pid: p.id, name: p.name, qty, selectedUnit: {...unit}, sellingUnits: options,
            unitId: b.id, units: displayUnits(options), grp: p.uom_group_id, sa: p.stock_affects, note: '', w: isW(p)
        });
        drawCart();
    }
    async function pick(p, unit) {
        try {
            await getUnits(p);
            sellingUnits(p);
            addItem(p, unit || defaultSellingUnit(p), 1);
        } catch (e) {
            toast(e.message || 'Could not load product units', 1);
        }
    }
    function discount() {
        const sub = cart.reduce((a, l) => a + lineTotal(l), 0);
        return {sub, d: Math.min(sub, num($('posDa').value))};
    }
    function calc() {
        const {sub, d} = discount(), del = $('posDelRow').style.display === 'none' ? 0 : num($('posDel').value), tot = sub - d + del;
        $('posSub').textContent = f(sub); $('posTot').textContent = f(tot); $('posCnt').textContent = cart.length;
        const t = $('posTend').value === '' ? tot : num($('posTend').value);
        $('posChange').textContent = f(Math.max(0, t - tot));
        $('posQuick').innerHTML = tot > 0 ? `<button type="button" data-t="${tot}">Exact</button>` : '';
        return {sub, d, del, tot};
    }
    function drawCart() {
        $('posLines').innerHTML = cart.length ? cart.map((l, x) => `
            <div class="pos-ln"><div class="n">${esc(l.name)}<small>${l.note ? esc(l.note) + ' · ' : ''}${f(l.selectedUnit.price)} / ${esc(l.selectedUnit.name)}</small></div>
                <b class="pos-line-total">${f(lineTotal(l))}</b><button type="button" class="pos-ic" data-e="${x}" title="Edit price / note">✎</button><button type="button" class="pos-ic x" data-r="${x}" title="Remove item">×</button>
                <select class="pos-unit" data-unit="${x}" aria-label="Selling unit for ${esc(l.name)}">${l.sellingUnits.map(u => `<option value="${esc(u.key)}" ${u.key === l.selectedUnit.key ? 'selected' : ''}>${esc(u.name)}${u.mixed ? '' : ' - ' + esc(f(u.price))}</option>`).join('')}</select>
                <div class="pos-q"><button type="button" data-x="${x}" data-d="-1">−</button><input type="number" min="0" step="any" value="${l.qty}" data-qty="${x}" aria-label="Quantity of ${esc(l.name)}">${l.selectedUnit.mixed ? `<small class="pos-mixedqty">${esc(displayQuantity(l))}</small>` : ''}<button type="button" data-x="${x}" data-d="1">+</button></div></div>`).join('')
            : '<div class="pos-empty">Click an item or scan a barcode</div>';
        $('posHeld').innerHTML = holds.map((h, x) => `<button type="button" data-h="${x}">⏸ #${x + 1} · ${h.cart.length} items</button>`).join('');
        calc();
    }

    // ----- optional line editor for price and kitchen/order notes -----
    function openEdit(idx) {
        const l = cart[idx];
        ED = {idx};
        $('posPb').style.maxWidth = '';
        $('posPb').innerHTML = `<h3>${esc(l.name)} - ${esc(l.selectedUnit.name)}</h3>
            <label class="t" for="posEditQty">Quantity (${esc(l.selectedUnit.name)})</label><input class="f" id="posEditQty" type="number" min="0.001" step="any" value="${l.qty}">
            ${l.w ? `<label class="t" for="posEditAmount">Amount (${esc(sym().trim())})</label><input class="f" id="posEditAmount" type="number" min="0" step="any" placeholder="e.g. 100">` : ''}
            <label class="t" for="posEditPrice">Price / ${esc(l.selectedUnit.name)}</label><input class="f" id="posEditPrice" type="number" min="0" step="0.01" value="${l.selectedUnit.price}">
            <label class="t" for="posEn">Note</label><input class="f" id="posEn" value="${esc(l.note || '')}" placeholder="optional">
            <div class="pos-chips">${MODES[mode].chips.map(c => `<button type="button" data-chip="${esc(c)}">${esc(c)}</button>`).join('')}</div>
            <div class="pos-btns"><button type="button" class="pos-btn full" id="posEditSave">Update</button><button type="button" class="pos-btn g full" id="posEc">Cancel</button></div>`;
        $('posPm').classList.add('on');
        setTimeout(() => { $('posEditQty').focus(); $('posEditQty').select(); }, 50);
    }
    function saveEdit() {
        const l = cart[ED.idx], qty = num($('posEditQty').value), price = num($('posEditPrice').value);
        if (!l || qty <= 0 || price < 0) return toast('Enter a valid quantity and price', 1);
        l.qty = qty;
        l.selectedUnit.price = price;
        l.note = $('posEn').value.trim();
        ED = null; $('posPm').classList.remove('on'); drawCart();
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
            const remarks = [ot + (tbl ? ' #' + tbl : '')].concat(cart.filter(l => l.note || l.selectedUnit.factor !== 1).map(l => {
                const description = l.selectedUnit.mixed ? fmtQty({units: l.units, qty: lineQuantity(l)}) : `${l.qty} ${l.selectedUnit.name}`;
                const note = l.note ? `: ${l.note}` : '';
                const price = l.selectedUnit.mixed ? `${f(lineTotal(l))} total` : `${f(l.selectedUnit.price)} / ${l.selectedUnit.name}`;
                return `${description} ${l.name} @ ${price}${l.selectedUnit.barcode ? ' [Barcode: ' + l.selectedUnit.barcode + ']' : ''}${note}`;
            })).join(' | ');
            const body = {
                saleDate: new Date().toLocaleDateString('en-CA'), customerId: cid, branchId: branch, companyId: M.company_id, currencyId: M.currency_id, counterId: $('posCounter').value || null,
                invoiceType: tend >= tot ? 'Cash' : 'Credit', status: 'Posted', remarks, amountPaidAutoFill: 'no',
                totalBill: sub, totalDiscountPercent: num($('posDp').value), totalDiscountAmount: d, shippingFees: del, netAmount: tot,
                amountPaid: tend, amountReturned: Math.max(0, tend - tot), paymentMethod: $('posPay').value, bankAccountId: bank,
                items: cart.flatMap(invoiceItems)
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
    function printBill(id) { $('posPrintFrame').removeAttribute('srcdoc'); $('posPrintFrame').src = PRINT_URL + '?id=' + id + '&autoprint=1&_=' + Date.now(); }

    // ----- barcode (Code 128, drawn as real bars - no library needed) -----
    const C128 = '212222 222122 222221 121223 121322 131222 122213 122312 132212 221213 221312 231212 112232 122132 122231 113222 123122 123221 223211 221132 221231 213212 223112 312131 311222 321122 321221 312212 322112 322211 212123 212321 232121 111323 131123 131321 112313 132113 132311 211313 231113 231311 112133 112331 132131 113123 113321 133121 313121 211331 231131 213113 213311 213131 311123 311321 331121 312113 312311 332111 314111 221411 431111 111224 111422 121124 121421 141122 141221 112214 112412 122114 122411 142112 142211 241211 221114 413111 241112 134111 111242 121142 121241 114212 124112 124211 411212 421112 421211 212141 214121 412121 111143 111341 131141 114113 114311 411113 411311 113141 114131 311141 411131 211412 211214 211232'.split(' ');
    function bcSvg(code, h) {
        code = String(code == null ? '' : code);
        if (!code) return '';
        const v = [104]; let sum = 104;
        for (let i = 0; i < code.length; i++) {
            const c = code.charCodeAt(i) - 32;
            if (c < 0 || c > 95) return '';
            v.push(c); sum += c * (i + 1);
        }
        v.push(sum % 103);
        const pat = v.map(x => C128[x]).join('') + '2331112';
        const Q = 10; let x = Q, r = '';
        for (let i = 0; i < pat.length; i++) {
            const w = +pat[i];
            if (i % 2 === 0) r += `<rect x="${x}" y="0" width="${w}" height="${h}"/>`;
            x += w;
        }
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${x + Q} ${h}" preserveAspectRatio="none" fill="#000">${r}</svg>`;
    }
    let BC = [];
    function bcItems(p) {
        const a = [];
        if (p.barcode) a.push({label: p.name + (p.unit ? ' / ' + p.unit : ''), code: p.barcode, price: p.price});
        (p.loose_units || []).forEach(u => { if (u.barcode) a.push({label: p.name + ' - ' + u.label, code: u.barcode, price: +u.price > 0 ? +u.price : p.price * +u.qty_in_base}); });
        return a;
    }
    function openBarcode(id) {
        const p = products.find(x => x.id === id); if (!p) return;
        BC = bcItems(p).filter(i => bcSvg(i.code, 60));
        capturing = null; ED = null;
        $('posPb').style.maxWidth = '';
        $('posPb').innerHTML = `<h3>${esc(p.name)} - Barcode</h3>` + (BC.length ? `
            <label class="t" for="posBcN">Copies to print</label><input class="f" id="posBcN" type="number" min="1" max="200" value="1">
            ${BC.length > 1 ? '<label class="pos-bcsel"><input type="checkbox" id="posBcAll" checked> Select all</label>' : ''}
            ${BC.map((i, x) => `<div class="pos-bcs"><label class="pos-bcsel"><input type="checkbox" class="posBcChk" value="${x}" checked> <b>${esc(i.label)} - ${esc(f(i.price))}</b></label>${bcSvg(i.code, 60)}<small>${esc(i.code)}</small></div>`).join('')}`
            : '<div class="pos-empty">This product has no barcode set.<br>Add one on the product page first.</div>')
            + '<div class="pos-btns">' + (BC.length ? '<button type="button" class="pos-btn full" id="posBcPrint">🖨 Print Selected</button>' : '') + '<button type="button" class="pos-btn g full" id="posEc">Close</button></div>';
        $('posPm').classList.add('on');
    }
    function printBarcode() {
        const sel = [...document.querySelectorAll('.posBcChk:checked')].map(c => BC[+c.value]).filter(Boolean);
        if (!sel.length) return toast('Select at least one barcode to print', 1);
        const n = Math.min(200, Math.max(1, parseInt($('posBcN').value, 10) || 1));
        const one = sel.map(i => `<div class="l"><div class="nm">${esc(i.label)}</div>${bcSvg(i.code, 60)}<div class="cd">${esc(i.code)}</div><div class="pr">${esc(f(i.price))}</div></div>`).join('');
        const html = '<!doctype html><html><head><meta charset="utf-8"><title>Barcode</title><style>@page{margin:8mm}body{margin:0;font-family:Arial,sans-serif}.w{display:flex;flex-wrap:wrap;gap:2mm;align-items:flex-start}.l{width:50mm;padding:2mm;box-sizing:border-box;text-align:center;overflow:hidden;border:1px dashed #bbb;break-inside:avoid;page-break-inside:avoid}.nm{font-size:10px;font-weight:700;line-height:1.15;max-height:24px;overflow:hidden}.l svg{width:100%;height:14mm;display:block;margin:2px 0}.cd{font-size:10px;letter-spacing:1px}.pr{font-size:11px;font-weight:700}</style></head><body><div class="w">' + one.repeat(n) + '</div></body></html>';
        const fr = $('posPrintFrame');
        fr.onload = () => { fr.onload = null; try { fr.contentWindow.focus(); fr.contentWindow.print(); } catch (e) { toast('Could not open print', 1); } };
        fr.srcdoc = html;
    }

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
        const t = e.target, el = t.closest('[data-c],[data-p],[data-x],[data-e],[data-r],[data-h],[data-t],[data-chip],[data-pr],[data-ed],[data-dl],[data-k],[data-bc]');
        if (el) {
            const d = el.dataset;
            if (d.c) { cat = d.c; drawCats(); drawItems(); }
            else if (d.p) pick(products.find(p => p.id === +d.p));
            else if (d.x !== undefined) { const l = cart[+d.x], st = l.w && l.selectedUnit.base ? 0.5 : 1; l.qty = Math.round((l.qty + +d.d * st) * 10000) / 10000; if (l.qty <= 0) cart.splice(+d.x, 1); drawCart(); }
            else if (d.e !== undefined) openEdit(+d.e);
            else if (d.r !== undefined) { cart.splice(+d.r, 1); drawCart(); }
            else if (d.h !== undefined) restoreHold(+d.h);
            else if (d.t !== undefined) { $('posTend').value = d.t; calc(); }
            else if (d.chip) { const n = $('posEn'); n.value = n.value ? n.value + ', ' + d.chip : d.chip; }
            else if (d.pr) printBill(d.pr);
            else if (d.ed) window.open(EDIT_URL + d.ed, '_blank');
            else if (d.dl) delBill(+d.dl, d.bn);
            else if (d.k) { capturing = d.k; drawKeys(); }
            else if (d.bc) openBarcode(+d.bc);
            return;
        }
        if (t.id === 'posEditSave') saveEdit();
        else if (t.id === 'posBcPrint') printBarcode();
        else if (t.id === 'posEc' || t === $('posPm')) { capturing = null; ED = null; $('posPm').classList.remove('on'); }
        else if (t.id === 'posPlace') place();
        else if (t.id === 'posClear') resetCart();
        else if (t.id === 'posBgo') loadBills();
        else if (t.id === 'posHold') holdBill();
        else if (t.id === 'posApBtn') setAp(!autoPrint);
        else if (t.id === 'posKeysBtn') openKeys();
        else if (t.id === 'posKreset') { KEYS = Object.assign({}, DEFKEYS); LS.set('keys', KEYS); capturing = null; drawKeys(); hints(); }
    });
    document.addEventListener('change', e => {
        if (e.target.id === 'posAp') setAp(e.target.checked);
        else if (e.target.id === 'posBcAll') document.querySelectorAll('.posBcChk').forEach(c => c.checked = e.target.checked);
        else if (e.target.classList.contains('posBcChk')) { const all = $('posBcAll'); if (all) all.checked = [...document.querySelectorAll('.posBcChk')].every(c => c.checked); }
        else if (e.target.matches('[data-unit]')) {
            const idx = +e.target.dataset.unit, line = cart[idx];
            const unit = line && line.sellingUnits.find(u => u.key === e.target.value);
            if (!unit) return;
            // Picking a unit from the dropdown loads THAT unit's own saved rate
            // (e.g. Half Kg = 500, from the product page) with quantity 1.
            line.selectedUnit = {...unit};
            line.qty = 1;
            drawCart();
        } else if (e.target.matches('[data-qty]')) {
            const idx = +e.target.dataset.qty, qty = num(e.target.value);
            if (qty <= 0) cart.splice(idx, 1);
            else cart[idx].qty = qty;
            drawCart();
        }
    });
    document.addEventListener('input', e => {
        if (e.target.matches('[data-qty]')) {
            const idx = +e.target.dataset.qty, line = cart[idx], qty = Math.max(0, num(e.target.value));
            if (!line) return;
            line.qty = qty;
            const total = e.target.closest('.pos-ln').querySelector('.pos-line-total');
            total.textContent = f(lineTotal(line));
            const qtyHint = e.target.closest('.pos-ln').querySelector('.pos-mixedqty');
            if (qtyHint) qtyHint.textContent = displayQuantity(line);
            calc();
        } else if (ED && e.target.id === 'posEditAmount') {
            const line = cart[ED.idx], price = num($('posEditPrice').value);
            if (price > 0) $('posEditQty').value = num(e.target.value) / price;
        }
    });
    $('posSearch').addEventListener('input', drawItems);
    $('posSearch').addEventListener('keydown', async e => {
        if (e.key !== 'Enter') return; e.preventDefault();
        const v = e.target.value.trim().toLowerCase(); if (!v) return;
        const looseHit = products.flatMap(p => (p.loose_units || []).map(u => ({product: p, unit: u}))).find(x => (x.unit.barcode || '').toLowerCase() === v);
        const hit = products.find(p => (p.barcode || '').toLowerCase() === v || (p.code || '').toLowerCase() === v) || (filtered().length === 1 ? filtered()[0] : null);
        if (looseHit) {
            try {
                await getUnits(looseHit.product);
                const unit = sellingUnits(looseHit.product).find(u => +u.packId === +looseHit.unit.id);
                if (!unit) throw new Error('Configured barcode unit is not available');
                addItem(looseHit.product, unit, 1);
                e.target.value = '';
                drawItems();
            } catch (error) { toast(error.message, 1); }
        } else if (hit) {
            try {
                await getUnits(hit);
                const unit = sellingUnits(hit).find(u => (u.barcode || '').toLowerCase() === v) || defaultSellingUnit(hit);
                addItem(hit, unit, 1);
                e.target.value = '';
                drawItems();
            } catch (error) { toast(error.message, 1); }
        } else toast('Item not found', 1);
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
        if (e.key === 'Escape') { ED = null; $('posPm').classList.remove('on'); return; }
        const c = combo(e); if (!c) return;
        const a = Object.keys(KEYS).find(x => KEYS[x] === c);
        if (a) { e.preventDefault(); run(a); }
    }, true);
    document.addEventListener('DOMContentLoaded', init);
})();
</script>
<?php // ===== /POS Business Section ===== ?>