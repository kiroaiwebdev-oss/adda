<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('users_view');
$db = getDB();
$activeNav = 'users';

// Filters
$search = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT id, name, email, mobile, role, status, referral_code, created_at FROM users WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (name LIKE ? OR email LIKE ? OR mobile LIKE ?)"; array_push($params, "%$search%", "%$search%", "%$search%"); }
if ($role)   { $sql .= " AND role = ?"; $params[] = $role; }
if ($status) { $sql .= " AND status = ?"; $params[] = $status; }
$sql .= " ORDER BY created_at DESC LIMIT 200";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    $err   = $e->getMessage();
}

// Aggregate stats
try {
    $totalUsers   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalLearners= $db->query("SELECT COUNT(*) FROM users WHERE role='learner'")->fetchColumn();
    $totalActive  = $db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $totalThisMonth = $db->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = $totalLearners = $totalActive = $totalThisMonth = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="breadcrumb">Manager Panel / <span>Users</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Users</h1>
                <p>Saare registered users ki list — search, filter aur details dekhne ke liye</p>
            </div>
        </div>

        <?php if (!empty($err)): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <div class="kpi-grid">
            <div class="kpi-card accent">
                <div class="kpi-label">Total Users</div>
                <div class="kpi-value"><?= number_format((int)$totalUsers) ?></div>
                <div class="kpi-sub">All accounts</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Learners</div>
                <div class="kpi-value"><?= number_format((int)$totalLearners) ?></div>
                <div class="kpi-sub">Role: learner</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Active</div>
                <div class="kpi-value"><?= number_format((int)$totalActive) ?></div>
                <div class="kpi-sub">status = active</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">This Month</div>
                <div class="kpi-value"><?= number_format((int)$totalThisMonth) ?></div>
                <div class="kpi-sub">New signups</div>
            </div>
        </div>

        <form method="GET" class="filter-bar">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, mobile…" style="min-width:220px;flex:1">
            <select name="role" onchange="this.form.submit()">
                <option value="">All roles</option>
                <option value="learner"  <?= $role==='learner'?'selected':'' ?>>Learner</option>
                <option value="admin"    <?= $role==='admin'?'selected':'' ?>>Admin</option>
                <option value="manager"  <?= $role==='manager'?'selected':'' ?>>Manager</option>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All status</option>
                <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                <option value="banned"   <?= $status==='banned'?'selected':'' ?>>Banned</option>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <?php if ($search || $role || $status): ?>
                <a href="users.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
            <span style="font-size:.82rem;color:var(--muted);margin-left:auto"><?= count($users) ?> shown</span>
        </form>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Referral Code</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">🙋</div><div>No users found.</div></div></td></tr>
                    <?php else: foreach ($users as $u): ?>
                        <tr>
                            <td style="font-weight:600"><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['mobile'] ?? '—') ?></td>
                            <td><span class="badge <?= $u['role']==='admin'?'badge-error':($u['role']==='manager'?'badge-warning':'badge-info') ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="badge status-<?= htmlspecialchars($u['status']) ?>"><?= ucfirst($u['status']) ?></span></td>
                            <td style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($u['referral_code'] ?? '—') ?></td>
                            <td style="font-size:.78rem;color:var(--muted)"><?= !empty($u['created_at']) ? date('d M Y', strtotime($u['created_at'])) : '—' ?></td>
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
