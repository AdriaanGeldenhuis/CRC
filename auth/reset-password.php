<?php
/**
 * CRC Reset Password Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Redirect if already logged in
if (Auth::check()) {
    Response::redirect('/');
}

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    Session::flash('error', 'Invalid or missing reset token');
    Response::redirect('/auth/forgot-password.php');
}

$pageTitle = 'Reset Password - CRC';
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
                <h1>Reset Password</h1>
                <p>Enter your new password below</p>
            </div>

            <?= flash_message() ?>

            <form id="resetForm" class="auth-form" novalidate>
                <input type="hidden" id="token" value="<?= e($token) ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Enter new password">
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
                    <label for="password_confirm">Confirm New Password</label>
                    <div class="password-input">
                        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" placeholder="Confirm new password">
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <span class="error-message" id="password_confirm-error"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                    <span class="btn-text">Reset Password</span>
                    <span class="btn-loading" style="display:none;">
                        <svg class="spinner" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div class="auth-footer">
                <p>Remember your password? <a href="/auth/">Sign in</a></p>
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

        document.getElementById('resetForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');

            // Clear errors
            clearErrors();

            // Get form data
            const token = document.getElementById('token').value;
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            // Validate
            let hasError = false;

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

            if (hasError) return;

            // Submit
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';

            try {
                const response = await fetch('/auth/api/reset-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ token, password, password_confirm: passwordConfirm })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Password reset successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = '/auth/';
                    }, 1500);
                } else {
                    showToast(data.error || 'Reset failed', 'error');
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
