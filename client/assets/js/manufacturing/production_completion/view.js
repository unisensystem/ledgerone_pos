(function() {
    const API_URL = BASE_URL + '/server/api/manufacturing/production_completion/list.php';

    async function loadCompletion() {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${COMPLETION_ID}`);
            const data = await res.json();

            if (data.success && data.data) {
                renderDetails(data.data);
            } else {
                document.getElementById('completionDetails').innerHTML = '<div style="text-align:center; padding:32px; color:#B12B2B;">Data not found</div>';
            }
        } catch (err) {
            document.getElementById('completionDetails').innerHTML = '<div style="text-align:center; padding:32px; color:#B12B2B;">Error loading data</div>';
        }
    }

    function renderDetails(completion) {
        let html = `
            <div class="detail-section">
                <div class="section-title">Completion Information</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Completion No</div>
                        <div class="detail-value"><strong>${completion.completion_no}</strong></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Production Order</div>
                        <div class="detail-value">${completion.order_no}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Branch</div>
                        <div class="detail-value">${completion.branch_name}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Machine</div>
                        <div class="detail-value">${completion.machine_name || '-'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Complete Date</div>
                        <div class="detail-value">${completion.complete_date}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Products</div>
                        <div class="detail-value">${completion.total_products}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Quantity</div>
                        <div class="detail-value">${completion.total_quantity}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Cost</div>
                        <div class="detail-value"><strong style="color:#059669;">${parseFloat(completion.total_cost).toFixed(2)}</strong></div>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <div class="section-title">Completed Products</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Planned Qty</th>
                                <th>Remaining Qty</th>
                                <th>Completed Qty</th>
                                <th>UOM</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        if (completion.products && completion.products.length > 0) {
            completion.products.forEach(p => {
                html += `
                    <tr>
                        <td><strong>${p.product_code} - ${p.product_name}</strong></td>
                        <td>${p.planned_qty}</td>
                        <td>${p.remaining_qty}</td>
                        <td>${p.completed_qty}</td>
                        <td>${p.uom_name}</td>
                        <td>${parseFloat(p.unit_cost).toFixed(2)}</td>
                        <td><strong>${parseFloat(p.total_cost).toFixed(2)}</strong></td>
                    </tr>
                `;
            });

            html += `
                <tr class="total-row">
                    <td colspan="6" style="text-align:right;">Total Cost:</td>
                    <td><strong>${parseFloat(completion.total_cost).toFixed(2)}</strong></td>
                </tr>
            `;
        } else {
            html += '<tr><td colspan="7" style="text-align:center;">No products found</td></tr>';
        }

        html += `
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="detail-section">
                <div class="section-title">WIP Materials Consumed</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Consumed Qty</th>
                                <th>UOM</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        if (completion.materials && completion.materials.length > 0) {
            let totalMaterialCost = 0;
            completion.materials.forEach(m => {
                totalMaterialCost += parseFloat(m.total_cost);
                html += `
                    <tr>
                        <td><strong>${m.material_code} - ${m.material_name}</strong></td>
                        <td>${parseFloat(m.consumed_qty).toFixed(4)}</td>
                        <td>${m.uom_name}</td>
                        <td>${parseFloat(m.unit_cost).toFixed(2)}</td>
                        <td><strong>${parseFloat(m.total_cost).toFixed(2)}</strong></td>
                    </tr>
                `;
            });

            html += `
                <tr class="total-row">
                    <td colspan="4" style="text-align:right;">Total Material Cost:</td>
                    <td><strong>${totalMaterialCost.toFixed(2)}</strong></td>
                </tr>
            `;
        } else {
            html += '<tr><td colspan="5" style="text-align:center;">No materials found</td></tr>';
        }

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        document.getElementById('completionDetails').innerHTML = html;
    }

    loadCompletion();
})();
