<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('contacts_view');
$db = getDB();
$activeNav = 'contacts';

// Status update (only with edit permission)
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('contacts_edit')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $id        = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        if ($id && in_array($newStatus, ['pending','in_progress','resolved','closed'], true)) {
            try {
                $db->prepare("UPDATE contact_submissions SET status = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$newStatus, $id]);
                logAction('contact_status_updated', 'contact', $id, "Status: $newStatus");
                $flash = ['ok' => "Status updated to $newStatus"];
            } catch (Exception $e) {
                $flash = ['err' => $e->getMessage()];
            }
        }
    }

    if ($action === 'add_note') {
        $id   = (int)($_POST['id'] ?? 0);
        $note = trim($_POST['admin_notes'] ?? '');
        if ($id) {
            try {
                $db->prepare("UPDATE contact_submissions SET admin_notes = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$note, $id]);
                logAction('contact_note_added', 'contact', $id, "Note saved");
                $flash = ['ok' => 'Note saved'];
            } catch (Exception $e) {
                $flash = ['err' => $e->getMessage()];
            }
        }
    }
}

// Filters
$status   = $_GET['status']   ?? '';
$priority = $_GET['priority'] ?? '';
$search   = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM contact_submissions WHERE 1=1";
$params = [];
if ($status)   { $sql .= " AND status = ?";   $params[] = $status; }
if ($priority) { $sql .= " AND priority = ?"; $params[] = $priority; }
if ($search)   { $sql .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
                 array_push($params, "%$search%", "%$search%", "%$search%", "%$search%"); }
$sql .= " ORDER BY created_at DESC LIMIT 100";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
    $flash    = $flash ?? ['err' => $e->getMessage()];
}

