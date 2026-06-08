<?php
/**
 * CRC Forgot Password Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Redirect if already logged in
if (Auth::check()) {
    Response::redirect('/');
}

$pageTitle = 'Forgot Password - CRC';
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
                <h1>Forgot Password?</h1>
                <p>Enter your email and we'll send you a reset link</p>
            </div>

            <?= flash_message() ?>

            <form id="forgotForm" class="auth-form" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required autocomplete="email" placeholder="your@email.com">
                    <span class="error-message" id="email-error"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                    <span class="btn-text">Send Reset Link</span>
                    <span class="btn-loading" style="display:none;">
                        <svg class="spinner" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div id="successMessage" class="success-message" style="display:none;">
                <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
                <h3>Check your email</h3>
                <p>If an account exists with that email, we've sent password reset instructions.</p>
            </div>

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
        document.getElementById('forgotForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');
            const form = this;
            const successMessage = document.getElementById('successMessage');

            // Clear errors
            clearErrors();

            // Get form data
            const email = document.getElementById('email').value.trim();

            // Validate
            if (!email) {
                showFieldError('email', 'Email is required');
                return;
            }

            if (!isValidEmail(email)) {
                showFieldError('email', 'Please enter a valid email');
                return;
            }

            // Submit
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';

            try {
                const response = await fetch('/auth/api/forgot-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ email })
                });

                const data = await response.json();

                // Always show success to prevent enumeration
                form.style.display = 'none';
                successMessage.style.display = 'block';

            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
