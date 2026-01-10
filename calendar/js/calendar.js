/**
 * CRC Calendar JavaScript
 * Enhanced with Month, Week, Day view support
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initCreateForm();
    initEditForm();
    initAllDayToggle();
    initRecurrenceToggle();
    initKeyboardShortcuts();
    initScrollToCurrentTime();
    initEventPopups();

    // Start reminder checking
    if (document.querySelector('[data-calendar]')) {
        checkReminders();
        setInterval(checkReminders, 120000); // Every 2 minutes
    }
});

/**
 * Initialize All Day toggle
 */
function initAllDayToggle() {
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
}

/**
 * Initialize Recurrence toggle
 */
function initRecurrenceToggle() {
    const recurrenceSelect = document.getElementById('recurrence');
    if (recurrenceSelect) {
        recurrenceSelect.addEventListener('change', function() {
            const endGroup = document.getElementById('recurrence-end-group');
            if (endGroup) {
                endGroup.style.display = this.value !== 'none' ? 'block' : 'none';
            }
        });
    }
}

/**
 * Initialize Create Event Form
 */
function initCreateForm() {
    const form = document.getElementById('create-event-form');
    if (!form) return;

    // Pre-fill date from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const dateParam = urlParams.get('date');
    if (dateParam) {
        const startDateInput = document.getElementById('start_date');
        if (startDateInput) {
            startDateInput.value = dateParam;
        }
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading">Creating...</span>';

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
                showSuccess('Event created successfully!');
                setTimeout(() => {
                    window.location.href = '/calendar/event.php?id=' + data.id;
                }, 500);
            } else {
                showError(data.error || 'Failed to create event');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Failed to create event. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

/**
 * Initialize Edit Event Form
 */
function initEditForm() {
    const form = document.getElementById('edit-event-form');
    if (!form) return;

    const eventId = form.dataset.eventId;
    const isPersonal = form.dataset.personal === '1';

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading">Saving...</span>';

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
                showSuccess('Event updated successfully!');
                setTimeout(() => {
                    window.location.href = '/calendar/event.php?id=' + eventId;
                }, 500);
            } else {
                showError(data.error || 'Failed to update event');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Failed to update event. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

/**
 * Delete Event
 */
async function deleteEvent(eventId) {
    if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('event_id', eventId);
    formData.append('is_personal', '1');

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
            showSuccess('Event deleted successfully!');
            setTimeout(() => {
                window.location.href = '/calendar/';
            }, 500);
        } else {
            showError(data.error || 'Failed to delete event');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to delete event. Please try again.');
    }
}

/**
 * Add to Google Calendar
 */
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

/**
 * Download ICS File
 */
function downloadICS() {
    if (typeof eventData === 'undefined') return;

    const formatDate = (date) => {
        return new Date(date).toISOString().replace(/-|:|\.\d+/g, '').slice(0, -1) + 'Z';
    };

    const icsContent = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//CRC//Calendar//EN',
        'BEGIN:VEVENT',
        'DTSTART:' + formatDate(eventData.start),
        'DTEND:' + formatDate(eventData.end),
        'SUMMARY:' + eventData.title,
        eventData.description ? 'DESCRIPTION:' + eventData.description.replace(/\n/g, '\\n') : '',
        eventData.location ? 'LOCATION:' + eventData.location : '',
        'END:VEVENT',
        'END:VCALENDAR'
    ].filter(Boolean).join('\r\n');

    const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = eventData.title.replace(/[^a-z0-9]/gi, '_') + '.ics';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Initialize keyboard shortcuts
 */
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Don't trigger if typing in an input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        const urlParams = new URLSearchParams(window.location.search);
        const currentView = urlParams.get('view') || 'month';

        switch(e.key) {
            case 't':
            case 'T':
                // Go to today
                e.preventDefault();
                const today = new Date();
                window.location.href = `?view=${currentView}&year=${today.getFullYear()}&month=${today.getMonth() + 1}&day=${today.getDate()}`;
                break;

            case 'm':
            case 'M':
                // Switch to month view
                e.preventDefault();
                window.location.href = window.location.href.replace(/view=[^&]+/, 'view=month');
                break;

            case 'w':
            case 'W':
                // Switch to week view
                e.preventDefault();
                window.location.href = window.location.href.replace(/view=[^&]+/, 'view=week');
                break;

            case 'd':
            case 'D':
                // Switch to day view
                e.preventDefault();
                window.location.href = window.location.href.replace(/view=[^&]+/, 'view=day');
                break;

            case 'n':
            case 'N':
            case 'c':
            case 'C':
                // Create new event
                e.preventDefault();
                window.location.href = '/calendar/create.php';
                break;

            case 'ArrowLeft':
                // Previous
                e.preventDefault();
                const prevBtn = document.querySelector('.controls-left .nav-btn:first-of-type');
                if (prevBtn) prevBtn.click();
                break;

            case 'ArrowRight':
                // Next
                e.preventDefault();
                const nextBtn = document.querySelector('.controls-left .nav-btn:last-of-type');
                if (nextBtn) nextBtn.click();
                break;
        }
    });
}

