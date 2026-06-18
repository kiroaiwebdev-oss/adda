<?php
require_once __DIR__ . '/manager_auth.php';

// Logout handler — TOP pe
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: manager_login.php');
    exit;
}

requireManagerAccess();
$db   = getDB();
$name = $_SESSION['user_name'];
$role = $_SESSION['user_role'];

// Stats — try-catch mein taaki crash na ho
try { $totalCourses = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(); }
catch(Exception $e) { $totalCourses = 0; }

try { $totalInternships = $db->query("SELECT COUNT(*) FROM internships")->fetchColumn(); }
catch(Exception $e) { $totalInternships = 0; }

try { $totalEnrollments = $db->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(); }
catch(Exception $e) { $totalEnrollments = 0; }

try { $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='learner'")->fetchColumn(); }
catch(Exception $e) { $totalUsers = 0; }

// Offline apps — multiple table names try karo
$pendingApps  = 0;
$offlineTable = '';
foreach (['offline_internship_applications','offlineinternshipapplications','offline_applications','internship_applications'] as $tbl) {
    try {
        $pendingApps  = $db->query("SELECT COUNT(*) FROM `$tbl` WHERE status='pending'")->fetchColumn();
        $offlineTable = $tbl;
        break;
    } catch(Exception $e) { continue; }
}

// Recent activity
$activities = [];
try {
    $stmt = $db->prepare("SELECT mal.*, u.name as manager_name FROM manager_activity_log mal JOIN users u ON u.id = mal.manager_id WHERE mal.manager_id = ? ORDER BY mal.created_at DESC LIMIT 8");
    $stmt->execute([$_SESSION['user_id']]);
    $activities = $stmt->fetchAll();
} catch(Exception $e) { $activities = []; }

// Recent courses
$recentCourses = [];
if (can('courses_view')) {
    try {
        $recentCourses = $db->query("SELECT id, title, status, price FROM courses ORDER BY created_at DESC LIMIT 6")->fetchAll();
    } catch(Exception $e) { $recentCourses = []; }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manager Dashboard — InternshipAdda</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{
  --bg:#f9fafb;--surface:#fff;--surface-2:#f3f4f6;
  --border:#e5e7eb;--divider:#e5e7eb;
  --text:#111827;--muted:#6b7280;--faint:#9ca3af;
  --primary:#16a34a;--primary-h:#15803d;--primary-hl:#dcfce7;
  --success:#437a22;--warning:#964219;--error:#a12c7b;--orange:#da7101;
  --r-sm:.375rem;--r-md:.5rem;--r-lg:.75rem;--r-xl:1rem;
  --shadow-sm:0 1px 2px rgba(0,0,0,0.05);
  --shadow-md:0 4px 12px rgba(0,0,0,0.08);
  --t:180ms cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#171614;--surface:#1c1b19;--surface-2:#201f1d;
  --border:rgba(255,255,255,0.08);--divider:#262523;
  --text:#cdccca;--muted:#797876;--faint:#5a5957;
  --primary:#4ade80;--primary-h:#22c55e;--primary-hl:#14532d;
  --success:#6daa45;--warning:#bb653b;--error:#d163a7;--orange:#fdab43;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh;display:flex}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}

/* SIDEBAR */
.sidebar{width:240px;min-height:100dvh;background:var(--surface);border-right:1px solid var(--divider);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100}
.sidebar-logo{padding:1.25rem 1.25rem 1rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;gap:.75rem}
.sidebar-logo svg{width:32px;height:32px;flex-shrink:0}
.logo-text{font-weight:700;font-size:.95rem}.logo-text span{color:var(--primary)}
.sidebar-user{padding:.9rem 1.25rem;border-bottom:1px solid var(--divider)}
.user-badge{font-size:.72rem;font-weight:600;background:rgba(22,163,74,0.12);color:var(--primary);padding:.2rem .6rem;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em;display:inline-block;margin-bottom:.35rem}
.user-name{font-weight:600;font-size:.9rem}
.user-email-sm{font-size:.75rem;color:var(--muted)}
nav{flex:1;padding:.75rem 0;overflow-y:auto}
.nav-section{padding:.5rem 1.25rem .25rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint)}
.nav-item{display:flex;align-items:center;gap:.7rem;padding:.55rem 1.25rem;font-size:.875rem;color:var(--muted);transition:color var(--t),background var(--t);position:relative}
.nav-item:hover{background:var(--bg);color:var(--text)}
.nav-item.active{background:rgba(22,163,74,0.1);color:var(--primary);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--primary);border-radius:0 4px 4px 0}
.nav-item svg{width:16px;height:16px;flex-shrink:0;opacity:.7}
.nav-item.active svg,.nav-item:hover svg{opacity:1}
.nav-badge{margin-left:auto;font-size:.7rem;background:var(--orange);color:#fff;padding:.1rem .45rem;border-radius:9999px;font-weight:700}
.sidebar-footer{padding:1rem 1.25rem;border-top:1px solid var(--divider);display:flex;gap:.5rem;align-items:center}
.btn-sm{padding:.45rem .9rem;font-size:.8rem;border-radius:var(--r-md);font-weight:500;transition:background var(--t),color var(--t);display:inline-flex;align-items:center;gap:.3rem}
.btn-ghost{border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{background:var(--bg);color:var(--text)}
.btn-danger{background:rgba(161,44,123,0.1);color:var(--error)}.btn-danger:hover{background:rgba(161,44,123,0.18)}

/* MAIN */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100dvh}
.topbar{height:56px;background:var(--surface);border-bottom:1px solid var(--divider);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;position:sticky;top:0;z-index:50}
.breadcrumb{font-size:.78rem;color:var(--muted);flex:1}
.breadcrumb span{color:var(--text);font-weight:500}
.content{padding:1.5rem;flex:1}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem}
.kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:1.25rem;box-shadow:var(--shadow-sm)}
.kpi-label{font-size:.78rem;color:var(--muted);font-weight:500;margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem}
.kpi-label svg{width:14px;height:14px}
.kpi-value{font-size:1.75rem;font-weight:700;line-height:1;font-variant-numeric:tabular-nums}
.kpi-sub{font-size:.75rem;color:var(--muted);margin-top:.3rem}
.kpi-card.accent{background:var(--primary);border-color:var(--primary)}
.kpi-card.accent .kpi-label,.kpi-card.accent .kpi-value,.kpi-card.accent .kpi-sub{color:#fff}

/* GRID */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:900px){.grid-2{grid-template-columns:1fr}}

