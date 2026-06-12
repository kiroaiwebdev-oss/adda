<?php
ob_start();
error_reporting(0);
@ini_set('display_errors', 0);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', 'internshipadda.com');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../../ai-studio/config/database.php';

$userId = $_SESSION['studio_user_id'] ?? 0;
if (!$userId) {
    $raw_check = file_get_contents('php://input');
    $check = json_decode($raw_check, true);
    if (($check['_token'] ?? '') === 'AISTUDIO_LMS_2026') {
        $userId = 1;
        $input = $check;
    } else {
        ob_end_clean();
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit;
    }
} else {
    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }

$title     = trim($input['title']       ?? '');
$desc      = trim($input['description'] ?? '');
$price     = (float)($input['price']    ?? 0);
$type      = trim($input['type']        ?? 'course');
$days      = $input['course_data']      ?? [];
$historyId = (int)($input['history_id'] ?? 0);

if (!$title || empty($days)) {
    ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Title aur content required']); exit;
}

// ✅ Image HTML helper function
function makeImageHtml($imageQuery) {
    if (empty($imageQuery)) return '';
    $q = urlencode($imageQuery);
    return '<div style="background:#f0f4ff;border:1px solid #c7d7ff;border-radius:10px;padding:14px 18px;margin:0 0 20px 0;">
<p style="font-weight:700;font-size:13px;color:#3b5bdb;margin-bottom:8px;">🖼️ Suggested Images — Ek choose karke topic image set karo:</p>
<p style="font-size:11px;color:#888;margin-bottom:10px;">Search: ' . htmlspecialchars($imageQuery) . '</p>
<div style="display:flex;flex-wrap:wrap;gap:8px;">
<a href="https://www.google.com/search?tbm=isch&q=' . $q . '" target="_blank" style="background:#4285f4;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;text-decoration:none;font-weight:600;">🔍 Google Images</a>
<a href="https://unsplash.com/s/photos/' . $q . '" target="_blank" style="background:#111;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;text-decoration:none;font-weight:600;">🌄 Unsplash</a>
<a href="https://www.pexels.com/search/' . $q . '/" target="_blank" style="background:#05a081;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;text-decoration:none;font-weight:600;">📸 Pexels</a>
<a href="https://pixabay.com/images/search/' . $q . '/" target="_blank" style="background:#2ec66e;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;text-decoration:none;font-weight:600;">🎨 Pixabay</a>
<a href="https://www.freepik.com/search?query=' . $q . '" target="_blank" style="background:#ff5a5a;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;text-decoration:none;font-weight:600;">🖌️ Freepik</a>
</div></div>';
}

function makeSlug($t) {
    $s = strtolower(trim($t));
    $s = preg_replace('/[^a-z0-9\\s]/', '', $s);
    $s = preg_replace('/\\s+/', '-', $s);
    return trim($s,'-').'-'.time();
}

