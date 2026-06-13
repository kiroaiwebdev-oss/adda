<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

if (!isset($db) || !($db instanceof PDO)) {
    die('<p style="font-family:sans-serif;padding:2rem;color:red">❌ $db variable db.php se nahi mila.</p>');
}

// ─── SESSION / AUTH ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

$user_role = $_SESSION['manager_role'] ?? $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
$user_id   = $_SESSION['manager_id']   ?? $_SESSION['user_id']   ?? $_SESSION['id']   ?? null;

if (!$user_id || !in_array($user_role, ['admin', 'manager', 'super_admin'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/login.php');
    exit;
}
$is_manager = ($user_role === 'manager');

// ─── COURSE ID ─────────────────────────────────────────────────────────────
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0);
if (!$courseId) {
    die('<div style="font-family:sans-serif;padding:2rem">
        ❌ Course ID missing. URL mein <b>?id=COURSE_ID</b> lagao.<br><br>
        <a href="courses.php" style="color:#16a34a">← Courses List</a>
    </div>');
}

$stmt = $db->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) {
    die('<div style="font-family:sans-serif;padding:2rem">❌ Course nahi mila. <a href="courses.php">← Back</a></div>');
}

// is_mandatory column add karo agar nahi hai
try {
    $check = $db->query("SHOW COLUMNS FROM topics LIKE 'is_mandatory'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE topics ADD COLUMN is_mandatory TINYINT(1) DEFAULT 1 AFTER title");
    }
} catch (Exception $e) {}