// Counters
try {
    $cntPending = (int)$db->query("SELECT COUNT(*) FROM contact_submissions WHERE status='pending'")->fetchColumn();
    $cntInProg  = (int)$db->query("SELECT COUNT(*) FROM contact_submissions WHERE status='in_progress'")->fetchColumn();
    $cntResolved= (int)$db->query("SELECT COUNT(*) FROM contact_submissions WHERE status='resolved'")->fetchColumn();
    $cntTotal   = (int)$db->query("SELECT COUNT(*) FROM contact_submissions")->fetchColumn();
} catch (Exception $e) {
    $cntPending = $cntInProg = $cntResolved = $cntTotal = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contact Messages — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
.msg-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow-sm);margin-bottom:1rem;overflow:hidden}
.msg-head{padding:.85rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;align-items:center}
.msg-name{font-weight:700;font-size:.95rem}
.msg-meta{font-size:.78rem;color:var(--muted)}
.msg-subject{padding:.7rem 1.25rem;font-size:.9rem;font-weight:600;color:var(--text);background:var(--bg);border-bottom:1px solid var(--divider)}
.msg-body{padding:1rem 1.25rem;font-size:.88rem;line-height:1.6;color:var(--text);white-space:pre-wrap;word-wrap:break-word}
.msg-actions{padding:.85rem 1.25rem;border-top:1px solid var(--divider);display:flex;gap:.7rem;flex-wrap:wrap;background:var(--surface-2);align-items:center}
.msg-notes{padding:.85rem 1.25rem;border-top:1px solid var(--divider);background:var(--bg);font-size:.83rem}
.priority-low{background:rgba(107,114,128,0.15);color:var(--muted)}
.priority-medium{background:rgba(22,163,74,0.12);color:var(--primary)}
.priority-high{background:rgba(218,113,1,0.15);color:var(--orange)}
.priority-urgent{background:rgba(161,44,123,0.12);color:var(--error)}
</style>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb">Manager Panel / <span>Contact Messages</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Contact Messages</h1>
                <p>Users ne contact form se bheje hue messages</p>
            </div>
        </div>

        <?php if (!empty($flash['ok'])): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($flash['ok']) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['err'])): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($flash['err']) ?></div>
        <?php endif; ?>

        <div class="kpi-grid">
            <div class="kpi-card accent"><div class="kpi-label">Total</div><div class="kpi-value"><?= $cntTotal ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Pending</div><div class="kpi-value" style="color:var(--orange)"><?= $cntPending ?></div></div>
            <div class="kpi-card"><div class="kpi-label">In Progress</div><div class="kpi-value" style="color:var(--primary)"><?= $cntInProg ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Resolved</div><div class="kpi-value" style="color:var(--success)"><?= $cntResolved ?></div></div>
        </div>

        <form method="GET" class="filter-bar">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, subject…" style="min-width:220px;flex:1">
            <select name="status" onchange="this.form.submit()">
                <option value="">All status</option>
                <option value="pending"     <?= $status==='pending'?'selected':'' ?>>Pending</option>
                <option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>In Progress</option>
                <option value="resolved"    <?= $status==='resolved'?'selected':'' ?>>Resolved</option>
                <option value="closed"      <?= $status==='closed'?'selected':'' ?>>Closed</option>
            </select>
            <select name="priority" onchange="this.form.submit()">
                <option value="">All priority</option>
                <option value="low"     <?= $priority==='low'?'selected':'' ?>>Low</option>
                <option value="medium"  <?= $priority==='medium'?'selected':'' ?>>Medium</option>
                <option value="high"    <?= $priority==='high'?'selected':'' ?>>High</option>
                <option value="urgent"  <?= $priority==='urgent'?'selected':'' ?>>Urgent</option>
            </select>
            <button class="btn btn-primary" type="submit">Apply</button>
            <?php if ($search || $status || $priority): ?>
                <a class="btn btn-outline" href="contacts.php">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($messages)): ?>
            <div class="card"><div class="empty-state"><div class="empty-state-icon">📥</div><div>No messages match your filters.</div></div></div>
        <?php endif; ?>

        <?php foreach ($messages as $m): ?>
            <div class="msg-card">
                <div class="msg-head">
                    <div>
                        <div class="msg-name"><?= htmlspecialchars($m['name']) ?>
                            <span class="badge priority-<?= htmlspecialchars($m['priority'] ?? 'medium') ?>" style="margin-left:.5rem"><?= ucfirst($m['priority'] ?? 'medium') ?></span>
                        </div>
                        <div class="msg-meta">
                            <?= htmlspecialchars($m['email']) ?>
                            <?= !empty($m['phone']) ? ' • ' . htmlspecialchars($m['phone']) : '' ?>
                            • <?= date('d M Y, H:i', strtotime($m['created_at'])) ?>
                        </div>
                    </div>
                    <span class="badge status-<?= htmlspecialchars($m['status']) ?>"><?= ucfirst(str_replace('_', ' ', $m['status'])) ?></span>
                </div>

                <div class="msg-subject">📌 <?= htmlspecialchars($m['subject']) ?></div>
                <div class="msg-body"><?= nl2br(htmlspecialchars($m['message'])) ?></div>

                <?php if (can('contacts_edit')): ?>
                <form method="POST" class="msg-actions">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id"     value="<?= (int)$m['id'] ?>">
                    <select name="status">
                        <option value="pending"     <?= $m['status']==='pending'?'selected':'' ?>>Pending</option>
                        <option value="in_progress" <?= $m['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                        <option value="resolved"    <?= $m['status']==='resolved'?'selected':'' ?>>Resolved</option>
                        <option value="closed"      <?= $m['status']==='closed'?'selected':'' ?>>Closed</option>
                    </select>
                    <button class="btn btn-primary" type="submit">Update Status</button>
                    <a class="btn btn-outline" href="mailto:<?= htmlspecialchars($m['email']) ?>?subject=Re: <?= htmlspecialchars($m['subject']) ?>">✉ Reply via Email</a>
                </form>

                <form method="POST" class="msg-notes">
                    <input type="hidden" name="action" value="add_note">
                    <input type="hidden" name="id"     value="<?= (int)$m['id'] ?>">
                    <label class="label">Admin Notes</label>
                    <textarea name="admin_notes" rows="2" placeholder="Internal note about this message…"><?= htmlspecialchars($m['admin_notes'] ?? '') ?></textarea>
                    <div style="margin-top:.5rem;display:flex;justify-content:flex-end">
                        <button type="submit" class="btn btn-outline" style="font-size:.78rem;padding:.4rem .8rem">Save Note</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
