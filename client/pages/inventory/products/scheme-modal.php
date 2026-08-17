<div id="schemeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--surface-1); border-radius: 8px; width: 90%; max-width: 900px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-default);">
            <h2 id="schemeModalTitle" style="margin: 0; color: var(--heading);">Manage Schemes</h2>
            <button type="button" id="closeSchemeModal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--subtext);">×</button>
        </div>

        <!-- Content -->
        <div style="padding: 20px;">
            
            <!-- Example Section -->
            <div style="background: var(--surface-0); border: 1px solid var(--border-default); border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; color: var(--heading);">📋 Understanding Schemes</h4>
                <div style="font-size: 13px; color: var(--subtext); line-height: 1.6;">
                    <p><strong>Promo Qty (FOC):</strong> Free items given when customer buys specified quantity</p>
                    <p><strong>Bonus Qty:</strong> Additional bonus items (optional)</p>
                    <p><strong>TO Qty:</strong> Trade Offer - Minimum quantity condition for the offer</p>
                    <p><strong>TO Rs:</strong> Trade Offer - Discount amount in rupees when TO Qty is met</p>
                    <p style="margin-bottom: 0;"><strong>Example:</strong> Promo Qty=10, Bonus=2 means buy 10 get 2 free. TO Qty=5, TO Rs=50 means buy 5 units get ₹50 discount.</p>
                </div>
            </div>

            <!-- Scheme Sections (Dynamic) -->
            <div id="schemeTableBody" style="margin-bottom: 20px;">
                <!-- Populated by JavaScript -->
            </div>

        </div>

        <!-- Footer -->
        <div style="display: flex; gap: 10px; padding: 20px; border-top: 1px solid var(--border-default); background: var(--surface-0);">
            <div style="flex: 1; color: var(--subtext); font-size: 13px; display: flex; align-items: center;">
                ℹ️ Schemes will be saved when you save the product
            </div>
            <button type="button" id="cancelScheme" class="btn btn-secondary">Close</button>
        </div>

    </div>
</div>
