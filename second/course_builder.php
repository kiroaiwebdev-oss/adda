<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('courses_edit');

$db = getDB();
$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) { header('Location: courses.php'); exit; }

$course = $db->prepare("SELECT id, title, status FROM courses WHERE id = ?");
$course->execute([$courseId]);
$course = $course->fetch();
if (!$course) { header('Location: courses.php'); exit; }

$msg = '';

// ── AJAX / POST HANDLER ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── CHAPTER ACTIONS ──
    if ($action === 'add_chapter') {
        $title = trim($_POST['title'] ?? 'New Chapter');
        $order = (int)$db->query("SELECT COALESCE(MAX(sortorder),0)+1 FROM chapters WHERE courseid=$courseId")->fetchColumn();
        $db->prepare("INSERT INTO chapters (courseid, title, sortorder, chapterorder, createdat, updatedat) VALUES (?,?,?,?,NOW(),NOW())")
           ->execute([$courseId, $title, $order, $order]);
        $id = $db->lastInsertId();
        logAction('chapter_added','course',$courseId,"Chapter: $title");
        echo json_encode(['ok'=>true,'id'=>$id,'title'=>$title,'order'=>$order]); exit;
    }
    if ($action === 'update_chapter') {
        $id    = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $db->prepare("UPDATE chapters SET title=?, updatedat=NOW() WHERE id=? AND courseid=?")
               ->execute([$title, $id, $courseId]);
            logAction('chapter_updated','course',$courseId,"Chapter #$id: $title");
        }
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_chapter') {
        // Manager cannot delete
        echo json_encode(['ok'=>false,'msg'=>'Delete permission nahi hai']); exit;
    }

    // ── TOPIC ACTIONS ──
    if ($action === 'add_topic') {
        $chapterId = (int)$_POST['chapter_id'];
        $title     = trim($_POST['title'] ?? 'New Topic');
        $order     = (int)$db->query("SELECT COALESCE(MAX(sortorder),0)+1 FROM topics WHERE chapterid=$chapterId")->fetchColumn();
        $db->prepare("INSERT INTO topics (chapterid, title, sortorder, createdat, updatedat) VALUES (?,?,?,NOW(),NOW())")
           ->execute([$chapterId, $title, $order]);
        $id = $db->lastInsertId();
        logAction('topic_added','course',$courseId,"Topic: $title in chapter #$chapterId");
        echo json_encode(['ok'=>true,'id'=>$id,'title'=>$title]); exit;
    }
    if ($action === 'update_topic') {
        $id    = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $db->prepare("UPDATE topics SET title=?, updatedat=NOW() WHERE id=?")
               ->execute([$title, $id]);
            logAction('topic_updated','course',$courseId,"Topic #$id: $title");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    // ── CONTENT BLOCK ACTIONS ──
    if ($action === 'add_block') {
        $topicId = (int)$_POST['topic_id'];
        $type    = in_array($_POST['type'],['text','video','quiz']) ? $_POST['type'] : 'text';
        $order   = (int)$db->query("SELECT COALESCE(MAX(sortorder),0)+1 FROM contentblocks WHERE topicid=$topicId")->fetchColumn();
        $db->prepare("INSERT INTO contentblocks (topicid, type, content, sortorder, createdat, updatedat) VALUES (?,?,'',?,NOW(),NOW())")
           ->execute([$topicId, $type, $order]);
        $id = $db->lastInsertId();
        logAction('content_block_added','course',$courseId,"Block type:$type in topic #$topicId");
        echo json_encode(['ok'=>true,'id'=>$id,'type'=>$type]); exit;
    }
    if ($action === 'save_block') {
        $id      = (int)$_POST['id'];
        $content = $_POST['content'] ?? '';
        $db->prepare("UPDATE contentblocks SET content=?, updatedat=NOW() WHERE id=?")
           ->execute([$content, $id]);
        logAction('content_saved','course',$courseId,"Block #$id saved");
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_block') {
        echo json_encode(['ok'=>false,'msg'=>'Delete permission nahi hai']); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
}

// ── LOAD DATA ─────────────────────────────────────────────────
$chapters = $db->prepare(
    "SELECT c.*, 
        (SELECT COUNT(*) FROM topics WHERE chapterid=c.id) as topic_count
     FROM chapters c WHERE c.courseid=? ORDER BY c.sortorder, c.id"
);
$chapters->execute([$courseId]);
$chapters = $chapters->fetchAll();

// Load topics + blocks for each chapter
foreach ($chapters as &$ch) {
    $topics = $db->prepare("SELECT * FROM topics WHERE chapterid=? ORDER BY sortorder, id");
    $topics->execute([$ch['id']]);
    $ch['topics'] = $topics->fetchAll();
    foreach ($ch['topics'] as &$tp) {
        $blocks = $db->prepare("SELECT * FROM contentblocks WHERE topicid=? ORDER BY sortorder, id");
        $blocks->execute([$tp['id']]);
        $tp['blocks'] = $blocks->fetchAll();
    }
}
unset($ch, $tp);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Course Builder — <?= htmlspecialchars($course['title']) ?></title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{
  --bg:#f9fafb;--surface:#fff;--surface-2:#f3f4f6;
  --border:#e5e7eb;--divider:#e5e7eb;
  --text:#111827;--muted:#6b7280;--faint:#9ca3af;
  --primary:#16a34a;--primary-h:#15803d;--primary-hl:#dcfce7;
  --success:#437a22;--error:#a12c7b;--orange:#da7101;--blue:#006494;
  --r-sm:.375rem;--r-md:.5rem;--r-lg:.75rem;--r-xl:1rem;
  --shadow-sm:0 1px 2px rgba(0,0,0,0.05);
  --shadow-md:0 4px 16px oklch(0.2 0.01 80/0.1);
  --t:180ms cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#171614;--surface:#1c1b19;--surface-2:#201f1d;
  --border:rgba(255,255,255,0.08);--divider:#262523;
  --text:#cdccca;--muted:#797876;--faint:#5a5957;
  --primary:#4ade80;--primary-h:#22c55e;
  --success:#6daa45;--error:#d163a7;--orange:#fdab43;--blue:#5591c7;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh}
a{color:inherit;text-decoration:none}
button{cursor:pointer;font:inherit;color:inherit}

/* TOPBAR */
.topbar{background:var(--surface);border-bottom:1px solid var(--divider);padding:.75rem 1.5rem;display:flex;align-items:center;gap:1rem;position:sticky;top:0;z-index:100}
.back-btn{display:inline-flex;align-items:center;gap:.4rem;font-size:.83rem;color:var(--muted);background:none;border:none;padding:.35rem .75rem;border-radius:var(--r-md);transition:background var(--t),color var(--t)}
.back-btn:hover{background:var(--bg);color:var(--text)}
.course-title{font-weight:700;font-size:1rem;flex:1}
.course-status{font-size:.72rem;font-weight:600;padding:.2rem .65rem;border-radius:9999px}
.status-published{background:rgba(67,122,34,0.12);color:var(--success)}
.status-draft{background:rgba(107,114,128,0.15);color:var(--muted)}

/* LAYOUT */
.builder-layout{display:flex;gap:0;min-height:calc(100dvh - 56px)}
.sidebar-chapters{width:280px;background:var(--surface);border-right:1px solid var(--divider);overflow-y:auto;flex-shrink:0;position:sticky;top:56px;height:calc(100dvh - 56px)}
.main-area{flex:1;padding:1.5rem;overflow-y:auto}

/* CHAPTER LIST */
.ch-head{padding:.9rem 1rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between}
.ch-head h3{font-size:.85rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.btn-add-sm{background:var(--primary);color:#fff;border:none;padding:.35rem .75rem;border-radius:var(--r-md);font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;transition:background var(--t)}
.btn-add-sm:hover{background:var(--primary-h)}
.chapter-item{border-bottom:1px solid var(--divider);cursor:pointer;transition:background var(--t)}
.chapter-item:hover{background:var(--bg)}
.chapter-item.active{background:rgba(22,163,74,0.08)}
.ch-item-head{padding:.7rem 1rem;display:flex;align-items:center;gap:.6rem}
.ch-num{width:22px;height:22px;border-radius:50%;background:var(--primary);color:#fff;font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ch-item-head.active-head .ch-num{background:var(--primary)}
.ch-name{font-size:.85rem;font-weight:500;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ch-count{font-size:.7rem;color:var(--muted);flex-shrink:0}

/* TOPIC LIST INSIDE CHAPTER SIDEBAR */
.topic-list-sidebar{padding:.35rem .5rem .5rem 2.8rem;display:none}
.chapter-item.active .topic-list-sidebar{display:block}
.topic-sidebar-item{padding:.3rem .6rem;border-radius:var(--r-sm);font-size:.78rem;color:var(--muted);cursor:pointer;transition:background var(--t),color var(--t);display:flex;align-items:center;gap:.4rem}
.topic-sidebar-item:hover,.topic-sidebar-item.active{background:rgba(22,163,74,0.1);color:var(--primary)}
.topic-sidebar-item svg{width:12px;height:12px;flex-shrink:0}

/* MAIN CONTENT AREA */
.section-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap}
.section-header h2{font-size:1.1rem;font-weight:700;flex:1}
.btn{border:none;padding:.55rem 1.1rem;border-radius:var(--r-md);font:inherit;font-size:.83rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;transition:background var(--t),color var(--t)}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-h)}
.btn-ghost{background:none;border:1.5px solid var(--border);color:var(--muted)}.btn-ghost:hover{background:var(--bg);color:var(--text)}
.btn-success{background:rgba(67,122,34,0.12);color:var(--success)}.btn-success:hover{background:rgba(67,122,34,0.2)}
.btn-danger-soft{background:rgba(161,44,123,0.08);color:var(--error);opacity:.5;cursor:not-allowed}

/* CHAPTER CARD */
.chapter-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);margin-bottom:1.5rem;overflow:hidden;box-shadow:var(--shadow-sm)}
.chapter-card-head{padding:.9rem 1.25rem;background:oklch(from var(--primary) l c h/0.05);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.75rem}
.chapter-card-head input{flex:1;background:transparent;border:none;font:inherit;font-size:.95rem;font-weight:700;color:var(--text);padding:.2rem .4rem;border-radius:var(--r-sm);transition:background var(--t)}
.chapter-card-head input:focus{outline:none;background:var(--surface)}
.no-del-badge{font-size:.7rem;color:var(--faint);display:flex;align-items:center;gap:.25rem}

/* TOPIC CARD */
.topic-card{border:1px solid var(--border);border-radius:var(--r-lg);margin:.75rem 1rem 1rem;overflow:hidden}
.topic-head{padding:.7rem 1rem;background:var(--bg);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.6rem}
.topic-head input{flex:1;background:transparent;border:none;font:inherit;font-size:.88rem;font-weight:600;color:var(--text);padding:.15rem .3rem;border-radius:var(--r-sm)}
.topic-head input:focus{outline:none;background:var(--surface)}
.topic-body{padding:1rem}

/* CONTENT BLOCK */
.block-card{border:1.5px solid var(--border);border-radius:var(--r-md);margin-bottom:.9rem;overflow:hidden;transition:box-shadow var(--t)}
.block-card:hover{box-shadow:var(--shadow-sm)}
.block-head{padding:.55rem .9rem;background:var(--bg);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.6rem}
.block-type-badge{font-size:.7rem;font-weight:700;padding:.18rem .55rem;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em}
.type-text{background:rgba(0,100,148,0.12);color:var(--blue)}
.type-video{background:rgba(161,44,123,0.12);color:var(--error)}
.type-quiz{background:rgba(218,113,1,0.12);color:var(--orange)}
.block-body{padding:.75rem}
.block-textarea{width:100%;min-height:120px;border:1.5px solid var(--border);border-radius:var(--r-md);padding:.65rem .9rem;font:inherit;font-size:.85rem;background:var(--bg);color:var(--text);resize:vertical;transition:border-color var(--t)}
.block-textarea:focus{outline:none;border-color:var(--primary)}
.block-actions{display:flex;gap:.5rem;margin-top:.5rem;align-items:center}
.saved-indicator{font-size:.75rem;color:var(--success);display:none;align-items:center;gap:.3rem}
.saved-indicator.show{display:flex}
.video-help{font-size:.75rem;color:var(--muted);margin-top:.4rem;padding:.5rem .75rem;background:rgba(22,163,74,0.06);border-radius:var(--r-sm)}

/* ADD BLOCK TOOLBAR */
.add-block-toolbar{display:flex;gap:.5rem;padding:.75rem;background:var(--surface-2);border-top:1px solid var(--border)}
.add-block-btn{padding:.4rem .8rem;border-radius:var(--r-md);font-size:.78rem;font-weight:600;border:1.5px solid var(--border);background:var(--surface);cursor:pointer;transition:background var(--t),border-color var(--t)}
.add-block-btn:hover{border-color:var(--primary);color:var(--primary)}

/* EMPTY STATE */
.empty-state{text-align:center;padding:2.5rem 1rem;color:var(--muted)}
.empty-state svg{width:36px;height:36px;margin:0 auto .75rem;opacity:.4}
.empty-state p{font-size:.85rem}

/* TOAST */
.toast{position:fixed;bottom:1.5rem;right:1.5rem;background:var(--text);color:var(--bg);padding:.65rem 1.1rem;border-radius:var(--r-md);font-size:.83rem;font-weight:500;z-index:9999;transform:translateY(80px);opacity:0;transition:transform .3s ease,opacity .3s ease;pointer-events:none}
.toast.show{transform:translateY(0);opacity:1}

/* RESTRICT BANNER */
.restrict-banner{background:rgba(161,44,123,0.07);border:1px solid rgba(161,44,123,0.2);color:var(--error);padding:.6rem 1rem;border-radius:var(--r-md);font-size:.8rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem}

@media(max-width:768px){
  .sidebar-chapters{display:none}
  .main-area{padding:1rem}
}
</style>
</head>
<body>

<div class="topbar">
  <a href="courses.php" class="back-btn">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Courses
  </a>
  <div class="course-title"><?= htmlspecialchars($course['title']) ?></div>
  <span class="course-status status-<?= $course['status'] ?>"><?= ucfirst($course['status']) ?></span>
  <a href="course_edit.php?id=<?= $courseId ?>" class="btn btn-ghost" style="font-size:.78rem">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    Course Info
  </a>
</div>

<div class="builder-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar-chapters">
    <div class="ch-head">
      <h3>Chapters</h3>
      <button class="btn-add-sm" onclick="addChapter()">+ Add</button>
    </div>
    <?php if(empty($chapters)): ?>
      <div style="padding:1.5rem;text-align:center;color:var(--muted);font-size:.83rem">No chapters yet</div>
    <?php endif; ?>
    <?php foreach($chapters as $i => $ch): ?>
    <div class="chapter-item <?= $i===0?'active':'' ?>" id="ch-sidebar-<?= $ch['id'] ?>" onclick="selectChapter(<?= $ch['id'] ?>, this)">
      <div class="ch-item-head">
        <div class="ch-num"><?= $i+1 ?></div>
        <div class="ch-name"><?= htmlspecialchars($ch['title']) ?></div>
        <div class="ch-count"><?= $ch['topic_count'] ?> topics</div>
      </div>
      <div class="topic-list-sidebar">
        <?php foreach($ch['topics'] as $tp): ?>
        <div class="topic-sidebar-item" onclick="event.stopPropagation();scrollToTopic(<?= $tp['id'] ?>)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/></svg>
          <?= htmlspecialchars(mb_substr($tp['title'],0,22)) ?><?= mb_strlen($tp['title'])>22?'…':'' ?>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:.4rem">
          <button class="btn-add-sm" style="width:100%;justify-content:center" onclick="event.stopPropagation();addTopic(<?= $ch['id'] ?>)">+ Topic</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </aside>

  <!-- MAIN -->
  <main class="main-area" id="mainArea">
    <div class="restrict-banner">
      🔒 Manager Mode — Chapter/Topic/Content edit kar sakte hain. Delete restricted hai.
    </div>

    <?php if(empty($chapters)): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      <p>No chapters yet. Left sidebar se "Add" click karo.</p>
    </div>
    <?php endif; ?>

    <?php foreach($chapters as $i => $ch): ?>
    <div class="chapter-card" id="chapter-<?= $ch['id'] ?>">
      <div class="chapter-card-head">
        <div class="ch-num" style="width:26px;height:26px;font-size:.75rem"><?= $i+1 ?></div>
        <input type="text" value="<?= htmlspecialchars($ch['title']) ?>"
               onblur="updateChapter(<?= $ch['id'] ?>, this.value)"
               onkeydown="if(event.key==='Enter')this.blur()"
               placeholder="Chapter title...">
        <button class="btn btn-primary" onclick="addTopic(<?= $ch['id'] ?>)" style="font-size:.75rem;padding:.35rem .75rem">+ Topic</button>
        <span class="no-del-badge">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          No delete
        </span>
      </div>

      <?php if(empty($ch['topics'])): ?>
      <div style="padding:1.5rem;text-align:center;color:var(--muted);font-size:.83rem">Koi topic nahi — "Add Topic" click karo</div>
      <?php endif; ?>

      <?php foreach($ch['topics'] as $tp): ?>
      <div class="topic-card" id="topic-<?= $tp['id'] ?>">
        <div class="topic-head">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <input type="text" value="<?= htmlspecialchars($tp['title']) ?>"
                 onblur="updateTopic(<?= $tp['id'] ?>, this.value)"
                 onkeydown="if(event.key==='Enter')this.blur()"
                 placeholder="Topic title...">
          <span style="font-size:.72rem;color:var(--faint)"><?= count($tp['blocks']) ?> blocks</span>
        </div>
        <div class="topic-body">
          <?php if(empty($tp['blocks'])): ?>
          <div style="color:var(--muted);font-size:.82rem;padding:.5rem 0 1rem">Koi content block nahi</div>
          <?php endif; ?>

          <?php foreach($tp['blocks'] as $blk): ?>
          <div class="block-card" id="block-<?= $blk['id'] ?>">
            <div class="block-head">
              <span class="block-type-badge type-<?= $blk['type'] ?>"><?= $blk['type'] ?></span>
              <span style="font-size:.75rem;color:var(--muted)">Block #<?= $blk['id'] ?></span>
              <div style="margin-left:auto;display:flex;gap:.4rem">
                <button class="btn btn-success" style="font-size:.72rem;padding:.3rem .65rem" onclick="saveBlock(<?= $blk['id'] ?>, this)">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Save
                </button>
                <button class="btn btn-danger-soft" style="font-size:.72px;padding:.3rem .65rem" title="Delete restricted" disabled>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                </button>
              </div>
            </div>
            <div class="block-body">
              <?php if($blk['type'] === 'video'): ?>
                <input type="text" class="block-textarea" style="min-height:auto;height:42px"
                       id="block-content-<?= $blk['id'] ?>"
                       placeholder="YouTube embed URL ya video URL paste karo..."
                       value="<?= htmlspecialchars($blk['content']) ?>">
                <div class="video-help">Example: https://www.youtube.com/embed/VIDEO_ID</div>
              <?php elseif($blk['type'] === 'quiz'): ?>
                <textarea class="block-textarea" id="block-content-<?= $blk['id'] ?>"
                          placeholder='Quiz JSON format: [{"question":"Q1?","options":["A","B","C"],"answer":0}]'><?= htmlspecialchars($blk['content']) ?></textarea>
              <?php else: ?>
                <textarea class="block-textarea" id="block-content-<?= $blk['id'] ?>"
                          placeholder="HTML content yahan likhein... <h3>Title</h3><p>Content</p>"><?= htmlspecialchars($blk['content']) ?></textarea>
              <?php endif; ?>
              <div class="block-actions">
                <button class="btn btn-success" style="font-size:.78rem" onclick="saveBlock(<?= $blk['id'] ?>, this)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Save Content
                </button>
                <span class="saved-indicator" id="saved-<?= $blk['id'] ?>">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Saved!
                </span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

          <!-- ADD BLOCK TOOLBAR -->
          <div class="add-block-toolbar">
            <span style="font-size:.75rem;color:var(--muted);margin-right:.25rem">Add block:</span>
            <button class="add-block-btn" onclick="addBlock(<?= $tp['id'] ?>, 'text')">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Text/HTML
            </button>
            <button class="add-block-btn" onclick="addBlock(<?= $tp['id'] ?>, 'video')">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
              Video
            </button>
            <button class="add-block-btn" onclick="addBlock(<?= $tp['id'] ?>, 'quiz')">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
              Quiz
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </main>
</div>

<div class="toast" id="toast"></div>

<script>
const COURSE_ID = <?= $courseId ?>;

function showToast(msg, isError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = isError ? 'var(--error)' : 'var(--text)';
  t.style.color = isError ? '#fff' : 'var(--bg)';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2200);
}

