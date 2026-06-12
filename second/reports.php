<?php
require_once __DIR__ . '/manager_auth.php';
requireManagerAccess();
checkPermission('reports_view');
$db = getDB();
$activeNav = 'reports';

// Helper to safely run a count query
function _safeCount(PDO $db, string $sql, array $params = []): int {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
function _safeFetch(PDO $db, string $sql, array $params = []): array {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Course stats
$totalCourses     = _safeCount($db, "SELECT COUNT(*) FROM courses");
$publishedCourses = _safeCount($db, "SELECT COUNT(*) FROM courses WHERE status='published'");
$draftCourses     = _safeCount($db, "SELECT COUNT(*) FROM courses WHERE status='draft'");

// Internship stats
$totalInternships  = _safeCount($db, "SELECT COUNT(*) FROM internships");
$activeInternships = _safeCount($db, "SELECT COUNT(*) FROM internships WHERE is_active=1");

// Enrollment stats
$totalEnrollments = _safeCount($db, "SELECT COUNT(*) FROM enrollments");
$paidEnrollments  = _safeCount($db, "SELECT COUNT(*) FROM enrollments WHERE payment_status IN ('paid','completed')");
$intEnrollments   = _safeCount($db, "SELECT COUNT(*) FROM internship_enrollments");
$intEnrollPaid    = _safeCount($db, "SELECT COUNT(*) FROM internship_enrollments WHERE payment_status='completed'");

// Users
$totalUsers     = _safeCount($db, "SELECT COUNT(*) FROM users");
$totalLearners  = _safeCount($db, "SELECT COUNT(*) FROM users WHERE role='learner'");
$newUsers30d    = _safeCount($db, "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

// Revenue (best effort)
$courseRevenue = (float)($db->query("SELECT COALESCE(SUM(amount_paid),0) FROM enrollments WHERE payment_status IN ('paid','completed')")
    ->fetchColumn() ?: 0);
$internRevenue = (float)($db->query("SELECT COALESCE(SUM(payment_amount),0) FROM internship_enrollments WHERE payment_status='completed'")
    ->fetchColumn() ?: 0);

// Communications
$contactsPending  = _safeCount($db, "SELECT COUNT(*) FROM contact_submissions WHERE status='pending'");
$certReqPending   = _safeCount($db, "SELECT COUNT(*) FROM internship_certificate_requests WHERE status='pending'");
$offlinePending   = _safeCount($db, "SELECT COUNT(*) FROM offlineinternshipapplications WHERE status='pending'");

// Top 5 courses by enrollment
$topCourses = _safeFetch($db, "
    SELECT c.id, c.title, COUNT(e.id) as enroll_count
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id
    GROUP BY c.id, c.title
    ORDER BY enroll_count DESC, c.id DESC
    LIMIT 5
");

// Top 5 internships by enrollment
$topInternships = _safeFetch($db, "
    SELECT i.id, i.title, COUNT(ie.id) as enroll_count
    FROM internships i
    LEFT JOIN internship_enrollments ie ON ie.internship_id = i.id
    GROUP BY i.id, i.title
    ORDER BY enroll_count DESC, i.id DESC
    LIMIT 5
");

// Latest 10 enrollments (course+internship merged)
$recentEnrollments = _safeFetch($db, "
    (SELECT 'course' as kind, e.id, u.name as user_name, c.title as item_title, e.enrolled_at as ts
       FROM enrollments e
       LEFT JOIN users u ON u.id = e.user_id
       LEFT JOIN courses c ON c.id = e.course_id
       ORDER BY e.enrolled_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'internship' as kind, ie.id, u.name as user_name, i.title as item_title, ie.created_at as ts
       FROM internship_enrollments ie
       LEFT JOIN users u ON u.id = ie.user_id
       LEFT JOIN internships i ON i.id = ie.internship_id
       ORDER BY ie.created_at DESC LIMIT 5)
    ORDER BY ts DESC
    LIMIT 10
");

// Daily signups, last 14 days
$signupTrend = _safeFetch($db, "
    SELECT DATE(created_at) as d, COUNT(*) as c
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d ASC
");

$maxSignup = 0;
foreach ($signupTrend as $r) { $maxSignup = max($maxSignup, (int)$r['c']); }
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports — Manager Panel</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
.report-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-top:.5rem}
@media(max-width:900px){.report-grid{grid-template-columns:1fr}}
.bar-row{display:flex;align-items:center;gap:.75rem;margin:.5rem 1.25rem;font-size:.85rem}
.bar-label{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar-track{flex:2;height:8px;background:var(--bg);border-radius:4px;overflow:hidden}
.bar-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--success));border-radius:4px}
.bar-value{font-weight:700;font-variant-numeric:tabular-nums;min-width:40px;text-align:right}
.spark{display:flex;gap:3px;align-items:flex-end;height:40px;padding:0 1.25rem}
.spark-bar{flex:1;background:var(--primary);border-radius:2px 2px 0 0;min-height:2px;opacity:.85;position:relative}
.spark-bar:hover{opacity:1}
.spark-dates{display:flex;justify-content:space-between;padding:.25rem 1.25rem .5rem;font-size:.7rem;color:var(--muted)}
</style>
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="mobile-menu-btn" id="menuBtn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb">Manager Panel / <span>Reports</span></div>
    </header>

    <main class="content">
        <div class="page-header">
            <div>
                <h1>Reports & Analytics</h1>
                <p>Platform ka overall snapshot — courses, internships, users aur revenue</p>
            </div>
        </div>

        <h3 style="font-size:.9rem;font-weight:700;color:var(--muted);margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.04em">Content</h3>
        <div class="kpi-grid">
            <div class="kpi-card accent"><div class="kpi-label">Total Courses</div><div class="kpi-value"><?= $totalCourses ?></div><div class="kpi-sub"><?= $publishedCourses ?> published, <?= $draftCourses ?> draft</div></div>
            <div class="kpi-card"><div class="kpi-label">Total Internships</div><div class="kpi-value"><?= $totalInternships ?></div><div class="kpi-sub"><?= $activeInternships ?> active</div></div>
            <div class="kpi-card"><div class="kpi-label">Course Enrollments</div><div class="kpi-value"><?= $totalEnrollments ?></div><div class="kpi-sub"><?= $paidEnrollments ?> paid</div></div>
            <div class="kpi-card"><div class="kpi-label">Internship Enrollments</div><div class="kpi-value"><?= $intEnrollments ?></div><div class="kpi-sub"><?= $intEnrollPaid ?> paid</div></div>
        </div>

        <h3 style="font-size:.9rem;font-weight:700;color:var(--muted);margin:1.5rem 0 .75rem;text-transform:uppercase;letter-spacing:.04em">Audience</h3>
        <div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-label">Total Users</div><div class="kpi-value"><?= $totalUsers ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Learners</div><div class="kpi-value"><?= $totalLearners ?></div></div>
            <div class="kpi-card"><div class="kpi-label">New (30 days)</div><div class="kpi-value"><?= $newUsers30d ?></div></div>
        </div>

        <h3 style="font-size:.9rem;font-weight:700;color:var(--muted);margin:1.5rem 0 .75rem;text-transform:uppercase;letter-spacing:.04em">Revenue</h3>
        <div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-label">Course Revenue</div><div class="kpi-value">₹<?= number_format($courseRevenue, 0) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Internship Revenue</div><div class="kpi-value">₹<?= number_format($internRevenue, 0) ?></div></div>
            <div class="kpi-card accent"><div class="kpi-label">Total Revenue</div><div class="kpi-value">₹<?= number_format($courseRevenue + $internRevenue, 0) ?></div></div>
        </div>

        <h3 style="font-size:.9rem;font-weight:700;color:var(--muted);margin:1.5rem 0 .75rem;text-transform:uppercase;letter-spacing:.04em">Action items</h3>
        <div class="kpi-grid">
            <div class="kpi-card" style="border-left:3px solid var(--orange)"><div class="kpi-label">Pending Contacts</div><div class="kpi-value" style="color:var(--orange)"><?= $contactsPending ?></div><div class="kpi-sub"><a href="contacts.php" style="color:var(--primary)">Review →</a></div></div>
            <div class="kpi-card" style="border-left:3px solid var(--orange)"><div class="kpi-label">Pending Cert Requests</div><div class="kpi-value" style="color:var(--orange)"><?= $certReqPending ?></div><div class="kpi-sub"><a href="certificates.php" style="color:var(--primary)">Review →</a></div></div>
            <div class="kpi-card" style="border-left:3px solid var(--orange)"><div class="kpi-label">Offline Apps Pending</div><div class="kpi-value" style="color:var(--orange)"><?= $offlinePending ?></div><div class="kpi-sub"><a href="offline_apps.php" style="color:var(--primary)">Review →</a></div></div>
        </div>

        <div class="report-grid" style="margin-top:1.5rem">
            <div class="section-card">
                <div class="section-head"><h2>Top 5 Courses by Enrollments</h2><a href="courses.php">View all →</a></div>
                <?php if (empty($topCourses)): ?>
                    <div class="empty-state" style="padding:1.5rem">No data yet</div>
                <?php else: foreach ($topCourses as $c): $w = $topCourses[0]['enroll_count'] > 0 ? round(($c['enroll_count'] / $topCourses[0]['enroll_count']) * 100) : 0; ?>
                    <div class="bar-row">
                        <div class="bar-label" title="<?= htmlspecialchars($c['title']) ?>"><?= htmlspecialchars($c['title']) ?></div>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= $w ?>%"></div></div>
                        <div class="bar-value"><?= (int)$c['enroll_count'] ?></div>
                    </div>
                <?php endforeach; endif; ?>
                <div style="height:.75rem"></div>
            </div>

            <div class="section-card">
                <div class="section-head"><h2>Top 5 Internships by Enrollments</h2><a href="internships.php">View all →</a></div>
                <?php if (empty($topInternships)): ?>
                    <div class="empty-state" style="padding:1.5rem">No data yet</div>
                <?php else: foreach ($topInternships as $i): $w = $topInternships[0]['enroll_count'] > 0 ? round(($i['enroll_count'] / $topInternships[0]['enroll_count']) * 100) : 0; ?>
                    <div class="bar-row">
                        <div class="bar-label" title="<?= htmlspecialchars($i['title']) ?>"><?= htmlspecialchars($i['title']) ?></div>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= $w ?>%"></div></div>
                        <div class="bar-value"><?= (int)$i['enroll_count'] ?></div>
                    </div>
                <?php endforeach; endif; ?>
                <div style="height:.75rem"></div>
            </div>
        </div>

        <div class="report-grid">
            <div class="section-card">
                <div class="section-head"><h2>Recent enrollments</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Type</th><th>User</th><th>Item</th><th>When</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentEnrollments)): ?>
                            <tr><td colspan="4" style="padding:1.5rem;text-align:center;color:var(--muted)">No enrollments yet</td></tr>
                            <?php else: foreach ($recentEnrollments as $r): ?>
                            <tr>
                                <td><span class="badge <?= $r['kind']==='course'?'badge-info':'badge-success' ?>"><?= ucfirst($r['kind']) ?></span></td>
                                <td><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($r['item_title'] ?? '—') ?></td>
                                <td style="font-size:.78rem;color:var(--muted)"><?= !empty($r['ts']) ? date('d M, H:i', strtotime($r['ts'])) : '—' ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head"><h2>Daily Signups (last 14 days)</h2></div>
                <?php if (empty($signupTrend)): ?>
                    <div class="empty-state" style="padding:1.5rem">No data</div>
                <?php else: ?>
                <div class="spark">
                    <?php foreach ($signupTrend as $row): $h = $maxSignup > 0 ? round(($row['c'] / $maxSignup) * 100) : 2; ?>
                        <div class="spark-bar" style="height:<?= max($h, 4) ?>%" title="<?= htmlspecialchars($row['d']) ?>: <?= (int)$row['c'] ?>"></div>
                    <?php endforeach; ?>
                </div>
                <div class="spark-dates">
                    <span><?= htmlspecialchars($signupTrend[0]['d']) ?></span>
                    <span><?= htmlspecialchars(end($signupTrend)['d']) ?></span>
                </div>
                <?php endif; ?>
                <div style="padding:.5rem 1.25rem 1rem;font-size:.8rem;color:var(--muted)">Peak day: <strong><?= $maxSignup ?></strong> signups</div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/_layout_js.php'; ?>
</body>
</html>
