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
    header('Location: /public/login.php');
    exit;
}

// ✅ Check signup discount
$signupDiscountStmt = $db->prepare("
    SELECT 
        r.id,
        r.referral_code,
        r.signup_discount_applied,
        u.name as referrer_name,
        rs.setting_value as discount_percentage
    FROM referrals r
    JOIN users u ON r.referrer_user_id = u.id
    LEFT JOIN referral_settings rs ON rs.setting_key = 'signup_discount_percentage'
    WHERE r.referred_user_id = ? 
    AND r.signup_discount_applied = 0
    LIMIT 1
");
$signupDiscountStmt->execute([$userId]);
$signupDiscount = $signupDiscountStmt->fetch(PDO::FETCH_ASSOC);

$purchaseCheckStmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
$purchaseCheckStmt->execute([$userId]);
$hasPurchased = $purchaseCheckStmt->fetchColumn() > 0;

$showDiscountBanner = $signupDiscount && !$hasPurchased;

// ✅ INITIALIZE ALL VARIABLES FIRST (FIX FOR UNDEFINED VARIABLE ERROR)
$stats = [
    'total_courses' => 0,
    'total_internships' => 0,
    'total_enrollments' => 0,
    'in_progress' => 0,
    'completed_total' => 0,
    'total_certificates' => 0
];

$courseInProgress = 0;
$courseCompleted = 0;
$courseCertificates = 0;
$internshipInProgress = 0;
$internshipCompleted = 0;
$internshipCertificates = 0;

// Get Course Stats
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_courses'] = intval($stmt->fetchColumn());

    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND completed_at IS NULL");
    $stmt->execute([$userId]);
    $courseInProgress = intval($stmt->fetchColumn());

    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND completed_at IS NOT NULL");
    $stmt->execute([$userId]);
    $courseCompleted = intval($stmt->fetchColumn());

    $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ?");
    $stmt->execute([$userId]);
    $courseCertificates = intval($stmt->fetchColumn());
} catch (PDOException $e) {
    error_log("Course stats error: " . $e->getMessage());
}

// ========================================
// GET INTERNSHIP STATS - COMPLETE FIX
// ========================================
try {
    // Total internships enrolled
    $stmt = $db->prepare("SELECT COUNT(*) FROM internship_enrollments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_internships'] = intval($stmt->fetchColumn());

    // ✅ In Progress = Enrolled but certificate NOT approved
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ie.id)
        FROM internship_enrollments ie
        LEFT JOIN internship_certificate_requests icr 
            ON ie.id = icr.enrollment_id AND icr.status = 'approved'
        WHERE ie.user_id = ? 
        AND ie.status IN ('pending', 'applied')
        AND icr.id IS NULL
    ");
    $stmt->execute([$userId]);
    $internshipInProgress = intval($stmt->fetchColumn());

    // ✅ Completed = Status 'completed' OR certificate approved
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ie.id)
        FROM internship_enrollments ie
        LEFT JOIN internship_certificate_requests icr 
            ON ie.id = icr.enrollment_id
        WHERE ie.user_id = ? 
        AND (ie.status = 'completed' OR icr.status = 'approved')
    ");
    $stmt->execute([$userId]);
    $internshipCompleted = intval($stmt->fetchColumn());

    // Certificates count
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM internship_certificate_requests icr
        JOIN internship_enrollments ie ON icr.enrollment_id = ie.id
        WHERE ie.user_id = ? AND icr.status = 'approved'
    ");
    $stmt->execute([$userId]);
    $internshipCertificates = intval($stmt->fetchColumn());
    
} catch (PDOException $e) {
    error_log("Internship stats error: " . $e->getMessage());
}

// ✅ COMBINED TOTALS
$stats['total_enrollments'] = $stats['total_courses'] + $stats['total_internships'];
$stats['in_progress'] = $courseInProgress + $internshipInProgress;
$stats['completed_total'] = $courseCompleted + $internshipCompleted;
$stats['total_certificates'] = $courseCertificates + $internshipCertificates;

