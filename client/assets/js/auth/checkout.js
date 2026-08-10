// Current subscription state
const subscriptionState = {
    plan: 'business',
    billingCycle: 'monthly',
    userCount: 10,
    branchCount: 1,
    companyCount: 1,
    addons: [],
    paymentMethod: 'kuickpay',
    tenantId: null,
    prices: {
        starter: { monthly: 2999, yearly: 29990 },
        business: { monthly: 5999, yearly: 59990 },
        professional: { monthly: 9999, yearly: 99990 },
        enterprise: { monthly: 19999, yearly: 199990 },
        user: { monthly: 300, yearly: 3000 },
        branch: { monthly: 1000, yearly: 10000 },
        company: { monthly: 2000, yearly: 20000 }
    }
};

// Validate checkout access on page load
(async function validateCheckout() {
    const urlParams = new URLSearchParams(window.location.search);
    const tenantId = urlParams.get('tenant_id');

    if (!tenantId) {
        alert('Invalid access. Please register first.');
        window.location.href = 'register.html';
        return;
    }

    try {
        const response = await fetch('../../../server/api/auth/validate-checkout.php?tenant_id=' + tenantId);
        const result = await response.json();

        if (!result.success) {
            alert(result.message || 'Unauthorized access. Please register first.');
            window.location.href = 'register.html';
            return;
        }

        subscriptionState.tenantId = tenantId;
        
        // Update tenant info display
        document.querySelector('.tenant-name').textContent = result.tenant_name || 'Your Company';
        document.querySelector('.tenant-id').textContent = 'Tenant ID: ' + tenantId;
    } catch (error) {
        console.error('Validation error:', error);
        // Don't block on validation error, just log it
        subscriptionState.tenantId = tenantId;
        document.querySelector('.tenant-name').textContent = 'Your Company';
        document.querySelector('.tenant-id').textContent = 'Tenant ID: ' + tenantId;
    }
})();

// Show more plans button - REMOVED
// document.getElementById('showMorePlans')?.addEventListener('click', function() {
//     const morePlans = document.getElementById('morePlans');
//     if (morePlans.style.display === 'none') {
//         morePlans.style.display = 'block';
//         this.innerHTML = '<i class="fas fa-chevron-up"></i> Show Less Plans';
//     } else {
//         morePlans.style.display = 'none';
//         this.innerHTML = '<i class="fas fa-chevron-down"></i> See More Plans';
//     }
// });

// Build custom plan button - REMOVED
// document.getElementById('buildCustomBtn')?.addEventListener('click', function() {
//     ...
// });

// Plan selection
document.querySelectorAll('.plan-card').forEach(card => {
    card.addEventListener('click', function () {
        // Remove selected class from all plans
        document.querySelectorAll('.plan-card').forEach(c => {
            c.classList.remove('selected');
        });

        // Add selected class to clicked plan
        this.classList.add('selected');
        subscriptionState.plan = this.getAttribute('data-plan');
        
        // Auto-update user count based on plan
        const userInput = document.getElementById('userCount');
        const planIncludedUsers = getPlanIncludedUsers(subscriptionState.plan);
        userInput.value = planIncludedUsers;
        subscriptionState.userCount = planIncludedUsers;
        
        // Auto-update branch count based on plan
        const branchInput = document.getElementById('branchCount');
        if (subscriptionState.plan === 'multi_branch') {
            branchInput.value = 5;
            subscriptionState.branchCount = 5;
        } else if (subscriptionState.plan === 'regional') {
            branchInput.value = 15;
            subscriptionState.branchCount = 15;
        } else {
            branchInput.value = 1;
            subscriptionState.branchCount = 1;
        }
        
        // Uncheck all custom modules when selecting a pre-built plan
        document.querySelectorAll('.custom-module-check').forEach(cb => {
            cb.checked = false;
            cb.closest('.custom-module-item').style.borderColor = 'var(--border-default)';
            cb.closest('.custom-module-item').style.background = 'transparent';
        });
        
        updatePricing();
    });
});

// Allow deselecting plans by clicking again
document.querySelectorAll('.plan-card').forEach(card => {
    card.addEventListener('dblclick', function () {
        if (this.classList.contains('selected')) {
            this.classList.remove('selected');
            subscriptionState.plan = 'custom';
            updatePricing();
        }
    });
});

