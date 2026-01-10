/**
 * CRC Profile Page JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    initProfile();
});

function initProfile() {
    // File upload handlers
    setupFileUpload('avatarUpload', 'avatarFile', '/profile/api/upload.php', 'avatar');
    setupFileUpload('coverUpload', 'coverFile', '/profile/api/upload.php', 'cover');

    // Edit form toggle
    const editBtn = document.getElementById('editProfileBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const viewSection = document.getElementById('profileView');
    const editSection = document.getElementById('profileEdit');

    if (editBtn) {
        editBtn.addEventListener('click', () => {
            viewSection.style.display = 'none';
            editSection.style.display = 'block';
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            viewSection.style.display = 'block';
            editSection.style.display = 'none';
        });
    }

    // Profile form submission
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', handleProfileSubmit);
    }
}

function setupFileUpload(buttonId, inputId, url, type) {
    const button = document.getElementById(buttonId);
    const input = document.getElementById(inputId);

    if (!button || !input) return;

    button.addEventListener('click', () => input.click());

    input.addEventListener('change', async function() {
        if (!this.files || !this.files[0]) return;

        const file = this.files[0];

        // Validate file type
        if (!file.type.startsWith('image/')) {
            showToast('Please select an image file', 'error');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            showToast('Image must be less than 5MB', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        formData.append('type', type);
        formData.append('csrf_token', getCsrfToken());

        button.classList.add('loading');

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showToast('Photo updated successfully', 'success');
                // Reload page to show new image
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.error || 'Upload failed', 'error');
            }
        } catch (error) {
            showToast('Upload failed. Please try again.', 'error');
            console.error('Upload error:', error);
        } finally {
            button.classList.remove('loading');
        }
    });
}

async function handleProfileSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    formData.append('csrf_token', getCsrfToken());

    submitBtn.classList.add('loading');
    submitBtn.disabled = true;

    try {
        const response = await fetch('/profile/api/update.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast('Profile updated successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.error || 'Update failed', 'error');
        }
    } catch (error) {
        showToast('Update failed. Please try again.', 'error');
        console.error('Update error:', error);
    } finally {
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
    }
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);

    // Auto-hide
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Birthday countdown
function updateBirthdayCountdown() {
    const countdownEl = document.getElementById('birthdayCountdown');
    if (!countdownEl) return;

    const birthday = countdownEl.dataset.birthday;
    if (!birthday) return;

    const today = new Date();
    const birthDate = new Date(birthday);

    // Set to this year
    let nextBirthday = new Date(today.getFullYear(), birthDate.getMonth(), birthDate.getDate());

    // If birthday has passed this year, set to next year
    if (nextBirthday < today) {
        nextBirthday.setFullYear(today.getFullYear() + 1);
    }

    const diffTime = nextBirthday - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        countdownEl.textContent = "🎉 Today is your birthday!";
    } else if (diffDays === 1) {
        countdownEl.textContent = "🎂 Tomorrow is your birthday!";
    } else {
        countdownEl.textContent = `${diffDays} days until your birthday`;
    }
}

updateBirthdayCountdown();
