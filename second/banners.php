<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('banners_view');
$db = getDB();
$activeNav = 'banners';

$flash = null;

// ── Auto-setup: create banners table on first load if missing ────
//    Idempotent — IF NOT EXISTS makes this safe to run on every page load
//    when the table is genuinely missing. No user action required.
$tableExists   = false;
$bannerColumns = [];

try {
    $colsStmt = $db->query("SHOW COLUMNS FROM banners");
    $bannerColumns = array_column($colsStmt->fetchAll(), 'Field');
    $tableExists = !empty($bannerColumns);
} catch (Exception $e) {
    $tableExists = false;
}

if (!$tableExists && can('banners_edit')) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `banners` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `title` VARCHAR(255) DEFAULT NULL,
              `subtitle` VARCHAR(500) DEFAULT NULL,
              `image_url` VARCHAR(500) NOT NULL,
              `link_url` VARCHAR(500) DEFAULT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `sort_order` INT(11) NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_active_order` (`is_active`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        logAction('banners_table_created', 'banners', 0, 'Auto-setup on first load');

        // Re-introspect so the rest of the page treats the table as existing
        $colsStmt      = $db->query("SHOW COLUMNS FROM banners");
        $bannerColumns = array_column($colsStmt->fetchAll(), 'Field');
        $tableExists   = !empty($bannerColumns);
    } catch (Exception $e) {
        $flash = ['err' => 'Banners table create karne mein error: ' . $e->getMessage()];
    }
}

$has = function ($col) use ($bannerColumns) { return in_array($col, $bannerColumns, true); };

// Upload directory (relative to this file)
$uploadDirAbs = __DIR__ . '/uploads/banners';
$uploadDirRel = '/second/uploads/banners';
if (!is_dir($uploadDirAbs)) {
    @mkdir($uploadDirAbs, 0775, true);
}

function _uploadBanner(array $file, string $uploadDirAbs, string $uploadDirRel): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $ok  = ['image/jpeg','image/png','image/webp','image/gif','image/svg+xml'];
    $info = getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? mime_content_type($file['tmp_name']);
    if (!in_array($mime, $ok, true)) {
        throw new Exception('Only image files (jpg, png, webp, gif, svg) allowed');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Image must be under 5 MB');
    }
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $name = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
    $dest = $uploadDirAbs . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Upload failed');
    }
    return $uploadDirRel . '/' . $name;
}

// Edit permission required for any write
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('banners_edit') && $tableExists) {
    $action = $_POST['action'] ?? '';
    if ($action === 'setup_table') { $action = ''; } // already handled above

    try {
        if ($action === 'create') {
            $title    = trim($_POST['title'] ?? '');
            $subtitle = trim($_POST['subtitle'] ?? '');
            $linkUrl  = trim($_POST['link_url'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $sortOrder= (int)($_POST['sort_order'] ?? 0);

            $imagePath = _uploadBanner($_FILES['image'] ?? [], $uploadDirAbs, $uploadDirRel);
            if (!$imagePath) throw new Exception('Banner image required');

            $cols = ['image_url'];
            $vals = [$imagePath];
            $imageCol = $has('image_url') ? 'image_url' : ($has('image') ? 'image' : 'image_url');
            $cols = [$imageCol]; $vals = [$imagePath];

            if ($has('title'))      { $cols[] = 'title';      $vals[] = $title; }
            if ($has('subtitle'))   { $cols[] = 'subtitle';   $vals[] = $subtitle; }
            if ($has('link_url'))   { $cols[] = 'link_url';   $vals[] = $linkUrl; }
            if ($has('is_active'))  { $cols[] = 'is_active';  $vals[] = $isActive; }
            elseif ($has('status')) { $cols[] = 'status';     $vals[] = $isActive ? 'active' : 'inactive'; }
            if ($has('sort_order')) { $cols[] = 'sort_order'; $vals[] = $sortOrder; }
            elseif ($has('display_order')) { $cols[] = 'display_order'; $vals[] = $sortOrder; }
            if ($has('created_at')) { $cols[] = 'created_at'; $vals[] = date('Y-m-d H:i:s'); }

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $colsSql      = '`' . implode('`,`', $cols) . '`';
            $db->prepare("INSERT INTO banners ($colsSql) VALUES ($placeholders)")->execute($vals);

            logAction('banner_created', 'banner', (int)$db->lastInsertId(), "Title: $title");
            $flash = ['ok' => 'Banner uploaded'];
        }

        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($has('is_active')) {
                $db->prepare("UPDATE banners SET is_active = 1 - COALESCE(is_active, 0) WHERE id = ?")->execute([$id]);
            } elseif ($has('status')) {
                $db->prepare("UPDATE banners SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
            }
            logAction('banner_toggled', 'banner', $id);
            $flash = ['ok' => 'Banner status toggled'];
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            // optional: also unlink the file
            $imageCol = $has('image_url') ? 'image_url' : ($has('image') ? 'image' : null);
            if ($imageCol) {
                $row = $db->prepare("SELECT $imageCol FROM banners WHERE id = ?");
                $row->execute([$id]);
                $img = $row->fetchColumn();
                if ($img && strpos($img, '/second/uploads/banners/') !== false) {
                    @unlink(__DIR__ . '/../' . ltrim($img, '/'));
                }
            }
            $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
            logAction('banner_deleted', 'banner', $id);
            $flash = ['ok' => 'Banner deleted'];
        }

        if ($action === 'replace') {
            $id = (int)($_POST['id'] ?? 0);
            $imagePath = _uploadBanner($_FILES['image'] ?? [], $uploadDirAbs, $uploadDirRel);
            if (!$imagePath) throw new Exception('Image required');
            $imageCol = $has('image_url') ? 'image_url' : ($has('image') ? 'image' : 'image_url');
            $db->prepare("UPDATE banners SET `$imageCol` = ? WHERE id = ?")->execute([$imagePath, $id]);
            logAction('banner_replaced', 'banner', $id);
            $flash = ['ok' => 'Banner image replaced'];
        }
    } catch (Exception $e) {
        $flash = ['err' => $e->getMessage()];
    }
}

// Fetch banners (only if table exists)
$banners = [];
if ($tableExists) {
    try {
        $orderCol = $has('sort_order') ? 'sort_order' : ($has('display_order') ? 'display_order' : 'id');
        $banners  = $db->query("SELECT * FROM banners ORDER BY $orderCol ASC, id DESC")->fetchAll();
    } catch (Exception $e) { /* ignore */ }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Homepage Banners — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
.banner-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;margin-top:1rem}
.banner-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm);display:flex;flex-direction:column}
.banner-img{width:100%;aspect-ratio:16/7;object-fit:cover;background:var(--bg)}
.banner-body{padding:.85rem 1rem;flex:1}
.banner-title{font-weight:700;font-size:.95rem;margin-bottom:.25rem}
.banner-sub{font-size:.78rem;color:var(--muted)}
.banner-actions{padding:.7rem 1rem;border-top:1px solid var(--divider);display:flex;gap:.5rem;flex-wrap:wrap;background:var(--surface-2)}
.upload-card{background:var(--surface);border:1px dashed var(--border);border-radius:var(--r-lg);padding:1.5rem;margin-bottom:1.5rem}
</style>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb">Manager Panel / <span>Homepage Banners</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Homepage Banners</h1>
                <p>Frontpage carousel/hero banners — upload, toggle aur delete kar sakte hain</p>
            </div>
        </div>

        <?php if (!empty($flash['ok'])): ?><div class="alert alert-success">✓ <?= htmlspecialchars($flash['ok']) ?></div><?php endif; ?>
        <?php if (!empty($flash['err'])): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($flash['err']) ?></div><?php endif; ?>

        <?php if (!$tableExists): ?>
            <div class="alert alert-warn">
                ⚠️ Banners feature ke liye <code>banners</code> table chahiye, lekin tumhare paas <code>banners_edit</code> permission nahi hai.
                Apne admin se kahokar setup karwao.
            </div>
        <?php endif; ?>

        <?php if ($tableExists && can('banners_edit')): ?>
        <div class="upload-card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:.75rem">+ Add new banner</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="field-row">
                    <div class="field">
                        <label class="label">Banner image (≤ 5 MB, 1600×700 ideal)</label>
                        <input type="file" name="image" accept="image/*" required>
                    </div>
                    <?php if ($has('sort_order') || $has('display_order')): ?>
                    <div class="field">
                        <label class="label">Sort order</label>
                        <input type="number" name="sort_order" value="0" min="0">
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($has('title')): ?>
                <div class="field">
                    <label class="label">Title (optional)</label>
                    <input type="text" name="title" placeholder="e.g. Summer offer 50% off">
                </div>
                <?php endif; ?>
                <?php if ($has('subtitle')): ?>
                <div class="field">
                    <label class="label">Subtitle (optional)</label>
                    <input type="text" name="subtitle" placeholder="Short description shown under title">
                </div>
                <?php endif; ?>
                <?php if ($has('link_url')): ?>
                <div class="field">
                    <label class="label">Link URL (optional)</label>
                    <input type="text" name="link_url" placeholder="https://… or /public/courses.php">
                </div>
                <?php endif; ?>
                <div class="field" style="display:flex;align-items:center;gap:.5rem">
                    <input type="checkbox" id="is_active" name="is_active" checked style="width:auto">
                    <label for="is_active" class="label" style="margin:0">Active immediately</label>
                </div>
                <button class="btn btn-primary" type="submit">Upload Banner</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($tableExists && empty($banners)): ?>
            <div class="card"><div class="empty-state"><div class="empty-state-icon">🎨</div><div>Koi banner abhi nahi hai. Upar se upload karein.</div></div></div>
        <?php elseif ($tableExists): ?>
        <div class="banner-grid">
            <?php foreach ($banners as $b):
                $imageCol = $has('image_url') ? 'image_url' : ($has('image') ? 'image' : null);
                $imgSrc   = $imageCol ? ($b[$imageCol] ?? '') : '';
                $isActive = $has('is_active') ? !empty($b['is_active']) : (($b['status'] ?? 'active') === 'active');
            ?>
            <div class="banner-card">
                <?php if (!empty($imgSrc)): ?>
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" class="banner-img" loading="lazy">
                <?php else: ?>
                    <div class="banner-img" style="display:flex;align-items:center;justify-content:center;color:var(--muted)">No image</div>
                <?php endif; ?>
                <div class="banner-body">
                    <?php if (!empty($b['title'])): ?>
                        <div class="banner-title"><?= htmlspecialchars($b['title']) ?></div>
                    <?php else: ?>
                        <div class="banner-title" style="color:var(--muted)">Banner #<?= (int)$b['id'] ?></div>
                    <?php endif; ?>
                    <?php if (!empty($b['subtitle'])): ?>
                        <div class="banner-sub"><?= htmlspecialchars($b['subtitle']) ?></div>
                    <?php endif; ?>
                    <div style="margin-top:.4rem;display:flex;gap:.4rem;align-items:center">
                        <span class="badge <?= $isActive ? 'badge-success' : 'badge-muted' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                        <?php if ($has('sort_order')): ?><span class="badge badge-muted">Order: <?= (int)$b['sort_order'] ?></span><?php endif; ?>
                    </div>
                </div>
                <?php if (can('banners_edit')): ?>
                <div class="banner-actions">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button class="btn btn-outline" type="submit"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                    </form>
                    <form method="POST" style="display:inline" enctype="multipart/form-data" onclick="this.querySelector('input[type=file]').click(); return false">
                        <input type="hidden" name="action" value="replace">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <input type="file" name="image" accept="image/*" style="display:none" onchange="this.form.submit()">
                        <button class="btn btn-outline" type="button">Replace Image</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this banner permanently?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button class="btn btn-error" type="submit">Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
