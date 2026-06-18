<?php
/**
 * Shared sidebar partial for the manager panel.
 *
 * Usage from any page:
 *   $activeNav = 'users'; // one of: dashboard, courses, internships, users, banners,
 *                        //         reports, contacts, offline_apps, certificates,
 *                        //         coupons, referrals, activity_log
 *   include __DIR__ . '/_sidebar.php';
 *
 * Permission helpers (can(), getManagerPermissions()) must already be in scope —
 * include manager_auth.php before requiring this partial.
 */
if (!isset($activeNav)) { $activeNav = ''; }
$_role = $_SESSION['user_role'] ?? 'manager';
$_name = $_SESSION['user_name'] ?? 'Manager';

function _navItemClass(string $key, string $active): string {
    return 'nav-item' . ($key === $active ? ' active' : '');
}
?>
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
        <div class="user-badge"><?= htmlspecialchars(ucfirst($_role)) ?></div>
        <div class="user-name"><?= htmlspecialchars($_name) ?></div>
        <div class="user-email-sm">Platform Manager</div>
    </div>

    <nav>
        <div class="nav-section">Overview</div>
        <a href="manager_dashboard.php" class="<?= _navItemClass('dashboard', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>

        <?php if (can('reports_view')): ?>
        <a href="reports.php" class="<?= _navItemClass('reports', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/></svg>
            Reports
        </a>
        <?php endif; ?>

        <div class="nav-section">Content</div>
        <?php if (can('courses_view')): ?>
        <a href="courses.php" class="<?= _navItemClass('courses', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            Courses
        </a>
        <?php endif; ?>
        <?php if (can('internships_view')): ?>
        <a href="internships.php" class="<?= _navItemClass('internships', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            Internships
        </a>
        <?php endif; ?>
        <?php if (can('banners_view')): ?>
        <a href="banners.php" class="<?= _navItemClass('banners', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            Homepage Banners
        </a>
        <?php endif; ?>

        <div class="nav-section">Audience</div>
        <?php if (can('users_view')): ?>
        <a href="users.php" class="<?= _navItemClass('users', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Users
        </a>
        <?php endif; ?>
        <?php if (can('referrals_view')): ?>
        <a href="referrals.php" class="<?= _navItemClass('referrals', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3l3 3-3 3"/><path d="M21 6h-5"/></svg>
            Referrals
        </a>
        <?php endif; ?>

        <div class="nav-section">Inbox</div>
        <?php if (can('contacts_view')): ?>
        <a href="contacts.php" class="<?= _navItemClass('contacts', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Contact Messages
        </a>
        <?php endif; ?>
        <?php if (can('offline_apps_view')): ?>
        <a href="offline_apps.php" class="<?= _navItemClass('offline_apps', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Offline Applications
        </a>
        <?php endif; ?>
        <?php if (can('certificates_view')): ?>
        <a href="certificates.php" class="<?= _navItemClass('certificates', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M9 13.5V21l3-2 3 2v-7.5"/></svg>
            Certificate Requests
        </a>
        <?php endif; ?>

        <div class="nav-section">Marketing</div>
        <?php if (can('coupons_view')): ?>
        <a href="coupons.php" class="<?= _navItemClass('coupons', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9.5V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3.5a2.5 2.5 0 0 0 0 5V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-3.5a2.5 2.5 0 0 0 0-5z"/><line x1="9" y1="9" x2="9" y2="9.01"/><line x1="15" y1="15" x2="15" y2="15.01"/><line x1="9" y1="15" x2="15" y2="9"/></svg>
            Coupons
        </a>
        <?php endif; ?>

        <div class="nav-section">Logs</div>
        <a href="activity_log.php" class="<?= _navItemClass('activity_log', $activeNav) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Activity Log
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="manager_dashboard.php?logout=1" class="btn-sm btn-danger">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
        <button data-theme-toggle class="btn-sm btn-ghost" aria-label="Toggle theme" style="padding:.45rem .6rem">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
    </div>
</aside>
