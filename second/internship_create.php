<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('internships_create');

$db = getDB();
$activeNav = 'internships';
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
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="breadcrumb">Manager Panel / <a href="internships.php" style="color:var(--primary)">Internships</a> / <span>Create</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Create New Internship</h1>
                <p>Details bharo — save karte hi content builder khul jayega jahan modules/lessons add kar sakte ho.</p>
            </div>
            <a href="internships.php" class="btn btn-outline">← Back to Internships</a>
        </div>

        <?php if($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card" style="max-width:760px">
          <div style="padding:1.5rem">
            <form method="POST">
              <div class="field">
                <label class="label" for="title">Internship Title *</label>
                <input type="text" id="title" name="title" required placeholder="e.g. Full Stack Development Internship"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
              </div>
              <?php if($hasCol('description')): ?>
              <div class="field">
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description" style="min-height:120px" placeholder="Internship ke baare me batao..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              </div>
              <?php endif; ?>
              <div class="field-row">
                <?php if($hasCol('category')): ?>
                <div class="field">
                  <label class="label" for="category">Category</label>
                  <input type="text" id="category" name="category" placeholder="e.g. Web Development"
                         value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
                </div>
                <?php endif; ?>
                <?php if($hasCol('mode')): ?>
                <div class="field">
                  <label class="label" for="mode">Mode</label>
                  <select id="mode" name="mode">
                    <option value="Remote">Remote</option>
                    <option value="Onsite">Onsite</option>
                    <option value="Hybrid">Hybrid</option>
                  </select>
                </div>
                <?php endif; ?>
              </div>
              <div class="field-row">
                <?php if($hasCol('duration_weeks')): ?>
                <div class="field">
                  <label class="label" for="duration_weeks">Duration (weeks)</label>
                  <input type="number" id="duration_weeks" name="duration_weeks" min="1" value="<?= htmlspecialchars($_POST['duration_weeks'] ?? '4') ?>">
                </div>
                <?php endif; ?>
                <?php if($hasCol('skill_level')): ?>
                <div class="field">
                  <label class="label" for="skill_level">Skill Level</label>
                  <select id="skill_level" name="skill_level">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                  </select>
                </div>
                <?php endif; ?>
              </div>
              <div class="field-row">
                <?php if($hasCol('price')): ?>
                <div class="field">
                  <label class="label" for="price">Price (₹) <span style="font-weight:400;color:var(--muted)">0 = Free</span></label>
                  <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
                </div>
                <?php endif; ?>
                <?php if($hasCol('is_active')): ?>
                <div class="field" style="display:flex;align-items:flex-end">
                  <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:.65rem;font-size:.85rem;font-weight:600">
                    <input type="checkbox" name="is_active" checked style="width:auto"> Active (publicly visible)
                  </label>
                </div>
                <?php endif; ?>
              </div>
              <div style="display:flex;gap:.75rem;margin-top:.5rem">
                <button type="submit" class="btn btn-primary">Create &amp; Add Content →</button>
                <a href="internships.php" class="btn btn-outline">Cancel</a>
              </div>
            </form>
          </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