// Add visual feedback on plan selection
document.querySelectorAll('.plan-card').forEach(card => {
    card.addEventListener('click', function() {
        // Add checkmark animation
        const checkmark = document.createElement('div');
        checkmark.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success); font-size: 48px;"></i>';
        checkmark.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); animation: scaleIn 0.3s ease;';
        this.style.position = 'relative';
        this.appendChild(checkmark);
        setTimeout(() => checkmark.remove(), 500);
    });
});

// Billing cycle toggle
document.querySelectorAll('.billing-option').forEach(option => {
    option.addEventListener('click', function () {
        const cycle = this.getAttribute('data-cycle');

        // Update UI
        document.querySelectorAll('.billing-option').forEach(opt => {
            opt.classList.remove('active');
        });
        this.classList.add('active');

        // Update state
        subscriptionState.billingCycle = cycle;
        updatePricing();
    });
});

// User count change
document.getElementById('userPlus').addEventListener('click', function () {
    const input = document.getElementById('userCount');
    input.value = Math.min(parseInt(input.value) + 1, 1000);
    subscriptionState.userCount = parseInt(input.value);
    updatePricing();
});

document.getElementById('userMinus').addEventListener('click', function () {
    const input = document.getElementById('userCount');
    input.value = Math.max(parseInt(input.value) - 1, 3);
    subscriptionState.userCount = parseInt(input.value);
    updatePricing();
});

// Branch count change
document.getElementById('branchPlus').addEventListener('click', function () {
    const input = document.getElementById('branchCount');
    input.value = Math.min(parseInt(input.value) + 1, 100);
    subscriptionState.branchCount = parseInt(input.value);
    updatePricing();
});

document.getElementById('branchMinus').addEventListener('click', function () {
    const input = document.getElementById('branchCount');
    input.value = Math.max(parseInt(input.value) - 1, 1);
    subscriptionState.branchCount = parseInt(input.value);
    updatePricing();
});

// Company count change
document.getElementById('companyPlus').addEventListener('click', function () {
    const input = document.getElementById('companyCount');
    input.value = Math.min(parseInt(input.value) + 1, 50);
    subscriptionState.companyCount = parseInt(input.value);
    updatePricing();
});

document.getElementById('companyMinus').addEventListener('click', function () {
    const input = document.getElementById('companyCount');
    input.value = Math.max(parseInt(input.value) - 1, 1);
    subscriptionState.companyCount = parseInt(input.value);
    updatePricing();
});

// Custom module selection
document.querySelectorAll('.custom-module-check').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const label = this.closest('.custom-module-item');
        if (this.checked) {
            label.style.borderColor = 'var(--primary)';
            label.style.background = 'rgba(31, 123, 255, 0.05)';
            
            // Deselect all pre-built plans when using custom builder
            document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
            subscriptionState.plan = 'custom';
        } else {
            label.style.borderColor = 'var(--border-default)';
            label.style.background = 'transparent';
        }
        updateCustomTotal();
        updatePricing();
    });
});

function updateCustomTotal() {
    const basePromo = 199;
    const baseRegular = 1999;
    let totalPromo = basePromo;
    let totalRegular = baseRegular;

    document.querySelectorAll('.custom-module-check:checked').forEach(cb => {
        totalPromo += parseInt(cb.dataset.promo);
        totalRegular += parseInt(cb.dataset.price);
    });

    // Add user, branch, company costs
    const userCount = subscriptionState.userCount;
    const branchCount = subscriptionState.branchCount;
    const companyCount = subscriptionState.companyCount;
    const cycle = subscriptionState.billingCycle;

    const includedUsers = 3;
    const additionalUsers = Math.max(0, userCount - includedUsers);
    const userPricePer = subscriptionState.prices.user[cycle];
    totalPromo += (additionalUsers * userPricePer * 0.1);
    totalRegular += (additionalUsers * userPricePer);

    const additionalBranches = Math.max(0, branchCount - 1);
    const branchPricePer = subscriptionState.prices.branch[cycle];
    totalPromo += (additionalBranches * branchPricePer * 0.1);
    totalRegular += (additionalBranches * branchPricePer);

    const additionalCompanies = Math.max(0, companyCount - 1);
    const companyPricePer = subscriptionState.prices.company[cycle];
    totalPromo += (additionalCompanies * companyPricePer * 0.1);
    totalRegular += (additionalCompanies * companyPricePer);

    document.getElementById('custom-total').innerHTML = `
        <div style="font-size: 24px; font-weight: 700; color: var(--primary);">PKR ${Math.round(totalPromo).toLocaleString()}/mo for 3 months</div>
        <div style="font-size: 14px; color: var(--subtext); margin-top: var(--spacing-sm);">Then PKR ${totalRegular.toLocaleString()}/mo</div>
    `;
}

