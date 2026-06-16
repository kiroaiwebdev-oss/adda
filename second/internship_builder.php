<?php
// second/internship_builder.php — MANAGER VERSION
// View + Edit + Add modules/lessons/content/quiz — NO DELETE

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/manager_auth.php';

$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
$user_id   = $_SESSION['user_id']   ?? $_SESSION['id']   ?? null;

if (!$user_id || !in_array($user_role, ['admin', 'manager', 'super_admin'])) {
    header('Location: manager_login.php');
    exit;
}

// DB Connect
global $db;
if (!isset($db) || !$db) {
    $dbConfig = require __DIR__ . '/db.php';
    try {
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $dbConfig['host'], $dbConfig['port'] ?? 3306,
            $dbConfig['database'], $dbConfig['charset'] ?? 'utf8mb4'
        );
        $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die('<p style="font-family:sans-serif;padding:2rem;color:red">❌ DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>');
    }
}

$internshipId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$internshipId) {
    header('Location: internships.php');
    exit;
}

// Get internship
$stmt = $db->prepare("SELECT * FROM `internships` WHERE `id` = ?");
$stmt->execute([$internshipId]);
$internship = $stmt->fetch();

if (!$internship) {
    header('Location: internships.php');
    exit;
}

// Get modules → lessons → content_blocks + quiz count
$stmt = $db->prepare("SELECT * FROM `internship_modules` WHERE `internship_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
$stmt->execute([$internshipId]);
$modulesTemp = $stmt->fetchAll();

$modules = [];
foreach ($modulesTemp as $mi => $moduleRow) {
    $modules[$mi] = $moduleRow;

    $stmt = $db->prepare("SELECT * FROM `internship_lessons` WHERE `module_id` = ? ORDER BY `sort_order` ASC");
    $stmt->execute([$moduleRow['id']]);
    $lessonsTemp = $stmt->fetchAll();

    $modules[$mi]['lessons'] = [];
    foreach ($lessonsTemp as $li => $lessonRow) {
        $modules[$mi]['lessons'][$li] = $lessonRow;

        $stmt = $db->prepare("SELECT * FROM `internship_content_blocks` WHERE `lesson_id` = ? ORDER BY `sort_order` ASC");
        $stmt->execute([$lessonRow['id']]);
        $modules[$mi]['lessons'][$li]['content_blocks'] = $stmt->fetchAll();

        $qStmt = $db->prepare("SELECT COUNT(*) FROM `internship_quiz_questions` WHERE `lesson_id` = ?");
        $qStmt->execute([$lessonRow['id']]);
        $modules[$mi]['lessons'][$li]['quiz_count'] = (int)$qStmt->fetchColumn();
    }
}
unset($modulesTemp, $lessonsTemp);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Internship Builder — <?= htmlspecialchars($internship['title']) ?></title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root,[data-theme="light"]{
  --bg:#f7f6f2;--surface:#fff;--surface-2:#fbfbf9;
  --border:rgba(40,37,29,.12);--divider:#dcd9d5;
  --text:#28251d;--muted:#7a7974;--faint:#bab9b4;
  --primary:#01696f;--primary-h:#0c4e54;--primary-light:rgba(1,105,111,.08);
  --success:#437a22;--warning:#964219;--error:#a12c7b;--orange:#da7101;
  --blue:#006494;--purple:#7a39bb;
  --r-sm:.375rem;--r-md:.5rem;--r-lg:.75rem;--r-xl:1rem;
  --shadow-sm:0 1px 2px rgba(0,0,0,.06);
  --shadow-md:0 4px 12px rgba(0,0,0,.08);
  --t:180ms cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#171614;--surface:#1c1b19;--surface-2:#201f1d;
  --border:rgba(255,255,255,.08);--divider:#262523;
  --text:#cdccca;--muted:#797876;--faint:#5a5957;
  --primary:#4f98a3;--primary-h:#227f8b;--primary-light:rgba(79,152,163,.1);
  --success:#6daa45;--warning:#bb653b;--error:#d163a7;--orange:#fdab43;
  --blue:#5591c7;--purple:#a86fdf;
  --shadow-sm:0 1px 2px rgba(0,0,0,.2);
  --shadow-md:0 4px 12px rgba(0,0,0,.3);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh;display:flex}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}

/* SIDEBAR */
.sidebar{width:240px;min-height:100dvh;background:var(--surface);border-right:1px solid var(--divider);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100}
.sidebar-logo{padding:1.1rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;gap:.75rem}
.sidebar-logo svg{width:30px;height:30px;flex-shrink:0}
.logo-text{font-weight:700;font-size:.92rem}.logo-text span{color:var(--primary)}
.sidebar-user{padding:.75rem 1.25rem;border-bottom:1px solid var(--divider)}
.user-badge{font-size:.7rem;font-weight:700;background:var(--primary-light);color:var(--primary);padding:.18rem .55rem;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em;display:inline-block;margin-bottom:.3rem}
.user-name{font-weight:600;font-size:.88rem}
nav{flex:1;padding:.5rem 0;overflow-y:auto}
.nav-section{padding:.45rem 1.25rem .2rem;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint)}
.nav-item{display:flex;align-items:center;gap:.65rem;padding:.5rem 1.25rem;font-size:.85rem;color:var(--muted);transition:color var(--t),background var(--t);position:relative}
.nav-item:hover{background:var(--bg);color:var(--text)}
.nav-item.active{background:var(--primary-light);color:var(--primary);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--primary);border-radius:0 4px 4px 0}
.nav-item svg{width:15px;height:15px;flex-shrink:0;opacity:.7}
.nav-item.active svg,.nav-item:hover svg{opacity:1}
.sidebar-footer{padding:.9rem 1.25rem;border-top:1px solid var(--divider);display:flex;gap:.5rem}
.btn-sm{padding:.4rem .85rem;font-size:.78rem;border-radius:var(--r-md);font-weight:500;cursor:pointer;transition:background var(--t),color var(--t)}
.btn-ghost{border:1px solid var(--border);color:var(--muted);background:none}.btn-ghost:hover{background:var(--bg)}
.btn-danger{background:rgba(161,44,123,.1);color:var(--error);border:none}.btn-danger:hover{background:rgba(161,44,123,.18)}

