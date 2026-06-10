/**
 * CRC Admin JavaScript
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

let editingCongregationId = null;
let editingCourseId = null;
let editingSermonId = null;
let editingLivestreamId = null;
let editingContentId = null;

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
function bindCrudForm(formId, addAction, updateAction, idField, getEditingId, label) {
    const form = document.getElementById(formId);
    if (!form) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const editId = getEditingId();
        if (editId) {
            formData.append('action', updateAction);
            formData.append(idField, editId);
        } else {
            formData.append('action', addAction);
        }
        try {
            const res = await fetch('/admin/api/admin.php', { method: 'POST', body: formData, headers: { 'X-CSRF-Token': CSRF_TOKEN } });
            const data = await res.json();
            if (data.ok) { window.location.reload(); }
            else { alert(data.error || ('Failed to save ' + label)); }
        } catch (err) { console.error(err); alert('Failed to save ' + label); }
    });
}

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

// ===== Congregations: Edit (reuses the add modal) =====
function openAddCongregation() {
    editingCongregationId = null;
    document.getElementById('add-congregation-form')?.reset();
    setModalTitle('add-congregation-modal', 'Add Congregation');
    openModal('add-congregation-modal');
}
async function editCongregation(id) {
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
}

// ===== Courses =====
function openAddCourse() {
    editingCourseId = null;
    document.getElementById('add-course-form')?.reset();
    setModalTitle('add-course-modal', 'Add Course');
    openModal('add-course-modal');
}
bindCrudForm('add-course-form', 'add_course', 'update_course', 'course_id', () => editingCourseId, 'course');
async function editCourse(id) {
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
}
async function deleteCourse(id) {
    if (!confirm('Are you sure you want to delete this course?')) return;
    const data = await adminPost('delete_course', { course_id: id });
    if (data.ok) { window.location.reload(); } else { alert(data.error || 'Failed to delete course'); }
}

// ===== Sermons =====
function openAddSermon() {
    editingSermonId = null;
    document.getElementById('add-sermon-form')?.reset();
    setModalTitle('add-sermon-modal', 'Add Sermon');
    openModal('add-sermon-modal');
}
bindCrudForm('add-sermon-form', 'add_sermon', 'update_sermon', 'sermon_id', () => editingSermonId, 'sermon');
async function editSermon(id) {
    const data = await adminPost('get_sermon', { sermon_id: id });
    if (!data.ok) { alert(data.error || 'Failed to load sermon'); return; }
    const s = data.sermon;
    const form = document.getElementById('add-sermon-form');
    form.reset();
    editingSermonId = s.id;
    fillForm(form, s, [
        { f: 'title', k: 'title' }, { f: 'speaker', k: 'speaker' }, { f: 'sermon_date', k: 'sermon_date' },
        { f: 'description', k: 'description' }, { f: 'video_url', k: 'video_url' },
        { f: 'audio_url', k: 'audio_url' }, { f: 'category', k: 'category' },
        { f: 'thumbnail', k: 'thumbnail' }, { f: 'status', k: 'status' },
        { f: 'series_id', k: 'series_id' }, { f: 'congregation_id', k: 'congregation_id' }
    ]);
    // Duration is stored in seconds; the form captures whole minutes.
    const durMin = form.querySelector('[name="duration_minutes"]');
    if (durMin) durMin.value = s.duration ? Math.round(s.duration / 60) : '';
    // Scripture references are stored as a JSON array; show one per line.
    const scrip = form.querySelector('[name="scripture_references"]');
    if (scrip) {
        let refs = [];
        try { refs = s.scripture_references ? JSON.parse(s.scripture_references) : []; } catch (e) { refs = []; }
        scrip.value = Array.isArray(refs) ? refs.join('\n') : (s.scripture_references || '');
    }
    const newSeries = form.querySelector('[name="new_series"]');
    if (newSeries) newSeries.value = '';
    setModalTitle('add-sermon-modal', 'Edit Sermon');
    openModal('add-sermon-modal');
}
async function deleteSermon(id) {
    if (!confirm('Are you sure you want to delete this sermon?')) return;
    const data = await adminPost('delete_sermon', { sermon_id: id });
    if (data.ok) { window.location.reload(); } else { alert(data.error || 'Failed to delete sermon'); }
}

// ===== Livestreams =====
function openAddLivestream() {
    editingLivestreamId = null;
    document.getElementById('add-livestream-form')?.reset();
    setModalTitle('add-livestream-modal', 'Add Livestream');
    openModal('add-livestream-modal');
}
bindCrudForm('add-livestream-form', 'add_livestream', 'update_livestream', 'stream_id', () => editingLivestreamId, 'livestream');
async function editLivestream(id) {
    const data = await adminPost('get_livestream', { stream_id: id });
    if (!data.ok) { alert(data.error || 'Failed to load livestream'); return; }
    const s = data.livestream;
    const form = document.getElementById('add-livestream-form');
    form.reset();
    editingLivestreamId = s.id;
    fillForm(form, s, [
        { f: 'title', k: 'title' }, { f: 'status', k: 'status' },
        { f: 'congregation_id', k: 'congregation_id' }, { f: 'embed_url', k: 'embed_url' },
        { f: 'description', k: 'description' }, { f: 'recording_url', k: 'recording_url' },
        { f: 'thumbnail_url', k: 'thumbnail_url' }
    ]);
    // datetime-local wants YYYY-MM-DDTHH:MM
    const sched = form.querySelector('[name="scheduled_at"]');
    if (sched) sched.value = s.scheduled_at ? s.scheduled_at.replace(' ', 'T').slice(0, 16) : '';
    // duration stored in seconds; form captures whole minutes
    const durMin = form.querySelector('[name="duration_minutes"]');
    if (durMin) durMin.value = s.duration ? Math.round(s.duration / 60) : '';
    const chat = form.querySelector('[name="chat_enabled"]');
    if (chat) chat.checked = !!Number(s.chat_enabled);
    setModalTitle('add-livestream-modal', 'Edit Livestream');
    openModal('add-livestream-modal');
}
async function deleteLivestream(id) {
    if (!confirm('Are you sure you want to delete this livestream?')) return;
    const data = await adminPost('delete_livestream', { stream_id: id });
    if (data.ok) { window.location.reload(); } else { alert(data.error || 'Failed to delete livestream'); }
}

// ===== Content =====
function openAddContent() {
    editingContentId = null;
    document.getElementById('add-content-form')?.reset();
    setModalTitle('add-content-modal', 'Add Content');
    openModal('add-content-modal');
}
bindCrudForm('add-content-form', 'add_content', 'update_content', 'content_id', () => editingContentId, 'content');
async function editContent(id) {
    const data = await adminPost('get_content', { content_id: id });
    if (!data.ok) { alert(data.error || 'Failed to load content'); return; }
    const c = data.content;
    const form = document.getElementById('add-content-form');
    form.reset();
    editingContentId = c.id;
    fillForm(form, c, [
        { f: 'title', k: 'title' }, { f: 'type', k: 'type' },
        { f: 'body', k: 'body' }, { f: 'status', k: 'status' }
    ]);
    setModalTitle('add-content-modal', 'Edit Content');
    openModal('add-content-modal');
}
async function deleteContent(id) {
    if (!confirm('Are you sure you want to delete this content?')) return;
    const data = await adminPost('delete_content', { content_id: id });
    if (data.ok) { window.location.reload(); } else { alert(data.error || 'Failed to delete content'); }
}