// ========================================
// GET RECENT COURSES
// ========================================
$recentCourses = [];
try {
    $stmt = $db->prepare("
        SELECT 
            e.id, e.course_id, e.enrolled_at, e.completed_at, e.progress_percent,
            c.title, c.description,
            COALESCE((SELECT COUNT(*) FROM topics t JOIN chapters ch ON t.chapter_id = ch.id WHERE ch.course_id = c.id), 0) as total_topics,
            COALESCE((SELECT COUNT(*) FROM topic_progress tp JOIN topics t ON tp.topic_id = t.id JOIN chapters ch ON t.chapter_id = ch.id WHERE tp.user_id = e.user_id AND ch.course_id = c.id AND tp.is_completed = 1), 0) as completed_topics
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $recentCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recentCourses as &$course) {
        $totalTopics = intval($course['total_topics'] ?? 0);
        $completedTopics = intval($course['completed_topics'] ?? 0);
        if ($totalTopics > 0) {
            $course['progress_percent'] = ($completedTopics / $totalTopics) * 100;
        } else {
            $course['progress_percent'] = 0;
        }
    }
    unset($course); // Break reference
    
} catch (Exception $e) {
    error_log("Course error: " . $e->getMessage());
}

// ========================================
// GET RECENT INTERNSHIPS - COMPLETE FIX
// ========================================
$recentInternships = [];
try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            ie.id as enrollment_id,
            ie.status as enrollment_status,
            ie.created_at as enrolled_at,
            ie.payment_status,
            ie.payment_amount,
            ie.final_amount,
            icr.status as certificate_status,
            icr.id as certificate_request_id,
            DATEDIFF(DATE_ADD(ie.created_at, INTERVAL i.duration_weeks WEEK), NOW()) as days_remaining
        FROM internships i
        INNER JOIN internship_enrollments ie ON i.id = ie.internship_id
        LEFT JOIN internship_certificate_requests icr ON ie.id = icr.enrollment_id
        WHERE ie.user_id = ?
        ORDER BY ie.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $recentInternships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Calculate completion percentage
    foreach ($recentInternships as &$internship) {
        // Check if completed (either status or certificate approved)
        $isCompleted = ($internship['enrollment_status'] === 'completed' || $internship['certificate_status'] === 'approved');
        
        if ($isCompleted) {
            $internship['completion_percentage'] = 100;
        } else {
            // Calculate based on time passed
            $enrolledDate = strtotime($internship['enrolled_at']);
            $currentTime = time();
            $totalDuration = intval($internship['duration_weeks']) * 7 * 24 * 60 * 60; // weeks to seconds
            $timePassed = $currentTime - $enrolledDate;
            
            if ($totalDuration > 0 && $timePassed > 0) {
                $internship['completion_percentage'] = min(99, round(($timePassed / $totalDuration) * 100));
            } else {
                $internship['completion_percentage'] = 0;
            }
        }
        
        // Ensure completion_percentage is set
        if (!isset($internship['completion_percentage'])) {
            $internship['completion_percentage'] = 0;
        }
    }
    unset($internship); // Break reference
    
} catch (Exception $e) {
    error_log("Internship error: " . $e->getMessage());
}


