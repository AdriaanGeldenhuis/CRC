/**
 * CRC Onboarding JavaScript
 */

// Get CSRF token
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
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

// Tab switching
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active from all tabs
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        // Add active to clicked tab
        this.classList.add('active');
        const tabId = this.dataset.tab + '-tab';
        document.getElementById(tabId).classList.add('active');
    });
});

// Search congregations
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.congregation-item').forEach(item => {
            const name = item.dataset.name;
            if (name.includes(query)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });
    });
}

// Show congregation list (after invite view)
function showCongregationList() {
    document.querySelector('.invite-section').style.display = 'none';
    document.getElementById('tabsNav').style.display = 'flex';
    document.getElementById('join-tab').style.display = 'block';
}

// Join congregation (open)
async function joinCongregation(congregationId) {
    try {
        const response = await fetch('/onboarding/api/join.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({
                congregation_id: congregationId,
                action: 'join'
            })
        });

        const data = await response.json();

        if (data.ok) {
            showToast('Successfully joined congregation!', 'success');
            setTimeout(() => {
                window.location.href = '/home/';
            }, 1000);
        } else {
            showToast(data.error || 'Failed to join', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

// Request to join congregation (approval required)
async function requestToJoin(congregationId) {
    try {
        const response = await fetch('/onboarding/api/join.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({
                congregation_id: congregationId,
                action: 'request'
            })
        });

        const data = await response.json();

        if (data.ok) {
            showToast('Join request submitted! Waiting for approval.', 'success');
            // Disable the button
            event.target.disabled = true;
            event.target.textContent = 'Pending';
        } else {
            showToast(data.error || 'Failed to submit request', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

// Accept invite
async function acceptInvite(token) {
    try {
        const response = await fetch('/onboarding/api/join.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({
                invite_token: token,
                action: 'accept_invite'
            })
        });

        const data = await response.json();

        if (data.ok) {
            showToast('Invitation accepted!', 'success');
            setTimeout(() => {
                window.location.href = '/home/';
            }, 1000);
        } else {
            showToast(data.error || 'Failed to accept invitation', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

// Use invite code
async function useInviteCode() {
    const code = document.getElementById('inviteCode').value.trim();
    if (!code) {
        showToast('Please enter an invite code', 'error');
        return;
    }

    // Redirect with invite code
    window.location.href = '/onboarding/?invite=' + encodeURIComponent(code);
}

// Create congregation form
const createForm = document.getElementById('createForm');
if (createForm) {
    createForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('createBtn');
        const btnText = btn.querySelector('.btn-text');
        const btnLoading = btn.querySelector('.btn-loading');

        // Clear errors
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

        // Get form data
        const name = document.getElementById('congName').value.trim();
        const city = document.getElementById('congCity').value.trim();
        const province = document.getElementById('congProvince').value;
        const description = document.getElementById('congDescription').value.trim();
        const joinMode = document.getElementById('congJoinMode').value;

        // Validate
        let hasError = false;

        if (!name || name.length < 3) {
            document.getElementById('name-error').textContent = 'Please enter a valid congregation name';
            hasError = true;
        }

        if (!city) {
            document.getElementById('city-error').textContent = 'City is required';
            hasError = true;
        }

        if (!province) {
            document.getElementById('province-error').textContent = 'Please select a province';
            hasError = true;
        }

        if (hasError) return;

        // Submit
        btn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';

        try {
            const response = await fetch('/onboarding/api/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({
                    name,
                    city,
                    province,
                    description,
                    join_mode: joinMode
                })
            });

            const data = await response.json();

            if (data.ok) {
                showToast('Congregation created!', 'success');
                setTimeout(() => {
                    window.location.href = '/home/';
                }, 1000);
            } else {
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        const errorEl = document.getElementById(field + '-error');
                        if (errorEl) {
                            errorEl.textContent = data.errors[field];
                        }
                    });
                } else {
                    showToast(data.error || 'Failed to create congregation', 'error');
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
}
