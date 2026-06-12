<?php
// manager/internships.php — FINAL FIXED (exact DB columns matched)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/manager_auth.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: manager_login.php');
    exit;
}

$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
$user_id   = $_SESSION['user_id']   ?? $_SESSION['id']   ?? null;

if (!$user_id || !in_array($user_role, ['admin', 'manager', 'super_admin'])) {
    header('Location: manager_login.php');
    exit;
}

$is_manager = ($user_role === 'manager');

$_perms = $is_manager
    ? ['internships_view', 'internships_edit', 'enrollments_view']
    : ['courses_view', 'courses_edit', 'internships_view', 'internships_edit',
       'enrollments_view', 'offline_apps_view', 'offline_apps_edit'];

if (!in_array('internships_view', $_perms)) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;padding:2rem;color:red">❌ Access Denied</p>');
}

if (!function_exists('can')) {
    function can(string $p): bool {
        global $_perms;
        return in_array($p, $_perms);
    }
}

// DB Connect
global $db;
if (!isset($db) || !$db) {
    $dbConfig = require __DIR__ . '/db.php';
    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $dbConfig['host'],
            $dbConfig['port']    ?? 3306,
            $dbConfig['database'],
            $dbConfig['charset'] ?? 'utf8mb4'
        );
        $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die('<p style="font-family:sans-serif;padding:2rem;color:red">❌ DB Error: '
            . htmlspecialchars($e->getMessage()) . '</p>');
    }
}

// Search / Filter
$search   = trim($_GET['q']        ?? '');
$category = trim($_GET['category'] ?? '');
$mode     = trim($_GET['mode']     ?? '');
$active   = $_GET['active'] ?? '';

// ✅ EXACT COLUMN NAMES FROM DB:
// duration_weeks, cover_image, skill_level, is_active, discount_price, created_at
$sql = "SELECT
            `id`,
            `title`,
            `category`,
            `mode`,
            `duration_weeks`,
            `price`,
            `discount_price`,
            `skill_level`,
            `is_active`,
            `cover_image`,
            `created_at`
        FROM `internships`
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql     .= " AND (`title` LIKE ? OR `category` LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($category !== '') { $sql .= " AND `category` = ?";  $params[] = $category; }
if ($mode !== '')     { $sql .= " AND `mode` = ?";      $params[] = $mode; }
if ($active !== '')   { $sql .= " AND `is_active` = ?"; $params[] = (int)$active; }
$sql .= " ORDER BY `created_at` DESC LIMIT 100";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $internships = $stmt->fetchAll();
} catch (PDOException $e) {
    die('<p style="font-family:sans-serif;padding:2rem;color:red">❌ Query Error: '
        . htmlspecialchars($e->getMessage()) . '</p>');
}

// Distinct categories
$categories = $db->query(
    "SELECT DISTINCT `category` FROM `internships`
     WHERE `category` IS NOT NULL AND `category` != ''
     ORDER BY `category`"
)->fetchAll(PDO::FETCH_COLUMN);

// Enrollment counts — DB mein table hai: internship_enrollments (underscore wala)
$enrollCounts = [];
try {
    foreach ($db->query(
        "SELECT `internship_id`, COUNT(*) as cnt FROM `internship_enrollments` GROUP BY `internship_id`"
    )->fetchAll() as $row) {
        $enrollCounts[$row['internship_id']] = $row['cnt'];
    }
} catch (PDOException $e) {
    // Table name alag ho to silently ignore
    $enrollCounts = [];
}

// Stats
$totalAll    = (int)$db->query("SELECT COUNT(*) FROM `internships`")->fetchColumn();
$totalActive = (int)$db->query("SELECT COUNT(*) FROM `internships` WHERE `is_active`=1")->fetchColumn();
$totalEnroll = array_sum($enrollCounts);