// Addon selection
document.querySelectorAll('.addon-item').forEach(item => {
    const checkbox = item.querySelector('.addon-checkbox');

    item.addEventListener('click', function (e) {
        if (e.target !== checkbox) {
            checkbox.checked = !checkbox.checked;
        }

        // Update UI
        if (checkbox.checked) {
            this.classList.add('selected');
            if (!subscriptionState.addons.includes(this.getAttribute('data-addon'))) {
                subscriptionState.addons.push(this.getAttribute('data-addon'));
            }
        } else {
            this.classList.remove('selected');
            const index = subscriptionState.addons.indexOf(this.getAttribute('data-addon'));
            if (index > -1) {
                subscriptionState.addons.splice(index, 1);
            }
        }

        updatePricing();
    });
});

// Payment method selection
document.querySelectorAll('.payment-method').forEach(method => {
    method.addEventListener('click', function () {
        // Update UI
        document.querySelectorAll('.payment-method').forEach(m => {
            m.classList.remove('selected');
            const icon = m.querySelector('i:last-child');
            icon.className = 'far fa-circle';
            icon.style.color = 'var(--subtext)';
        });

        this.classList.add('selected');
        const icon = this.querySelector('i:last-child');
        icon.className = 'fas fa-check-circle';
        icon.style.color = 'var(--primary)';

        // Update state
        subscriptionState.paymentMethod = this.getAttribute('data-method');

        // Show/hide payment details based on method
        document.getElementById('kuickpayDetails').style.display = 'none';
        document.getElementById('2checkoutDetails').style.display = 'none';
        document.getElementById('bankDetails').style.display = 'none';

        if (subscriptionState.paymentMethod === 'kuickpay') {
            document.getElementById('kuickpayDetails').style.display = 'block';
        } else if (subscriptionState.paymentMethod === '2checkout') {
            document.getElementById('2checkoutDetails').style.display = 'block';
        } else if (subscriptionState.paymentMethod === 'bank') {
            document.getElementById('bankDetails').style.display = 'block';
        }
    });
});

