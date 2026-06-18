<?php
require_once __DIR__ . '/manager_auth.php';

// Logout handler — TOP pe (consistent with manager_dashboard.php)
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: manager_login.php');
    exit;
}

requireManagerAccess();
$db = getDB();

$role    = $_SESSION['user_role'] ?? 'manager';
$name    = $_SESSION['user_name'] ?? 'Manager';
$isAdmin = ($role === 'admin');

$TABLE      = 'promotional_banners';
$UPLOAD_DIR = __DIR__ . '/../public/uploads/banners/';   // physical path
$UPLOAD_REL = 'public/uploads/banners/';                 // stored/displayed path

$flash = ['type' => '', 'msg' => ''];

/* ----------------------------------------------------------------------------
 *  Build candidate image URLs (handles docroot differences + path formats)
 * -------------------------------------------------------------------------- */
function bannerImgCandidates(string $img): array {
    $img = trim($img);
    if ($img === '') return [];
    // Already an absolute URL
    if (preg_match('#^https?://#i', $img)) return [$img];

    $p = ltrim($img, '/');                       // strip leading slashes
    $noPublic = preg_replace('#^public/#', '', $p);

    $cands = [
        '/' . $p,            // docroot = repo root  ->  /public/uploads/banners/x
        '/' . $noPublic,     // docroot = public/    ->  /uploads/banners/x
        '../' . $p,          // relative from /second/
    ];
    // If path had no "public/" prefix, also try adding it
    if ($noPublic === $p) {
        $cands[] = '/public/' . $p;
        $cands[] = '../public/' . $p;
    }
    return array_values(array_unique($cands));
}

/* ----------------------------------------------------------------------------
 *  Image upload helper
 * -------------------------------------------------------------------------- */
