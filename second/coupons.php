<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('coupons_view');
$db = getDB();
$activeNav = 'coupons';

$flash = null;

// Toggle / delete actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'toggle' && $id && can('coupons_edit')) {
            $db->prepare("UPDATE coupons SET is_active = 1 - COALESCE(is_active, 0) WHERE id = ?")->execute([$id]);
            logAction('coupon_toggled', 'coupon', $id);
            $flash = ['ok' => 'Coupon status toggled'];
        }
        if ($action === 'delete' && $id && can('coupons_delete')) {
            // optional: also clear related coupon_usage
            try { $db->prepare("DELETE FROM coupon_usage WHERE coupon_id = ?")->execute([$id]); } catch (Exception $e) {}
            $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
            logAction('coupon_deleted', 'coupon', $id);
            $flash = ['ok' => 'Coupon deleted'];
        }
    } catch (Exception $e) {
        $flash = ['err' => $e->getMessage()];
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT * FROM coupons WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND code LIKE ?"; $params[] = "%$search%"; }
if ($status === 'active')   { $sql .= " AND is_active = 1"; }
if ($status === 'inactive') { $sql .= " AND COALESCE(is_active,0) = 0"; }
if ($status === 'expired')  { $sql .= " AND valid_until IS NOT NULL AND valid_until < NOW()"; }
$sql .= " ORDER BY created_at DESC LIMIT 200";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $coupons = $stmt->fetchAll();
} catch (Exception $e) {
    $coupons = [];
    $flash   = $flash ?? ['err' => $e->getMessage()];
}

// Stats
try {
    $totalCoupons   = (int)$db->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
    $activeCoupons  = (int)$db->query("SELECT COUNT(*) FROM coupons WHERE is_active=1 AND (valid_until IS NULL OR valid_until >= NOW())")->fetchColumn();
    $usedCoupons    = (int)$db->query("SELECT COUNT(*) FROM coupons WHERE used_count > 0")->fetchColumn();
    $totalUses      = (int)$db->query("SELECT COALESCE(SUM(used_count),0) FROM coupons")->fetchColumn();
} catch (Exception $e) {
    $totalCoupons = $activeCoupons = $usedCoupons = $totalUses = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Coupons — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
.code-pill{font-family:'SF Mono',Menlo,monospace;font-size:.82rem;background:var(--bg);border:1px dashed var(--border);padding:.2rem .55rem;border-radius:6px;font-weight:600}
</style>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb">Manager Panel / <span>Coupons</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Coupons</h1>
                <p>Discount coupons banao, edit karo aur usage track karo</p>
            </div>
            <?php if (can('coupons_create')): ?>
                <a href="coupons_form.php" class="btn btn-primary">+ New Coupon</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($flash['ok'])): ?><div class="alert alert-success">✓ <?= htmlspecialchars($flash['ok']) ?></div><?php endif; ?>
        <?php if (!empty($flash['err'])): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($flash['err']) ?></div><?php endif; ?>

        <div class="kpi-grid">
            <div class="kpi-card accent"><div class="kpi-label">Total Coupons</div><div class="kpi-value"><?= $totalCoupons ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Active &amp; Valid</div><div class="kpi-value" style="color:var(--success)"><?= $activeCoupons ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Used at least once</div><div class="kpi-value"><?= $usedCoupons ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Total Uses</div><div class="kpi-value"><?= $totalUses ?></div></div>
        </div>

        <form method="GET" class="filter-bar">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search coupon code…" style="min-width:220px;flex:1">
            <select name="status" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="active"   <?= $status==='active'?'selected':'' ?>>Active &amp; valid</option>
                <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                <option value="expired"  <?= $status==='expired'?'selected':'' ?>>Expired</option>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <?php if ($search || $status): ?><a href="coupons.php" class="btn btn-outline">Clear</a><?php endif; ?>
        </form>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Min Purchase</th>
                            <th>Used / Limit</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">🎟️</div><div>No coupons yet. Create one above.</div></div></td></tr>
                        <?php else: foreach ($coupons as $c):
                            $isExpired = !empty($c['valid_until']) && strtotime($c['valid_until']) < time();
                            $usagePct  = !empty($c['usage_limit']) && $c['usage_limit'] > 0 ? min(100, round(($c['used_count'] / $c['usage_limit']) * 100)) : 0;
                        ?>
                            <tr>
                                <td>
                                    <span class="code-pill"><?= htmlspecialchars($c['code']) ?></span>
                                    <?php if (!empty($c['is_referral_coupon'])): ?>
                                        <span class="badge badge-info" style="margin-left:.4rem">Referral</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($c['discount_type'] ?? 'percentage') === 'percentage'): ?>
                                        <strong><?= number_format((float)$c['discount_value'], 1) ?>%</strong>
                                        <?php if (!empty($c['max_discount'])): ?>
                                            <div style="font-size:.72rem;color:var(--muted)">max ₹<?= number_format((float)$c['max_discount'], 0) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <strong>₹<?= number_format((float)$c['discount_value'], 0) ?></strong>
                                        <div style="font-size:.72rem;color:var(--muted)">flat</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= empty($c['min_purchase']) ? '—' : '₹' . number_format((float)$c['min_purchase'], 0) ?></td>
                                <td>
                                    <strong><?= (int)$c['used_count'] ?></strong>
                                    <?php if (!empty($c['usage_limit'])): ?>
                                        <span style="color:var(--muted)"> / <?= (int)$c['usage_limit'] ?></span>
                                        <div style="height:4px;background:var(--bg);border-radius:2px;margin-top:.25rem;overflow:hidden">
                                            <div style="height:100%;background:var(--primary);width:<?= $usagePct ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.78rem;color:var(--muted)">
                                    <?= empty($c['valid_until']) ? 'No expiry' : date('d M Y', strtotime($c['valid_until'])) ?>
                                </td>
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span class="badge badge-error">Expired</span>
                                    <?php elseif (!empty($c['is_active'])): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                                        <?php if (can('coupons_edit')): ?>
                                            <a href="coupons_form.php?id=<?= (int)$c['id'] ?>" class="btn btn-outline" style="font-size:.75rem;padding:.35rem .7rem">Edit</a>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" class="btn btn-outline" style="font-size:.75rem;padding:.35rem .7rem">Toggle</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (can('coupons_delete')): ?>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this coupon? Iska usage history bhi remove ho jayega.')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" class="btn btn-error" style="font-size:.75rem;padding:.35rem .7rem">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
