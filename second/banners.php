<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('banners_view');
$db = getDB();
$activeNav = 'banners';

$flash = null;

/*
 * IMPORTANT: This page manages the SAME banners that show on the public
 * homepage and in the main admin panel — i.e. the `promotional_banners`
 * table (columns: title, description, image_path, link_url, display_order,
 * is_active). We introspect columns so it keeps working even if the schema
 * differs slightly across environments.
 */
$TABLE = 'promotional_banners';

$tableExists   = false;
$bannerColumns = [];
try {
    $cols = $db->query("SHOW COLUMNS FROM `$TABLE`")->fetchAll();
    $bannerColumns = array_column($cols, 'Field');
    $tableExists = !empty($bannerColumns);
} catch (Exception $e) {
    $tableExists = false;
}

$has = function ($col) use ($bannerColumns) { return in_array($col, $bannerColumns, true); };

// Resolve the actual column names present in this table
$IMG_COL   = $has('image_path') ? 'image_path' : ($has('image_url') ? 'image_url' : ($has('image') ? 'image' : 'image_path'));
$ORDER_COL = $has('display_order') ? 'display_order' : ($has('sort_order') ? 'sort_order' : null);
$ACTIVE_COL= $has('is_active') ? 'is_active' : null;
$STATUS_COL= (!$ACTIVE_COL && $has('status')) ? 'status' : null;
$DESC_COL  = $has('description') ? 'description' : ($has('subtitle') ? 'subtitle' : null);
$HAS_TITLE = $has('title');
$HAS_LINK  = $has('link_url');

// Upload to the SHARED public path so new banners also appear on the homepage.
$uploadDirAbs = __DIR__ . '/../public/uploads/banners';
$uploadRel    = 'public/uploads/banners';   // stored exactly like existing rows
if (!is_dir($uploadDirAbs)) { @mkdir($uploadDirAbs, 0775, true); }

function _uploadBanner(array $file, string $uploadDirAbs, string $uploadRel): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ok   = ['image/jpeg','image/png','image/webp','image/gif'];
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? (function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : null);
    if (!in_array($mime, $ok, true)) {
        throw new Exception('Only image files (jpg, png, webp, gif) allowed');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Image must be under 5 MB');
    }
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $ext  = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';
    $name = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDirAbs . '/' . $name)) {
        throw new Exception('Upload failed');
    }
    return $uploadRel . '/' . $name;   // e.g. public/uploads/banners/banner_xxx.jpg
}