/* MAIN */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100dvh}
.topbar{background:var(--surface);border-bottom:1px solid var(--divider);padding:.85rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;gap:1rem}
.topbar-left{display:flex;flex-direction:column;gap:.2rem}
.breadcrumb{font-size:.75rem;color:var(--muted)}
.breadcrumb a:hover{color:var(--primary)}
.page-title{font-size:1.1rem;font-weight:700;display:flex;align-items:center;gap:.5rem}
.restrict-badge{display:inline-flex;align-items:center;gap:.3rem;background:rgba(161,44,123,.07);border:1px solid rgba(161,44,123,.2);color:var(--error);padding:.25rem .7rem;border-radius:9999px;font-size:.7rem;font-weight:600}
.topbar-actions{display:flex;gap:.65rem;align-items:center}
.btn-back{padding:.45rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);font-size:.83rem;font-weight:600;color:var(--muted);cursor:pointer;transition:background var(--t)}
.btn-back:hover{background:var(--bg);color:var(--text)}
.btn-primary{padding:.5rem 1.1rem;background:var(--primary);color:#fff;border:none;border-radius:var(--r-md);font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:background var(--t);display:inline-flex;align-items:center;gap:.4rem}
.btn-primary:hover{background:var(--primary-h)}

/* CONTENT */
.content{padding:1.25rem 1.5rem;flex:1}

/* MODULE CARD */
.module-card{background:var(--surface);border:1.5px solid var(--border);border-radius:var(--r-xl);margin-bottom:1rem;overflow:hidden;box-shadow:var(--shadow-sm);transition:border-color var(--t),box-shadow var(--t)}
.module-card:hover{border-color:var(--primary);box-shadow:var(--shadow-md)}
.module-header{padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--divider)}
.module-num{width:38px;height:38px;background:var(--primary);color:#fff;border-radius:var(--r-lg);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0}
.module-info{flex:1}
.module-title{font-weight:700;font-size:.97rem}
.module-meta{font-size:.75rem;color:var(--muted);margin-top:.15rem}
.module-actions{display:flex;gap:.5rem;align-items:center}
.btn-add-lesson{padding:.38rem .85rem;background:var(--primary-light);color:var(--primary);border:1px solid rgba(1,105,111,.2);border-radius:var(--r-md);font-size:.78rem;font-weight:600;transition:background var(--t)}
.btn-add-lesson:hover{background:rgba(1,105,111,.15)}
.btn-icon{width:32px;height:32px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:background var(--t),color var(--t)}
.btn-icon-edit{color:var(--muted)}.btn-icon-edit:hover{background:var(--bg);color:var(--text)}

/* LESSONS */
.lessons-wrap{padding:.75rem 1.25rem 1.25rem}
.empty-lessons{border:1.5px dashed var(--divider);border-radius:var(--r-lg);padding:1.5rem;text-align:center;color:var(--muted);font-size:.83rem}
.lesson-card{background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:.85rem 1rem;margin-bottom:.65rem;transition:border-color var(--t),background var(--t)}
.lesson-card:hover{background:var(--surface);border-color:var(--primary)}
.lesson-inner{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.lesson-num{width:28px;height:28px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;color:var(--primary);flex-shrink:0}
.lesson-info{flex:1}
.lesson-title{font-weight:600;font-size:.87rem}
.lesson-meta{display:flex;align-items:center;gap:.5rem;margin-top:.2rem;flex-wrap:wrap}
.meta-chip{font-size:.7rem;color:var(--muted)}
.quiz-chip{font-size:.7rem;background:rgba(218,113,1,.1);color:var(--orange);padding:.15rem .5rem;border-radius:9999px;font-weight:600}
.lesson-actions{display:flex;gap:.4rem;align-items:center}
.btn-content{padding:.32rem .75rem;background:rgba(0,100,148,.08);color:var(--blue);border:1px solid rgba(0,100,148,.2);border-radius:var(--r-md);font-size:.75rem;font-weight:600;transition:background var(--t)}
.btn-content:hover{background:rgba(0,100,148,.14)}
.btn-quiz{padding:.32rem .75rem;background:rgba(218,113,1,.08);color:var(--orange);border:1px solid rgba(218,113,1,.2);border-radius:var(--r-md);font-size:.75rem;font-weight:600;transition:background var(--t)}
.btn-quiz:hover{background:rgba(218,113,1,.14)}

/* EMPTY STATE */
.empty-state{background:var(--surface);border:2px dashed var(--divider);border-radius:var(--r-xl);padding:3.5rem 2rem;text-align:center}
.empty-icon{width:52px;height:52px;background:var(--primary-light);border-radius:var(--r-xl);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;color:var(--primary)}
.empty-state h3{font-size:1rem;font-weight:700;margin-bottom:.35rem}
.empty-state p{font-size:.83rem;color:var(--muted);margin-bottom:1.25rem}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:1.25rem}
.modal-overlay.active{display:flex}
.modal-box{background:var(--surface);border-radius:var(--r-xl);width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,.25)}
.modal-box.sm{max-width:500px}
.modal-box.lg{max-width:960px}
.modal-box.xl{max-width:1200px}
.modal-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface);z-index:2}
.modal-header h2{font-size:1.05rem;font-weight:700}
.modal-close{width:32px;height:32px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;color:var(--muted);transition:background var(--t)}
.modal-close:hover{background:var(--bg);color:var(--text)}
.modal-body{padding:1.25rem 1.5rem}
.modal-footer{padding:1rem 1.5rem;border-top:1px solid var(--divider);display:flex;gap:.65rem;justify-content:flex-end}

