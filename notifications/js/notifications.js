/**
 * CRC Notifications JavaScript
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

// Mark single notification as read
async function markRead(notificationId) {
    try {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);

        const response = await fetch('/notifications/api/notifications.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
                item.classList.add('read');
                const actionBtn = item.querySelector('.action-btn:not(.delete)');
                if (actionBtn) actionBtn.remove();
            }
            updateUnreadCount();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Mark all notifications as read
async function markAllRead() {
    if (!confirm('Mark all notifications as read?')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'mark_all_read');

        const response = await fetch('/notifications/api/notifications.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
            });
            showToast('All notifications marked as read', 'success');
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to mark notifications as read', 'error');
    }
}

// Delete single notification
async function deleteNotification(notificationId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('notification_id', notificationId);

        const response = await fetch('/notifications/api/notifications.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    item.remove();
                    checkEmptyState();
                }, 300);
            }
            updateUnreadCount();
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to delete notification', 'error');
    }
}

// Clear all notifications
async function clearAllNotifications() {
    if (!confirm('Are you sure you want to delete ALL notifications? This cannot be undone.')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'clear_all');

        const response = await fetch('/notifications/api/notifications.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            showToast('All notifications cleared', 'success');
            setTimeout(() => window.location.href = '/notifications/', 1000);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to clear notifications', 'error');
    }
}

// Handle notification click
function handleNotificationClick(notificationId, link) {
    // Mark as read first
    markRead(notificationId);

    // Navigate if there's a link
    if (link) {
        setTimeout(() => {
            window.location.href = link;
        }, 100);
    }
}

// Update unread count in UI
async function updateUnreadCount() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_unread_count');

        const response = await fetch('/notifications/api/notifications.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            const count = data.count;

            // Update filter badge
            const unreadFilter = document.querySelector('.filter-btn[href*="unread"] .count');
            if (unreadFilter) {
                unreadFilter.textContent = count;
            }

            // Update page subtitle
            const subtitle = document.querySelector('.page-title p');
            if (subtitle) {
                subtitle.textContent = `${count} unread notification${count !== 1 ? 's' : ''}`;
            }

            // Update navbar badge if exists
            const navBadge = document.querySelector('.notification-badge');
            if (navBadge) {
                if (count > 0) {
                    navBadge.textContent = count > 99 ? '99+' : count;
                    navBadge.style.display = 'block';
                } else {
                    navBadge.style.display = 'none';
                }
            }

            // Hide mark all read button if no unread
            const markAllBtn = document.querySelector('button[onclick="markAllRead()"]');
            if (markAllBtn && count === 0) {
                markAllBtn.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error updating count:', error);
    }
}

// Check if notifications list is empty
function checkEmptyState() {
    const container = document.querySelector('.notifications-container');
    const items = document.querySelectorAll('.notification-item');

    if (container && items.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="border-radius: 0;">
                <div class="empty-icon">🔔</div>
                <h3>No notifications</h3>
                <p>You don't have any notifications.</p>
            </div>
        `;
    }
}

// Settings form handler
const settingsForm = document.getElementById('settings-form');
if (settingsForm) {
    settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(settingsForm);
        formData.append('action', 'save_settings');

        // Add unchecked checkboxes
        settingsForm.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (!cb.checked) {
                formData.set(cb.name, '0');
            } else {
                formData.set(cb.name, '1');
            }
        });

        try {
            const response = await fetch('/notifications/api/notifications.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                showToast('Settings saved', 'success');
            } else {
                showToast(data.error || 'Failed to save settings', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Failed to save settings', 'error');
        }
    });
}

// Push notification subscription
async function subscribeToPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.log('Push notifications not supported');
        return false;
    }

    try {
        const registration = await navigator.serviceWorker.ready;

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY)
        });

        // Send subscription to server
        const formData = new FormData();
        formData.append('action', 'subscribe_push');
        formData.append('subscription', JSON.stringify(subscription));

        await fetch('/notifications/api/notifications.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        return true;
    } catch (error) {
        console.error('Push subscription error:', error);
        return false;
    }
}

// Helper: Convert VAPID key
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Toast notification
function showToast(message, type = 'info') {
    // Remove existing toast
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span>${message}</span>
    `;
    document.body.appendChild(toast);

    // Show
    setTimeout(() => toast.classList.add('show'), 10);

    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Poll for new notifications (every 60 seconds)
function startNotificationPolling() {
    setInterval(async () => {
        try {
            const formData = new FormData();
            formData.append('action', 'get_unread_count');

            const response = await fetch('/notifications/api/notifications.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok) {
                const navBadge = document.querySelector('.notification-badge');
                if (navBadge) {
                    if (data.count > 0) {
                        navBadge.textContent = data.count > 99 ? '99+' : data.count;
                        navBadge.style.display = 'block';
                    } else {
                        navBadge.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            // Silent fail for polling
        }
    }, 60000);
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    startNotificationPolling();
});
