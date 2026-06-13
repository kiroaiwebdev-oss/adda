<?php
require_once __DIR__ . '/manager_auth.php';

// Logout handler (consistent with other manager pages)
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: manager_login.php');
    exit;
}

requireManagerAccess();
$db = getDB();

$role = $_SESSION['user_role'] ?? 'manager';
$name = $_SESSION['user_name'] ?? 'Manager';
$isAdmin = ($role === 'admin');

$TABLE      = 'promotional_banners';
$UPLOAD_DIR = __DIR__ . '/../public/uploads/banners/';   // physical path
$UPLOAD_REL = 'public/uploads/banners/';                 // stored/displayed path

$flash = ['type' => '', 'msg' => ''];

/* ----------------------------------------------------------------------------
 *  Image upload helper
 * -------------------------------------------------------------------------- */
function uploadBannerImage(array $file, string $dir, string $rel): array {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed, true)) {
        return ['ok' => false, 'msg' => 'Invalid file type. Only JPG, PNG, WEBP allowed.'];
    }
    if ($file['size'] > $maxSize) {
        return ['ok' => false, 'msg' => 'File size must be less than 5MB.'];
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $filename = 'banner_' . uniqid() . '_' . time() . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return ['ok' => true, 'path' => $rel . $filename];
    }
    return ['ok' => false, 'msg' => 'Failed to upload file.'];
}

/* ----------------------------------------------------------------------------
 *  Handle POST actions (create / update / toggle / delete)
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $db->prepare("UPDATE `$TABLE` SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
                logAction('Toggled banner status', 'banner', $id);
                $flash = ['type' => 'success', 'msg' => 'Banner status updated.'];
            }
        } elseif ($action === 'delete') {
            // Delete is restricted to super admin only
            if (!$isAdmin) {
                $flash = ['type' => 'error', 'msg' => 'Delete is restricted for managers.'];
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    $row = $db->prepare("SELECT image_path FROM `$TABLE` WHERE id = ?");
                    $row->execute([$id]);
                    $banner = $row->fetch();
                    $db->prepare("DELETE FROM `$TABLE` WHERE id = ?")->execute([$id]);
                    if ($banner && !empty($banner['image_path'])) {
                        $fp = __DIR__ . '/../' . $banner['image_path'];
                        if (is_file($fp)) @unlink($fp);
                    }
                    logAction('Deleted banner', 'banner', $id);
                    $flash = ['type' => 'success', 'msg' => 'Banner deleted.'];
                }
            }
        } elseif ($action === 'create' || $action === 'update') {
            $id          = (int)($_POST['id'] ?? 0);
            $title       = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $link_url    = trim($_POST['link_url'] ?? '');
            $order       = (int)($_POST['display_order'] ?? 0);
            $active      = isset($_POST['is_active']) ? 1 : 0;

            $imagePath = null;
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
                $up = uploadBannerImage($_FILES['banner_image'], $UPLOAD_DIR, $UPLOAD_REL);
                if (!$up['ok']) {
                    $flash = ['type' => 'error', 'msg' => $up['msg']];
                } else {
                    $imagePath = $up['path'];
                }
            }

            if ($flash['type'] !== 'error') {
                if ($action === 'create') {
                    if (!$imagePath) {
                        $flash = ['type' => 'error', 'msg' => 'Banner image is required.'];
                    } else {
                        $sql = "INSERT INTO `$TABLE`
                                (title, description, image_path, link_url, display_order, is_active)
                                VALUES (?,?,?,?,?,?)";
                        $db->prepare($sql)->execute([$title, $description, $imagePath, $link_url, $order, $active]);
                        logAction('Created banner', 'banner', (int)$db->lastInsertId());
                        $flash = ['type' => 'success', 'msg' => 'Banner created successfully.'];
                    }
                } else { // update
                    if (!$imagePath) {
                        // keep existing image
                        $ex = $db->prepare("SELECT image_path FROM `$TABLE` WHERE id = ?");
                        $ex->execute([$id]);
                        $row = $ex->fetch();
                        $imagePath = $row['image_path'] ?? '';
                    }
                    $sql = "UPDATE `$TABLE` SET
                                title = ?, description = ?, image_path = ?,
                                link_url = ?, display_order = ?, is_active = ?
                            WHERE id = ?";
                    $db->prepare($sql)->execute([$title, $description, $imagePath, $link_url, $order, $active, $id]);
                    logAction('Updated banner', 'banner', $id);
                    $flash = ['type' => 'success', 'msg' => 'Banner updated successfully.'];
                }
            }
        }
    } catch (Throwable $e) {
        $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
    }
}

/* ----------------------------------------------------------------------------
 *  Load banners
 * -------------------------------------------------------------------------- */
