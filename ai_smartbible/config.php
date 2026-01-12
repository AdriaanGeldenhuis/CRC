<?php
/**
 * AI SmartBible Configuration
 * CRC Christian Revival Church
 *
 * API keys are loaded from environment variables or core config
 */
declare(strict_types=1);

// Load from core config if available
if (defined('SMARTBIBLE_OPENAI_API_KEY')) {
    // Already defined in core config
} else {
    // Fallback to environment variable
    define('SMARTBIBLE_OPENAI_API_KEY', getenv('SMARTBIBLE_OPENAI_API_KEY') ?: '');
}

// Model configuration
if (!defined('SMARTBIBLE_MODEL')) {
    define('SMARTBIBLE_MODEL', 'gpt-4o-mini');
}

if (!defined('SMARTBIBLE_TEMPERATURE')) {
    define('SMARTBIBLE_TEMPERATURE', 0.3);
}
