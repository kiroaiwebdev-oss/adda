<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed");
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../core/' . $class . '.php',
        __DIR__ . '/../../models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);

if (!$auth->check()) {
    header('Location: /public/login.php');
    exit;
}

if ($auth->isAdmin()) {
    header('Location: /app/views/admin/dashboard.php');
    exit;
}

$user = $auth->user();
$userId = $auth->id();

// Get enrolled internships
try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            ie.id as enrollment_id,
            ie.status as enrollment_status,
            ie.payment_id,
            ie.payment_amount,
            ie.created_at as enrolled_at
        FROM internships i
        INNER JOIN internship_enrollments ie ON i.id = ie.internship_id
        WHERE ie.user_id = ?
        ORDER BY ie.created_at DESC
    ");
    $stmt->execute([$userId]);
    $myInternships = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching internships: ' . $e->getMessage());
    $myInternships = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>My Internships - Internship Adda</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                            800: '#166534', 900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #fafafa;
            padding-bottom: 80px;
        }
        h1, h2, h3, h4, h5, h6 { 
            font-family: 'Poppins', sans-serif; 
            font-weight: 700; 
        }

        .mobile-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }

        .sidebar-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 280px;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            z-index: 200;
            transition: right 0.3s ease-in-out;
            overflow-y: auto;
        }

        .sidebar-menu.active {
            right: 0;
        }

        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 150;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }

        .menu-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .hamburger span {
            display: block;
            width: 24px;
            height: 2px;
            background: #374151;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .hamburger span:nth-child(2) {
            margin: 5px 0;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 50;
        }

        .touch-btn {
            min-height: 44px;
            min-width: 44px;
        }

        .main-content {
            padding-top: 70px;
        }

        .desktop-sidebar {
            display: none;
        }

        @media (min-width: 769px) {
            .mobile-navbar,
            .mobile-nav {
                display: none;
            }

            .desktop-sidebar {
                display: block;
            }

            .main-content {
                padding-top: 0;
            }

            body {
                padding-bottom: 0;
            }
        }

        html {
            scroll-behavior: smooth;
        }

        .line-clamp-2 { 
            display: -webkit-box; 
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical; 
            overflow: hidden; 
        }

        .internship-card {
            transition: all 0.3s ease;
        }

        .internship-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(34, 197, 94, 0.15);
        }

        .internship-card:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>

<!-- Mobile Top Navbar -->
<nav class="mobile-navbar">
    <div class="flex items-center justify-between px-4 py-4">
        <div class="flex items-center gap-2">
             <a href="https://internshipadda.com"> <img 
                        src="https://internshipadda.com/icon.png" 
                        alt="InternshipAdda Logo"
                        class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
                    ></a>
        </div>

        <button onclick="toggleMenu()" class="touch-btn hamburger p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 transition-colors" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<!-- Menu Overlay -->
<div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

<!-- Sidebar Menu -->
<div class="sidebar-menu" id="sidebarMenu">
    <div class="bg-gradient-to-br from-primary-500 to-primary-700 p-6 text-white">
        <div class="flex justify-end -mt-2 -mr-2 mb-2">
            <button onclick="toggleMenu()" class="touch-btn p-2.5 hover:bg-white/10 rounded-lg transition-all active:scale-95">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="flex items-center gap-3 mb-4">
            <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-2xl font-bold shadow-lg border-2 border-white/30">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-lg truncate"><?php echo htmlspecialchars($user['name']); ?></h3>
                <p class="text-sm text-white/80 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 text-center border border-white/20">
                <div class="text-2xl font-bold"><?php echo count($myInternships); ?></div>
                <div class="text-xs text-white/80 font-medium">Internships</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 text-center border border-white/20">
                <div class="text-2xl font-bold">
                    <?php 
                    $completedCount = 0;
                    foreach ($myInternships as $i) {
                        if (strtolower($i['enrollment_status']) === 'completed') $completedCount++;
                    }
                    echo $completedCount;
                    ?>
                </div>
                <div class="text-xs text-white/80 font-medium">Completed</div>
            </div>
        </div>
    </div>

  <!-- Menu Items -->
