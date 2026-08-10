// Scheme Management Functions - Integrated with Product Save

let currentSchemeUnits = [];
let currentSchemeProductId = null;
let currentSchemeMode = null;

function initSchemeButton() {
    const defaultUnitSelect = document.getElementById('defaultUnit');
    const uomGroupSelect = document.getElementById('uomGroup');
    
    const unitSchemeContainer = document.createElement('div');
    unitSchemeContainer.id = 'unitSchemeButtonContainer';
    unitSchemeContainer.style.cssText = 'margin-top: 8px; display: none;';
    unitSchemeContainer.innerHTML = '<button type="button" class="btn btn-secondary" id="manageUnitSchemeBtn" style="width: 100%;">⚙️ Manage Schemes</button>';
    defaultUnitSelect.parentNode.appendChild(unitSchemeContainer);
    
    const groupSchemeContainer = document.createElement('div');
    groupSchemeContainer.id = 'groupSchemeButtonContainer';
    groupSchemeContainer.style.cssText = 'margin-top: 8px; display: none;';
    groupSchemeContainer.innerHTML = '<button type="button" class="btn btn-secondary" id="manageGroupSchemeBtn" style="width: 100%;">⚙️ Manage Schemes</button>';
    uomGroupSelect.parentNode.appendChild(groupSchemeContainer);
    
    document.getElementById('manageUnitSchemeBtn').addEventListener('click', openSchemeModal);
    document.getElementById('manageGroupSchemeBtn').addEventListener('click', openSchemeModal);
}

function handleDefaultUnitChangeWithScheme() {
    const select = document.getElementById('defaultUnit');
    const selectedOption = select.options[select.selectedIndex];
    const unitSchemeContainer = document.getElementById('unitSchemeButtonContainer');
    
    if (selectedOption && selectedOption.value) {
        unitSchemeContainer.style.display = 'block';
        currentSchemeMode = 'unit';
        currentSchemeUnits = [{
            id: selectedOption.value,
            name: selectedOption.textContent,
            uom_name: selectedOption.textContent
        }];
    } else {
        unitSchemeContainer.style.display = 'none';
        currentSchemeMode = null;
        currentSchemeUnits = [];
    }
}

function handleUomGroupChangeWithScheme() {
    const select = document.getElementById('uomGroup');
    const groupSchemeContainer = document.getElementById('groupSchemeButtonContainer');
    
    if (select.value && window.currentGroupUnits && window.currentGroupUnits.length > 0) {
        groupSchemeContainer.style.display = 'block';
        currentSchemeMode = 'group';
        currentSchemeUnits = window.currentGroupUnits;
    } else {
        groupSchemeContainer.style.display = 'none';
        currentSchemeMode = null;
        currentSchemeUnits = [];
    }
}

function openSchemeModal() {
    if (!currentSchemeUnits || currentSchemeUnits.length === 0) {
        alert('Please select a unit first');
        return;
    }

    const unitNames = currentSchemeUnits.map(u => u.uom_name).join(', ');
    document.getElementById('schemeModalTitle').textContent = `Manage Schemes - ${unitNames}`;
    document.getElementById('schemeModal').style.display = 'flex';
    
    // Clear previous content
    document.getElementById('schemeTableBody').innerHTML = '';
    
    if (currentSchemeMode === 'group') {
        displayGroupSchemeSections();
    } else {
        displayUnitSchemeSections();
    }
    
    // Load existing schemes if in edit mode
    const editId = document.getElementById('productForm').dataset.editId;
    if (editId && window.existingSchemesData) {
        populateExistingSchemes();
    }
}

function displayUnitSchemeSections() {
    const container = document.getElementById('schemeTableBody');
    container.innerHTML = '';
    
    const unit = currentSchemeUnits[0];
    
    const section = document.createElement('div');
    section.className = 'scheme-unit-section';
    section.style.cssText = 'margin-bottom: 30px;';
    section.innerHTML = `
        <h4 style="margin: 0 0 15px 0; color: var(--heading); font-size: 14px; font-weight: 600;">
            ${unit.uom_name}
        </h4>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">Promo Qty (FOC)</th>
                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">Bonus Qty</th>
                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">TO Qty</th>
                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">TO Rs</th>
                    </tr>
                </thead>
                <tbody class="scheme-unit-tbody" data-unit-id="${unit.id}">
                </tbody>
            </table>
        </div>
    `;
    
    container.appendChild(section);
    
    // Add 1 empty row by default
    addSchemeRowForUnit(unit.id);
}

function displayGroupSchemeSections() {
    const container = document.getElementById('schemeTableBody');
    container.innerHTML = '';
    
    currentSchemeUnits.forEach(unit => {
        const section = document.createElement('div');
        section.className = 'scheme-unit-section';
        section.style.cssText = 'margin-bottom: 30px;';
        section.innerHTML = `
            <h4 style="margin: 0 0 15px 0; color: var(--heading); font-size: 14px; font-weight: 600;">
                ${unit.uom_name}
            </h4>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">Promo Qty (FOC)</th>
                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">Bonus Qty</th>
                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">TO Qty</th>
                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; background: var(--surface-0);">TO Rs</th>
                        </tr>
                    </thead>
                    <tbody class="scheme-unit-tbody" data-unit-id="${unit.id}">
                    </tbody>
                </table>
            </div>
        `;
        
        container.appendChild(section);
        
        // Add 1 empty row by default
        addSchemeRowForUnit(unit.id);
    });
}

