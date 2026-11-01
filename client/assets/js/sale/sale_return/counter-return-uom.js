// UOM Group System for Counter Return
let maxUnitColumns = 0;

function getProductUOMDetails(product) {
    const details = {
        type: product.uom_type || 'unit',
        units: []
    };

    if (details.type === 'group' && product.group_units && product.group_units.length > 0) {
        details.units = product.group_units.map(unit => {
            const cf = unit.is_base_unit == 1 ? 1 : (parseFloat(unit.conversion_factor) || 1);
            return {
                id: parseInt(unit.uom_id),
                name: unit.uom_name,
                conversionFactor: cf
            };
        });
    } else if (product.units && product.units.length > 0) {
        // Non-group product with a bigger default unit (e.g. CTN) - API sends [CTN, Piece]
        details.units = product.units.map(unit => ({
            id: parseInt(unit.id),
            name: unit.name,
            conversionFactor: parseFloat(unit.factor) || 1
        }));
    } else if (product.default_unit_id) {
        details.units = [{
            id: parseInt(product.default_unit_id),
            name: product.default_unit_name || 'Unit',
            conversionFactor: 1
        }];
    }

    return details;
}

// For non-group products with several units (CTN + Piece) the Price field is per BASE unit
// (quantity is converted to base units), so invoice prices (which are per sold unit) are converted here.
function getReturnPriceFromInvoiceRows(uomDetails, rows) {
    const first = parseFloat(rows[0].sale_price) || 0;
    if (!uomDetails.type || uomDetails.type === 'group' || uomDetails.units.length < 2) return first;

    let amount = 0, baseQty = 0;
    rows.forEach(row => {
        const unit = uomDetails.units.find(u => u.id === parseInt(row.uom_id));
        const qty = parseFloat(row.quantity) || 0;
        amount += qty * (parseFloat(row.sale_price) || 0);
        baseQty += qty * (unit ? (parseFloat(unit.conversionFactor) || 1) : 1);
    });
    return baseQty > 0 ? amount / baseQty : first;
}

// Edit mode: keep the saved gross amount unchanged, even for returns saved before Piece was available.
function getReturnPriceFromSavedItem(uomDetails, item) {
    const saved = parseFloat(item.sale_price) || 0;
    if (!uomDetails.type || uomDetails.type === 'group' || uomDetails.units.length < 2) return saved;

    let baseQty = 0;
    (item.unit_entries || []).forEach(entry => {
        const unit = uomDetails.units.find(u => u.id === parseInt(entry.uom_id));
        baseQty += (parseFloat(entry.quantity) || 0) * (unit ? (parseFloat(unit.conversionFactor) || 1) : 1);
    });
    const gross = parseFloat(item.gross_amount) || 0;
    return (baseQty > 0 && gross > 0) ? gross / baseQty : saved;
}

function recalculateMaxColumns() {
    maxUnitColumns = 0;
    currentReturn.items.forEach(item => {
        if (item.units && item.units.length > maxUnitColumns) {
            maxUnitColumns = item.units.length;
        }
    });
}

function calculateTotalQty(item) {
    let total = 0;
    if (item.units) {
        item.units.forEach(unit => {
            total += (parseFloat(unit.qty) || 0) * (parseFloat(unit.conversionFactor) || 1);
        });
    }
    return total;
}