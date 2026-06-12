<?php
// Error debugging - REMOVE in production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    require_once __DIR__ . '/../../config/database.php';
    
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (Exception $e) {
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
try {
    $auth = new Auth($db);
    $auth->requireAuth();
    
    if ($auth->isAdmin()) {
        header('Location: /app/views/admin/dashboard.php');
        exit;
    }
    
    $currentUser = $auth->user();
    $userId = $auth->id();
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

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

// Get user certificates
$certificates = [];
try {
    $stmt = $db->prepare("
        SELECT 
            cert.*,
            COALESCE(cert.certificate_number, cert.certificate_code) as display_number,
            c.title as course_title,
            c.description as course_description
        FROM certificates cert
        INNER JOIN courses c ON cert.course_id = c.id
        WHERE cert.user_id = ? AND cert.status = 'active'
        ORDER BY cert.issued_date DESC
    ");
    $stmt->execute([$userId]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Certificates error: " . $e->getMessage());
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
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
        
        .cert-card {
            background: white; 
            border: 1px solid #e5e7eb; 
            border-radius: 16px; 
            overflow: hidden; 
            transition: all 0.3s ease;
        }
        .cert-card:active {
            transform: scale(0.98);
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            
            .cert-card:hover {
                box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1); 
                border-color: rgba(34, 197, 94, 0.3); 
                transform: translateY(-2px);
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
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
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

            <!-- Course Certificates - ACTIVE -->
            <a href="/app/views/learner/certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 font-semibold">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                My Internships
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
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">My Certificates 🎓</h1>
                        <p class="text-gray-600 mt-1">View and download your earned certificates</p>
                    </div>
                    <a href="/app/views/learner/my-courses.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        My Courses
                    </a>
                </div>
            </div>
        </header>

        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">
            
            <!-- Mobile Header -->
            <div class="md:hidden mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-1">🎓 My Certificates</h1>
                <p class="text-sm text-gray-600">View and download your achievements</p>
            </div>
            
            <?php if (empty($certificates)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl border-2 border-dashed border-gray-300 p-8 sm:p-12 text-center">
                    <div class="max-w-md mx-auto">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 sm:w-12 sm:h-12 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">No Certificates Yet</h2>
                        <p class="text-sm sm:text-base text-gray-600 mb-6">Complete a course to earn your first certificate and showcase your achievements!</p>
                        <a href="/app/views/learner/my-courses.php" class="touch-btn inline-block bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-6 py-3 rounded-lg font-semibold transition-all text-sm sm:text-base">
                            Go to My Courses
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Certificates Count Badge -->
                <div class="mb-6">
                    <div class="inline-flex items-center gap-2 bg-purple-50 border border-purple-200 rounded-lg px-4 py-2">
                        <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
                            <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                        </svg>
                        <span class="text-sm font-semibold text-purple-700"><?php echo count($certificates); ?> Certificate<?php echo count($certificates) > 1 ? 's' : ''; ?> Earned</span>
                    </div>
                </div>
                
                <!-- Certificates Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <?php foreach ($certificates as $cert): ?>
                        <div class="cert-card">
                            <!-- Certificate Header with Gradient -->
                            <div class="bg-gradient-to-br from-purple-500 via-blue-500 to-green-500 h-32 sm:h-40 relative flex items-center justify-center">
                                <div class="text-center text-white">
                                    <svg class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-2 opacity-90" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
                                        <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                                    </svg>
                                    <p class="text-xs sm:text-sm font-semibold">Certificate of Completion</p>
                                </div>
                                <div class="absolute top-2 right-2 sm:top-3 sm:right-3 bg-white/20 backdrop-blur-sm rounded-full px-2 sm:px-3 py-1">
                                    <span class="text-xs text-white font-medium">✓ Verified</span>
                                </div>
                            </div>
                            
                            <!-- Certificate Body -->
                            <div class="p-4 sm:p-6">
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-3 min-h-[3rem] line-clamp-2">
                                    <?php echo htmlspecialchars($cert['course_title']); ?>
                                </h3>
                                
                                <div class="space-y-2 mb-5">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-500">Certificate Number</p>
                                            <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($cert['display_number']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-500">Issued Date</p>
                                            <p class="text-sm font-semibold text-gray-700">
                                                <?php echo date('F j, Y', strtotime($cert['issued_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex gap-2">
                                    <button 
                                        onclick="viewCertificate(<?php echo $cert['id']; ?>, <?php echo $cert['course_id']; ?>)" 
                                        class="touch-btn flex-1 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white py-2.5 px-4 rounded-lg font-semibold transition-all text-sm flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        View
                                    </button>
                                    <button 
                                        onclick="downloadCertificate(<?php echo $cert['id']; ?>, <?php echo $cert['course_id']; ?>)" 
                                        class="touch-btn flex-1 bg-gray-600 hover:bg-gray-700 active:bg-gray-800 text-white py-2.5 px-4 rounded-lg font-semibold transition-all text-sm flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
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
        <a href="/app/views/learner/certificates.php" class="flex flex-col items-center justify-center py-2 text-primary-600 bg-primary-50">
            <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
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

// ✅ FIXED: Open certificate in new tab (full page with styling)
function viewCertificate(certId, courseId) {
    showToast('📄 Opening certificate...');
    window.open(`/api/generate-certificate.php?id=${certId}&course_id=${courseId}`, '_blank');
}

// Download certificate
function downloadCertificate(certId, courseId) {
    showToast('📥 Downloading certificate...');
    window.open(`/api/generate-certificate.php?id=${certId}&course_id=${courseId}&download=1`, '_blank');
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-24 left-4 right-4 sm:left-auto sm:right-4 sm:max-w-sm bg-gray-900 text-white px-4 py-3 rounded-lg shadow-xl z-[250] animate-slide-up';
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
