<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne · Costing Configuration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/production_expenses/expense-add.css">
</head>
<body>
    <div class="app-container">
        <div class="page">
            <div class="section-label"><i class="fas fa-cog"></i> Costing Method Configuration</div>
            <div class="card">
                <div class="card-inner">
                    <div class="field">
                        <label>Costing Method <span class="req">*</span></label>
                        <select id="costingMethod" onchange="toggleCostingMethod()" class="form-control">
                            <option value="actual">Actual Costing (Retroactive Adjustment)</option>
                            <option value="standard">Standard Costing (Predetermined Rates)</option>
                        </select>
                        <span class="field-hint" id="methodHint"></span>
                    </div>
                    
                    <div id="actualCostingInfo" style="margin-top:20px; padding:16px; background:#FFF9E6; border-left:4px solid #E8B23F; border-radius:8px; display:none;">
                        <strong style="color:#C96B0A;">Actual Costing Method</strong>
                        <p style="margin:8px 0 0 0; font-size:13px; color:#6B7280;">
                            • Production completed with material cost only<br>
                            • Overhead expenses posted separately (can be after completion)<br>
                            • Run "Adjust Period Costs" at month-end to update inventory & COGS<br>
                            • Best for: Variable overhead, accurate final costs
                        </p>
                    </div>
                    
                    <div id="standardCostingSection" style="display:none; margin-top:20px;">
                        <div style="padding:16px; background:#EEF4FF; border-left:4px solid #1F7BFF; border-radius:8px; margin-bottom:20px;">
                            <strong style="color:#1559B8;">Standard Costing Method</strong>
                            <p style="margin:8px 0 0 0; font-size:13px; color:#6B7280;">
                                • Production completed with material + predetermined overhead<br>
                                • COGS accurate immediately (no waiting for month-end)<br>
                                • Variances tracked separately for analysis<br>
                                • Best for: Consistent overhead, real-time profitability
                            </p>
                        </div>
                        
                        <div class="field">
                            <label>Standard Overhead Rate Type <span class="req">*</span></label>
                            <select id="rateType" class="form-control" onchange="updateRateHint()">
                                <option value="per_unit">Per Unit Produced</option>
                                <option value="percentage_of_material">Percentage of Material Cost</option>
                                <option value="per_hour">Per Machine Hour (Estimated)</option>
                            </select>
                        </div>
                        
                        <div class="grid-2" style="margin-top:16px;">
                            <div class="field">
                                <label>Rate Value <span class="req">*</span></label>
                                <input type="number" id="rateValue" class="form-control" placeholder="e.g., 50" step="0.01">
                                <span class="field-hint" id="rateHint">Enter the standard rate</span>
                            </div>
                            <div class="field">
                                <label>Description</label>
                                <input type="text" id="rateDescription" class="form-control" placeholder="e.g., Factory overhead allocation">
                            </div>
                        </div>
                        
                        <div style="margin-top:20px; padding:12px; background:#F7F9FC; border-radius:8px;">
                            <div style="font-size:12px; font-weight:600; color:#6B7280; margin-bottom:8px;">How to Calculate Standard Rate:</div>
                            <div style="font-size:11px; color:#6B7280; font-family:var(--mono);">
                                1. Total monthly overhead (electricity + rent + labor) = Rs. 50,000<br>
                                2. Average monthly production = 1,000 units<br>
                                3. Standard rate = 50,000 / 1,000 = Rs. 50 per unit
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="actions-bar">
                <button class="btn btn-secondary" onclick="window.location.href='expense-list.php'">Cancel</button>
                <button class="btn btn-primary" onclick="saveConfiguration()"><i class="fas fa-save"></i> Save Configuration</button>
            </div>
        </div>
        
        <div class="toast" id="toast"><i class="fas fa-check-circle" id="toastIcon"></i>
            <div><strong id="toastTitle"></strong>
                <div id="toastMsg"></div>
            </div>
        </div>
    </div>

    <script>
        let currentConfig = null;
        
        window.onload = async () => {
            await loadConfiguration();
        };
        
        async function loadConfiguration() {
            try {
                const response = await fetch('../../../../server/api/manufacturing/production_expenses/costing-config.php');
                const result = await response.json();
                
                if (result.success) {
                    currentConfig = result.data;
                    document.getElementById('costingMethod').value = currentConfig.costing_method;
                    
                    if (currentConfig.overhead_rates && currentConfig.overhead_rates.length > 0) {
                        const rate = currentConfig.overhead_rates[0];
                        document.getElementById('rateType').value = rate.rate_type;
                        document.getElementById('rateValue').value = rate.rate_value;
                        document.getElementById('rateDescription').value = rate.description || '';
                    }
                    
                    toggleCostingMethod();
                }
            } catch (error) {
                console.error('Error loading configuration:', error);
                showToast('error', 'Error', 'Failed to load configuration');
            }
        }
        
        function toggleCostingMethod() {
            const method = document.getElementById('costingMethod').value;
            const actualInfo = document.getElementById('actualCostingInfo');
            const standardSection = document.getElementById('standardCostingSection');
            const methodHint = document.getElementById('methodHint');
            
            if (method === 'actual') {
                actualInfo.style.display = 'block';
                standardSection.style.display = 'none';
                methodHint.textContent = 'Costs adjusted at period-end after all expenses posted';
            } else {
                actualInfo.style.display = 'none';
                standardSection.style.display = 'block';
                methodHint.textContent = 'Costs calculated immediately using predetermined rates';
                updateRateHint();
            }
        }
        
        function updateRateHint() {
            const rateType = document.getElementById('rateType').value;
            const rateHint = document.getElementById('rateHint');
            
            if (rateType === 'per_unit') {
                rateHint.textContent = 'Example: Rs. 50 per unit produced';
            } else if (rateType === 'percentage_of_material') {
                rateHint.textContent = 'Example: 85 (means 85% of material cost)';
            } else if (rateType === 'per_hour') {
                rateHint.textContent = 'Example: Rs. 100 per hour (estimated from production days)';
            }
        }
        
        async function saveConfiguration() {
            const method = document.getElementById('costingMethod').value;
            
            const data = {
                costing_method: method,
                overhead_rates: []
            };
            
            if (method === 'standard') {
                const rateType = document.getElementById('rateType').value;
                const rateValue = parseFloat(document.getElementById('rateValue').value);
                const rateDescription = document.getElementById('rateDescription').value;
                
                if (!rateValue || rateValue <= 0) {
                    showToast('error', 'Validation', 'Please enter a valid rate value');
                    return;
                }
                
                data.overhead_rates.push({
                    rate_type: rateType,
                    rate_value: rateValue,
                    description: rateDescription
                });
            }
            
            try {
                const response = await fetch('../../../../server/api/manufacturing/production_expenses/costing-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('success', 'Saved', 'Costing configuration updated successfully');
                    setTimeout(() => {
                        window.location.href = 'expense-list.php';
                    }, 2000);
                } else {
                    showToast('error', 'Error', result.message);
                }
            } catch (error) {
                console.error('Error saving configuration:', error);
                showToast('error', 'Error', 'Failed to save configuration');
            }
        }
        
        function showToast(type, title, msg) {
            const t = document.getElementById('toast');
            const icon = t.querySelector('#toastIcon');
            icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            document.getElementById('toastTitle').innerText = title;
            document.getElementById('toastMsg').innerText = msg;
            t.classList.remove('success','error');
            t.classList.add(type);
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3800);
        }
    </script>
</body>
</html>