/* SECTION CARD */
.section-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm)}
.section-head{padding:.9rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between}
.section-head h2{font-size:.9rem;font-weight:700}
.section-head a{font-size:.8rem;color:var(--primary);font-weight:500}
.section-head a:hover{text-decoration:underline}

/* TABLE */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{padding:.65rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--divider);white-space:nowrap}
td{padding:.75rem 1rem;font-size:.85rem;border-bottom:1px solid var(--divider)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--bg)}
.status-badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600}
.status-published{background:rgba(67,122,34,0.12);color:var(--success)}
.status-draft{background:rgba(107,114,128,0.15);color:var(--muted)}
.status-pending{background:rgba(218,113,1,0.15);color:var(--orange)}
.status-active{background:rgba(67,122,34,0.12);color:var(--success)}

/* ACTIVITY */
.activity-list{list-style:none}
.activity-item{display:flex;gap:.85rem;padding:.75rem 1.25rem;border-bottom:1px solid var(--divider);align-items:flex-start}
.activity-item:last-child{border-bottom:none}
.activity-dot{width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:.35rem}
.activity-text{font-size:.83rem;line-height:1.4}
.activity-meta{font-size:.73rem;color:var(--muted);margin-top:.15rem}

/* NOTICES */
.perm-notice{background:rgba(22,163,74,0.07);border:1px solid rgba(22,163,74,0.2);border-radius:var(--r-md);padding:.75rem 1rem;font-size:.82rem;color:var(--primary);margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem}
.warn-notice{background:rgba(218,113,1,0.08);border:1px solid oklch(from var(--orange) l c h/0.25);border-radius:var(--r-md);padding:.65rem 1rem;font-size:.8rem;color:var(--orange);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}

/* MOBILE */
.mobile-menu-btn{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--r-md);color:var(--muted);flex-shrink:0}
.mobile-menu-btn:hover{background:var(--bg)}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);transition:transform .3s ease}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .kpi-grid{grid-template-columns:1fr 1fr}
  .mobile-menu-btn{display:flex}
}
</style>
</head>
<body>

<?php $activeNav = "dashboard"; include __DIR__ . "/_sidebar.php"; ?>