// Get recent quiz attempts
$recentAttempts = [];
try {
    $stmt = $db->prepare("
        SELECT qa.*, q.title as quiz_title, c.title as course_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN content_blocks cb ON q.content_block_id = cb.id
        JOIN topics t ON cb.topic_id = t.id
        JOIN chapters ch ON t.chapter_id = ch.id
        JOIN courses c ON ch.course_id = c.id
        WHERE qa.user_id = ?
        ORDER BY qa.started_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Quiz error: " . $e->getMessage());
}

// Get latest report
$latestReport = null;
try {
    $stmt = $db->prepare("SELECT * FROM internship_reports WHERE user_id = ? ORDER BY generated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $latestReport = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Report error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>My Dashboard - Internship Adda</title>
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

        /* Overlay */
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

        /* Mobile-First Stat Cards */
        .stat-card {
            background: white; 
            border: 1px solid #e5e7eb; 
            border-radius: 12px; 
            padding: 16px; 
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-card:active {
            transform: scale(0.98);
        }

        /* Animations */
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .discount-banner {
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
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

        /* Desktop sidebar hidden on mobile */
        .desktop-sidebar {
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

            .stat-card:hover {
                box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1); 
                border-color: rgba(34, 197, 94, 0.3); 
                transform: translateY(-2px);
            }
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        .line-clamp-2 { 
            display: -webkit-box; 
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical; 
            overflow: hidden; 
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
            <div class="text-xs text-white/80 font-medium">Total Enrolled</div>
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
            <a href="/app/views/learner/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 font-semibold">
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
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
                        <h1 class="text-3xl font-bold text-gray-900">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>! 👋</h1>
                        <p class="text-gray-600 mt-1">Continue your learning journey</p>
                    </div>
                    <a href="/public/courses.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Browse Courses
                    </a>
                </div>
            </div>
        </header>

        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">

            <?php if ($showDiscountBanner): ?>
            <!-- Signup Discount Banner -->
            <div class="discount-banner mb-4 md:mb-8 bg-gradient-to-r from-yellow-50 via-orange-50 to-red-50 border-2 border-yellow-400 rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-xl" id="discountBanner">
                <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4">
                    <!-- Icon -->
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-lg">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <!-- Title & Close Button -->
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 leading-tight">
                                🎉 Special Welcome Gift!
                            </h3>
                            <button onclick="dismissBanner()" class="touch-btn text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0 -mt-1">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Description -->
                        <p class="text-sm sm:text-base md:text-lg text-gray-700 mb-3 sm:mb-4 leading-relaxed">
                            <span class="font-semibold"><?php echo htmlspecialchars($signupDiscount['referrer_name']); ?></span> 
                            invited you! Get <span class="inline-block text-xl sm:text-2xl font-bold text-orange-600"><?php echo (int)($signupDiscount['discount_percentage'] ?? 40); ?>% OFF</span> on your first course! 🚀
                        </p>

                        <!-- Discount Code Card -->
                        <div class="bg-white rounded-lg sm:rounded-xl p-3 sm:p-4 border-2 border-dashed border-yellow-500 mb-3 sm:mb-4 shadow-lg">
                            <p class="text-xs text-gray-600 mb-2 font-semibold uppercase tracking-wider">🎁 Your Exclusive Code</p>
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                                <code class="text-xl sm:text-2xl md:text-3xl font-mono font-bold text-primary-700 tracking-wider break-all" id="discountCode">
                                    WELCOME<?php echo strtoupper(substr($signupDiscount['referral_code'], 3, 6)); ?>
                                </code>
                                <button 
                                    onclick="copyDiscountCode('WELCOME<?php echo strtoupper(substr($signupDiscount['referral_code'], 3, 6)); ?>')" 
                                    class="touch-btn p-3 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white rounded-lg transition-all shadow-md hover:shadow-lg active:scale-95 flex-shrink-0 self-end sm:self-auto"
                                    title="Copy code"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">✨ Use at checkout for <?php echo (int)($signupDiscount['discount_percentage'] ?? 40); ?>% discount!</p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                            <a href="/public/courses.php" class="touch-btn flex-1 px-4 sm:px-8 py-3 bg-gradient-to-r from-primary-600 to-green-600 hover:from-primary-700 hover:to-green-700 text-white font-bold rounded-lg transition-all shadow-lg hover:shadow-xl active:scale-95 text-center text-sm sm:text-base">
                                🎓 Browse Courses Now
                            </a>
                            <button onclick="copyDiscountCode('WELCOME<?php echo strtoupper(substr($signupDiscount['referral_code'], 3, 6)); ?>')" class="touch-btn px-4 sm:px-6 py-3 bg-white hover:bg-gray-50 text-gray-700 font-semibold rounded-lg transition-colors border-2 border-gray-300 text-sm sm:text-base">
                                📋 Copy Code
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ✅ COMBINED STATS GRID - Courses + Internships -->
           <!-- ✅ COMBINED STATS GRID - Courses + Internships (NOW CLICKABLE) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-6 md:mb-8">
    <!-- Total Enrolled (Courses + Internships) - Click to My Courses -->
    <a href="/app/views/learner/my-courses.php" class="stat-card cursor-pointer hover:shadow-lg transition-all">
        <div class="flex flex-col">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?php echo $stats['total_enrollments']; ?></h3>
            <p class="text-xs sm:text-sm text-gray-600">Total Enrolled</p>
            <p class="text-xs text-gray-500 mt-1"><?php echo $stats['total_courses']; ?> Courses • <?php echo $stats['total_internships']; ?> Internships</p>
        </div>
    </a>

    <!-- In Progress (Combined) - Click to In Progress filter -->
    <a href="/app/views/learner/my-courses.php?status=in-progress" class="stat-card cursor-pointer hover:shadow-lg transition-all">
        <div class="flex flex-col">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?php echo $stats['in_progress']; ?></h3>
            <p class="text-xs sm:text-sm text-gray-600">In Progress</p>
        </div>
    </a>

    <!-- Completed (Combined) - Click to Completed filter -->
    <a href="/app/views/learner/my-courses.php?status=completed" class="stat-card cursor-pointer hover:shadow-lg transition-all">
        <div class="flex flex-col">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?php echo $stats['completed_total']; ?></h3>
            <p class="text-xs sm:text-sm text-gray-600">Completed</p>
        </div>
    </a>

    <!-- Certificates (Combined) - Click to Certificates page -->
    <!-- Certificates (Combined) - Click to Certificates page -->
<a href="/app/views/learner/certificates.php" class="stat-card cursor-pointer hover:shadow-lg transition-all">
    <div class="flex flex-col">
        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
            </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?php echo $stats['total_certificates']; ?></h3>
        <p class="text-xs sm:text-sm text-gray-600">Certificates</p>
        <!-- ✅ NEW: Certificate Breakdown -->
        <p class="text-xs text-gray-500 mt-1"><?php echo $courseCertificates; ?> Courses • <?php echo $internshipCertificates; ?> Internships</p>
    </div>
</a>

</div>


            <!-- ✅ NEW: Internship Stats Section -->
            <!-- ✅ NEW: Internship Stats Section (CLICKABLE) -->
<div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4 sm:p-6 md:p-8 mb-6 md:mb-8">
    <div class="flex items-center gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-green-600 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <div class="flex-1">
            <h2 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900">💼 Internship Overview</h2>
            <p class="text-xs sm:text-sm text-gray-600 mt-0.5">Your professional training progress</p>
        </div>
        <a href="/app/views/learner/my-internships.php" class="touch-btn hidden sm:inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition-all text-sm">
            View All →
        </a>
    </div>

    <div class="grid grid-cols-3 gap-3 sm:gap-4">
        <!-- Enrolled - Click to My Internships -->
        <a href="/app/views/learner/my-internships.php" class="bg-white rounded-lg p-3 sm:p-4 text-center hover:shadow-lg transition-all cursor-pointer">
            <div class="text-2xl sm:text-3xl font-bold text-blue-600"><?php echo $stats['total_internships']; ?></div>
            <div class="text-xs sm:text-sm text-gray-600 mt-1">Enrolled</div>
        </a>
        
        <!-- Active - Click to My Internships -->
        <a href="/app/views/learner/my-internships.php" class="bg-white rounded-lg p-3 sm:p-4 text-center hover:shadow-lg transition-all cursor-pointer">
            <div class="text-2xl sm:text-3xl font-bold text-yellow-600"><?php echo $internshipInProgress ?? 0; ?></div>
            <div class="text-xs sm:text-sm text-gray-600 mt-1">Active</div>
        </a>
        
        <!-- Completed - Click to My Internships -->
        <a href="/app/views/learner/my-internships.php" class="bg-white rounded-lg p-3 sm:p-4 text-center hover:shadow-lg transition-all cursor-pointer">
            <div class="text-2xl sm:text-3xl font-bold text-green-600"><?php echo $internshipCompleted ?? 0; ?></div>
            <div class="text-xs sm:text-sm text-gray-600 mt-1">Completed</div>
        </a>
    </div>

    <a href="/app/views/learner/my-internships.php" class="sm:hidden mt-4 block text-center bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-semibold transition-all text-sm">
        View All Internships →
    </a>
</div>


            <!-- Report Section -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-4 sm:p-6 md:p-8 mb-6 md:mb-8">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4 sm:mb-6">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900">📊 Learning Report</h2>
                            <p class="text-xs sm:text-sm text-gray-600 mt-0.5">Track your performance</p>
                        </div>
                    </div>
                    <button onclick="generateReport()" id="generateReportBtn" class="touch-btn w-full sm:w-auto bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white px-4 sm:px-6 py-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-2 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Generate Report
                    </button>
                </div>

                <?php if ($latestReport): ?>
                    <div class="bg-white rounded-xl p-4 sm:p-6 grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <div class="text-center">
                            <div class="text-2xl sm:text-3xl font-bold text-blue-600"><?php echo $latestReport['completed_courses']; ?>/<?php echo $latestReport['total_courses']; ?></div>
                            <div class="text-xs sm:text-sm text-gray-600 mt-1">Courses Done</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl sm:text-3xl font-bold text-green-600"><?php echo number_format($latestReport['overall_progress'], 1); ?>%</div>
                            <div class="text-xs sm:text-sm text-gray-600 mt-1">Progress</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl sm:text-3xl font-bold text-purple-600"><?php echo number_format($latestReport['average_quiz_score'], 1); ?>%</div>
                            <div class="text-xs sm:text-sm text-gray-600 mt-1">Quiz Score</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl sm:text-3xl font-bold text-orange-600"><?php echo $latestReport['certificates_earned']; ?></div>
                            <div class="text-xs sm:text-sm text-gray-600 mt-1">Certificates</div>
                        </div>
                    </div>
                    <p class="text-center mt-3 sm:mt-4 text-xs sm:text-sm text-gray-600">Last generated: <?php echo date('M j, Y g:i A', strtotime($latestReport['generated_at'])); ?></p>
                <?php else: ?>
                    <div class="bg-white rounded-xl p-8 sm:p-12 text-center">
                        <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mx-auto mb-3 sm:mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">No Report Yet</h3>
                        <p class="text-sm sm:text-base text-gray-600">Generate your first progress report</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Continue Learning - Courses -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 md:p-8 mb-6 md:mb-8">
                <div class="flex items-center justify-between mb-4 sm:mb-6">
                    <h2 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900">📚 Continue Learning</h2>
                    <a href="/app/views/learner/my-courses.php" class="text-primary-600 hover:text-primary-700 font-semibold text-xs sm:text-sm">View All →</a>
                </div>

                <?php if (empty($recentCourses)): ?>
                    <div class="text-center py-8 sm:py-12">
                        <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mx-auto mb-3 sm:mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">No courses yet</h3>
                        <p class="text-sm sm:text-base text-gray-600 mb-4 sm:mb-6">Start your learning journey</p>
                        <a href="/public/courses.php" class="touch-btn inline-block bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-6 py-3 rounded-lg font-semibold transition-all text-sm sm:text-base">Browse Courses</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
                        <?php foreach ($recentCourses as $course): ?>
                            <div class="border border-gray-200 rounded-xl p-4 sm:p-6 hover:border-primary-300 transition-all active:scale-[0.98]">
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2 line-clamp-2"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="text-xs sm:text-sm text-gray-600 mb-4 line-clamp-2"><?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>...</p>
                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-xs sm:text-sm mb-2">
                                        <span class="text-gray-600">Progress</span>
                                        <span class="font-bold text-primary-600"><?php echo number_format($course['progress_percent'], 1); ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-gradient-to-r from-primary-500 to-green-600 h-2.5 rounded-full transition-all" 
                                             style="width: <?php echo $course['progress_percent']; ?>%"></div>
                                    </div>
                                </div>
                                <a href="/app/views/learner/course-player.php?id=<?php echo $course['course_id']; ?>" 
                                   class="block text-center bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-lg font-semibold transition-all text-sm">
                                    Continue →
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ✅ NEW: Recent Internships Section -->
            <?php if (!empty($recentInternships)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 md:p-8 mb-6 md:mb-8">
                <div class="flex items-center justify-between mb-4 sm:mb-6">
                    <h2 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900">💼 Active Internships</h2>
                    <a href="/app/views/learner/my-internships.php" class="text-primary-600 hover:text-primary-700 font-semibold text-xs sm:text-sm">View All →</a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
                    <?php foreach ($recentInternships as $internship): ?>
                        <div class="border border-gray-200 rounded-xl p-4 sm:p-6 hover:border-green-300 transition-all active:scale-[0.98]">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                    <?php echo htmlspecialchars($internship['category']); ?>
                                </span>
                                <?php
                                $statusBadges = [
                                    'active' => ['bg' => 'bg-green-500', 'text' => 'Active'],
                                    'completed' => ['bg' => 'bg-blue-500', 'text' => 'Completed']
                                ];
                                $badge = $statusBadges[$internship['enrollment_status']] ?? ['bg' => 'bg-gray-500', 'text' => 'Unknown'];
                                ?>
                                <span class="<?= $badge['bg'] ?> text-white px-2 py-1 rounded-full text-xs font-bold">
                                    <?= $badge['text'] ?>
                                </span>
                            </div>
                            <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2 line-clamp-2"><?php echo htmlspecialchars($internship['title']); ?></h3>
                            <div class="flex items-center gap-3 text-xs text-gray-600 mb-4">
                                <span>⏱️ <?php echo $internship['duration_weeks']; ?> weeks</span>
                                <?php if ($internship['days_remaining'] !== null && $internship['enrollment_status'] === 'active'): ?>
                                    <span class="<?= $internship['days_remaining'] < 7 ? 'text-red-600 font-bold' : '' ?>">
                                        ⏳ <?php echo max(0, $internship['days_remaining']); ?> days left
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-xs sm:text-sm mb-2">
                                    <span class="text-gray-600">Progress</span>
                                    <span class="font-bold text-green-600"><?php echo round($internship['completion_percentage']); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 h-2.5 rounded-full transition-all" 
                                         style="width: <?php echo $internship['completion_percentage']; ?>%"></div>
                                </div>
                            </div>
                            <?php if ($internship['enrollment_status'] === 'active'): ?>
                                <a href="/app/views/learner/internship-player.php?id=<?php echo $internship['id']; ?>" 
                                   class="block text-center bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold transition-all text-sm">
                                    Continue →
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav">
    <div class="grid grid-cols-5 h-full">
        <a href="/app/views/learner/dashboard.php" class="flex flex-col items-center justify-center py-2 text-primary-600 bg-primary-50">
            <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
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

function dismissBanner() {
    const banner = document.getElementById('discountBanner');
    if (banner) {
        banner.style.transform = 'translateY(-20px)';
        banner.style.opacity = '0';
        banner.style.transition = 'all 0.3s ease';
        setTimeout(() => banner.remove(), 300);
    }
}

function copyDiscountCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        showToast('✅ Discount code copied!');
    }).catch(() => {
        showToast('❌ Failed to copy');
    });
}

async function generateReport() {
    const btn = document.getElementById('generateReportBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Generating...';

    try {
        const response = await fetch('/api/reports.php?action=generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            showToast('✅ Report generated successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('❌ ' + (result.error || 'Failed to generate report'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        showToast('❌ Network error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-24 left-4 right-4 sm:left-auto sm:right-4 sm:max-w-sm bg-gray-900 text-white px-4 py-3 rounded-xl shadow-2xl z-50 font-semibold';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

</body>
</html>
