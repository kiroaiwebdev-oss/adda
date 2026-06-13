<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('courses_create');

$db  = getDB();
$activeNav = 'courses';
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
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="breadcrumb">Manager Panel / <a href="courses.php" style="color:var(--primary)">Courses</a> / <span>Create</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Create New Course</h1>
                <p>Basic details bharo — save karte hi content builder khul jayega jahan chapters/lessons add kar sakte ho.</p>
            </div>
            <a href="courses.php" class="btn btn-outline">← Back to Courses</a>
        </div>

        <div class="alert alert-info">ℹ️ Course pehle <strong>Draft</strong> me banega. Ready hone par Edit page se <strong>Published</strong> kar dena.</div>

        <?php if($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card" style="max-width:720px">
          <div style="padding:1.5rem">
            <form method="POST">
              <div class="field">
                <label class="label" for="title">Course Title *</label>
                <input type="text" id="title" name="title" required placeholder="e.g. Python for Beginners"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
              </div>
              <div class="field">
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description" style="min-height:130px" placeholder="Course ke baare me short description..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              </div>
              <div class="field-row">
                <div class="field">
                  <label class="label" for="price">Price (₹)</label>
                  <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
                </div>
                <div class="field">
                  <label class="label" for="status">Status</label>
                  <select id="status" name="status">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                  </select>
                </div>
              </div>
              <div style="display:flex;gap:.75rem;margin-top:.5rem">
                <button type="submit" class="btn btn-primary">Create &amp; Add Content →</button>
                <a href="courses.php" class="btn btn-outline">Cancel</a>
              </div>
            </form>
          </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
