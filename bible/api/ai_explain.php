<?php
/**
 * CRC Bible AI Commentary API
 * POST /bible/api/ai_explain.php
 *
 * Uses OpenAI to explain Bible verses following strict rules:
 * - Only describe WHAT HAPPENS (events, actions, dialogue)
 * - NO interpretation, meaning, or application
 * - Like a news reporter - just the facts
 */

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

// Rate limiting
$user = Auth::user();
$userId = $user['id'];
$today = date('Y-m-d');

// Check daily rate limit using bible_ai_usage table
$currentCount = 0;
try {
    $usage = Database::fetchOne(
        "SELECT request_count FROM bible_ai_usage
         WHERE user_id = ? AND date = ?",
        [$userId, $today]
    );
    $currentCount = $usage ? (int)$usage['request_count'] : 0;
} catch (Exception $e) {
    // Table might not exist yet - create it
    try {
        Database::query("CREATE TABLE IF NOT EXISTS bible_ai_usage (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            request_count INT UNSIGNED DEFAULT 0,
            tokens_used INT UNSIGNED DEFAULT 0,
            UNIQUE KEY unique_user_date (user_id, date),
            INDEX idx_user_id (user_id),
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e2) {
        // Ignore table creation error - just proceed without rate limiting
        error_log('Failed to create bible_ai_usage table: ' . $e2->getMessage());
    }
}

if ($currentCount >= AI_RATE_LIMIT_DAY) {
    Response::error('Daily limit exceeded. Please try again tomorrow.');
}

// Get request data
$bookNumber = (int)input('book_number');
$chapter = (int)input('chapter');
$verse = (int)input('verse');
$verseText = input('verse_text', '');
$contextBefore = input('context_before', '');
$contextAfter = input('context_after', '');
$bookName = input('book_name', '');

if (!$bookNumber || !$chapter || !$verse) {
    Response::error('Book, chapter, and verse are required');
}

// Build reference string
$reference = $bookName ? "$bookName $chapter:$verse" : "Book $bookNumber, Chapter $chapter, Verse $verse";

// Check cache first
$cacheKey = md5("$bookNumber:$chapter:$verse:en:explain");
$cached = null;
try {
    $cached = Database::fetchOne(
        "SELECT response FROM bible_ai_cache
         WHERE cache_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
        [$cacheKey]
    );
} catch (Exception $e) {
    // Table might not exist yet - create it
    try {
        Database::query("CREATE TABLE IF NOT EXISTS bible_ai_cache (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(64) NOT NULL UNIQUE,
            version_code VARCHAR(20) NOT NULL,
            reference VARCHAR(100) NOT NULL,
            mode VARCHAR(50) NOT NULL,
            context_hash VARCHAR(64) DEFAULT NULL,
            response TEXT NOT NULL,
            tokens_used INT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_cache_key (cache_key),
            INDEX idx_reference (version_code, reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e2) {
        error_log('Failed to create bible_ai_cache table: ' . $e2->getMessage());
    }
}

if ($cached) {
    Response::success([
        'explanation' => $cached['response'],
        'cached' => true
    ]);
}

// Build the prompt
$prompt = "You are a Bible context assistant. Your task is to describe ONLY WHAT HAPPENS in the passage - like a news reporter describing events. NO interpretation, meaning, or application.

VERSE TO EXPLAIN:
$reference
\"$verseText\"

";

if ($contextBefore) {
    $prompt .= "CONTEXT BEFORE (previous verses):
$contextBefore

";
}

if ($contextAfter) {
    $prompt .= "CONTEXT AFTER (following verses):
$contextAfter

";
}

$prompt .= "Follow this exact format:

**PLACE:** Where is this happening? (city, region, building)

**WHO:** Who is there? Who is speaking? Who is listening?

**WHAT HAPPENS BEFORE:** What events happened just before this? (previous verses/chapter)

**WHAT HAPPENS NOW:** What is happening in this verse? What is said? What is done?

**WHAT HAPPENS AFTER:** What happens next? (following verses)

REMEMBER: Only describe EVENTS. No meanings, lessons, interpretations, or applications.";

// Check if API key is configured
if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) {
    Response::error('AI service not configured. Please contact administrator.');
}

// Call OpenAI API
$explanation = callOpenAI($prompt);

if (!$explanation) {
    Response::error('Failed to generate explanation. Please try again.');
}

// Update usage tracking
try {
    if ($currentCount > 0) {
        Database::query(
            "UPDATE bible_ai_usage SET request_count = request_count + 1 WHERE user_id = ? AND date = ?",
            [$userId, $today]
        );
    } else {
        Database::insert('bible_ai_usage', [
            'user_id' => $userId,
            'date' => $today,
            'request_count' => 1,
            'tokens_used' => 0
        ]);
    }
} catch (Exception $e) {
    // Usage tracking failed - log but continue
    error_log('Failed to update AI usage: ' . $e->getMessage());
}

// Cache the result
try {
    Database::insert('bible_ai_cache', [
        'cache_key' => $cacheKey,
        'reference' => $reference,
        'version_code' => 'KJV',
        'mode' => 'explain_verse',
        'response' => $explanation,
        'created_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    // Cache insert failed - log but continue
    error_log('Failed to cache AI response: ' . $e->getMessage());
}

Response::success([
    'explanation' => $explanation,
    'cached' => false
]);

/**
 * Call OpenAI API
 */
function callOpenAI($prompt) {
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    $apiUrl = defined('OPENAI_API_URL') ? OPENAI_API_URL : 'https://api.openai.com/v1/chat/completions';
    $model = defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini';
    $maxTokens = defined('OPENAI_MAX_TOKENS') ? OPENAI_MAX_TOKENS : 600;
    $temperature = defined('OPENAI_TEMPERATURE') ? OPENAI_TEMPERATURE : 0.2;

    if (!$apiKey) {
        error_log('OpenAI API key not configured');
        return null;
    }

    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a Bible context assistant. You ONLY describe what HAPPENS in Bible passages - events, actions, dialogue. You NEVER provide meanings, interpretations, lessons, or applications. You respond in English only.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => $maxTokens,
        'temperature' => $temperature
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('OpenAI API curl error: ' . $error);
        return null;
    }

    if ($httpCode !== 200) {
        error_log('OpenAI API HTTP error: ' . $httpCode . ' - ' . $response);
        return null;
    }

    $result = json_decode($response, true);

    if (!isset($result['choices'][0]['message']['content'])) {
        error_log('OpenAI API unexpected response: ' . $response);
        return null;
    }

    return trim($result['choices'][0]['message']['content']);
}
