<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('offline_apps_view');

$db = getDB();
$activeNav = 'offline_apps';

$flash = null;

// ── Schema-detection: which table does this DB use? ────────────────
//  newer (correct) schema: offline_internship_applications + is_contacted etc.
//  older legacy schema:    offlineinternshipapplications  + iscontacted etc.
$tbl = null;
$colMap = []; // maps logical name → actual column
try {
    $hasNew = false;
    $hasOld = false;
    $tablesStmt = $db->query("SHOW TABLES");
    foreach ($tablesStmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
        if ($t === 'offline_internship_applications') $hasNew = true;
        if ($t === 'offlineinternshipapplications')   $hasOld = true;
    }
    if ($hasNew) {
        $tbl = 'offline_internship_applications';
        $colMap = [
            'is_contacted' => 'is_contacted',
            'is_enrolled'  => 'is_enrolled',
            'contacted_at' => 'contacted_at',
            'enrolled_at'  => 'enrolled_at',
            'created_at'   => 'created_at',
        ];
    } elseif ($hasOld) {
        $tbl = 'offlineinternshipapplications';
        $colMap = [
            'is_contacted' => 'iscontacted',
            'is_enrolled'  => 'isenrolled',
            'contacted_at' => 'contactedat',
            'enrolled_at'  => 'enrolledat',
            'created_at'   => 'createdat',
        ];
    }
} catch (Exception $e) {
    $flash = ['err' => 'Schema detect fail: ' . $e->getMessage()];
}

// ── POST handler ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('offline_apps_edit') && $tbl) {
    $appId     = (int)($_POST['app_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    if ($appId && in_array($newStatus, ['pending','contacted','enrolled','rejected'], true)) {
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE `$tbl` SET status = ? WHERE id = ?")
               ->execute([$newStatus, $appId]);
            if ($newStatus === 'contacted') {
                $col1 = $colMap['is_contacted']; $col2 = $colMap['contacted_at'];
                $db->prepare("UPDATE `$tbl` SET `$col1` = 1, `$col2` = NOW() WHERE id = ?")->execute([$appId]);
            }
            if ($newStatus === 'enrolled') {
                $a = $colMap['is_contacted']; $b = $colMap['contacted_at'];
                $c = $colMap['is_enrolled'];  $d = $colMap['enrolled_at'];
                $db->prepare("UPDATE `$tbl` SET `$a` = 1, `$c` = 1, `$b` = COALESCE(`$b`, NOW()), `$d` = NOW() WHERE id = ?")->execute([$appId]);
            }
            if ($notes !== '') {
                $db->prepare("UPDATE `$tbl` SET notes = ? WHERE id = ?")->execute([$notes, $appId]);
            }
            $db->commit();
            logAction('offline_app_status_updated', 'offline_app', $appId, "Status: $newStatus");
            header('Location: offline_apps.php?updated=1'); exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $flash = ['err' => $e->getMessage()];
        }
    }
}

// ── Fetch ───────────────────────────────────────────────────────────
$apps   = [];
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($tbl) {
    $sql = "SELECT * FROM `$tbl` WHERE 1=1";
    $params = [];
    if ($status) { $sql .= " AND status = ?"; $params[] = $status; }
    if ($search) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        array_push($params, "%$search%", "%$search%", "%$search%");
    }
    $created = $colMap['created_at'];
    $sql .= " ORDER BY `$created` DESC LIMIT 100";
    try {
        $stmt = $db->prepare($sql); $stmt->execute($params);
        $apps = $stmt->fetchAll();
    } catch (Exception $e) {
        $flash = ['err' => $e->getMessage()];
    }
}

// ── Stats ───────────────────────────────────────────────────────────
$stats = ['total'=>0,'pending'=>0,'contacted'=>0,'enrolled'=>0];
if ($tbl) {
    try {
        $stats['total']     = (int)$db->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        $stats['pending']   = (int)$db->query("SELECT COUNT(*) FROM `$tbl` WHERE status='pending'")->fetchColumn();
        $stats['contacted'] = (int)$db->query("SELECT COUNT(*) FROM `$tbl` WHERE status='contacted'")->fetchColumn();
        $stats['enrolled']  = (int)$db->query("SELECT COUNT(*) FROM `$tbl` WHERE status='enrolled'")->fetchColumn();
    } catch (Exception $e) { /* ignore */ }
}

