<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

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
$auth->requireAuth();
$currentUser = $auth->user();
$userId = $auth->id();

// Get stats for menu
$stats = [
    'total_enrollments' => 0,
    'total_certificates' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_enrollments'] = intval($stmt->fetchColumn());

    $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $stats['total_certificates'] = intval($stmt->fetchColumn());
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Referrals & Points - Internship Adda</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
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
        
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .stat-card:active {
            transform: scale(0.98);
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
            
            .stat-card {
                padding: 24px;
            }
            
            .stat-card:hover {
                box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1);
                border-color: rgba(34, 197, 94, 0.3);
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
                <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-lg truncate"><?php echo htmlspecialchars($currentUser['name']); ?></h3>
                <p class="text-sm text-white/80 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
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

            <!-- Referrals - ACTIVE -->
            <a href="/app/views/learner/referrals.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 font-semibold">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
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
    <?php include __DIR__ . '/../components/sidebar-learner.php'; ?>
</div>

<!-- Main Content -->
<div class="md:ml-72 min-h-screen">
    <div class="main-content">
        <!-- Desktop Header (Hidden on Mobile) -->
        <header class="hidden md:block bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="px-8 py-6">
                <h1 class="text-3xl font-bold text-gray-900">Referrals & Points</h1>
                <p class="text-gray-600 mt-1">Refer friends and earn rewards! 💰</p>
            </div>
        </header>

        <!-- Content -->
        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">
            
            <!-- Mobile Header -->
            <div class="md:hidden mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-1">💰 Referrals & Points</h1>
                <p class="text-sm text-gray-600">Refer friends and earn rewards</p>
            </div>
            
            <!-- Loading State -->
            <div id="loading" class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-primary-500 border-t-transparent"></div>
                <p class="text-gray-600 mt-4 text-sm sm:text-base">Loading your referral data...</p>
            </div>

            <!-- Main Content (Hidden until loaded) -->
            <div id="content" class="hidden">
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
                    <!-- Total Points -->
                    <div class="stat-card bg-gradient-to-br from-primary-50 to-primary-100 col-span-2 lg:col-span-1">
                        <div class="flex items-center justify-between mb-3 sm:mb-4">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-primary-500 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-2xl sm:text-3xl font-bold text-primary-900" id="totalPoints">0</h3>
                        <p class="text-xs sm:text-sm text-primary-700 mt-1">Available Points</p>
                        <p class="text-xs text-primary-600 mt-2">Worth: ₹<span id="pointsValue">0</span></p>
                    </div>

                    <!-- Total Referrals -->
                    <div class="stat-card">
                        <div class="flex items-center justify-between mb-3 sm:mb-4">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-900" id="totalReferrals">0</h3>
                        <p class="text-xs sm:text-sm text-gray-600 mt-1">Total Referrals</p>
                    </div>

                    <!-- Completed -->
                    <div class="stat-card">
                        <div class="flex items-center justify-between mb-3 sm:mb-4">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-900" id="completedReferrals">0</h3>
                        <p class="text-xs sm:text-sm text-gray-600 mt-1">Completed</p>
                    </div>

                    <!-- Pending -->
                    <div class="stat-card">
                        <div class="flex items-center justify-between mb-3 sm:mb-4">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-900" id="pendingReferrals">0</h3>
                        <p class="text-xs sm:text-sm text-gray-600 mt-1">Pending</p>
                    </div>
                </div>

                <!-- Referral Link Section -->
                <div class="stat-card mb-6 sm:mb-8">
                    <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                        <div class="flex-1 w-full">
                            <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">Your Referral Link</h3>
                            <p class="text-xs sm:text-sm text-gray-600 mb-4">Share this link. Friends get <span id="discountPercent" class="font-bold text-primary-600">40%</span> off, you get <span id="bonusPoints" class="font-bold text-primary-600">100</span> points!</p>
                            
                            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                                <input 
                                    type="text" 
                                    id="referralLink" 
                                    readonly 
                                    class="flex-1 px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-300 rounded-lg bg-gray-50 text-gray-700 font-mono text-xs sm:text-sm"
                                    value="Loading..."
                                >
                                <button 
                                    onclick="copyLink()" 
                                    class="touch-btn px-4 sm:px-6 py-2.5 sm:py-3 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold rounded-lg transition-all flex items-center justify-center gap-2 text-sm sm:text-base"
                                >
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    Copy Link
                                </button>
                            </div>
                            
                            <p class="text-xs text-gray-500 mt-3">Your Code: <span id="referralCode" class="font-mono font-bold text-primary-600">Loading...</span></p>
                        </div>
                    </div>
                </div>

                <!-- Redeem Points Section -->
                <div class="stat-card mb-6 sm:mb-8 bg-gradient-to-br from-yellow-50 to-orange-50 border-yellow-200">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">💎 Redeem Your Points</h3>
                            <p class="text-xs sm:text-sm text-gray-600">Convert points to discount coupons (Min: <span id="minRedemption">50</span> points)</p>
                        </div>
                        <button 
                            onclick="openRedeemModal()" 
                            id="redeemBtn"
                            class="touch-btn w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 active:from-yellow-700 active:to-orange-700 text-white font-bold rounded-lg transition-all shadow-lg text-sm sm:text-base"
                        >
                            Redeem Points
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="stat-card">
                    <div class="border-b border-gray-200 mb-4 sm:mb-6 -mx-4 sm:-mx-6 px-4 sm:px-6 overflow-x-auto">
                        <div class="flex gap-4 sm:gap-6 min-w-max sm:min-w-0">
                            <button onclick="switchTab('referrals')" id="tabReferrals" class="tab-btn active pb-3 sm:pb-4 px-2 font-semibold text-primary-600 border-b-2 border-primary-600 text-sm sm:text-base whitespace-nowrap">
                                My Referrals
                            </button>
                            <button onclick="switchTab('earnings')" id="tabEarnings" class="tab-btn pb-3 sm:pb-4 px-2 font-semibold text-gray-500 border-b-2 border-transparent hover:text-primary-600 text-sm sm:text-base whitespace-nowrap">
                                Earnings
                            </button>
                            <button onclick="switchTab('coupons')" id="tabCoupons" class="tab-btn pb-3 sm:pb-4 px-2 font-semibold text-gray-500 border-b-2 border-transparent hover:text-primary-600 text-sm sm:text-base whitespace-nowrap">
                                🎟️ Coupons
                            </button>
                        </div>
                    </div>

                    <!-- Referrals List -->
                    <div id="referralsList">
                        <div id="referralsContent"></div>
                    </div>

                    <!-- Earnings List -->
                    <div id="earningsList" class="hidden">
                        <div id="earningsContent"></div>
                    </div>

                    <!-- Coupons List -->
                    <div id="couponsList" class="hidden">
                        <div id="couponsContent"></div>
                    </div>
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
        <a href="/app/views/learner/profile.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600 active:bg-gray-50">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="text-xs font-semibold">Profile</span>
        </a>
    </div>
</nav>

<!-- Redeem Modal -->
<div id="redeemModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-[200] p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 sm:p-8">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl sm:text-2xl font-bold text-gray-900">Redeem Points</h3>
            <button onclick="closeRedeemModal()" class="touch-btn text-gray-400 hover:text-gray-600 p-2 -mr-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Points to Redeem</label>
            <input 
                type="number" 
                id="redeemPointsInput" 
                min="50" 
                step="10"
                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-base"
                placeholder="Enter points amount"
            >
            <p class="text-xs text-gray-500 mt-2">Available: <span id="modalAvailablePoints" class="font-bold">0</span> points</p>
            <p class="text-sm text-primary-600 font-semibold mt-2">Coupon Value: ₹<span id="couponValuePreview">0</span></p>
        </div>

        <button 
            onclick="confirmRedeem()" 
            class="touch-btn w-full bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-6 py-3 rounded-lg font-semibold transition-all"
        >
            Generate Coupon
        </button>
    </div>
</div>

<script>
    let dashboardData = null;

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

    // Load dashboard data
    async function loadDashboard() {
        try {
            const response = await fetch('/api/referrals.php?action=dashboard');
            const result = await response.json();

            if (result.success) {
                dashboardData = result.data;
                updateUI(result.data);
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('content').classList.remove('hidden');
            } else {
                showToast('❌ Failed to load data: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('❌ Network error. Please refresh the page.');
        }
    }

    function updateUI(data) {
        // Update stats
        document.getElementById('totalPoints').textContent = data.total_points;
        document.getElementById('pointsValue').textContent = data.points_value.toFixed(2);
        document.getElementById('totalReferrals').textContent = data.stats.total_referrals;
        document.getElementById('completedReferrals').textContent = data.stats.completed_referrals;
        document.getElementById('pendingReferrals').textContent = data.stats.pending_referrals;

        // Update link
        document.getElementById('referralLink').value = data.referral_link;
        document.getElementById('referralCode').textContent = data.referral_code;

        // Update settings display
        document.getElementById('discountPercent').textContent = data.settings.signup_discount + '%';
        document.getElementById('bonusPoints').textContent = data.settings.points_per_referral;
        document.getElementById('minRedemption').textContent = data.settings.min_redemption;

        // Enable/disable redeem button
        const redeemBtn = document.getElementById('redeemBtn');
        if (data.total_points < data.settings.min_redemption) {
            redeemBtn.disabled = true;
            redeemBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    function copyLink() {
        const linkInput = document.getElementById('referralLink');
        linkInput.select();
        linkInput.setSelectionRange(0, 99999);
        
        navigator.clipboard.writeText(linkInput.value).then(() => {
            showToast('✅ Referral link copied!');
        }).catch(() => {
            document.execCommand('copy');
            showToast('✅ Referral link copied!');
        });
    }

    // Load referrals list
    async function loadReferrals() {
        try {
            const response = await fetch('/api/referrals.php?action=list');
            const result = await response.json();

            if (result.success) {
                displayReferrals(result.referrals);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function displayReferrals(referrals) {
        const container = document.getElementById('referralsContent');
        
        if (referrals.length === 0) {
            container.innerHTML = '<div class="text-center py-12 text-gray-500 text-sm sm:text-base">No referrals yet. Start sharing your link! 🚀</div>';
            return;
        }

        container.innerHTML = referrals.map(ref => `
            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 sm:p-4 bg-gray-50 rounded-lg mb-3 hover:bg-gray-100 transition-colors gap-3 sm:gap-0">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-bold text-base sm:text-lg flex-shrink-0">
                        ${ref.referred_user_name.charAt(0).toUpperCase()}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900 text-sm sm:text-base truncate">${ref.referred_user_name}</p>
                        <p class="text-xs text-gray-500 truncate">${ref.referred_user_email}</p>
                        <p class="text-xs text-gray-500 mt-1">Joined: ${new Date(ref.signup_date).toLocaleDateString()}</p>
                    </div>
                </div>
                <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-2 sm:text-right">
                    <span class="px-2.5 sm:px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap ${ref.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                        ${ref.status === 'completed' ? '✓ Completed' : '⏳ Pending'}
                    </span>
                    ${ref.points_earned > 0 ? `<p class="text-sm font-bold text-primary-600">+${ref.points_earned} pts</p>` : ''}
                </div>
            </div>
        `).join('');
    }

    // Load earnings history
    async function loadEarnings() {
        try {
            const response = await fetch('/api/referrals.php?action=earnings');
            const result = await response.json();

            if (result.success) {
                displayEarnings(result.earnings);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function displayEarnings(earnings) {
        const container = document.getElementById('earningsContent');
        
        if (earnings.length === 0) {
            container.innerHTML = '<div class="text-center py-12 text-gray-500 text-sm sm:text-base">No earnings yet.</div>';
            return;
        }

        container.innerHTML = earnings.map(earning => `
            <div class="flex items-center justify-between p-3 sm:p-4 border-b border-gray-200 last:border-0 gap-3">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 text-sm sm:text-base">${earning.description}</p>
                    <p class="text-xs text-gray-500 truncate">${earning.referred_user_name}</p>
                    <p class="text-xs text-gray-400 mt-1">${new Date(earning.created_at).toLocaleString()}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-base sm:text-lg font-bold ${earning.transaction_type === 'credit' ? 'text-green-600' : 'text-red-600'}">
                        ${earning.transaction_type === 'credit' ? '+' : '-'}${earning.points_earned}
                    </p>
                    <p class="text-xs text-gray-500">${earning.points_type.replace('_', ' ')}</p>
                </div>
            </div>
        `).join('');
    }

    // Load my coupons
    async function loadMyCoupons() {
        try {
            const response = await fetch('/api/referrals.php?action=my-coupons');
            const result = await response.json();

            if (result.success) {
                displayCoupons(result.coupons);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function displayCoupons(coupons) {
        const container = document.getElementById('couponsContent');
        
        if (coupons.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <div class="text-5xl sm:text-6xl mb-4">🎟️</div>
                    <p class="text-gray-500 text-base sm:text-lg font-semibold">No coupons yet</p>
                    <p class="text-gray-400 text-xs sm:text-sm mt-2">Redeem your points to generate coupons!</p>
                </div>
            `;
            return;
        }

        container.innerHTML = coupons.map(coupon => {
            const isUsed = coupon.used_count > 0;
            const isExpired = coupon.valid_until && new Date(coupon.valid_until) < new Date();
            
            return `
                <div class="p-4 sm:p-6 mb-4 rounded-xl border-2 ${isUsed ? 'bg-gray-50 border-gray-300' : isExpired ? 'bg-red-50 border-red-200' : 'bg-gradient-to-br from-green-50 to-emerald-50 border-green-300'} transition-all hover:shadow-lg">
                    <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                        <div class="flex-1 w-full">
                            <!-- Coupon Code -->
                            <div class="flex items-center gap-2 sm:gap-3 mb-3 flex-wrap">
                                <code class="text-lg sm:text-2xl font-mono font-bold ${isUsed ? 'text-gray-500' : 'text-primary-700'} bg-white px-3 sm:px-4 py-2 rounded-lg border-2 border-dashed ${isUsed ? 'border-gray-300' : 'border-primary-400'}">
                                    ${coupon.code}
                                </code>
                                ${!isUsed && !isExpired ? `
                                    <button onclick="copyCoupon('${coupon.code}')" class="touch-btn p-2 hover:bg-white rounded-lg transition-colors" title="Copy code">
                                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                    </button>
                                ` : ''}
                            </div>
                            
                            <!-- Coupon Details -->
                            <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-3">
                                <div>
                                    <p class="text-xs text-gray-500">Discount Value</p>
                                    <p class="text-lg sm:text-xl font-bold text-gray-900">₹${parseFloat(coupon.discount_value).toFixed(2)}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Points Redeemed</p>
                                    <p class="text-lg sm:text-xl font-bold text-orange-600">${coupon.points_redeemed || 0} pts</p>
                                </div>
                            </div>
                            
                            <!-- Dates -->
                            <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 text-xs text-gray-600">
                                <div>
                                    <span class="font-semibold">Generated:</span> ${new Date(coupon.created_at).toLocaleDateString()}
                                </div>
                                ${coupon.valid_until ? `
                                    <div>
                                        <span class="font-semibold">Expires:</span> ${new Date(coupon.valid_until).toLocaleDateString()}
                                    </div>
                                ` : ''}
                            </div>
                            
                            ${coupon.used_at ? `
                                <div class="mt-3 p-2 bg-white rounded-lg border border-gray-200">
                                    <p class="text-xs text-gray-600">✓ Used on ${new Date(coupon.used_at).toLocaleDateString()}</p>
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="sm:flex-shrink-0">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap ${
                                isUsed ? 'bg-gray-200 text-gray-700' : 
                                isExpired ? 'bg-red-200 text-red-700' : 
                                'bg-green-200 text-green-700'
                            }">
                                ${isUsed ? '✓ Used' : isExpired ? '⏰ Expired' : '🎉 Active'}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function copyCoupon(code) {
        navigator.clipboard.writeText(code).then(() => {
            showToast('✅ Coupon code copied: ' + code);
        }).catch(() => {
            // Fallback
            const temp = document.createElement('input');
            temp.value = code;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            showToast('✅ Coupon code copied: ' + code);
        });
    }

    function switchTab(tab) {
        // Update buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active', 'text-primary-600', 'border-primary-600');
            btn.classList.add('text-gray-500', 'border-transparent');
        });

        const activeBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        activeBtn.classList.add('active', 'text-primary-600', 'border-primary-600');
        activeBtn.classList.remove('text-gray-500', 'border-transparent');

        // Toggle content
        document.getElementById('referralsList').classList.add('hidden');
        document.getElementById('earningsList').classList.add('hidden');
        document.getElementById('couponsList').classList.add('hidden');
        
        if (tab === 'referrals') {
            document.getElementById('referralsList').classList.remove('hidden');
            loadReferrals();
        } else if (tab === 'earnings') {
            document.getElementById('earningsList').classList.remove('hidden');
            loadEarnings();
        } else if (tab === 'coupons') {
            document.getElementById('couponsList').classList.remove('hidden');
            loadMyCoupons();
        }
    }

    function openRedeemModal() {
        if (!dashboardData) return;
        
        document.getElementById('modalAvailablePoints').textContent = dashboardData.total_points;
        document.getElementById('redeemPointsInput').max = dashboardData.total_points;
        document.getElementById('redeemModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeRedeemModal() {
        document.getElementById('redeemModal').classList.add('hidden');
        document.getElementById('redeemPointsInput').value = '';
        document.getElementById('couponValuePreview').textContent = '0';
        document.body.style.overflow = '';
    }

    document.getElementById('redeemPointsInput').addEventListener('input', function(e) {
        const points = parseInt(e.target.value) || 0;
        const value = points * (dashboardData?.settings?.point_value || 1);
        document.getElementById('couponValuePreview').textContent = value.toFixed(2);
    });

    async function confirmRedeem() {
        const points = parseInt(document.getElementById('redeemPointsInput').value);
        
        if (!points || points < (dashboardData?.settings?.min_redemption || 50)) {
            showToast('❌ Please enter valid points amount!');
            return;
        }

        if (points > dashboardData.total_points) {
            showToast('❌ Insufficient points!');
            return;
        }

        try {
            const response = await fetch('/api/referrals.php?action=redeem', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ points })
            });

            const result = await response.json();

            if (result.success) {
                showToast(`🎉 Coupon generated! Code: ${result.coupon.coupon_code}`);
                closeRedeemModal();
                loadDashboard(); // Refresh data
                switchTab('coupons'); // Switch to coupons tab
            } else {
                showToast('❌ Error: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('❌ Network error. Please try again.');
        }
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

    // Close modal on outside click
    document.getElementById('redeemModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRedeemModal();
        }
    });

    // Initialize
    window.addEventListener('DOMContentLoaded', () => {
        loadDashboard();
        loadReferrals();
    });
</script>

</body>
</html>
