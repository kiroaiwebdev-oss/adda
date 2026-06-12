<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('courses_edit');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: courses.php'); exit; }

$course = $db->prepare("SELECT * FROM courses WHERE id = ?");
$course->execute([$id]);
$course = $course->fetch();
if (!$course) { header('Location: courses.php'); exit; }

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $status      = in_array($_POST['status'],['published','draft']) ? $_POST['status'] : 'draft';

    if (!$title) {
        $error = 'Title required hai.';
    } else {
        $stmt = $db->prepare(
            "UPDATE courses SET title=?, description=?, price=?, status=?, updated_at=NOW() WHERE id=?"
        );
        $stmt->execute([$title, $description, $price, $status, $id]);
        logAction("course_updated", 'course', $id, "Title: $title, Status: $status");
        $success = 'Course successfully updated!';
        // Refresh
        $course = $db->prepare("SELECT * FROM courses WHERE id = ?");
        $course->execute([$id]); $course = $course->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Course — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f7f6f2;--surface:#fff;--border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;--text:#28251d;--muted:#7a7974;--primary:#16a34a;--primary-h:#15803d;--success:#437a22;--error:#a12c7b;--r-lg:.75rem;--r-md:.5rem;--shadow-md:0 4px 12px oklch(0.2 0.01 80/0.08);--t:180ms cubic-bezier(.16,1,.3,1)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:oklch(1 0 0/0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--primary:#4ade80;--primary-h:#22c55e;--success:#6daa45;--error:#d163a7}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);padding:1.5rem;min-height:100dvh}
.form-wrap{max-width:700px;margin:0 auto}
.back{font-size:.83rem;color:var(--primary);display:inline-flex;align-items:center;gap:.3rem;margin-bottom:1rem}
h1{font-size:1.25rem;font-weight:700;margin-bottom:1.5rem}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:1.75rem;box-shadow:var(--shadow-md)}
.form-group{margin-bottom:1.2rem}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem}
input[type=text],input[type=number],select,textarea{width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--bg);color:var(--text);font:inherit;font-size:.9rem;transition:border-color var(--t)}
textarea{min-height:130px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary)}
.btn-primary{background:var(--primary);color:#fff;padding:.7rem 1.5rem;border-radius:var(--r-md);font:inherit;font-size:.9rem;font-weight:600;cursor:pointer;border:none;transition:background var(--t)}
.btn-primary:hover{background:var(--primary-h)}
.btn-ghost{padding:.7rem 1.2rem;border:1.5px solid var(--border);border-radius:var(--r-md);font:inherit;font-size:.9rem;cursor:pointer;color:var(--muted);background:none;transition:background var(--t)}
.btn-ghost:hover{background:var(--bg)}
.alert{padding:.75rem 1rem;border-radius:var(--r-md);font-size:.875rem;margin-bottom:1.2rem}
.alert-success{background:oklch(from var(--success) l c h/0.1);border:1px solid oklch(from var(--success) l c h/0.3);color:var(--success)}
.alert-error{background:oklch(from var(--error) l c h/0.1);border:1px solid oklch(from var(--error) l c h/0.3);color:var(--error)}
.no-delete-notice{background:oklch(from var(--error) l c h/0.07);border:1px solid oklch(from var(--error) l c h/0.2);color:var(--error);padding:.65rem 1rem;border-radius:var(--r-md);font-size:.8rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem}
.meta-info{font-size:.78rem;color:var(--muted);margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--divider)}
</style>
</head>
<body>
<div class="form-wrap">
  <a href="courses.php" class="back">← Back to Courses</a>
  <h1>Edit Course #<?= $course['id'] ?></h1>
<a href="course_builder.php?course_id=<?= $course['id'] ?>" 
   style="display:inline-flex;align-items:center;gap:.4rem;background:var(--primary);color:#fff;padding:.55rem 1.1rem;border-radius:.5rem;font-size:.85rem;font-weight:600;margin-bottom:1.25rem;text-decoration:none">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
  Course Content Builder
</a>
  <div class="no-delete-notice">
    🔒 Delete permission nahi hai — sirf edit allowed hai.
  </div>

  <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

  <div class="card">
    <form method="POST">
      <div class="form-group">
        <label for="title">Course Title *</label>
        <input type="text" id="title" name="title" required
               value="<?= htmlspecialchars($course['title']) ?>">
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"><?= htmlspecialchars($course['description']??'') ?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="form-group">
          <label for="price">Price (₹)</label>
          <input type="number" id="price" name="price" step="0.01" min="0"
                 value="<?= $course['price'] ?>">
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="published" <?= $course['status']==='published'?'selected':'' ?>>Published</option>
            <option value="draft" <?= $course['status']==='draft'?'selected':'' ?>>Draft</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:.5rem">
        <button type="submit" class="btn-primary">Save Changes</button>
        <a href="courses.php"><button type="button" class="btn-ghost">Cancel</button></a>
      </div>
      <div class="meta-info">
        Created: <?= date('d M Y, H:i', strtotime($course['created_at'])) ?>
        &nbsp;•&nbsp; Last updated: <?= date('d M Y, H:i', strtotime($course['updated_at'])) ?>
      </div>
    </form>
  </div>
</div>
</body>
</html>