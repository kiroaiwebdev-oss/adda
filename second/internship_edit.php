<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('internships_edit');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: internships.php'); exit; }

$intern = $db->prepare("SELECT * FROM internships WHERE id = ?");
$intern->execute([$id]);
$intern = $intern->fetch();
if (!$intern) { header('Location: internships.php'); exit; }

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration    = trim($_POST['duration'] ?? '');
    $stipend     = (float)($_POST['stipend'] ?? 0);
    $status      = $_POST['status'] ?? $intern['status'];
    $skills      = trim($_POST['skills'] ?? '');

    if (!$title) {
        $error = 'Title required hai.';
    } else {
        // Dynamic column update — sirf jo columns exist karti hain wo update karo
        $columns = $db->query("SHOW COLUMNS FROM internships")->fetchAll(PDO::FETCH_COLUMN);
        $sets = []; $vals = [];
        $map = [
            'title'       => $title,
            'description' => $description,
            'duration'    => $duration,
            'stipend'     => $stipend,
            'status'      => $status,
            'skills'      => $skills,
        ];
        foreach ($map as $col => $val) {
            if (in_array($col, $columns)) {
                $sets[] = "`$col` = ?";
                $vals[] = $val;
            }
        }
        if (in_array('updated_at', $columns)) {
            $sets[] = "`updated_at` = NOW()";
        } elseif (in_array('updatedat', $columns)) {
            $sets[] = "`updatedat` = NOW()";
        }
        $vals[] = $id;
        $db->prepare("UPDATE internships SET " . implode(', ', $sets) . " WHERE id = ?")
           ->execute($vals);

        logAction('internship_updated', 'internship', $id, "Title: $title | Status: $status");
        $success = 'Internship successfully updated!';

        $intern = $db->prepare("SELECT * FROM internships WHERE id = ?");
        $intern->execute([$id]);
        $intern = $intern->fetch();
    }
}

// Get all columns to know what fields exist
$existingCols = $db->query("SHOW COLUMNS FROM internships")->fetchAll(PDO::FETCH_COLUMN);
function hasCol($col, $cols) { return in_array($col, $cols); }
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Internship — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{
  --bg:#f7f6f2;--surface:#fff;--border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;
  --text:#28251d;--muted:#7a7974;--faint:#bab9b4;
  --primary:#01696f;--primary-h:#0c4e54;
  --success:#437a22;--error:#a12c7b;--orange:#da7101;
  --r-md:.5rem;--r-lg:.75rem;
  --shadow-md:0 4px 16px oklch(0.2 0.01 80/0.1);
  --t:180ms cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#171614;--surface:#1c1b19;--border:oklch(1 0 0/0.08);--divider:#262523;
  --text:#cdccca;--muted:#797876;--faint:#5a5957;
  --primary:#4f98a3;--primary-h:#227f8b;
  --success:#6daa45;--error:#d163a7;--orange:#fdab43;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh;padding:1.5rem}
a{color:inherit;text-decoration:none}

.form-wrap{max-width:740px;margin:0 auto}
.top-nav{display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap}
.back-btn{display:inline-flex;align-items:center;gap:.4rem;font-size:.83rem;color:var(--muted);padding:.35rem .75rem;border-radius:var(--r-md);border:1.5px solid var(--border);transition:background var(--t)}
.back-btn:hover{background:var(--surface)}
h1{font-size:1.25rem;font-weight:700;flex:1}
.builder-btn{display:inline-flex;align-items:center;gap:.4rem;background:var(--orange);color:#fff;padding:.5rem 1rem;border-radius:var(--r-md);font-size:.83rem;font-weight:600;transition:background var(--t)}
.builder-btn:hover{background:#c55700}

.restrict-banner{background:oklch(from var(--error) l c h/0.07);border:1px solid oklch(from var(--error) l c h/0.2);color:var(--error);padding:.65rem 1rem;border-radius:var(--r-md);font-size:.8rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:1.75rem;box-shadow:var(--shadow-md);margin-bottom:1.25rem}
.card-title{font-size:.85rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:1.1rem;padding-bottom:.75rem;border-bottom:1px solid var(--divider)}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.1rem}
.form-group{margin-bottom:1.1rem}
.form-group:last-child{margin-bottom:0}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:var(--text)}
.label-optional{font-size:.72rem;color:var(--muted);font-weight:400;margin-left:.35rem}
input[type=text],input[type=number],select,textarea{width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--bg);color:var(--text);font:inherit;font-size:.9rem;transition:border-color var(--t)}
textarea{min-height:120px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary)}

.btn{border:none;padding:.7rem 1.5rem;border-radius:var(--r-md);font:inherit;font-size:.9rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:background var(--t)}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-h)}
.btn-ghost{padding:.7rem 1.2rem;border:1.5px solid var(--border);background:none;color:var(--muted)}.btn-ghost:hover{background:var(--bg)}

