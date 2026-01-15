<?php
/**
 * CRC AI SmartBible - Bible Study Assistant
 * Christian Revival Church
 */

// Load local config FIRST (before bootstrap which has empty defaults)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/bootstrap.php';

// Require authentication
Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'AI SmartBible - CRC';

// Load system prompt from instructions file
$INSTR_FILE = __DIR__ . '/SmartBible_Instructions.txt';
$SYSTEM_PROMPT = '';
if (is_file($INSTR_FILE)) {
    $SYSTEM_PROMPT = file_get_contents($INSTR_FILE) ?: '';
} else {
    $SYSTEM_PROMPT = 'You are a helpful Bible study assistant for CRC (Christian Revival Church). Answer questions about the Bible using the 1933/1953 Afrikaans translation or KJV English. Be helpful, accurate, and encouraging.';
}

// Detect language preference from browser or default to English
$pageLang = 'en';
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    if ($browserLang === 'af') {
        $pageLang = 'af';
    }
}

// Translation helper
function t(string $key): string {
    global $pageLang;
    $translations = [
        'en' => [
            'ai_smartbible' => 'AI SmartBible',
            'bible_study_ai' => 'Your Bible Study Assistant',
            'ask_scripture' => 'Ask About Scripture',
            'ask_placeholder' => 'Ask a question about the Bible...',
            'explain' => 'Ask',
            'help_text' => 'Ask any question about the Bible, Christian living, or spiritual growth. SmartBible will help you understand Scripture and apply it to your life.',
            'answer' => 'Answer',
            'answer_placeholder' => 'Your answer will appear here...',
            'example_questions' => 'Example Questions',
            'forgiveness_question_short' => 'Forgiveness',
            'forgiveness_question_full' => 'What does the Bible say about forgiveness?',
            'prayer_question_short' => 'Prayer',
            'prayer_question_full' => 'How should I pray according to Scripture?',
            'faith_question_short' => 'Faith',
            'faith_question_full' => 'How can I grow stronger in my faith?',
            'love_question_short' => 'Love',
            'love_question_full' => 'What does the Bible teach about loving others?',
            'empty_question' => 'Please enter a question.',
            'missing_api_key' => 'Configuration error. Please contact support.',
        ],
        'af' => [
            'ai_smartbible' => 'AI SlimBybel',
            'bible_study_ai' => 'Jou Bybelstudiehelper',
            'ask_scripture' => 'Vra Oor Die Skrif',
            'ask_placeholder' => 'Vra \'n vraag oor die Bybel...',
            'explain' => 'Vra',
            'help_text' => 'Vra enige vraag oor die Bybel, Christelike lewe, of geestelike groei. SlimBybel sal jou help om die Skrif te verstaan en dit in jou lewe toe te pas.',
            'answer' => 'Antwoord',
            'answer_placeholder' => 'Jou antwoord sal hier verskyn...',
            'example_questions' => 'Voorbeeldvrae',
            'forgiveness_question_short' => 'Vergifnis',
            'forgiveness_question_full' => 'Wat sê die Bybel oor vergifnis?',
            'prayer_question_short' => 'Gebed',
            'prayer_question_full' => 'Hoe moet ek bid volgens die Skrif?',
            'faith_question_short' => 'Geloof',
            'faith_question_full' => 'Hoe kan ek sterker word in my geloof?',
            'love_question_short' => 'Liefde',
            'love_question_full' => 'Wat leer die Bybel ons oor om ander lief te hê?',
            'empty_question' => 'Voer asseblief \'n vraag in.',
            'missing_api_key' => 'Konfigurasie fout. Kontak asseblief ondersteuning.',
        ],
    ];
    return $translations[$pageLang][$key] ?? $translations['en'][$key] ?? $key;
}

