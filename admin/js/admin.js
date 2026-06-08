/**
 * CRC Admin JavaScript
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

// Edit state (reused add/edit modals)
let editingCongregationId = null;
let editingCourseId = null;

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
        if (editingCongregationId) {
            formData.append('action', 'update_congregation');
            formData.append('congregation_id', editingCongregationId);
        } else {
            formData.append('action', 'add_congregation');
        }

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

// ===== Congregations & Courses: Add/Edit (reuse the add modal) =====
function setModalTitle(modalId, text) {
    const h = document.querySelector('#' + modalId + ' .modal-header h2');
    if (h) h.textContent = text;
}
function fillForm(form, data, fields) {
    fields.forEach(m => {
        const el = form.querySelector('[name="' + m.f + '"]');
        if (el) el.value = data[m.k] ?? '';
    });
}
async function adminPost(action, params) {
    const fd = new FormData();
    fd.append('action', action);
    Object.keys(params || {}).forEach(k => fd.append(k, params[k]));
    const res = await fetch('/admin/api/admin.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF_TOKEN } });
    return res.json();
}

// --- Congregations ---
function openAddCongregation() {
    editingCongregationId = null;
    document.getElementById('add-congregation-form')?.reset();
    setModalTitle('add-congregation-modal', 'Add Congregation');
    openModal('add-congregation-modal');
}
async function editCongregation(id) {
    try {
        const data = await adminPost('get_congregation', { congregation_id: id });
        if (!data.ok) { alert(data.error || 'Failed to load congregation'); return; }
        const c = data.congregation;
        const form = document.getElementById('add-congregation-form');
        form.reset();
        editingCongregationId = c.id;
        fillForm(form, c, [
            { f: 'name', k: 'name' }, { f: 'city', k: 'city' }, { f: 'country', k: 'country' },
            { f: 'address', k: 'address' }, { f: 'code', k: 'slug' }, { f: 'status', k: 'status' }
        ]);
        setModalTitle('add-congregation-modal', 'Edit Congregation');
        openModal('add-congregation-modal');
    } catch (e) { console.error(e); alert('Failed to load congregation'); }
}

// --- Courses ---
function openAddCourse() {
    editingCourseId = null;
    document.getElementById('add-course-form')?.reset();
    setModalTitle('add-course-modal', 'Add Course');
    openModal('add-course-modal');
}
const addCourseForm = document.getElementById('add-course-form');
if (addCourseForm) {
    addCourseForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(addCourseForm);
        if (editingCourseId) {
            formData.append('action', 'update_course');
            formData.append('course_id', editingCourseId);
        } else {
            formData.append('action', 'add_course');
        }
        try {
            const res = await fetch('/admin/api/admin.php', { method: 'POST', body: formData, headers: { 'X-CSRF-Token': CSRF_TOKEN } });
            const data = await res.json();
            if (data.ok) { window.location.reload(); }
            else { alert(data.error || 'Failed to save course'); }
        } catch (err) { console.error(err); alert('Failed to save course'); }
    });
}
async function editCourse(id) {
    try {
        const data = await adminPost('get_course', { course_id: id });
        if (!data.ok) { alert(data.error || 'Failed to load course'); return; }
        const c = data.course;
        const form = document.getElementById('add-course-form');
        form.reset();
        editingCourseId = c.id;
        fillForm(form, c, [
            { f: 'title', k: 'title' }, { f: 'category', k: 'category' },
            { f: 'description', k: 'description' }, { f: 'thumbnail', k: 'thumbnail' }
        ]);
        setModalTitle('add-course-modal', 'Edit Course');
        openModal('add-course-modal');
    } catch (e) { console.error(e); alert('Failed to load course'); }
}
async function deleteCourse(id) {
    if (!confirm('Are you sure you want to delete this course?')) return;
    try {
        const data = await adminPost('delete_course', { course_id: id });
        if (data.ok) { window.location.reload(); }
        else { alert(data.error || 'Failed to delete course'); }
    } catch (e) { console.error(e); alert('Failed to delete course'); }
}
