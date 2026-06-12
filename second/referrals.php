<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('referrals_view');
$db = getDB();
$activeNav = 'referrals';

// Filters
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

// Stats — be defensive (table might not exist)
$totalRefs   = 0;
$completedRefs = 0;
$pendingRefs   = 0;
$totalPoints  = 0;
$couponsGen   = 0;
try {
    $totalRefs     = (int)$db->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
    $completedRefs = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status='completed'")->fetchColumn();
    $pendingRefs   = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { /* ignore */ }
try {
    $totalPoints = (int)$db->query("SELECT COALESCE(SUM(CASE WHEN transaction_type='credit' THEN points_earned ELSE -points_earned END),0) FROM referral_earnings")->fetchColumn();
} catch (Exception $e) { /* ignore */ }
try {
    $couponsGen = (int)$db->query("SELECT COUNT(*) FROM coupons WHERE is_referral_coupon=1")->fetchColumn();
} catch (Exception $e) { /* ignore */ }

// Top referrers
$topReferrers = [];
try {
    $topReferrers = $db->query("
        SELECT u.id, u.name, u.email, u.referral_code,
               COUNT(r.id) AS total_refs,
               SUM(CASE WHEN r.status='completed' THEN 1 ELSE 0 END) AS completed_refs
        FROM users u
        LEFT JOIN referrals r ON r.referrer_user_id = u.id
        WHERE r.id IS NOT NULL
        GROUP BY u.id, u.name, u.email, u.referral_code
        ORDER BY completed_refs DESC, total_refs DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) { /* ignore */ }

// Recent referrals list
$sql = "SELECT
            r.id, r.status, r.referral_code, r.signup_date, r.first_purchase_date,
            referrer.name  AS referrer_name,  referrer.email AS referrer_email,
            referred.name  AS referred_name,  referred.email AS referred_email
        FROM referrals r
        LEFT JOIN users referrer ON referrer.id = r.referrer_user_id
        LEFT JOIN users referred ON referred.id = r.referred_user_id
        WHERE 1=1";
$params = [];
if ($status) { $sql .= " AND r.status = ?"; $params[] = $status; }
if ($search) {
    $sql .= " AND (referrer.name LIKE ? OR referrer.email LIKE ? OR referred.name LIKE ? OR referred.email LIKE ?)";
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$sql .= " ORDER BY COALESCE(r.signup_date, r.id) DESC LIMIT 100";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $referrals = $stmt->fetchAll();
} catch (Exception $e) {
    $referrals = [];
    $err = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Referrals — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb">Manager Panel / <span>Referrals</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Referral Tracking</h1>
                <p>Saare referrals, top referrers aur points ki summary</p>
            </div>
        </div>

        <?php if (!empty($err)): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <div class="kpi-grid">
            <div class="kpi-card accent"><div class="kpi-label">Total Referrals</div><div class="kpi-value"><?= $totalRefs ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Completed</div><div class="kpi-value" style="color:var(--success)"><?= $completedRefs ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Pending</div><div class="kpi-value" style="color:var(--orange)"><?= $pendingRefs ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Net Points</div><div class="kpi-value"><?= number_format($totalPoints) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Coupons Generated</div><div class="kpi-value"><?= $couponsGen ?></div></div>
        </div>

        <div class="grid-2">
            <div class="section-card">
                <div class="section-head"><h2>Top Referrers</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Referrer</th><th>Code</th><th>Completed</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if (empty($topReferrers)): ?>
                                <tr><td colspan="4" style="padding:1.5rem;text-align:center;color:var(--muted)">No referrers yet</td></tr>
                            <?php else: foreach ($topReferrers as $u): ?>
                                <tr>
                                    <td><div style="font-weight:600"><?= htmlspecialchars($u['name']) ?></div><div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></div></td>
                                    <td style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($u['referral_code'] ?? '—') ?></td>
                                    <td><span class="badge badge-success"><?= (int)$u['completed_refs'] ?></span></td>
                                    <td><?= (int)$u['total_refs'] ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head"><h2>Status Distribution</h2></div>
                <div style="padding:1.25rem">
                    <?php
                    $totalForBar = max(1, $totalRefs);
                    $rows = [
                        ['Completed', $completedRefs, 'var(--success)'],
                        ['Pending',   $pendingRefs,   'var(--orange)'],
                        ['Other',     max(0, $totalRefs - $completedRefs - $pendingRefs), 'var(--muted)'],
                    ];
                    foreach ($rows as [$label, $val, $color]):
                        $w = round(($val / $totalForBar) * 100);
                    ?>
                    <div style="margin-bottom:1rem">
                        <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.3rem">
                            <span><?= $label ?></span>
                            <span style="font-weight:700"><?= $val ?> (<?= $w ?>%)</span>
                        </div>
                        <div style="height:8px;background:var(--bg);border-radius:4px;overflow:hidden">
                            <div style="height:100%;background:<?= $color ?>;width:<?= $w ?>%;border-radius:4px"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section-card" style="margin-top:1.25rem">
            <div class="section-head">
                <h2>All Referrals</h2>
                <span style="font-size:.78rem;color:var(--muted)"><?= count($referrals) ?> shown</span>
            </div>
            <form method="GET" class="filter-bar" style="padding:.75rem 1.25rem;margin:0;border-bottom:1px solid var(--divider)">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search referrer / referred…" style="min-width:220px;flex:1">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All status</option>
                    <option value="pending"   <?= $status==='pending'?'selected':'' ?>>Pending</option>
                    <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
                </select>
                <button class="btn btn-primary" type="submit">Filter</button>
                <?php if ($search || $status): ?><a class="btn btn-outline" href="referrals.php">Clear</a><?php endif; ?>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Referrer</th><th>Referred</th><th>Code</th><th>Status</th><th>Signup</th><th>1st Purchase</th></tr></thead>
                    <tbody>
                    <?php if (empty($referrals)): ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">🔗</div><div>No referrals yet.</div></div></td></tr>
                    <?php else: foreach ($referrals as $r): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($r['referrer_name'] ?? '—') ?></div>
                                <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($r['referrer_email'] ?? '') ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($r['referred_name'] ?? '—') ?></div>
                                <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($r['referred_email'] ?? '') ?></div>
                            </td>
                            <td style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($r['referral_code'] ?? '—') ?></td>
                            <td><span class="badge status-<?= htmlspecialchars($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
                            <td style="font-size:.78rem;color:var(--muted)"><?= !empty($r['signup_date']) ? date('d M Y', strtotime($r['signup_date'])) : '—' ?></td>
                            <td style="font-size:.78rem;color:var(--muted)"><?= !empty($r['first_purchase_date']) ? date('d M Y', strtotime($r['first_purchase_date'])) : '—' ?></td>
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
