/**
 * POS Settings - hide / unhide items-table columns and form fields/dropdowns.
 * Include AFTER counter-invoice.js in counter-invoice page:
 *   <script src="../../../assets/js/sale/pos_invoice/pos-settings.js?v=<?php echo time(); ?>"></script>
 * Settings are saved in localStorage (per browser), same as the theme toggle.
 */
(function () {
    const STORAGE_KEY = 'pos_invoice_settings';

    // Items table columns (1-based position, must match .items-header / .item-row order)
    const COLUMNS = [
        { id: 1,  label: '# (Serial)' },
        { id: 2,  label: 'Product', locked: true },
        { id: 3,  label: 'Unit' },
        { id: 4,  label: 'Qty', locked: true },
        { id: 5,  label: 'Price', locked: true },
        { id: 6,  label: 'Gross' },
        { id: 7,  label: 'Disc %' },
        { id: 8,  label: 'Disc Amt' },
        { id: 9,  label: 'GST %' },
        { id: 10, label: 'Net', locked: true },
        { id: 11, label: 'Action', locked: true }
    ];

    // Form fields / dropdowns (wrapper is found from the element id)
    const FIELDS = [
        { id: 'company',                label: 'Company',               wrap: '.header-item' },
        { id: 'branch',                 label: 'Branch',                wrap: '.header-item' },
        { id: 'counterSelect',          label: 'Counter',               wrap: '.header-item' },
        { id: 'supplierMan',            label: 'Supplier Man',          wrap: '.form-group-micro' },
        { id: 'project',                label: 'Project',               wrap: '.form-group-micro' },
        { id: 'paymentMethod',          label: 'Payment Method',        wrap: '.form-group-micro' },
        { id: 'priceSetting',           label: 'Price Type',            wrap: '.form-group-micro' },
        { id: 'autoAddChildren',        label: 'Auto-add child products', wrap: '.form-group-micro' },
        { id: 'manualPrintToggle',      label: 'Manual Print toggle',   wrap: '.form-group-micro' },
        { id: 'amountReceived',         label: 'Amount Received',       wrap: '.form-group-micro' },
        { id: 'amountReturned',         label: 'Amount Returned',       wrap: '.form-group-micro' },
        { id: 'invoiceDiscountPercent', label: 'Invoice Disc %',        wrap: '.form-group-micro' },
        { id: 'invoiceDiscountAmount',  label: 'Invoice Disc Amt',      wrap: '.form-group-micro' },
        { id: 'currency',               label: 'Currency',              wrap: '.form-group-micro' },
        { id: 'stockPanel',             label: 'Real-time Stock panel', selector: '.stock-panel' }
    ];

    function loadSettings() {
        try {
            const s = JSON.parse(localStorage.getItem(STORAGE_KEY));
            return {
                columns: Array.isArray(s && s.columns) ? s.columns : [],
                fields: Array.isArray(s && s.fields) ? s.fields : []
            };
        } catch (e) {
            return { columns: [], fields: [] };
        }
    }

    function saveSettings(s) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); } catch (e) { /* ignore */ }
    }

    function getFieldWrapper(f) {
        const el = f.selector ? document.querySelector(f.selector) : document.getElementById(f.id);
        if (!el) return null;
        return f.selector ? el : el.closest(f.wrap);
    }

    // ---- Apply ----
    function applyColumns(hiddenCols) {
        let styleTag = document.getElementById('posSettingsColumnsStyle');
        if (styleTag) styleTag.remove();

        const header = document.querySelector('.items-header');
        if (!header || !hiddenCols.length) return;

        // Read the current responsive grid widths, then drop the hidden ones
        const widths = getComputedStyle(header).gridTemplateColumns.split(' ');
        const kept = widths.filter((w, i) => !hiddenCols.includes(i + 1));

        const hideSel = hiddenCols.map(n =>
            `.items-header > :nth-child(${n}), .item-row > :nth-child(${n}), .items-footer > :nth-child(${n})`
        ).join(',\n');

        styleTag = document.createElement('style');
        styleTag.id = 'posSettingsColumnsStyle';
        styleTag.textContent =
            `${hideSel} { display: none !important; }\n` +
            `.items-header, .item-row, .items-footer { grid-template-columns: ${kept.join(' ')} !important; }`;
        document.head.appendChild(styleTag);
    }

    function applyFields(hiddenFields) {
        FIELDS.forEach(f => {
            const w = getFieldWrapper(f);
            if (w) w.style.display = hiddenFields.includes(f.id) ? 'none' : '';
        });
    }

    function applySettings(s) {
        applyFields(s.fields);
        applyColumns(s.columns);
    }

    // ---- UI ----
    function checkboxHtml(group, item) {
        return `<label style="display:flex;align-items:center;gap:8px;padding:4px 0;${item.locked ? 'opacity:.6;' : 'cursor:pointer;'}">
            <input type="checkbox" data-group="${group}" data-id="${item.id}" style="width:auto;height:auto;" ${item.locked ? 'checked disabled' : ''}>
            <span>${item.label}${item.locked ? ' (required)' : ''}</span>
        </label>`;
    }

    function buildModal() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.id = 'posSettingsModal';
        modal.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;';
        modal.innerHTML = `
            <div class="modal-content" style="background:white;padding:24px;border-radius:8px;max-width:700px;width:90%;max-height:80vh;overflow-y:auto;">
                <h3 style="margin-bottom:4px;color:var(--heading);">POS Settings</h3>
                <div style="font-size:11px;color:var(--subtext);margin-bottom:16px;">Checked = visible, unchecked = hidden</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                    <div>
                        <div class="form-label" style="margin-bottom:6px;">ITEMS TABLE COLUMNS</div>
                        ${COLUMNS.map(c => checkboxHtml('columns', c)).join('')}
                    </div>
                    <div>
                        <div class="form-label" style="margin-bottom:6px;">FIELDS / DROPDOWNS</div>
                        ${FIELDS.map(f => checkboxHtml('fields', f)).join('')}
                    </div>
                </div>
                <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
                    <button class="btn btn-secondary" id="posSettingsResetBtn">Show All</button>
                    <button class="btn btn-secondary" id="posSettingsCloseBtn">Close</button>
                    <button class="btn btn-primary" id="posSettingsSaveBtn">Save</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
        return modal;
    }

    function fillModal(modal, s) {
        modal.querySelectorAll('input[data-group]').forEach(cb => {
            if (cb.disabled) return;
            const hidden = cb.dataset.group === 'columns'
                ? s.columns.includes(Number(cb.dataset.id))
                : s.fields.includes(cb.dataset.id);
            cb.checked = !hidden;
        });
    }

    function readModal(modal) {
        const s = { columns: [], fields: [] };
        modal.querySelectorAll('input[data-group]').forEach(cb => {
            if (cb.disabled || cb.checked) return;
            if (cb.dataset.group === 'columns') s.columns.push(Number(cb.dataset.id));
            else s.fields.push(cb.dataset.id);
        });
        return s;
    }

    // ---- Left panel show/hide toggle ----
    const PANEL_KEY = 'pos_left_panel_collapsed';

    function setupPanelToggle() {
        const container = document.querySelector('.invoice-container');
        const right = document.querySelector('.right-panel');
        if (!container || !right) return;

        const style = document.createElement('style');
        style.textContent =
            '.invoice-container.left-collapsed { grid-template-columns: 1fr !important; }\n' +
            '.invoice-container.left-collapsed .left-panel { display: none; }';
        document.head.appendChild(style);

        const btn = document.createElement('button');
        btn.className = 'btn btn-secondary';
        btn.id = 'leftPanelToggleBtn';
        btn.style.cssText = 'height:24px;width:auto;align-self:flex-start;padding:0 10px;';
        right.insertBefore(btn, right.firstChild);

        const render = () => {
            const collapsed = container.classList.contains('left-collapsed');
            btn.innerHTML = collapsed
                ? '<i class="fas fa-angles-right"></i> Show Panel'
                : '<i class="fas fa-angles-left"></i> Hide Panel';
        };

        let collapsed = false;
        try { collapsed = localStorage.getItem(PANEL_KEY) === 'true'; } catch (e) { /* ignore */ }
        container.classList.toggle('left-collapsed', collapsed);
        render();

        btn.addEventListener('click', () => {
            const nowCollapsed = container.classList.toggle('left-collapsed');
            try { localStorage.setItem(PANEL_KEY, nowCollapsed); } catch (e) { /* ignore */ }
            render();
            // Column widths depend on available space
            applyColumns(loadSettings().columns);
        });
    }

    function init() {
        const themeBtn = document.getElementById('themeToggleBtn');
        if (!themeBtn) return;

        // Settings button under the theme toggle
        const btn = document.createElement('button');
        btn.className = 'btn btn-secondary';
        btn.id = 'posSettingsBtn';
        btn.style.cssText = 'width:100%;height:32px;margin-top:6px;';
        btn.innerHTML = '<i class="fas fa-cog"></i> POS Settings';
        themeBtn.parentElement.appendChild(btn);

        const modal = buildModal();
        let settings = loadSettings();

        const open = () => { fillModal(modal, settings); modal.style.display = 'flex'; };
        const close = () => { modal.style.display = 'none'; };

        btn.addEventListener('click', open);
        document.getElementById('posSettingsCloseBtn').addEventListener('click', close);
        modal.addEventListener('click', e => { if (e.target === modal) close(); });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && modal.style.display === 'flex') close();
        });

        document.getElementById('posSettingsResetBtn').addEventListener('click', () => {
            fillModal(modal, { columns: [], fields: [] });
        });

        document.getElementById('posSettingsSaveBtn').addEventListener('click', () => {
            settings = readModal(modal);
            saveSettings(settings);
            applySettings(settings);
            close();
        });

        // Re-calculate column widths when the screen size (responsive grid) changes
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => applyColumns(settings.columns), 150);
        });

        applySettings(settings);
        setupPanelToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();