// SSE Streaming Route
if (isset($_GET['stream']) && $_GET['stream'] === '1') {
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @ob_implicit_flush(1);

    $sse = function(string $event, string $data) {
        if ($event !== 'message') { echo "event: {$event}\n"; }
        foreach (preg_split("/\r?\n/", $data) as $ln) { echo 'data: ' . $ln . "\n"; }
        echo "\n";
        @flush();
    };

    if ($q === '') {
        $sse('error', t('empty_question'));
        $sse('done', 'end');
        exit;
    }

    if (!defined('SMARTBIBLE_OPENAI_API_KEY') || empty(SMARTBIBLE_OPENAI_API_KEY)) {
        $sse('error', t('missing_api_key'));
        $sse('done', 'end');
        exit;
    }

    $messages = [
        ['role' => 'system', 'content' => $SYSTEM_PROMPT],
        ['role' => 'user', 'content' => $q],
    ];

    // Add language instruction based on detected language
    if ($pageLang === 'af' || preg_match('/[àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]/i', $q) || preg_match('/\b(wat|hoe|waar|wanneer|hoekom|wie|is|die|en|van|vir|om|te|kan|sal|het|nie|ons|jou|my)\b/i', $q)) {
        $messages[] = ['role' => 'system', 'content' => 'The user appears to be asking in Afrikaans. Answer in Afrikaans. Use the 1933/1953 Afrikaans Bible translation for all Scripture quotes.'];
    } else {
        $messages[] = ['role' => 'system', 'content' => 'Answer in English. Use the King James Version (KJV) for all Scripture quotes.'];
    }

    $payload = json_encode([
        'model' => SMARTBIBLE_MODEL,
        'messages' => $messages,
        'temperature' => SMARTBIBLE_TEMPERATURE,
        'stream' => true,
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SMARTBIBLE_OPENAI_API_KEY,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($sse) {
        $lines = preg_split("/\r?\n/", $chunk);
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $json = trim(substr($line, 6));
                if ($json === '[DONE]') {
                    $sse('done', 'end');
                    continue;
                }
                $obj = json_decode($json, true);
                if (isset($obj['choices'][0]['delta']['content'])) {
                    $token = (string)$obj['choices'][0]['delta']['content'];
                    if ($token !== '') {
                        $sse('message', $token);
                    }
                }
            }
        }
        return strlen($chunk);
    });

    $ok = curl_exec($ch);
    if ($ok === false) {
        $sse('error', 'Stream error: ' . curl_error($ch));
        $sse('done', 'end');
    }
    curl_close($ch);
    exit;
}

