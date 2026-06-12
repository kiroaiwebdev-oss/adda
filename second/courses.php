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
:root,[data-theme="light"]{--bg:#f7f6f2;--surface:#fff;--border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;--text:#28251d;--muted:#7a7974;--faint:#bab9b4;--primary:#16a34a;--primary-h:#15803d;--success:#437a22;--error:#a12c7b;--warning:#e67e22;--r-lg:.75rem;--r-md:.5rem;--shadow-sm:0 1px 2px oklch(0.2 0.01 80/0.06);--t:180ms cubic-bezier(.16,1,.3,1)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:oklch(1 0 0/0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--faint:#5a5957;--primary:#4ade80;--primary-h:#22c55e;--success:#6daa45;--error:#d163a7;--warning:#e67e22}
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
  <?php $activeNav = "courses"; include __DIR__ . "/_sidebar.php"; ?>

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