function uploadBannerImage(array $file, string $dir, string $rel): array {
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
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
                        $fp = __DIR__ . '/../' . ltrim($banner['image_path'], '/');
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
                if (!$up['ok']) { $flash = ['type' => 'error', 'msg' => $up['msg']]; }
                else { $imagePath = $up['path']; }
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
$banners     = [];
$activeCount = 0;
$loadError   = '';
try {
    $banners = $db->query("SELECT * FROM `$TABLE` ORDER BY display_order ASC, created_at DESC")->fetchAll();
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Banners — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
/* ===== WHITE + GREEN THEME ===== */
:root,[data-theme="light"]{
  --bg:#f4f8f4;--surface:#fff;--surface-2:#f7faf7;
  --border:#dbe7db;--divider:#e7efe7;
  --text:#16241a;--muted:#5f6f63;--faint:#9fb0a4;
  --primary:#16a34a;--primary-h:#15803d;--primary-d:#166534;
  --success:#16a34a;--warning:#b45309;--error:#dc2626;--orange:#ea7317;
  --r-sm:.375rem;--r-md:.5rem;--r-lg:.75rem;--r-xl:1rem;
  --shadow-sm:0 1px 2px rgba(16,40,24,.06);
  --shadow-md:0 4px 14px rgba(16,40,24,.10);
  --t:180ms cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#0f1511;--surface:#15201a;--surface-2:#18241d;
  --border:#243a2c;--divider:#1f3025;
  --text:#dce9e0;--muted:#8aa093;--faint:#5e7568;
  --primary:#34d399;--primary-h:#10b981;--primary-d:#059669;
  --success:#34d399;--warning:#f59e0b;--error:#f87171;--orange:#fb923c;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh;display:flex}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}

/* ===== SIDEBAR (matches manager_dashboard.php) ===== */
.sidebar{width:240px;min-height:100dvh;background:var(--surface);border-right:1px solid var(--divider);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100}
.sidebar-logo{padding:1.25rem 1.25rem 1rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;gap:.75rem}
.sidebar-logo svg{width:32px;height:32px;flex-shrink:0}
.logo-text{font-weight:700;font-size:.95rem}.logo-text span{color:var(--primary)}
.sidebar-user{padding:.9rem 1.25rem;border-bottom:1px solid var(--divider)}
.user-badge{font-size:.72rem;font-weight:600;background:oklch(from var(--primary) l c h/0.12);color:var(--primary);padding:.2rem .6rem;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em;display:inline-block;margin-bottom:.35rem}
.user-name{font-weight:600;font-size:.9rem}
.user-email-sm{font-size:.75rem;color:var(--muted)}
nav{flex:1;padding:.75rem 0;overflow-y:auto}
.nav-section{padding:.5rem 1.25rem .25rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint)}
.nav-item{display:flex;align-items:center;gap:.7rem;padding:.55rem 1.25rem;font-size:.875rem;color:var(--muted);transition:color var(--t),background var(--t);position:relative}
.nav-item:hover{background:var(--bg);color:var(--text)}
.nav-item.active{background:oklch(from var(--primary) l c h/0.1);color:var(--primary);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--primary);border-radius:0 4px 4px 0}
.nav-item svg{width:16px;height:16px;flex-shrink:0;opacity:.7}
.nav-item.active svg,.nav-item:hover svg{opacity:1}
.sidebar-footer{padding:1rem 1.25rem;border-top:1px solid var(--divider);display:flex;gap:.5rem;align-items:center}
.btn-sm{padding:.45rem .9rem;font-size:.8rem;border-radius:var(--r-md);font-weight:500;transition:background var(--t),color var(--t);display:inline-flex;align-items:center;gap:.3rem}
.btn-ghost{border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{background:var(--bg);color:var(--text)}
.btn-danger{background:oklch(from var(--error) l c h/0.1);color:var(--error)}.btn-danger:hover{background:oklch(from var(--error) l c h/0.18)}

/* ===== MAIN ===== */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100dvh}
.topbar{height:56px;background:var(--surface);border-bottom:1px solid var(--divider);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;position:sticky;top:0;z-index:50}
.breadcrumb{font-size:.78rem;color:var(--muted);flex:1}
.breadcrumb span{color:var(--text);font-weight:500}
.content{padding:1.5rem;flex:1}

.page-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem}
.page-head h1{font-size:1.4rem;font-weight:700}
.page-head .sub{font-size:.82rem;color:var(--muted);margin-top:.2rem}
.btn-primary{background:var(--primary);color:#fff;padding:.6rem 1.15rem;border-radius:var(--r-md);font-size:.85rem;font-weight:600;border:none;cursor:pointer;transition:background var(--t);display:inline-flex;align-items:center;gap:.45rem;box-shadow:var(--shadow-sm)}
.btn-primary:hover{background:var(--primary-h)}
.pill{font-size:.78rem;color:var(--primary);background:oklch(from var(--primary) l c h/0.1);padding:.4rem .9rem;border-radius:9999px;font-weight:600}

.flash{padding:.7rem 1rem;border-radius:var(--r-md);font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.flash-success{background:oklch(from var(--success) l c h/0.12);color:var(--primary-d);border:1px solid oklch(from var(--success) l c h/0.3)}
.flash-error{background:oklch(from var(--error) l c h/0.1);color:var(--error);border:1px solid oklch(from var(--error) l c h/0.3)}
.warn-notice{background:oklch(from var(--orange) l c h/0.08);border:1px solid oklch(from var(--orange) l c h/0.25);border-radius:var(--r-md);padding:.65rem 1rem;font-size:.82rem;color:var(--orange);margin-bottom:1rem}
.perm-notice{background:oklch(from var(--primary) l c h/0.07);border:1px solid oklch(from var(--primary) l c h/0.2);border-radius:var(--r-md);padding:.7rem 1rem;font-size:.82rem;color:var(--primary-d);margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem}

/* ===== BANNER GRID ===== */
.banner-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem}
.banner-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm);transition:box-shadow var(--t),transform var(--t)}
.banner-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px)}
.banner-thumb{position:relative;height:170px;background:var(--surface-2);display:flex;align-items:center;justify-content:center}
.banner-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.status-chip{position:absolute;top:.65rem;left:.65rem;padding:.22rem .65rem;border-radius:9999px;font-size:.7rem;font-weight:700;color:#fff;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.chip-active{background:var(--success)}
.chip-inactive{background:#94a3b8}
.order-chip{position:absolute;top:.65rem;right:.65rem;padding:.22rem .65rem;border-radius:9999px;font-size:.7rem;font-weight:700;background:rgba(0,0,0,.62);color:#fff}
.banner-body{padding:1rem}
.banner-title{font-weight:700;font-size:.95rem;margin-bottom:.25rem}
.banner-desc{font-size:.8rem;color:var(--muted);margin-bottom:.85rem;min-height:34px}
.banner-actions{display:flex;gap:.5rem;flex-wrap:wrap}
.act{padding:.45rem .8rem;border-radius:var(--r-md);font-size:.78rem;font-weight:600;border:none;cursor:pointer;color:#fff;transition:opacity var(--t)}
.act:hover{opacity:.88}
.act-edit{background:var(--primary)}
.act-toggle{background:var(--orange)}
.act-del{background:var(--error)}
.empty{text-align:center;padding:3rem 1rem;color:var(--muted);background:var(--surface);border:1px dashed var(--border);border-radius:var(--r-lg)}

/* ===== MODAL ===== */
.modal{position:fixed;inset:0;background:rgba(8,20,12,.5);backdrop-filter:blur(2px);z-index:200;display:none;align-items:center;justify-content:center;padding:1rem}
.modal.open{display:flex}
.modal-box{background:var(--surface);border-radius:var(--r-lg);max-width:560px;width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.25rem;border-bottom:1px solid var(--divider);position:sticky;top:0;background:var(--surface)}
.modal-head h2{font-size:1rem;font-weight:700}
.modal-body{padding:1.25rem;display:flex;flex-direction:column;gap:1rem}
label{font-size:.8rem;font-weight:600;display:block;margin-bottom:.35rem}
input[type=text],input[type=url],input[type=number],textarea,input[type=file]{width:100%;padding:.55rem .85rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--surface-2);color:var(--text);font:inherit;font-size:.85rem}
input:focus,textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px oklch(from var(--primary) l c h/0.15)}
textarea{resize:vertical;min-height:64px}
.preview{width:100%;height:120px;object-fit:cover;border-radius:var(--r-md);border:1px solid var(--border);display:none;margin-top:.6rem}
.check-row{display:flex;align-items:center;gap:.55rem;background:var(--surface-2);padding:.65rem .85rem;border-radius:var(--r-md)}
.check-row input{width:18px;height:18px;accent-color:var(--primary)}
.modal-foot{display:flex;gap:.6rem;padding:1.1rem 1.25rem;border-top:1px solid var(--divider)}
.btn-cancel{padding:.6rem 1.1rem;border:1.5px solid var(--border);border-radius:var(--r-md);font-size:.85rem;font-weight:600;background:transparent;color:var(--text)}
.hint{font-size:.72rem;color:var(--faint);margin-top:.25rem}

/* ===== MOBILE ===== */
.mobile-menu-btn{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--r-md);color:var(--muted);flex-shrink:0}
.mobile-menu-btn:hover{background:var(--bg)}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);transition:transform .3s ease}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .mobile-menu-btn{display:flex}
}
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
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

