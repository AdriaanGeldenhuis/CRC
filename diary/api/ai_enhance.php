<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/config.php'; // Load AI config

if (!isset($pdo) || !($pdo instanceof PDO)) { 
    http_response_code(500); 
    echo json_encode(['error'=>'db_unavailable']); 
    exit; 
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) { 
    http_response_code(400); 
    echo json_encode(['error'=>'unauthorized']); 
    exit; 
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$text = trim((string)($input['text'] ?? ''));

if (empty($text)) {
    http_response_code(400);
    echo json_encode(['error'=>'text_required']);
    exit;
}

// Get OpenAI API Key
$OPENAI_API_KEY = defined('DIARY_OPENAI_API_KEY') ? DIARY_OPENAI_API_KEY : null;

if (!$OPENAI_API_KEY) {
    http_response_code(500);
    echo json_encode([
        'error' => 'openai_key_missing',
        'message' => 'AI key nie gekonfigureer nie'
    ]);
    exit;
}

// Get language
$lang = $_SESSION['language'] ?? 'af';

// Get system prompt based on language
$systemPrompt = $lang === 'af' 
    ? (defined('DIARY_AI_ENHANCE_PROMPT_AF') ? DIARY_AI_ENHANCE_PROMPT_AF : 'Verbeter die teks.')
    : (defined('DIARY_AI_ENHANCE_PROMPT_EN') ? DIARY_AI_ENHANCE_PROMPT_EN : 'Enhance the text.');

// Get AI settings
$model = defined('DIARY_AI_MODEL') ? DIARY_AI_MODEL : 'gpt-4o-mini';
$maxTokens = defined('DIARY_AI_MAX_TOKENS') ? DIARY_AI_MAX_TOKENS : 1000;
$temperature = defined('DIARY_AI_TEMPERATURE') ? DIARY_AI_TEMPERATURE : 0.7;
$timeout = defined('DIARY_AI_TIMEOUT') ? DIARY_AI_TIMEOUT : 30;

try {
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
            'Authorization: Bearer ' . $OPENAI_API_KEY,
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
        $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
        throw new Exception('OpenAI API error (HTTP ' . $httpCode . '): ' . $errorMsg);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Invalid OpenAI response format');
    }
    
    $enhancedText = trim($result['choices'][0]['message']['content']);
    
    // Calculate tokens used (approximate)
    $inputTokens = $result['usage']['prompt_tokens'] ?? 0;
    $outputTokens = $result['usage']['completion_tokens'] ?? 0;
    $totalTokens = $result['usage']['total_tokens'] ?? 0;
    
    // Calculate cost (gpt-4o-mini pricing)
    $costInput = ($inputTokens / 1000000) * 0.150;  // $0.150 per 1M tokens
    $costOutput = ($outputTokens / 1000000) * 0.600; // $0.600 per 1M tokens
    $totalCost = $costInput + $costOutput;
    
    // Log usage for monitoring
    error_log(sprintf(
        'AI Enhance: user=%d, model=%s, tokens=%d (in=%d, out=%d), cost=$%.6f',
        $userId,
        $model,
        $totalTokens,
        $inputTokens,
        $outputTokens,
        $totalCost
    ));
    
    echo json_encode([
        'success' => true,
        'enhanced_text' => $enhancedText,
        'original_length' => mb_strlen($text),
        'enhanced_length' => mb_strlen($enhancedText),
        'model' => $model,
        'tokens_used' => $totalTokens,
        'estimated_cost' => number_format($totalCost, 6)
    ]);

} catch (Throwable $e) {
    error_log('AI enhance error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'ai_error',
        'message' => $e->getMessage()
    ]);
}