async function post(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k,v));
  const r = await fetch(location.href, {method:'POST', body:fd});
  return r.json();
}

function selectChapter(id, el) {
  document.querySelectorAll('.chapter-item').forEach(x => x.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('chapter-'+id)?.scrollIntoView({behavior:'smooth',block:'start'});
}

function scrollToTopic(id) {
  document.getElementById('topic-'+id)?.scrollIntoView({behavior:'smooth',block:'start'});
}

async function addChapter() {
  const title = prompt('Chapter title:', 'New Chapter');
  if (!title) return;
  const r = await post({action:'add_chapter', title});
  if (r.ok) { showToast('Chapter added! Page reload ho raha hai...'); setTimeout(()=>location.reload(),800); }
  else showToast(r.msg || 'Error', true);
}

async function updateChapter(id, title) {
  if (!title.trim()) return;
  const r = await post({action:'update_chapter', id, title});
  if (r.ok) showToast('Chapter updated ✓');
}

async function addTopic(chapterId) {
  const title = prompt('Topic title:', 'New Topic');
  if (!title) return;
  const r = await post({action:'add_topic', chapter_id:chapterId, title});
  if (r.ok) { showToast('Topic added!'); setTimeout(()=>location.reload(),800); }
  else showToast(r.msg || 'Error', true);
}

async function updateTopic(id, title) {
  if (!title.trim()) return;
  const r = await post({action:'update_topic', id, title});
  if (r.ok) showToast('Topic updated ✓');
}

async function addBlock(topicId, type) {
  const r = await post({action:'add_block', topic_id:topicId, type});
  if (r.ok) { showToast(`${type} block added!`); setTimeout(()=>location.reload(),800); }
  else showToast(r.msg || 'Error', true);
}

async function saveBlock(id, btn) {
  const el = document.getElementById('block-content-'+id);
  if (!el) return;
  const content = el.value ?? el.textContent;
  const r = await post({action:'save_block', id, content});
  if (r.ok) {
    showToast('Content saved ✓');
    const ind = document.getElementById('saved-'+id);
    if (ind) { ind.classList.add('show'); setTimeout(()=>ind.classList.remove('show'),2000); }
  } else showToast(r.msg || 'Error', true);
}

// Dark mode toggle
(function(){
  const r = document.documentElement;
  let d = 'light';
  r.setAttribute('data-theme', d);
})();
</script>
</body>
</html>