/**
 * Scroll to current time in day/week view
 */
function initScrollToCurrentTime() {
    const container = document.querySelector('.week-grid-container, .day-grid-container');
    const timeLine = document.querySelector('.current-time-line');

    if (container && timeLine) {
        // Scroll to show current time, with some offset
        const containerHeight = container.clientHeight;
        const lineTop = parseInt(timeLine.style.top) || 0;
        const scrollTo = Math.max(0, lineTop - containerHeight / 3);

        container.scrollTop = scrollTo;
    }
}

/**
 * Initialize event popups/tooltips
 */
function initEventPopups() {
    const events = document.querySelectorAll('.week-event, .day-event, .event-pill');

    events.forEach(event => {
        event.addEventListener('mouseenter', function(e) {
            // Could add tooltip functionality here
        });
    });
}

/**
 * Check for due reminders
 */
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

/**
 * Show browser notification
 */
function showNotification(reminder) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const startTime = new Date(reminder.start_datetime).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });

        new Notification('Event Reminder', {
            body: `${reminder.event_title} at ${startTime}`,
            icon: '/assets/icons/calendar.png',
            tag: 'reminder-' + reminder.id
        });
    }
}

/**
 * Request notification permission
 */
if ('Notification' in window && Notification.permission === 'default') {
    document.addEventListener('click', function requestNotifPermission() {
        Notification.requestPermission();
        document.removeEventListener('click', requestNotifPermission);
    }, { once: true });
}


/**
 * Show error message
 */
function showError(message) {
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
        <span>${escapeHtml(message)}</span>
    `;

    const container = document.querySelector('.form-page') ||
                     document.querySelector('.event-form') ||
                     document.querySelector('.calendar-main');

    if (container) {
        container.insertBefore(error, container.firstChild);
        setTimeout(() => error.remove(), 5000);
    }
}

/**
 * Show success message
 */
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
        <span>${escapeHtml(message)}</span>
    `;

    const container = document.querySelector('.form-page') ||
                     document.querySelector('.event-form') ||
                     document.querySelector('.calendar-main');

    if (container) {
        container.insertBefore(success, container.firstChild);
        setTimeout(() => success.remove(), 3000);
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format time for display
 */
function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
}

/**
 * Fetch events via AJAX (for dynamic updates)
 */
async function fetchEvents(startDate, endDate, sources = 'all') {
    const formData = new FormData();
    formData.append('action', 'list_all');
    formData.append('start', startDate);
    formData.append('end', endDate);
    formData.append('sources', sources);

    try {
        const response = await fetch('/calendar/api/events.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });

        const data = await response.json();

        if (data.ok) {
            return data.events;
        }
    } catch (error) {
        console.error('Error fetching events:', error);
    }

    return [];
}

/**
 * Quick add event (for future enhancement)
 */
function quickAddEvent(date, time) {
    const url = new URL('/calendar/create.php', window.location.origin);
    url.searchParams.set('date', date);
    if (time) {
        url.searchParams.set('time', time);
    }
    window.location.href = url.toString();
}