if (isset($_GET['updated'])) $flash = ['ok' => 'Application updated'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Offline Applications — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
.app-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow-sm);margin-bottom:1rem;overflow:hidden}
.app-head{padding:.85rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;align-items:center}
.app-name{font-weight:700;font-size:.95rem}
.app-meta{font-size:.78rem;color:var(--muted)}
.app-grid{padding:1rem 1.25rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.85rem;font-size:.85rem}
.app-grid-item .lbl{font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem}
.app-actions{padding:.85rem 1.25rem;border-top:1px solid var(--divider);background:var(--surface-2);display:flex;gap:.65rem;flex-wrap:wrap;align-items:center}
</style>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb">Manager Panel / <span>Offline Applications</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Offline Internship Applications</h1>
                <p>Walk-in / form-based internship applications jo public website se aati hain</p>
            </div>
        </div>

        <?php if (!empty($flash['ok'])): ?><div class="alert alert-success">✓ <?= htmlspecialchars($flash['ok']) ?></div><?php endif; ?>
        <?php if (!empty($flash['err'])): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($flash['err']) ?></div><?php endif; ?>

        <?php if (!$tbl): ?>
            <div class="alert alert-warn">
                ⚠️ Offline applications table is missing. Aap public form se ek application bhejein, table auto-create ho jayega.
            </div>
        <?php else: ?>

        <div class="kpi-grid">
            <div class="kpi-card accent"><div class="kpi-label">Total</div><div class="kpi-value"><?= $stats['total'] ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Pending</div><div class="kpi-value" style="color:var(--orange)"><?= $stats['pending'] ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Contacted</div><div class="kpi-value" style="color:var(--primary)"><?= $stats['contacted'] ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Enrolled</div><div class="kpi-value" style="color:var(--success)"><?= $stats['enrolled'] ?></div></div>
        </div>

        <form method="GET" class="filter-bar">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, phone…" style="min-width:220px;flex:1">
            <select name="status" onchange="this.form.submit()">
                <option value="">All status</option>
                <option value="pending"   <?= $status==='pending'?'selected':'' ?>>Pending</option>
                <option value="contacted" <?= $status==='contacted'?'selected':'' ?>>Contacted</option>
                <option value="enrolled"  <?= $status==='enrolled'?'selected':'' ?>>Enrolled</option>
                <option value="rejected"  <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <?php if ($search || $status): ?><a class="btn btn-outline" href="offline_apps.php">Clear</a><?php endif; ?>
        </form>

        <?php if (empty($apps)): ?>
            <div class="card"><div class="empty-state"><div class="empty-state-icon">📋</div><div>Koi offline application abhi nahi.</div></div></div>
        <?php endif; ?>

        <?php foreach ($apps as $app): ?>
            <div class="app-card">
                <div class="app-head">
                    <div>
                        <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
                        <div class="app-meta">
                            <?= htmlspecialchars($app['email']) ?>
                            <?= !empty($app['phone']) ? ' • ' . htmlspecialchars($app['phone']) : '' ?>
                            • <?= !empty($app[$colMap['created_at']]) ? date('d M Y, H:i', strtotime($app[$colMap['created_at']])) : '—' ?>
                        </div>
                    </div>
                    <span class="badge status-<?= htmlspecialchars($app['status']) ?>"><?= ucfirst($app['status']) ?></span>
                </div>

                <div class="app-grid">
                    <?php if (!empty($app['college'])): ?>
                    <div class="app-grid-item"><div class="lbl">College</div><?= htmlspecialchars($app['college']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($app['course'])): ?>
                    <div class="app-grid-item"><div class="lbl">Course</div><?= htmlspecialchars($app['course']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($app['year'])): ?>
                    <div class="app-grid-item"><div class="lbl">Year</div><?= htmlspecialchars($app['year']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($app['internship_type'])): ?>
                    <div class="app-grid-item"><div class="lbl">Type</div><?= htmlspecialchars($app['internship_type']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($app['duration'])): ?>
                    <div class="app-grid-item"><div class="lbl">Duration</div><?= htmlspecialchars($app['duration']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($app['message'])): ?>
                    <div class="app-grid-item" style="grid-column:1/-1"><div class="lbl">Message</div><?= nl2br(htmlspecialchars($app['message'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($app['notes'])): ?>
                    <div class="app-grid-item" style="grid-column:1/-1"><div class="lbl">Admin Notes</div><?= nl2br(htmlspecialchars($app['notes'])) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (can('offline_apps_edit')): ?>
                <form method="POST" class="app-actions">
                    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                    <select name="new_status" required>
                        <option value="">— Update status —</option>
                        <option value="pending">Pending</option>
                        <option value="contacted">Contacted</option>
                        <option value="enrolled">Enrolled</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input type="text" name="notes" placeholder="Optional notes" style="flex:1;min-width:160px">
                    <button class="btn btn-primary" type="submit">Update</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php endif; /* $tbl exists */ ?>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
