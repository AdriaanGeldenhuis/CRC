/**
 * CRC Congregation Admin JavaScript
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

// Invite Form
const inviteForm = document.getElementById('invite-form');
if (inviteForm) {
    inviteForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(inviteForm);
        formData.append('action', 'invite_member');

        try {
            const response = await fetch('/congregation/admin/api/congregation.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('Invite sent successfully');
                closeModal('invite-modal');
                inviteForm.reset();
            } else {
                alert(data.error || 'Failed to send invite');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to send invite');
        }
    });
}

// Edit Member
async function editMember(memberId) {
    // Could implement edit modal here
    alert('Edit member functionality - implement modal with role change');
}

// Remove Member
async function removeMember(memberId) {
    if (!confirm('Are you sure you want to remove this member?')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'remove_member');
        formData.append('member_id', memberId);

        const response = await fetch('/congregation/admin/api/congregation.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to remove member');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to remove member');
    }
}

// Approve Member
async function approveMember(memberId) {
    try {
        const formData = new FormData();
        formData.append('action', 'approve_member');
        formData.append('member_id', memberId);

        const response = await fetch('/congregation/admin/api/congregation.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to approve member');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to approve member');
    }
}

// Update Member Role
async function updateMemberRole(memberId, newRole) {
    try {
        const formData = new FormData();
        formData.append('action', 'update_member_role');
        formData.append('member_id', memberId);
        formData.append('role', newRole);

        const response = await fetch('/congregation/admin/api/congregation.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to update role');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update role');
    }
}

// Event Form
const eventForm = document.getElementById('event-form');
if (eventForm) {
    eventForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(eventForm);
        formData.append('action', formData.get('event_id') ? 'update_event' : 'add_event');

        try {
            const response = await fetch('/congregation/admin/api/congregation.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('Event saved successfully');
                window.location.href = '/congregation/admin/events.php';
            } else {
                alert(data.error || 'Failed to save event');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save event');
        }
    });
}

// Delete Event
async function deleteEvent(eventId) {
    if (!confirm('Are you sure you want to delete this event?')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete_event');
        formData.append('event_id', eventId);

        const response = await fetch('/congregation/admin/api/congregation.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to delete event');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to delete event');
    }
}

// Announcement Form
const announcementForm = document.getElementById('announcement-form');
if (announcementForm) {
    announcementForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(announcementForm);
        formData.append('action', 'add_announcement');

        try {
            const response = await fetch('/congregation/admin/api/congregation.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('Announcement posted successfully');
                window.location.href = '/congregation/admin/announcements.php';
            } else {
                alert(data.error || 'Failed to post announcement');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to post announcement');
        }
    });
}

// Homecell Form
const homecellForm = document.getElementById('homecell-form');
if (homecellForm) {
    homecellForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(homecellForm);
        formData.append('action', 'add_homecell');

        try {
            const response = await fetch('/congregation/admin/api/congregation.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                alert('Homecell created successfully');
                window.location.href = '/congregation/admin/homecells.php';
            } else {
                alert(data.error || 'Failed to create homecell');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to create homecell');
        }
    });
}

// Settings Form
const congSettingsForm = document.getElementById('congregation-settings-form');
if (congSettingsForm) {
    congSettingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(congSettingsForm);
        formData.append('action', 'update_settings');

        try {
            const response = await fetch('/congregation/admin/api/congregation.php', {
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
