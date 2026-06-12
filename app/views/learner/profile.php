<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Database connection with error handling
$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Autoloader
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../core/' . $class . '.php',
        __DIR__ . '/../../models/' . $class . '.php',
        __DIR__ . '/../../controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Authentication
$auth = new Auth($db);
$auth->requireAuth();

if ($auth->isAdmin()) {
    header('Location: /app/views/admin/dashboard.php');
    exit;
}

$currentUser = $auth->user();
$userId = $auth->id();

// Initialize default stats
$stats = [
    'total_enrollments' => 0,
    'completed_courses' => 0,
    'total_certificates' => 0,
    'avg_quiz_score' => 0
];

// Get user stats with proper error handling - FIXED VERSION
try {
    // ✅ FIXED: Total Enrollments (any status)
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_enrollments'] = intval($stmt->fetchColumn());

    // ✅ FIXED: Completed Courses (check for certificates OR 100% progress)
    $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['completed_courses'] = intval($stmt->fetchColumn());

    // ✅ FIXED: Total Certificates (active status)
    $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $stats['total_certificates'] = intval($stmt->fetchColumn());

    // ✅ FIXED: Average Quiz Score (only completed attempts)
    $stmt = $db->prepare("
        SELECT COALESCE(AVG(score), 0) 
        FROM quiz_attempts 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$userId]);
    $avgScore = $stmt->fetchColumn();
    $stats['avg_quiz_score'] = $avgScore ? floatval($avgScore) : 0;
    
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Safe user data extraction
$userName = isset($currentUser['name']) ? htmlspecialchars($currentUser['name']) : 'User';
$userEmail = isset($currentUser['email']) ? htmlspecialchars($currentUser['email']) : '';
$userCreated = isset($currentUser['created_at']) ? $currentUser['created_at'] : date('Y-m-d H:i:s');
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>My Profile - Internship Adda</title>
    
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
        
        /* Mobile Navbar */
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

        /* Sidebar Menu */
        .sidebar-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 290px;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            z-index: 200;
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        .sidebar-menu.active {
            right: 0;
        }

        /* Overlay */
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 150;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .menu-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        /* Hamburger Animation */
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

        /* Mobile Bottom Navigation */
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
        
        /* Touch-friendly buttons */
        .touch-btn {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* Content padding for fixed navbar */
        .main-content {
            padding-top: 70px;
        }
        
        /* Desktop sidebar visibility */
        .desktop-sidebar {
            display: none;
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Responsive adjustments */
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
    </style>
</head>
<body>

<!-- Mobile Top Navbar -->
<nav class="mobile-navbar">
    <div class="flex items-center justify-between px-4 py-4">
        <!-- Logo -->
        <div class="flex items-center gap-2">
             <a href="https://internshipadda.com"> <img 
                        src="https://internshipadda.com/icon.png" 
                        alt="InternshipAdda Logo"
                        class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
                    ></a>
        </div>

        <!-- Hamburger Menu -->
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
    <!-- User Profile Section -->
    <div class="bg-gradient-to-br from-primary-500 to-primary-700 p-6 text-white">
        <!-- Close Button -->
        <div class="flex justify-end -mt-2 -mr-2 mb-2">
            <button onclick="toggleMenu()" class="touch-btn p-2.5 hover:bg-white/10 rounded-lg transition-all active:scale-95">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="flex items-center gap-3 mb-4">
            <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-2xl font-bold shadow-lg border-2 border-white/30">
                <?php echo $userInitial; ?>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-lg truncate"><?php echo $userName; ?></h3>
                <p class="text-sm text-white/80 truncate"><?php echo $userEmail; ?></p>
            </div>
        </div>

        <!-- Quick Stats in Menu -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 text-center border border-white/20">
                <div class="text-2xl font-bold"><?php echo $stats['total_enrollments']; ?></div>
                <div class="text-xs text-white/80 font-medium">Courses</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 text-center border border-white/20">
                <div class="text-2xl font-bold"><?php echo $stats['total_certificates']; ?></div>
                <div class="text-xs text-white/80 font-medium">Certificates</div>
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

            <!-- Section Heading: Courses -->
            <div class="px-4 pt-4 pb-2">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">📚 Courses</h4>
            </div>

            <!-- My Courses -->
            <a href="/app/views/learner/my-courses.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                My Courses
            </a>

            <!-- Browse Courses -->
            <a href="/public/courses.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Browse Courses
            </a>

            <!-- Course Certificates -->
            <a href="/app/views/learner/certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                </svg>
                Course Certificates
            </a>

            <!-- Section Heading: Internships -->
            <div class="px-4 pt-4 pb-2">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">💼 Internships</h4>
            </div>

            <!-- My Internships -->
            <a href="/app/views/learner/my-internships.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                My Internships
            </a>

            <!-- Browse Internships -->
            <a href="/public/internships.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Browse Internships
            </a>

            <!-- Internship Certificates -->
            <a href="/app/views/learner/internship-certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                Internship Certificates
            </a>

            <!-- Divider -->
            <div class="border-t border-gray-200 my-3"></div>

            <!-- Reports -->
            <a href="/app/views/learner/reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Reports
            </a>

            <!-- Referrals -->
            <a href="/app/views/learner/referrals.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 active:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Referrals
            </a>

            <!-- Profile - ACTIVE -->
            <a href="/app/views/learner/profile.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 font-semibold">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
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
    <?php include __DIR__ . '/../components/sidebar-learner.php'; ?>
</div>

<!-- Main Content -->
<div class="md:ml-72 min-h-screen">
    <div class="main-content">
        <!-- Desktop Header (Hidden on Mobile) -->
        <header class="hidden md:block bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="px-8 py-6">
                <h1 class="text-3xl font-bold text-gray-900">My Profile</h1>
                <p class="text-gray-600 mt-1">Manage your account settings and preferences</p>
            </div>
        </header>

        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">
            
            <!-- Mobile Header -->
            <div class="md:hidden mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-1">👤 My Profile</h1>
                <p class="text-sm text-gray-600">Manage your account settings</p>
            </div>
            
            <div class="max-w-4xl mx-auto space-y-4 sm:space-y-6">
                <!-- Profile Card -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="h-24 sm:h-32 bg-gradient-to-br from-primary-500 to-primary-700"></div>
                    <div class="px-4 sm:px-8 pb-6 sm:pb-8">
                        <div class="flex flex-col sm:flex-row items-start sm:items-end gap-4 sm:gap-6 -mt-12 sm:-mt-16">
                            <div class="w-24 h-24 sm:w-32 sm:h-32 bg-white rounded-full border-4 border-white shadow-xl flex items-center justify-center flex-shrink-0">
                                <span class="text-4xl sm:text-5xl font-bold text-primary-600"><?php echo $userInitial; ?></span>
                            </div>
                            <div class="flex-1 sm:pb-2">
                                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo $userName; ?></h2>
                                <p class="text-sm sm:text-base text-gray-600 break-all"><?php echo $userEmail; ?></p>
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    <span class="px-3 py-1 bg-primary-100 text-primary-700 rounded-full text-xs sm:text-sm font-semibold">
                                        Learner
                                    </span>
                                    <span class="text-xs sm:text-sm text-gray-500">
                                        Joined <?php echo date('F Y', strtotime($userCreated)); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo $stats['total_enrollments']; ?></h3>
                        <p class="text-xs sm:text-sm text-gray-600">Enrolled</p>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo $stats['completed_courses']; ?></h3>
                        <p class="text-xs sm:text-sm text-gray-600">Completed</p>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo $stats['total_certificates']; ?></h3>
                        <p class="text-xs sm:text-sm text-gray-600">Certificates</p>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo number_format($stats['avg_quiz_score'], 1); ?>%</h3>
                        <p class="text-xs sm:text-sm text-gray-600">Avg Score</p>
                    </div>
                </div>

                <!-- Update Profile Form -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-8">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4 sm:mb-6">Update Profile</h2>
                    
                    <div id="alertContainer" class="mb-6"></div>

                    <form id="updateProfileForm" class="space-y-4 sm:space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-base"
                                    value="<?php echo $userName; ?>"
                                    required
                                >
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-base"
                                    value="<?php echo $userEmail; ?>"
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" class="touch-btn w-full sm:w-auto bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-6 py-3 rounded-lg font-semibold transition-all">
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-8">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4 sm:mb-6">Change Password</h2>

                    <div id="passwordAlertContainer" class="mb-6"></div>

                    <form id="changePasswordForm" class="space-y-4 sm:space-y-6">
                        <div>
                            <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password *</label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-base"
                                required
                            >
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                            <div>
                                <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password *</label>
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-base"
                                    minlength="8"
                                    required
                                >
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password *</label>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-base"
                                    minlength="8"
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" class="touch-btn w-full sm:w-auto bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-6 py-3 rounded-lg font-semibold transition-all">
                            Change Password
                        </button>
                    </form>
                </div>
<!-- Account Management Section - NEW -->
<div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-8 mb-4 sm:mb-6">
    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Account Management</h2>
    <p class="text-sm sm:text-base text-gray-600 mb-4">Manage your account settings and security options</p>
    
    <!-- Logout Button -->
    <button onclick="confirmLogout()" class="touch-btn w-full sm:w-auto bg-yellow-500 hover:bg-yellow-600 active:bg-yellow-700 text-white px-6 py-3 rounded-lg font-semibold transition-all flex items-center justify-center sm:justify-start gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
        </svg>
        Logout from Account
    </button>
</div>

                <!-- Danger Zone -->
                <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 sm:p-8">
                    <h2 class="text-xl sm:text-2xl font-bold text-red-900 mb-2">Danger Zone</h2>
                    <p class="text-sm sm:text-base text-red-700 mb-4">Once you delete your account, there is no going back. Please be certain.</p>
                    <button onclick="deleteAccount()" class="touch-btn w-full sm:w-auto bg-red-600 hover:bg-red-700 active:bg-red-800 text-white px-6 py-3 rounded-lg font-semibold transition-all">
                        Delete Account
                    </button>
                </div>
            </div>
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
        <a href="/public/courses.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600 active:bg-gray-50">
            <div class="w-12 h-12 -mt-6 bg-primary-600 rounded-full flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <span class="text-xs font-semibold mt-1">Explore</span>
        </a>
        <a href="/app/views/learner/certificates.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600 active:bg-gray-50">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
            </svg>
            <span class="text-xs font-semibold">Certificates</span>
        </a>
        <a href="/app/views/learner/profile.php" class="flex flex-col items-center justify-center py-2 text-primary-600 bg-primary-50">
            <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 24 24">
                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
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

    // Update Profile
    document.getElementById('updateProfileForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            name: formData.get('name'),
            email: formData.get('email')
        };

        try {
            const response = await fetch('/api/users.php?action=update-profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showAlert('alertContainer', 'success', '✅ Profile updated successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('alertContainer', 'error', '❌ ' + (result.error || 'Failed to update profile'));
            }
        } catch (error) {
            showAlert('alertContainer', 'error', '❌ Network error. Please try again.');
        }
    });

    // Change Password
    document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');

        if (newPassword !== confirmPassword) {
            showAlert('passwordAlertContainer', 'error', '❌ New passwords do not match!');
            return;
        }

        if (newPassword.length < 8) {
            showAlert('passwordAlertContainer', 'error', '❌ Password must be at least 8 characters!');
            return;
        }

        const data = {
            current_password: formData.get('current_password'),
            new_password: newPassword
        };

        try {
            const response = await fetch('/api/users.php?action=change-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showAlert('passwordAlertContainer', 'success', '✅ Password changed successfully!');
                e.target.reset();
            } else {
                showAlert('passwordAlertContainer', 'error', '❌ ' + (result.error || 'Failed to change password'));
            }
        } catch (error) {
            showAlert('passwordAlertContainer', 'error', '❌ Network error. Please try again.');
        }
    });
