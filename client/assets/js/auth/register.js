document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registrationForm');
    const requiredFields = form.querySelectorAll('.required');

    // Initialize international phone input
    const phoneInput = document.querySelector('#phone');
    const iti = window.intlTelInput(phoneInput, {
        initialCountry: 'pk',
        preferredCountries: ['pk', 'us', 'gb', 'ae', 'sa'],
        separateDialCode: true,
        utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js'
    });

    // Form validation on submit
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        console.log('Form submitted');

        let isValid = true;
        const errorMessages = [];

        // Check required fields
        requiredFields.forEach(label => {
            const fieldId = label.getAttribute('for');
            const field = document.getElementById(fieldId);

            if (field && !field.value.trim()) {
                isValid = false;
                field.classList.add('error');

                // Update helper text with error
                const helper = field.parentElement.querySelector('.form-helper');
                if (helper) {
                    helper.textContent = 'This field is required';
                    helper.classList.add('error');
                }

                errorMessages.push(`${label.textContent.replace('*', '')} is required`);
            } else if (field) {
                field.classList.remove('error');

                // Reset helper text
                const helper = field.parentElement.querySelector('.form-helper');
                if (helper) {
                    helper.classList.remove('error');
                    const originalText = helper.dataset.originalText || getDefaultHelperText(fieldId);
                    helper.textContent = originalText;
                }

                // Email validation
                if (fieldId === 'email' && field.value.trim()) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value.trim())) {
                        isValid = false;
                        field.classList.add('error');
                        const helper = field.parentElement.querySelector('.form-helper');
                        if (helper) {
                            helper.textContent = 'Please enter a valid email address';
                            helper.classList.add('error');
                        }
                        errorMessages.push('Please enter a valid email address');
                    }
                }

                // Password validation
                if (fieldId === 'password' && field.value.trim()) {
                    if (field.value.length < 8) {
                        isValid = false;
                        field.classList.add('error');
                        const helper = field.parentElement.querySelector('.form-helper');
                        if (helper) {
                            helper.textContent = 'Password must be at least 8 characters';
                            helper.classList.add('error');
                        }
                        errorMessages.push('Password must be at least 8 characters');
                    }
                }
            }
        });

        if (isValid) {
            console.log('Validation passed, submitting...');
            const submitButton = form.querySelector('.primary-button');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';

            try {
                console.log('Making API call...');
                const response = await fetch('../../../server/api/auth/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        companyName: document.getElementById('companyName').value,
                        firstName: document.getElementById('firstName').value,
                        lastName: document.getElementById('lastName').value,
                        phone: iti.getNumber(), // Get full international number
                        email: document.getElementById('email').value,
                        password: document.getElementById('password').value,
                        country: document.getElementById('country').value,
                        state: document.getElementById('state').value,
                        city: document.getElementById('city').value,
                        postalCode: document.getElementById('postalCode').value,
                        address1: document.getElementById('address1').value,
                        address2: document.getElementById('address2').value
                    })
                });

                console.log('API response received:', response.status);
                const result = await response.json();
                console.log('Result:', result);

                if (result.success) {
                    alert('Registration successful! Redirecting to subscription checkout...');
                    window.location.href = 'checkout.html?tenant_id=' + result.tenant_id;
                } else {
                    alert('Registration failed: ' + result.message);
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Registration error:', error);
                alert('Connection error. Please try again.');
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        } else {
            console.log('Form validation failed:', errorMessages);
            if (errorMessages.length > 0) {
                alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
            }
        }
    });

    // Store original helper text on page load
    document.querySelectorAll('.form-helper').forEach(helper => {
        const fieldId = helper.parentElement.querySelector('.form-input').id;
        helper.dataset.originalText = helper.textContent;
    });

    // Helper function to get default helper text
    function getDefaultHelperText(fieldId) {
        const defaults = {
            companyName: 'The official name of your company',
            firstName: 'Your given name',
            lastName: 'Your family name',
            phone: 'Include country code',
            email: "We'll send a verification email",
            password: 'Minimum 8 characters',
            country: 'Select your country',
            state: 'State or province',
            city: 'City name',
            postalCode: 'ZIP or postal code',
            address1: 'Primary address line',
            address2: 'Optional additional address info'
        };

        return defaults[fieldId] || '';
    }

    // Cancel button functionality
    const cancelButton = document.querySelector('.secondary-button');
    cancelButton.addEventListener('click', function () {
        if (confirm('Are you sure you want to cancel registration? All entered data will be lost.')) {
            window.location.href = 'login.html';
        }
    });

    // Login link functionality
    const loginLink = document.querySelector('.login-link');
    loginLink.addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = 'login.html';
    });
});