<!-- ===== MAIN ===== -->
<div class="main">
  <header class="topbar">
    <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="breadcrumb">Manager Panel / <span>Banners</span></div>
    <span style="font-size:.78rem;color:var(--muted)"><?= date('D, d M Y') ?></span>
  </header>

  <main class="content">
    <div class="page-head">
      <div>
        <h1>🎨 Promotional Banners</h1>
        <div class="sub"><?= count($banners) ?> total &nbsp;•&nbsp; <?= $activeCount ?> active on homepage</div>
      </div>
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <span class="pill"><?= $isAdmin ? 'Full access' : 'View + Edit • Delete restricted' ?></span>
        <button class="btn-primary" onclick="openCreate()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add New Banner
        </button>
      </div>
    </div>

    <div class="perm-notice">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Manager Mode — Banners view, add &amp; edit allowed.<?= $isAdmin ? '' : ' Delete operations restricted hain.' ?>
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
            $img    = (string)($b['image_path'] ?? '');
            $cands  = bannerImgCandidates($img);
            $first  = $cands[0] ?? '';
            $alts   = array_slice($cands, 1);
            $jsonB  = htmlspecialchars(json_encode([
                        'id'            => $bid,
                        'title'         => $b['title'] ?? '',
                        'description'   => $b['description'] ?? '',
                        'link_url'      => $b['link_url'] ?? '',
                        'display_order' => (int)($b['display_order'] ?? 0),
                        'is_active'     => $active ? 1 : 0,
                        'image_src'     => $first,
                      ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
        ?>
        <div class="banner-card">
          <div class="banner-thumb">
            <img src="<?= htmlspecialchars($first) ?>"
                 data-alts='<?= htmlspecialchars(json_encode($alts), ENT_QUOTES) ?>'
                 data-idx="0"
                 alt="<?= htmlspecialchars($b['title'] ?? 'Banner') ?>"
                 onerror="bannerImgFallback(this)">
            <span class="status-chip <?= $active?'chip-active':'chip-inactive' ?>"><?= $active?'✓ Active':'✕ Hidden' ?></span>
            <span class="order-chip">Order: <?= (int)($b['display_order'] ?? 0) ?></span>
          </div>
          <div class="banner-body">
            <div class="banner-title"><?= htmlspecialchars($b['title'] ?: 'Untitled Banner') ?></div>
            <div class="banner-desc"><?php
                $d = (string)($b['description'] ?? '');
                echo $d !== '' ? htmlspecialchars(mb_strimwidth($d, 0, 90, '…')) : '<span style="color:var(--faint)">No description</span>';
            ?></div>
            <div class="banner-actions">
              <button class="act act-edit" onclick='openEdit(<?= $jsonB ?>)'>Edit</button>
              <form method="POST" style="display:inline">
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

<!-- ===== Add / Edit Modal ===== -->
<div class="modal" id="bannerModal">
  <div class="modal-box">
    <form method="POST" enctype="multipart/form-data" id="bannerForm">
      <div class="modal-head">
        <h2 id="modalTitle">Add New Banner</h2>
        <button type="button" onclick="closeModal()" style="font-size:1.3rem;color:var(--muted)">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="f_action" value="create">
        <input type="hidden" name="id" id="f_id" value="">

        <div>
          <label>Banner Image <span style="color:var(--error)">*</span></label>
          <input type="file" name="banner_image" id="f_image" accept="image/png,image/jpeg,image/jpg,image/webp" onchange="previewImg(event)">
          <img class="preview" id="f_preview" alt="preview">
          <div class="hint">PNG, JPG, WEBP — max 5MB. Recommended 1920×600. (Editing: leave empty to keep current image.)</div>
        </div>

        <div>
          <label>Banner Title</label>
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
/* Theme toggle */
(function(){
  const root = document.documentElement;
  const btn  = document.querySelector('[data-theme-toggle]');
  const saved = localStorage.getItem('mgr-theme');
  root.setAttribute('data-theme', saved || 'light');
  if(btn){
    btn.addEventListener('click', () => {
      const t = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', t);
      localStorage.setItem('mgr-theme', t);
    });
  }
})();

/* Mobile sidebar */
const menuBtn = document.getElementById('menuBtn');
const sidebar = document.getElementById('sidebar');
if(menuBtn && sidebar){
  menuBtn.addEventListener('click', e => { e.stopPropagation(); sidebar.classList.toggle('open'); });
  document.addEventListener('click', e => {
    if(window.innerWidth <= 768 && !sidebar.contains(e.target) && !menuBtn.contains(e.target))
      sidebar.classList.remove('open');
  });
}

/* Image fallback chain — tries multiple path variants before placeholder */
const BANNER_PLACEHOLDER = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 200%22%3E%3Crect fill=%22%23e8f0e8%22 width=%22400%22 height=%22200%22/%3E%3Ctext fill=%22%2399ab9e%22 font-family=%22sans-serif%22 font-size=%2218%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3EImage not found%3C/text%3E%3C/svg%3E';
function bannerImgFallback(el){
  let alts = [];
  try { alts = JSON.parse(el.dataset.alts || '[]'); } catch(e){}
  let i = parseInt(el.dataset.idx || '0', 10);
  if(i < alts.length){
    el.dataset.idx = String(i + 1);
    el.src = alts[i];
  } else {
    el.onerror = null;
    el.src = BANNER_PLACEHOLDER;
  }
}

/* Modal */
const modal = document.getElementById('bannerModal');
function openCreate(){
  document.getElementById('modalTitle').textContent = 'Add New Banner';
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
  if(b.image_src){ p.src = b.image_src; p.style.display = 'block'; }
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
