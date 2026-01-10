/**
 * CRC Media JavaScript
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

// Toggle save/bookmark sermon
async function toggleSave(sermonId) {
    const btn = document.getElementById('save-btn');
    const isSaved = btn.classList.contains('saved');

    try {
        const formData = new FormData();
        formData.append('action', isSaved ? 'unsave' : 'save');
        formData.append('sermon_id', sermonId);

        const response = await fetch('/media/api/sermons.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            btn.classList.toggle('saved');
            btn.querySelector('.icon').textContent = isSaved ? '☆' : '★';
            btn.querySelector('.label').textContent = isSaved ? 'Save' : 'Saved';
        } else {
            alert(data.error || 'Failed to update');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Share sermon
function shareSermon() {
    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: window.location.href
        }).catch(() => {});
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}

// Save sermon notes
async function saveNotes() {
    const textarea = document.getElementById('sermon-notes');
    const status = document.getElementById('notes-status');
    const content = textarea.value;

    status.textContent = 'Saving...';

    try {
        const formData = new FormData();
        formData.append('action', 'save_notes');
        formData.append('sermon_id', typeof sermonId !== 'undefined' ? sermonId : 0);
        formData.append('content', content);

        const response = await fetch('/media/api/sermons.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            status.textContent = 'Saved!';
            setTimeout(() => status.textContent = '', 2000);
        } else {
            status.textContent = 'Failed to save';
        }
    } catch (error) {
        console.error('Error:', error);
        status.textContent = 'Failed to save';
    }
}

// Auto-save notes on blur
const notesTextarea = document.getElementById('sermon-notes');
if (notesTextarea) {
    let saveTimeout;
    notesTextarea.addEventListener('input', () => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveNotes, 2000);
    });
}

// Seek to timestamp in audio/video
function seekTo(seconds) {
    const video = document.querySelector('video');
    const audio = document.querySelector('audio');
    const player = video || audio;

    if (player) {
        player.currentTime = seconds;
        player.play();
    }
}

// Track playback progress
function initProgressTracking() {
    const video = document.querySelector('video');
    const audio = document.querySelector('audio');
    const player = video || audio;

    if (player && typeof sermonId !== 'undefined') {
        let lastSave = 0;

        player.addEventListener('timeupdate', () => {
            const now = Date.now();
            if (now - lastSave > 30000) { // Save every 30 seconds
                lastSave = now;
                trackProgress(sermonId, Math.floor(player.currentTime), false);
            }
        });

        player.addEventListener('ended', () => {
            trackProgress(sermonId, Math.floor(player.duration), true);
        });

        // Load saved position
        loadProgress(sermonId);
    }
}

async function trackProgress(sermonId, position, completed) {
    try {
        const formData = new FormData();
        formData.append('action', 'track_progress');
        formData.append('sermon_id', sermonId);
        formData.append('position', position);
        formData.append('completed', completed ? '1' : '0');

        await fetch('/media/api/sermons.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });
    } catch (error) {
        // Silent fail
    }
}

async function loadProgress(sermonId) {
    // Could load saved position here and seek to it
}

// Set reminder for livestream
async function setReminder(streamId) {
    try {
        const formData = new FormData();
        formData.append('action', 'set_reminder');
        formData.append('stream_id', streamId);

        const response = await fetch('/media/api/livestream.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });

        const data = await response.json();

        if (data.ok) {
            alert('Reminder set! You\'ll be notified before the stream starts.');
        } else {
            alert(data.error || 'Failed to set reminder');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to set reminder');
    }
}

// Livestream countdown
function initCountdown() {
    const countdownEl = document.getElementById('countdown');
    if (!countdownEl) return;

    const targetTime = new Date(countdownEl.dataset.time).getTime();

    function update() {
        const now = Date.now();
        const diff = targetTime - now;

        if (diff <= 0) {
            window.location.reload();
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        document.getElementById('days').textContent = String(days).padStart(2, '0');
        document.getElementById('hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
        document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
    }

    update();
    setInterval(update, 1000);
}

// Live chat
function initChat() {
    if (typeof isLive === 'undefined' || !isLive || typeof chatEnabled === 'undefined' || !chatEnabled) return;

    const form = document.getElementById('chat-form');
    const messagesContainer = document.getElementById('chat-messages');
    let lastMessageId = 0;

    // Get initial last message ID
    const messages = messagesContainer.querySelectorAll('.chat-message');
    if (messages.length > 0) {
        // Could track IDs here
    }

    // Send message
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const input = form.querySelector('input');
            const message = input.value.trim();
            if (!message) return;

            input.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'send_chat');
                formData.append('stream_id', livestreamId);
                formData.append('message', message);

                const response = await fetch('/media/api/livestream.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-Token': CSRF_TOKEN }
                });

                const data = await response.json();

                if (data.ok) {
                    input.value = '';
                    appendChatMessage(data.message);
                } else {
                    alert(data.error || 'Failed to send message');
                }
            } catch (error) {
                console.error('Error:', error);
            }

            input.disabled = false;
            input.focus();
        });
    }

    // Poll for new messages
    setInterval(async () => {
        try {
            const formData = new FormData();
            formData.append('action', 'get_chat');
            formData.append('stream_id', livestreamId);
            formData.append('last_id', lastMessageId);

            const response = await fetch('/media/api/livestream.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    appendChatMessage(msg);
                    lastMessageId = msg.id;
                });
            }
        } catch (error) {
            // Silent fail
        }
    }, 3000);
}

function appendChatMessage(msg) {
    const container = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = 'chat-message';
    div.innerHTML = `
        <span class="chat-author">${escapeHtml(msg.name)}</span>
        <span class="chat-text">${escapeHtml(msg.message)}</span>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Update viewer count
function initViewerCount() {
    if (typeof isLive === 'undefined' || !isLive) return;

    // Report viewer
    async function reportViewer() {
        try {
            const formData = new FormData();
            formData.append('action', 'report_viewer');
            formData.append('stream_id', livestreamId);

            const response = await fetch('/media/api/livestream.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();
            if (data.ok) {
                const el = document.getElementById('viewer-count');
                if (el) el.textContent = data.viewer_count.toLocaleString();
            }
        } catch (error) {
            // Silent fail
        }
    }

    reportViewer();
    setInterval(reportViewer, 30000);
}

// Check if stream went live
function checkStreamStatus() {
    if (typeof livestreamId === 'undefined') return;

    setInterval(async () => {
        try {
            const formData = new FormData();
            formData.append('action', 'check_status');
            formData.append('stream_id', livestreamId);

            const response = await fetch('/media/api/livestream.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });

            const data = await response.json();

            if (data.ok && data.status === 'live' && !isLive) {
                // Stream went live, reload page
                window.location.reload();
            }
        } catch (error) {
            // Silent fail
        }
    }, 30000);
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initProgressTracking();
    initCountdown();
    initChat();
    initViewerCount();
    checkStreamStatus();
});
