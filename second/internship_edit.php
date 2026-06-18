<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('internships_edit');

$db = getDB();
$activeNav = 'internships';
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
    $status      = $_POST['status'] ?? ($intern['status'] ?? '');
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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Internship — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn" aria-label="Open menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="breadcrumb">Manager Panel / <a href="internships.php" style="color:var(--primary)">Internships</a> / <span>Edit #<?= $id ?></span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Edit Internship #<?= $id ?></h1>
                <p>Internship details update karo ya content builder me modules/lessons manage karo.</p>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a href="internship_builder.php?id=<?= $id ?>" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    Content Builder
                </a>
                <a href="internships.php" class="btn btn-outline">← Back</a>
            </div>
        </div>

        <div class="alert alert-warn">🔒 Manager Mode — Sirf edit allowed. Delete nahi kar sakte.</div>

        <?php if($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" style="max-width:760px">
            <div class="card" style="margin-bottom:1.25rem">
              <div class="section-head"><h2>Basic Information</h2></div>
              <div style="padding:1.25rem">
                <div class="field">
                  <label class="label" for="title">Internship Title *</label>
                  <input type="text" id="title" name="title" required value="<?= htmlspecialchars($intern['title']) ?>" placeholder="e.g. Full Stack Development Internship">
                </div>
                <?php if(hasCol('description', $existingCols)): ?>
                <div class="field">
                  <label class="label" for="description">Description</label>
                  <textarea id="description" name="description" style="min-height:120px" placeholder="Internship ke baare mein batao..."><?= htmlspecialchars($intern['description'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>
                <div class="field-row">
                  <?php if(hasCol('duration', $existingCols)): ?>
                  <div class="field">
                    <label class="label" for="duration">Duration</label>
                    <input type="text" id="duration" name="duration" value="<?= htmlspecialchars($intern['duration'] ?? '') ?>" placeholder="e.g. 4 weeks, 2 months">
                  </div>
                  <?php endif; ?>
                  <?php if(hasCol('stipend', $existingCols)): ?>
                  <div class="field">
                    <label class="label" for="stipend">Stipend (₹) <span style="font-weight:400;color:var(--muted)">0 = unpaid</span></label>
                    <input type="number" id="stipend" name="stipend" min="0" step="100" value="<?= htmlspecialchars($intern['stipend'] ?? '0') ?>">
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="card" style="margin-bottom:1.25rem">
              <div class="section-head"><h2>Status &amp; Settings</h2></div>
              <div style="padding:1.25rem">
                <div class="field-row">
                  <?php if(hasCol('status', $existingCols)): ?>
                  <div class="field">
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status">
                      <?php
                      $currentStatus = $intern['status'] ?? 'draft';
                      foreach(['active','inactive','open','closed','published','draft'] as $opt): ?>
                      <option value="<?= $opt ?>" <?= $currentStatus===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php endif; ?>
                  <?php if(hasCol('skills', $existingCols)): ?>
                  <div class="field">
                    <label class="label" for="skills">Skills Required <span style="font-weight:400;color:var(--muted)">(comma separated)</span></label>
                    <input type="text" id="skills" name="skills" value="<?= htmlspecialchars($intern['skills'] ?? '') ?>" placeholder="PHP, MySQL, HTML, CSS">
                  </div>
                  <?php endif; ?>
                </div>
                <?php if(!hasCol('status',$existingCols) && !hasCol('skills',$existingCols)): ?>
                <p style="font-size:.82rem;color:var(--muted)">Is internship ke active/inactive status aur baaki settings Content Builder se manage hote hain.</p>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
              <button type="submit" class="btn btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Changes
              </button>
              <a href="internship_builder.php?id=<?= $id ?>" class="btn btn-outline">Go to Content Builder</a>
              <a href="internships.php" class="btn btn-outline">Cancel</a>
            </div>

            <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.78rem;color:var(--muted);padding-top:.9rem;margin-top:.75rem">
              <?php if(isset($intern['created_at'])): ?><span>Created: <?= date('d M Y, H:i', strtotime($intern['created_at'])) ?></span><?php endif; ?>
              <?php $updCol = hasCol('updated_at',$existingCols)?'updated_at':(hasCol('updatedat',$existingCols)?'updatedat':null); ?>
              <?php if($updCol && !empty($intern[$updCol])): ?><span>Last updated: <?= date('d M Y, H:i', strtotime($intern[$updCol])) ?></span><?php endif; ?>
              <span>ID: #<?= $id ?></span>
            </div>
        </form>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
