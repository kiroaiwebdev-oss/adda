<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('courses_edit');

$db = getDB();
$activeNav = 'courses';
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
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="breadcrumb">Manager Panel / <a href="courses.php" style="color:var(--primary)">Courses</a> / <span>Edit #<?= (int)$course['id'] ?></span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Edit Course #<?= (int)$course['id'] ?></h1>
                <p>Course details update karo ya content builder me jaake chapters/lessons manage karo.</p>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a href="course_content_editor.php?course_id=<?= (int)$course['id'] ?>" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    Content Builder
                </a>
                <a href="courses.php" class="btn btn-outline">← Back</a>
            </div>
        </div>

        <div class="alert alert-warn">🔒 Manager Mode — Delete permission nahi hai, sirf edit allowed hai.</div>

        <?php if($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card" style="max-width:720px">
          <div style="padding:1.5rem">
            <form method="POST">
              <div class="field">
                <label class="label" for="title">Course Title *</label>
                <input type="text" id="title" name="title" required value="<?= htmlspecialchars($course['title']) ?>">
              </div>
              <div class="field">
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description" style="min-height:130px"><?= htmlspecialchars($course['description']??'') ?></textarea>
              </div>
              <div class="field-row">
                <div class="field">
                  <label class="label" for="price">Price (₹)</label>
                  <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($course['price']) ?>">
                </div>
                <div class="field">
                  <label class="label" for="status">Status</label>
                  <select id="status" name="status">
                    <option value="published" <?= $course['status']==='published'?'selected':'' ?>>Published</option>
                    <option value="draft" <?= $course['status']==='draft'?'selected':'' ?>>Draft</option>
                  </select>
                </div>
              </div>
              <div style="display:flex;gap:.75rem;margin-top:.5rem">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="courses.php" class="btn btn-outline">Cancel</a>
              </div>
              <div style="font-size:.78rem;color:var(--muted);margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--divider)">
                Created: <?= date('d M Y, H:i', strtotime($course['created_at'])) ?>
                &nbsp;•&nbsp; Last updated: <?= date('d M Y, H:i', strtotime($course['updated_at'])) ?>
              </div>
            </form>
          </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
