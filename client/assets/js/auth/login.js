// Theme toggle functionality
const themeToggle = document.getElementById('themeToggle');
const body = document.body;

// Check for saved theme preference or default to light
const savedTheme = localStorage.getItem('fuelingsys-theme') || 'light';
if (savedTheme === 'dark') {
    body.classList.add('dark-theme');
}

// Auto-fill saved credentials on page load
window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('fuelingsys-remember');
    if (saved) {
        const data = JSON.parse(saved);
        if (Date.now() < data.expires) {
            document.getElementById('email').value = data.email;
            document.getElementById('password').value = data.password;
            document.getElementById('remember').checked = true;
        } else {
            localStorage.removeItem('fuelingsys-remember');
        }
    }
});

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-theme');

        // Save theme preference
        const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
        localStorage.setItem('fuelingsys-theme', currentTheme);
    });
}

// Form submission
const loginForm = document.getElementById('loginForm');
const loginButton = loginForm?.querySelector('button[type="submit"]');

if (loginForm && loginButton) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const emailEl = document.getElementById('email');
        const passwordEl = document.getElementById('password');
        
        if (!emailEl || !passwordEl) {
            showError('Form elements not found');
            return;
        }

        const email = emailEl.value.trim();
        const password = passwordEl.value;

    if (!email || !password) {
        showError('Please fill in all fields');
        return;
    }

    setLoading(true);

    try {
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const response = await fetch('../../../server/api/auth/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                action: 'login',
                email: email,
                password: password,
                csrf_token: csrfToken
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();

        if (result.redirect === 'checkout') {
            window.location.href = `checkout.html?tenant_id=${result.tenant_id}`;
            return;
        }

        if (result.success) {
            // Handle Remember Me
            const rememberMe = document.getElementById('remember').checked;
            if (rememberMe) {
                const expires = Date.now() + (30 * 24 * 60 * 60 * 1000); // 30 days
                localStorage.setItem('fuelingsys-remember', JSON.stringify({
                    email: email,
                    password: password,
                    expires: expires
                }));
            } else {
                localStorage.removeItem('fuelingsys-remember');
            }
            
            // Store session token securely
            sessionStorage.setItem('session_token', result.session_token);
            sessionStorage.setItem('user_id', result.user.id);
            sessionStorage.setItem('tenant_id', result.user.tenant_id);
            sessionStorage.setItem('user_data', JSON.stringify(result.user));
            sessionStorage.setItem('tenant_data', JSON.stringify(result.tenant));
            sessionStorage.setItem('subscription_status', result.subscription.status);
            sessionStorage.setItem('billing_type', result.subscription.billing_type);
            sessionStorage.setItem('subscription_end_date', result.subscription.end_date);
            sessionStorage.setItem('subscription_expired', result.subscription.is_expired);
            
            // Redirect to dashboard
            window.location.href = '../dashboard/dashboard.php';
        } else {
            showError(result.message || 'Login failed');
        }
    } catch (error) {
        console.error('Login error:', error);
        showError('Connection error. Please try again.');
    } finally {
        setLoading(false);
    }
    });
}

function setLoading(loading, button = loginButton) {
    if (button) {
        button.disabled = loading;
        button.textContent = loading ? 'Signing In...' : 'Sign In';
    }
}

function showError(message) {
    // Remove existing error
    const existingError = document.querySelector('.error-message');
    if (existingError) existingError.remove();

    // Create error element
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.cssText = 'color: #dc3545; font-size: 14px; margin-top: 10px; text-align: center;';
    
    loginForm.appendChild(errorDiv);
    
    // Remove error after 5 seconds
    setTimeout(() => {
        if (errorDiv.parentNode) errorDiv.remove();
    }, 5000);
}