// ─── AJAX HANDLERS ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    $blockedForManager = ['delete_chapter', 'delete_topic'];
    if ($is_manager && in_array($action, $blockedForManager, true)) {
        echo json_encode(['success' => false, 'message' => '⚠️ Manager ko delete permission nahi hai.']);
        exit;
    }

    try {
        if ($action === 'create_chapter') {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { echo json_encode(['success'=>false,'message'=>'Title required']); exit; }
            $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM chapters WHERE course_id=?");
            $stmt->execute([$courseId]);
            $next = (int)$stmt->fetchColumn();
            $db->prepare("INSERT INTO chapters (course_id, title, sort_order, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())")
               ->execute([$courseId, $title, $next]);
            echo json_encode(['success'=>true, 'id'=>$db->lastInsertId()]);

        } elseif ($action === 'update_chapter') {
            $id    = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $db->prepare("UPDATE chapters SET title=?, updated_at=NOW() WHERE id=? AND course_id=?")
               ->execute([$title, $id, $courseId]);
            echo json_encode(['success'=>true]);

        } elseif ($action === 'delete_chapter') {
            $id = (int)($_POST['id'] ?? 0);
            $topicsRes = $db->prepare("SELECT id FROM topics WHERE chapter_id=?");
            $topicsRes->execute([$id]);
            foreach ($topicsRes->fetchAll() as $t) {
                $db->prepare("DELETE FROM content_blocks WHERE topic_id=?")->execute([$t['id']]);
            }
            $db->prepare("DELETE FROM topics WHERE chapter_id=?")->execute([$id]);
            $db->prepare("DELETE FROM chapters WHERE id=? AND course_id=?")->execute([$id, $courseId]);
            echo json_encode(['success'=>true]);

        } elseif ($action === 'create_topic') {
            $chapterId   = (int)($_POST['chapter_id'] ?? 0);
            $title       = trim($_POST['title'] ?? '');
            $isMandatory = !empty($_POST['is_mandatory']) ? 1 : 0;
            $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM topics WHERE chapter_id=?");
            $stmt->execute([$chapterId]);
            $next = (int)$stmt->fetchColumn();
            $db->prepare("INSERT INTO topics (chapter_id, title, is_mandatory, sort_order, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())")
               ->execute([$chapterId, $title, $isMandatory, $next]);
            echo json_encode(['success'=>true, 'id'=>$db->lastInsertId()]);

        } elseif ($action === 'update_topic') {
            $id          = (int)($_POST['id'] ?? 0);
            $title       = trim($_POST['title'] ?? '');
            $isMandatory = !empty($_POST['is_mandatory']) ? 1 : 0;
            $db->prepare("UPDATE topics SET title=?, is_mandatory=?, updated_at=NOW() WHERE id=?")
               ->execute([$title, $isMandatory, $id]);
            echo json_encode(['success'=>true]);

        } elseif ($action === 'delete_topic') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("DELETE FROM content_blocks WHERE topic_id=?")->execute([$id]);
            $db->prepare("DELETE FROM topics WHERE id=?")->execute([$id]);
            echo json_encode(['success'=>true]);

        } elseif ($action === 'load_content') {
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $stmt = $db->prepare("SELECT content FROM content_blocks WHERE topic_id=? AND type='text' ORDER BY sort_order ASC, id ASC LIMIT 1");
            $stmt->execute([$topicId]);
            $row = $stmt->fetch();
            echo json_encode(['success'=>true, 'content' => $row['content'] ?? '']);

        } elseif ($action === 'save_content') {
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $content = $_POST['content'] ?? '';
            if (!$topicId) { echo json_encode(['success'=>false,'message'=>'topic_id missing']); exit; }
            $stmt = $db->prepare("SELECT id FROM content_blocks WHERE topic_id=? AND type='text' ORDER BY sort_order ASC, id ASC LIMIT 1");
            $stmt->execute([$topicId]);
            $existing = $stmt->fetch();
            if ($existing) {
                $db->prepare("UPDATE content_blocks SET content=?, updated_at=NOW() WHERE id=?")
                   ->execute([$content, $existing['id']]);
                $blockId = $existing['id'];
            } else {
                $db->prepare("INSERT INTO content_blocks (topic_id, type, content, sort_order, created_at, updated_at) VALUES (?,'text',?,1,NOW(),NOW())")
                   ->execute([$topicId, $content]);
                $blockId = $db->lastInsertId();
            }
            echo json_encode(['success'=>true, 'block_id'=>$blockId]);

        } elseif ($action === 'upload_media') {
            if (!isset($_FILES['file'])) { echo json_encode(['success'=>false,'message'=>'No file']); exit; }
            $file = $_FILES['file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            $isVideo = in_array($ext, ['mp4','webm','ogg','mov']);
            if (!$isImage && !$isVideo) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
            $maxSize = $isImage ? 10*1024*1024 : 200*1024*1024;
            if ($file['size'] > $maxSize) { echo json_encode(['success'=>false,'message'=>'File too large']); exit; }
            $folder = $isImage ? 'images' : 'videos';
            $dir = __DIR__ . "/uploads/$folder/";
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = uniqid('media_', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                $baseUrl = dirname($_SERVER['PHP_SELF']);
                echo json_encode(['success'=>true, 'url'=> $baseUrl . "/uploads/$folder/$fname"]);
            } else {
                echo json_encode(['success'=>false, 'message'=>'Upload failed — folder permissions check karo']);
            }

        } else {
            echo json_encode(['success'=>false, 'message'=>'Unknown action']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }
    exit;
}

// ─── LOAD CHAPTERS + TOPICS ────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM chapters WHERE course_id=? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$courseId]);
$chaptersRaw = $stmt->fetchAll();

$chapters = [];
foreach ($chaptersRaw as $chapterRow) {
    $tStmt = $db->prepare("SELECT * FROM topics WHERE chapter_id=? ORDER BY sort_order ASC, id ASC");
    $tStmt->execute([$chapterRow['id']]);
    $topics = $tStmt->fetchAll();
    foreach ($topics as &$topic) {
        $cb = $db->prepare("SELECT * FROM content_blocks WHERE topic_id=? ORDER BY sort_order ASC, id ASC");
        $cb->execute([$topic['id']]);
        $topic['content_blocks'] = $cb->fetchAll();
        $qz = $db->prepare("SELECT q.id FROM quizzes q INNER JOIN content_blocks cb ON q.content_block_id=cb.id WHERE cb.topic_id=? LIMIT 1");
        $qz->execute([$topic['id']]);
        $topic['has_quiz'] = (bool)$qz->fetch();
    }
    unset($topic);
    $chapterRow['topics'] = $topics;
    $chapters[] = $chapterRow;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Builder — <?= htmlspecialchars($course['title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: { extend: { colors: {
        primary: { 50:'#f0fdf4',100:'#dcfce7',200:'#bbf7d0',300:'#86efac',
                   400:'#4ade80',500:'#22c55e',600:'#16a34a',700:'#15803d',800:'#166534',900:'#14532d' }
    }}}
}
</script>
<style>
body{font-family:'Inter',sans-serif;background:#f8fafc}
h1,h2,h3,h4{font-family:'Poppins',sans-serif}
.chapter-card{background:#fff;border:2px solid #e5e7eb;border-radius:16px;transition:all .3s}
.chapter-card:hover{border-color:#22c55e;box-shadow:0 4px 20px rgba(34,197,94,.1)}
.topic-card{background:#f9fafb;border:2px solid #e5e7eb;border-radius:12px;transition:all .3s}
.topic-card:hover{background:#fff;border-color:#22c55e}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px}
.modal.active{display:flex}
.modal-content{background:#fff;border-radius:24px;max-width:1000px;width:100%;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.form-input,.form-textarea{width:100%;padding:12px 16px;border:2px solid #e5e7eb;border-radius:10px;font-size:15px;font-family:'Inter',sans-serif;transition:all .2s}
.form-input:focus,.form-textarea:focus{outline:none;border-color:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.1)}
.btn-primary{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;padding:12px 22px;border-radius:10px;font-weight:600;transition:all .2s;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(34,197,94,.4)}
.btn-secondary{background:#fff;color:#374151;padding:10px 18px;border-radius:10px;font-weight:600;border:2px solid #e5e7eb;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-secondary:hover{background:#f9fafb;border-color:#d1d5db}
.btn-danger{background:#fef2f2;color:#dc2626;padding:8px 14px;border-radius:8px;font-weight:600;border:2px solid #fecaca;cursor:pointer;transition:all .2s}
.btn-danger:hover{background:#fee2e2}
.manager-notice{background:#fffbeb;border-bottom:2px solid #f59e0b;color:#92400e;padding:.75rem 1.5rem;font-size:.88rem;font-weight:600;display:flex;align-items:center;gap:.5rem}
.editor-btn{padding:7px 10px;background:#fff;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;min-width:36px;height:36px;font-size:13px}
.editor-btn:hover{background:#f3f4f6;border-color:#22c55e;color:#16a34a}
#contentEditor{min-height:380px;max-height:500px;overflow-y:auto;padding:18px;border:2px solid #e5e7eb;border-radius:0 0 10px 10px;background:#fff;line-height:1.7;font-size:15px;outline:none}
#contentEditor:focus{border-color:#22c55e}
#contentEditor:empty:before{content:'Topic ka content yahan likhna shuru karo...';color:#9ca3af}
#contentEditor h1{font-size:1.8em;font-weight:700;margin:.5em 0 .3em;font-family:'Poppins',sans-serif}
#contentEditor h2{font-size:1.4em;font-weight:700;margin:.5em 0 .25em;font-family:'Poppins',sans-serif}
#contentEditor h3{font-size:1.15em;font-weight:700;margin:.4em 0 .2em;font-family:'Poppins',sans-serif}
#contentEditor ul{list-style:disc;margin-left:1.75rem;margin-bottom:.75em}
#contentEditor ol{list-style:decimal;margin-left:1.75rem;margin-bottom:.75em}
#contentEditor li{margin-bottom:.35em}
#contentEditor blockquote{border-left:4px solid #22c55e;padding:.5rem 1rem;background:#f0fdf4;border-radius:0 8px 8px 0;margin:.75em 0;color:#374151;font-style:italic}
#contentEditor pre{background:#1f2937;color:#f9fafb;padding:16px;border-radius:8px;overflow-x:auto;font-family:'Courier New',monospace;font-size:.85em;margin:.75em 0}
#contentEditor code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-family:'Courier New',monospace;font-size:.88em}
#contentEditor img{max-width:100%;border-radius:10px;margin:12px 0;display:block;cursor:pointer}
#contentEditor video{max-width:100%;border-radius:12px;margin:16px 0;display:block}
#contentEditor iframe{max-width:100%;border-radius:12px;margin:16px 0;display:block;min-height:360px;border:none}
#contentEditor a{color:#2563eb;text-decoration:underline}
#contentEditor p{margin:.4em 0}
#contentEditor strong,#contentEditor b{font-weight:700}
#contentEditor em,#contentEditor i{font-style:italic}
#contentEditor u{text-decoration:underline}
#contentEditor table{border-collapse:collapse;width:100%;margin:.75em 0}
#contentEditor th,#contentEditor td{border:1px solid #e5e7eb;padding:8px 12px;text-align:left}
#contentEditor th{background:#f9fafb;font-weight:600}
#contentEditor hr{border:none;border-top:2px solid #e5e7eb;margin:1em 0}
#contentEditor div[contenteditable="false"]{outline:2px dashed #e5e7eb;outline-offset:4px;border-radius:8px;margin:8px 0}
#contentEditor div[contenteditable="false"]:hover{outline-color:#22c55e}
.toast{position:fixed;bottom:1.5rem;right:1.5rem;background:#fff;border-radius:12px;padding:.85rem 1.25rem;box-shadow:0 8px 30px rgba(0,0,0,.15);z-index:10000;transform:translateY(10px);opacity:0;transition:all .25s;font-size:.88rem;max-width:320px;font-weight:500}
.toast.show{transform:translateY(0);opacity:1}
.toast.success{border-left:4px solid #22c55e;color:#15803d}
.toast.error{border-left:4px solid #dc2626;color:#dc2626}
.toast.info{border-left:4px solid #3b82f6;color:#1d4ed8}
</style>
</head>
<body>

<!-- ===== Shared-style nav sidebar (self-contained for this Tailwind page) ===== -->
<style>
.mp-sidebar{width:240px;height:100vh;background:#fff;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:9000;font-family:'Inter','Satoshi',sans-serif}
.mp-sidebar .mp-logo{padding:1.1rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:.6rem}
.mp-sidebar .mp-logo svg{width:30px;height:30px}
.mp-logo-text{font-weight:700;font-size:.92rem;color:#111827}.mp-logo-text span{color:#16a34a}
.mp-user{padding:.75rem 1.25rem;border-bottom:1px solid #e5e7eb}
.mp-badge{font-size:.68rem;font-weight:700;background:rgba(22,163,74,.1);color:#16a34a;padding:.18rem .55rem;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em;display:inline-block;margin-bottom:.3rem}
.mp-uname{font-weight:600;font-size:.88rem;color:#111827}
.mp-usub{font-size:.72rem;color:#6b7280}
.mp-nav{flex:1;padding:.5rem 0;overflow-y:auto}
.mp-sec{padding:.45rem 1.25rem .2rem;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af}
.mp-link{display:flex;align-items:center;gap:.65rem;padding:.5rem 1.25rem;font-size:.85rem;color:#6b7280;text-decoration:none;position:relative;transition:background .15s,color .15s}
.mp-link:hover{background:#f9fafb;color:#111827}
.mp-link.active{background:rgba(22,163,74,.1);color:#16a34a;font-weight:600}
.mp-link.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:#16a34a;border-radius:0 4px 4px 0}
.mp-link svg{width:15px;height:15px;flex-shrink:0;opacity:.7}
.mp-link.active svg{opacity:1}
.mp-foot{padding:.9rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;gap:.5rem}
.mp-foot a{padding:.4rem .85rem;font-size:.78rem;border-radius:.5rem;font-weight:600;background:rgba(161,44,123,.1);color:#a12c7b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
.mp-foot a:hover{background:rgba(161,44,123,.18)}
body{padding-left:240px}
.mp-menu-btn{display:none}
@media(max-width:768px){
  body{padding-left:0}
  .mp-sidebar{transform:translateX(-100%);transition:transform .3s ease}
  .mp-sidebar.open{transform:translateX(0)}
  .mp-menu-btn{display:inline-flex;align-items:center;justify-content:center;position:fixed;top:.6rem;left:.6rem;z-index:9500;width:38px;height:38px;border-radius:.5rem;background:#16a34a;color:#fff;border:none;box-shadow:0 2px 8px rgba(0,0,0,.2)}
}
</style>

<aside class="mp-sidebar" id="sidebar">
  <div class="mp-logo">
    <svg viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="7" fill="#16a34a"/><path d="M9 23L16 9L23 23" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 19h8" stroke="white" stroke-width="1.8" stroke-linecap="round"/></svg>
    <div class="mp-logo-text">Internship<span>Adda</span></div>
  </div>
  <div class="mp-user">
    <div class="mp-badge"><?= htmlspecialchars(ucfirst($user_role)) ?></div>
    <div class="mp-uname"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Manager') ?></div>
    <div class="mp-usub">Platform Manager</div>
  </div>
  <nav class="mp-nav">
    <div class="mp-sec">Overview</div>
    <a href="manager_dashboard.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
    <a href="reports.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/></svg>Reports</a>
    <div class="mp-sec">Content</div>
    <a href="courses.php" class="mp-link active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>Courses</a>
    <a href="internships.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>Internships</a>
    <a href="banners.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>Homepage Banners</a>
    <div class="mp-sec">Audience</div>
    <a href="users.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Users</a>
    <a href="referrals.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3l3 3-3 3"/><path d="M21 6h-5"/></svg>Referrals</a>
    <div class="mp-sec">Inbox</div>
    <a href="contacts.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Contact Messages</a>
    <a href="offline_apps.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Offline Applications</a>
    <a href="certificates.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M9 13.5V21l3-2 3 2v-7.5"/></svg>Certificate Requests</a>
    <div class="mp-sec">Marketing</div>
    <a href="coupons.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9.5V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3.5a2.5 2.5 0 0 0 0 5V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-3.5a2.5 2.5 0 0 0 0-5z"/><line x1="9" y1="15" x2="15" y2="9"/></svg>Coupons</a>
    <div class="mp-sec">Logs</div>
    <a href="activity_log.php" class="mp-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Activity Log</a>
  </nav>
  <div class="mp-foot">
    <a href="manager_dashboard.php?logout=1"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </div>
</aside>
<button id="menuBtn" class="mp-menu-btn" aria-label="Open menu">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<?php if ($is_manager): ?>
<div class="manager-notice">
    <i class="fas fa-shield-alt"></i>
    Manager Mode — Content view/edit allowed. Delete restricted.
</div>
<?php endif; ?>

<header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
                <a href="courses.php" class="hover:text-green-600 transition-colors">📚 Courses</a>
                <span>/</span>
                <span class="text-gray-600 font-medium">Builder</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($course['title']) ?></h1>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="courses.php" class="btn-secondary"><i class="fas fa-arrow-left text-sm"></i> Back</a>
            <button onclick="openAddChapterModal()" class="btn-primary"><i class="fas fa-plus text-sm"></i> Add Chapter</button>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-6 space-y-6">

<?php if (empty($chapters)): ?>
<div class="bg-white rounded-2xl border-2 border-dashed border-gray-300 p-12 text-center">
    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5">
        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
    </div>
    <h3 class="text-xl font-bold text-gray-900 mb-2">Abhi koi chapter nahi hai</h3>
    <p class="text-gray-500 mb-6">Pehla chapter add karke course banana shuru karo</p>
    <button onclick="openAddChapterModal()" class="btn-primary">
        <i class="fas fa-plus"></i> Add First Chapter
    </button>
</div>
<?php else: ?>

<?php foreach ($chapters as $index => $chapter): ?>
<div class="chapter-card" id="chapter-<?= (int)$chapter['id'] ?>">
    <div class="p-6">
        <!-- Chapter Header -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-700 rounded-xl flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                    <?= $index + 1 ?>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($chapter['title']) ?></h3>
                    <p class="text-sm text-gray-500"><?= count($chapter['topics']) ?> topics</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button onclick="openAddTopicModal(<?= (int)$chapter['id'] ?>)"
                    class="bg-green-50 text-green-700 hover:bg-green-100 px-3 py-2 rounded-lg font-semibold text-sm transition-colors">
                    <i class="fas fa-plus text-xs mr-1"></i>Add Topic
                </button>
                <button onclick="editChapter(<?= (int)$chapter['id'] ?>, <?= htmlspecialchars(json_encode($chapter['title'])) ?>)"
                    class="btn-secondary text-sm px-3 py-2">
                    <i class="fas fa-edit text-xs"></i> Edit
                </button>
                <?php if (!$is_manager): ?>
                <button onclick="deleteChapter(<?= (int)$chapter['id'] ?>)" class="btn-danger text-sm">
                    <i class="fas fa-trash text-xs"></i> Delete
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Topics -->
        <?php if (empty($chapter['topics'])): ?>
        <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl p-6 text-center">
            <p class="text-gray-400 text-sm mb-2">Koi topic nahi hai</p>
            <button onclick="openAddTopicModal(<?= (int)$chapter['id'] ?>)"
                class="text-green-600 hover:text-green-700 font-semibold text-sm">
                + Add First Topic
            </button>
        </div>
        <?php else: ?>
        <div class="space-y-3 pl-2">
            <?php foreach ($chapter['topics'] as $ti => $topic): ?>
            <div class="topic-card p-4" id="topic-<?= (int)$topic['id'] ?>">
                <div class="flex items-start justify-between gap-2 flex-wrap">
                    <div class="flex items-start gap-3 flex-1">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center text-green-700 font-bold text-sm flex-shrink-0 mt-0.5">
                            <?= $ti + 1 ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <h4 class="font-bold text-gray-900"><?= htmlspecialchars($topic['title']) ?></h4>
                                <?php if (!empty($topic['is_mandatory'])): ?>
                                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-semibold">Required</span>
                                <?php endif; ?>
                                <?php if ($topic['has_quiz']): ?>
                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-semibold">📝 Quiz</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-400"><?= count($topic['content_blocks']) ?> content block(s)</p>
                            <?php if (!empty($topic['content_blocks'][0]['content'])): ?>
                            <p class="text-sm text-gray-500 mt-1.5 bg-white border border-gray-100 rounded-lg px-3 py-2">
                                <?= htmlspecialchars(substr(strip_tags($topic['content_blocks'][0]['content']), 0, 180)) ?>...
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0 flex-wrap">
                        <button onclick="openContentModal(<?= (int)$topic['id'] ?>, <?= htmlspecialchars(json_encode($topic['title'])) ?>)"
                            class="bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                            <i class="fas fa-pen text-xs mr-1"></i>Content
                        </button>
                        <button onclick="editTopic(<?= (int)$topic['id'] ?>, <?= htmlspecialchars(json_encode($topic['title'])) ?>, <?= !empty($topic['is_mandatory']) ? 'true' : 'false' ?>)"
                            class="text-gray-400 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition-colors" title="Edit Topic">
                            <i class="fas fa-edit text-sm"></i>
                        </button>
                        <?php if (!$is_manager): ?>
                        <button onclick="deleteTopic(<?= (int)$topic['id'] ?>)"
                            class="text-red-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition-colors" title="Delete Topic">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
</main>

<!-- ══════════════════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════════════════ -->

<!-- Add Chapter -->
<div id="addChapterModal" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-bold text-gray-900">📖 Add Chapter</h2>
            <button onclick="closeModal('addChapterModal')" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>
        <form id="addChapterForm">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Chapter Title *</label>
            <input type="text" name="title" class="form-input mb-5" placeholder="e.g. Introduction to JavaScript" required>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Create Chapter</button>
                <button type="button" onclick="closeModal('addChapterModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Chapter -->
<div id="editChapterModal" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-bold text-gray-900">✏️ Edit Chapter</h2>
            <button onclick="closeModal('editChapterModal')" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>
        <form id="editChapterForm">
            <input type="hidden" id="editChapterId">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Chapter Title *</label>
            <input type="text" id="editChapterTitle" class="form-input mb-5" required>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Save Changes</button>
                <button type="button" onclick="closeModal('editChapterModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Topic -->
<div id="addTopicModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-bold text-gray-900">📝 Add Topic</h2>
            <button onclick="closeModal('addTopicModal')" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>
        <form id="addTopicForm">
            <input type="hidden" id="topicChapterId">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Topic Title *</label>
            <input type="text" id="topicTitle" class="form-input mb-4" placeholder="e.g. Variables and Data Types" required>
            <label class="flex items-center gap-2 cursor-pointer mb-5 p-3 bg-orange-50 rounded-lg border border-orange-200">
                <input type="checkbox" id="topicMandatory" value="1" checked class="w-4 h-4 text-green-600 rounded">
                <span class="text-sm font-semibold text-orange-800">Required Topic (students ko complete karna hoga)</span>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Create Topic</button>
                <button type="button" onclick="closeModal('addTopicModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Topic -->
<div id="editTopicModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-bold text-gray-900">✏️ Edit Topic</h2>
            <button onclick="closeModal('editTopicModal')" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>
        <form id="editTopicForm">
            <input type="hidden" id="editTopicId">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Topic Title *</label>
            <input type="text" id="editTopicTitle" class="form-input mb-4" required>
            <label class="flex items-center gap-2 cursor-pointer mb-5 p-3 bg-orange-50 rounded-lg border border-orange-200">
                <input type="checkbox" id="editTopicMandatory" value="1" class="w-4 h-4 text-green-600 rounded">
                <span class="text-sm font-semibold text-orange-800">Required Topic</span>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Save Changes</button>
                <button type="button" onclick="closeModal('editTopicModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     CONTENT EDITOR MODAL — Full Rich Editor
══════════════════════════════════════════════════════════════════════ -->
<div id="contentModal" class="modal">
    <div class="modal-content" style="max-width:980px">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-xl font-bold text-gray-900">🎨 Content Editor</h2>
                <p class="text-sm text-gray-500 mt-0.5" id="contentTopicTitle"></p>
            </div>
            <button onclick="closeModal('contentModal')" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">✕</button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap gap-1.5 p-2.5 bg-gray-50 border border-gray-200 rounded-t-xl border-b-0">
            <!-- Text formatting -->
            <button class="editor-btn" onclick="execCmd('bold')" title="Bold"><b>B</b></button>
            <button class="editor-btn" onclick="execCmd('italic')" title="Italic"><i>I</i></button>
            <button class="editor-btn" onclick="execCmd('underline')" title="Underline"><u>U</u></button>
            <button class="editor-btn" onclick="execCmd('strikeThrough')" title="Strike"><s>S</s></button>
            <div class="w-px bg-gray-300 self-stretch mx-0.5"></div>
            <!-- Headings -->
            <button class="editor-btn font-bold text-xs" onclick="execCmd('formatBlock','h1')" title="Heading 1">H1</button>
            <button class="editor-btn font-bold text-xs" onclick="execCmd('formatBlock','h2')" title="Heading 2">H2</button>
            <button class="editor-btn font-bold text-xs" onclick="execCmd('formatBlock','h3')" title="Heading 3">H3</button>
            <button class="editor-btn text-xs" onclick="execCmd('formatBlock','p')" title="Paragraph">P</button>
            <div class="w-px bg-gray-300 self-stretch mx-0.5"></div>
            <!-- Lists -->
            <button class="editor-btn" onclick="execCmd('insertUnorderedList')" title="Bullet List"><i class="fas fa-list-ul text-xs"></i></button>
            <button class="editor-btn" onclick="execCmd('insertOrderedList')" title="Numbered List"><i class="fas fa-list-ol text-xs"></i></button>
            <div class="w-px bg-gray-300 self-stretch mx-0.5"></div>
            <!-- Alignment -->
            <button class="editor-btn" onclick="execCmd('justifyLeft')" title="Align Left"><i class="fas fa-align-left text-xs"></i></button>
            <button class="editor-btn" onclick="execCmd('justifyCenter')" title="Align Center"><i class="fas fa-align-center text-xs"></i></button>
            <button class="editor-btn" onclick="execCmd('justifyRight')" title="Align Right"><i class="fas fa-align-right text-xs"></i></button>
            <div class="w-px bg-gray-300 self-stretch mx-0.5"></div>
            <!-- Insert -->
            <button class="editor-btn" onclick="insertLink()" title="Insert Link"><i class="fas fa-link text-xs"></i></button>
            <button class="editor-btn" onclick="insertCodeBlock()" title="Code Block"><i class="fas fa-code text-xs"></i></button>
            <button class="editor-btn" onclick="execCmd('formatBlock','blockquote')" title="Quote"><i class="fas fa-quote-left text-xs"></i></button>
            <button class="editor-btn" onclick="execCmd('insertHorizontalRule')" title="Divider">—</button>
            <div class="w-px bg-gray-300 self-stretch mx-0.5"></div>
            <!-- Media -->
            <button class="editor-btn text-xs font-semibold" onclick="triggerImageUpload()" title="Upload Image">📤 Img</button>
            <button class="editor-btn text-xs font-semibold" onclick="insertImageByUrl()" title="Image from URL">🔗 Img</button>
            <button class="editor-btn text-xs font-semibold" onclick="insertYouTube()" title="YouTube Video">▶ YT</button>
            <button class="editor-btn text-xs font-semibold" onclick="insertVideoByUrl()" title="Video URL">🎥 Vid</button>
            <div class="w-px bg-gray-300 self-stretch mx-0.5"></div>
            <!-- Font color -->
            <label class="editor-btn" title="Font Color">
                <i class="fas fa-font text-xs"></i>
                <input type="color" id="fontColorPicker" style="width:0;height:0;opacity:0;position:absolute"
                    onchange="execCmd('foreColor', this.value)">
            </label>
            <!-- Highlight -->
            <label class="editor-btn" title="Highlight">
                <i class="fas fa-highlighter text-xs"></i>
                <input type="color" id="highlightPicker" value="#fef08a" style="width:0;height:0;opacity:0;position:absolute"
                    onchange="execCmd('hiliteColor', this.value)">
            </label>
            <div class="w-px bg-gray-300 self-stretch mx-0.5"></div>
            <!-- Clear -->
            <button class="editor-btn text-red-400 hover:text-red-600" onclick="execCmd('removeFormat')" title="Clear Formatting">
                <i class="fas fa-eraser text-xs"></i>
            </button>
        </div>

        <!-- Editor area -->
        <div id="contentEditor" contenteditable="true" placeholder="Topic ka content yahan likhna shuru karo..."></div>

        <!-- Image upload progress -->
        <div id="imageUploadProgress" class="hidden mt-2 p-3 bg-blue-50 text-blue-700 text-sm rounded-lg font-medium">
            ⏳ Uploading...
        </div>

        <!-- Hidden file input -->
        <input type="file" id="imageUploadInput" accept="image/*,video/*" style="display:none" onchange="handleMediaUpload(event)">

        <div class="flex gap-2 mt-5">
            <button onclick="saveContent()" class="btn-primary flex-1 justify-center text-base">
                <i class="fas fa-save"></i> Save Content
            </button>
            <button onclick="closeModal('contentModal')" class="btn-secondary text-base">Cancel</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toastEl" class="toast"></div>

<script>
const IS_MANAGER = <?= $is_manager ? 'true' : 'false' ?>;
let currentTopicId = null;

// ── Utils ──────────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

const toastEl = document.getElementById('toastEl');
function showToast(msg, type = 'success') {
    toastEl.textContent = msg;
    toastEl.className = 'toast ' + type;
    toastEl.classList.add('show');
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(() => toastEl.classList.remove('show'), 3000);
}

async function ajax(data) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    const res = await fetch(window.location.href, { method: 'POST', body: fd });
    return await res.json();
}

function reload() { setTimeout(() => location.reload(), 700); }

// ── Chapter ────────────────────────────────────────────────────────────────
function openAddChapterModal() {
    document.getElementById('addChapterForm').reset();
    openModal('addChapterModal');
}
function editChapter(id, title) {
    document.getElementById('editChapterId').value = id;
    document.getElementById('editChapterTitle').value = title;
    openModal('editChapterModal');
}

document.getElementById('addChapterForm').addEventListener('submit', async e => {
    e.preventDefault();
    const title = e.target.querySelector('[name=title]').value.trim();
    const r = await ajax({ ajax_action: 'create_chapter', title });
    if (r.success) { showToast('✅ Chapter created!'); reload(); }
    else showToast('❌ ' + (r.message || 'Failed'), 'error');
});

document.getElementById('editChapterForm').addEventListener('submit', async e => {
    e.preventDefault();
    const r = await ajax({
        ajax_action: 'update_chapter',
        id: document.getElementById('editChapterId').value,
        title: document.getElementById('editChapterTitle').value.trim()
    });
    if (r.success) { showToast('✅ Chapter updated!'); reload(); }
    else showToast('❌ ' + (r.message || 'Failed'), 'error');
});

async function deleteChapter(id) {
    if (IS_MANAGER) { showToast('⚠️ Manager delete nahi kar sakta', 'error'); return; }
    if (!confirm('Are you sure? Chapter aur uske saare topics delete ho jaenge.')) return;
    const r = await ajax({ ajax_action: 'delete_chapter', id });
    if (r.success) { showToast('✅ Chapter deleted!'); reload(); }
    else showToast('❌ ' + (r.message || 'Failed'), 'error');
}

// ── Topic ──────────────────────────────────────────────────────────────────
function openAddTopicModal(chapterId) {
    document.getElementById('topicTitle').value = '';
    document.getElementById('topicChapterId').value = chapterId;
    document.getElementById('topicMandatory').checked = true;
    openModal('addTopicModal');
}
function editTopic(id, title, isMandatory) {
    document.getElementById('editTopicId').value = id;
    document.getElementById('editTopicTitle').value = title;
    document.getElementById('editTopicMandatory').checked = isMandatory;
    openModal('editTopicModal');
}

document.getElementById('addTopicForm').addEventListener('submit', async e => {
    e.preventDefault();
    const r = await ajax({
        ajax_action: 'create_topic',
        chapter_id: document.getElementById('topicChapterId').value,
        title: document.getElementById('topicTitle').value.trim(),
        is_mandatory: document.getElementById('topicMandatory').checked ? 1 : 0
    });
    if (r.success) { showToast('✅ Topic created!'); reload(); }
    else showToast('❌ ' + (r.message || 'Failed'), 'error');
});

document.getElementById('editTopicForm').addEventListener('submit', async e => {
    e.preventDefault();
    const r = await ajax({
        ajax_action: 'update_topic',
        id: document.getElementById('editTopicId').value,
        title: document.getElementById('editTopicTitle').value.trim(),
        is_mandatory: document.getElementById('editTopicMandatory').checked ? 1 : 0
    });
    if (r.success) { showToast('✅ Topic updated!'); reload(); }
    else showToast('❌ ' + (r.message || 'Failed'), 'error');
});

async function deleteTopic(id) {
    if (IS_MANAGER) { showToast('⚠️ Manager delete nahi kar sakta', 'error'); return; }
    if (!confirm('Topic aur uska sara content delete ho jaega. Sure?')) return;
    const r = await ajax({ ajax_action: 'delete_topic', id });
    if (r.success) { showToast('✅ Topic deleted!'); reload(); }
    else showToast('❌ ' + (r.message || 'Failed'), 'error');
}

// ── Content Editor ─────────────────────────────────────────────────────────
async function openContentModal(topicId, topicTitle) {
    currentTopicId = topicId;
    document.getElementById('contentTopicTitle').textContent = topicTitle;
    document.getElementById('contentEditor').innerHTML =
        '<p style="color:#9ca3af;font-size:13px;text-align:center;padding:2rem">⏳ Loading content...</p>';
    openModal('contentModal');
    try {
        const r = await ajax({ ajax_action: 'load_content', topic_id: topicId });
        document.getElementById('contentEditor').innerHTML = r.content || '';
    } catch(err) {
        document.getElementById('contentEditor').innerHTML = '';
        showToast('❌ Load failed: ' + err.message, 'error');
    }
}

function execCmd(cmd, val = null) {
    document.getElementById('contentEditor').focus();
    document.execCommand(cmd, false, val);
}

function insertLink() {
    const sel = window.getSelection().toString();
    const url = prompt('Enter URL:', 'https://');
    if (url && url !== 'https://') {
        const text = sel || prompt('Link text:', url) || url;
        execCmd('insertHTML', `<a href="${url}" target="_blank">${text}</a>`);
    }
}

function insertCodeBlock() {
    execCmd('insertHTML', '<pre><code>// Your code here</code></pre><p><br></p>');
}

function insertYouTube() {
    const url = prompt('YouTube URL paste karo:');
    if (!url) return;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&?\/\s]+)/);
    if (!m) { showToast('❌ Invalid YouTube URL', 'error'); return; }
    const id = m[1];
    execCmd('insertHTML',
        `<div contenteditable="false" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;margin:16px 0">
            <iframe src="https://www.youtube.com/embed/${id}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;border-radius:12px" allowfullscreen loading="lazy"></iframe>
        </div><p><br></p>`
    );
    showToast('✅ YouTube video inserted!');
}

function insertVideoByUrl() {
    const url = prompt('Video URL paste karo (mp4/webm):');
    if (!url) return;
    execCmd('insertHTML',
        `<div contenteditable="false" style="margin:16px 0">
            <video controls style="max-width:100%;border-radius:12px;display:block">
                <source src="${url}" type="video/mp4">
            </video>
        </div><p><br></p>`
    );
}

function insertImageByUrl() {
    const url = prompt('Image URL paste karo:');
    if (url && url.trim()) {
        execCmd('insertHTML', `<img src="${url.trim()}" alt="image" style="max-width:100%;border-radius:8px;margin:10px 0"><p><br></p>`);
    }
}

function triggerImageUpload() {
    document.getElementById('imageUploadInput').click();
}

async function handleMediaUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    const prog = document.getElementById('imageUploadProgress');
    prog.textContent = '⏳ Uploading ' + file.name + '...';
    prog.classList.remove('hidden');
    const fd = new FormData();
    fd.append('ajax_action', 'upload_media');
    fd.append('file', file);
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const r = await res.json();
        prog.classList.add('hidden');
        event.target.value = '';
        if (r.success && r.url) {
            const ext = file.name.split('.').pop().toLowerCase();
            const isVideo = ['mp4','webm','ogg','mov'].includes(ext);
            if (isVideo) {
                execCmd('insertHTML',
                    `<div contenteditable="false" style="margin:16px 0">
                        <video controls style="max-width:100%;border-radius:12px;display:block">
                            <source src="${r.url}" type="video/${ext}">
                        </video>
                    </div><p><br></p>`
                );
            } else {
                execCmd('insertHTML', `<img src="${r.url}" alt="${file.name}" style="max-width:100%;border-radius:8px;margin:10px 0"><p><br></p>`);
            }
            showToast('✅ Uploaded: ' + file.name);
        } else {
            showToast('❌ ' + (r.message || 'Upload failed'), 'error');
        }
    } catch(err) {
        prog.classList.add('hidden');
        showToast('❌ Network error: ' + err.message, 'error');
    }
}

async function saveContent() {
    if (!currentTopicId) { showToast('❌ No topic selected', 'error'); return; }
    const content = document.getElementById('contentEditor').innerHTML;
    try {
        const r = await ajax({ ajax_action: 'save_content', topic_id: currentTopicId, content });
        if (r.success) showToast('✅ Content saved successfully!');
        else showToast('❌ ' + (r.message || 'Save failed'), 'error');
    } catch(err) {
        showToast('❌ Network error: ' + err.message, 'error');
    }
}

// Color picker click forward
document.querySelector('label[title="Font Color"]').addEventListener('click', () => {
    document.getElementById('fontColorPicker').click();
});
document.querySelector('label[title="Highlight"]').addEventListener('click', () => {
    document.getElementById('highlightPicker').click();
});

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal(modal.id);
    });
});
</script>
<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>