/**
 * CRC Calendar JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize forms
    initCreateForm();
    initEditForm();

    // All day toggle
    const allDayCheckbox = document.getElementById('all_day');
    if (allDayCheckbox) {
        allDayCheckbox.addEventListener('change', function() {
            const timeInputs = document.querySelectorAll('input[type="time"]');
            timeInputs.forEach(input => {
                input.disabled = this.checked;
                if (this.checked) {
                    input.value = '';
                }
            });
        });
    }

    // Recurrence toggle
    const recurrenceSelect = document.getElementById('recurrence');
    if (recurrenceSelect) {
        recurrenceSelect.addEventListener('change', function() {
            const endGroup = document.getElementById('recurrence-end-group');
            if (endGroup) {
                endGroup.style.display = this.value !== 'none' ? 'block' : 'none';
            }
        });
    }

    // User menu
    initUserMenu();
});

function initCreateForm() {
    const form = document.getElementById('create-event-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'create');

        try {
            const response = await fetch('/calendar/api/events.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.ok) {
                window.location.href = '/calendar/event.php?id=' + data.id;
            } else {
                showError(data.error || 'Failed to create event');
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Failed to create event');
        }
    });
}

function initEditForm() {
    const form = document.getElementById('edit-event-form');
    if (!form) return;

    const eventId = form.dataset.eventId;
    const isPersonal = form.dataset.personal === '1';

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'update');
        formData.append('event_id', eventId);
        formData.append('is_personal', isPersonal ? '1' : '0');

        try {
            const response = await fetch('/calendar/api/events.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.ok) {
                window.location.href = '/calendar/event.php?id=' + eventId;
            } else {
                showError(data.error || 'Failed to update event');
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Failed to update event');
        }
    });
}

async function deleteEvent(eventId) {
    if (!confirm('Are you sure you want to delete this event?')) {
        return;
    }

    const isPersonal = document.querySelector('.event-detail-page') !== null;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('event_id', eventId);
    formData.append('is_personal', '1'); // Default to personal, will be checked server-side

    try {
        const response = await fetch('/calendar/api/events.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.href = '/calendar/';
        } else {
            showError(data.error || 'Failed to delete event');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to delete event');
    }
}

function addToGoogleCalendar() {
    if (typeof eventData === 'undefined') return;

    const startDate = new Date(eventData.start).toISOString().replace(/-|:|\.\d+/g, '');
    const endDate = new Date(eventData.end).toISOString().replace(/-|:|\.\d+/g, '');

    const url = new URL('https://calendar.google.com/calendar/render');
    url.searchParams.set('action', 'TEMPLATE');
    url.searchParams.set('text', eventData.title);
    url.searchParams.set('dates', startDate + '/' + endDate);
    if (eventData.location) {
        url.searchParams.set('location', eventData.location);
    }
    if (eventData.description) {
        url.searchParams.set('details', eventData.description);
    }

    window.open(url.toString(), '_blank');
}

function showError(message) {
    // Remove existing error
    const existing = document.querySelector('.error-message');
    if (existing) existing.remove();

    const error = document.createElement('div');
    error.className = 'error-message';
    error.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
        <span>${message}</span>
    `;

    const form = document.querySelector('.event-form') || document.querySelector('.form-page');
    if (form) {
        form.insertBefore(error, form.firstChild);
        setTimeout(() => error.remove(), 5000);
    }
}

function showSuccess(message) {
    const existing = document.querySelector('.success-message');
    if (existing) existing.remove();

    const success = document.createElement('div');
    success.className = 'success-message';
    success.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>${message}</span>
    `;

    const form = document.querySelector('.event-form') || document.querySelector('.form-page');
    if (form) {
        form.insertBefore(success, form.firstChild);
        setTimeout(() => success.remove(), 3000);
    }
}

function initUserMenu() {
    const menuBtn = document.querySelector('.user-menu-btn');
    const dropdown = document.querySelector('.user-dropdown');

    if (menuBtn && dropdown) {
        menuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', function() {
            dropdown.classList.remove('show');
        });
    }
}

// Check for due reminders periodically
async function checkReminders() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_due');

        const response = await fetch('/calendar/api/reminders.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });

        const data = await response.json();

        if (data.ok && data.reminders && data.reminders.length > 0) {
            data.reminders.forEach(reminder => {
                showNotification(reminder);
            });
        }
    } catch (error) {
        console.error('Error checking reminders:', error);
    }
}

function showNotification(reminder) {
    // Browser notification if supported
    if ('Notification' in window && Notification.permission === 'granted') {
        const startTime = new Date(reminder.start_datetime).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });

        new Notification('Event Reminder', {
            body: `${reminder.event_title} at ${startTime}`,
            icon: '/assets/icons/calendar.png'
        });
    }
}

// Request notification permission
if ('Notification' in window && Notification.permission === 'default') {
    // Will request when user interacts with page
    document.addEventListener('click', function requestNotifPermission() {
        Notification.requestPermission();
        document.removeEventListener('click', requestNotifPermission);
    }, { once: true });
}

// Check reminders every 2 minutes
setInterval(checkReminders, 120000);
