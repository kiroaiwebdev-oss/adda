<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
$db = getDB();
$activeNav = 'activity_log';

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
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="breadcrumb">Manager Panel / <span>Activity Log</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>My Activity Log</h1>
                <p>Aapke dwara ki gayi recent actions (last 100)</p>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Action</th><th>Type</th><th>Entity ID</th><th>IP</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php if(empty($logs)): ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">📋</div><div>No activity yet</div></div></td></tr>
                        <?php else: foreach($logs as $l): ?>
                        <tr>
                            <td style="color:var(--faint)"><?= $l['id'] ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($l['action']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($l['entity_type']) ?></span></td>
                            <td style="color:var(--muted)"><?= $l['entity_id'] ?: '—' ?></td>
                            <td style="color:var(--muted);font-size:.75rem"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
                            <td style="color:var(--muted);font-size:.78rem"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
