<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('internships_create');

$db = getDB();
$error = '';

function uniqueSlug(PDO $db, string $table, string $title): string {
    $base = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($title)), '-');
    if ($base === '') $base = 'internship';
    $slug = $base; $n = 1;
    while (true) {
        $st = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE slug = ?");
        $st->execute([$slug]);
        if (!$st->fetchColumn()) break;
        $slug = $base . '-' . (++$n);
    }
    return $slug;
}

// Which optional columns exist (schema-safe insert)
$cols = $db->query("SHOW COLUMNS FROM internships")->fetchAll(PDO::FETCH_COLUMN);
$hasCol = fn($c) => in_array($c, $cols, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $mode        = in_array($_POST['mode'] ?? '', ['Remote','Onsite','Hybrid'], true) ? $_POST['mode'] : 'Remote';
    $level       = in_array($_POST['skill_level'] ?? '', ['beginner','intermediate','advanced'], true) ? $_POST['skill_level'] : 'beginner';
    $weeks       = max(1, (int)($_POST['duration_weeks'] ?? 4));
    $price       = (float)($_POST['price'] ?? 0);
    $active      = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '') {
        $error = 'Internship title required hai.';
    } else {
        try {
            $slug = uniqueSlug($db, 'internships', $title);

            // Build insert based on available columns
            $data = ['title' => $title, 'slug' => $slug];
            if ($hasCol('description'))    $data['description']    = $description;
            if ($hasCol('category'))       $data['category']       = $category;
            if ($hasCol('mode'))           $data['mode']           = $mode;
            if ($hasCol('skill_level'))    $data['skill_level']    = $level;
            if ($hasCol('duration_weeks')) $data['duration_weeks'] = $weeks;
            if ($hasCol('price'))          $data['price']          = $price;
            if ($hasCol('is_active'))      $data['is_active']      = $active;

            $colsSql = '`' . implode('`,`', array_keys($data)) . '`';
            $ph      = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO internships ($colsSql) VALUES ($ph)")->execute(array_values($data));
            $newId = (int)$db->lastInsertId();
            logAction('internship_created', 'internship', $newId, "Title: $title");
            header("Location: internship_builder.php?id=$newId");
            exit;
        } catch (Exception $e) {
            $error = 'Internship create nahi ho paaya: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Internship — Manager Panel</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f9fafb;--surface:#fff;--border:#e5e7eb;--divider:#e5e7eb;--text:#111827;--muted:#6b7280;--primary:#16a34a;--primary-h:#15803d;--success:#437a22;--error:#a12c7b;--orange:#da7101;--r-lg:.75rem;--r-md:.5rem;--shadow-md:0 4px 12px rgba(0,0,0,0.08);--t:180ms cubic-bezier(.16,1,.3,1)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:rgba(255,255,255,0.08);--divider:#262523;--text:#cdccca;--muted:#797876;--primary:#4ade80;--primary-h:#22c55e;--success:#6daa45;--error:#d163a7;--orange:#fdab43}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);padding:1.5rem;min-height:100dvh}
.form-wrap{max-width:740px;margin:0 auto}
.back{font-size:.83rem;color:var(--primary);display:inline-flex;align-items:center;gap:.3rem;margin-bottom:1rem}
h1{font-size:1.25rem;font-weight:700;margin-bottom:.35rem}
.sub{font-size:.83rem;color:var(--muted);margin-bottom:1.5rem}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:1.75rem;box-shadow:var(--shadow-md)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{margin-bottom:1.2rem}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem}
input[type=text],input[type=number],select,textarea{width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--bg);color:var(--text);font:inherit;font-size:.9rem;transition:border-color var(--t)}
textarea{min-height:120px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary)}
.btn-primary{background:var(--primary);color:#fff;padding:.7rem 1.5rem;border-radius:var(--r-md);font:inherit;font-size:.9rem;font-weight:600;cursor:pointer;border:none;transition:background var(--t)}
.btn-primary:hover{background:var(--primary-h)}
.btn-ghost{padding:.7rem 1.2rem;border:1.5px solid var(--border);border-radius:var(--r-md);font:inherit;font-size:.9rem;cursor:pointer;color:var(--muted);background:none;text-decoration:none;display:inline-block}
.btn-ghost:hover{background:var(--bg)}
.alert{padding:.75rem 1rem;border-radius:var(--r-md);font-size:.875rem;margin-bottom:1.2rem}
.alert-error{background:rgba(161,44,123,0.1);border:1px solid rgba(161,44,123,0.3);color:var(--error)}
.check-row{display:flex;align-items:center;gap:.5rem}
.check-row input{width:auto}
@media(max-width:600px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="form-wrap">
  <a href="internships.php" class="back">← Back to Internships</a>
  <h1>Create New Internship</h1>
  <p class="sub">Details bharo — save karte hi content builder khul jayega jahan modules/lessons add kar sakte ho.</p>

  <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <form method="POST">
      <div class="form-group">
        <label for="title">Internship Title *</label>
        <input type="text" id="title" name="title" required placeholder="e.g. Full Stack Development Internship"
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
      </div>
      <?php if($hasCol('description')): ?>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" placeholder="Internship ke baare me batao..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <?php endif; ?>
      <div class="form-row">
        <?php if($hasCol('category')): ?>
        <div class="form-group">
          <label for="category">Category</label>
          <input type="text" id="category" name="category" placeholder="e.g. Web Development"
                 value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
        </div>
        <?php endif; ?>
        <?php if($hasCol('mode')): ?>
        <div class="form-group">
          <label for="mode">Mode</label>
          <select id="mode" name="mode">
            <option value="Remote">Remote</option>
            <option value="Onsite">Onsite</option>
            <option value="Hybrid">Hybrid</option>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="form-row">
        <?php if($hasCol('duration_weeks')): ?>
        <div class="form-group">
          <label for="duration_weeks">Duration (weeks)</label>
          <input type="number" id="duration_weeks" name="duration_weeks" min="1" value="<?= htmlspecialchars($_POST['duration_weeks'] ?? '4') ?>">
        </div>
        <?php endif; ?>
        <?php if($hasCol('skill_level')): ?>
        <div class="form-group">
          <label for="skill_level">Skill Level</label>
          <select id="skill_level" name="skill_level">
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="form-row">
        <?php if($hasCol('price')): ?>
        <div class="form-group">
          <label for="price">Price (₹) <span style="font-weight:400;color:var(--muted)">0 = Free</span></label>
          <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
        </div>
        <?php endif; ?>
        <?php if($hasCol('is_active')): ?>
        <div class="form-group" style="display:flex;align-items:flex-end">
          <label class="check-row" style="margin-bottom:.65rem">
            <input type="checkbox" name="is_active" checked> Active (publicly visible)
          </label>
        </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:.5rem">
        <button type="submit" class="btn-primary">Create &amp; Add Content →</button>
        <a href="internships.php" class="btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
