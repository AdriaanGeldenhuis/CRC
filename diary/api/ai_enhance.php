<?php
/**
 * CRC Diary API - AI Enhance
 * Enhances diary text using OpenAI GPT-4o-mini
 */

declare(strict_types=1);

// Catch all errors early
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

    // Require authentication
    if (!Auth::check()) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }

    $userId = Auth::user()['id'] ?? 0;

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $text = trim((string)($input['text'] ?? ''));

    if (empty($text)) {
        http_response_code(400);
        echo json_encode(['error' => 'text_required']);
        exit;
    }

    // Get OpenAI API Key - check config first, then fallback
    $apiKey = '';
    if (defined('DIARY_OPENAI_API_KEY') && !empty(DIARY_OPENAI_API_KEY)) {
        $apiKey = DIARY_OPENAI_API_KEY;
    }

    if (empty($apiKey)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'ai_not_configured',
            'message' => 'OpenAI API key not configured. Set DIARY_OPENAI_API_KEY in config or environment.'
        ]);
        exit;
    }

    // Get language preference (default to English)
    $lang = $_SESSION['language'] ?? 'en';

    // System prompts
    $promptEn = 'You are a helpful writing assistant. Enhance the following diary entry to make it more expressive, clear, and engaging while maintaining the original meaning and personal voice. Keep it in the same language as the original. Only return the enhanced text, nothing else.';
    $promptAf = 'Jy is \'n hulpvaardige skryfassistent. Verbeter die volgende dagboekinskrywing om dit meer ekspressief, duidelik en boeiend te maak terwyl jy die oorspronklike betekenis en persoonlike stem behou. Hou dit in dieselfde taal as die oorspronklike. Gee net die verbeterde teks terug, niks anders nie.';

    $systemPrompt = ($lang === 'af') ? $promptAf : $promptEn;

    // AI settings
    $model = 'gpt-4o-mini';
    $maxTokens = 1000;
    $temperature = 0.7;
    $timeout = 30;

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $text]
    ];

    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('cURL error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'Unknown API error';
        throw new Exception('OpenAI error: ' . $errorMsg);
    }

    $result = json_decode($response, true);

    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response from OpenAI');
    }

    $enhancedText = trim($result['choices'][0]['message']['content']);

    // Log usage
    $tokens = $result['usage']['total_tokens'] ?? 0;
    error_log("AI Enhance: user=$userId, tokens=$tokens");

    echo json_encode([
        'success' => true,
        'enhanced_text' => $enhancedText
    ]);

} catch (Throwable $e) {
    error_log('AI enhance error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'error' => 'ai_error',
        'message' => $e->getMessage()
    ]);
}