// HTML helper
function esc($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="<?= $pageLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?></title>
    <?= CSRF::meta() ?>
    <link rel="stylesheet" href="/ai_smartbible/css/ai_smartbible.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        // Load saved theme before page renders to prevent flash
        (function() {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<?php
$primaryCong = Auth::primaryCongregation();
$unreadNotifications = 0;
try {
    $unreadNotifications = Database::fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$user['id']]
    ) ?: 0;
} catch (Exception $e) {}
?>
<body data-theme="dark">
    <!-- Top Bar / Navigation (matching Home page exactly) -->
    <div class="topbar">
        <div class="inner">
            <div class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div>
                    <h1>CRC App</h1>
                    <span><?= e($primaryCong['name'] ?? 'CRC') ?></span>
                </div>
            </div>

            <div class="actions">
                <!-- Status Chip (hidden on mobile) -->
                <div class="chip" title="Status">
                    <span class="dot"></span>
                    <?= e(explode(' ', $user['name'])[0]) ?>
                </div>

                <!-- Theme Toggle -->
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" data-ripple>
                    <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.2 5.2l1.4 1.4m10.8 10.8l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"></path>
                        <circle cx="12" cy="12" r="5"></circle>
                    </svg>
                    <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>

                <!-- Notifications -->
                <a href="/notifications/" class="nav-icon-btn" title="Notifications" data-ripple>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </a>

                <!-- 3-dot More Menu -->
                <div class="more-menu">
                    <button class="more-menu-btn" onclick="toggleMoreMenu()" title="More" data-ripple>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="2"></circle>
                            <circle cx="12" cy="12" r="2"></circle>
                            <circle cx="12" cy="19" r="2"></circle>
                        </svg>
                    </button>
                    <div class="more-dropdown" id="moreDropdown">
                        <a href="/gospel_media/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 11a9 9 0 0 1 9 9"></path>
                                <path d="M4 4a16 16 0 0 1 16 16"></path>
                                <circle cx="5" cy="19" r="1"></circle>
                            </svg>
                            Feed
                        </a>
                        <a href="/bible/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                <path d="M12 6v7"></path>
                                <path d="M8 9h8"></path>
                            </svg>
                            Bible
                        </a>
                        <a href="/ai_smartbible/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                <circle cx="19" cy="5" r="3" fill="currentColor"></circle>
                            </svg>
                            AI SmartBible
                        </a>
                        <a href="/morning_watch/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            Morning Study
                        </a>
                        <a href="/calendar/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Calendar
                        </a>
                        <a href="/media/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                            </svg>
                            Media
                        </a>
                        <div class="more-dropdown-divider"></div>
                        <a href="/diary/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            My Diary
                        </a>
                        <a href="/homecells/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Homecells
                        </a>
                        <a href="/learning/" class="more-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                            </svg>
                            Courses
                        </a>
                    </div>
                </div>

                <!-- User Profile Menu -->
                <div class="user-menu">
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-header">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($primaryCong['name'] ?? 'CRC') ?></span>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="/profile/" class="user-dropdown-item">Profile</a>
                        <?php if ($primaryCong && Auth::isCongregationAdmin($primaryCong['id'])): ?>
                            <div class="user-dropdown-divider"></div>
                            <a href="/admin_congregation/" class="user-dropdown-item">Manage Congregation</a>
                        <?php endif; ?>
                        <?php if (Auth::isAdmin()): ?>
                            <a href="/admin/" class="user-dropdown-item">Admin Panel</a>
                        <?php endif; ?>
                        <div class="user-dropdown-divider"></div>
                        <a href="/auth/logout.php" class="user-dropdown-item logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="main-content">
        <div class="container">
            <!-- Header Section -->
            <div class="smartbible-header">
                <div class="smartbible-title">
                    <h1 class="display-title"><?= esc(t('ai_smartbible')) ?></h1>
                    <p class="subtitle"><?= esc(t('bible_study_ai')) ?></p>
                </div>
                <div class="smartbible-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
            </div>

            <!-- Search Section -->
            <section class="smartbible-section search-section">
                <div class="section-header">
                    <div class="section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                    </div>
                    <h2><?= esc(t('ask_scripture')) ?></h2>
                </div>

                <form id="sbForm" class="smartbible-form" action="#" method="get">
                    <div class="input-group">
                        <input
                            type="text"
                            id="q_input"
                            name="q"
                            class="smartbible-input"
                            autocomplete="off"
                            placeholder="<?= esc(t('ask_placeholder')) ?>"
                        >
                        <button class="btn btn-primary" id="askBtn" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                            </svg>
                            <span><?= esc(t('explain')) ?></span>
                        </button>
                    </div>
                </form>

                <div class="help-text">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 16v.01M12 8v5"/>
                    </svg>
                    <p><?= esc(t('help_text')) ?></p>
                </div>
            </section>

            <!-- Answer Section -->
            <section class="smartbible-section answer-section">
                <div class="section-header">
                    <div class="section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h2><?= esc(t('answer')) ?></h2>
                    <div class="loading-indicator" id="loadingIndicator" hidden>
                        <div class="spinner"></div>
                    </div>
                </div>

                <div class="answer-panel">
                    <div class="answer-scroll" id="answerBox">
                        <div class="placeholder" id="placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="48" height="48">
                                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" opacity="0.3"/>
                            </svg>
                            <p><?= esc(t('answer_placeholder')) ?></p>
                        </div>
                        <div class="answer-content" id="answerContent" hidden></div>
                    </div>
                </div>
            </section>

            <!-- Example Questions -->
            <section class="smartbible-section examples-section">
                <h3 class="examples-title"><?= esc(t('example_questions')) ?></h3>
                <div class="examples-grid">
                    <button class="example-card" data-question="<?= esc(t('forgiveness_question_full')) ?>">
                        <div class="example-icon">🙏</div>
                        <p class="example-text"><?= esc(t('forgiveness_question_short')) ?></p>
                    </button>
                    <button class="example-card" data-question="<?= esc(t('prayer_question_full')) ?>">
                        <div class="example-icon">📖</div>
                        <p class="example-text"><?= esc(t('prayer_question_short')) ?></p>
                    </button>
                    <button class="example-card" data-question="<?= esc(t('faith_question_full')) ?>">
                        <div class="example-icon">✨</div>
                        <p class="example-text"><?= esc(t('faith_question_short')) ?></p>
                    </button>
                    <button class="example-card" data-question="<?= esc(t('love_question_full')) ?>">
                        <div class="example-icon">❤️</div>
                        <p class="example-text"><?= esc(t('love_question_short')) ?></p>
                    </button>
                </div>
            </section>
        </div>
    </main>

    <script src="/ai_smartbible/js/ai_smartbible.js"></script>
    <script>
        // Theme toggle function
        function toggleTheme() {
            const html = document.documentElement;
            const body = document.body;
            const currentTheme = html.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-theme', newTheme);
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        function toggleUserMenu() {
            document.getElementById('moreDropdown')?.classList.remove('show');
            document.getElementById('userDropdown').classList.toggle('show');
        }

        function toggleMoreMenu() {
            document.getElementById('userDropdown')?.classList.remove('show');
            document.getElementById('moreDropdown').classList.toggle('show');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('.more-menu')) {
                document.getElementById('moreDropdown')?.classList.remove('show');
            }
        });

        // Ripple effect
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-ripple]');
            if (!target) return;

            const rect = target.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.left = (e.clientX - rect.left) + 'px';
            ripple.style.top = (e.clientY - rect.top) + 'px';
            target.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 650);
        });

        // Keyboard shortcut for theme toggle (Ctrl/Cmd + Shift + L)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
                e.preventDefault();
                toggleTheme();
            }
        });

        // Apply saved theme on load
        (function() {
            var saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
            document.body.setAttribute('data-theme', saved);
        })();
    </script>
</body>
</html>