/* FORM */
.form-group{margin-bottom:1rem}
.form-label{display:block;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);margin-bottom:.4rem}
.form-input,.form-textarea,.form-select{width:100%;padding:.6rem .85rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--bg);color:var(--text);font:inherit;font-size:.9rem;transition:border-color var(--t)}
.form-input:focus,.form-textarea:focus,.form-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(1,105,111,.08)}
.form-textarea{min-height:90px;resize:vertical}
.btn-cancel{padding:.5rem 1.1rem;border:1.5px solid var(--border);border-radius:var(--r-md);font:inherit;font-size:.85rem;font-weight:600;color:var(--muted);background:none;cursor:pointer;transition:background var(--t)}
.btn-cancel:hover{background:var(--bg);color:var(--text)}

/* EDITOR */
.editor-toolbar{background:var(--surface-2);border:1.5px solid var(--border);border-bottom:none;border-radius:var(--r-lg) var(--r-lg) 0 0;padding:.65rem .85rem;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.editor-btn{padding:.4rem .65rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);cursor:pointer;font-size:.8rem;font-weight:600;color:var(--muted);min-width:34px;height:34px;display:flex;align-items:center;justify-content:center;transition:background var(--t),border-color var(--t),color var(--t)}
.editor-btn:hover{background:var(--primary-light);border-color:var(--primary);color:var(--primary)}
.editor-sep{width:1px;background:var(--divider);height:24px;margin:0 .2rem}
#contentEditor{min-height:360px;max-height:480px;overflow-y:auto;padding:1.1rem;border:1.5px solid var(--border);border-radius:0 0 var(--r-lg) var(--r-lg);background:var(--surface);line-height:1.65;font-size:.9rem;color:var(--text)}
#contentEditor:empty::before{content:'Yahan lesson content type karein...';color:var(--faint)}
#contentEditor:focus{outline:none;border-color:var(--primary)}
#contentEditor h1{font-size:1.6em;font-weight:700;margin:.5em 0}
#contentEditor h2{font-size:1.3em;font-weight:700;margin:.5em 0}
#contentEditor h3{font-size:1.1em;font-weight:700;margin:.4em 0}
#contentEditor ul{list-style:disc;margin-left:1.5rem;margin-block:.5em}
#contentEditor ol{list-style:decimal;margin-left:1.5rem;margin-block:.5em}
#contentEditor li{margin-bottom:.3em}
#contentEditor a{color:var(--blue);text-decoration:underline}
#contentEditor code{background:var(--surface-2);color:var(--text);padding:.1rem .35rem;border-radius:3px;font-family:monospace;font-size:.88em}
#contentEditor pre{background:#1f2937;color:#f9fafb;padding:1rem;border-radius:var(--r-md);overflow-x:auto;margin:.5em 0}
#contentEditor pre code{background:transparent;color:#f9fafb;padding:0;border-radius:0;font-size:.88em;white-space:pre}
#contentEditor blockquote{border-left:3px solid var(--primary);padding-left:1rem;color:var(--muted);margin:.5em 0;font-style:italic}
#contentEditor img{max-width:100%;border-radius:var(--r-md);margin:.5rem 0}
#contentEditor video{max-width:100%;border-radius:var(--r-md);margin:.75rem 0}
#contentEditor iframe{max-width:100%;border-radius:var(--r-md);margin:.75rem 0;min-height:360px}

.media-panel{background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--r-md);padding:1rem;margin-bottom:.75rem;display:none}
.media-panel.open{display:block}
.media-panel h4{font-size:.82rem;font-weight:700;margin-bottom:.75rem}
.media-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}

/* QUIZ */
.question-item{background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:.85rem 1rem;margin-bottom:.65rem}
.q-header{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}
.q-badges{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.4rem}
.q-badge{font-size:.68rem;font-weight:700;padding:.18rem .55rem;border-radius:9999px}
.q-badge-type{background:var(--primary-light);color:var(--primary)}
.q-badge-pts{background:rgba(218,113,1,.1);color:var(--orange)}
.q-text{font-weight:600;font-size:.87rem;margin-bottom:.4rem}
.q-option{font-size:.8rem;padding:.2rem 0;color:var(--muted)}
.q-option.correct{color:var(--success);font-weight:600}
.quiz-empty{padding:2rem;text-align:center;color:var(--muted);font-size:.85rem}

.alert{padding:.75rem 1rem;border-radius:var(--r-md);margin-bottom:.75rem;font-size:.85rem;font-weight:500;display:none}
.alert.show{display:flex;align-items:center;gap:.5rem}
.alert-success{background:rgba(67,122,34,.1);color:var(--success);border:1px solid rgba(67,122,34,.2)}
.alert-error{background:rgba(161,44,123,.08);color:var(--error);border:1px solid rgba(161,44,123,.2)}

