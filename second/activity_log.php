<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
$db = getDB();

$logs = $db->prepare(
    "SELECT mal.*, u.name FROM manager_activity_log mal 
     JOIN users u ON u.id = mal.manager_id 
     WHERE mal.manager_id = ? 
     ORDER BY mal.created_at DESC LIMIT 100"
);
$logs->execute([$_SESSION['user_id']]);
$logs = $logs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activity Log — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f7f6f2;--surface:#fff;--border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;--text:#28251d;--muted:#7a7974;--faint:#bab9b4;--primary:#16a34a;--r-lg:.75rem;--shadow-sm:0 1px 2px oklch(0.2 0.01 80/0.06)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:oklch(1 0 0/0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--faint:#5a5957;--primary:#4ade80}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);padding:1.5rem}
h1{font-size:1.2rem;font-weight:700;margin-bottom:1.25rem}
.back{font-size:.83rem;color:var(--primary);display:inline-flex;gap:.3rem;margin-bottom:.75rem}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm);max-width:860px}
table{width:100%;border-collapse:collapse}
th{padding:.6rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--divider)}
td{padding:.7rem 1rem;font-size:.83rem;border-bottom:1px solid var(--divider)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--bg)}
.entity-badge{display:inline-flex;padding:.15rem .55rem;border-radius:9999px;font-size:.7rem;font-weight:600;background:oklch(from var(--primary) l c h/0.1);color:var(--primary)}
</style>
</head>
<body>
<a href="manager_dashboard.php" class="back">← Dashboard</a>
<h1>My Activity Log</h1>
<div class="card">
  <table>
    <thead><tr><th>#</th><th>Action</th><th>Type</th><th>Entity ID</th><th>IP</th><th>Time</th></tr></thead>
    <tbody>
      <?php if(empty($logs)): ?>
      <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted)">No activity yet</td></tr>
      <?php else: ?>
      <?php foreach($logs as $l): ?>
      <tr>
        <td style="color:var(--faint)"><?= $l['id'] ?></td>
        <td><?= htmlspecialchars($l['action']) ?></td>
        <td><span class="entity-badge"><?= $l['entity_type'] ?></span></td>
        <td style="color:var(--muted)"><?= $l['entity_id'] ?: '—' ?></td>
        <td style="color:var(--muted);font-size:.75rem"><?= $l['ip_address'] ?></td>
        <td style="color:var(--muted);font-size:.78rem"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>