<div class="p-4">
    <div class="space-y-1">
        <!-- Dashboard -->
        <a href="/app/views/learner/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
            </svg>
            Dashboard
        </a>

        <!-- Section: Courses -->
        <div class="px-4 pt-4 pb-2">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">📚 Courses</h4>
        </div>

        <a href="/app/views/learner/my-courses.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            My Courses
        </a>

        <a href="/public/courses.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Browse Courses
        </a>

        <a href="/app/views/learner/certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
            </svg>
            Course Certificates
        </a>

        <!-- Section: Internships -->
        <div class="px-4 pt-4 pb-2">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">💼 Internships</h4>
        </div>

        <a href="/app/views/learner/my-internships.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            My Internships
        </a>

        <a href="/public/internships.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Browse Internships
        </a>

        <a href="/app/views/learner/internship-certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 font-semibold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            Internship Certificates
        </a>

        <!-- Divider -->
        <div class="border-t border-gray-200 my-3"></div>

        <!-- ✅ NEW: Reports -->
        <a href="/app/views/learner/reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Reports
        </a>

        <!-- ✅ NEW: Referrals -->
        <a href="/app/views/learner/referrals.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            Referrals
        </a>

        <!-- Profile -->
        <a href="/app/views/learner/profile.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            Profile
        </a>
    </div>

    <!-- Divider -->
    <div class="border-t border-gray-200 my-4"></div>

    <!-- Logout -->
    <a href="/api/auth.php?action=logout" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 active:bg-red-100 transition-colors font-semibold">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
        </svg>
        Logout
    </a>
</div>

</div>

<!-- Desktop Sidebar -->
<div class="desktop-sidebar">
    <?php
    $sidebarFile = __DIR__ . '/../components/sidebar-learner.php';
    if (file_exists($sidebarFile)) {
        include $sidebarFile;
    }
    ?>
</div>

