<?php
/**
 * CRC Congregation Admin - Events Management
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$primaryCong = Auth::primaryCongregation();
if (!$primaryCong) {
    Response::redirect('/onboarding/');
}

if (!Auth::isCongregationAdmin($primaryCong['id'])) {
    Session::flash('error', 'You do not have admin access');
    Response::redirect('/home/');
}

$congregation = $primaryCong;
$pageTitle = 'Events - ' . $congregation['name'] . ' - CRC';
$currentUser = Auth::user();

// Filter
$filter = input('filter') ?: 'upcoming';

// Build query based on filter
$where = ['e.congregation_id = ?'];
$params = [$congregation['id']];

if ($filter === 'upcoming') {
    $where[] = "e.start_datetime >= NOW()";
    $where[] = "e.status = 'published'";
    $orderBy = "e.start_datetime ASC";
} elseif ($filter === 'past') {
    $where[] = "e.start_datetime < NOW()";
    $orderBy = "e.start_datetime DESC";
} elseif ($filter === 'draft') {
    $where[] = "e.status = 'draft'";
    $orderBy = "e.created_at DESC";
} else {
    $orderBy = "e.start_datetime DESC";
}

$whereClause = implode(' AND ', $where);

// Get events
$events = Database::fetchAll(
    "SELECT e.*, u.name as created_by_name,
            (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as going_count
     FROM events e
     JOIN users u ON e.user_id = u.id
     WHERE $whereClause
     ORDER BY $orderBy
     LIMIT 50",
    $params
) ?: [];

// Get counts
$counts = [
    'upcoming' => Database::fetchColumn(
        "SELECT COUNT(*) FROM events WHERE congregation_id = ? AND start_datetime >= NOW() AND status = 'published'",
        [$congregation['id']]
    ),
    'past' => Database::fetchColumn(
        "SELECT COUNT(*) FROM events WHERE congregation_id = ? AND start_datetime < NOW()",
        [$congregation['id']]
    ),
    'draft' => Database::fetchColumn(
        "SELECT COUNT(*) FROM events WHERE congregation_id = ? AND status = 'draft'",
        [$congregation['id']]
    ),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/admin_congregation/css/admin_congregation.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .filter-tabs { display: flex; gap: 0.5rem; }
        .filter-tab { padding: 0.5rem 1rem; background: var(--gray-100); border: none; border-radius: var(--radius); cursor: pointer; font-size: 0.875rem; color: var(--gray-600); transition: var(--transition); text-decoration: none; }
        .filter-tab:hover { background: var(--gray-200); }
        .filter-tab.active { background: var(--primary); color: white; }
        .filter-tab .count { margin-left: 0.5rem; opacity: 0.7; }

        .events-grid { display: grid; gap: 1rem; }
        .event-card { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); overflow: hidden; display: flex; }
        .event-date-box { background: var(--primary); color: white; padding: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 80px; }
        .event-date-box .day { font-size: 1.75rem; font-weight: 700; line-height: 1; }
        .event-date-box .month { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .event-date-box .year { font-size: 0.7rem; opacity: 0.8; }
        .event-content { flex: 1; padding: 1rem 1.5rem; display: flex; flex-direction: column; }
        .event-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .event-title { font-weight: 600; color: var(--gray-800); font-size: 1rem; }
        .event-status { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 100px; }
        .event-status.published { background: #D1FAE5; color: #065F46; }
        .event-status.draft { background: #FEF3C7; color: #92400E; }
        .event-status.cancelled { background: #FEE2E2; color: #991B1B; }
        .event-meta { display: flex; gap: 1rem; font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem; flex-wrap: wrap; }
        .event-meta-item { display: flex; align-items: center; gap: 0.375rem; }
        .event-meta-item svg { width: 14px; height: 14px; }
        .event-description { font-size: 0.875rem; color: var(--gray-600); line-height: 1.5; margin-bottom: 0.75rem; flex: 1; }
        .event-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px solid var(--gray-100); }
        .event-stats { font-size: 0.8rem; color: var(--gray-500); }
        .event-actions { display: flex; gap: 0.5rem; }
        .btn-xs { padding: 0.25rem 0.75rem; font-size: 0.75rem; }
        .btn-outline { background: transparent; border: 1px solid var(--gray-300); color: var(--gray-600); }
        .btn-primary { background: var(--primary); color: white; border: none; }

        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; overflow-y: auto; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: var(--radius-lg); padding: 1.5rem; max-width: 600px; width: 90%; margin: 2rem; }
        .modal-header { font-weight: 600; margin-bottom: 1rem; font-size: 1.1rem; }
        .modal-body { margin-bottom: 1.5rem; max-height: 60vh; overflow-y: auto; }
        .modal-footer { display: flex; gap: 0.5rem; justify-content: flex-end; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.625rem; border: 1px solid var(--gray-300); border-radius: var(--radius); font-size: 0.875rem; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group small { font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem; display: block; }

        .empty-state { text-align: center; padding: 3rem; }
        .empty-state svg { width: 64px; height: 64px; color: var(--gray-300); margin-bottom: 1rem; }
        .empty-state h3 { color: var(--gray-700); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--gray-500); margin-bottom: 1.5rem; }

        @media (max-width: 600px) {
            .event-card { flex-direction: column; }
            .event-date-box { flex-direction: row; gap: 0.5rem; min-width: auto; padding: 0.75rem 1rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/home/" class="sidebar-logo">CRC</a>
                <span class="congregation-badge"><?= e($congregation['name']) ?></span>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin_congregation/" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="/admin_congregation/members.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    Members
                </a>
                <a href="/admin_congregation/invites.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    Invites
                </a>
                <a href="/admin_congregation/events.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Events
                </a>
                <a href="/admin_congregation/settings.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/home/" class="back-link">← Back to Home</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Events</h1>
                <p>Manage congregation events</p>
            </header>

            <div class="page-actions">
                <div class="filter-tabs">
                    <a href="?filter=upcoming" class="filter-tab <?= $filter === 'upcoming' ? 'active' : '' ?>">
                        Upcoming <span class="count"><?= $counts['upcoming'] ?></span>
                    </a>
                    <a href="?filter=past" class="filter-tab <?= $filter === 'past' ? 'active' : '' ?>">
                        Past <span class="count"><?= $counts['past'] ?></span>
                    </a>
                    <a href="?filter=draft" class="filter-tab <?= $filter === 'draft' ? 'active' : '' ?>">
                        Drafts <span class="count"><?= $counts['draft'] ?></span>
                    </a>
                </div>

                <button class="btn btn-primary" onclick="openCreateModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Create Event
                </button>
            </div>

            <!-- Events List -->
            <?php if (empty($events)): ?>
                <div class="card">
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <h3>No events found</h3>
                        <p>Create an event to bring your congregation together.</p>
                        <button class="btn btn-primary" onclick="openCreateModal()">Create Event</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="events-grid">
                    <?php foreach ($events as $event):
                        $startDate = new DateTime($event['start_datetime']);
                    ?>
                        <div class="event-card" id="event-<?= $event['id'] ?>">
                            <div class="event-date-box">
                                <span class="day"><?= $startDate->format('d') ?></span>
                                <span class="month"><?= $startDate->format('M') ?></span>
                                <span class="year"><?= $startDate->format('Y') ?></span>
                            </div>
                            <div class="event-content">
                                <div class="event-header">
                                    <span class="event-title"><?= e($event['title']) ?></span>
                                    <span class="event-status <?= $event['status'] ?>"><?= ucfirst($event['status']) ?></span>
                                </div>
                                <div class="event-meta">
                                    <span class="event-meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        <?= $startDate->format('g:i A') ?>
                                    </span>
                                    <?php if ($event['location']): ?>
                                        <span class="event-meta-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                            <?= e($event['location']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($event['description']): ?>
                                    <p class="event-description"><?= e(truncate($event['description'], 150)) ?></p>
                                <?php endif; ?>
                                <div class="event-footer">
                                    <span class="event-stats"><?= $event['going_count'] ?> going</span>
                                    <div class="event-actions">
                                        <button class="btn btn-outline btn-xs" onclick="editEvent(<?= $event['id'] ?>)">Edit</button>
                                        <?php if ($event['status'] === 'draft'): ?>
                                            <button class="btn btn-primary btn-xs" onclick="publishEvent(<?= $event['id'] ?>)">Publish</button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-xs" onclick="deleteEvent(<?= $event['id'] ?>, '<?= e(addslashes($event['title'])) ?>')">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal" id="eventModal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle">Create Event</div>
            <div class="modal-body">
                <input type="hidden" id="eventId" value="">

                <div class="form-group">
                    <label>Event Title *</label>
                    <input type="text" id="eventTitle" placeholder="Enter event title" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="eventDescription" placeholder="Describe the event..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" id="startDate" required>
                    </div>
                    <div class="form-group">
                        <label>Start Time *</label>
                        <input type="time" id="startTime" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" id="endDate">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" id="endTime">
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" id="eventLocation" placeholder="Where will this event take place?">
                </div>

                <div class="form-group">
                    <label>Event Type</label>
                    <select id="eventType">
                        <option value="general">General</option>
                        <option value="worship">Worship Service</option>
                        <option value="prayer">Prayer Meeting</option>
                        <option value="youth">Youth Event</option>
                        <option value="outreach">Outreach</option>
                        <option value="fellowship">Fellowship</option>
                        <option value="conference">Conference</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select id="eventStatus">
                        <option value="draft">Draft (not visible to members)</option>
                        <option value="published">Published (visible to members)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('eventModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveEvent()">Save Event</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create Event';
            document.getElementById('eventId').value = '';
            document.getElementById('eventTitle').value = '';
            document.getElementById('eventDescription').value = '';
            document.getElementById('startDate').value = '';
            document.getElementById('startTime').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('endTime').value = '';
            document.getElementById('eventLocation').value = '';
            document.getElementById('eventType').value = 'general';
            document.getElementById('eventStatus').value = 'draft';
            document.getElementById('eventModal').classList.add('show');
        }

        async function editEvent(eventId) {
            try {
                const response = await fetch('/admin_congregation/api/events.php?action=get&id=' + eventId, {
                    headers: { 'X-CSRF-Token': getCSRFToken() }
                });
                const data = await response.json();
                if (data.ok && data.event) {
                    const event = data.event;
                    document.getElementById('modalTitle').textContent = 'Edit Event';
                    document.getElementById('eventId').value = event.id;
                    document.getElementById('eventTitle').value = event.title;
                    document.getElementById('eventDescription').value = event.description || '';
                    document.getElementById('startDate').value = event.start_datetime.split(' ')[0];
                    document.getElementById('startTime').value = event.start_datetime.split(' ')[1].substring(0, 5);
                    if (event.end_datetime) {
                        document.getElementById('endDate').value = event.end_datetime.split(' ')[0];
                        document.getElementById('endTime').value = event.end_datetime.split(' ')[1].substring(0, 5);
                    }
                    document.getElementById('eventLocation').value = event.location || '';
                    document.getElementById('eventType').value = event.event_type || 'general';
                    document.getElementById('eventStatus').value = event.status;
                    document.getElementById('eventModal').classList.add('show');
                } else {
                    showToast('Failed to load event', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        async function saveEvent() {
            const eventId = document.getElementById('eventId').value;
            const title = document.getElementById('eventTitle').value;
            const description = document.getElementById('eventDescription').value;
            const startDate = document.getElementById('startDate').value;
            const startTime = document.getElementById('startTime').value;
            const endDate = document.getElementById('endDate').value;
            const endTime = document.getElementById('endTime').value;
            const location = document.getElementById('eventLocation').value;
            const eventType = document.getElementById('eventType').value;
            const status = document.getElementById('eventStatus').value;

            if (!title || !startDate || !startTime) {
                showToast('Please fill in required fields', 'error');
                return;
            }

            const eventData = {
                action: eventId ? 'update' : 'create',
                id: eventId || undefined,
                title: title,
                description: description,
                start_datetime: startDate + ' ' + startTime + ':00',
                end_datetime: endDate && endTime ? endDate + ' ' + endTime + ':00' : null,
                location: location,
                event_type: eventType,
                status: status
            };

            try {
                const response = await fetch('/admin_congregation/api/events.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify(eventData)
                });
                const data = await response.json();
                if (data.ok) {
                    showToast(eventId ? 'Event updated' : 'Event created');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to save event', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
            closeModal('eventModal');
        }

        async function publishEvent(eventId) {
            try {
                const response = await fetch('/admin_congregation/api/events.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'publish', id: eventId })
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Event published');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to publish', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        async function deleteEvent(eventId, title) {
            if (!confirm('Are you sure you want to delete "' + title + '"?')) return;

            try {
                const response = await fetch('/admin_congregation/api/events.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'delete', id: eventId })
                });
                const data = await response.json();
                if (data.ok) {
                    showToast('Event deleted');
                    document.getElementById('event-' + eventId).remove();
                } else {
                    showToast(data.error || 'Failed to delete', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal(modal.id);
            });
        });
    </script>
</body>
</html>
