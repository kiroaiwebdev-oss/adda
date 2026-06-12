<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('certificates_view');
$db = getDB();
$activeNav = 'certificates';

$flash = null;

// Approve / reject (edit permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('certificates_edit')) {
    $action = $_POST['action']    ?? '';
    $id     = (int)($_POST['id']  ?? 0);
    $notes  = trim($_POST['admin_notes'] ?? '');
    if ($id && in_array($action, ['approve','reject'], true)) {
        try {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $db->prepare("UPDATE internship_certificate_requests SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?")
               ->execute([$newStatus, $notes, $_SESSION['user_id'], $id]);
            logAction("certificate_$action", 'certificate_request', $id, "Status: $newStatus");
            $flash = ['ok' => "Request marked as $newStatus"];
        } catch (Exception $e) {
            $flash = ['err' => $e->getMessage()];
        }
    }
}

// Filters
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT
            r.*,
            i.title AS internship_title, i.company_name,
            u.name  AS user_name, u.email AS user_email
        FROM internship_certificate_requests r
        LEFT JOIN internships i ON i.id = r.internship_id
        LEFT JOIN users u       ON u.id = r.user_id
        WHERE 1=1";
$params = [];
if ($status) { $sql .= " AND r.status = ?"; $params[] = $status; }
if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR r.student_name LIKE ? OR i.title LIKE ?)";
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$sql .= " ORDER BY r.request_date DESC LIMIT 100";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    $requests = [];
    $flash = $flash ?? ['err' => $e->getMessage()];
}

// Stats
try {
    $cntPending  = (int)$db->query("SELECT COUNT(*) FROM internship_certificate_requests WHERE status='pending'")->fetchColumn();
    $cntApproved = (int)$db->query("SELECT COUNT(*) FROM internship_certificate_requests WHERE status='approved'")->fetchColumn();
    $cntRejected = (int)$db->query("SELECT COUNT(*) FROM internship_certificate_requests WHERE status='rejected'")->fetchColumn();
    $cntTotal    = (int)$db->query("SELECT COUNT(*) FROM internship_certificate_requests")->fetchColumn();
} catch (Exception $e) {
    $cntPending = $cntApproved = $cntRejected = $cntTotal = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Certificate Requests — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
.req-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow-sm);margin-bottom:1rem;overflow:hidden}
.req-head{padding:.9rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;align-items:center}
.req-name{font-weight:700;font-size:.95rem}
.req-meta{font-size:.78rem;color:var(--muted);margin-top:.15rem}
.req-grid{padding:1rem 1.25rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.85rem;font-size:.85rem}
.req-grid-item .lbl{font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem}
.req-actions{padding:.85rem 1.25rem;border-top:1px solid var(--divider);background:var(--surface-2)}
</style>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb">Manager Panel / <span>Certificate Requests</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Certificate Requests</h1>
                <p>Internship completion ke baad certificate ke liye students ke requests</p>
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
            <div class="kpi-card"><div class="kpi-label">Approved</div><div class="kpi-value" style="color:var(--success)"><?= $cntApproved ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Rejected</div><div class="kpi-value" style="color:var(--error)"><?= $cntRejected ?></div></div>
        </div>

        <form method="GET" class="filter-bar">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by student or internship…" style="min-width:220px;flex:1">
            <select name="status" onchange="this.form.submit()">
                <option value="">All status</option>
                <option value="pending"  <?= $status==='pending'?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $status==='approved'?'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <?php if ($search || $status): ?>
                <a class="btn btn-outline" href="certificates.php">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($requests)): ?>
            <div class="card"><div class="empty-state"><div class="empty-state-icon">🎓</div><div>No certificate requests match.</div></div></div>
        <?php endif; ?>

        <?php foreach ($requests as $r): ?>
            <div class="req-card">
                <div class="req-head">
                    <div>
                        <div class="req-name"><?= htmlspecialchars($r['student_name'] ?: $r['user_name']) ?></div>
                        <div class="req-meta">
                            <?= htmlspecialchars($r['email'] ?: $r['user_email']) ?>
                            <?= !empty($r['phone']) ? ' • ' . htmlspecialchars($r['phone']) : '' ?>
                        </div>
                    </div>
                    <span class="badge status-<?= htmlspecialchars($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
                </div>

                <div class="req-grid">
                    <div class="req-grid-item">
                        <div class="lbl">Internship</div>
                        <?= htmlspecialchars($r['internship_title'] ?? '—') ?>
                    </div>
                    <div class="req-grid-item">
                        <div class="lbl">Company</div>
                        <?= htmlspecialchars($r['company_name'] ?? '—') ?>
                    </div>
                    <div class="req-grid-item">
                        <div class="lbl">College</div>
                        <?= htmlspecialchars($r['college_name'] ?? '—') ?>
                    </div>
                    <div class="req-grid-item">
                        <div class="lbl">Degree</div>
                        <?= htmlspecialchars($r['degree'] ?? '—') ?>
                    </div>
                    <div class="req-grid-item">
                        <div class="lbl">LinkedIn</div>
                        <?php if (!empty($r['linkedin_url'])): ?>
                            <a href="<?= htmlspecialchars($r['linkedin_url']) ?>" target="_blank" rel="noopener" style="color:var(--primary)">View profile</a>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                    <div class="req-grid-item">
                        <div class="lbl">Requested</div>
                        <?= date('d M Y', strtotime($r['request_date'])) ?>
                    </div>
                    <?php if (!empty($r['notes'])): ?>
                    <div class="req-grid-item" style="grid-column:1/-1">
                        <div class="lbl">Student notes</div>
                        <?= nl2br(htmlspecialchars($r['notes'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($r['admin_notes'])): ?>
                    <div class="req-grid-item" style="grid-column:1/-1">
                        <div class="lbl">Admin notes</div>
                        <?= nl2br(htmlspecialchars($r['admin_notes'])) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (can('certificates_edit') && $r['status'] === 'pending'): ?>
                <form method="POST" class="req-actions">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <label class="label">Notes (optional, shared with student)</label>
                    <textarea name="admin_notes" rows="2" placeholder="Why approving / rejecting?"></textarea>
                    <div style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap">
                        <button type="submit" name="action" value="approve" class="btn btn-primary">✓ Approve</button>
                        <button type="submit" name="action" value="reject"  class="btn btn-error">✕ Reject</button>
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
