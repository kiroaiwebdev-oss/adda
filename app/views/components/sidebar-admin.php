<?php
if (!isset($auth)) {
    die('Auth not initialized');
}

$currentUser = $auth->user();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Admin Sidebar -->
<aside class="fixed left-0 top-0 h-full w-64 bg-white border-r border-gray-200 z-40" id="adminSidebar">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-200">
           <a href="https://internshipadda.com" class="inline-flex flex-col items-center gap-3 mb-4">
        
        <img 
            src="https://internshipadda.com/icon.png" 
            alt="InternshipAdda Logo"
            class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
        >

        <span class="text-xs text-gray-500 font-medium">Admin Panel</span>

    </a>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
            <a href="/app/views/admin/dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="/app/views/admin/users/list.php" class="nav-item <?php echo $currentPage === 'list' && strpos($_SERVER['REQUEST_URI'], 'users') !== false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Users</span>
            </a>

            <!-- 🔥 CONTACT SUBMISSIONS SECTION -->
            <a href="/app/views/admin/contact/list.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'contact') !== false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span>Contact Messages</span>
                <?php
                try {
                    $stmt = $db->query("SELECT COUNT(*) FROM contact_submissions WHERE status = 'pending'");
                    $pendingCount = $stmt->fetchColumn();
                    if ($pendingCount > 0) {
                        echo '<span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">' . $pendingCount . '</span>';
                    }
                } catch (Exception $e) {}
                ?>
            </a>

            <!-- ✅ COURSES SECTION -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">📚 Courses</p>
                
                <a href="/app/views/admin/courses/list.php" class="nav-item <?php echo $currentPage === 'list' && strpos($_SERVER['REQUEST_URI'], 'courses') !== false && strpos($_SERVER['REQUEST_URI'], 'enrollments') === false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span>Manage Courses</span>
                </a>

                <a href="/app/views/admin/courses/enrollments.php" class="nav-item <?php echo $currentPage === 'enrollments' && strpos($_SERVER['REQUEST_URI'], 'courses') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span>Course Enrollments</span>
                </a>

                <a href="/app/views/admin/certificates/course-list.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'certificates/course-list') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                    <span>Course Certificates</span>
                </a>
            </div>

            <!-- ✅ INTERNSHIPS SECTION -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="px-4 text-xs font-semibold text-purple-400 uppercase tracking-wider mb-2">💼 Internships</p>
                
                <a href="/app/views/admin/internships/list.php" class="nav-item <?php echo $currentPage === 'list' && strpos($_SERVER['REQUEST_URI'], 'internships') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span>Manage Internships</span>
                </a>

                <a href="/app/views/admin/internships/enrollments.php" class="nav-item <?php echo $currentPage === 'enrollments' && strpos($_SERVER['REQUEST_URI'], 'internships') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span>Internship Enrollments</span>
                </a>

                <a href="/app/views/admin/internships/offline-applications.php" class="nav-item <?php echo $currentPage === 'offline-applications' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>Offline Applications</span>
                    <?php
                    try {
                        $stmt = $db->query("SELECT COUNT(*) FROM offline_internship_applications WHERE status = 'pending'");
                        $pendingCount = $stmt->fetchColumn();
                        if ($pendingCount > 0) {
                            echo '<span class="ml-auto bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">' . $pendingCount . '</span>';
                        }
                    } catch (Exception $e) {}
                    ?>
                </a>

                <a href="/app/views/admin/certificates/internship-requests.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'certificates/internship-requests') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span>Certificate Requests</span>
                    <?php
                    try {
                        $stmt = $db->query("SELECT COUNT(*) FROM internship_certificate_requests WHERE status = 'pending'");
                        $pendingCount = $stmt->fetchColumn();
                        if ($pendingCount > 0) {
                            echo '<span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">' . $pendingCount . '</span>';
                        }
                    } catch (Exception $e) {}
                    ?>
                </a>

                <!-- 🔥 NEW: INTERNSHIP CERTIFICATES CSV IMPORT -->
                <a href="/app/views/admin/certificates/internship-list.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'certificates/internship-list') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>Internship Certificates</span>
                </a>

                <a href="/app/views/admin/certificates/internship-issued.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'certificates/internship-issued') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <span>Issued Internship Certs</span>
                </a>
            </div>

            <a href="/app/views/admin/banners/list.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'banners') !== false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Homepage Banners</span>
            </a>

            <a href="/app/views/admin/quizzes/list.php" class="nav-item <?php echo $currentPage === 'list' && strpos($_SERVER['REQUEST_URI'], 'quizzes') !== false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <span>Quizzes</span>
            </a>

            <a href="/app/views/admin/free-courses/assign.php" class="nav-item <?php echo $currentPage === 'assign' && strpos($_SERVER['REQUEST_URI'], 'free-courses') !== false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                </svg>
                <span>Offer Free Course</span>
            </a>

            <a href="/app/views/admin/coupons/list.php" class="nav-item <?php echo $currentPage === 'list' && strpos($_SERVER['REQUEST_URI'], 'coupons') !== false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                </svg>
                <span>Coupons</span>
            </a>

            <a href="/app/views/admin/certificates/templates.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'certificates/templates') !== false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                </svg>
                <span>Certificate Templates</span>
            </a>

            <a href="/app/views/admin/analytics.php" class="nav-item <?php echo $currentPage === 'analytics' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Analytics</span>
            </a>

            <a href="/app/views/admin/settings.php" class="nav-item <?php echo $currentPage === 'settings' && strpos($_SERVER['REQUEST_URI'], 'referrals') === false ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>Settings</span>
            </a>
            
            <!-- REFERRAL SYSTEM -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Referral System</p>
                
                <a href="/app/views/admin/referrals/dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' && strpos($_SERVER['REQUEST_URI'], 'referrals') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span>Referral Overview</span>
                </a>
                
                <a href="/app/views/admin/referrals/settings.php" class="nav-item <?php echo $currentPage === 'settings' && strpos($_SERVER['REQUEST_URI'], 'referrals') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                    <span>Referral Settings</span>
                </a>
                
                <a href="/app/views/admin/referrals/coupons.php" class="nav-item <?php echo $currentPage === 'coupons' && strpos($_SERVER['REQUEST_URI'], 'referrals') !== false ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                    </svg>
                    <span>Redeemed Coupons</span>
                </a>
            </div>
        </nav>

        <!-- User Profile -->
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer" onclick="toggleProfileMenu()">
                <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-semibold">
                    <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                    <p class="text-xs text-gray-500">Super Admin</p>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>

            <div id="profileMenu" class="hidden mt-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                <a href="/" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">View Site</a>
                <a href="/api/auth.php?action=logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
            </div>
        </div>
    </div>
</aside>

<style>
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 10px;
        font-weight: 500;
        font-size: 14px;
        color: #4b5563;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .nav-item:hover {
        background: #f9fafb;
        color: #16a34a;
    }

    .nav-item.active {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        color: #16a34a;
        font-weight: 600;
    }

    .nav-item svg {
        flex-shrink: 0;
    }
</style>

<script>
    function toggleProfileMenu() {
        const menu = document.getElementById('profileMenu');
        menu.classList.toggle('hidden');
    }

    document.addEventListener('click', function(event) {
        const menu = document.getElementById('profileMenu');
        const trigger = event.target.closest('[onclick="toggleProfileMenu()"]');
        
        if (!trigger && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });
</script>