function skillBadgeClass(string $level): string {
    $l = strtolower(trim($level));
    if ($l === 'intermediate') return 'intermediate';
    if ($l === 'advanced')     return 'advanced';
    return 'beginner';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Internships — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{
  --bg:#f7f6f2;--surface:#fff;--surface-2:#fbfbf9;
  --border:rgba(40,37,29,.12);--divider:#dcd9d5;
  --text:#28251d;--muted:#7a7974;--faint:#bab9b4;
  --primary:#01696f;--primary-h:#0c4e54;
  --success:#437a22;--warning:#964219;--error:#a12c7b;--orange:#da7101;
  --r-sm:.375rem;--r-md:.5rem;--r-lg:.75rem;
  --shadow-sm:0 1px 2px rgba(0,0,0,.06);
  --t:180ms cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#171614;--surface:#1c1b19;--surface-2:#201f1d;
  --border:rgba(255,255,255,.08);--divider:#262523;
  --text:#cdccca;--muted:#797876;--faint:#5a5957;
  --primary:#4f98a3;--primary-h:#227f8b;
  --success:#6daa45;--warning:#bb653b;--error:#d163a7;--orange:#fdab43;
  --shadow-sm:0 1px 2px rgba(0,0,0,.2);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh;display:flex}
a{color:inherit;text-decoration:none}button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}
img{max-width:100%;height:auto;display:block}
.sidebar{width:240px;min-height:100dvh;background:var(--surface);border-right:1px solid var(--divider);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100}
.sidebar-logo{padding:1.1rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;gap:.75rem}
.sidebar-logo svg{width:30px;height:30px;flex-shrink:0}
.logo-text{font-weight:700;font-size:.92rem}.logo-text span{color:var(--primary)}
.sidebar-user{padding:.75rem 1.25rem;border-bottom:1px solid var(--divider)}
.user-badge{font-size:.7rem;font-weight:700;background:rgba(1,105,111,.12);color:var(--primary);padding:.18rem .55rem;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em;display:inline-block;margin-bottom:.3rem}
.user-name{font-weight:600;font-size:.88rem}
nav{flex:1;padding:.5rem 0;overflow-y:auto}
.nav-section{padding:.45rem 1.25rem .2rem;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint)}
.nav-item{display:flex;align-items:center;gap:.65rem;padding:.5rem 1.25rem;font-size:.85rem;color:var(--muted);transition:color var(--t),background var(--t);position:relative}
.nav-item:hover{background:var(--bg);color:var(--text)}
.nav-item.active{background:rgba(1,105,111,.1);color:var(--primary);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--primary);border-radius:0 4px 4px 0}
.nav-item svg{width:15px;height:15px;flex-shrink:0;opacity:.7}
.nav-item.active svg,.nav-item:hover svg{opacity:1}
.sidebar-footer{padding:.9rem 1.25rem;border-top:1px solid var(--divider);display:flex;gap:.5rem;align-items:center}
.btn-sm{padding:.4rem .85rem;font-size:.78rem;border-radius:var(--r-md);font-weight:500;cursor:pointer;transition:background var(--t),color var(--t)}
.btn-ghost{border:1px solid var(--border);color:var(--muted);background:none}.btn-ghost:hover{background:var(--bg)}
.btn-danger{background:rgba(161,44,123,.1);color:var(--error);border:none}.btn-danger:hover{background:rgba(161,44,123,.18)}
.main{margin-left:240px;flex:1;display:flex;flex-direction:column}
.topbar{height:52px;background:var(--surface);border-bottom:1px solid var(--divider);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;position:sticky;top:0;z-index:50}
.breadcrumb{font-size:.78rem;color:var(--muted);flex:1}.breadcrumb span{color:var(--text);font-weight:600}
.content{padding:1.25rem 1.5rem;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
.page-header h1{font-size:1.2rem;font-weight:700}
.page-header .subtitle{font-size:.8rem;color:var(--muted);margin-top:.2rem}
.restrict-notice{display:inline-flex;align-items:center;gap:.4rem;background:rgba(161,44,123,.07);border:1px solid rgba(161,44,123,.2);color:var(--error);padding:.4rem .9rem;border-radius:9999px;font-size:.75rem;font-weight:600}
.stats-row{display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap}
.stat-chip{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:.6rem 1rem;font-size:.8rem;display:flex;align-items:center;gap:.5rem;box-shadow:var(--shadow-sm)}
.stat-chip strong{font-size:1.1rem;font-weight:700}
.stat-chip.primary strong{color:var(--primary)}.stat-chip.success strong{color:var(--success)}.stat-chip.orange strong{color:var(--orange)}
.filter-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:.9rem 1.1rem;margin-bottom:1.25rem;display:flex;gap:.65rem;flex-wrap:wrap;align-items:flex-end;box-shadow:var(--shadow-sm)}
.filter-group{display:flex;flex-direction:column;gap:.3rem}
.filter-group label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.filter-group input,.filter-group select{padding:.48rem .8rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--bg);color:var(--text);font:inherit;font-size:.85rem;transition:border-color var(--t);min-width:140px}
.filter-group input:focus,.filter-group select:focus{outline:none;border-color:var(--primary)}
.filter-group input[type=text]{min-width:200px}
.btn-filter{padding:.5rem 1.1rem;background:var(--primary);color:#fff;border:none;border-radius:var(--r-md);font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;align-self:flex-end;transition:background var(--t)}
.btn-filter:hover{background:var(--primary-h)}
.btn-reset{padding:.5rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);font:inherit;font-size:.83rem;color:var(--muted);background:none;cursor:pointer;align-self:flex-end}
.btn-reset:hover{background:var(--bg)}
.table-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:860px}
th{padding:.6rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--divider);white-space:nowrap;background:var(--surface-2)}
td{padding:.75rem 1rem;font-size:.845rem;border-bottom:1px solid var(--divider);vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr:hover td{background:var(--bg)}
.cover-thumb{width:48px;height:36px;object-fit:cover;border-radius:var(--r-sm);background:var(--bg);flex-shrink:0}
.cover-ph{width:48px;height:36px;border-radius:var(--r-sm);background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--faint);flex-shrink:0}
.title-cell{display:flex;align-items:center;gap:.75rem}
.title-text{font-weight:600;font-size:.87rem;line-height:1.3}
.title-meta{font-size:.72rem;color:var(--muted);margin-top:.15rem}
.badge{display:inline-flex;align-items:center;padding:.2rem .6rem;border-radius:9999px;font-size:.7rem;font-weight:700;white-space:nowrap}
.badge-active{background:rgba(67,122,34,.12);color:var(--success)}
.badge-inactive{background:rgba(122,121,116,.12);color:var(--muted)}
.badge-remote,.badge-onsite,.badge-hybrid{background:rgba(1,105,111,.1);color:var(--primary)}
.badge-onsite{background:rgba(218,113,1,.12);color:var(--orange)}
.badge-hybrid{background:rgba(0,100,148,.1);color:#006494}
.badge-beginner{background:rgba(67,122,34,.1);color:var(--success)}
.badge-intermediate{background:rgba(150,66,25,.1);color:var(--warning)}
.badge-advanced{background:rgba(161,44,123,.1);color:var(--error)}
.price-cell{white-space:nowrap}
.price-main{font-weight:700}.price-strike{font-size:.72rem;color:var(--muted);text-decoration:line-through;display:block}
.price-free{color:var(--success);font-weight:700}
.actions{display:flex;gap:.5rem;align-items:center}
.btn-edit{display:inline-flex;align-items:center;gap:.3rem;padding:.35rem .8rem;background:rgba(1,105,111,.1);color:var(--primary);border:1px solid rgba(1,105,111,.2);border-radius:var(--r-md);font-size:.78rem;font-weight:600;transition:background var(--t)}
.btn-edit:hover{background:rgba(1,105,111,.18)}
.no-del{font-size:.7rem;color:var(--faint);font-style:italic}
.empty-state{padding:3rem 1.5rem;text-align:center;color:var(--muted)}
.empty-state svg{width:40px;height:40px;margin:0 auto .75rem;opacity:.35}
.empty-state h3{font-size:1rem;font-weight:600;color:var(--text);margin-bottom:.3rem}
.table-footer{padding:.7rem 1rem;border-top:1px solid var(--divider);font-size:.78rem;color:var(--muted);display:flex;justify-content:space-between}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}}
</style>
</head>
<body>

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
    <a href="manager_dashboard.php" class="nav-item ">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>

    <div class="nav-section">Content</div>
    <?php if(can('courses_view')): ?>
    <a href="courses.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Courses
    </a>
    <?php endif; ?>
    <?php if(can('internships_view')): ?>
    <a href="internships.php" class="nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      Internships
    </a>
    <?php endif; ?>

    

    <div class="nav-section">Logs</div>
    <a href="activity_log.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Activity Log
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="?logout=1" class="btn-sm btn-danger">Logout</a>
    <button data-theme-toggle class="btn-sm btn-ghost" aria-label="Toggle theme">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="breadcrumb">Manager Panel / <span>Internships</span></div>
    <span style="font-size:.78rem;color:var(--muted)"><?= date('D, d M Y') ?></span>
  </header>

  <main class="content">
    <div class="page-header">
      <div>
        <h1>Internships Management</h1>
        <p class="subtitle">Sabhi internship programs view aur edit karein</p>
      </div>
      <div class="restrict-notice">🔒 View + Edit only &nbsp;·&nbsp; Delete nahi</div>
    </div>

    <div class="stats-row">
      <div class="stat-chip primary"><strong><?= number_format($totalAll) ?></strong><span>Total</span></div>
      <div class="stat-chip success"><strong><?= number_format($totalActive) ?></strong><span>Active</span></div>
      <div class="stat-chip orange"><strong><?= number_format($totalEnroll) ?></strong><span>Enrollments</span></div>
      <div class="stat-chip"><strong><?= number_format(count($internships)) ?></strong><span>Showing</span></div>
    </div>

    <form method="GET" class="filter-bar" id="filterForm">
      <div class="filter-group">
        <label>Search</label>
        <input type="text" name="q" placeholder="Title ya category..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="filter-group">
        <label>Category</label>
        <select name="category">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>" <?= ($category===$cat)?'selected':'' ?>>
            <?= htmlspecialchars($cat) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label>Mode</label>
        <select name="mode">
          <option value="">All Modes</option>
          <option value="Remote"  <?= ($mode==='Remote') ?'selected':'' ?>>Remote</option>
          <option value="Onsite"  <?= ($mode==='Onsite') ?'selected':'' ?>>Onsite</option>
          <option value="Hybrid"  <?= ($mode==='Hybrid') ?'selected':'' ?>>Hybrid</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select name="active">
          <option value="">All</option>
          <option value="1" <?= ($active==='1')?'selected':'' ?>>Active</option>
          <option value="0" <?= ($active==='0')?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <button type="submit" class="btn-filter">Search</button>
      <a href="internships.php"><button type="button" class="btn-reset">Reset</button></a>
    </form>

    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th><th>Internship</th><th>Category</th><th>Mode</th>
              <th>Duration</th><th>Price</th><th>Level</th><th>Status</th>
              <th>Enrolled</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($internships)): ?>
            <tr><td colspan="10">
              <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <rect x="2" y="7" width="20" height="14" rx="2"/>
                  <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                </svg>
                <h3>Koi internship nahi mili</h3>
                <p>Filter change karke dobara try karein</p>
              </div>
            </td></tr>
          <?php else: ?>
            <?php foreach ($internships as $i):
              $price    = (float)($i['price']          ?? 0);
              $discount = (float)($i['discount_price'] ?? 0);
              $hasFree  = ($price == 0);
              $hasDisc  = ($discount > 0 && $discount < $price);
              $showP    = $hasDisc ? $discount : $price;
              $imgSrc   = !empty($i['cover_image']) ? '../' . ltrim($i['cover_image'], '/') : '';
              $modeVal  = strtolower($i['mode'] ?? 'remote');
              $eCount   = $enrollCounts[$i['id']] ?? 0;
              $created  = !empty($i['created_at']) ? date('d M Y', strtotime($i['created_at'])) : '—';
            ?>
            <tr>
              <td style="color:var(--faint);font-size:.75rem"><?= (int)$i['id'] ?></td>
              <td>
                <div class="title-cell">
                  <?php if ($imgSrc): ?>
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" class="cover-thumb" loading="lazy" width="48" height="36">
                  <?php else: ?>
                    <div class="cover-ph">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                      </svg>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div class="title-text"><?= htmlspecialchars($i['title']) ?></div>
                    <div class="title-meta"><?= $created ?></div>
                  </div>
                </div>
              </td>
              <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($i['category'] ?? '—') ?></td>
              <td><span class="badge badge-<?= $modeVal ?>"><?= htmlspecialchars($i['mode'] ?? 'Remote') ?></span></td>
              <td style="font-size:.83rem;white-space:nowrap"><?= (int)($i['duration_weeks'] ?? 0) ?> wks</td>
              <td class="price-cell">
                <?php if ($hasFree): ?>
                  <span class="price-free">Free</span>
                <?php elseif ($hasDisc): ?>
                  <span class="price-main">₹<?= number_format($showP, 0) ?></span>
                  <span class="price-strike">₹<?= number_format($price, 0) ?></span>
                <?php else: ?>
                  <span class="price-main">₹<?= number_format($price, 0) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= skillBadgeClass($i['skill_level'] ?? '') ?>"><?= ucfirst(htmlspecialchars($i['skill_level'] ?? 'beginner')) ?></span></td>
              <td><span class="badge <?= $i['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $i['is_active'] ? '● Active' : '○ Inactive' ?></span></td>
              <td style="font-size:.83rem"><?= number_format($eCount) ?></td>
              <td>
                <div class="actions">
                  <?php if (can('internships_edit')): ?>
                  <a href="internship_edit.php?id=<?= (int)$i['id'] ?>" class="btn-edit">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit
                  </a>
                  
<a href="internship_builder.php?id=<?= (int)$i['id'] ?>" 
   class="btn-edit" 
   style="background:rgba(122,57,187,.1);color:var(--purple);border-color:rgba(122,57,187,.2)">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
    </svg>
    Builder
</a>
                  <?php else: ?>
                    <span style="font-size:.78rem;color:var(--muted)">View only</span>
                  <?php endif; ?>
                  <span class="no-del">🔒 No delete</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="table-footer">
        <span><?= number_format(count($internships)) ?> result(s)</span>
        <span>Delete restricted — Manager role</span>
      </div>
    </div>
  </main>
</div>

<script>
(function(){
  var t=document.querySelector('[data-theme-toggle]'),r=document.documentElement;
  var d=window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';
  r.setAttribute('data-theme',d);
  if(t)t.addEventListener('click',function(){d=d==='dark'?'light':'dark';r.setAttribute('data-theme',d);});
})();
document.querySelectorAll('#filterForm select').forEach(function(el){
  el.addEventListener('change',function(){document.getElementById('filterForm').submit();});
});
</script>
</body>
</html>