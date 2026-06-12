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

// ✅ Get ONLY issued certificates for this user
$issuedCertificates = [];
try {
    $stmt = $db->prepare("
        SELECT 
            ic.id,
            ic.certificate_number,
            ic.certificate_code,
            ic.student_name,
            ic.company_name,
            ic.internship_title,
            ic.start_date,
            ic.end_date,
            ic.duration_months,
            ic.issued_at,
            ct.template_name,
            ct.id as template_id
        FROM internship_certificates ic
        LEFT JOIN certificate_templates ct ON ic.template_id = ct.id
        WHERE ic.user_id = ? AND ic.revoked = 0
        ORDER BY ic.issued_at DESC
    ");
    $stmt->execute([$userId]);
    $issuedCertificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Certificate fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>My Certificates - Internship Adda</title>
    
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

        /* ===== MOBILE NAVBAR - FIXED TOP ===== */
        .mobile-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            z-index: 100;
            height: 64px;
        }

        /* ===== SIDEBAR MENU - RIGHT SLIDE ===== */
        .sidebar-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 85%;
            max-width: 320px;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 24px rgba(0,0,0,0.2);
            z-index: 200;
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sidebar-menu.active {
            right: 0;
        }

        /* ===== OVERLAY ===== */
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
            backdrop-filter: blur(2px);
        }

        .menu-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        /* ===== HAMBURGER ANIMATION ===== */
        .hamburger {
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 44px;
            height: 44px;
            cursor: pointer;
            position: relative;
        }

        .hamburger span {
            display: block;
            width: 24px;
            height: 3px;
            background: #374151;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 3px;
            margin: 3px auto;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
            transform: translateX(-20px);
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* ===== MOBILE BOTTOM NAV ===== */
        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -2px 16px rgba(0,0,0,0.1);
            z-index: 50;
            height: 72px;
        }

        /* ===== TOUCH-FRIENDLY BUTTONS ===== */
        .touch-btn {
            min-height: 44px;
            min-width: 44px;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        .touch-btn:active {
            transform: scale(0.96);
        }

        /* ===== CERTIFICATE CARDS ===== */
        .cert-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .cert-card:active {
            transform: scale(0.98);
        }

        /* ===== CONTENT PADDING FOR FIXED NAVBAR ===== */
        .main-content {
            padding-top: 64px;
        }

        /* ===== DESKTOP SIDEBAR HIDDEN ===== */
        .desktop-sidebar {
            display: none;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ===== LINE CLAMP ===== */
        .line-clamp-2 { 
            display: -webkit-box; 
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical; 
            overflow: hidden; 
        }

        /* ===== SMOOTH SCROLLING ===== */
        html {
            scroll-behavior: smooth;
        }

        /* Sidebar menu items hover effect */
        .sidebar-menu a {
            transition: all 0.2s ease;
        }

        .sidebar-menu a:active {
            transform: scale(0.98);
        }

        /* ===== DESKTOP BREAKPOINT ===== */
        @media (min-width: 769px) {
            /* Hide mobile elements */
            .mobile-navbar,
            .mobile-nav {
                display: none;
            }

            /* Show desktop sidebar */
            .desktop-sidebar {
                display: block;
            }

            /* Remove mobile padding */
            .main-content {
                padding-top: 0;
            }

            body {
                padding-bottom: 0;
            }

            /* Desktop hover effects */
            .cert-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(34, 197, 94, 0.15);
                border-color: rgba(34, 197, 94, 0.3);
            }

            .cert-card:active {
                transform: translateY(-2px);
            }
        }

        /* ===== TABLET ADJUSTMENTS ===== */
        @media (max-width: 768px) and (min-width: 481px) {
            .sidebar-menu {
                max-width: 360px;
            }
        }

        /* ===== SMALL MOBILE ADJUSTMENTS ===== */
        @media (max-width: 360px) {
            .mobile-navbar {
                height: 56px;
            }

            .main-content {
                padding-top: 56px;
            }

            .mobile-nav {
                height: 64px;
            }

            body {
                padding-bottom: 64px;
            }
        }

        /* Prevent body scroll when menu is open */
        body.menu-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
    </style>
</head>
<body>

<!-- Mobile Top Navbar -->
<nav class="mobile-navbar">
    <div class="flex items-center justify-between px-4 py-3 h-full">
        <!-- Logo -->
        <div class="flex items-center gap-2">
             <a href="https://internshipadda.com"> <img 
                        src="https://internshipadda.com/icon.png" 
                        alt="InternshipAdda Logo"
                        class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
                    ></a>
        </div>

        <!-- Hamburger Menu -->
        <button onclick="toggleMenu()" class="touch-btn hamburger rounded-lg hover:bg-gray-100 active:bg-gray-200 transition-colors p-2" id="hamburger">
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
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-lg truncate"><?php echo htmlspecialchars($user['name']); ?></h3>
                <p class="text-sm text-white/80 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="bg-white/10 backdrop-blur rounded-lg p-3 text-center border border-white/20">
            <div class="text-2xl font-bold"><?php echo count($issuedCertificates); ?></div>
            <div class="text-xs text-white/80 font-medium">Total Certificates</div>
        </div>
    </div>

    <!-- Menu Items -->
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
    <?php include __DIR__ . '/../components/sidebar-learner.php'; ?>
</div>

<!-- Main Content -->
<div class="md:ml-64 min-h-screen">
    <div class="main-content">
        <!-- Desktop Header (Hidden on Mobile) -->
        <header class="hidden md:block bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">🎓 My Certificates</h1>
                        <p class="text-gray-600 mt-1">View and download your earned certificates</p>
                    </div>
                    <a href="/app/views/learner/my-internships.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        My Internships
                    </a>
                </div>
            </div>
        </header>

        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">
            <!-- Stats -->
            <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-2 border-purple-200 rounded-xl p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-purple-600 to-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm text-purple-600 font-semibold mb-0.5 sm:mb-1">Total Certificates Earned</p>
                        <p class="text-2xl sm:text-3xl md:text-4xl font-bold text-purple-900"><?= count($issuedCertificates) ?> Certificate<?= count($issuedCertificates) !== 1 ? 's' : '' ?></p>
                    </div>
                </div>
            </div>

            <?php if (empty($issuedCertificates)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl sm:rounded-2xl border-2 border-dashed border-gray-300 p-6 sm:p-8 md:p-12 text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4 sm:mb-6">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 mb-2 sm:mb-3">No Certificates Yet</h3>
                    <p class="text-sm sm:text-base text-gray-600 mb-6 sm:mb-8 max-w-md mx-auto px-4">Complete internships to earn your certificates and showcase your achievements.</p>
                    <a href="/app/views/learner/my-internships.php" 
                       class="touch-btn inline-flex items-center gap-2 bg-gradient-to-r from-primary-600 to-green-600 hover:from-primary-700 hover:to-green-700 active:scale-95 text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl font-bold text-sm sm:text-base md:text-lg transition-all shadow-xl hover:shadow-2xl">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span>Browse Internships</span>
                    </a>
                </div>
            <?php else: ?>
                <!-- Certificates Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <?php foreach ($issuedCertificates as $cert): ?>
                        <div class="cert-card">
                            <!-- Certificate Preview -->
                            <div class="bg-gradient-to-br from-purple-500 via-blue-500 to-cyan-500 p-6 sm:p-8 text-center relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-24 h-24 sm:w-32 sm:h-32 bg-white/10 rounded-full -mr-12 sm:-mr-16 -mt-12 sm:-mt-16"></div>
                                <div class="absolute bottom-0 left-0 w-20 h-20 sm:w-24 sm:h-24 bg-white/10 rounded-full -ml-10 sm:-ml-12 -mb-10 sm:-mb-12"></div>
                                <div class="relative z-10">
                                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-white/20 backdrop-blur rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                        </svg>
                                    </div>
                                    <span class="inline-block px-2.5 sm:px-3 py-0.5 sm:py-1 bg-white/20 backdrop-blur rounded-full text-white text-xs font-bold mb-2 sm:mb-3">
                                        ✓ Verified
                                    </span>
                                    <h3 class="text-white text-base sm:text-lg font-bold">Certificate of Completion</h3>
                                </div>
                            </div>

                            <!-- Certificate Details -->
                            <div class="p-4 sm:p-6">
                                <h4 class="text-lg sm:text-xl font-bold text-gray-900 mb-3 line-clamp-2">
                                    <?= htmlspecialchars($cert['internship_title']) ?>
                                </h4>

                                <div class="space-y-2 mb-4 sm:mb-6">
                                    <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                        <span class="font-medium truncate"><?= htmlspecialchars($cert['company_name']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span><?= $cert['duration_months'] ?> month<?= $cert['duration_months'] > 1 ? 's' : '' ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="truncate">Issued: <?= date('M d, Y', strtotime($cert['issued_at'])) ?></span>
                                    </div>
                                </div>

                                <!-- Certificate Number -->
                                <div class="bg-gray-50 rounded-lg p-2.5 sm:p-3 mb-3 sm:mb-4">
                                    <p class="text-xs text-gray-500 font-semibold mb-0.5 sm:mb-1">Certificate Number</p>
                                    <p class="text-xs sm:text-sm font-mono font-bold text-gray-900 truncate"><?= htmlspecialchars($cert['certificate_number']) ?></p>
                                </div>

                                <!-- Actions -->
                                <div class="space-y-2">
                                    <a href="/app/views/learner/certificate-view.php?id=<?= $cert['id'] ?>" 
                                       target="_blank"
                                       class="touch-btn flex items-center justify-center gap-2 w-full bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-4 py-2.5 sm:py-3 rounded-lg font-bold transition-all shadow-md hover:shadow-lg text-sm sm:text-base">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        View
                                    </a>
                                    <button onclick="downloadCertificate(<?= $cert['id'] ?>)" 
                                            class="touch-btn flex items-center justify-center gap-2 w-full bg-gray-600 hover:bg-gray-700 active:bg-gray-800 text-white px-4 py-2.5 sm:py-3 rounded-lg font-bold transition-all text-sm sm:text-base"
                                            id="download-btn-<?= $cert['id'] ?>">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                        </svg>
                                        Download
                                    </button>
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
        <a href="/app/views/learner/dashboard.php" class="flex flex-col items-center justify-center text-gray-600 hover:text-primary-600 active:bg-gray-50 transition-colors">
            <svg class="w-6 h-6 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span class="text-xs font-semibold">Home</span>
        </a>
        <a href="/app/views/learner/my-courses.php" class="flex flex-col items-center justify-center text-gray-600 hover:text-primary-600 active:bg-gray-50 transition-colors">
            <svg class="w-6 h-6 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span class="text-xs font-semibold">Courses</span>
        </a>
        <a href="/public/internships.php" class="flex flex-col items-center justify-center text-gray-600 hover:text-primary-600 active:bg-gray-50 transition-colors">
            <div class="w-14 h-14 -mt-7 bg-gradient-to-r from-primary-600 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <span class="text-xs font-semibold mt-1">Explore</span>
        </a>
        <a href="/app/views/learner/my-internships.php" class="flex flex-col items-center justify-center text-gray-600 hover:text-primary-600 active:bg-gray-50 transition-colors">
            <svg class="w-6 h-6 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span class="text-xs font-semibold">Internships</span>
        </a>
        <a href="/app/views/learner/profile.php" class="flex flex-col items-center justify-center text-gray-600 hover:text-primary-600 active:bg-gray-50 transition-colors">
            <svg class="w-6 h-6 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    document.body.classList.toggle('menu-open');
}

function downloadCertificate(id) {
    const btn = document.getElementById('download-btn-' + id);
    const originalHTML = btn.innerHTML;
    
    // Show loading
    btn.disabled = true;
    btn.innerHTML = `
        <svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    `;
    
    // Open certificate with autoprint
    window.open('/app/views/learner/certificate-view.php?id=' + id + '&autoprint=1', '_blank');
    
    // Show success after delay
    setTimeout(() => {
        btn.innerHTML = `
            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>Ready!</span>
        `;
        
        // Reset after 2 seconds
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }, 2000);
    }, 1000);
}

// Close menu when clicking on links
document.addEventListener('DOMContentLoaded', function() {
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            toggleMenu();
        });
    });

    // Prevent scroll issues on iOS
    const menuOverlay = document.getElementById('menuOverlay');
    menuOverlay.addEventListener('touchmove', function(e) {
        e.preventDefault();
    }, { passive: false });
});
</script>

</body>
</html>