@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg viewBox="0 0 32 32" fill="none">
      <rect width="32" height="32" rx="7" fill="var(--primary)"/>
      <path d="M9 23L16 9L23 23" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M12 19h8" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
    <div class="logo-text">Internship<span>Adda</span></div>
  </div>
  <div class="sidebar-user">
    <div class="user-badge"><?= htmlspecialchars($_SESSION['user_role'] ?? 'manager') ?></div>
    <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Manager') ?></div>
  </div>
  <nav>
    <div class="nav-section">Overview</div>
    <a href="manager_dashboard.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <div class="nav-section">Content</div>
    <a href="courses.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Courses
    </a>
    <a href="internships.php" class="nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      Internships
    </a>
    <div class="nav-section">People</div>
    <a href="enrollments.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Enrollments
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="?logout=1" class="btn-sm btn-danger">Logout</a>
    <button data-theme-toggle class="btn-sm btn-ghost">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <div class="topbar-left">
      <div class="breadcrumb">
        <a href="internships.php">Internships</a> /
        <span>Builder</span>
      </div>
      <div class="page-title">
        🎓 <?= htmlspecialchars($internship['title']) ?>
      </div>
    </div>
    <div class="topbar-actions">
      <span class="restrict-badge">🔒 No Delete — Manager</span>
      <button onclick="history.back()" class="btn-back">← Back</button>
      <button onclick="openAddModuleModal()" class="btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
        Add Module
      </button>
    </div>
  </header>

  <main class="content">
    <div id="alertBox" class="alert" style="max-width:780px"></div>

    <?php if (empty($modules)): ?>
    <div class="empty-state" style="max-width:580px;margin:2rem auto">
      <div class="empty-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
      </div>
      <h3>Abhi koi module nahi hai</h3>
      <p>Pehla module add karke curriculum banana start karein</p>
      <button onclick="openAddModuleModal()" class="btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
        Add First Module
      </button>
    </div>
    <?php else: ?>
    <div style="max-width:880px">
      <?php foreach ($modules as $mi => $module): ?>
      <div class="module-card">
        <div class="module-header">
          <div class="module-num"><?= $mi + 1 ?></div>
          <div class="module-info">
            <div class="module-title"><?= htmlspecialchars($module['title']) ?></div>
            <div class="module-meta"><?= count($module['lessons']) ?> lessons</div>
          </div>
          <div class="module-actions">
            <button onclick="openAddLessonModal(<?= $module['id'] ?>)" class="btn-add-lesson">+ Add Lesson</button>
            <button onclick="editModule(<?= $module['id'] ?>, '<?= addslashes($module['title']) ?>')" class="btn-icon btn-icon-edit" title="Edit Module">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <!-- NO DELETE BUTTON FOR MANAGER -->
          </div>
        </div>

        <div class="lessons-wrap">
          <?php if (empty($module['lessons'])): ?>
          <div class="empty-lessons">
            Koi lesson nahi — upar "Add Lesson" click karein
          </div>
          <?php else: ?>
          <?php foreach ($module['lessons'] as $li => $lesson): ?>
          <div class="lesson-card">
            <div class="lesson-inner">
              <div class="lesson-num"><?= $li + 1 ?></div>
              <div class="lesson-info">
                <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                <div class="lesson-meta">
                  <span class="meta-chip"><?= count($lesson['content_blocks']) ?> blocks</span>
                  <?php if ($lesson['quiz_count'] > 0): ?>
                  <span class="quiz-chip">📝 <?= $lesson['quiz_count'] ?> quiz</span>
                  <?php endif; ?>
                  <?php if (!empty($lesson['duration_minutes'])): ?>
                  <span class="meta-chip">⏱ <?= (int)$lesson['duration_minutes'] ?> min</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="lesson-actions">
                <button onclick="openContentModal(<?= $lesson['id'] ?>, '<?= addslashes($lesson['title']) ?>')" class="btn-content">📄 Content</button>
                <button onclick="openQuizModal(<?= $lesson['id'] ?>, '<?= addslashes($lesson['title']) ?>')" class="btn-quiz">📝 Quiz</button>
                <button onclick="editLesson(<?= $lesson['id'] ?>, <?= $module['id'] ?>, '<?= addslashes($lesson['title']) ?>')" class="btn-icon btn-icon-edit" title="Edit Lesson">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <!-- NO DELETE LESSON FOR MANAGER -->
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════ -->

