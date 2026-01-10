<?php
/**
 * CRC Registration Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Redirect if already logged in
if (Auth::check()) {
    Response::redirect('/');
}

$pageTitle = 'Register - CRC';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/auth/css/auth.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <span class="logo-text">CRC</span>
                </div>
                <h1>Create Account</h1>
                <p>Join the CRC community today</p>
            </div>

            <?= flash_message() ?>

            <form id="registerForm" class="auth-form" novalidate>
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required autocomplete="name" placeholder="Enter your full name">
                    <span class="error-message" id="name-error"></span>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required autocomplete="email" placeholder="your@email.com">
                    <span class="error-message" id="email-error"></span>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number <span class="optional">(optional)</span></label>
                    <input type="tel" id="phone" name="phone" autocomplete="tel" placeholder="0XX XXX XXXX">
                    <span class="error-message" id="phone-error"></span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Create a password">
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <span id="req-length" class="requirement">8+ characters</span>
                        <span id="req-letter" class="requirement">Letter</span>
                        <span id="req-number" class="requirement">Number</span>
                    </div>
                    <span class="error-message" id="password-error"></span>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <div class="password-input">
                        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" placeholder="Confirm your password">
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <span class="error-message" id="password_confirm-error"></span>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span class="checkmark"></span>
                        I agree to the <a href="#" class="terms-link">Terms & Conditions</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                    <span class="btn-text">Create Account</span>
                    <span class="btn-loading" style="display:none;">
                        <svg class="spinner" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="/auth/">Sign in</a></p>
            </div>
        </div>

        <div class="auth-bg">
            <div class="auth-bg-content">
                <h2>Christian Resource Center</h2>
                <p>Connect, Grow, and Serve together in your faith journey.</p>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="/auth/js/auth.js"></script>
    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const reqLength = document.getElementById('req-length');
        const reqLetter = document.getElementById('req-letter');
        const reqNumber = document.getElementById('req-number');

        passwordInput.addEventListener('input', function() {
            const value = this.value;
            reqLength.classList.toggle('valid', value.length >= 8);
            reqLetter.classList.toggle('valid', /[a-zA-Z]/.test(value));
            reqNumber.classList.toggle('valid', /[0-9]/.test(value));
        });

        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');

            // Clear errors
            clearErrors();

            // Get form data
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const terms = document.getElementById('terms').checked;

            // Validate
            let hasError = false;

            if (!name || name.length < 2) {
                showFieldError('name', 'Please enter your name');
                hasError = true;
            }

            if (!email) {
                showFieldError('email', 'Email is required');
                hasError = true;
            } else if (!isValidEmail(email)) {
                showFieldError('email', 'Please enter a valid email');
                hasError = true;
            }

            if (!password) {
                showFieldError('password', 'Password is required');
                hasError = true;
            } else if (password.length < 8) {
                showFieldError('password', 'Password must be at least 8 characters');
                hasError = true;
            } else if (!/[a-zA-Z]/.test(password) || !/[0-9]/.test(password)) {
                showFieldError('password', 'Password must contain letters and numbers');
                hasError = true;
            }

            if (password !== passwordConfirm) {
                showFieldError('password_confirm', 'Passwords do not match');
                hasError = true;
            }

            if (!terms) {
                showToast('Please accept the Terms & Conditions', 'error');
                hasError = true;
            }

            if (hasError) return;

            // Submit
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';

            try {
                const response = await fetch('/auth/api/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ name, email, phone, password, password_confirm: passwordConfirm })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Account created! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || '/onboarding/';
                    }, 500);
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            showFieldError(field, data.errors[field]);
                        });
                    } else {
                        showToast(data.error || 'Registration failed', 'error');
                    }
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            } finally {
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
