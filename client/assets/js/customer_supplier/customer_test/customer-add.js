(function () {
    const formCard = document.querySelector('.form-card');
    const customerCode = document.getElementById('customerCode');
    const customerName = document.getElementById('customerName');
    const phone = document.getElementById('phone');
    const email = document.getElementById('email');

    const nameField = document.getElementById('name-field');
    const phoneField = document.getElementById('phone-field');
    const emailField = document.getElementById('email-field');

    const nameHelper = document.getElementById('name-helper');
    const phoneHelper = document.getElementById('phone-helper');
    const emailHelper = document.getElementById('email-helper');

    const resetBtn = document.getElementById('resetBtn');
    const submitBtn = document.getElementById('submitBtn');

    function clearErrors() {
        [nameField, phoneField, emailField].forEach(f => f.classList.remove('error'));
        nameHelper.textContent = '';
        phoneHelper.textContent = '';
        emailHelper.textContent = '';
    }

    function validateForm() {
        let isValid = true;
        clearErrors();

        if (!customerName.value.trim()) {
            nameField.classList.add('error');
            nameHelper.textContent = 'Customer name is required';
            isValid = false;
        }

        const phoneVal = phone.value.trim();
        if (!phoneVal) {
            phoneField.classList.add('error');
            phoneHelper.textContent = 'Phone number is required';
            isValid = false;
        } else {
            const phoneClean = phoneVal.replace(/[0-9\s\+\-\(\)]/g, '');
            if (phoneClean.length > 0 || phoneVal.replace(/\D/g, '').length < 5) {
                phoneField.classList.add('error');
                phoneHelper.textContent = 'Enter a valid phone number (at least 5 digits)';
                isValid = false;
            }
        }

        const emailVal = email.value.trim();
        if (emailVal !== '') {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailVal)) {
                emailField.classList.add('error');
                emailHelper.textContent = 'Enter a valid email address';
                isValid = false;
            }
        }

        return isValid;
    }

    submitBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        if (!validateForm()) {
            document.querySelector('.field.error input')?.focus();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const response = await fetch('../../../../server/api/customer_supplier/customer_test/customer-add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customerName: customerName.value.trim(),
                    phone: phone.value.trim(),
                    email: email.value.trim()
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('✅ Customer added successfully!');
                customerCode.value = result.customer_code;
                customerName.value = '';
                phone.value = '';
                email.value = '';
            } else {
                alert('❌ Error: ' + result.message);
            }
        } catch (error) {
            alert('❌ Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save customer';
        }
    });

    resetBtn.addEventListener('click', (e) => {
        e.preventDefault();
        customerName.value = '';
        phone.value = '';
        email.value = '';
        clearErrors();
    });
})();