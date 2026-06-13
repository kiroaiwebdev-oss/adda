<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('courses_view');
$db = getDB();

// Search / filter
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT c.id, c.title, c.status, c.price, c.created_at, u.name as creator
        FROM courses c LEFT JOIN users u ON u.id = c.created_by WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND c.title LIKE ?"; $params[] = "%$search%"; }
if ($status)  { $sql .= " AND c.status = ?"; $params[] = $status; }
$sql .= " ORDER BY c.created_at DESC LIMIT 60";
$stmt = $db->prepare($sql); $stmt->execute($params);
$courses = $stmt->fetchAll();

// Get current user info for sidebar
$role = $_SESSION['user_role'] ?? 'manager';
$name = $_SESSION['user_name'] ?? 'Manager';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Courses — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
/* Reuse same token CSS as dashboard */
:root,[data-theme="light"]{--bg:#f7f6f2;--surface:#fff;--border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;--text:#28251d;--muted:#7a7974;--faint:#bab9b4;--primary:#01696f;--primary-h:#0c4e54;--success:#437a22;--error:#a12c7b;--warning:#e67e22;--r-lg:.75rem;--r-md:.5rem;--shadow-sm:0 1px 2px oklch(0.2 0.01 80/0.06);--t:180ms cubic-bezier(.16,1,.3,1)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:oklch(1 0 0/0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--faint:#5a5957;--primary:#4f98a3;--primary-h:#227f8b;--success:#6daa45;--error:#d163a7;--warning:#e67e22}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh}

/* Layout with sidebar */
.app{display:flex;min-height:100dvh}

/* Sidebar styles */
.sidebar{width:280px;background:var(--surface);border-right:1px solid var(--border);padding:1.5rem 1rem;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:.6rem;margin-bottom:1.5rem;padding:0 .5rem}
.sidebar-logo svg{width:34px;height:34px}
.logo-text{font-size:1.1rem;font-weight:700;letter-spacing:-0.3px;color:var(--text)}
.logo-text span{color:var(--primary);font-weight:600}
.sidebar-user{background:var(--bg);border-radius:var(--r-lg);padding:1rem;margin-bottom:1.5rem;border:1px solid var(--border)}
.user-badge{display:inline-block;padding:.2rem .6rem;background:var(--primary);color:#fff;border-radius:999px;font-size:.65rem;font-weight:600;margin-bottom:.5rem}
.user-name{font-weight:600;font-size:.85rem;color:var(--text)}
.user-email-sm{font-size:.7rem;color:var(--muted);margin-top:.2rem}
.nav-section{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:1.2rem 0 .5rem .5rem}
.nav-section:first-of-type{margin-top:0}
.nav-item{display:flex;align-items:center;gap:.6rem;padding:.6rem .8rem;border-radius:var(--r-md);color:var(--text);text-decoration:none;font-size:.85rem;font-weight:500;transition:all var(--t)}
.nav-item svg{width:1.2rem;height:1.2rem;color:var(--muted);flex-shrink:0}
.nav-item:hover{background:var(--bg);color:var(--primary)}
.nav-item.active{background:oklch(from var(--primary) l c h/0.1);color:var(--primary);font-weight:600}
.nav-item.active svg{color:var(--primary)}
.sidebar-footer{display:flex;gap:.5rem;margin-top:auto;padding-top:1.5rem}
.btn-sm{padding:.45rem .8rem;border-radius:var(--r-md);font-size:.75rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;border:none;cursor:pointer;transition:all var(--t)}
.btn-danger{background:oklch(from var(--error) l c h/0.12);color:var(--error)}
.btn-danger:hover{background:var(--error);color:#fff}
.btn-ghost{background:transparent;color:var(--muted)}
.btn-ghost:hover{background:var(--bg);color:var(--text)}

/* Main content */
.main{flex:1;padding:1.5rem;min-width:0}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
h1{font-size:1.3rem;font-weight:700}
.filter-bar{display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap}
input[type=text],select{padding:.5rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--surface);color:var(--text);font:inherit;font-size:.85rem}
input:focus,select:focus{outline:none;border-color:var(--primary)}
.btn-primary{background:var(--primary);color:#fff;padding:.5rem 1rem;border-radius:var(--r-md);font-size:.85rem;font-weight:600;border:none;cursor:pointer;transition:background var(--t)}
.btn-primary:hover{background:var(--primary-h)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm)}
table{width:100%;border-collapse:collapse}
th{padding:.65rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--divider)}
td{padding:.75rem 1rem;font-size:.85rem;border-bottom:1px solid var(--divider)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--bg)}
.badge{display:inline-flex;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600}
.badge-published{background:oklch(from var(--success) l c h/0.12);color:var(--success)}
.badge-draft{background:oklch(from var(--muted) l c h/0.15);color:var(--muted)}
.action-link{font-size:.8rem;color:var(--primary);font-weight:500;text-decoration:none}
.action-link:hover{text-decoration:underline}
.no-delete{font-size:.75rem;color:var(--faint);font-style:italic}
.content-link{font-size:.78rem;color:var(--warning);font-weight:500;margin-left:.5rem;text-decoration:none}
.content-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="app">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="7" fill="var(--primary)"/>
        <path d="M9 23L16 9L23 23" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M12 19h8" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
      <div class="logo-text">Internship<span>Adda</span></div>
    </div>

    <div class="sidebar-user">
      <div class="user-badge"><?= htmlspecialchars(ucfirst($role)) ?></div>
      <div class="user-name"><?= htmlspecialchars($name) ?></div>
      <div class="user-email-sm">Platform Manager</div>
    </div>

    <nav>
      <div class="nav-section">Overview</div>
      <a href="manager_dashboard.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>

      <div class="nav-section">Content</div>
      <?php if(can('courses_view')): ?>
      <a href="courses.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Courses
      </a>
      <?php endif; ?>
      <?php if(can('internships_view')): ?>
      <a href="internships.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        Internships
      </a>
      <?php endif; ?>
      <a href="banners.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
        Banners
      </a>

      <div class="nav-section">Logs</div>
      <a href="activity_log.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Activity Log
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="manager_dashboard.php?logout=1" class="btn-sm btn-danger">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
      <button data-theme-toggle class="btn-sm btn-ghost" aria-label="Toggle theme" style="padding:.45rem .6rem">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main">
    <div class="page-header">
      <div>
        <h1>Courses Management</h1>
      </div>
      <div style="font-size:.82rem;color:var(--muted);background:oklch(from var(--primary) l c h/0.08);padding:.4rem .9rem;border-radius:9999px">
        View + Edit only &nbsp;•&nbsp; Delete restricted
      </div>
    </div>

    <form method="GET" class="filter-bar">
      <input type="text" name="q" placeholder="Search course..." value="<?= htmlspecialchars($search) ?>">
      <select name="status">
        <option value="">All Status</option>
        <option value="published" <?= $status==='published'?'selected':'' ?>>Published</option>
        <option value="draft" <?= $status==='draft'?'selected':'' ?>>Draft</option>
      </select>
      <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card">
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>#</th><th>Title</th><th>Status</th><th>Price</th>
              <th>Creator</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($courses)): ?>
            <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted)">No courses found</td><tr>
            <?php else: ?>
            <?php foreach($courses as $c): ?>
            <tr>
              <td style="color:var(--faint);font-size:.78rem"><?= $c['id'] ?></td>
              <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
              <td><span class="badge badge-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>
              <td>₹<?= number_format($c['price'],0) ?></td>
              <td style="color:var(--muted)"><?= htmlspecialchars($c['creator']??'—') ?></td>
              <td>
                <?php if(can('courses_edit')): ?>
                  <a href="course_edit.php?id=<?= $c['id'] ?>" class="action-link">Edit</a>
                  <a href="course_content_editor.php?course_id=<?= $c['id'] ?>" class="content-link">Content</a>
                <?php else: ?>
                  <span class="no-delete">View only</span>
                <?php endif; ?>
                &nbsp; <span class="no-delete">No delete</span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script>
(function(){
  // Theme toggle
  const root = document.documentElement;
  const themeBtn = document.querySelector('[data-theme-toggle]');
  const savedTheme = localStorage.getItem('theme');
  const prefersDark = matchMedia('(prefers-color-scheme:dark)').matches;
  let currentTheme = savedTheme || (prefersDark ? 'dark' : 'light');
  root.setAttribute('data-theme', currentTheme);

  if(themeBtn) {
    themeBtn.addEventListener('click', () => {
      const newTheme = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
    });
  }
})();
</script>
</body>
</html>