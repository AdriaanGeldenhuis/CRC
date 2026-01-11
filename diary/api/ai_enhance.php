<?php
/**
 * CRC Diary API - AI Enhance
 * Enhances diary text using OpenAI
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

// Require authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$text = trim((string)($input['text'] ?? ''));

if (empty($text)) {
    http_response_code(400);
    echo json_encode(['error' => 'text_required']);
    exit;
}

// OpenAI API key - should be set in environment or config
$apiKey = getenv('OPENAI_API_KEY') ?: '';

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'ai_not_configured']);
    exit;
}

try {
    $prompt = "You are a helpful writing assistant. Enhance the following diary entry to make it more expressive, clear, and engaging while maintaining the original meaning and personal voice. Keep it in the same language as the original. Only return the enhanced text, nothing else.\n\nOriginal text:\n" . $text;

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('OpenAI API error: ' . $httpCode);
    }

    $data = json_decode($response, true);
    $enhancedText = $data['choices'][0]['message']['content'] ?? '';

    if (empty($enhancedText)) {
        throw new Exception('No response from AI');
    }

    echo json_encode([
        'success' => true,
        'enhanced_text' => trim($enhancedText)
    ]);

} catch (Throwable $e) {
    error_log('AI enhance error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'ai_error',
        'message' => 'Could not enhance text'
    ]);
}
