<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed");
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../core/' . $class . '.php',
        __DIR__ . '/../../models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
$auth->requireAuth();

if ($auth->isAdmin()) {
    header('Location: /app/views/admin/dashboard.php');
    exit;
}

$userId       = $auth->id();
$lessonId     = isset($_GET['lesson']) ? (int)$_GET['lesson'] : 0;
$internshipId = isset($_GET['internship']) ? (int)$_GET['internship'] : 0;

if (!$lessonId) {
    header('Location: /app/views/learner/my-internships.php');
    exit;
}

// Verify enrollment & fetch lesson + internship context
$lessonStmt = $db->prepare("
    SELECT il.*, im.title AS module_title, im.internship_id, i.title AS internship_title
    FROM internship_lessons il
    INNER JOIN internship_modules im ON il.module_id = im.id
    INNER JOIN internships i ON im.internship_id = i.id
    WHERE il.id = ?
    LIMIT 1
");
$lessonStmt->execute([$lessonId]);
$lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    header('Location: /app/views/learner/my-internships.php');
    exit;
}

$internshipId = (int) $lesson['internship_id'];

// Confirm user is enrolled
$enrollStmt = $db->prepare("SELECT id FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
$enrollStmt->execute([$userId, $internshipId]);
if (!$enrollStmt->fetchColumn()) {
    header('Location: /app/views/learner/my-internships.php?error=not_enrolled');
    exit;
}

// Fetch quiz questions for this lesson
$qStmt = $db->prepare("
    SELECT id, question, type, options, correct_option, correct_answer, explanation, points
    FROM internship_quiz_questions
    WHERE lesson_id = ?
    ORDER BY sort_order ASC, id ASC
");
$qStmt->execute([$lessonId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// Decode JSON options for each question
foreach ($questions as &$q) {
    $opts = $q['options'];
    if (is_string($opts) && $opts !== '') {
        $decoded = json_decode($opts, true);
        $q['options_arr'] = is_array($decoded) ? $decoded : [];
    } else {
        $q['options_arr'] = [];
    }
}
unset($q);

$totalPoints = 0;
foreach ($questions as $q) {
    $totalPoints += (int)($q['points'] ?? 10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Quiz - <?php echo htmlspecialchars($lesson['internship_title']); ?></title>

    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; font-weight: 700; }
        .answer-card { transition: all 0.2s ease; }
        .answer-card:has(input:checked) {
            background: #dcfce7;
            border-color: #16a34a;
            box-shadow: 0 4px 12px rgba(34,197,94,0.2);
        }
        .answer-card.correct { background: #d1fae5 !important; border-color: #16a34a !important; }
        .answer-card.incorrect { background: #fee2e2 !important; border-color: #dc2626 !important; }
    </style>
</head>
<body>

<div class="min-h-screen py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">

        <!-- Header -->
        <div class="mb-6">
            <a href="/app/views/learner/internship-player.php?id=<?php echo $internshipId; ?>&lesson=<?php echo $lessonId; ?>"
               class="inline-flex items-center text-sm text-gray-600 hover:text-primary-600 mb-3">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Lesson
            </a>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="text-xs font-bold text-white bg-blue-600 px-2 py-0.5 rounded-full">Weekly Quiz</span>
                            <span class="text-xs font-bold text-yellow-700 bg-yellow-100 px-2 py-0.5 rounded-full">Day 7</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($lesson['module_title']); ?></h1>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($lesson['title']); ?></p>
                        <div class="flex items-center gap-3 mt-3 text-xs sm:text-sm text-gray-600">
                            <span class="inline-flex items-center gap-1 font-semibold">
                                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <?php echo count($questions); ?> Questions
                            </span>
                            <span>•</span>
                            <span class="inline-flex items-center gap-1 font-semibold">
                                <svg class="w-4 h-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <?php echo $totalPoints; ?> Points
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($questions)): ?>
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-8 text-center">
                <div class="text-5xl mb-3">📭</div>
                <h2 class="text-xl font-bold text-yellow-900 mb-2">No quiz questions yet</h2>
                <p class="text-yellow-800">The admin hasn't added quiz questions for this lesson yet. Please check back later.</p>
                <a href="/app/views/learner/internship-player.php?id=<?php echo $internshipId; ?>&lesson=<?php echo $lessonId; ?>"
                   class="inline-block mt-4 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-semibold">
                    Back to Lesson
                </a>
            </div>
        <?php else: ?>

        <!-- Quiz Form -->
        <form id="quizForm" class="space-y-5" onsubmit="return submitQuiz(event)">
            <?php foreach ($questions as $idx => $q): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-7" data-qid="<?php echo (int)$q['id']; ?>" data-qtype="<?php echo htmlspecialchars($q['type']); ?>">
                    <div class="flex items-start gap-3 mb-4">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 text-white text-sm font-bold flex-shrink-0">
                            <?php echo $idx + 1; ?>
                        </span>
                        <div class="flex-1">
                            <p class="text-base sm:text-lg font-semibold text-gray-900 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($q['question'])); ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1"><?php echo (int)$q['points']; ?> points</p>
                        </div>
                    </div>

                    <?php if ($q['type'] === 'multiple_choice' || $q['type'] === 'true_false'): ?>
                        <div class="space-y-2">
                            <?php foreach ($q['options_arr'] as $optIdx => $optText): ?>
                                <label class="answer-card block px-4 py-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-primary-300 hover:bg-gray-50">
                                    <input type="radio"
                                           name="q_<?php echo (int)$q['id']; ?>"
                                           value="<?php echo (int)$optIdx; ?>"
                                           data-correct="<?php echo (int)($q['correct_option'] ?? -1); ?>"
                                           class="mr-3 align-middle">
                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars(is_array($optText) ? json_encode($optText) : $optText); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($q['type'] === 'short_answer'): ?>
                        <input type="text"
                               name="q_<?php echo (int)$q['id']; ?>"
                               data-correct-answer="<?php echo htmlspecialchars(trim((string)($q['correct_answer'] ?? ''))); ?>"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-primary-500"
                               placeholder="Type your answer here...">
                    <?php endif; ?>

                    <div class="explanation-box hidden mt-3 p-3 rounded-lg bg-blue-50 border border-blue-200">
                        <p class="text-xs font-bold text-blue-800 mb-1">💡 Explanation</p>
                        <p class="text-sm text-blue-900"><?php echo nl2br(htmlspecialchars((string)($q['explanation'] ?? ''))); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Result Card (initially hidden) -->
            <div id="resultCard" class="hidden bg-white rounded-2xl shadow-lg border-2 border-primary-200 p-6 sm:p-8 text-center">
                <div id="resultIcon" class="text-6xl mb-3">🎉</div>
                <h2 id="resultTitle" class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Great work!</h2>
                <p id="resultText" class="text-gray-600 mb-5">You scored well on this weekly quiz.</p>
                <div class="inline-flex items-center gap-3 bg-gradient-to-r from-primary-50 to-emerald-50 px-6 py-4 rounded-2xl border border-primary-200 mb-5">
                    <div class="text-left">
                        <p class="text-xs text-gray-500 font-semibold">SCORE</p>
                        <p class="text-3xl font-bold text-primary-700"><span id="resultScore">0</span>/<span id="resultTotal"><?php echo count($questions); ?></span></p>
                    </div>
                    <div class="w-px h-12 bg-gray-300"></div>
                    <div class="text-left">
                        <p class="text-xs text-gray-500 font-semibold">PERCENT</p>
                        <p class="text-3xl font-bold text-primary-700"><span id="resultPct">0</span>%</p>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button type="button" onclick="window.location.reload()"
                            class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50">
                        Retake Quiz
                    </button>
                    <a id="continueBtn"
                       href="/app/views/learner/internship-player.php?id=<?php echo $internshipId; ?>&lesson=<?php echo $lessonId; ?>"
                       class="px-6 py-3 bg-gradient-to-r from-primary-600 to-emerald-600 text-white rounded-xl font-semibold shadow-md hover:shadow-lg">
                        Continue to Lesson →
                    </a>
                </div>
            </div>

            <button type="submit"
                    id="submitBtn"
                    class="w-full bg-gradient-to-r from-primary-600 to-emerald-600 hover:from-primary-700 hover:to-emerald-700 text-white px-6 py-4 rounded-xl font-bold text-base sm:text-lg shadow-lg transition-all">
                Submit Quiz
            </button>
        </form>

        <?php endif; ?>
    </div>
</div>

<script>
const lessonId = <?php echo (int)$lessonId; ?>;
const internshipId = <?php echo (int)$internshipId; ?>;

function submitQuiz(e) {
    e.preventDefault();
    const form = document.getElementById('quizForm');
    const cards = form.querySelectorAll('[data-qid]');

    let total = cards.length;
    let correctCount = 0;
    const answers = [];

    cards.forEach(card => {
        const qid = parseInt(card.dataset.qid);
        const qtype = card.dataset.qtype;
        let isCorrect = false;
        let userAnswer = null;

        if (qtype === 'multiple_choice' || qtype === 'true_false') {
            const checked = card.querySelector('input[type="radio"]:checked');
            if (checked) {
                userAnswer = parseInt(checked.value);
                const correctIdx = parseInt(checked.dataset.correct);
                isCorrect = userAnswer === correctIdx && correctIdx >= 0;
                // Highlight correctness
                card.querySelectorAll('.answer-card').forEach(label => {
                    const radio = label.querySelector('input');
                    const v = parseInt(radio.value);
                    if (v === correctIdx) label.classList.add('correct');
                    else if (v === userAnswer) label.classList.add('incorrect');
                });
            }
        } else if (qtype === 'short_answer') {
            const input = card.querySelector('input[type="text"]');
            const expected = (input.dataset.correctAnswer || '').trim().toLowerCase();
            userAnswer = (input.value || '').trim();
            isCorrect = expected !== '' && userAnswer.toLowerCase() === expected;
        }

        if (isCorrect) correctCount++;
        // Show explanation regardless
        const exp = card.querySelector('.explanation-box');
        if (exp && exp.querySelector('p:last-child').textContent.trim() !== '') {
            exp.classList.remove('hidden');
        }
        answers.push({ question_id: qid, answer: userAnswer, is_correct: isCorrect });
    });

    const pct = total > 0 ? Math.round((correctCount / total) * 100) : 0;
    const passed = pct >= 60;

    // Render result card
    document.getElementById('resultCard').classList.remove('hidden');
    document.getElementById('resultScore').textContent = correctCount;
    document.getElementById('resultPct').textContent = pct;
    document.getElementById('resultIcon').textContent = passed ? '🎉' : '📚';
    document.getElementById('resultTitle').textContent = passed ? 'Great work!' : 'Keep going!';
    document.getElementById('resultText').textContent = passed
        ? 'You passed this weekly quiz. The lesson will be marked complete.'
        : 'You scored below 60%. Review the explanations and try again to lock this week in.';

    document.getElementById('submitBtn').classList.add('hidden');
    document.getElementById('resultCard').scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Submit attempt to server (records points + auto-completes lesson if passed)
    fetch('/api/internship-quiz-attempt.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lesson_id: lessonId,
            internship_id: internshipId,
            score: correctCount,
            total: total,
            percentage: pct,
            passed: passed,
            answers: answers,
        })
    }).catch(() => { /* silent fail — quiz already shown */ });

    return false;
}
</script>

</body>
</html>