.alert{padding:.75rem 1rem;border-radius:var(--r-md);font-size:.875rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem}
.alert-success{background:oklch(from var(--success) l c h/0.1);border:1px solid oklch(from var(--success) l c h/0.3);color:var(--success)}
.alert-error{background:oklch(from var(--error) l c h/0.1);border:1px solid oklch(from var(--error) l c h/0.3);color:var(--error)}

.meta-row{display:flex;gap:1rem;flex-wrap:wrap;font-size:.78rem;color:var(--muted);padding-top:.9rem;border-top:1px solid var(--divider);margin-top:.75rem}

@media(max-width:600px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="form-wrap">

  <!-- NAV -->
  <div class="top-nav">
    <a href="internships.php" class="back-btn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Internships
    </a>
    <h1>Edit Internship #<?= $id ?></h1>
    <a href="internship_builder.php?internship_id=<?= $id ?>" class="builder-btn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      Content Builder
    </a>
  </div>

  <div class="restrict-banner">
    🔒 Manager Mode — Sirf edit allowed. Delete nahi kar sakte.
  </div>

  <?php if($success): ?>
  <div class="alert alert-success">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if($error): ?>
  <div class="alert alert-error">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST">
    <!-- BASIC INFO -->
    <div class="card">
      <div class="card-title">Basic Information</div>

      <div class="form-group">
        <label for="title">Internship Title <span style="color:var(--error)">*</span></label>
        <input type="text" id="title" name="title" required
               value="<?= htmlspecialchars($intern['title']) ?>"
               placeholder="e.g. Full Stack Development Internship">
      </div>

      <?php if(hasCol('description', $existingCols)): ?>
      <div class="form-group">
        <label for="description">Description <span class="label-optional">(optional)</span></label>
        <textarea id="description" name="description"
                  placeholder="Internship ke baare mein batao..."><?= htmlspecialchars($intern['description'] ?? '') ?></textarea>
      </div>
      <?php endif; ?>

      <div class="form-row">
        <?php if(hasCol('duration', $existingCols)): ?>
        <div class="form-group" style="margin-bottom:0">
          <label for="duration">Duration</label>
          <input type="text" id="duration" name="duration"
                 value="<?= htmlspecialchars($intern['duration'] ?? '') ?>"
                 placeholder="e.g. 4 weeks, 2 months">
        </div>
        <?php endif; ?>

        <?php if(hasCol('stipend', $existingCols)): ?>
        <div class="form-group" style="margin-bottom:0">
          <label for="stipend">Stipend (₹) <span class="label-optional">0 = unpaid</span></label>
          <input type="number" id="stipend" name="stipend" min="0" step="100"
                 value="<?= htmlspecialchars($intern['stipend'] ?? '0') ?>">
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- STATUS & SETTINGS -->
    <div class="card">
      <div class="card-title">Status &amp; Settings</div>

      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label for="status">Status</label>
          <select id="status" name="status">
            <?php
            $currentStatus = $intern['status'] ?? 'draft';
            $statusOptions = ['active','inactive','open','closed','published','draft'];
            foreach($statusOptions as $opt):
            ?>
            <option value="<?= $opt ?>" <?= $currentStatus===$opt?'selected':'' ?>>
              <?= ucfirst($opt) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if(hasCol('skills', $existingCols)): ?>
        <div class="form-group" style="margin-bottom:0">
          <label for="skills">Skills Required <span class="label-optional">(comma separated)</span></label>
          <input type="text" id="skills" name="skills"
                 value="<?= htmlspecialchars($intern['skills'] ?? '') ?>"
                 placeholder="PHP, MySQL, HTML, CSS">
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- BUTTONS -->
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Save Changes
      </button>
      <a href="internship_builder.php?internship_id=<?= $id ?>">
        <button type="button" class="btn" style="background:oklch(from var(--orange) l c h/0.12);color:var(--orange)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
          Go to Content Builder
        </button>
      </a>
      <a href="internships.php">
        <button type="button" class="btn btn-ghost">Cancel</button>
      </a>
    </div>

    <div class="meta-row">
      <?php if(isset($intern['created_at'])): ?>
      <span>Created: <?= date('d M Y, H:i', strtotime($intern['created_at'])) ?></span>
      <?php endif; ?>
      <?php $updCol = hasCol('updated_at',$existingCols)?'updated_at':(hasCol('updatedat',$existingCols)?'updatedat':null); ?>
      <?php if($updCol && !empty($intern[$updCol])): ?>
      <span>Last updated: <?= date('d M Y, H:i', strtotime($intern[$updCol])) ?></span>
      <?php endif; ?>
      <span>ID: #<?= $id ?></span>
    </div>
  </form>
</div>

<script>
(function(){
  const r = document.documentElement;
  const d = matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light';
  r.setAttribute('data-theme', d);
})();
</script>
</body>
</html>