/* ---------- Write actions (need banners_edit) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('banners_edit') && $tableExists) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $title    = trim($_POST['title'] ?? '');
            $desc     = trim($_POST['description'] ?? '');
            $linkUrl  = trim($_POST['link_url'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $order    = (int)($_POST['display_order'] ?? 0);

            $imagePath = _uploadBanner($_FILES['image'] ?? [], $uploadDirAbs, $uploadRel);
            if (!$imagePath) throw new Exception('Banner image required');

            $cols = [$IMG_COL]; $vals = [$imagePath];
            if ($HAS_TITLE)  { $cols[] = 'title';        $vals[] = $title; }
            if ($DESC_COL)   { $cols[] = $DESC_COL;       $vals[] = $desc; }
            if ($HAS_LINK)   { $cols[] = 'link_url';      $vals[] = $linkUrl; }
            if ($ACTIVE_COL) { $cols[] = $ACTIVE_COL;     $vals[] = $isActive; }
            elseif ($STATUS_COL) { $cols[] = $STATUS_COL; $vals[] = $isActive ? 'active' : 'inactive'; }
            if ($ORDER_COL)  { $cols[] = $ORDER_COL;      $vals[] = $order; }
            if ($has('created_at')) { $cols[] = 'created_at'; $vals[] = date('Y-m-d H:i:s'); }

            $ph      = implode(',', array_fill(0, count($cols), '?'));
            $colsSql = '`' . implode('`,`', $cols) . '`';
            $db->prepare("INSERT INTO `$TABLE` ($colsSql) VALUES ($ph)")->execute($vals);
            logAction('banner_created', 'banner', (int)$db->lastInsertId(), "Title: $title");
            $flash = ['ok' => 'Banner uploaded successfully'];
        }

        if ($action === 'update') {
            $id      = (int)($_POST['id'] ?? 0);
            $title   = trim($_POST['title'] ?? '');
            $desc    = trim($_POST['description'] ?? '');
            $linkUrl = trim($_POST['link_url'] ?? '');
            $order   = (int)($_POST['display_order'] ?? 0);

            $set = []; $vals = [];
            if ($HAS_TITLE) { $set[] = '`title` = ?';  $vals[] = $title; }
            if ($DESC_COL)  { $set[] = "`$DESC_COL` = ?"; $vals[] = $desc; }
            if ($HAS_LINK)  { $set[] = '`link_url` = ?'; $vals[] = $linkUrl; }
            if ($ORDER_COL) { $set[] = "`$ORDER_COL` = ?"; $vals[] = $order; }

            // Optional new image
            $newImg = _uploadBanner($_FILES['image'] ?? [], $uploadDirAbs, $uploadRel);
            if ($newImg) { $set[] = "`$IMG_COL` = ?"; $vals[] = $newImg; }

            if ($set) {
                $vals[] = $id;
                $db->prepare("UPDATE `$TABLE` SET " . implode(',', $set) . " WHERE id = ?")->execute($vals);
            }
            logAction('banner_updated', 'banner', $id, "Title: $title");
            $flash = ['ok' => 'Banner updated'];
        }

        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($ACTIVE_COL) {
                $db->prepare("UPDATE `$TABLE` SET `$ACTIVE_COL` = 1 - COALESCE(`$ACTIVE_COL`,0) WHERE id = ?")->execute([$id]);
            } elseif ($STATUS_COL) {
                $db->prepare("UPDATE `$TABLE` SET `$STATUS_COL` = CASE WHEN `$STATUS_COL`='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
            }
            logAction('banner_toggled', 'banner', $id);
            $flash = ['ok' => 'Banner status updated'];
        }

        if ($action === 'replace') {
            $id  = (int)($_POST['id'] ?? 0);
            $img = _uploadBanner($_FILES['image'] ?? [], $uploadDirAbs, $uploadRel);
            if (!$img) throw new Exception('Image required');
            $db->prepare("UPDATE `$TABLE` SET `$IMG_COL` = ? WHERE id = ?")->execute([$img, $id]);
            logAction('banner_replaced', 'banner', $id);
            $flash = ['ok' => 'Banner image replaced'];
        }

        if ($action === 'delete') {
            $id  = (int)($_POST['id'] ?? 0);
            $row = $db->prepare("SELECT `$IMG_COL` AS img FROM `$TABLE` WHERE id = ?");
            $row->execute([$id]);
            $img = $row->fetchColumn();
            $db->prepare("DELETE FROM `$TABLE` WHERE id = ?")->execute([$id]);
            // unlink only files we manage under public/uploads/banners
            if ($img && strpos($img, 'uploads/banners/') !== false) {
                $fp = __DIR__ . '/../' . ltrim($img, '/');
                if (is_file($fp)) @unlink($fp);
            }
            logAction('banner_deleted', 'banner', $id);
            $flash = ['ok' => 'Banner deleted'];
        }
    } catch (Exception $e) {
        $flash = ['err' => $e->getMessage()];
    }
}

/* ---------- Fetch banners ---------- */
$banners = [];
$loadErr = '';
if ($tableExists) {
    try {
        $orderBy = $ORDER_COL ? "`$ORDER_COL` ASC, id DESC" : "id DESC";
        $banners = $db->query("SELECT * FROM `$TABLE` ORDER BY $orderBy")->fetchAll();
    } catch (Exception $e) { $loadErr = $e->getMessage(); }
}
$activeCount = 0;
foreach ($banners as $b) {
    $isAct = $ACTIVE_COL ? !empty($b[$ACTIVE_COL]) : (($b[$STATUS_COL ?? ''] ?? 'active') === 'active');
    if ($isAct) $activeCount++;
}

