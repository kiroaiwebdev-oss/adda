<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../ai-studio/config/database.php';

$db = getAIDb();

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input       = json_decode(file_get_contents('php://input'), true);
$topic       = trim($input['topic']          ?? '');
$language    = trim($input['language']       ?? 'English');
$level       = trim($input['level']          ?? 'Beginner');
$includeQuiz = (bool)($input['include_quiz'] ?? true);
$batch       = $input['batch']               ?? [];

if (!$topic || empty($batch)) {
    echo json_encode(['success' => false, 'message' => 'Topic aur batch required']);
    exit;
}

// Settings fetch
$stmt     = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings");
$settings = [];
while ($r = $stmt->fetch()) $settings[$r['setting_key']] = $r['setting_value'];

$provider    = $settings['active_ai_provider'] ?? 'gemini';
$apiKey      = $provider === 'gemini' ? ($settings['gemini_api_key'] ?? '') : ($settings['grok_api_key'] ?? '');
$geminiModel = $settings['gemini_model'] ?? 'gemini-2.0-flash';
$grokModel   = $settings['grok_model']   ?? 'grok-beta';
$activeModel = $provider === 'gemini' ? $geminiModel : $grokModel;

if (!$apiKey) {
    echo json_encode(['success' => false, 'message' => 'API key missing. Settings mein jao.']);
    exit;
}

// Build batch info
$batchInfo = '';
$quizDays  = [];
foreach ($batch as $item) {
    $topicsStr  = implode(', ', $item['topics'] ?? []);
    $batchInfo .= "Day {$item['day']}: \"{$item['title']}\" — Topics: {$topicsStr}\n";
    if (!empty($item['has_quiz'])) $quizDays[] = $item['day'];
}

$batchCount = count($batch);
$firstDay   = $batch[0]['day'];
$lastDay    = $batch[$batchCount - 1]['day'];

// Quiz instruction
if ($includeQuiz && !empty($quizDays)) {
    $quizDaysStr     = implode(', ', $quizDays);
    $quizInstruction = <<<QUIZ
For Day(s) {$quizDaysStr} — include "quiz" array with exactly 4 MCQ questions:
"quiz": [
  {
    "question": "Clear question text?",
    "options": ["Option A", "Option B", "Option C", "Option D"],
    "correct": 0,
    "explanation": "Why this answer is correct"
  }
]
For all other days set "quiz": []
QUIZ;
} else {
    $quizInstruction = 'For ALL days set "quiz": []';
}

$prompt = <<<PROMPT
You are an expert {$topic} instructor writing detailed course content.

Write complete, educational HTML content for these {$batchCount} days of a {$topic} course ({$level} level):

{$batchInfo}

CONTENT RULES:
- Language: {$language}
- Each day: minimum 600 words
- Use proper HTML: <h3>, <p>, <ul><li>, <ol><li>, <strong>, <em>, <blockquote>
- Include <pre><code class="language-xxx"> blocks for code examples (ONLY when topic needs code)
- Add real-world examples and practical use cases
- Add a 💡 Pro Tip in a <blockquote> tag in each day
- Do NOT include day number or title in the content body
- Content must be detailed, educational, AdSense-friendly

{$quizInstruction}

Return ONLY valid JSON array — no markdown, no backticks, no extra text:
[
  {
    "day": {$firstDay},
    "content": "<h3>...</h3><p>...</p>... (full HTML min 600 words)",
    "image_query": "specific descriptive search query for images",
    "quiz": []
  }
]

Generate all {$batchCount} days.
PROMPT;

$result = callAI($provider, $apiKey, $prompt, 16000, $activeModel);

if (!$result['success']) {
    echo json_encode($result);
    exit;
}

$text    = cleanJSON($result['text']);
$content = json_decode($text, true);

if (!$content || !is_array($content)) {
    preg_match('/\[[\s\S]+\]/m', $text, $matches);
    if (!empty($matches[0])) $content = json_decode($matches[0], true);
}

if (!$content) {
    echo json_encode([
        'success' => false,
        'message' => "Batch Day {$firstDay}-{$lastDay} parse failed.",
        'raw'     => substr($text, 0, 300)
    ]);
    exit;
}

echo json_encode([
    'success'     => true,
    'content'     => $content,
    'batch_start' => $firstDay,
    'batch_end'   => $lastDay,
    'model'       => $activeModel
]);

// ── Helpers ───────────────────────────────────────

function callAI($provider, $apiKey, $prompt, $maxTokens = 8000, $model = '') {
    if ($provider === 'gemini') {
        $useModel = $model ?: 'gemini-2.0-flash';
        $url      = "https://generativelanguage.googleapis.com/v1beta/models/{$useModel}:generateContent?key={$apiKey}";
        $body     = json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.8, 'maxOutputTokens' => $maxTokens]
        ]);
        $headers  = ['Content-Type: application/json'];
    } else {
        $useModel = $model ?: 'grok-beta';
        $url      = "https://api.x.ai/v1/chat/completions";
        $body     = json_encode([
            'model'       => $useModel,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => $maxTokens,
            'temperature' => 0.8
        ]);
        $headers  = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr)          return ['success' => false, 'message' => 'cURL Error: ' . $curlErr];
    if ($httpCode !== 200) return ['success' => false, 'message' => "API Error {$httpCode}: " . substr($response, 0, 400)];

    $result = json_decode($response, true);
    $text   = $provider === 'gemini'
        ? ($result['candidates'][0]['content']['parts'][0]['text'] ?? '')
        : ($result['choices'][0]['message']['content'] ?? '');

    if (empty(trim($text))) return ['success' => false, 'message' => 'Empty AI response. Model: ' . $model];

    return ['success' => true, 'text' => $text];
}

function cleanJSON($text) {
    $text = preg_replace('/```json\s*|\s*```/i', '', $text);
    return trim($text);
}