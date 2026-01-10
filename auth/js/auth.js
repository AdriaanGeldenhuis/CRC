/**
 * CRC Auth JavaScript
 * Common utilities for authentication pages
 */

// Get CSRF token from meta tag
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Validate email format
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Show field error
function showFieldError(field, message) {
    const input = document.getElementById(field);
    const errorSpan = document.getElementById(field + '-error');

    if (input) {
        input.classList.add('error');
    }

    if (errorSpan) {
        errorSpan.textContent = message;
    }
}

// Clear all errors
function clearErrors() {
    document.querySelectorAll('.error-message').forEach(el => {
        el.textContent = '';
    });
    document.querySelectorAll('input.error').forEach(el => {
        el.classList.remove('error');
    });
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Toggle password visibility
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('.eye-icon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                `;
            } else {
                input.type = 'password';
                icon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            }
        });
    });

    // Auto-focus first input
    const firstInput = document.querySelector('.auth-form input:not([type="hidden"])');
    if (firstInput) {
        firstInput.focus();
    }

    // Clear error on input
    document.querySelectorAll('.auth-form input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
            const errorSpan = document.getElementById(this.id + '-error');
            if (errorSpan) {
                errorSpan.textContent = '';
            }
        });
    });
});

// Format phone number as user types
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');

    if (value.startsWith('27')) {
        value = '0' + value.substring(2);
    }

    if (value.length > 10) {
        value = value.substring(0, 10);
    }

    // Format: 0XX XXX XXXX
    if (value.length > 6) {
        value = value.substring(0, 3) + ' ' + value.substring(3, 6) + ' ' + value.substring(6);
    } else if (value.length > 3) {
        value = value.substring(0, 3) + ' ' + value.substring(3);
    }

    input.value = value;
}

// Add phone formatting
const phoneInput = document.getElementById('phone');
if (phoneInput) {
    phoneInput.addEventListener('input', function() {
        formatPhoneNumber(this);
    });
}

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
