<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
$db = getDB();
$activeNav = 'coupons';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Permission gates
if ($isEdit) checkPermission('coupons_edit');
else        checkPermission('coupons_create');

$flash = null;
$coupon = [
    'code' => '',
    'discount_type' => 'percentage',
    'discount_value' => '',
    'min_purchase' => '',
    'max_discount' => '',
    'usage_limit' => '',
    'valid_from' => date('Y-m-d'),
    'valid_until' => '',
    'is_active' => 1,
];

// Load existing on edit
if ($isEdit) {
    try {
        $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            header('Location: coupons.php?notfound=1'); exit;
        }
        $coupon = array_merge($coupon, $row);
        // Format dates for input[type=date]
        $coupon['valid_from']  = !empty($coupon['valid_from'])  ? date('Y-m-d', strtotime($coupon['valid_from']))  : '';
        $coupon['valid_until'] = !empty($coupon['valid_until']) ? date('Y-m-d', strtotime($coupon['valid_until'])) : '';
    } catch (Exception $e) {
        $flash = ['err' => $e->getMessage()];
    }
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code         = strtoupper(trim($_POST['code'] ?? ''));
    $type         = $_POST['discount_type'] === 'fixed' ? 'fixed' : 'percentage';
    $value        = (float)($_POST['discount_value'] ?? 0);
    $minPurchase  = $_POST['min_purchase']  !== '' ? (float)$_POST['min_purchase']  : null;
    $maxDiscount  = $_POST['max_discount']  !== '' ? (float)$_POST['max_discount']  : null;
    $usageLimit   = $_POST['usage_limit']   !== '' ? (int)$_POST['usage_limit']     : null;
    $validFrom    = !empty($_POST['valid_from'])  ? $_POST['valid_from']  : date('Y-m-d');
    $validUntil   = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $isActive     = isset($_POST['is_active']) ? 1 : 0;

    // Validate
    $errors = [];
    if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
        $errors[] = 'Code 3–40 chars (A–Z, 0–9, _, -) hona chahiye';
    }
    if ($value <= 0) {
        $errors[] = 'Discount value zero se zyada honi chahiye';
    }
    if ($type === 'percentage' && $value > 100) {
        $errors[] = 'Percentage 100 se zyada nahi ho sakti';
    }

    if (empty($errors)) {
        try {
            // Code uniqueness check
            $check = $db->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
            $check->execute([$code, $isEdit ? $id : 0]);
            if ($check->fetchColumn()) {
                $errors[] = 'Yeh coupon code already exist karta hai';
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $db->prepare("
                    UPDATE coupons SET
                        code = ?, discount_type = ?, discount_value = ?,
                        min_purchase = ?, max_discount = ?, usage_limit = ?,
                        valid_from = ?, valid_until = ?, is_active = ?
                    WHERE id = ?
                ")->execute([$code, $type, $value, $minPurchase, $maxDiscount, $usageLimit, $validFrom, $validUntil, $isActive, $id]);
                logAction('coupon_updated', 'coupon', $id, "Code: $code");
                $flash = ['ok' => 'Coupon updated'];
                // Refresh data
                $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?"); $stmt->execute([$id]);
                $coupon = array_merge($coupon, $stmt->fetch() ?: []);
                $coupon['valid_from']  = !empty($coupon['valid_from'])  ? date('Y-m-d', strtotime($coupon['valid_from']))  : '';
                $coupon['valid_until'] = !empty($coupon['valid_until']) ? date('Y-m-d', strtotime($coupon['valid_until'])) : '';
            } else {
                $db->prepare("
                    INSERT INTO coupons
                    (code, discount_type, discount_value, min_purchase, max_discount,
                     usage_limit, used_count, valid_from, valid_until, is_active,
                     is_referral_coupon, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0, ?, NOW())
                ")->execute([$code, $type, $value, $minPurchase, $maxDiscount, $usageLimit, $validFrom, $validUntil, $isActive, $_SESSION['user_id']]);
                $newId = (int)$db->lastInsertId();
                logAction('coupon_created', 'coupon', $newId, "Code: $code");
                header("Location: coupons_form.php?id={$newId}&created=1"); exit;
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $flash = ['err' => implode(' • ', $errors)];
        // Repopulate form with submitted values
        $coupon = array_merge($coupon, [
            'code'           => $code,
            'discount_type'  => $type,
            'discount_value' => $value,
            'min_purchase'   => $minPurchase,
            'max_discount'   => $maxDiscount,
            'usage_limit'    => $usageLimit,
            'valid_from'     => $validFrom,
            'valid_until'    => $validUntil,
            'is_active'      => $isActive,
        ]);
    }
}

if (isset($_GET['created'])) $flash = ['ok' => 'Coupon created'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $isEdit ? 'Edit' : 'New' ?> Coupon — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb"><a href="coupons.php" style="color:inherit">Manager Panel / Coupons</a> / <span><?= $isEdit ? 'Edit' : 'New' ?></span></div>
    </header>

    <main class="content" style="max-width:760px">
        <div class="page-header">
            <div>
                <h1><?= $isEdit ? 'Edit coupon' : 'Create new coupon' ?></h1>
                <p><?= $isEdit ? 'Update coupon settings' : 'Discount details fill karo aur publish karo' ?></p>
            </div>
            <a href="coupons.php" class="btn btn-outline">← Back</a>
        </div>

        <?php if (!empty($flash['ok'])): ?><div class="alert alert-success">✓ <?= htmlspecialchars($flash['ok']) ?></div><?php endif; ?>
        <?php if (!empty($flash['err'])): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($flash['err']) ?></div><?php endif; ?>

        <div class="card" style="padding:1.5rem">
            <form method="POST">
                <div class="field">
                    <label class="label">Coupon Code *</label>
                    <input type="text" name="code" required maxlength="40" placeholder="WELCOME25"
                           style="font-family:monospace;text-transform:uppercase"
                           value="<?= htmlspecialchars($coupon['code']) ?>">
                    <div style="font-size:.74rem;color:var(--muted);margin-top:.3rem">3–40 chars, only A-Z, 0-9, _ and -</div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="label">Discount Type *</label>
                        <select name="discount_type">
                            <option value="percentage" <?= $coupon['discount_type']==='percentage'?'selected':'' ?>>Percentage (%)</option>
                            <option value="fixed"      <?= $coupon['discount_type']==='fixed'?'selected':'' ?>>Fixed Amount (₹)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label">Discount Value *</label>
                        <input type="number" name="discount_value" required min="0" step="0.01"
                               value="<?= htmlspecialchars((string)$coupon['discount_value']) ?>"
                               placeholder="e.g. 25 for 25% off OR 500 for ₹500 off">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="label">Min Purchase Amount (₹, optional)</label>
                        <input type="number" name="min_purchase" min="0" step="0.01"
                               value="<?= htmlspecialchars((string)($coupon['min_purchase'] ?? '')) ?>"
                               placeholder="0 = no minimum">
                    </div>
                    <div class="field">
                        <label class="label">Max Discount Cap (₹, only for %)</label>
                        <input type="number" name="max_discount" min="0" step="0.01"
                               value="<?= htmlspecialchars((string)($coupon['max_discount'] ?? '')) ?>"
                               placeholder="Empty = no cap">
                    </div>
                </div>

                <div class="field">
                    <label class="label">Usage Limit (total redemptions)</label>
                    <input type="number" name="usage_limit" min="0"
                           value="<?= htmlspecialchars((string)($coupon['usage_limit'] ?? '')) ?>"
                           placeholder="Empty = unlimited">
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="label">Valid From</label>
                        <input type="date" name="valid_from"
                               value="<?= htmlspecialchars($coupon['valid_from']) ?>">
                    </div>
                    <div class="field">
                        <label class="label">Valid Until (empty = never expires)</label>
                        <input type="date" name="valid_until"
                               value="<?= htmlspecialchars($coupon['valid_until']) ?>">
                    </div>
                </div>

                <div class="field" style="display:flex;align-items:center;gap:.5rem">
                    <input type="checkbox" id="is_active" name="is_active" <?= !empty($coupon['is_active'])?'checked':'' ?> style="width:auto">
                    <label for="is_active" class="label" style="margin:0">Active</label>
                </div>

                <div style="display:flex;gap:.6rem;margin-top:1.25rem;flex-wrap:wrap">
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? '💾 Save Changes' : '+ Create Coupon' ?></button>
                    <a href="coupons.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>

        <?php if ($isEdit): ?>
        <?php
        // Show usage details for this coupon
        $uses = [];
        try {
            $stmt = $db->prepare("
                SELECT cu.*, u.name AS user_name, u.email
                FROM coupon_usage cu
                LEFT JOIN users u ON u.id = cu.user_id
                WHERE cu.coupon_id = ?
                ORDER BY cu.used_at DESC LIMIT 30
            ");
            $stmt->execute([$id]);
            $uses = $stmt->fetchAll();
        } catch (Exception $e) {}
        ?>
        <div class="section-card" style="margin-top:1.5rem">
            <div class="section-head">
                <h2>Recent Uses (<?= count($uses) ?>)</h2>
            </div>
            <?php if (empty($uses)): ?>
                <div class="empty-state" style="padding:1.5rem">Abhi koi use nahi hua</div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>User</th><th>Discount Applied</th><th>Used At</th></tr></thead>
                    <tbody>
                    <?php foreach ($uses as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['user_name'] ?? '—') ?><div style="font-size:.74rem;color:var(--muted)"><?= htmlspecialchars($u['email'] ?? '') ?></div></td>
                            <td>₹<?= number_format((float)$u['discount_amount'], 2) ?></td>
                            <td style="font-size:.78rem;color:var(--muted)"><?= date('d M Y, H:i', strtotime($u['used_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