<!-- Add Module Modal -->
<div id="addModuleModal" class="modal-overlay">
  <div class="modal-box sm">
    <div class="modal-header">
      <h2>Add Module</h2>
      <button onclick="closeModal('addModuleModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="addModuleForm" onsubmit="handleAddModule(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Module Title *</label>
          <input type="text" name="title" required class="form-input" placeholder="e.g., Introduction to Web Dev">
        </div>
        <div class="form-group">
          <label class="form-label">Description (optional)</label>
          <textarea name="description" class="form-textarea" placeholder="Module ki brief description"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('addModuleModal')" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-primary">Add Module</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Module Modal -->
<div id="editModuleModal" class="modal-overlay">
  <div class="modal-box sm">
    <div class="modal-header">
      <h2>Edit Module</h2>
      <button onclick="closeModal('editModuleModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="editModuleForm" onsubmit="handleEditModule(event)">
      <div class="modal-body">
        <input type="hidden" name="module_id" id="editModuleId">
        <div class="form-group">
          <label class="form-label">Module Title *</label>
          <input type="text" name="title" id="editModuleTitle" required class="form-input">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('editModuleModal')" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Lesson Modal -->
<div id="addLessonModal" class="modal-overlay">
  <div class="modal-box sm">
    <div class="modal-header">
      <h2>Add Lesson</h2>
      <button onclick="closeModal('addLessonModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="addLessonForm" onsubmit="handleAddLesson(event)">
      <div class="modal-body">
        <input type="hidden" name="module_id" id="addLessonModuleId">
        <div class="form-group">
          <label class="form-label">Lesson Title *</label>
          <input type="text" name="title" required class="form-input" placeholder="e.g., Introduction to HTML">
        </div>
        <div class="form-group">
          <label class="form-label">Description (optional)</label>
          <textarea name="description" class="form-textarea" placeholder="Is lesson mein kya seekhenge"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Duration (minutes)</label>
          <input type="number" name="duration_minutes" min="0" class="form-input" placeholder="e.g., 30">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('addLessonModal')" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-primary">Add Lesson</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Lesson Modal -->
<div id="editLessonModal" class="modal-overlay">
  <div class="modal-box sm">
    <div class="modal-header">
      <h2>Edit Lesson</h2>
      <button onclick="closeModal('editLessonModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="editLessonForm" onsubmit="handleEditLesson(event)">
      <div class="modal-body">
        <input type="hidden" name="lesson_id" id="editLessonId">
        <div class="form-group">
          <label class="form-label">Lesson Title *</label>
          <input type="text" name="title" id="editLessonTitle" required class="form-input">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('editLessonModal')" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Content Editor Modal -->
<div id="contentModal" class="modal-overlay">
  <div class="modal-box xl">
    <div class="modal-header">
      <div>
        <h2>📄 Lesson Content Editor</h2>
        <p id="contentLessonTitle" style="font-size:.78rem;color:var(--muted);margin-top:.15rem"></p>
      </div>
      <button onclick="closeModal('contentModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">

      <!-- Image Panel -->
      <div id="imagePanel" class="media-panel">
        <h4>📸 Image Insert</h4>
        <div class="media-grid">
          <div>
            <label class="form-label">Device se Upload</label>
            <input type="file" id="localImageInput" accept="image/*" class="form-input">
            <div id="imgProgress" style="display:none;margin-top:.5rem">
              <div style="background:var(--divider);border-radius:9999px;height:6px">
                <div id="imgBar" style="background:var(--primary);height:6px;border-radius:9999px;width:0;transition:width .3s"></div>
              </div>
              <p style="font-size:.72rem;color:var(--muted);margin-top:.3rem">Uploading...</p>
            </div>
          </div>
          <div>
            <label class="form-label">Ya Image URL</label>
            <input type="url" id="imageUrlInput" class="form-input" placeholder="https://..." style="margin-bottom:.5rem">
            <button onclick="insertImageFromUrl()" class="btn-primary" style="font-size:.8rem;padding:.38rem .8rem">Insert</button>
          </div>
        </div>
        <button onclick="closeImagePanel()" style="margin-top:.75rem;font-size:.78rem;color:var(--muted)">✕ Close</button>
      </div>

      <!-- Video Panel -->
      <div id="videoPanel" class="media-panel">
        <h4>🎥 Video Insert</h4>
        <div class="media-grid">
          <div>
            <label class="form-label">Video Upload</label>
            <input type="file" id="localVideoInput" accept="video/*" class="form-input">
            <div id="vidProgress" style="display:none;margin-top:.5rem">
              <div style="background:var(--divider);border-radius:9999px;height:6px">
                <div id="vidBar" style="background:var(--purple);height:6px;border-radius:9999px;width:0;transition:width .3s"></div>
              </div>
              <p style="font-size:.72rem;color:var(--muted);margin-top:.3rem">Uploading... (max 1GB)</p>
            </div>
          </div>
          <div>
            <label class="form-label">YouTube / Video URL</label>
            <input type="url" id="videoUrlInput" class="form-input" placeholder="https://youtube.com/watch?v=..." style="margin-bottom:.5rem">
            <button onclick="insertVideoFromUrl()" class="btn-primary" style="font-size:.8rem;padding:.38rem .8rem">Insert</button>
          </div>
        </div>
        <button onclick="closeVideoPanel()" style="margin-top:.75rem;font-size:.78rem;color:var(--muted)">✕ Close</button>
      </div>

      <!-- Toolbar -->
      <div class="editor-toolbar">
        <button type="button" onclick="execCmd('bold')" class="editor-btn" title="Bold"><i class="fas fa-bold"></i></button>
        <button type="button" onclick="execCmd('italic')" class="editor-btn" title="Italic"><i class="fas fa-italic"></i></button>
        <button type="button" onclick="execCmd('underline')" class="editor-btn" title="Underline"><i class="fas fa-underline"></i></button>
        <div class="editor-sep"></div>
        <button type="button" onclick="execCmd('formatBlock','h1')" class="editor-btn">H1</button>
        <button type="button" onclick="execCmd('formatBlock','h2')" class="editor-btn">H2</button>
        <button type="button" onclick="execCmd('formatBlock','h3')" class="editor-btn">H3</button>
        <div class="editor-sep"></div>
        <button type="button" onclick="execCmd('insertUnorderedList')" class="editor-btn"><i class="fas fa-list-ul"></i></button>
        <button type="button" onclick="execCmd('insertOrderedList')" class="editor-btn"><i class="fas fa-list-ol"></i></button>
        <div class="editor-sep"></div>
        <button type="button" onclick="insertLink()" class="editor-btn" title="Link"><i class="fas fa-link"></i></button>
        <button type="button" onclick="toggleImagePanel()" class="editor-btn" title="Image"><i class="fas fa-image"></i></button>
        <button type="button" onclick="toggleVideoPanel()" class="editor-btn" title="Video"><i class="fas fa-video"></i></button>
        <div class="editor-sep"></div>
        <button type="button" onclick="execCmd('formatBlock','pre')" class="editor-btn" title="Code"><i class="fas fa-code"></i></button>
        <button type="button" onclick="execCmd('formatBlock','blockquote')" class="editor-btn" title="Quote"><i class="fas fa-quote-right"></i></button>
      </div>
      <div id="contentEditor" contenteditable="true"></div>
    </div>
    <div class="modal-footer">
      <button type="button" onclick="closeModal('contentModal')" class="btn-cancel">Cancel</button>
      <button onclick="saveContent()" class="btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
        Save Content
      </button>
    </div>
  </div>
</div>

<!-- Quiz Modal -->
<div id="quizModal" class="modal-overlay">
  <div class="modal-box lg">
    <div class="modal-header">
      <div>
        <h2>📝 Quiz Manager</h2>
        <p id="quizLessonTitle" style="font-size:.78rem;color:var(--muted);margin-top:.15rem"></p>
      </div>
      <button onclick="closeModal('quizModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
        <span style="font-size:.85rem;font-weight:700">Questions</span>
        <button onclick="openAddQuestionModal()" class="btn-primary" style="font-size:.8rem;padding:.38rem .9rem">+ Add Question</button>
      </div>
      <div id="questionsContainer"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('quizModal')" class="btn-cancel">Close</button>
    </div>
  </div>
</div>

<!-- Add Question Modal -->
<div id="addQuestionModal" class="modal-overlay">
  <div class="modal-box lg">
    <div class="modal-header">
      <h2>Add Quiz Question</h2>
      <button onclick="closeModal('addQuestionModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="addQuestionForm" onsubmit="handleAddQuestion(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Question *</label>
          <textarea name="question" required rows="3" class="form-textarea" placeholder="Question yahan likho"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Type *</label>
          <select name="type" id="questionType" class="form-select" onchange="toggleOptionsFields()">
            <option value="multiple_choice">Multiple Choice</option>
            <option value="true_false">True/False</option>
            <option value="short_answer">Short Answer</option>
          </select>
        </div>
        <div id="optionsFields" class="form-group">
          <label class="form-label">Options</label>
          <?php for ($oi=1; $oi<=4; $oi++): ?>
          <div style="display:flex;gap:.65rem;margin-bottom:.5rem;align-items:center">
            <input type="text" name="option_<?= $oi ?>" class="form-input" placeholder="Option <?= $oi ?>">
            <label style="display:flex;align-items:center;gap:.35rem;font-size:.8rem;white-space:nowrap">
              <input type="radio" name="correct_option" value="<?= $oi ?>"> Correct
            </label>
          </div>
          <?php endfor; ?>
        </div>
        <div id="answerField" class="form-group" style="display:none">
          <label class="form-label">Correct Answer *</label>
          <input type="text" name="correct_answer" class="form-input" placeholder="Answer">
        </div>
        <div class="form-group">
          <label class="form-label">Explanation (optional)</label>
          <textarea name="explanation" rows="2" class="form-textarea" placeholder="Correct answer kyun hai"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Points</label>
          <input type="number" name="points" value="10" min="1" class="form-input">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('addQuestionModal')" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-primary">Add Question</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Question Modal -->
<div id="editQuestionModal" class="modal-overlay">
  <div class="modal-box sm">
    <div class="modal-header">
      <h2>Edit Question</h2>
      <button onclick="closeModal('editQuestionModal')" class="modal-close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="editQuestionForm" onsubmit="handleEditQuestion(event)">
      <div class="modal-body">
        <input type="hidden" name="question_id" id="editQuestionId">
        <div class="form-group">
          <label class="form-label">Question *</label>
          <textarea name="question" id="editQuestionText" required rows="3" class="form-textarea"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Points</label>
          <input type="number" name="points" id="editQuestionPoints" min="1" class="form-input">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('editQuestionModal')" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const internshipId = <?= $internshipId ?>;
let currentLessonId = null;
let currentQuizLessonId = null;

// ─── MODAL ───────────────────────────────────────────────────────────
function openAddModuleModal(){ document.getElementById('addModuleModal').classList.add('active'); }
function openAddLessonModal(moduleId){
    document.getElementById('addLessonModuleId').value = moduleId;
    document.getElementById('addLessonModal').classList.add('active');
}
function editModule(id, title){
    document.getElementById('editModuleId').value = id;
    document.getElementById('editModuleTitle').value = title;
    document.getElementById('editModuleModal').classList.add('active');
}
function editLesson(id, moduleId, title){
    document.getElementById('editLessonId').value = id;
    document.getElementById('editLessonTitle').value = title;
    document.getElementById('editLessonModal').classList.add('active');
}
function closeModal(id){ document.getElementById(id).classList.remove('active'); }

// ─── ALERT ───────────────────────────────────────────────────────────
function showAlert(msg, type='success'){
    const b = document.getElementById('alertBox');
    b.className = 'alert show alert-' + type;
    b.textContent = (type==='success'?'✅ ':'❌ ') + msg;
    setTimeout(()=>{ b.className='alert'; }, 4000);
}

// ─── MODULE CRUD ─────────────────────────────────────────────────────
async function handleAddModule(e){
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch('/api/internship-modules.php?action=create', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ internship_id: internshipId, title: fd.get('title'), description: fd.get('description') })
        });
        const res = await r.json();
        if(res.success){ location.reload(); } else { showAlert(res.message||'Failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}
async function handleEditModule(e){
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch('/api/internship-modules.php?action=update', {
            method:'PUT', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ id: fd.get('module_id'), title: fd.get('title') })
        });
        const res = await r.json();
        if(res.success){ location.reload(); } else { showAlert(res.message||'Failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}

// ─── LESSON CRUD ─────────────────────────────────────────────────────
async function handleAddLesson(e){
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch('/api/internship-lessons.php?action=create', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ module_id: fd.get('module_id'), title: fd.get('title'), description: fd.get('description'), duration_minutes: fd.get('duration_minutes')||0 })
        });
        const res = await r.json();
        if(res.success){ location.reload(); } else { showAlert(res.message||'Failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}
async function handleEditLesson(e){
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch('/api/internship-lessons.php?action=update', {
            method:'PUT', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ id: fd.get('lesson_id'), title: fd.get('title') })
        });
        const res = await r.json();
        if(res.success){ location.reload(); } else { showAlert(res.message||'Failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}

// ─── CONTENT EDITOR ──────────────────────────────────────────────────
async function openContentModal(lessonId, lessonTitle){
    currentLessonId = lessonId;
    document.getElementById('contentLessonTitle').textContent = lessonTitle;
    try {
        const r = await fetch(`/api/internship-content.php?action=get&lesson_id=${lessonId}`);
        const res = await r.json();
        document.getElementById('contentEditor').innerHTML = (res.success && res.data && res.data.length > 0) ? (res.data[0].content||'') : '';
    } catch(err){ document.getElementById('contentEditor').innerHTML = ''; }
    document.getElementById('contentModal').classList.add('active');
}
function execCmd(cmd, val=null){ document.execCommand(cmd, false, val); document.getElementById('contentEditor').focus(); }
function insertLink(){ const u=prompt('URL enter karein:'); if(u) execCmd('createLink', u); }

function toggleImagePanel(){ const p=document.getElementById('imagePanel'); p.classList.toggle('open'); document.getElementById('videoPanel').classList.remove('open'); }
function closeImagePanel(){ document.getElementById('imagePanel').classList.remove('open'); }
function toggleVideoPanel(){ const p=document.getElementById('videoPanel'); p.classList.toggle('open'); document.getElementById('imagePanel').classList.remove('open'); }
function closeVideoPanel(){ document.getElementById('videoPanel').classList.remove('open'); }

document.getElementById('localImageInput')?.addEventListener('change', async(e)=>{
    const file = e.target.files[0]; if(!file) return;
    if(file.size > 1024*1024*1024){ alert('1GB se chhoti image chahiye'); return; }
    const fd = new FormData(); fd.append('file', file);
    document.getElementById('imgProgress').style.display='block';
    try {
        const r = await fetch('/api/upload-media.php', { method:'POST', body:fd });
        const res = await r.json();
        if(res.success){
            const url = res.data.url||res.data.file_path;
            document.getElementById('contentEditor').focus();
            document.execCommand('insertHTML', false, `<img src="${url}" alt="image" style="max-width:100%;border-radius:8px;margin:8px 0;">`);
            closeImagePanel(); showAlert('Image uploaded!');
        } else { showAlert(res.error||'Upload failed','error'); }
    } catch(err){ showAlert('Upload error','error'); }
    finally { document.getElementById('imgProgress').style.display='none'; e.target.value=''; }
});
function insertImageFromUrl(){
    const url=document.getElementById('imageUrlInput').value.trim();
    if(!url){ alert('URL enter karein'); return; }
    try{ new URL(url); } catch(e){ alert('Invalid URL'); return; }
    document.getElementById('contentEditor').focus();
    document.execCommand('insertHTML', false, `<img src="${url}" alt="image" style="max-width:100%;border-radius:8px;margin:8px 0;">`);
    closeImagePanel(); showAlert('Image inserted!');
}
document.getElementById('localVideoInput')?.addEventListener('change', async(e)=>{
    const file=e.target.files[0]; if(!file) return;
    if(file.size > 1024*1024*1024){ alert('1GB se chhota video chahiye'); return; }
    const fd=new FormData(); fd.append('file', file);
    document.getElementById('vidProgress').style.display='block';
    try {
        const r=await fetch('/api/upload-media.php', { method:'POST', body:fd });
        const res=await r.json();
        if(res.success){
            const url=res.data.url||res.data.file_path;
            document.getElementById('contentEditor').focus();
            document.execCommand('insertHTML', false, `<video controls style="max-width:100%;border-radius:12px;margin:12px 0;"><source src="${url}" type="video/mp4"></video>`);
            closeVideoPanel(); showAlert('Video uploaded!');
        } else { showAlert(res.error||'Upload failed','error'); }
    } catch(err){ showAlert('Upload error','error'); }
    finally { document.getElementById('vidProgress').style.display='none'; e.target.value=''; }
});
function insertVideoFromUrl(){
    const url=document.getElementById('videoUrlInput').value.trim();
    if(!url){ alert('URL enter karein'); return; }
    let embed='';
    try {
        if(url.includes('youtube.com')||url.includes('youtu.be')){
            let vid='';
            if(url.includes('v=')) vid=url.split('v=')[1].split('&')[0];
            else if(url.includes('youtu.be/')) vid=url.split('youtu.be/')[1].split('?')[0];
            else if(url.includes('embed/')) vid=url.split('embed/')[1].split('?')[0];
            if(!vid){ alert('Invalid YouTube URL'); return; }
            embed=`<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%;margin:16px 0;border-radius:12px;"><iframe src="https://www.youtube.com/embed/${vid}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;border-radius:12px;" allowfullscreen></iframe></div>`;
        } else if(url.includes('vimeo.com')){
            let vid=url.split('vimeo.com/')[1].split('/')[0].split('?')[0];
            embed=`<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%;margin:16px 0;border-radius:12px;"><iframe src="https://player.vimeo.com/video/${vid}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;border-radius:12px;" allowfullscreen></iframe></div>`;
        } else {
            new URL(url);
            embed=`<video controls style="max-width:100%;border-radius:12px;margin:12px 0;"><source src="${url}" type="video/mp4"></video>`;
        }
        document.getElementById('contentEditor').focus();
        document.execCommand('insertHTML', false, embed);
        closeVideoPanel(); showAlert('Video inserted!');
    } catch(e){ alert('Invalid URL'); }
}
async function saveContent(){
    const content=document.getElementById('contentEditor').innerHTML;
    if(!content.trim()||content==='<br>'){ alert('Content add karo pehle'); return; }
    try {
        const r=await fetch('/api/internship-content.php?action=save', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ lesson_id: currentLessonId, content, type:'text' })
        });
        const res=await r.json();
        if(res.success){ showAlert('Content saved!'); closeModal('contentModal'); location.reload(); }
        else { showAlert(res.message||'Save failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}

// ─── QUIZ ─────────────────────────────────────────────────────────────
async function openQuizModal(lessonId, lessonTitle){
    currentQuizLessonId=lessonId;
    document.getElementById('quizLessonTitle').textContent=lessonTitle;
    await loadQuestions(lessonId);
    document.getElementById('quizModal').classList.add('active');
}
async function loadQuestions(lessonId){
    try {
        const r=await fetch(`/api/internship-quiz.php?action=get&lesson_id=${lessonId}`);
        const res=await r.json();
        const c=document.getElementById('questionsContainer');
        if(res.success && res.data && res.data.length>0){
            c.innerHTML=res.data.map((q,i)=>`
                <div class="question-item">
                    <div class="q-header">
                        <div style="flex:1">
                            <div class="q-badges">
                                <span class="q-badge q-badge-type">Q${i+1} · ${q.type.replace('_',' ')}</span>
                                <span class="q-badge q-badge-pts">${q.points} pts</span>
                            </div>
                            <div class="q-text">${q.question}</div>
                            ${q.options ? JSON.parse(q.options).map((opt,oi)=>`<div class="q-option ${oi+1==q.correct_option?'correct':''}">${oi+1}. ${opt}${oi+1==q.correct_option?' ✓':''}</div>`).join('') : ''}
                        </div>
                        <div style="display:flex;gap:.3rem">
                            <button onclick="editQuestion(${q.id},'${q.question.replace(/'/g,"\\'")}',${q.points})" class="btn-icon btn-icon-edit">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button onclick="deleteQuestion(${q.id})" class="btn-icon" style="color:var(--error)" title="Delete question">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            c.innerHTML='<div class="quiz-empty">Abhi koi question nahi — upar + Add Question click karein</div>';
        }
    } catch(err){ console.error(err); }
}
function openAddQuestionModal(){ document.getElementById('addQuestionModal').classList.add('active'); }
function toggleOptionsFields(){
    const t=document.getElementById('questionType').value;
    document.getElementById('optionsFields').style.display=(t==='multiple_choice')?'block':'none';
    document.getElementById('answerField').style.display=(t==='short_answer')?'block':'none';
}
async function handleAddQuestion(e){
    e.preventDefault();
    const fd=new FormData(e.target);
    const type=fd.get('type');
    let data={ lesson_id:currentQuizLessonId, question:fd.get('question'), type, points:parseInt(fd.get('points')), explanation:fd.get('explanation') };
    if(type==='multiple_choice'){
        const opts=[fd.get('option_1'),fd.get('option_2'),fd.get('option_3'),fd.get('option_4')].filter(Boolean);
        data.options=JSON.stringify(opts);
        data.correct_option=parseInt(fd.get('correct_option'));
    } else if(type==='true_false'){
        data.options=JSON.stringify(['True','False']);
        data.correct_option=1;
    } else { data.correct_answer=fd.get('correct_answer'); }
    try {
        const r=await fetch('/api/internship-quiz.php?action=create', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify(data)
        });
        const res=await r.json();
        if(res.success){ closeModal('addQuestionModal'); loadQuestions(currentQuizLessonId); e.target.reset(); showAlert('Question added!'); }
        else { showAlert(res.message||'Failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}
function editQuestion(id, question, points){
    document.getElementById('editQuestionId').value=id;
    document.getElementById('editQuestionText').value=question;
    document.getElementById('editQuestionPoints').value=points;
    document.getElementById('editQuestionModal').classList.add('active');
}
async function handleEditQuestion(e){
    e.preventDefault();
    const fd=new FormData(e.target);
    try {
        const r=await fetch('/api/internship-quiz.php?action=update', {
            method:'PUT', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({ id:fd.get('question_id'), question:fd.get('question'), points:parseInt(fd.get('points')) })
        });
        const res=await r.json();
        if(res.success){ closeModal('editQuestionModal'); loadQuestions(currentQuizLessonId); showAlert('Question updated!'); }
        else { showAlert(res.message||'Failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}
async function deleteQuestion(id){
    if(!confirm('Is question ko delete karein?')) return;
    try {
        const r=await fetch('/api/internship-quiz.php?action=delete', {
            method:'DELETE', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({ id })
        });
        const res=await r.json();
        if(res.success){ loadQuestions(currentQuizLessonId); showAlert('Question deleted!'); }
        else { showAlert(res.message||'Failed','error'); }
    } catch(err){ showAlert('Network error','error'); }
}

// ─── THEME TOGGLE ─────────────────────────────────────────────────────
(function(){
    var t=document.querySelector('[data-theme-toggle]'),r=document.documentElement;
    var d=window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';
    r.setAttribute('data-theme',d);
    if(t) t.addEventListener('click',function(){ d=d==='dark'?'light':'dark'; r.setAttribute('data-theme',d); });
})();

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(overlay){
    overlay.addEventListener('click', function(e){ if(e.target===overlay) overlay.classList.remove('active'); });
});
</script>
</body>
</html>