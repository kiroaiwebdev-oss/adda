<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_name('ai_studio_session');
session_start();
set_time_limit(60);
ini_set('max_execution_time', 60);
header('Content-Type: application/json');
require_once __DIR__ . '/../../ai-studio/config/database.php';

$db = getAIDb();

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input       = json_decode(file_get_contents('php://input'), true);
$topic       = trim($input['topic']          ?? '');
$totalDays   = (int)($input['total_days']    ?? 30);
$startDay    = (int)($input['start_day']     ?? 1);
$endDay      = (int)($input['end_day']       ?? 7);
$level       = trim($input['level']          ?? 'Beginner');
$language    = trim($input['language']       ?? 'English');
$type        = trim($input['type']           ?? 'course');
$includeQuiz = (bool)($input['includeQuiz']  ?? true);
$saveHistory = (bool)($input['save_history'] ?? false);

if (!$topic) {
    echo json_encode(['success' => false, 'message' => 'Topic required']);
    exit;
}

// ── Settings fetch ────────────────────────────────
$stmt     = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings");
$settings = [];
while ($r = $stmt->fetch()) $settings[$r['setting_key']] = $r['setting_value'];

$provider    = $settings['active_ai_provider'] ?? 'gemini';
$geminiModel = $settings['gemini_model'] ?? 'gemini-2.0-flash';
$grokModel   = $settings['grok_model']   ?? 'grok-beta';
$activeModel = $provider === 'gemini' ? $geminiModel : $grokModel;

// ✅ Multi-key array — empty keys skip honge
if ($provider === 'gemini') {
    $apiKeys = array_values(array_filter([
        $settings['gemini_api_key']   ?? '',
        $settings['gemini_api_key_2'] ?? '',
        $settings['gemini_api_key_3'] ?? '',
    ]));
} else {
    $apiKeys = array_values(array_filter([
        $settings['grok_api_key']   ?? '',
        $settings['grok_api_key_2'] ?? '',
        $settings['grok_api_key_3'] ?? '',
    ]));
}

if (empty($apiKeys)) {
    echo json_encode(['success' => false, 'message' => 'Koi API key set nahi hai. Settings mein jao.']);
    exit;
}

// ── History save mode ─────────────────────────────
if ($saveHistory) {
    $db->prepare("
        INSERT INTO ai_generated_history
        (studio_user_id, topic, duration_days, level, language, generate_for, status, ai_provider, created_at)
        VALUES (?,?,?,?,?,?,'generating',?,NOW())
    ")->execute([
        $_SESSION['studio_user_id'], $topic, $totalDays,
        $level, $language, $type, $provider . '/' . $activeModel
    ]);
    echo json_encode(['success' => true, 'history_id' => $db->lastInsertId()]);
    exit;
}

// ── Prompt build ──────────────────────────────────
$bDays    = $endDay - $startDay + 1;
$quizNote = $includeQuiz
    ? "Day {$endDay} MUST have has_quiz: true if {$endDay} is divisible by 7, otherwise false."
    : "has_quiz should always be false.";

$prompt = <<<PROMPT
You are an expert course curriculum designer.

Create days {$startDay} to {$endDay} of a {$totalDays}-day "{$topic}" course for {$level} level students in {$language} language.

STRICT RULES:
- Day numbers MUST start from {$startDay} and end at {$endDay}
- Topics must build on previous days — do NOT repeat basics already covered
- Each day covers DIFFERENT and UNIQUE topics
- {$quizNote}
- image_query must be specific and descriptive for image searching

Return ONLY valid JSON array with exactly {$bDays} objects. No markdown, no backticks, no explanation — pure JSON only:
[{"day":{$startDay},"title":"Short title","topics":["topic 1","topic 2","topic 3"],"image_query":"specific query","has_quiz":false}]
PROMPT;

// ✅ Multi-key fallback loop
$result     = null;
$usedKeyIdx = 0;
$lastError  = '';

foreach ($apiKeys as $idx => $apiKey) {
    $res = callAI($provider, $apiKey, $prompt, 8000, $activeModel);
    if ($res['success']) {
        $result     = $res;
        $usedKeyIdx = $idx + 1;
        break;
    }
    $lastError = $res['message'];
    error_log("AI Key " . ($idx+1) . " failed: {$lastError} — trying next key");
}

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Saari API keys fail. Last error: ' . $lastError]);
    exit;
}

$text  = cleanJSON($result['text']);
$batch = json_decode($text, true);

if (!$batch || !is_array($batch)) {
    preg_match('/\[[\s\S]+\]/m', $text, $matches);
    if (!empty($matches[0])) $batch = json_decode($matches[0], true);
}

if (!$batch || count($batch) < 1) {
    echo json_encode([
        'success' => false,
        'message' => "Parse failed (Day {$startDay}-{$endDay}). Dobara try karo.",
        'raw'     => substr($text, 0, 300)
    ]);
    exit;
}

echo json_encode([
    'success'   => true,
    'batch'     => $batch,
    'start_day' => $startDay,
    'end_day'   => $endDay,
    'provider'  => $provider,
    'model'     => $activeModel,
    'key_used'  => $usedKeyIdx
]);

// ── Helpers ───────────────────────────────────────
function callAI($provider, $apiKey, $prompt, $maxTokens = 8000, $model = '') {
    if ($provider === 'gemini') {
        $useModel = $model ?: 'gemini-2.0-flash';
        $url      = "https://generativelanguage.googleapis.com/v1beta/models/{$useModel}:generateContent?key={$apiKey}";
        $body     = json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => $maxTokens]
        ]);
        $headers = ['Content-Type: application/json'];
    } else {
        $useModel = $model ?: 'grok-beta';
        $url      = "https://api.x.ai/v1/chat/completions";
        $body     = json_encode([
            'model'       => $useModel,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => $maxTokens,
            'temperature' => 0.7
        ]);
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 55,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr)          return ['success' => false, 'message' => 'cURL: ' . $curlErr];
    if ($httpCode === 429) return ['success' => false, 'message' => 'RATE_LIMIT_429'];
    if ($httpCode === 403) return ['success' => false, 'message' => 'KEY_INVALID_403'];
    if ($httpCode === 401) return ['success' => false, 'message' => 'KEY_INVALID_401'];
    if ($httpCode === 500) return ['success' => false, 'message' => 'SERVER_ERROR_500'];
    if ($httpCode !== 200) return ['success' => false, 'message' => "HTTP_{$httpCode}: " . substr($response, 0, 200)];

    $result = json_decode($response, true);
    $text   = $provider === 'gemini'
        ? ($result['candidates'][0]['content']['parts'][0]['text'] ?? '')
        : ($result['choices'][0]['message']['content'] ?? '');

    if (empty(trim($text))) return ['success' => false, 'message' => 'EMPTY_RESPONSE'];
    return ['success' => true, 'text' => $text];
}

function cleanJSON($text) {
    $text = preg_replace('/```json\s*|\s*```/i', '', $text);
    return trim($text);
}