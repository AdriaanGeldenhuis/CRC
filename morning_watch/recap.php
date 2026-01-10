<?php
/**
 * CRC Morning Study - Recap Page
 * Copy/Share session recap
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$primaryCong = Auth::primaryCongregation();
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    Response::redirect('/morning_watch/');
}

// Get recap with session info
$recap = Database::fetchOne(
    "SELECT r.*, ms.title, ms.scripture_ref, ms.key_verse, ms.session_date,
            ms.content_mode, ms.stream_url, ms.replay_url
     FROM morning_study_recaps r
     JOIN morning_sessions ms ON r.session_id = ms.id
     WHERE r.session_id = ?
     AND (ms.scope = 'global' OR ms.congregation_id = ?)",
    [$sessionId, $primaryCong['id'] ?? 0]
);

if (!$recap) {
    // Check if session exists but no recap
    $session = Database::fetchOne(
        "SELECT id, title FROM morning_sessions WHERE id = ?",
        [$sessionId]
    );

    if ($session) {
        // Redirect to room if no recap yet
        Response::redirect('/morning_watch/room.php?session_id=' . $sessionId);
    }
    Response::redirect('/morning_watch/');
}

$pageTitle = "Recap: " . $recap['title'] . " - Morning Study";

// Parse recap JSON
$recapData = [];
if ($recap['recap_json']) {
    $recapData = json_decode($recap['recap_json'], true) ?? [];
}

// WhatsApp share URL
$whatsappText = urlencode($recap['recap_text']);
$whatsappUrl = "https://wa.me/?text=" . $whatsappText;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .recap-container { max-width: 700px; margin: 0 auto; padding: 2rem 1rem; }
        .recap-header { margin-bottom: 2rem; }
        .recap-back { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--gray-600); text-decoration: none; font-size: 0.9rem; margin-bottom: 1rem; }
        .recap-back:hover { color: var(--primary); }
        .recap-badge { display: inline-block; background: #10B981; color: white; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem; }
        .recap-title { font-size: 1.75rem; font-weight: 700; margin: 0.5rem 0; }
        .recap-meta { color: var(--gray-500); font-size: 0.9rem; }

        .recap-card { background: white; border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; margin-bottom: 1.5rem; }
        .recap-card-header { padding: 1rem 1.5rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-100); }
        .recap-card-header h3 { margin: 0; font-size: 1rem; font-weight: 600; }
        .recap-card-body { padding: 1.5rem; }

        .recap-scripture { font-size: 1.1rem; color: var(--primary); font-weight: 500; margin-bottom: 0.5rem; }
        .recap-key-verse { background: var(--primary-light); padding: 1rem; border-radius: 8px; border-left: 3px solid var(--primary); font-style: italic; color: var(--gray-700); margin-top: 1rem; }

        .recap-points { margin: 0; padding: 0; list-style: none; }
        .recap-points li { padding: 0.75rem 0; border-bottom: 1px solid var(--gray-100); display: flex; gap: 0.75rem; }
        .recap-points li:last-child { border-bottom: none; }
        .recap-points li::before { content: "•"; color: var(--primary); font-weight: bold; }

        .recap-text-box { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; max-height: 300px; overflow-y: auto; }

        .recap-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.9rem; font-weight: 500; text-decoration: none; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: #25D366; color: white; }
        .btn-success:hover { background: #128C7E; }
        .btn-outline { background: transparent; border: 1px solid var(--gray-300); color: var(--gray-700); }
        .btn-outline:hover { background: var(--gray-50); }

        .copy-feedback { position: fixed; bottom: 2rem; right: 2rem; background: var(--gray-800); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; opacity: 0; transition: opacity 0.3s; z-index: 1000; }
        .copy-feedback.show { opacity: 1; }

        .stats-row { display: flex; gap: 2rem; margin-top: 1rem; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 0.8rem; color: var(--gray-500); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <main class="main-content">
        <div class="recap-container">
            <div class="recap-header">
                <a href="/morning_watch/" class="recap-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    Back to Morning Study
                </a>
                <span class="recap-badge">Session Recap</span>
                <h1 class="recap-title"><?= e($recap['title']) ?></h1>
                <p class="recap-meta"><?= date('l, F j, Y', strtotime($recap['session_date'])) ?></p>
            </div>

            <!-- Scripture -->
            <div class="recap-card">
                <div class="recap-card-header">
                    <h3>Scripture</h3>
                </div>
                <div class="recap-card-body">
                    <p class="recap-scripture"><?= e($recap['scripture_ref']) ?></p>
                    <?php if ($recap['key_verse']): ?>
                        <div class="recap-key-verse">
                            <strong>Key Verse:</strong> <?= e($recap['key_verse']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Key Points -->
            <?php if (!empty($recapData['key_points'])): ?>
                <div class="recap-card">
                    <div class="recap-card-header">
                        <h3>Key Points</h3>
                    </div>
                    <div class="recap-card-body">
                        <ul class="recap-points">
                            <?php foreach ($recapData['key_points'] as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Questions Discussed -->
            <?php if (!empty($recapData['questions'])): ?>
                <div class="recap-card">
                    <div class="recap-card-header">
                        <h3>Questions Discussed</h3>
                    </div>
                    <div class="recap-card-body">
                        <ul class="recap-points">
                            <?php foreach ($recapData['questions'] as $q): ?>
                                <li><?= e($q) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="recap-card">
                <div class="recap-card-body">
                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-value"><?= $recapData['attendee_count'] ?? 0 ?></div>
                            <div class="stat-label">Attendees</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= count($recapData['key_points'] ?? []) ?></div>
                            <div class="stat-label">Key Points</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= count($recapData['questions'] ?? []) ?></div>
                            <div class="stat-label">Questions</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full Recap Text -->
            <div class="recap-card">
                <div class="recap-card-header">
                    <h3>Full Recap (Copy & Share)</h3>
                </div>
                <div class="recap-card-body">
                    <div class="recap-text-box" id="recapText"><?= e($recap['recap_text']) ?></div>

                    <div class="recap-actions">
                        <button class="btn btn-primary" onclick="copyRecap()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            Copy Recap
                        </button>
                        <a href="<?= $whatsappUrl ?>" target="_blank" class="btn btn-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            Share on WhatsApp
                        </a>
                        <?php if ($recap['replay_url']): ?>
                            <a href="/morning_watch/room.php?session_id=<?= $sessionId ?>" class="btn btn-outline">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                </svg>
                                Watch Replay
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Point & Memory Verse -->
            <?php if ($recap['action_point'] || $recap['memory_verse']): ?>
                <div class="recap-card">
                    <div class="recap-card-body">
                        <?php if ($recap['action_point']): ?>
                            <p><strong>Action Point:</strong> <?= e($recap['action_point']) ?></p>
                        <?php endif; ?>
                        <?php if ($recap['memory_verse']): ?>
                            <p style="margin-top: 0.5rem;"><strong>Memory Verse:</strong> <?= e($recap['memory_verse']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="copy-feedback" id="copyFeedback">Copied to clipboard!</div>

    <script>
        function copyRecap() {
            const text = document.getElementById('recapText').textContent;
            navigator.clipboard.writeText(text).then(() => {
                const feedback = document.getElementById('copyFeedback');
                feedback.classList.add('show');
                setTimeout(() => feedback.classList.remove('show'), 2000);
            });
        }
    </script>
</body>
</html>