/* Build a browser src + fallback list from a stored path */
function bannerSrcInfo(string $p): array {
    $p = trim($p);
    if ($p === '') return ['', []];
    if (preg_match('#^https?://#i', $p)) return [$p, []];
    $clean = ltrim($p, '/');
    $noPub = preg_replace('#^public/#', '', $clean);
    $cands = array_values(array_unique([
        '/' . $clean,        // /public/uploads/banners/x   (docroot = repo root)
        '/' . $noPub,        // /uploads/banners/x          (docroot = public/)
        '../' . $clean,      // relative from /second/
    ]));
    return [$cands[0], array_slice($cands, 1)];
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
.banner-img{width:100%;aspect-ratio:16/7;object-fit:cover;background:var(--surface-2)}
.banner-body{padding:.85rem 1rem;flex:1}
.banner-title{font-weight:700;font-size:.95rem;margin-bottom:.25rem}
.banner-sub{font-size:.78rem;color:var(--muted)}
.banner-actions{padding:.7rem 1rem;border-top:1px solid var(--divider);display:flex;gap:.5rem;flex-wrap:wrap;background:var(--surface-2)}
.upload-card{background:var(--surface);border:1px dashed var(--border);border-radius:var(--r-lg);padding:1.5rem;margin-bottom:1.5rem}
/* edit modal */
.modal{position:fixed;inset:0;background:rgba(8,20,12,.5);backdrop-filter:blur(2px);z-index:200;display:none;align-items:center;justify-content:center;padding:1rem}
.modal.open{display:flex}
.modal-box{background:var(--surface);border-radius:var(--r-lg);max-width:520px;width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--divider)}
.modal-head h2{font-size:1rem;font-weight:700}
.modal-pad{padding:1.25rem}
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
                <p><?= count($banners) ?> total &nbsp;•&nbsp; <?= $activeCount ?> active on homepage — yeh wahi banners hain jo website pe dikhte hain</p>
            </div>
        </div>

        <?php if (!empty($flash['ok'])): ?><div class="alert alert-success">✓ <?= htmlspecialchars($flash['ok']) ?></div><?php endif; ?>
        <?php if (!empty($flash['err'])): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($flash['err']) ?></div><?php endif; ?>

        <?php if (!$tableExists): ?>
            <div class="alert alert-warn">
                ⚠️ <code>promotional_banners</code> table nahi mili is database mein.
                <?= $loadErr ? '<br>'.htmlspecialchars($loadErr) : '' ?>
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
                    <?php if ($ORDER_COL): ?>
                    <div class="field">
                        <label class="label">Display order</label>
                        <input type="number" name="display_order" value="0" min="0">
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($HAS_TITLE): ?>
                <div class="field">
                    <label class="label">Title (optional)</label>
                    <input type="text" name="title" placeholder="e.g. Summer offer 50% off">
                </div>
                <?php endif; ?>
                <?php if ($DESC_COL): ?>
                <div class="field">
                    <label class="label">Description (optional)</label>
                    <input type="text" name="description" placeholder="Short description shown under title">
                </div>
                <?php endif; ?>
                <?php if ($HAS_LINK): ?>
                <div class="field">
                    <label class="label">Link URL (optional)</label>
                    <input type="text" name="link_url" placeholder="https://… or /public/courses.php">
                </div>
                <?php endif; ?>
                <div class="field" style="display:flex;align-items:center;gap:.5rem">
                    <input type="checkbox" id="is_active" name="is_active" checked style="width:auto">
                    <label for="is_active" class="label" style="margin:0">Active immediately (show on homepage)</label>
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
                $bid      = (int)$b['id'];
                $imgVal   = (string)($b[$IMG_COL] ?? '');
                [$src, $alts] = bannerSrcInfo($imgVal);
                $isActive = $ACTIVE_COL ? !empty($b[$ACTIVE_COL]) : (($b[$STATUS_COL ?? ''] ?? 'active') === 'active');
                $title    = $HAS_TITLE ? (string)($b['title'] ?? '') : '';
                $desc     = $DESC_COL ? (string)($b[$DESC_COL] ?? '') : '';
                $link     = $HAS_LINK ? (string)($b['link_url'] ?? '') : '';
                $order    = $ORDER_COL ? (int)($b[$ORDER_COL] ?? 0) : 0;
                $editData = htmlspecialchars(json_encode([
                    'id' => $bid, 'title' => $title, 'description' => $desc,
                    'link_url' => $link, 'display_order' => $order,
                ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
            ?>
            <div class="banner-card">
                <?php if ($src !== ''): ?>
                    <img src="<?= htmlspecialchars($src) ?>"
                         data-alts='<?= htmlspecialchars(json_encode($alts), ENT_QUOTES) ?>' data-idx="0"
                         onerror="bannerImgFallback(this)"
                         alt="" class="banner-img" loading="lazy">
                <?php else: ?>
                    <div class="banner-img" style="display:flex;align-items:center;justify-content:center;color:var(--muted)">No image</div>
                <?php endif; ?>
                <div class="banner-body">
                    <?php if ($title !== ''): ?>
                        <div class="banner-title"><?= htmlspecialchars($title) ?></div>
                    <?php else: ?>
                        <div class="banner-title" style="color:var(--muted)">Banner #<?= $bid ?></div>
                    <?php endif; ?>
                    <?php if ($desc !== ''): ?><div class="banner-sub"><?= htmlspecialchars($desc) ?></div><?php endif; ?>
                    <div style="margin-top:.5rem;display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                        <span class="badge <?= $isActive ? 'badge-success' : 'badge-muted' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                        <?php if ($ORDER_COL): ?><span class="badge badge-muted">Order: <?= $order ?></span><?php endif; ?>
                    </div>
                </div>
                <?php if (can('banners_edit')): ?>
                <div class="banner-actions">
                    <button class="btn btn-outline" type="button" onclick='openEdit(<?= $editData ?>)'>Edit</button>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $bid ?>">
                        <button class="btn btn-outline" type="submit"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                    </form>
                    <form method="POST" style="display:inline" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="replace">
                        <input type="hidden" name="id" value="<?= $bid ?>">
                        <input type="file" name="image" accept="image/*" style="display:none" onchange="this.form.submit()">
                        <button class="btn btn-outline" type="button" onclick="this.previousElementSibling.click()">Replace Image</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this banner permanently?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $bid ?>">
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

<?php if ($tableExists && can('banners_edit')): ?>
<!-- Edit details modal -->
<div class="modal" id="editModal">
  <div class="modal-box">
    <form method="POST">
      <div class="modal-head">
        <h2>Edit Banner</h2>
        <button type="button" onclick="closeEdit()" style="font-size:1.3rem;color:var(--muted)">&times;</button>
      </div>
      <div class="modal-pad">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="e_id">
        <?php if ($HAS_TITLE): ?>
        <div class="field"><label class="label">Title</label><input type="text" name="title" id="e_title"></div>
        <?php endif; ?>
        <?php if ($DESC_COL): ?>
        <div class="field"><label class="label">Description</label><input type="text" name="description" id="e_desc"></div>
        <?php endif; ?>
        <?php if ($HAS_LINK): ?>
        <div class="field"><label class="label">Link URL</label><input type="text" name="link_url" id="e_link"></div>
        <?php endif; ?>
        <?php if ($ORDER_COL): ?>
        <div class="field"><label class="label">Display order</label><input type="number" name="display_order" id="e_order" min="0"></div>
        <?php endif; ?>
        <div style="display:flex;gap:.6rem;margin-top:.5rem">
          <button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Save Changes</button>
          <button class="btn btn-outline" type="button" onclick="closeEdit()">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/_layout_js.php'; ?>
<script>
/* Image fallback: try alternate path variants before giving up */
function bannerImgFallback(el){
  let alts = [];
  try { alts = JSON.parse(el.dataset.alts || '[]'); } catch(e){}
  let i = parseInt(el.dataset.idx || '0', 10);
  if (i < alts.length){ el.dataset.idx = String(i+1); el.src = alts[i]; }
  else {
    el.onerror = null;
    el.src = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 175%22%3E%3Crect fill=%22%23e8f0e8%22 width=%22400%22 height=%22175%22/%3E%3Ctext fill=%22%2399ab9e%22 font-family=%22sans-serif%22 font-size=%2216%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3EImage not found%3C/text%3E%3C/svg%3E';
  }
}

/* Edit modal */
const editModal = document.getElementById('editModal');
function openEdit(b){
  if(!editModal) return;
  const set = (id,v)=>{ const el=document.getElementById(id); if(el) el.value = v ?? ''; };
  set('e_id', b.id); set('e_title', b.title); set('e_desc', b.description);
  set('e_link', b.link_url); set('e_order', b.display_order);
  editModal.classList.add('open');
}
function closeEdit(){ if(editModal) editModal.classList.remove('open'); }
if(editModal){ editModal.addEventListener('click', e => { if(e.target === editModal) closeEdit(); }); }
</script>
</body>
</html>
