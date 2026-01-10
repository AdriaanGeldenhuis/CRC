<?php
/**
 * CRC Congregation Admin - Morning Study Management
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/includes/admin_auth.php';

$pageTitle = 'Morning Study - ' . $congregation['name'] . ' - CRC';
$currentUser = Auth::user();
$today = date('Y-m-d');

// Initialize defaults
$todaySession = null;
$recentSessions = [];
$existingQuestions = [];

// Get today's session if it exists
try {
    $todaySession = Database::fetchOne(
        "SELECT ms.*, u.name as author_name
         FROM morning_sessions ms
         LEFT JOIN users u ON ms.created_by = u.id
         WHERE ms.session_date = ?
         AND (ms.congregation_id = ? OR ms.scope = 'global')
         ORDER BY ms.congregation_id = ? DESC
         LIMIT 1",
        [$today, $congregation['id'], $congregation['id']]
    );
} catch (Exception $e) {}

// Get recent sessions
try {
    $recentSessions = Database::fetchAll(
        "SELECT ms.id, ms.session_date, ms.title, ms.scripture_ref,
                ms.content_mode, ms.live_status
         FROM morning_sessions ms
         WHERE ms.congregation_id = ? OR ms.scope = 'global'
         ORDER BY ms.session_date DESC
         LIMIT 10",
        [$congregation['id']]
    ) ?: [];
} catch (Exception $e) {}

// Parse existing study questions if editing
if ($todaySession && !empty($todaySession['study_questions'])) {
    $existingQuestions = json_decode($todaySession['study_questions'], true) ?: [];
}
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
        .congregation-select { width: 100%; padding: 0.5rem; margin-top: 0.5rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; background: rgba(255,255,255,0.1); color: white; font-size: 0.8rem; cursor: pointer; }
        .congregation-select option { background: #1F2937; color: white; }
        .super-admin-link { display: inline-block; margin-top: 0.5rem; padding: 0.25rem 0.5rem; background: #F59E0B; color: white; border-radius: 4px; text-decoration: none; font-size: 0.75rem; }
        .super-admin-link:hover { background: #D97706; }
        .study-grid { display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; }
        @media (max-width: 900px) { .study-grid { grid-template-columns: 1fr; } }

        .card { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-100); display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 600; }
        .card-body { padding: 1.5rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.625rem; border: 1px solid var(--gray-300); border-radius: var(--radius); font-size: 0.875rem; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1); }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group small { font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem; display: block; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .questions-list { margin-top: 0.5rem; }
        .question-item { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
        .question-item input { flex: 1; }
        .question-item button { padding: 0.5rem; background: var(--gray-100); border: none; border-radius: var(--radius); cursor: pointer; }
        .question-item button:hover { background: #FEE2E2; color: #991B1B; }
        .add-question { background: var(--gray-100); border: 1px dashed var(--gray-300); padding: 0.5rem; border-radius: var(--radius); cursor: pointer; text-align: center; font-size: 0.875rem; color: var(--gray-600); }
        .add-question:hover { background: var(--gray-200); }

        .status-badge { padding: 0.375rem 0.75rem; border-radius: 100px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-badge.live { background: #FEE2E2; color: #991B1B; animation: pulse 2s infinite; }
        .status-badge.scheduled { background: #FEF3C7; color: #92400E; }
        .status-badge.ended { background: #D1FAE5; color: #065F46; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }

        .live-controls { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; border-radius: var(--radius); font-size: 0.875rem; font-weight: 500; cursor: pointer; border: none; transition: var(--transition); text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: #10B981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #EF4444; color: white; }
        .btn-danger:hover { background: #DC2626; }
        .btn-outline { background: transparent; border: 1px solid var(--gray-300); color: var(--gray-700); }
        .btn-outline:hover { background: var(--gray-50); }
        .btn-warning { background: #F59E0B; color: white; }
        .btn-warning:hover { background: #D97706; }

        .recent-sessions { list-style: none; padding: 0; margin: 0; }
        .recent-sessions li { padding: 0.75rem 0; border-bottom: 1px solid var(--gray-100); }
        .recent-sessions li:last-child { border-bottom: none; }
        .recent-session-title { font-weight: 500; color: var(--gray-800); font-size: 0.9rem; }
        .recent-session-meta { font-size: 0.8rem; color: var(--gray-500); display: flex; gap: 1rem; margin-top: 0.25rem; }

        .preview-link { font-size: 0.875rem; color: var(--primary); text-decoration: none; }
        .preview-link:hover { text-decoration: underline; }

        #toast { position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem; border-radius: var(--radius); background: var(--gray-800); color: white; opacity: 0; transition: opacity 0.3s; z-index: 1000; }
        #toast.show { opacity: 1; }
        #toast.error { background: #EF4444; }
        #toast.success { background: #10B981; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/home/" class="sidebar-logo">CRC</a>
                <?php if ($isSuperAdmin && count($allCongregations) > 1): ?>
                    <select class="congregation-select" onchange="window.location.href='?cong_id='+this.value">
                        <?php foreach ($allCongregations as $cong): ?>
                            <option value="<?= $cong['id'] ?>" <?= $cong['id'] == $congregation['id'] ? 'selected' : '' ?>>
                                <?= e($cong['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <span class="congregation-badge"><?= e($congregation['name']) ?></span>
                <?php endif; ?>
                <?php if ($isSuperAdmin): ?>
                    <a href="/admin/" class="super-admin-link" title="Super Admin">⚡</a>
                <?php endif; ?>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin_congregation/<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="/admin_congregation/members.php<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    Members
                </a>
                <a href="/admin_congregation/invites.php<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    Invites
                </a>
                <a href="/admin_congregation/events.php<?= $congQuery ?>" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Events
                </a>
                <a href="/admin_congregation/morning_study.php<?= $congQuery ?>" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    Morning Study
                </a>
                <a href="/admin_congregation/settings.php<?= $congQuery ?>" class="nav-item">
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
                <h1>Morning Study</h1>
                <p>Manage live study sessions for your congregation</p>
            </header>

            <div class="study-grid">
                <!-- Today's Study Form -->
                <div class="card">
                    <div class="card-header">
                        <h2>Today's Study - <?= date('F j, Y') ?></h2>
                        <?php if ($todaySession): ?>
                            <span class="status-badge <?= $todaySession['live_status'] ?? 'scheduled' ?>">
                                <?= ucfirst($todaySession['live_status'] ?? 'scheduled') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form id="studyForm">
                            <input type="hidden" id="sessionId" value="<?= $todaySession['id'] ?? '' ?>">

                            <div class="form-group">
                                <label>Session Title *</label>
                                <input type="text" id="title" placeholder="e.g., Walking in Faith" value="<?= e($todaySession['title'] ?? '') ?>" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Scripture Reference *</label>
                                    <input type="text" id="scriptureRef" placeholder="e.g., Hebrews 11:1-6" value="<?= e($todaySession['scripture_ref'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Bible Version</label>
                                    <select id="versionCode">
                                        <option value="KJV" <?= ($todaySession['version_code'] ?? '') === 'KJV' ? 'selected' : '' ?>>KJV</option>
                                        <option value="NIV" <?= ($todaySession['version_code'] ?? '') === 'NIV' ? 'selected' : '' ?>>NIV</option>
                                        <option value="ESV" <?= ($todaySession['version_code'] ?? '') === 'ESV' ? 'selected' : '' ?>>ESV</option>
                                        <option value="NKJV" <?= ($todaySession['version_code'] ?? '') === 'NKJV' ? 'selected' : '' ?>>NKJV</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Key Verse</label>
                                <input type="text" id="keyVerse" placeholder="Enter the key verse for today" value="<?= e($todaySession['key_verse'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label>Scripture Text</label>
                                <textarea id="scriptureText" placeholder="Paste the full scripture passage here..."><?= e($todaySession['scripture_text'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Study Questions</label>
                                <div id="questionsList" class="questions-list">
                                    <?php if ($existingQuestions): ?>
                                        <?php foreach ($existingQuestions as $i => $q): ?>
                                            <div class="question-item">
                                                <input type="text" name="questions[]" value="<?= e($q) ?>" placeholder="Question <?= $i + 1 ?>">
                                                <button type="button" onclick="removeQuestion(this)" title="Remove">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="question-item">
                                            <input type="text" name="questions[]" placeholder="Question 1">
                                            <button type="button" onclick="removeQuestion(this)" title="Remove">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="add-question" onclick="addQuestion()">+ Add Question</div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Stream URL (YouTube/Vimeo)</label>
                                    <input type="url" id="streamUrl" placeholder="https://youtube.com/watch?v=..." value="<?= e($todaySession['stream_url'] ?? '') ?>">
                                    <small>Paste your YouTube or Vimeo video URL</small>
                                </div>
                                <div class="form-group">
                                    <label>Scheduled Start Time</label>
                                    <input type="time" id="liveStartsAt" value="<?= isset($todaySession['live_starts_at']) && $todaySession['live_starts_at'] ? date('H:i', strtotime($todaySession['live_starts_at'])) : '' ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Content Mode</label>
                                <select id="contentMode">
                                    <option value="watch" <?= ($todaySession['content_mode'] ?? 'watch') === 'watch' ? 'selected' : '' ?>>Watch (Video/Devotional)</option>
                                    <option value="study" <?= ($todaySession['content_mode'] ?? 'watch') === 'study' ? 'selected' : '' ?>>Study (Live Interactive)</option>
                                </select>
                                <small>Study mode enables live chat, shared notes, and attendance tracking</small>
                            </div>

                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.5rem;">
                                <button type="submit" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                    Save Session
                                </button>

                                <?php if ($todaySession): ?>
                                    <a href="/morning_watch/room.php?session_id=<?= $todaySession['id'] ?>" target="_blank" class="btn btn-outline">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        Preview Room
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <?php if ($todaySession): ?>
                            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-200);">

                            <h3 style="font-size: 1rem; margin-bottom: 1rem;">Live Controls</h3>
                            <div class="live-controls">
                                <?php if (($todaySession['live_status'] ?? 'scheduled') === 'scheduled'): ?>
                                    <button class="btn btn-success" onclick="goLive()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                        Go Live
                                    </button>
                                <?php elseif (($todaySession['live_status'] ?? 'scheduled') === 'live'): ?>
                                    <button class="btn btn-danger" onclick="endSession()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="6" width="12" height="12"></rect></svg>
                                        End Session
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--gray-500);">Session has ended</span>
                                    <a href="/morning_watch/recap.php?session_id=<?= $todaySession['id'] ?>" class="btn btn-outline">View Recap</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar: Recent Sessions -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h2>Recent Sessions</h2>
                        </div>
                        <div class="card-body" style="padding: 0.75rem 1.5rem;">
                            <?php if (empty($recentSessions)): ?>
                                <p style="color: var(--gray-500); font-size: 0.9rem; padding: 1rem 0;">No sessions yet.</p>
                            <?php else: ?>
                                <ul class="recent-sessions">
                                    <?php foreach ($recentSessions as $session): ?>
                                        <li>
                                            <div class="recent-session-title"><?= e($session['title']) ?></div>
                                            <div class="recent-session-meta">
                                                <span><?= date('M j', strtotime($session['session_date'])) ?></span>
                                                <span><?= e($session['scripture_ref']) ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h2>Quick Links</h2>
                        </div>
                        <div class="card-body">
                            <a href="/morning_watch/" class="preview-link" style="display: block; margin-bottom: 0.5rem;">→ View Member Page</a>
                            <a href="/morning_watch/archive.php" class="preview-link" style="display: block;">→ View Archive</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="toast"></div>

    <script>
        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'show ' + type;
            setTimeout(() => toast.className = '', 3000);
        }

        function addQuestion() {
            const list = document.getElementById('questionsList');
            const count = list.querySelectorAll('.question-item').length + 1;
            const div = document.createElement('div');
            div.className = 'question-item';
            div.innerHTML = `
                <input type="text" name="questions[]" placeholder="Question ${count}">
                <button type="button" onclick="removeQuestion(this)" title="Remove">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            `;
            list.appendChild(div);
        }

        function removeQuestion(btn) {
            const list = document.getElementById('questionsList');
            if (list.querySelectorAll('.question-item').length > 1) {
                btn.closest('.question-item').remove();
            }
        }

        document.getElementById('studyForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const sessionId = document.getElementById('sessionId').value;
            const questions = Array.from(document.querySelectorAll('input[name="questions[]"]'))
                .map(i => i.value.trim())
                .filter(q => q);

            const liveStartsAtTime = document.getElementById('liveStartsAt').value;
            let liveStartsAt = null;
            if (liveStartsAtTime) {
                liveStartsAt = '<?= $today ?> ' + liveStartsAtTime + ':00';
            }

            const data = {
                action: 'save_today',
                session_id: sessionId || undefined,
                title: document.getElementById('title').value.trim(),
                scripture_ref: document.getElementById('scriptureRef').value.trim(),
                version_code: document.getElementById('versionCode').value,
                key_verse: document.getElementById('keyVerse').value.trim(),
                scripture_text: document.getElementById('scriptureText').value.trim(),
                study_questions: questions,
                stream_url: document.getElementById('streamUrl').value.trim(),
                live_starts_at: liveStartsAt,
                content_mode: document.getElementById('contentMode').value
            };

            try {
                const response = await fetch('/admin_congregation/api/morning_study.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.ok) {
                    showToast('Session saved successfully');
                    if (result.session_id && !sessionId) {
                        document.getElementById('sessionId').value = result.session_id;
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    showToast(result.error || 'Failed to save', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        });

        async function goLive() {
            const sessionId = document.getElementById('sessionId').value;
            if (!sessionId) {
                showToast('Save the session first', 'error');
                return;
            }

            if (!confirm('Start the live session now?')) return;

            try {
                const response = await fetch('/admin_congregation/api/morning_study.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'set_live', session_id: sessionId })
                });
                const result = await response.json();
                if (result.ok) {
                    showToast('Session is now LIVE!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.error || 'Failed to go live', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }

        async function endSession() {
            const sessionId = document.getElementById('sessionId').value;
            if (!sessionId) return;

            if (!confirm('End this live session?')) return;

            try {
                const response = await fetch('/admin_congregation/api/morning_study.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                    body: JSON.stringify({ action: 'end_session', session_id: sessionId })
                });
                const result = await response.json();
                if (result.ok) {
                    showToast('Session ended');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.error || 'Failed to end session', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            }
        }
    </script>
</body>
</html>
