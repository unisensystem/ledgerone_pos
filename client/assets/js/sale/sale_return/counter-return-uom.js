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
    } else if (product.default_unit_id) {
        details.units = [{
            id: parseInt(product.default_unit_id),
            name: product.default_unit_name || 'Unit',
            conversionFactor: 1
        }];
    }

    return details;
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
