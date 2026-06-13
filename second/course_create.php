<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('courses_create');

$db  = getDB();
$uid = (int)($_SESSION['user_id'] ?? 0);
$error = '';

/* Make a unique slug for the given table */
function uniqueSlug(PDO $db, string $table, string $title): string {
    $base = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($title)), '-');
    if ($base === '') $base = 'course';
    $slug = $base; $n = 1;
    while (true) {
        $st = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE slug = ?");
        $st->execute([$slug]);
        if (!$st->fetchColumn()) break;
        $slug = $base . '-' . (++$n);
    }
    return $slug;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $status      = in_array($_POST['status'] ?? '', ['published','draft'], true) ? $_POST['status'] : 'draft';

    if ($title === '') {
        $error = 'Course title required hai.';
    } else {
        try {
            $slug = uniqueSlug($db, 'courses', $title);
            $stmt = $db->prepare(
                "INSERT INTO courses (title, slug, description, price, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([$title, $slug, $description, $price, $status, $uid]);
            $newId = (int)$db->lastInsertId();
            logAction('course_created', 'course', $newId, "Title: $title, Status: $status");
            // Naya course banne ke baad seedhe content builder pe le jao
            header("Location: course_builder.php?course_id=$newId");
            exit;
        } catch (Exception $e) {
            $error = 'Course create nahi ho paaya: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Course — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f9fafb;--surface:#fff;--border:#e5e7eb;--divider:#e5e7eb;--text:#111827;--muted:#6b7280;--primary:#16a34a;--primary-h:#15803d;--success:#437a22;--error:#a12c7b;--r-lg:.75rem;--r-md:.5rem;--shadow-md:0 4px 12px rgba(0,0,0,0.08);--t:180ms cubic-bezier(.16,1,.3,1)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:rgba(255,255,255,0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--primary:#4ade80;--primary-h:#22c55e;--success:#6daa45;--error:#d163a7}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);padding:1.5rem;min-height:100dvh}
.form-wrap{max-width:700px;margin:0 auto}
.back{font-size:.83rem;color:var(--primary);display:inline-flex;align-items:center;gap:.3rem;margin-bottom:1rem}
h1{font-size:1.25rem;font-weight:700;margin-bottom:.35rem}
.sub{font-size:.83rem;color:var(--muted);margin-bottom:1.5rem}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:1.75rem;box-shadow:var(--shadow-md)}
.form-group{margin-bottom:1.2rem}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem}
input[type=text],input[type=number],select,textarea{width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--bg);color:var(--text);font:inherit;font-size:.9rem;transition:border-color var(--t)}
textarea{min-height:130px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary)}
.btn-primary{background:var(--primary);color:#fff;padding:.7rem 1.5rem;border-radius:var(--r-md);font:inherit;font-size:.9rem;font-weight:600;cursor:pointer;border:none;transition:background var(--t)}
.btn-primary:hover{background:var(--primary-h)}
.btn-ghost{padding:.7rem 1.2rem;border:1.5px solid var(--border);border-radius:var(--r-md);font:inherit;font-size:.9rem;cursor:pointer;color:var(--muted);background:none;text-decoration:none;display:inline-block}
.btn-ghost:hover{background:var(--bg)}
.alert{padding:.75rem 1rem;border-radius:var(--r-md);font-size:.875rem;margin-bottom:1.2rem}
.alert-error{background:rgba(161,44,123,0.1);border:1px solid rgba(161,44,123,0.3);color:var(--error)}
.hint{background:rgba(22,163,74,0.07);border:1px solid rgba(22,163,74,0.2);color:var(--primary-h);padding:.65rem 1rem;border-radius:var(--r-md);font-size:.8rem;margin-bottom:1.25rem}
</style>
</head>
<body>
<div class="form-wrap">
  <a href="courses.php" class="back">← Back to Courses</a>
  <h1>Create New Course</h1>
  <p class="sub">Basic details bharo — save karte hi content builder khul jayega jahan chapters/lessons add kar sakte ho.</p>

  <div class="hint">ℹ️ Course pehle <strong>Draft</strong> me banega. Ready hone par Edit page se <strong>Published</strong> kar dena.</div>

  <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <form method="POST">
      <div class="form-group">
        <label for="title">Course Title *</label>
        <input type="text" id="title" name="title" required placeholder="e.g. Python for Beginners"
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" placeholder="Course ke baare me short description..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="form-group">
          <label for="price">Price (₹)</label>
          <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:.5rem">
        <button type="submit" class="btn-primary">Create &amp; Add Content →</button>
        <a href="courses.php" class="btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
