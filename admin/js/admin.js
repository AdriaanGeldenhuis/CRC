/**
 * CRC Admin JavaScript
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId)?.classList.add('open');
}

function closeModal(modalId) {
    document.getElementById(modalId)?.classList.remove('open');
}

// Close modal on backdrop click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('open');
        }
    });
});

// Close modal on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.open').forEach(modal => {
            modal.classList.remove('open');
        });
    }
});

// Filter helper
function applyFilter(name, value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set(name, value);
    } else {
        url.searchParams.delete(name);
    }
    url.searchParams.delete('page');
    window.location = url;
}

// Add User Form
const addUserForm = document.getElementById('add-user-form');
if (addUserForm) {
    addUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(addUserForm);
        formData.append('action', 'add_user');

        try {
            const response = await fetch('/admin/api/admin.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('User created successfully');
                window.location.reload();
            } else {
                alert(data.error || 'Failed to create user');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to create user');
        }
    });
}

// Edit User
async function editUser(userId) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_user');
        formData.append('user_id', userId);

        const response = await fetch('/admin/api/admin.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            const user = data.user;
            document.getElementById('edit-user-id').value = user.id;
            document.getElementById('edit-user-name').value = user.name;
            document.getElementById('edit-user-email').value = user.email;
            document.getElementById('edit-user-role').value = user.global_role;
            openModal('edit-user-modal');
        } else {
            alert(data.error || 'Failed to load user');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load user');
    }
}

// Edit User Form
const editUserForm = document.getElementById('edit-user-form');
if (editUserForm) {
    editUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(editUserForm);
        formData.append('action', 'update_user');

        try {
            const response = await fetch('/admin/api/admin.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('User updated successfully');
                window.location.reload();
            } else {
                alert(data.error || 'Failed to update user');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update user');
        }
    });
}

// Delete User
async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);

        const response = await fetch('/admin/api/admin.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to delete user');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to delete user');
    }
}

// Add Congregation Form
const addCongregationForm = document.getElementById('add-congregation-form');
if (addCongregationForm) {
    addCongregationForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(addCongregationForm);
        formData.append('action', 'add_congregation');

        try {
            const response = await fetch('/admin/api/admin.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('Congregation created successfully');
                window.location.reload();
            } else {
                alert(data.error || 'Failed to create congregation');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to create congregation');
        }
    });
}

// Suspend/Activate Congregation
async function suspendCongregation(congId) {
    if (!confirm('Are you sure you want to suspend this congregation?')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'suspend_congregation');
        formData.append('congregation_id', congId);

        const response = await fetch('/admin/api/admin.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to suspend congregation');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function activateCongregation(congId) {
    try {
        const formData = new FormData();
        formData.append('action', 'activate_congregation');
        formData.append('congregation_id', congId);

        const response = await fetch('/admin/api/admin.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to activate congregation');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Settings Form
const settingsForm = document.getElementById('settings-form');
if (settingsForm) {
    settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(settingsForm);
        formData.append('action', 'save_settings');

        // Handle unchecked checkboxes
        settingsForm.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (!cb.checked) {
                formData.set(cb.name, '0');
            } else {
                formData.set(cb.name, '1');
            }
        });

        try {
            const response = await fetch('/admin/api/admin.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('Settings saved successfully');
            } else {
                alert(data.error || 'Failed to save settings');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save settings');
        }
    });
}

// Copy code to clipboard
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        alert('Code copied to clipboard!');
    });
}

// Mobile sidebar toggle
function toggleSidebar() {
    document.querySelector('.admin-sidebar')?.classList.toggle('open');
}