function addSchemeRowForUnit(unitId) {
    const tbody = document.querySelector(`.scheme-unit-tbody[data-unit-id="${unitId}"]`);
    const rowIndex = tbody.children.length;
    
    const row = document.createElement('tr');
    row.dataset.unitId = unitId;
    row.dataset.rowIndex = rowIndex;
    row.innerHTML = `
        <td style="padding: 12px; border: 1px solid var(--border-default);">
            <input type="number" class="scheme-promo-qty" step="1" min="0" placeholder="e.g., 10" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
        </td>
        <td style="padding: 12px; border: 1px solid var(--border-default);">
            <input type="number" class="scheme-bonus-qty" step="1" min="0" placeholder="e.g., 2" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
        </td>
        <td style="padding: 12px; border: 1px solid var(--border-default);">
            <input type="number" class="scheme-to-qty" step="1" min="0" placeholder="e.g., 5" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
        </td>
        <td style="padding: 12px; border: 1px solid var(--border-default);">
            <input type="number" class="scheme-to-rs" step="0.01" min="0" placeholder="e.g., 50" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
        </td>
    `;
    
    tbody.appendChild(row);
}

function collectAllSchemes() {
    const schemes = [];
    console.log('collectAllSchemes called');
    
    const tbodies = document.querySelectorAll('.scheme-unit-tbody');
    console.log('Found tbodies:', tbodies.length);
    
    tbodies.forEach(tbody => {
        const unitId = tbody.dataset.unitId;
        console.log('Processing unit:', unitId);
        
        tbody.querySelectorAll('tr').forEach(row => {
            const promoQty = row.querySelector('.scheme-promo-qty')?.value || '';
            const bonusQty = row.querySelector('.scheme-bonus-qty')?.value || '';
            const toQty = row.querySelector('.scheme-to-qty')?.value || '';
            const toRs = row.querySelector('.scheme-to-rs')?.value || '';
            
            console.log('Row values:', {promoQty, bonusQty, toQty, toRs});
            
            // Only add if at least one field has value
            if (promoQty || bonusQty || toQty || toRs) {
                const scheme = {
                    unit_id: unitId,
                    promo_qty: promoQty || 0,
                    bonus_qty: bonusQty || 0,
                    to_qty: toQty || 0,
                    to_rs: toRs || 0
                };
                schemes.push(scheme);
                console.log('Added scheme:', scheme);
            }
        });
    });
    
    console.log('Total schemes collected:', schemes.length, schemes);
    return schemes;
}

function closeSchemeModal() {
    document.getElementById('schemeModal').style.display = 'none';
}

function populateExistingSchemes() {
    if (!window.existingSchemesData || window.existingSchemesData.length === 0) {
        console.log('No existing schemes to populate');
        return;
    }
    
    console.log('Populating existing schemes:', window.existingSchemesData);
    
    const schemesByUnit = {};
    window.existingSchemesData.forEach(scheme => {
        if (!schemesByUnit[scheme.unit_id]) {
            schemesByUnit[scheme.unit_id] = [];
        }
        schemesByUnit[scheme.unit_id].push(scheme);
    });
    
    Object.keys(schemesByUnit).forEach(unitId => {
        const tbody = document.querySelector(`.scheme-unit-tbody[data-unit-id="${unitId}"]`);
        if (tbody) {
            tbody.innerHTML = '';
            schemesByUnit[unitId].forEach(scheme => {
                const row = document.createElement('tr');
                row.dataset.unitId = unitId;
                row.dataset.schemeId = scheme.id;
                row.innerHTML = `
                    <td style="padding: 12px; border: 1px solid var(--border-default);">
                        <input type="number" class="scheme-promo-qty" step="1" min="0" placeholder="e.g., 10" value="${scheme.promo_qty || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                    </td>
                    <td style="padding: 12px; border: 1px solid var(--border-default);">
                        <input type="number" class="scheme-bonus-qty" step="1" min="0" placeholder="e.g., 2" value="${scheme.bonus_qty || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                    </td>
                    <td style="padding: 12px; border: 1px solid var(--border-default);">
                        <input type="number" class="scheme-to-qty" step="1" min="0" placeholder="e.g., 5" value="${scheme.to_qty || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                    </td>
                    <td style="padding: 12px; border: 1px solid var(--border-default);">
                        <input type="number" class="scheme-to-rs" step="0.01" min="0" placeholder="e.g., 50" value="${scheme.to_rs || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        initSchemeButton();
        
        // Setup modal close handlers
        const closeSchemeBtn = document.getElementById('closeSchemeModal');
        const cancelSchemeBtn = document.getElementById('cancelScheme');
        const schemeModal = document.getElementById('schemeModal');
        
        if (closeSchemeBtn) closeSchemeBtn.addEventListener('click', closeSchemeModal);
        if (cancelSchemeBtn) cancelSchemeBtn.addEventListener('click', closeSchemeModal);
        if (schemeModal) {
            schemeModal.addEventListener('click', function(e) {
                if (e.target === this) closeSchemeModal();
            });
        }
    }, 500);
});
