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

// Helper function to safely decode and output text
function safe_output($text) {
    return htmlspecialchars(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

// Check what columns exist in topic_progress table
$columnsStmt = $db->query("SHOW COLUMNS FROM topic_progress");
$columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
$hasIsCompleted = in_array('is_completed', $columns);
$hasCompletedAt = in_array('completed_at', $columns);

// Get stats for menu
$stats = [
    'total_enrollments' => 0,
    'total_certificates' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_enrollments'] = intval($stmt->fetchColumn());

    $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_certificates'] = intval($stmt->fetchColumn());
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Get all enrollments - FIXED VERSION WITH COVER IMAGE
$enrollments = [];
try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            c.id as course_id,
            c.title,
            c.description,
            c.price,
            c.cover_image
        FROM enrollments e
        INNER JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$userId]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add topics count for each course
    foreach ($enrollments as &$enrollment) {
        $courseId = $enrollment['course_id'];
        
        // Count total topics
        try {
            $topicStmt = $db->prepare("
                SELECT COUNT(*) 
                FROM topics t 
                JOIN chapters ch ON t.chapter_id = ch.id 
                WHERE ch.course_id = ?
            ");
            $topicStmt->execute([$courseId]);
            $enrollment['total_topics'] = intval($topicStmt->fetchColumn());
        } catch (Exception $e) {
            $enrollment['total_topics'] = 0;
        }
        
        // Count completed topics - DYNAMIC QUERY BASED ON TABLE STRUCTURE
        try {
            if ($hasIsCompleted) {
                $completedStmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM topic_progress tp 
                    JOIN topics t ON tp.topic_id = t.id 
                    JOIN chapters ch ON t.chapter_id = ch.id 
                    WHERE tp.user_id = ? AND ch.course_id = ? AND tp.is_completed = 1
                ");
            } else {
                $completedStmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM topic_progress tp 
                    JOIN topics t ON tp.topic_id = t.id 
                    JOIN chapters ch ON t.chapter_id = ch.id 
                    WHERE tp.user_id = ? AND ch.course_id = ?
                ");
            }
            $completedStmt->execute([$userId, $courseId]);
            $enrollment['completed_topics'] = intval($completedStmt->fetchColumn());
        } catch (Exception $e) {
            error_log("Completed topics count error: " . $e->getMessage());
            $enrollment['completed_topics'] = 0;
        }
        
        // Calculate progress percentage
        if ($enrollment['total_topics'] > 0) {
            $enrollment['progress_percent'] = round(($enrollment['completed_topics'] / $enrollment['total_topics']) * 100, 1);
        } else {
            $enrollment['progress_percent'] = 0;
        }
    }
    unset($enrollment);
    
} catch (Exception $e) {
    error_log("Enrollment fetch error: " . $e->getMessage());
}

// Filter enrollments
$statusFilter = $_GET['status'] ?? 'all';
$allEnrollments = $enrollments; // Keep original for count
if (!empty($enrollments)) {
    if ($statusFilter === 'completed') {
        $enrollments = array_filter($enrollments, function($e) {
            return $e['progress_percent'] >= 100;
        });
    } elseif ($statusFilter === 'in-progress') {
        $enrollments = array_filter($enrollments, function($e) {
            return $e['progress_percent'] > 0 && $e['progress_percent'] < 100;
        });
    } elseif ($statusFilter === 'not-started') {
        $enrollments = array_filter($enrollments, function($e) {
            return $e['progress_percent'] == 0;
        });
    }
}

$totalCourses = count($allEnrollments);
$totalCertificates = $stats['total_certificates'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>My Courses - Internship Adda</title>
    
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
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Cover image styling */
        .cover-image {
            object-fit: cover;
            width: 100%;
            height: 100%;
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
        
        /* Card hover effects */
        .course-card {
            transition: all 0.3s ease;
        }
        
        .course-card:active {
            transform: scale(0.98);
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Filter chips scrollable on mobile */
        .filter-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .filter-scroll::-webkit-scrollbar {
            display: none;
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
            
            .course-card:hover {
                box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1);
                transform: translateY(-4px);
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
                <h3 class="font-bold text-lg truncate"><?php echo safe_output($currentUser['name']); ?></h3>
                <p class="text-sm text-white/80 truncate"><?php echo safe_output($currentUser['email']); ?></p>
            </div>
        </div>

        <!-- Quick Stats in Menu -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 text-center border border-white/20">
                <div class="text-2xl font-bold"><?php echo $totalCourses; ?></div>
                <div class="text-xs text-white/80 font-medium">Courses</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 text-center border border-white/20">
                <div class="text-2xl font-bold"><?php echo $totalCertificates; ?></div>
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

            <!-- My Courses - ACTIVE -->
            <a href="/app/views/learner/my-courses.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 font-semibold">
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
        <a href="/app/api/auth.php?action=logout" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 active:bg-red-100 transition-colors font-semibold">
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
                        <h1 class="text-3xl font-bold text-gray-900">My Courses</h1>
                        <p class="text-gray-600 mt-1">Manage and continue your enrolled courses</p>
                    </div>
                    <a href="/public/courses.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Browse More Courses
                    </a>
                </div>
            </div>
        </header>

        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">
            
            <!-- Mobile Header -->
            <div class="md:hidden mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-1">My Courses</h1>
                <p class="text-sm text-gray-600">Track your learning progress</p>
            </div>
            
            <!-- Filters - Mobile Optimized -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 mb-6 shadow-sm">
                <div class="flex items-center gap-3 sm:gap-4">
                    <span class="text-sm font-semibold text-gray-700 flex-shrink-0">Filter:</span>
                    <div class="filter-scroll flex items-center gap-2 sm:gap-3 -mr-4 pr-4 sm:mr-0 sm:pr-0">
                        <a href="?status=all" class="flex-shrink-0 touch-btn px-4 py-2 rounded-lg font-semibold text-sm transition-colors <?php echo $statusFilter === 'all' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 active:bg-gray-300'; ?>">
                            All (<?php echo count($allEnrollments); ?>)
                        </a>
                        <a href="?status=in-progress" class="flex-shrink-0 touch-btn px-4 py-2 rounded-lg font-semibold text-sm transition-colors <?php echo $statusFilter === 'in-progress' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 active:bg-gray-300'; ?>">
                            In Progress
                        </a>
                        <a href="?status=completed" class="flex-shrink-0 touch-btn px-4 py-2 rounded-lg font-semibold text-sm transition-colors <?php echo $statusFilter === 'completed' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 active:bg-gray-300'; ?>">
                            Completed
                        </a>
                        <a href="?status=not-started" class="flex-shrink-0 touch-btn px-4 py-2 rounded-lg font-semibold text-sm transition-colors <?php echo $statusFilter === 'not-started' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 active:bg-gray-300'; ?>">
                            Not Started
                        </a>
                    </div>
                </div>
            </div>

            <!-- Courses Grid -->
            <?php if (empty($enrollments)): ?>
                <div class="bg-white rounded-xl border-2 border-dashed border-gray-300 p-8 sm:p-12 text-center">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">No courses found</h3>
                    <p class="text-sm sm:text-base text-gray-600 mb-6">
                        <?php if ($statusFilter !== 'all'): ?>
                            No courses match this filter. Try a different filter.
                        <?php else: ?>
                            You haven't enrolled in any courses yet
                        <?php endif; ?>
                    </p>
                    <a href="/public/courses.php" class="touch-btn inline-block bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-6 py-3 rounded-lg font-semibold transition-all text-sm sm:text-base">
                        Browse Courses
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <?php foreach ($enrollments as $course): ?>
                        <?php
                        $courseTitle = isset($course['title']) ? safe_output($course['title']) : 'Untitled Course';
                        $courseDesc = isset($course['description']) ? safe_output($course['description']) : '';
                        $courseId = isset($course['course_id']) ? intval($course['course_id']) : 0;
                        $progressPercent = isset($course['progress_percent']) ? floatval($course['progress_percent']) : 0;
                        $totalTopics = isset($course['total_topics']) ? intval($course['total_topics']) : 0;
                        $completedTopics = isset($course['completed_topics']) ? intval($course['completed_topics']) : 0;
                        $isCompleted = $progressPercent >= 100;
                        $hasCoverImage = !empty($course['cover_image']);
                        ?>
                        <div class="course-card bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                            <!-- Course Image - FIXED WITH COVER -->
                            <div class="h-36 sm:h-48 bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center relative overflow-hidden">
                                <?php if ($hasCoverImage): ?>
                                    <img src="<?php echo safe_output($course['cover_image']); ?>" 
                                         alt="<?php echo $courseTitle; ?>"
                                         class="cover-image"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-white opacity-80" style="display:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                <?php endif; ?>
                                
                                <?php if ($isCompleted): ?>
                                    <div class="absolute top-3 right-3 bg-green-500 text-white px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1 shadow-lg">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Done
                                    </div>
                                <?php elseif ($progressPercent > 0): ?>
                                    <div class="absolute top-3 right-3 bg-yellow-500 text-white px-2.5 py-1 rounded-full text-xs font-semibold shadow-lg">
                                        In Progress
                                    </div>
                                <?php else: ?>
                                    <div class="absolute top-3 right-3 bg-gray-500 text-white px-2.5 py-1 rounded-full text-xs font-semibold shadow-lg">
                                        New
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-4 sm:p-6">
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2 line-clamp-2"><?php echo $courseTitle; ?></h3>
                                <p class="text-xs sm:text-sm text-gray-600 mb-4 line-clamp-2"><?php echo $courseDesc; ?></p>
                                
                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-xs sm:text-sm mb-2">
                                        <span class="text-gray-600">Progress</span>
                                        <span class="font-bold <?php echo $isCompleted ? 'text-green-600' : 'text-primary-600'; ?>">
                                            <?php echo number_format($progressPercent, 1); ?>%
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 sm:h-2.5">
                                        <div class="<?php echo $isCompleted ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-primary-500 to-primary-600'; ?> h-2 sm:h-2.5 rounded-full transition-all duration-500 ease-out" style="width: <?php echo min($progressPercent, 100); ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3 sm:gap-4 text-xs sm:text-sm text-gray-500 mb-4">
                                    <div class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <span><?php echo $totalTopics; ?> topics</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <svg class="w-4 h-4 <?php echo $isCompleted ? 'text-green-600' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="<?php echo $isCompleted ? 'text-green-600 font-semibold' : ''; ?>">
                                            <?php echo $completedTopics; ?> done
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($isCompleted): ?>
                                    <a href="/app/views/learner/course-player.php?id=<?php echo $courseId; ?>" class="touch-btn block w-full bg-green-600 hover:bg-green-700 active:bg-green-800 text-white text-center px-4 py-2.5 sm:py-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-2 text-sm sm:text-base">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Review Course
                                    </a>
                                <?php else: ?>
                                    <a href="/app/views/learner/course-player.php?id=<?php echo $courseId; ?>" class="touch-btn block w-full bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-center px-4 py-2.5 sm:py-3 rounded-lg font-semibold transition-all text-sm sm:text-base">
                                        <?php echo $progressPercent > 0 ? 'Continue Learning' : 'Start Learning'; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions - Mobile -->
            <div class="md:hidden mt-6">
                <a href="/public/courses.php" class="touch-btn block w-full bg-white border-2 border-primary-600 text-primary-600 text-center px-6 py-3 rounded-lg font-semibold transition-all hover:bg-primary-50 active:bg-primary-100">
                    Browse More Courses
                </a>
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
        <a href="/app/views/learner/my-courses.php" class="flex flex-col items-center justify-center py-2 text-primary-600 bg-primary-50">
            <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
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

<script>
function toggleMenu() {
    const menu = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('menuOverlay');
    const hamburger = document.getElementById('hamburger');
    
    menu.classList.toggle('active');
    overlay.classList.toggle('active');
    hamburger.classList.toggle('active');
    
    // Prevent body scroll when menu is open
    if (menu.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}
</script>

</body>
</html>
