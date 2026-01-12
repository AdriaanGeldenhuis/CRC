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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Parisienne&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

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
</body>
</html>
