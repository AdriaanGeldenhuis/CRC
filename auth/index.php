<?php
/**
 * CRC Login Page
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Redirect if already logged in
if (Auth::check()) {
    Response::redirect('/');
}

$pageTitle = 'Login - CRC';
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
                <h1>Welcome Back</h1>
                <p>Sign in to continue to your account</p>
            </div>

            <?= flash_message() ?>

            <form id="loginForm" class="auth-form" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required autocomplete="email" placeholder="your@email.com">
                    <span class="error-message" id="email-error"></span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <span class="error-message" id="password-error"></span>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me" id="remember_me">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="/auth/forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-loading" style="display:none;">
                        <svg class="spinner" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="/auth/register.php">Create one</a></p>
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
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');

            // Clear errors
            clearErrors();

            // Get form data
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('remember_me').checked;

            // Validate
            let hasError = false;
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
            }

            if (hasError) return;

            // Submit
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';

            try {
                const response = await fetch('/auth/api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ email, password, remember_me: rememberMe })
                });

                const data = await response.json();

                if (data.ok) {
                    showToast('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || '/';
                    }, 500);
                } else {
                    showToast(data.error || 'Login failed', 'error');
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
