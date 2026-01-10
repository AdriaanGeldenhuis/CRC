<?php
/**
 * CRC Morning Study - Live Room
 * Video stream + Chat + Shared Notes
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    Response::redirect('/morning_watch/');
}

// Get session
$session = Database::fetchOne(
    "SELECT ms.*, u.name as author_name
     FROM morning_sessions ms
     LEFT JOIN users u ON ms.created_by = u.id
     WHERE ms.id = ?
     AND (ms.scope = 'global' OR ms.congregation_id = ?)
     AND ms.published_at IS NOT NULL",
    [$sessionId, $primaryCong['id'] ?? 0]
);

if (!$session) {
    Response::redirect('/morning_watch/');
}

$pageTitle = $session['title'] . " - Live Room";

// Parse study questions
$studyQuestions = [];
if ($session['study_questions']) {
    $studyQuestions = json_decode($session['study_questions'], true) ?? [];
}

// Check if user is admin
$isAdmin = Auth::isCongregationAdmin($session['congregation_id']) ||
           Auth::globalRole() === 'super_admin';

// Helper to convert YouTube URL to embed URL
function getEmbedUrl($url) {
    if (!$url) return null;

    // YouTube patterns
    if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=1';
    }
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=1';
    }
    if (preg_match('/youtube\.com\/live\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=1';
    }

    // Vimeo pattern
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
        return 'https://player.vimeo.com/video/' . $matches[1] . '?autoplay=1';
    }

    // Already an embed URL or other
    return $url;
}

$embedUrl = getEmbedUrl($session['live_status'] === 'ended' ? $session['replay_url'] : $session['stream_url']);
$isLive = $session['live_status'] === 'live';
$isEnded = $session['live_status'] === 'ended';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/morning_watch/css/morning_watch.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0F172A; color: #E2E8F0; }
        .room-layout { display: grid; grid-template-columns: 1fr 380px; height: 100vh; }
        .room-main { display: flex; flex-direction: column; overflow: hidden; }
        .room-sidebar { background: #1E293B; border-left: 1px solid #334155; display: flex; flex-direction: column; }

        /* Video area */
        .video-container { position: relative; background: #000; flex-shrink: 0; }
        .video-wrapper { position: relative; padding-top: 56.25%; }
        .video-wrapper iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
        .video-placeholder { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); }
        .video-placeholder-text { text-align: center; color: #94A3B8; }
        .video-placeholder-icon { font-size: 4rem; margin-bottom: 1rem; }

        .live-badge { position: absolute; top: 1rem; left: 1rem; background: #EF4444; color: white; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; animation: pulse 2s infinite; z-index: 10; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .ended-badge { background: #6B7280; animation: none; }
        .attendee-count { position: absolute; top: 1rem; right: 1rem; background: rgba(0,0,0,0.7); color: white; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.8rem; z-index: 10; }

        /* Study info */
        .study-info { padding: 1rem 1.5rem; background: #1E293B; flex-shrink: 0; }
        .study-title { font-size: 1.25rem; font-weight: 600; margin: 0 0 0.5rem; }
        .study-scripture { color: #94A3B8; font-size: 0.9rem; }
        .study-key-verse { background: #334155; padding: 0.75rem 1rem; border-radius: 8px; margin-top: 0.75rem; border-left: 3px solid #6366F1; font-style: italic; }
        .study-questions { margin-top: 1rem; }
        .study-questions h4 { font-size: 0.8rem; text-transform: uppercase; color: #94A3B8; margin: 0 0 0.5rem; }
        .study-questions ul { margin: 0; padding-left: 1.25rem; }
        .study-questions li { margin-bottom: 0.5rem; color: #CBD5E1; font-size: 0.9rem; }

        /* Sidebar tabs */
        .sidebar-tabs { display: flex; border-bottom: 1px solid #334155; flex-shrink: 0; }
        .sidebar-tab { flex: 1; padding: 0.75rem; text-align: center; cursor: pointer; color: #94A3B8; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; }
        .sidebar-tab:hover { color: #E2E8F0; }
        .sidebar-tab.active { color: #6366F1; border-bottom: 2px solid #6366F1; }
        .sidebar-tab-badge { background: #EF4444; color: white; font-size: 0.65rem; padding: 0.125rem 0.375rem; border-radius: 9999px; margin-left: 0.25rem; }

        .sidebar-content { flex: 1; overflow: hidden; display: flex; flex-direction: column; }
        .tab-panel { display: none; flex: 1; flex-direction: column; overflow: hidden; }
        .tab-panel.active { display: flex; }

        /* Chat */
        .chat-messages { flex: 1; overflow-y: auto; padding: 1rem; }
        .chat-message { margin-bottom: 0.75rem; }
        .chat-message-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; }
        .chat-avatar { width: 24px; height: 24px; border-radius: 50%; background: #6366F1; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: white; }
        .chat-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .chat-name { font-weight: 500; font-size: 0.85rem; }
        .chat-time { color: #64748B; font-size: 0.7rem; }
        .chat-text { color: #CBD5E1; font-size: 0.9rem; line-height: 1.4; padding-left: 2rem; }
        .chat-question { background: #312E81; padding: 0.5rem 0.75rem; border-radius: 8px; margin-left: 2rem; }

        .chat-input-area { padding: 1rem; border-top: 1px solid #334155; flex-shrink: 0; }
        .chat-input-row { display: flex; gap: 0.5rem; }
        .chat-input { flex: 1; background: #334155; border: 1px solid #475569; border-radius: 8px; padding: 0.625rem; color: white; font-size: 0.9rem; }
        .chat-input:focus { outline: none; border-color: #6366F1; }
        .chat-send { background: #6366F1; border: none; color: white; padding: 0.625rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .chat-send:hover { background: #4F46E5; }
        .chat-send:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Notes */
        .notes-list { flex: 1; overflow-y: auto; padding: 1rem; }
        .note-item { background: #334155; border-radius: 8px; padding: 0.75rem; margin-bottom: 0.75rem; }
        .note-item.pinned { border: 1px solid #6366F1; background: #1E1B4B; }
        .note-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
        .note-author { font-size: 0.8rem; color: #94A3B8; }
        .note-pin-badge { background: #6366F1; color: white; font-size: 0.65rem; padding: 0.125rem 0.5rem; border-radius: 4px; }
        .note-content { color: #E2E8F0; font-size: 0.9rem; line-height: 1.5; }
        .note-actions { margin-top: 0.5rem; display: flex; gap: 0.5rem; }
        .note-action { background: none; border: none; color: #64748B; font-size: 0.75rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 4px; }
        .note-action:hover { background: #475569; color: #E2E8F0; }

        .note-input-area { padding: 1rem; border-top: 1px solid #334155; flex-shrink: 0; }
        .note-input { width: 100%; background: #334155; border: 1px solid #475569; border-radius: 8px; padding: 0.625rem; color: white; font-size: 0.9rem; min-height: 60px; resize: vertical; margin-bottom: 0.5rem; }
        .note-input:focus { outline: none; border-color: #6366F1; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #6366F1; color: white; }
        .btn-primary:hover { background: #4F46E5; }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }

        /* Header */
        .room-header { padding: 0.75rem 1rem; background: #1E293B; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #334155; }
        .room-header a { color: #94A3B8; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
        .room-header a:hover { color: #E2E8F0; }

        /* Mobile */
        @media (max-width: 900px) {
            .room-layout { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
            .room-sidebar { height: 50vh; border-left: none; border-top: 1px solid #334155; }
            .study-info { display: none; }
        }
    </style>
</head>
<body>
    <div class="room-layout">
        <!-- Main Content -->
        <div class="room-main">
            <div class="room-header">
                <a href="/morning_watch/">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    Back to Morning Study
                </a>
                <?php if ($isAdmin && !$isEnded): ?>
                    <button class="btn btn-sm btn-primary" onclick="endSession()">End Session</button>
                <?php endif; ?>
            </div>

            <!-- Video -->
            <div class="video-container">
                <?php if ($isLive): ?>
                    <span class="live-badge">LIVE</span>
                <?php elseif ($isEnded): ?>
                    <span class="live-badge ended-badge">REPLAY</span>
                <?php endif; ?>
                <span class="attendee-count" id="attendeeCount">0 watching</span>

                <div class="video-wrapper">
                    <?php if ($embedUrl): ?>
                        <iframe src="<?= e($embedUrl) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    <?php else: ?>
                        <div class="video-placeholder">
                            <div class="video-placeholder-text">
                                <div class="video-placeholder-icon">📺</div>
                                <p><?= $isLive ? 'Stream starting soon...' : 'No video available' ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Study Info -->
            <div class="study-info">
                <h1 class="study-title"><?= e($session['title']) ?></h1>
                <p class="study-scripture"><?= e($session['scripture_ref']) ?></p>

                <?php if ($session['key_verse']): ?>
                    <div class="study-key-verse">
                        <?= e($session['key_verse']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($studyQuestions): ?>
                    <div class="study-questions">
                        <h4>Study Questions</h4>
                        <ul>
                            <?php foreach ($studyQuestions as $q): ?>
                                <li><?= e($q) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="room-sidebar">
            <div class="sidebar-tabs">
                <div class="sidebar-tab active" data-tab="chat">Chat</div>
                <div class="sidebar-tab" data-tab="notes">Notes</div>
                <div class="sidebar-tab" data-tab="prayers">Prayers</div>
            </div>

            <div class="sidebar-content">
                <!-- Chat Panel -->
                <div class="tab-panel active" id="chat-panel">
                    <div class="chat-messages" id="chatMessages"></div>
                    <div class="chat-input-area">
                        <div class="chat-input-row">
                            <input type="text" class="chat-input" id="chatInput" placeholder="Type a message..." maxlength="500">
                            <button class="chat-send" id="chatSend">Send</button>
                        </div>
                    </div>
                </div>

                <!-- Notes Panel -->
                <div class="tab-panel" id="notes-panel">
                    <div class="notes-list" id="notesList"></div>
                    <div class="note-input-area">
                        <textarea class="note-input" id="noteInput" placeholder="Share an insight..." maxlength="2000"></textarea>
                        <button class="btn btn-primary btn-sm" id="noteAdd">Add Note</button>
                    </div>
                </div>

                <!-- Prayers Panel -->
                <div class="tab-panel" id="prayers-panel">
                    <div class="notes-list" id="prayersList"></div>
                    <div class="note-input-area">
                        <textarea class="note-input" id="prayerInput" placeholder="Share a prayer request..." maxlength="1000"></textarea>
                        <label style="display: flex; align-items: center; gap: 0.5rem; color: #94A3B8; font-size: 0.8rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="prayerPrivate"> Keep private
                        </label>
                        <button class="btn btn-primary btn-sm" id="prayerAdd">Add Prayer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const SESSION_ID = <?= $sessionId ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const CURRENT_USER_ID = <?= $user['id'] ?>;
        let lastMessageId = 0;
        let chatSendDisabled = false;

        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        // Tab switching
        document.querySelectorAll('.sidebar-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab + '-panel').classList.add('active');
            });
        });

        // Join session
        async function joinSession() {
            try {
                const formData = new FormData();
                formData.append('action', 'join');
                formData.append('session_id', SESSION_ID);
                formData.append('mode', '<?= $isEnded ? 'replay' : 'live' ?>');

                await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
            } catch (e) {}
        }

        // Leave session on unload
        window.addEventListener('beforeunload', () => {
            const formData = new FormData();
            formData.append('action', 'leave');
            formData.append('session_id', SESSION_ID);
            formData.append('mode', '<?= $isEnded ? 'replay' : 'live' ?>');
            navigator.sendBeacon('/morning_watch/api/study.php?' + new URLSearchParams({
                csrf_token: getCSRFToken()
            }), formData);
        });

        // Fetch messages (polling)
        async function fetchMessages() {
            try {
                const formData = new FormData();
                formData.append('action', 'fetch_messages');
                formData.append('session_id', SESSION_ID);
                formData.append('after_id', lastMessageId);

                const response = await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();

                if (data.ok && data.messages) {
                    const container = document.getElementById('chatMessages');
                    const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;

                    data.messages.forEach(msg => {
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                            const initials = msg.user_name.split(' ').map(n => n[0]).join('').substring(0, 2);
                            const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                            const isQuestion = msg.message_type === 'question';

                            container.innerHTML += `
                                <div class="chat-message">
                                    <div class="chat-message-header">
                                        <div class="chat-avatar">${msg.user_avatar ? '<img src="'+msg.user_avatar+'">' : initials}</div>
                                        <span class="chat-name">${msg.user_name}</span>
                                        <span class="chat-time">${time}</span>
                                    </div>
                                    <div class="chat-text ${isQuestion ? 'chat-question' : ''}">${msg.message}</div>
                                </div>
                            `;
                        }
                    });

                    if (wasAtBottom) {
                        container.scrollTop = container.scrollHeight;
                    }

                    document.getElementById('attendeeCount').textContent = (data.attendee_count || 0) + ' watching';
                }
            } catch (e) {}
        }

        // Send chat message
        async function sendMessage() {
            if (chatSendDisabled) return;

            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;

            chatSendDisabled = true;
            document.getElementById('chatSend').disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'post_message');
                formData.append('session_id', SESSION_ID);
                formData.append('message', message);
                formData.append('message_type', 'chat');

                const response = await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();

                if (data.ok) {
                    input.value = '';
                    fetchMessages();
                }
            } catch (e) {}

            setTimeout(() => {
                chatSendDisabled = false;
                document.getElementById('chatSend').disabled = false;
            }, 2000);
        }

        document.getElementById('chatSend').addEventListener('click', sendMessage);
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });

        // Fetch notes
        async function fetchNotes() {
            try {
                const formData = new FormData();
                formData.append('action', 'list_notes');
                formData.append('session_id', SESSION_ID);

                const response = await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();

                if (data.ok && data.notes) {
                    const container = document.getElementById('notesList');
                    container.innerHTML = data.notes.map(note => `
                        <div class="note-item ${note.is_pinned ? 'pinned' : ''}">
                            <div class="note-header">
                                <span class="note-author">${note.user_name}</span>
                                ${note.is_pinned ? '<span class="note-pin-badge">Pinned</span>' : ''}
                            </div>
                            <div class="note-content">${note.content}</div>
                            ${IS_ADMIN ? `
                                <div class="note-actions">
                                    <button class="note-action" onclick="togglePin(${note.id}, ${note.is_pinned})">${note.is_pinned ? 'Unpin' : 'Pin'}</button>
                                    <button class="note-action" onclick="hideNote(${note.id})">Hide</button>
                                </div>
                            ` : ''}
                        </div>
                    `).join('');
                }
            } catch (e) {}
        }

        // Add note
        document.getElementById('noteAdd').addEventListener('click', async () => {
            const input = document.getElementById('noteInput');
            const content = input.value.trim();
            if (!content) return;

            try {
                const formData = new FormData();
                formData.append('action', 'add_note');
                formData.append('session_id', SESSION_ID);
                formData.append('content', content);

                const response = await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();

                if (data.ok) {
                    input.value = '';
                    fetchNotes();
                }
            } catch (e) {}
        });

        // Toggle pin (admin)
        async function togglePin(noteId, isPinned) {
            try {
                const formData = new FormData();
                formData.append('action', isPinned ? 'unpin_note' : 'pin_note');
                formData.append('note_id', noteId);

                await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                fetchNotes();
            } catch (e) {}
        }

        // Hide note (admin)
        async function hideNote(noteId) {
            if (!confirm('Hide this note?')) return;
            try {
                const formData = new FormData();
                formData.append('action', 'hide_note');
                formData.append('note_id', noteId);

                await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                fetchNotes();
            } catch (e) {}
        }

        // Fetch prayers
        async function fetchPrayers() {
            try {
                const formData = new FormData();
                formData.append('action', 'list_prayers');
                formData.append('session_id', SESSION_ID);

                const response = await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();

                if (data.ok && data.prayers) {
                    const container = document.getElementById('prayersList');
                    container.innerHTML = data.prayers.map(p => `
                        <div class="note-item">
                            <div class="note-header">
                                <span class="note-author">${p.user_name}${p.is_private ? ' (Private)' : ''}</span>
                            </div>
                            <div class="note-content">${p.prayer_request}</div>
                        </div>
                    `).join('') || '<p style="color:#64748B;text-align:center;padding:2rem;">No prayer requests yet</p>';
                }
            } catch (e) {}
        }

        // Add prayer
        document.getElementById('prayerAdd').addEventListener('click', async () => {
            const input = document.getElementById('prayerInput');
            const content = input.value.trim();
            if (!content) return;

            try {
                const formData = new FormData();
                formData.append('action', 'add_prayer');
                formData.append('session_id', SESSION_ID);
                formData.append('prayer_request', content);
                formData.append('is_private', document.getElementById('prayerPrivate').checked ? '1' : '0');

                const response = await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();

                if (data.ok) {
                    input.value = '';
                    fetchPrayers();
                }
            } catch (e) {}
        });

        // End session (admin)
        async function endSession() {
            if (!confirm('End this session and generate recap?')) return;
            try {
                const formData = new FormData();
                formData.append('action', 'generate_recap');
                formData.append('session_id', SESSION_ID);

                const response = await fetch('/morning_watch/api/study.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': getCSRFToken() },
                    body: formData
                });
                const data = await response.json();

                if (data.ok) {
                    window.location.href = '/morning_watch/recap.php?session_id=' + SESSION_ID;
                }
            } catch (e) {}
        }

        // Initialize
        joinSession();
        fetchMessages();
        fetchNotes();
        fetchPrayers();

        // Polling
        setInterval(fetchMessages, 3000);
        setInterval(fetchNotes, 5000);
        setInterval(fetchPrayers, 10000);
    </script>
</body>
</html>
