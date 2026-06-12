<?php
// Ensure auth is available
if (!isset($auth)) {
    die('Auth not initialized');
}

$currentUser = $auth->user();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Learner Sidebar -->
<aside class="fixed left-0 top-0 h-full w-64 bg-white border-r border-gray-200 z-40 transition-transform duration-300" id="learnerSidebar">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-200">
            <a href="https://internshipadda.com" class="inline-flex flex-col items-center gap-3 mb-4">
        
        <img 
            src="https://internshipadda.com/icon.png" 
            alt="InternshipAdda Logo"
            class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
        >

       <span class="text-xs text-gray-500 font-medium">Learning Portal</span>

    </a>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
            <!-- Dashboard -->
            <a href="/app/views/learner/dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>

            <!-- ✅ COURSES SECTION -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">📚 Courses</p>
                
                <a href="/app/views/learner/my-courses.php" class="nav-item <?php echo $currentPage === 'my-courses' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span>My Courses</span>
                </a>

                <a href="/public/courses.php" class="nav-item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span>Browse Courses</span>
                </a>

                <a href="/app/views/learner/certificates.php" class="nav-item <?php echo $currentPage === 'certificates' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                    <span>Course Certificates</span>
                </a>
            </div>

            <!-- ✅ INTERNSHIPS SECTION -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="px-4 text-xs font-semibold text-purple-400 uppercase tracking-wider mb-2">💼 Internships</p>
                
                <a href="/app/views/learner/my-internships.php" class="nav-item <?php echo $currentPage === 'my-internships' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span>My Internships</span>
                </a>
      <a href="/public/internships.php" class="nav-item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span>Browse Internships</span>
                </a>
                <a href="/app/views/learner/internship-certificates.php" class="nav-item <?php echo $currentPage === 'internship-certificates' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                    </svg>
                    <span>Internship Certificates</span>
                </a>

                <a href="/app/views/learner/internship-report.php" class="nav-item <?php echo $currentPage === 'internship-report' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Internship Report</span>
                </a>
            </div>

            <!-- Reports -->
            <a href="/app/views/learner/reports.php" class="nav-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Reports</span>
            </a>

            <!-- Referrals -->
            <a href="/app/views/learner/referrals.php" class="nav-item <?php echo $currentPage === 'referrals' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Referrals & Points</span>
            </a>

            <!-- Profile -->
            <a href="/app/views/learner/profile.php" class="nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>Profile</span>
            </a>
        </nav>

        <!-- User Profile -->
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer" onclick="toggleProfileMenu()">
                <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-semibold">
                    <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                    <p class="text-xs text-gray-500">Learner</p>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>

            <!-- Profile Dropdown -->
            <div id="profileMenu" class="hidden mt-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                <a href="/" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Home</a>
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

    // Close profile menu when clicking outside
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('profileMenu');
        const trigger = event.target.closest('[onclick="toggleProfileMenu()"]');
        
        if (!trigger && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });
</script>