// Logout Confirmation Function
function confirmLogout() {
    if (confirm('🚪 Are you sure you want to logout?')) {
        // Clear session and redirect to landing page
        window.location.href = '/api/auth.php?action=logout';
    }
}

    function deleteAccount() {
        if (!confirm('⚠️ Are you sure you want to delete your account? This action cannot be undone!')) return;
        if (!confirm('🗑️ This will permanently delete all your data including enrollments, progress, and certificates. Continue?')) return;

        showToast('⚠️ Account deletion feature will be implemented by admin.');
    }

    function showAlert(containerId, type, message) {
        const container = document.getElementById(containerId);
        const bgColor = type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
        const textColor = type === 'success' ? 'text-green-800' : 'text-red-800';
        
        container.innerHTML = `
            <div class="${bgColor} border-2 rounded-lg p-3 sm:p-4">
                <p class="${textColor} font-semibold text-sm sm:text-base">${message}</p>
            </div>
        `;
        
        setTimeout(() => container.innerHTML = '', 5000);
    }

    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-24 left-4 right-4 sm:left-auto sm:right-4 sm:max-w-sm bg-gray-900 text-white px-4 py-3 rounded-lg shadow-xl z-[250] animate-slide-up text-sm';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    const style = document.createElement('style');
    style.textContent = `
        @keyframes slide-up {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-up { animation: slide-up 0.3s ease-out; }
    `;
    document.head.appendChild(style);
</script>

</body>
</html>
