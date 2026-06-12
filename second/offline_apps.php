<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('offline_apps_view');

$db = getDB();

// Status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('offline_apps_edit')) {
    $appId     = (int)($_POST['app_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');
    if ($appId && in_array($newStatus, ['pending','contacted','enrolled','rejected'])) {
        $extra = '';
        $params = [$newStatus];
        if ($newStatus === 'contacted') { $extra = ", iscontacted=1, contactedat=NOW()"; }
        if ($newStatus === 'enrolled')  { $extra = ", isenrolled=1, enrolledat=NOW()"; }
        if ($notes) { $params[] = $notes; $extra .= ", notes=?"; }
        $params[] = $appId;
        $db->prepare("UPDATE offlineinternshipapplications SET status=?$extra WHERE id=?")
           ->execute($params);
        logAction("offline_app_status_updated", 'offline_app', $appId, "New status: $newStatus");
    }
    header('Location: offline_apps.php?updated=1'); exit;
}

$filter = $_GET['status'] ?? '';
$sql = "SELECT * FROM offlineinternshipapplications WHERE 1=1";
$params = [];
if ($filter) { $sql .= " AND status = ?"; $params[] = $filter; }
$sql .= " ORDER BY created_at DESC LIMIT 80";
$stmt = $db->prepare($sql); $stmt->execute($params);
$apps = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Offline Applications — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f7f6f2;--surface:#fff;--border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;--text:#28251d;--muted:#7a7974;--faint:#bab9b4;--primary:#01696f;--primary-h:#0c4e54;--success:#437a22;--error:#a12c7b;--orange:#da7101;--r-lg:.75rem;--r-md:.5rem;--shadow-sm:0 1px 2px oklch(0.2 0.01 80/0.06);--t:180ms}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:oklch(1 0 0/0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--faint:#5a5957;--primary:#4f98a3;--success:#6daa45;--error:#d163a7;--orange:#fdab43}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);padding:1.5rem;min-height:100dvh}
h1{font-size:1.25rem;font-weight:700;margin-bottom:1.25rem}
.back{font-size:.83rem;color:var(--primary);display:inline-flex;gap:.3rem;margin-bottom:.75rem}
.filter-bar{display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center}
select,input{padding:.5rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--surface);color:var(--text);font:inherit;font-size:.85rem}
.btn{padding:.5rem 1rem;border-radius:var(--r-md);font:inherit;font-size:.83rem;font-weight:600;cursor:pointer;border:none}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-h)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:1rem}
.app-header{padding:.9rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.app-name{font-weight:700;font-size:.95rem}
.app-meta{font-size:.78rem;color:var(--muted)}
.app-body{padding:1rem 1.25rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem}
.info-item{font-size:.8rem}.info-label{color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem}
.badge{display:inline-flex;padding:.2rem .6rem;border-radius:9999px;font-size:.72rem;font-weight:600}
.badge-pending{background:oklch(from var(--orange) l c h/0.15);color:var(--orange)}
.badge-contacted{background:oklch(from var(--primary) l c h/0.12);color:var(--primary)}
.badge-enrolled{background:oklch(from var(--success) l c h/0.12);color:var(--success)}
.badge-rejected{background:oklch(from var(--error) l c h/0.12);color:var(--error)}
.update-form{padding:.9rem 1.25rem;border-top:1px solid var(--divider);display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;background:var(--bg)}
.alert{padding:.65rem 1rem;border-radius:var(--r-md);font-size:.85rem;margin-bottom:1.2rem}
.alert-success{background:oklch(from var(--success) l c h/0.1);border:1px solid oklch(from var(--success) l c h/0.3);color:var(--success)}
</style>
</head>
<body>
<a href="manager_dashboard.php" class="back">← Dashboard</a>
<h1>Offline Internship Applications</h1>

<?php if(isset($_GET['updated'])): ?>
<div class="alert alert-success">✓ Application status updated successfully!</div>
<?php endif; ?>

<form method="GET" class="filter-bar">
  <select name="status" onchange="this.form.submit()">
    <option value="">All Applications</option>
    <option value="pending"   <?= $filter==='pending'?'selected':'' ?>>Pending</option>
    <option value="contacted" <?= $filter==='contacted'?'selected':'' ?>>Contacted</option>
    <option value="enrolled"  <?= $filter==='enrolled'?'selected':'' ?>>Enrolled</option>
    <option value="rejected"  <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
  </select>
  <span style="font-size:.82rem;color:var(--muted)"><?= count($apps) ?> applications</span>
</form>

<?php if(empty($apps)): ?>
  <p style="color:var(--muted);font-size:.9rem;padding:2rem;text-align:center">No applications found.</p>
<?php endif; ?>

<?php foreach($apps as $app): ?>
<div class="card">
  <div class="app-header">
    <div>
      <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
      <div class="app-meta"><?= htmlspecialchars($app['email']) ?> &nbsp;•&nbsp; <?= htmlspecialchars($app['phone']) ?></div>
    </div>
    <span class="badge badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
  </div>
  <div class="app-body">
    <div class="info-item"><div class="info-label">Internship Type</div><?= htmlspecialchars($app['internshiptype']) ?></div>
    <div class="info-item"><div class="info-label">Duration</div><?= htmlspecialchars($app['duration']??'—') ?></div>
    <div class="info-item"><div class="info-label">College</div><?= htmlspecialchars($app['college']??'—') ?></div>
    <div class="info-item"><div class="info-label">Year</div><?= htmlspecialchars($app['year']??'—') ?></div>
    <div class="info-item"><div class="info-label">Applied</div><?= date('d M Y', strtotime($app['created_at'])) ?></div>
    <?php if($app['notes']): ?>
    <div class="info-item" style="grid-column:1/-1"><div class="info-label">Notes</div><?= htmlspecialchars($app['notes']) ?></div>
    <?php endif; ?>
  </div>
  <?php if(can('offline_apps_edit')): ?>
  <form method="POST" class="update-form">
    <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
    <select name="new_status">
      <option value="pending"   <?= $app['status']==='pending'?'selected':'' ?>>Pending</option>
      <option value="contacted" <?= $app['status']==='contacted'?'selected':'' ?>>Contacted</option>
      <option value="enrolled"  <?= $app['status']==='enrolled'?'selected':'' ?>>Enrolled</option>
      <option value="rejected"  <?= $app['status']==='rejected'?'selected':'' ?>>Rejected</option>
    </select>
    <input type="text" name="notes" placeholder="Notes add karo..." style="flex:1;min-width:160px">
    <button type="submit" class="btn btn-primary">Update Status</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</body>
</html>