try {
    $aiDb  = getAIDb();
    $lmsDb = getLMSDb();
    $lmsDb->beginTransaction();
    $lmsId = 0; $editUrl = '';

    if ($type === 'course') {
        $st = $lmsDb->prepare("INSERT INTO courses (title, slug, description, cover_image, price, status, created_by, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, 'draft', 1, NOW(), NOW())");
        $st->execute([$title, makeSlug($title), $desc, $price]);
        $courseId = $lmsDb->lastInsertId();

        $weekNum = 0;
        for ($i = 0; $i < count($days); $i += 7) {
            $weekNum++;
            $batch = array_slice($days, $i, 7);
            $st = $lmsDb->prepare("INSERT INTO chapters (course_id, chapter_order, title, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $st->execute([$courseId, $weekNum, "Week $weekNum", $weekNum]);
            $chapterId = $lmsDb->lastInsertId();

            foreach ($batch as $idx => $day) {
                $ord      = $idx + 1;
                $dayTitle = "Day ".$day['day'].": ".($day['title'] ?? '');
                $st = $lmsDb->prepare("INSERT INTO topics (chapter_id, topic_order, title, sort_order, is_mandatory, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
                $st->execute([$chapterId, $ord, $dayTitle, $ord]);
                $topicId = $lmsDb->lastInsertId();

                // ✅ FIXED — image HTML + content dono save ho rahe hain
                $finalContent = makeImageHtml($day['image_query'] ?? '') . ($day['content'] ?? '');
                $st = $lmsDb->prepare("INSERT INTO content_blocks (topic_id, type, content, sort_order, created_at, updated_at) VALUES (?, 'text', ?, 1, NOW(), NOW())");
                $st->execute([$topicId, $finalContent]);

                if (!empty($day['has_quiz']) && !empty($day['quiz'])) {
                    $st = $lmsDb->prepare("INSERT INTO content_blocks (topic_id, type, content, sort_order, created_at, updated_at) VALUES (?, 'quiz', '', 2, NOW(), NOW())");
                    $st->execute([$topicId]);
                    $cbId = $lmsDb->lastInsertId();

                    $st = $lmsDb->prepare("INSERT INTO quizzes (topic_id, content_block_id, title, time_limit, pass_score, max_attempts, created_at, updated_at) VALUES (?, ?, ?, 30, 70, 0, NOW(), NOW())");
                    $st->execute([$topicId, $cbId, "Day ".$day['day']." Quiz"]);
                    $quizId = $lmsDb->lastInsertId();

                    $lmsDb->prepare("UPDATE content_blocks SET content=? WHERE id=?")->execute([$quizId, $cbId]);

                    foreach ($day['quiz'] as $qIdx => $q) {
                        $opts    = $q['options'] ?? [];
                        $correct = $opts[(int)($q['correct'] ?? 0)] ?? '';
                        $st = $lmsDb->prepare("INSERT INTO quiz_questions (quiz_id, question_text, type, options, correct_answer, points, sort_order) VALUES (?, ?, 'multiple_choice', ?, ?, 1, ?)");
                        $st->execute([$quizId, $q['question'] ?? '', implode(',', $opts), $correct, $qIdx+1]);
                    }
                }
            }
        }
        $lmsId   = $courseId;
        $editUrl = "https://internshipadda.com/app/views/admin/courses/builder.php?id=$courseId";

    } else {
        $slug  = makeSlug($title);
        $weeks = max(1, (int)ceil(count($days)/7));
        $st = $lmsDb->prepare("INSERT INTO internships (title, slug, description, cover_image, price, duration_weeks, skill_level, is_active, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, 'beginner', 1, NOW(), NOW())");
        $st->execute([$title, $slug, $desc, $price, $weeks]);
        $internshipId = $lmsDb->lastInsertId();

        $weekNum = 0;
        for ($i = 0; $i < count($days); $i += 7) {
            $weekNum++;
            $batch = array_slice($days, $i, 7);
            $st = $lmsDb->prepare("INSERT INTO internship_modules (internship_id, title, description, sort_order, is_mandatory, created_at, updated_at) VALUES (?, ?, NULL, ?, 1, NOW(), NOW())");
            $st->execute([$internshipId, "Week $weekNum", $weekNum]);
            $moduleId = $lmsDb->lastInsertId();

            foreach ($batch as $idx => $day) {
                $ord      = $idx + 1;
                $dayTitle = "Day ".$day['day'].": ".($day['title'] ?? '');
                $st = $lmsDb->prepare("INSERT INTO internship_lessons (module_id, title, description, duration_minutes, sort_order, is_mandatory, created_at, updated_at) VALUES (?, ?, NULL, 0, ?, 1, NOW(), NOW())");
                $st->execute([$moduleId, $dayTitle, $ord]);
                $lessonId = $lmsDb->lastInsertId();

                // ✅ FIXED — image HTML + content dono save ho rahe hain
                $finalContent = makeImageHtml($day['image_query'] ?? '') . ($day['content'] ?? '');
                $st = $lmsDb->prepare("INSERT INTO internship_content_blocks (lesson_id, type, content, title, sort_order, created_at, updated_at) VALUES (?, 'text', ?, ?, 1, NOW(), NOW())");
                $st->execute([$lessonId, $finalContent, $day['title'] ?? '']);

                if (!empty($day['has_quiz']) && !empty($day['quiz'])) {
                    foreach ($day['quiz'] as $qIdx => $q) {
                        $opts = json_encode($q['options'] ?? []);
                        $st = $lmsDb->prepare("INSERT INTO internship_quiz_questions (lesson_id, question, type, options, correct_option, correct_answer, explanation, points, sort_order, created_at, updated_at) VALUES (?, ?, 'multiple_choice', ?, ?, NULL, ?, 10, ?, NOW(), NOW())");
                        $st->execute([$lessonId, $q['question'] ?? '', $opts, (int)($q['correct'] ?? 0), $q['explanation'] ?? '', $qIdx+1]);
                    }
                }
            }
        }
        $lmsId   = $internshipId;
        $editUrl = "https://internshipadda.com/app/views/admin/internships/edit.php?id=$internshipId";
    }

    $lmsDb->commit();

    if ($historyId && $lmsId) {
        $aiDb->prepare("UPDATE ai_generated_history SET lms_id=?, status='saved' WHERE id=?")->execute([$lmsId, $historyId]);
    }

    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'message'  => ($type==='course' ? 'Course' : 'Internship').' LMS mein save ho gaya!',
        'lms_id'   => $lmsId,
        'edit_url' => $editUrl,
        'type'     => $type
    ]);

} catch (PDOException $e) {
    if (isset($lmsDb) && $lmsDb->inTransaction()) $lmsDb->rollBack();
    if ($historyId) { try { $aiDb->prepare("UPDATE ai_generated_history SET status='failed' WHERE id=?")->execute([$historyId]); } catch(Exception $x){} }
    ob_end_clean();
    echo json_encode(['success'=>false,'message'=>'DB Error: '.$e->getMessage()]);
} catch (Exception $e) {
    if (isset($lmsDb) && $lmsDb->inTransaction()) $lmsDb->rollBack();
    ob_end_clean();
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}