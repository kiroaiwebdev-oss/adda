<?php
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$provider = $input['provider'] ?? 'gemini';
$apiKey   = trim($input['api_key'] ?? '');

if (!$apiKey) {
    echo json_encode(['success' => false, 'message' => 'API key required']);
    exit;
}

$MODELS = [
    'gemini' => [
        'gemini-2.5-flash',
        'gemini-2.5-pro',
        'gemini-2.0-flash',
        'gemini-2.0-pro',
        'gemini-1.5-flash',
        'gemini-1.5-pro'
    ],
    'grok' => [
        'grok-4',
        'grok-4-fast',
        'grok-3',
        'grok-3-fast',
        'grok-2-1212',
    ],
    'openai' => [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4-turbo',
        'gpt-3.5-turbo'
    ],
    'groq' => [
        'llama-3.3-70b-versatile',
        'openai/gpt-oss-120b',
        'openai/gpt-oss-20b',
        'llama-3.1-8b-instant',
        'qwen/qwen3-32b',
        'meta-llama/llama-4-scout',
        'mixtral-8x7b-32768',
        'gemma2-9b-it'
    ],
];

$PRIORITY = [
    'gemini' => ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'],
    'grok'   => ['grok-3-fast', 'grok-4-fast', 'grok-3'],
    'openai' => ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
    'groq'   => ['llama-3.3-70b-versatile', 'openai/gpt-oss-120b', 'llama-3.1-8b-instant'],
];

$models  = $MODELS[$provider] ?? [];
$results = [];
$bestModel = null;

foreach ($models as $model) {
    $res     = testModel($provider, $apiKey, $model);
    $results[] = $res;
    if (!$bestModel && $res['working'] && in_array($model, $PRIORITY[$provider] ?? [])) {
        $bestModel = $model;
    }
}
if (!$bestModel) {
    foreach ($results as $r) {
        if ($r['working']) { $bestModel = $r['model']; break; }
    }
}

echo json_encode([
    'success'    => true,
    'results'    => $results,
    'best_model' => $bestModel,
    'provider'   => $provider
]);

// ─────────────────────────────────────────────────────────
function testModel($provider, $apiKey, $model) {
    $base = ['model' => $model, 'working' => false, 'status' => 0, 'message' => '', 'latency_ms' => 0];
    $t0   = microtime(true);

    if ($provider === 'gemini') {
        $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $body = json_encode([
            'contents'         => [['parts' => [['text' => 'Say hello in one sentence.']]]],
            'generationConfig' => ['maxOutputTokens' => 30]
        ]);
        $res  = httpPost($url, $body, ['Content-Type: application/json']);

    } elseif ($provider === 'grok') {
        $url  = 'https://api.x.ai/v1/chat/completions';
        $body = json_encode([
            'model'                => $model,
            'messages'             => [['role' => 'user', 'content' => 'Say hello.']],
            'max_completion_tokens'=> 20   // ← fixed: max_tokens nahi
        ]);
        $res  = httpPost($url, $body, ['Content-Type: application/json', "Authorization: Bearer {$apiKey}"]);

    } elseif ($provider === 'openai') {
        $url  = 'https://api.openai.com/v1/chat/completions';
        $body = json_encode([
            'model'      => $model,
            'messages'   => [['role' => 'user', 'content' => 'Say hello.']],
            'max_tokens' => 20
        ]);
        $res  = httpPost($url, $body, ['Content-Type: application/json', "Authorization: Bearer {$apiKey}"]);

    } elseif ($provider === 'groq') {
        $url  = 'https://api.groq.com/openai/v1/chat/completions';
        $body = json_encode([
            'model'      => $model,
            'messages'   => [['role' => 'user', 'content' => 'Say hello.']],
            'max_tokens' => 20
        ]);
        $res  = httpPost($url, $body, ['Content-Type: application/json', "Authorization: Bearer {$apiKey}"]);

    } else {
        $base['message'] = 'Unknown provider';
        return $base;
    }

    $base['latency_ms'] = round((microtime(true) - $t0) * 1000);
    $base['status']     = $res['code'];

    if ($res['code'] === 200)     { $base['working'] = true; $base['message'] = '✅ Working (' . $base['latency_ms'] . 'ms)'; }
    elseif ($res['code'] === 429) { $base['message'] = '⚠️ Rate Limited'; }
    elseif ($res['code'] === 404) { $base['message'] = '❌ Not Found / No Access'; }
    elseif ($res['code'] === 401) { $base['message'] = '❌ Invalid API Key'; }
    elseif ($res['code'] === 400) {
        $errBody = json_decode($res['body'], true);
        $errMsg  = $errBody['error']['message'] ?? 'Bad Request';
        $base['message'] = '❌ 400: ' . substr($errMsg, 0, 60);
    }
    else { $base['message'] = '❌ Error ' . $res['code']; }

    return $base;
}

function httpPost($url, $body, $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => (int)$code, 'body' => $response];
}