// Update pricing display
function updatePricing() {
    const cycle = subscriptionState.billingCycle;
    const plan = subscriptionState.plan;
    const userCount = subscriptionState.userCount;

    // Update plan prices
    document.querySelectorAll('.plan-price').forEach(priceEl => {
        const planType = priceEl.closest('.plan-card').getAttribute('data-plan');
        const price = subscriptionState.prices[planType][cycle];
        priceEl.textContent = `PKR ${price.toLocaleString()}`;
    });

    // Update plan periods
    document.querySelectorAll('.plan-period').forEach(periodEl => {
        const planCard = periodEl.closest('.plan-card');
        if (planCard && planCard.getAttribute('data-plan') !== 'enterprise') {
            periodEl.textContent = cycle === 'monthly' ? 'per month' : 'per month, billed annually';
            periodEl.setAttribute('data-cycle', cycle);
        }
    });

    // Update addon prices
    document.querySelectorAll('.addon-price').forEach(priceEl => {
        const addonItem = priceEl.closest('.addon-item');
        const addonType = addonItem.getAttribute('data-addon');
        const monthlyPrice = subscriptionState.prices[addonType].monthly;
        const yearlyPrice = subscriptionState.prices[addonType].yearly;

        priceEl.textContent = cycle === 'monthly' ?
            `PKR ${monthlyPrice.toLocaleString()}/mo` : `PKR ${yearlyPrice.toLocaleString()}/mo`;
        priceEl.setAttribute('data-monthly', `PKR ${monthlyPrice.toLocaleString()}/mo`);
        priceEl.setAttribute('data-yearly', `PKR ${yearlyPrice.toLocaleString()}/mo`);
    });

    // Calculate pricing
    const planPrice = subscriptionState.prices[plan][cycle];
    const includedUsers = 3;
    const additionalUsers = Math.max(0, userCount - includedUsers);
    const userPricePer = subscriptionState.prices.user[cycle];
    const additionalUsersCost = additionalUsers * userPricePer;

    // Calculate branch cost (first branch included)
    const additionalBranches = Math.max(0, subscriptionState.branchCount - 1);
    const branchPricePer = subscriptionState.prices.branch[cycle];
    const branchCost = additionalBranches * branchPricePer;

    // Calculate company cost (first company included)
    const additionalCompanies = Math.max(0, subscriptionState.companyCount - 1);
    const companyPricePer = subscriptionState.prices.company[cycle];
    const companyCost = additionalCompanies * companyPricePer;

    // Calculate addon costs
    let addonsCost = 0;
    subscriptionState.addons.forEach(addon => {
        addonsCost += subscriptionState.prices[addon][cycle];
    });

    // Calculate totals
    const subtotal = planPrice + additionalUsersCost + branchCost + companyCost + addonsCost;
    
    // Apply discount - 30% off first month for monthly, no promo for yearly
    let discount = 0;
    let discountLabel = '';
    if (cycle === 'monthly') {
        discount = subtotal * 0.30;
        discountLabel = 'First Month Discount (30%)';
    }
    
    const total = subtotal - discount;

    // Update order summary
    const planName = plan.charAt(0).toUpperCase() + plan.slice(1).replace(/_/g, ' ');
    const planIncludedUsers = getPlanIncludedUsers(plan);
    
    // Build order details HTML
    let orderDetailsHTML = `
        <div class="order-item">
            <div>
                <div class="item-name">${planName} Plan</div>
                <div class="item-note">${planIncludedUsers} users included</div>
            </div>
            <div class="item-price">PKR ${planPrice.toLocaleString()}</div>
        </div>
    `;
    
    if (additionalUsers > 0) {
        orderDetailsHTML += `
            <div class="order-item">
                <div>
                    <div class="item-name">Additional Users</div>
                    <div class="item-note">${additionalUsers} users × PKR ${userPricePer.toLocaleString()}/user</div>
                </div>
                <div class="item-price">PKR ${additionalUsersCost.toLocaleString()}</div>
            </div>
        `;
    }
    
    if (additionalBranches > 0) {
        orderDetailsHTML += `
            <div class="order-item">
                <div>
                    <div class="item-name">Additional Branches</div>
                    <div class="item-note">${additionalBranches} branches × PKR ${branchPricePer.toLocaleString()}/branch</div>
                </div>
                <div class="item-price">PKR ${branchCost.toLocaleString()}</div>
            </div>
        `;
    }
    
    if (additionalCompanies > 0) {
        orderDetailsHTML += `
            <div class="order-item">
                <div>
                    <div class="item-name">Additional Companies</div>
                    <div class="item-note">${additionalCompanies} companies × PKR ${companyPricePer.toLocaleString()}/company</div>
                </div>
                <div class="item-price">PKR ${companyCost.toLocaleString()}</div>
            </div>
        `;
    }
    
    // Add custom modules
    document.querySelectorAll('.custom-module-check:checked').forEach(cb => {
        const moduleName = cb.parentElement.querySelector('div > div').textContent;
        const modulePrice = cycle === 'monthly' ? parseInt(cb.dataset.promo) : parseInt(cb.dataset.price);
        orderDetailsHTML += `
            <div class="order-item">
                <div class="item-name">${moduleName}</div>
                <div class="item-price">PKR ${modulePrice.toLocaleString()}</div>
            </div>
        `;
    });
    
    orderDetailsHTML += `
        <div class="order-item">
            <div class="item-name">Subtotal</div>
            <div class="item-price">PKR ${subtotal.toLocaleString()}</div>
        </div>
    `;
    
    if (cycle === 'monthly') {
        orderDetailsHTML += `
            <div class="order-item" style="color: var(--success);">
                <div>
                    <div class="item-name">${discountLabel}</div>
                    <div class="item-note">Launch offer for new customers</div>
                </div>
                <div class="item-price">-PKR ${discount.toLocaleString()}</div>
            </div>
        `;
    }
    
    orderDetailsHTML += `
        <div class="order-item total">
            <div>Total</div>
            <div>PKR ${total.toLocaleString()}</div>
        </div>
    `;
    
    document.querySelector('.order-details').innerHTML = orderDetailsHTML;

    document.getElementById('userPrice').textContent = `PKR ${additionalUsersCost.toLocaleString()}`;
    document.getElementById('branchPrice').textContent = `PKR ${branchCost.toLocaleString()}`;
    document.getElementById('companyPrice').textContent = `PKR ${companyCost.toLocaleString()}`;

    // Update billing frequency
    document.getElementById('billingFrequency').textContent = cycle === 'monthly' ? 'monthly' : 'annually';
}

