<?php
/**
 * Bible Module Configuration - CRC
 * =================================
 * Configuration for Bible reader with AI features.
 */
declare(strict_types=1);

// =============================================================================
// OPENAI API CONFIGURATION
// =============================================================================

// OpenAI API key - get from environment or config.local.php
// To configure: create bible/config.local.php with:
// define('OPENAI_API_KEY', 'your-api-key-here');
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
}

// OpenAI API endpoint
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// Model to use (gpt-4o-mini is fast and affordable, gpt-4o for better quality)
define('OPENAI_MODEL', 'gpt-4o-mini');

// Maximum tokens for AI responses
define('OPENAI_MAX_TOKENS', 600);

// Temperature (0.0-2.0, lower = more focused, higher = more creative)
define('OPENAI_TEMPERATURE', 0.2);

// =============================================================================
// AI RULES FILE
// =============================================================================

// Path to the AI rules/prompt file
define('AI_RULES_FILE', __DIR__ . '/ai_rules.txt');

// =============================================================================
// BIBLE FILES CONFIGURATION
// =============================================================================

// Directory where Bible JSON files are stored
define('BIBLE_DIR', __DIR__ . '/bibles/');

// Available Bible versions (code => filename)
define('BIBLE_VERSIONS', [
    'en' => 'en_kjv.json'      // King James Version
]);

// =============================================================================
// CONTEXT SETTINGS
// =============================================================================

// Number of verses before and after to include for AI context
define('AI_CONTEXT_VERSES_BEFORE', 7);
define('AI_CONTEXT_VERSES_AFTER', 7);

// =============================================================================
// RATE LIMITING
// =============================================================================

// Maximum AI requests per user per hour
define('AI_RATE_LIMIT_HOUR', 30);

// Maximum AI requests per user per day
define('AI_RATE_LIMIT_DAY', 100);