<div class="main">
  <header class="topbar">
    <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="breadcrumb">Manager Panel / <span>Dashboard</span></div>
    <span style="font-size:.78rem;color:var(--muted)"><?= date('D, d M Y') ?></span>
  </header>

  <main class="content">
    <div class="perm-notice">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Manager Mode — Content view &amp; edit allowed. Delete operations restricted hain.
    </div>

    <?php if($offlineTable === ''): ?>
    <div class="warn-notice">
      ⚠️ Offline applications table nahi mili — woh section disabled hai.
    </div>
    <?php endif; ?>

    <!-- KPI ROW -->
    <div class="kpi-grid">
      <?php if(can('courses_view')): ?>
      <div class="kpi-card accent">
        <div class="kpi-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          Total Courses
        </div>
        <div class="kpi-value"><?= number_format((int)$totalCourses) ?></div>
        <div class="kpi-sub">Platform pe available</div>
      </div>
      <?php endif; ?>

      <?php if(can('internships_view')): ?>
      <div class="kpi-card">
        <div class="kpi-label">Internships</div>
        <div class="kpi-value"><?= number_format((int)$totalInternships) ?></div>
        <div class="kpi-sub">Active programs</div>
      </div>
      <?php endif; ?>

      <?php if(can('enrollments_view')): ?>
      <div class="kpi-card">
        <div class="kpi-label">Enrollments</div>
        <div class="kpi-value"><?= number_format((int)$totalEnrollments) ?></div>
        <div class="kpi-sub">Total</div>
      </div>
      <?php endif; ?>

      <?php if(can('users_view')): ?>
      <div class="kpi-card">
        <div class="kpi-label">Learners</div>
        <div class="kpi-value"><?= number_format((int)$totalUsers) ?></div>
        <div class="kpi-sub">Registered users</div>
      </div>
      <?php endif; ?>

      <?php if(can('offline_apps_view') && $pendingApps > 0): ?>
      <div class="kpi-card" style="border-color:oklch(from var(--orange) l c h/0.4)">
        <div class="kpi-label" style="color:var(--orange)">Pending Applications</div>
        <div class="kpi-value" style="color:var(--orange)"><?= (int)$pendingApps ?></div>
        <div class="kpi-sub">Offline — action needed</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- BOTTOM GRID -->
    <div class="grid-2">

      <?php if(can('courses_view')): ?>
      <div class="section-card">
        <div class="section-head">
          <h2>Recent Courses</h2>
          <a href="courses.php">View All →</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Title</th><th>Status</th><th>Price</th><th></th></tr></thead>
            <tbody>
              <?php if(empty($recentCourses)): ?>
              <tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--muted)">Koi course nahi mila</td></tr>
              <?php else: ?>
              <?php foreach($recentCourses as $c): ?>
              <tr>
                <td><?= htmlspecialchars(mb_substr($c['title'],0,28)) ?><?= mb_strlen($c['title'])>28?'…':'' ?></td>
                <td><span class="status-badge status-<?= htmlspecialchars($c['status']) ?>"><?= ucfirst(htmlspecialchars($c['status'])) ?></span></td>
                <td>₹<?= number_format((float)$c['price'],0) ?></td>
                <td><?php if(can('courses_edit')): ?><a href="course_edit.php?id=<?= (int)$c['id'] ?>" style="font-size:.78rem;color:var(--primary);font-weight:500">Edit</a><?php endif; ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <div class="section-card">
        <div class="section-head">
          <h2>My Recent Activity</h2>
          <a href="activity_log.php">Full Log →</a>
        </div>
        <?php if(empty($activities)): ?>
          <p style="padding:2rem;text-align:center;color:var(--muted);font-size:.85rem">Abhi koi activity nahi hai</p>
        <?php else: ?>
        <ul class="activity-list">
          <?php foreach($activities as $act): ?>
          <li class="activity-item">
            <div class="activity-dot"></div>
            <div>
              <div class="activity-text"><?= htmlspecialchars($act['action']) ?></div>
              <div class="activity-meta">
                <?= htmlspecialchars($act['entity_type']) ?>
                <?= !empty($act['entity_id']) ? '#'.(int)$act['entity_id'] : '' ?>
                &middot;
                <?= date('d M, H:i', strtotime($act['created_at'])) ?>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<script>
(function(){
  const t = document.querySelector('[data-theme-toggle]');
  const r = document.documentElement;
  let d = 'light';
  r.setAttribute('data-theme', d);
  if(t) t.addEventListener('click', () => {
    d = d === 'dark' ? 'light' : 'dark';
    r.setAttribute('data-theme', d);
  });
})();

const menuBtn = document.getElementById('menuBtn');
const sidebar = document.getElementById('sidebar');
if(menuBtn && sidebar){
  menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if(!sidebar.contains(e.target) && !menuBtn.contains(e.target))
      sidebar.classList.remove('open');
  });
}
</script>
</body>
</html>