$banners      = [];
$activeCount  = 0;
$loadError    = '';
try {
    $banners = $db->query("SELECT * FROM `$TABLE` ORDER BY display_order ASC, created_at DESC")
                  ->fetchAll();
    foreach ($banners as $b) {
        if ((int)($b['is_active'] ?? 0) === 1) $activeCount++;
    }
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Banners — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f7f6f2;--surface:#fff;--border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;--text:#28251d;--muted:#7a7974;--faint:#bab9b4;--primary:#01696f;--primary-h:#0c4e54;--success:#437a22;--error:#a12c7b;--warning:#e67e22;--orange:#da7101;--r-lg:.75rem;--r-md:.5rem;--shadow-sm:0 1px 2px oklch(0.2 0.01 80/0.06);--shadow-md:0 4px 12px oklch(0.2 0.01 80/0.08);--t:180ms cubic-bezier(.16,1,.3,1)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:oklch(1 0 0/0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--faint:#5a5957;--primary:#4f98a3;--primary-h:#227f8b;--success:#6daa45;--error:#d163a7;--warning:#e67e22;--orange:#fdab43}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}
.app{display:flex;min-height:100dvh}

/* Sidebar */
.sidebar{width:280px;background:var(--surface);border-right:1px solid var(--border);padding:1.5rem 1rem;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:.6rem;margin-bottom:1.5rem;padding:0 .5rem}
.sidebar-logo svg{width:34px;height:34px}
.logo-text{font-size:1.1rem;font-weight:700;letter-spacing:-0.3px;color:var(--text)}
.logo-text span{color:var(--primary);font-weight:600}
.sidebar-user{background:var(--bg);border-radius:var(--r-lg);padding:1rem;margin-bottom:1.5rem;border:1px solid var(--border)}
.user-badge{display:inline-block;padding:.2rem .6rem;background:var(--primary);color:#fff;border-radius:999px;font-size:.65rem;font-weight:600;margin-bottom:.5rem}
.user-name{font-weight:600;font-size:.85rem;color:var(--text)}
.user-email-sm{font-size:.7rem;color:var(--muted);margin-top:.2rem}
.nav-section{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:1.2rem 0 .5rem .5rem}
.nav-section:first-of-type{margin-top:0}
.nav-item{display:flex;align-items:center;gap:.6rem;padding:.6rem .8rem;border-radius:var(--r-md);color:var(--text);text-decoration:none;font-size:.85rem;font-weight:500;transition:all var(--t)}
.nav-item svg{width:1.2rem;height:1.2rem;color:var(--muted);flex-shrink:0}
.nav-item:hover{background:var(--bg);color:var(--primary)}
.nav-item.active{background:oklch(from var(--primary) l c h/0.1);color:var(--primary);font-weight:600}
.nav-item.active svg{color:var(--primary)}
.sidebar-footer{display:flex;gap:.5rem;margin-top:auto;padding-top:1.5rem}
.btn-sm{padding:.45rem .8rem;border-radius:var(--r-md);font-size:.75rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;border:none;cursor:pointer;transition:all var(--t)}
.btn-danger{background:oklch(from var(--error) l c h/0.12);color:var(--error)}
.btn-danger:hover{background:var(--error);color:#fff}
.btn-ghost{background:transparent;color:var(--muted)}
.btn-ghost:hover{background:var(--bg);color:var(--text)}

/* Main */
.main{flex:1;padding:1.5rem;min-width:0}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem}
h1{font-size:1.3rem;font-weight:700}
.sub{font-size:.8rem;color:var(--muted);margin-top:.2rem}
.btn-primary{background:var(--primary);color:#fff;padding:.55rem 1.1rem;border-radius:var(--r-md);font-size:.85rem;font-weight:600;border:none;cursor:pointer;transition:background var(--t);display:inline-flex;align-items:center;gap:.45rem}
.btn-primary:hover{background:var(--primary-h)}
.pill{font-size:.78rem;color:var(--muted);background:oklch(from var(--primary) l c h/0.08);padding:.4rem .9rem;border-radius:9999px}

/* Flash + notices */
.flash{padding:.7rem 1rem;border-radius:var(--r-md);font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.flash-success{background:oklch(from var(--success) l c h/0.12);color:var(--success);border:1px solid oklch(from var(--success) l c h/0.25)}
.flash-error{background:oklch(from var(--error) l c h/0.1);color:var(--error);border:1px solid oklch(from var(--error) l c h/0.25)}
.warn-notice{background:oklch(from var(--orange) l c h/0.08);border:1px solid oklch(from var(--orange) l c h/0.25);border-radius:var(--r-md);padding:.65rem 1rem;font-size:.82rem;color:var(--orange);margin-bottom:1rem}

/* Banner grid */
.banner-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem}
.banner-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm);transition:box-shadow var(--t)}
.banner-card:hover{box-shadow:var(--shadow-md)}
.banner-thumb{position:relative;height:160px;background:var(--bg)}
.banner-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.status-chip{position:absolute;top:.65rem;left:.65rem;padding:.2rem .6rem;border-radius:9999px;font-size:.7rem;font-weight:700;color:#fff}
.chip-active{background:var(--success)}
.chip-inactive{background:var(--faint)}
.order-chip{position:absolute;top:.65rem;right:.65rem;padding:.2rem .6rem;border-radius:9999px;font-size:.7rem;font-weight:700;background:rgba(0,0,0,.6);color:#fff}
.banner-body{padding:1rem}
.banner-title{font-weight:700;font-size:.95rem;margin-bottom:.25rem}
.banner-desc{font-size:.8rem;color:var(--muted);margin-bottom:.85rem;min-height:34px}
.banner-actions{display:flex;gap:.5rem;flex-wrap:wrap}
.act{padding:.4rem .75rem;border-radius:var(--r-md);font-size:.78rem;font-weight:600;border:none;cursor:pointer}
.act-edit{background:oklch(from var(--primary) l c h/0.12);color:var(--primary)}
.act-edit:hover{background:var(--primary);color:#fff}
.act-toggle{background:oklch(from var(--warning) l c h/0.14);color:var(--warning)}
.act-toggle:hover{background:var(--warning);color:#fff}
.act-del{background:oklch(from var(--error) l c h/0.12);color:var(--error)}
.act-del:hover{background:var(--error);color:#fff}
.empty{text-align:center;padding:3rem 1rem;color:var(--muted);background:var(--surface);border:1px dashed var(--border);border-radius:var(--r-lg)}

/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(2px);z-index:100;display:none;align-items:center;justify-content:center;padding:1rem}
.modal.open{display:flex}
.modal-box{background:var(--surface);border-radius:var(--r-lg);max-width:560px;width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.25rem;border-bottom:1px solid var(--divider);position:sticky;top:0;background:var(--surface)}
.modal-head h2{font-size:1rem;font-weight:700}
.modal-body{padding:1.25rem;display:flex;flex-direction:column;gap:1rem}
label{font-size:.8rem;font-weight:600;display:block;margin-bottom:.35rem}
input[type=text],input[type=url],input[type=number],textarea{width:100%;padding:.55rem .85rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--bg);color:var(--text);font:inherit;font-size:.85rem}
input:focus,textarea:focus{outline:none;border-color:var(--primary)}
textarea{resize:vertical;min-height:64px}
.file-row{display:flex;align-items:center;gap:.75rem}
.preview{width:120px;height:64px;object-fit:cover;border-radius:var(--r-md);border:1px solid var(--border);display:none}
.check-row{display:flex;align-items:center;gap:.55rem;background:var(--bg);padding:.65rem .85rem;border-radius:var(--r-md)}
.check-row input{width:18px;height:18px}
.modal-foot{display:flex;gap:.6rem;padding:1.1rem 1.25rem;border-top:1px solid var(--divider)}
.btn-cancel{padding:.55rem 1.1rem;border:1.5px solid var(--border);border-radius:var(--r-md);font-size:.85rem;font-weight:600;background:transparent;color:var(--text)}
.hint{font-size:.72rem;color:var(--faint);margin-top:.25rem}
</style>
</head>
<body>
<div class="app">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="7" fill="var(--primary)"/>
        <path d="M9 23L16 9L23 23" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M12 19h8" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
      <div class="logo-text">Internship<span>Adda</span></div>
    </div>

    <div class="sidebar-user">
      <div class="user-badge"><?= htmlspecialchars(ucfirst($role)) ?></div>
      <div class="user-name"><?= htmlspecialchars($name) ?></div>
      <div class="user-email-sm">Platform Manager</div>
    </div>

    <nav>
      <div class="nav-section">Overview</div>
      <a href="manager_dashboard.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>

      <div class="nav-section">Content</div>
      <?php if(can('courses_view')): ?>
      <a href="courses.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Courses
      </a>
      <?php endif; ?>
      <?php if(can('internships_view')): ?>
      <a href="internships.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        Internships
      </a>
      <?php endif; ?>
      <a href="banners.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
        Banners
      </a>

      <div class="nav-section">Logs</div>
      <a href="activity_log.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Activity Log
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="?logout=1" class="btn-sm btn-danger">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
      <button data-theme-toggle class="btn-sm btn-ghost" aria-label="Toggle theme" style="padding:.45rem .6rem">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </div>
  </aside>

  <!-- Main -->
  <main class="main">
    <div class="page-header">
      <div>
        <h1>Promotional Banners</h1>
        <div class="sub"><?= count($banners) ?> total &nbsp;•&nbsp; <?= $activeCount ?> active on homepage</div>
      </div>
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <span class="pill"><?= $isAdmin ? 'Full access' : 'View + Edit • Delete restricted' ?></span>
        <button class="btn-primary" onclick="openCreate()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Banner
        </button>
      </div>
    </div>

    <?php if($flash['msg']): ?>
      <div class="flash flash-<?= $flash['type']==='error'?'error':'success' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if($loadError): ?>
      <div class="warn-notice">⚠️ Banners load nahi ho paaye: <?= htmlspecialchars($loadError) ?></div>
    <?php endif; ?>

    <?php if(empty($banners)): ?>
      <div class="empty">
        <p style="font-weight:600;margin-bottom:.35rem">No banners found</p>
        <p style="font-size:.85rem">Add your first promotional banner using the button above.</p>
      </div>
    <?php else: ?>
      <div class="banner-grid">
        <?php foreach($banners as $b):
            $bid    = (int)$b['id'];
            $active = (int)($b['is_active'] ?? 0) === 1;
            $img    = $b['image_path'] ?? '';
            $jsonB  = htmlspecialchars(json_encode([
                        'id'            => $bid,
                        'title'         => $b['title'] ?? '',
                        'description'   => $b['description'] ?? '',
                        'link_url'      => $b['link_url'] ?? '',
                        'display_order' => (int)($b['display_order'] ?? 0),
                        'is_active'     => $active ? 1 : 0,
                        'image_path'    => $img,
                      ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
        ?>
        <div class="banner-card">
          <div class="banner-thumb">
            <img src="/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($b['title'] ?? 'Banner') ?>"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 200%22%3E%3Crect fill=%22%23e5e5e5%22 width=%22400%22 height=%22200%22/%3E%3Ctext fill=%22%23999%22 font-size=%2218%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22%3ENo Image%3C/text%3E%3C/svg%3E'">
            <span class="status-chip <?= $active?'chip-active':'chip-inactive' ?>"><?= $active?'Active':'Hidden' ?></span>
            <span class="order-chip">Order: <?= (int)($b['display_order'] ?? 0) ?></span>
          </div>
          <div class="banner-body">
            <div class="banner-title"><?= htmlspecialchars($b['title'] ?: 'Untitled Banner') ?></div>
            <div class="banner-desc"><?= htmlspecialchars(mb_strimwidth($b['description'] ?? '', 0, 90, '…')) ?: '<span style="color:var(--faint)">No description</span>' ?></div>
            <div class="banner-actions">
              <button class="act act-edit" onclick='openEdit(<?= $jsonB ?>)'>Edit</button>
              <form method="POST" style="display:inline" onsubmit="return true">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $bid ?>">
                <button type="submit" class="act act-toggle"><?= $active?'Hide':'Show' ?></button>
              </form>
              <?php if($isAdmin): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this banner permanently?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $bid ?>">
                <button type="submit" class="act act-del">Delete</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<!-- Add / Edit Modal -->
<div class="modal" id="bannerModal">
  <div class="modal-box">
    <form method="POST" enctype="multipart/form-data" id="bannerForm">
      <div class="modal-head">
        <h2 id="modalTitle">Add Banner</h2>
        <button type="button" onclick="closeModal()" style="font-size:1.3rem;color:var(--muted)">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="f_action" value="create">
        <input type="hidden" name="id" id="f_id" value="">

        <div>
          <label>Banner Image <span style="color:var(--error)">*</span></label>
          <div class="file-row">
            <input type="file" name="banner_image" id="f_image" accept="image/png,image/jpeg,image/jpg,image/webp" onchange="previewImg(event)">
            <img class="preview" id="f_preview" alt="preview">
          </div>
          <div class="hint">PNG, JPG, WEBP — max 5MB. Recommended 1920×600. (Editing: leave empty to keep current image.)</div>
        </div>

        <div>
          <label>Title</label>
          <input type="text" name="title" id="f_title" placeholder="e.g., Get 50% OFF — Use Code SAVE50">
        </div>

        <div>
          <label>Description</label>
          <textarea name="description" id="f_description" placeholder="Limited time offer on all courses"></textarea>
        </div>

        <div>
          <label>Link URL (optional)</label>
          <input type="url" name="link_url" id="f_link" placeholder="https://internshipadda.com/courses">
        </div>

        <div>
          <label>Display Order</label>
          <input type="number" name="display_order" id="f_order" value="0" min="0">
          <div class="hint">Lower numbers appear first in the slider.</div>
        </div>

        <div class="check-row">
          <input type="checkbox" name="is_active" id="f_active" value="1" checked>
          <label for="f_active" style="margin:0">Active (show on homepage)</label>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn-primary" style="flex:1;justify-content:center">Save Banner</button>
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const root = document.documentElement;
  const themeBtn = document.querySelector('[data-theme-toggle]');
  const saved = localStorage.getItem('theme');
  const prefersDark = matchMedia('(prefers-color-scheme:dark)').matches;
  root.setAttribute('data-theme', saved || (prefersDark ? 'dark' : 'light'));
  if(themeBtn){
    themeBtn.addEventListener('click', () => {
      const t = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', t);
      localStorage.setItem('theme', t);
    });
  }
})();

const modal = document.getElementById('bannerModal');

function openCreate(){
  document.getElementById('modalTitle').textContent = 'Add Banner';
  document.getElementById('f_action').value = 'create';
  document.getElementById('f_id').value = '';
  document.getElementById('f_title').value = '';
  document.getElementById('f_description').value = '';
  document.getElementById('f_link').value = '';
  document.getElementById('f_order').value = '0';
  document.getElementById('f_active').checked = true;
  document.getElementById('f_image').value = '';
  document.getElementById('f_image').required = true;
  const p = document.getElementById('f_preview');
  p.style.display = 'none'; p.src = '';
  modal.classList.add('open');
}

function openEdit(b){
  document.getElementById('modalTitle').textContent = 'Edit Banner';
  document.getElementById('f_action').value = 'update';
  document.getElementById('f_id').value = b.id;
  document.getElementById('f_title').value = b.title || '';
  document.getElementById('f_description').value = b.description || '';
  document.getElementById('f_link').value = b.link_url || '';
  document.getElementById('f_order').value = b.display_order || 0;
  document.getElementById('f_active').checked = (b.is_active == 1);
  document.getElementById('f_image').value = '';
  document.getElementById('f_image').required = false;
  const p = document.getElementById('f_preview');
  if(b.image_path){ p.src = '/' + b.image_path; p.style.display = 'block'; }
  else { p.style.display = 'none'; p.src = ''; }
  modal.classList.add('open');
}

function closeModal(){ modal.classList.remove('open'); }

function previewImg(e){
  const f = e.target.files[0];
  const p = document.getElementById('f_preview');
  if(f){
    const r = new FileReader();
    r.onload = ev => { p.src = ev.target.result; p.style.display = 'block'; };
    r.readAsDataURL(f);
  }
}

modal.addEventListener('click', e => { if(e.target === modal) closeModal(); });
</script>
</body>
</html>