function getPlanIncludedUsers(plan) {
    const userMap = {
        starter: 3,
        business: 10,
        professional: 20,
        enterprise: 50
    };
    return userMap[plan] || 3;
}


// Form validation and submission
function validateForm() {
    let isValid = true;

    // Check terms agreement
    const termsCheckbox = document.getElementById('terms');
    if (!termsCheckbox.checked) {
        alert('Please agree to the Terms of Service, SLA, and DPA to proceed.');
        termsCheckbox.focus();
        return false;
    }

    // No additional validation needed for KuickPay or Bank Transfer
    return isValid;
}

// Activate subscription
document.getElementById('activateBtn').addEventListener('click', async function () {
    if (validateForm()) {
        if (!subscriptionState.tenantId) {
            alert('Invalid session. Please register again.');
            window.location.href = 'register.html';
            return;
        }

        // Show processing state
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        this.disabled = true;

        try {
            // Get total from order summary
            const orderTotalElement = document.getElementById('orderTotal');
            const totalText = orderTotalElement ? orderTotalElement.textContent : '0';
            const amount = totalText.replace('PKR ', '').replace(/,/g, '');

            // Prepare subscription data
            const subscriptionData = {
                tenant_id: subscriptionState.tenantId,
                plan: subscriptionState.plan,
                billing_cycle: subscriptionState.billingCycle,
                user_count: subscriptionState.userCount,
                branch_count: subscriptionState.branchCount,
                company_count: subscriptionState.companyCount,
                addons: subscriptionState.addons,
                payment_method: subscriptionState.paymentMethod,
                amount: amount
            };

            // Submit to checkout API
            const response = await fetch('../../../server/api/auth/checkout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(subscriptionData)
            });

            const result = await response.json();

            if (result.success) {
                if (subscriptionState.paymentMethod === 'kuickpay' || subscriptionState.paymentMethod === '2checkout') {
                    // Redirect to payment gateway
                    window.location.href = result.payment_url;
                } else if (subscriptionState.paymentMethod === 'bank') {
                    alert('Subscription created!\n\nIMPORTANT: Share payment receipt within 24 hours via:\n📧 Email: billing@unisensystems.com\n📱 WhatsApp: +92 300 1234567\n\nInclude your Tenant ID: ' + subscriptionState.tenantId + '\n\n⚠️ Unverified payments will be refunded within 7 business days.');
                    window.location.href = 'login.html';
                }
            } else {
                alert('Subscription failed: ' + result.message);
                this.innerHTML = originalText;
                this.disabled = false;
            }
        } catch (error) {
            console.error('Checkout error:', error);
            alert('Connection error. Please try again.');
            this.innerHTML = originalText;
            this.disabled = false;
        }
    }
});

// Start Trial
document.getElementById('trialBtn').addEventListener('click', async function () {
    if (!subscriptionState.tenantId) {
        alert('Invalid session. Please register again.');
        window.location.href = 'register.html';
        return;
    }

    if (!confirm('Start your 14-day free trial?\n\nNo credit card required.\nFull access to all features.')) {
        return;
    }

    const originalText = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Activating Trial...';
    this.disabled = true;

    try {
        const response = await fetch('../../../server/api/auth/activate-trial.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tenant_id: subscriptionState.tenantId })
        });

        const result = await response.json();

        if (result.success) {
            const params = new URLSearchParams({
                tenant_id: subscriptionState.tenantId,
                trial_ends_at: result.trial_ends_at
            });
            window.location.href = 'trial-success.html?' + params.toString();
        } else {
            alert('Trial activation failed: ' + result.message);
            this.innerHTML = originalText;
            this.disabled = false;
        }
    } catch (error) {
        console.error('Trial error:', error);
        alert('Connection error. Please try again.');
        this.innerHTML = originalText;
        this.disabled = false;
    }
});

// Back button
document.getElementById('backBtn').addEventListener('click', function () {
    if (confirm('Are you sure you want to go back? Your subscription selections will be saved.')) {
        // In a real app, this would go back to previous step
        window.location.href = '#';
    }
});

// Initialize pricing
updatePricing();