<!-- Main Content -->
<div class="md:ml-64 min-h-screen">
    <div class="main-content">
        <!-- Desktop Header -->
        <header class="hidden md:block bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">💼 My Internships</h1>
                        <p class="text-gray-600 mt-1">Track your enrolled internship programs</p>
                    </div>
                    <a href="/public/internships.php" class="bg-gradient-to-r from-primary-600 to-green-600 hover:from-primary-700 hover:to-green-700 text-white px-6 py-3 rounded-lg font-semibold transition-all shadow-lg">
                        🔍 Browse Internships
                    </a>
                </div>
            </div>
        </header>

        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">
            
            <?php if (isset($_GET['success']) && $_GET['success'] === 'certificate_requested'): ?>
                <div class="mb-6 bg-green-50 border-2 border-green-200 text-green-800 px-6 py-4 rounded-xl">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold">🎓 Certificate request submitted successfully! Admin will review it soon.</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($myInternships)): ?>
                <div class="bg-white rounded-2xl border-2 border-dashed border-gray-300 p-8 sm:p-12 text-center">
                    <div class="w-20 h-20 bg-gradient-to-br from-green-100 to-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">No Internships Yet</h3>
                    <p class="text-gray-600 mb-8 max-w-md mx-auto">You haven't enrolled in any internship programs yet. Start your professional journey today!</p>
                    <a href="/public/internships.php" 
                       class="inline-flex items-center gap-2 bg-gradient-to-r from-primary-600 to-green-600 hover:from-primary-700 hover:to-green-700 text-white px-8 py-4 rounded-xl font-bold text-lg transition-all shadow-xl hover:shadow-2xl active:scale-95">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <span>Browse Internships</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($myInternships as $internship): ?>
                        <?php
                        // Calculate completion percentage
                        $totalLessonsStmt = $db->prepare("
                            SELECT COUNT(DISTINCT il.id) as total
                            FROM internship_lessons il
                            INNER JOIN internship_modules im ON il.module_id = im.id
                            WHERE im.internship_id = ?
                        ");
                        $totalLessonsStmt->execute([$internship['id']]);
                        $totalLessons = $totalLessonsStmt->fetch(PDO::FETCH_ASSOC)['total'];

                        $completedLessonsStmt = $db->prepare("
                            SELECT COUNT(DISTINCT ilp.lesson_id) as completed
                            FROM internship_lesson_progress ilp
                            INNER JOIN internship_lessons il ON ilp.lesson_id = il.id
                            INNER JOIN internship_modules im ON il.module_id = im.id
                            WHERE im.internship_id = ? AND ilp.student_id = ? AND ilp.is_completed = 1
                        ");
                        $completedLessonsStmt->execute([$internship['id'], $userId]);
                        $completedLessons = $completedLessonsStmt->fetch(PDO::FETCH_ASSOC)['completed'];

                        $completionPercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

                        // Check if certificate already requested
                        $certCheckStmt = $db->prepare("SELECT * FROM internship_certificate_requests WHERE user_id = ? AND internship_id = ?");
                        $certCheckStmt->execute([$userId, $internship['id']]);
                        $certificateRequest = $certCheckStmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        
                        <div class="internship-card bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-lg">
                            <!-- Cover Image -->
                            <?php if (!empty($internship['cover_image'])): ?>
                                <div class="relative h-48 overflow-hidden">
                                    <img src="<?= htmlspecialchars($internship['cover_image']) ?>" 
                                         alt="<?= htmlspecialchars($internship['title']) ?>" 
                                         class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
                                    
                                    <div class="absolute top-4 right-4">
                                        <?php
                                        $status = strtolower($internship['enrollment_status']);
                                        $badges = [
                                            'applied' => ['bg' => 'bg-blue-500', 'text' => 'Applied', 'icon' => '📝'],
                                            'accepted' => ['bg' => 'bg-green-500', 'text' => 'Accepted', 'icon' => '✅'],
                                            'active' => ['bg' => 'bg-green-500', 'text' => 'Active', 'icon' => '⚡'],
                                            'completed' => ['bg' => 'bg-purple-500', 'text' => 'Completed', 'icon' => '🎓'],
                                            'rejected' => ['bg' => 'bg-red-500', 'text' => 'Rejected', 'icon' => '❌']
                                        ];
                                        $badge = $badges[$status] ?? ['bg' => 'bg-gray-500', 'text' => ucfirst($status), 'icon' => '📌'];
                                        ?>
                                        <span class="<?= $badge['bg'] ?> text-white px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm">
                                            <?= $badge['icon'] ?> <?= $badge['text'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="relative h-48 bg-gradient-to-br from-green-400 via-emerald-500 to-teal-600 flex items-center justify-center">
                                    <svg class="w-20 h-20 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <div class="absolute top-4 right-4">
                                        <?php
                                        $status = strtolower($internship['enrollment_status']);
                                        $badges = [
                                            'applied' => ['bg' => 'bg-blue-500', 'text' => 'Applied', 'icon' => '📝'],
                                            'accepted' => ['bg' => 'bg-green-500', 'text' => 'Accepted', 'icon' => '✅'],
                                            'active' => ['bg' => 'bg-green-500', 'text' => 'Active', 'icon' => '⚡'],
                                            'completed' => ['bg' => 'bg-purple-500', 'text' => 'Completed', 'icon' => '🎓'],
                                            'rejected' => ['bg' => 'bg-red-500', 'text' => 'Rejected', 'icon' => '❌']
                                        ];
                                        $badge = $badges[$status] ?? ['bg' => 'bg-gray-500', 'text' => ucfirst($status), 'icon' => '📌'];
                                        ?>
                                        <span class="<?= $badge['bg'] ?> text-white px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm">
                                            <?= $badge['icon'] ?> <?= $badge['text'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Content -->
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2">
                                    <?= htmlspecialchars($internship['title']) ?>
                                </h3>

                                <div class="flex flex-wrap gap-2 mb-4">
                                    <?php if (!empty($internship['company_name'])): ?>
                                        <span class="text-xs font-semibold text-primary-600 bg-primary-50 px-3 py-1 rounded-full">
                                            🏢 <?= htmlspecialchars($internship['company_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($internship['category'])): ?>
                                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                                            📂 <?= htmlspecialchars($internship['category']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Progress Bar -->
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-700">Progress</span>
                                        <span class="text-sm font-bold text-primary-600"><?php echo $completionPercentage; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-2.5 rounded-full transition-all" 
                                             style="width: <?php echo $completionPercentage; ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $completedLessons; ?> of <?php echo $totalLessons; ?> lessons completed</p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="space-y-3">
                                    <a href="/app/views/learner/internship-player.php?id=<?= $internship['id'] ?>" 
                                       class="block w-full bg-gradient-to-r from-primary-600 to-green-600 hover:from-primary-700 hover:to-green-700 text-white text-center px-4 py-3 rounded-xl font-semibold transition-all shadow-md">
                                        <?php echo $completionPercentage > 0 ? '▶️ Continue Learning' : '🚀 Start Learning'; ?>
                                    </a>
                                    
                                    <?php if ($completionPercentage >= 100): ?>
                                        <?php if ($certificateRequest): ?>
                                            <!-- Already Requested -->
                                            <button class="w-full bg-gray-100 text-gray-600 px-4 py-3 rounded-xl font-semibold cursor-not-allowed" disabled>
                                                <div class="flex items-center justify-center gap-2">
                                                    <?php if ($certificateRequest['status'] === 'approved'): ?>
                                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <span class="text-green-600 font-bold">Certificate Approved ✓</span>
                                                    <?php elseif ($certificateRequest['status'] === 'rejected'): ?>
                                                        <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <span class="text-red-600 font-bold">Certificate Rejected</span>
                                                    <?php else: ?>
                                                        <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <span class="text-yellow-600 font-bold">Certificate Pending ⏳</span>
                                                    <?php endif; ?>
                                                </div>
                                            </button>
                                        <?php else: ?>
                                            <!-- Request Certificate Button -->
                                            <a href="/app/views/learner/request-internship-certificate.php?id=<?= $internship['id'] ?>" 
                                               class="block w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white text-center px-4 py-3 rounded-xl font-semibold shadow-md transition-all">
                                                <div class="flex items-center justify-center gap-2">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                                                    </svg>
                                                    🎓 Get Certificate
                                                </div>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav">
    <div class="grid grid-cols-5 h-full">
        <a href="/app/views/learner/dashboard.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600 active:bg-gray-50">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span class="text-xs font-semibold">Home</span>
        </a>
        <a href="/app/views/learner/my-courses.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600 active:bg-gray-50">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span class="text-xs font-semibold">Courses</span>
        </a>
        <a href="/public/internships.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600 active:bg-gray-50">
            <div class="w-12 h-12 -mt-6 bg-gradient-to-r from-primary-600 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <span class="text-xs font-semibold mt-1">Explore</span>
        </a>
        <a href="/app/views/learner/my-internships.php" class="flex flex-col items-center justify-center py-2 text-primary-600 bg-primary-50">
            <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                <path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z"/>
            </svg>
            <span class="text-xs font-semibold">Internships</span>
        </a>
        <a href="/app/views/learner/profile.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600 active:bg-gray-50">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="text-xs font-semibold">Profile</span>
        </a>
    </div>
</nav>

<script>
function toggleMenu() {
    const menu = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('menuOverlay');
    const hamburger = document.getElementById('hamburger');

    menu.classList.toggle('active');
    overlay.classList.toggle('active');
    hamburger.classList.toggle('active');

    if (menu.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 769) {
            toggleMenu();
        }
    });
});
</script>

</body>
</html>
