<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/core/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
        __DIR__ . '/../app/controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Check if user is logged in
$auth = new Auth($db);
$isLoggedIn = $auth->check();
$userId = $isLoggedIn ? $auth->id() : null;

// Get course slug from URL
$courseSlug = $_GET['slug'] ?? null;
if (!$courseSlug) {
    header('Location: /public/courses.php');
    exit;
}

// Get course details
$stmt = $db->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as total_enrollments,
           (SELECT COUNT(*) FROM chapters WHERE course_id = c.id) as total_chapters,
           (SELECT COUNT(*) FROM topics t 
            JOIN chapters ch ON t.chapter_id = ch.id 
            WHERE ch.course_id = c.id) as total_topics,
           (SELECT AVG(rating) FROM course_reviews WHERE course_id = c.id) as avg_rating,
           (SELECT COUNT(*) FROM course_reviews WHERE course_id = c.id) as total_reviews
    FROM courses c
    WHERE c.slug = ? AND c.status = 'published'
");
$stmt->execute([$courseSlug]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: /public/courses.php');
    exit;
}

// Check if user is enrolled
$isEnrolled = false;
if ($isLoggedIn) {
    $enrollCheck = $db->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $enrollCheck->execute([$userId, $course['id']]);
    $isEnrolled = (bool)$enrollCheck->fetch();
}

// Get curriculum (chapters and topics)
$chaptersStmt = $db->prepare("
    SELECT ch.*,
           (SELECT COUNT(*) FROM topics WHERE chapter_id = ch.id) as topic_count
    FROM chapters ch
    WHERE ch.course_id = ?
    ORDER BY ch.chapter_order ASC, ch.sort_order ASC
");
$chaptersStmt->execute([$course['id']]);
$chapters = $chaptersStmt->fetchAll();

// Get topics for each chapter
foreach ($chapters as &$chapter) {
    $topicsStmt = $db->prepare("
        SELECT * FROM topics 
        WHERE chapter_id = ? 
        ORDER BY topic_order ASC, sort_order ASC
    ");
    $topicsStmt->execute([$chapter['id']]);
    $chapter['topics'] = $topicsStmt->fetchAll();
}

// Get reviews
$reviewsStmt = $db->prepare("
    SELECT cr.*, u.name as user_name
    FROM course_reviews cr
    JOIN users u ON cr.user_id = u.id
    WHERE cr.course_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 10
");
$reviewsStmt->execute([$course['id']]);
$reviews = $reviewsStmt->fetchAll();

// Check if user has already reviewed
$hasReviewed = false;
if ($isLoggedIn) {
    $reviewCheck = $db->prepare("SELECT id FROM course_reviews WHERE user_id = ? AND course_id = ?");
    $reviewCheck->execute([$userId, $course['id']]);
    $hasReviewed = (bool)$reviewCheck->fetch();
}

$isFree = $course['price'] == 0;
$avgRating = round($course['avg_rating'], 1);

// Helper function to safely display text (fixes &amp; issue)
function safeText($text) {
    return htmlspecialchars_decode($text, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safeText($course['title']); ?> - Internship Adda</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Razorpay SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

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
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex items-center justify-between">
           <a href="https://internshipadda.com" class="inline-flex flex-col items-center gap-3 mb-4">
        
        <img 
            src="../../icon.png" 
            alt="InternshipAdda Logo"
            class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
        >

    

    </a>
            
            <div class="flex items-center gap-2 sm:gap-4">
                <a href="/" class="text-sm sm:text-base text-gray-600 hover:text-primary-600 font-semibold">Home</a>
                <a href="/public/courses.php" class="text-sm sm:text-base text-gray-600 hover:text-primary-600 font-semibold">Courses</a>
                <?php if ($isLoggedIn): ?>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="/app/views/admin/dashboard.php" class="hidden md:inline text-sm sm:text-base text-gray-600 hover:text-primary-600 font-semibold">Dashboard</a>
                    <?php else: ?>
                        <a href="/app/views/learner/dashboard.php" class="hidden md:inline text-sm sm:text-base text-gray-600 hover:text-primary-600 font-semibold">My Dashboard</a>
                    <?php endif; ?>
                    <a href="/api/auth.php?action=logout" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 sm:px-6 sm:py-2 rounded-lg font-semibold transition-colors text-sm sm:text-base">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="/public/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="text-sm sm:text-base text-gray-600 hover:text-primary-600 font-semibold">Login</a>
                    <a href="/public/signup.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 sm:px-6 sm:py-2 rounded-lg font-semibold transition-colors text-sm sm:text-base">
                        Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Course Hero Section -->
<section class="bg-gradient-to-br from-primary-50 to-blue-50 py-6 sm:py-8 lg:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Left: Course Info -->
            <div class="lg:col-span-2">
                <div class="mb-4">
                    <a href="/public/courses.php" class="text-primary-600 hover:text-primary-700 font-semibold inline-flex items-center gap-2 text-sm sm:text-base">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Courses
                    </a>
                </div>
                
                <h1 class="text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-bold text-gray-900 mb-3 sm:mb-4">
                    <?php echo safeText($course['title']); ?>
                </h1>
                
                <p class="text-base sm:text-lg text-gray-600 mb-4 sm:mb-6">
                    <?php echo safeText($course['description']); ?>
                </p>
                
                <!-- Rating & Stats -->
                <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-xs sm:text-sm">
                    <div class="flex items-center gap-2">
                        <div class="flex items-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 <?php echo $i <= $avgRating ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <span class="font-semibold text-gray-900"><?php echo $avgRating; ?></span>
                        <span class="text-gray-500">(<?php echo $course['total_reviews']; ?>)</span>
                    </div>
                    
                    <div class="flex items-center gap-2 text-gray-600">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span><?php echo $course['total_enrollments']; ?> students</span>
                    </div>
                    
                    <div class="flex items-center gap-2 text-gray-600">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span><?php echo $course['total_chapters']; ?> chapters • <?php echo $course['total_topics']; ?> topics</span>
                    </div>
                </div>
            </div>
            
            <!-- Right: Enrollment Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-200 lg:sticky lg:top-24">
                    <!-- ✅ Cover Image -->
<div class="aspect-video bg-gradient-to-br from-primary-500 to-primary-700 rounded-lg overflow-hidden mb-4 relative">
    <?php if (!empty($course['cover_image'])): ?>
        <img src="<?php echo htmlspecialchars($course['cover_image']); ?>" 
             alt="<?php echo safeText($course['title']); ?>"
             class="w-full h-full object-cover"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center" style="display:none;">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
        </div>
    <?php else: ?>
        <div class="w-full h-full flex items-center justify-center">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
        </div>
    <?php endif; ?>
</div>

                    
                    <!-- Price Section -->
                    <div class="mb-4" id="priceContainer">
                        <?php if ($isFree): ?>
                            <div class="text-2xl sm:text-3xl font-bold text-green-600 mb-2">FREE</div>
                            <p class="text-gray-600 text-xs sm:text-sm">Get lifetime access at no cost</p>
                        <?php else: ?>
                            <!-- Original Price Section -->
                            <div id="originalPriceSection">
                                <div class="text-3xl font-bold text-gray-900 mb-2">₹<?php echo number_format($course['price'], 2); ?></div>
                                <p class="text-gray-600 text-sm">One-time payment, lifetime access</p>
                            </div>
                            
                            <!-- Discounted Price Section (Hidden by default) -->
                            <div id="discountedPriceSection" class="hidden">
                                <div class="flex items-center gap-3 mb-2 flex-wrap">
                                    <div class="text-3xl font-bold text-green-600" id="finalPrice">₹0.00</div>
                                    <div class="text-lg text-gray-400 line-through">₹<?php echo number_format($course['price'], 2); ?></div>
                                </div>
                                <div class="mb-2">
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold" id="discountBadge"></span>
                                </div>
                                <p class="text-green-600 text-sm font-semibold">🎉 You save ₹<span id="savedAmount">0</span></p>
                            </div>
                            
                            <!-- Coupon Input -->
                            <?php if (!$isEnrolled && $isLoggedIn): ?>
                                <div class="mt-4 border-t pt-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Have a coupon / Referral Code? </label>
                                    <div class="flex gap-2">
                                        <input 
                                            type="text" 
                                            id="couponInput" 
                                            placeholder="Enter code" 
                                            class="flex-1 px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 uppercase"
                                            maxlength="20"
                                        >
                                        <button 
                                            onclick="applyCoupon()" 
                                            id="applyBtn"
                                            class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors whitespace-nowrap text-sm">
                                            Apply
                                        </button>
                                    </div>
                                    <div id="couponMessage" class="mt-2 text-sm"></div>
                                    
                                    <!-- Remove Coupon Button -->
                                    <button 
                                        onclick="removeCoupon()" 
                                        id="removeCouponBtn" 
                                        class="hidden w-full mt-2 text-red-600 hover:text-red-700 text-sm font-semibold">
                                        ✕ Remove Coupon
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Enrollment Buttons -->
                    <?php if ($isEnrolled): ?>
                        <a href="/app/views/learner/course-player.php?id=<?php echo $course['id']; ?>" class="block w-full bg-primary-600 hover:bg-primary-700 text-white text-center px-6 py-4 rounded-lg font-bold text-lg transition-colors mb-3">
                            Continue Learning →
                        </a>
                        <p class="text-center text-sm text-green-600 font-semibold">✓ You're enrolled</p>
                   <?php elseif (!$isLoggedIn): ?>
    <!-- ✅ UPDATED: Pass redirect URL -->
    <a href="/public/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
       class="block w-full bg-primary-600 hover:bg-primary-700 text-white text-center px-6 py-4 rounded-lg font-bold text-lg transition-colors mb-3">
        Login to Enroll
    </a>

                    <?php else: ?>
                        <button onclick="enrollCourse(<?php echo $course['id']; ?>)" id="enrollBtn" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-4 rounded-lg font-bold text-lg transition-colors mb-3">
                            Enroll Now
                        </button>
                    <?php endif; ?>
                    
                    <div class="border-t pt-4 mt-4 space-y-2 text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Lifetime access</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Certificate of completion</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Learn at your own pace</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Course Content Tabs -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <!-- Tabs -->
            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div class="flex border-b border-gray-200 overflow-x-auto">
                    <button onclick="switchTab('overview')" class="tab-btn flex-1 px-6 py-4 font-semibold text-gray-600 hover:text-primary-600 border-b-2 border-transparent hover:border-primary-600 transition-colors active" data-tab="overview">
                        Overview
                    </button>
                    <button onclick="switchTab('curriculum')" class="tab-btn flex-1 px-6 py-4 font-semibold text-gray-600 hover:text-primary-600 border-b-2 border-transparent hover:border-primary-600 transition-colors" data-tab="curriculum">
                        Curriculum
                    </button>
                    <button onclick="switchTab('reviews')" class="tab-btn flex-1 px-6 py-4 font-semibold text-gray-600 hover:text-primary-600 border-b-2 border-transparent hover:border-primary-600 transition-colors" data-tab="reviews">
                        Reviews (<?php echo $course['total_reviews']; ?>)
                    </button>
                </div>
                
                <!-- Tab Content -->
                <div class="p-8">
                    <!-- Overview Tab -->
                    <div id="overview-tab" class="tab-content active">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">About This Course</h2>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            <?php echo nl2br(safeText($course['description'])); ?>
                        </p>
                        
                        <h3 class="text-xl font-bold text-gray-900 mb-3">What You'll Get</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span><?php echo $course['total_topics']; ?> comprehensive topics across <?php echo $course['total_chapters']; ?> chapters</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span>Certificate of completion upon finishing the course</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span>Lifetime access to course content and updates</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span>Learn at your own pace with flexible scheduling</span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Curriculum Tab -->
                    <div id="curriculum-tab" class="tab-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Course Curriculum</h2>
                        
                        <?php if (empty($chapters)): ?>
                            <p class="text-gray-500 text-center py-8">Curriculum coming soon!</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($chapters as $index => $chapter): ?>
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <button onclick="toggleChapter(<?php echo $index; ?>)" class="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">
                                                    <?php echo $index + 1; ?>
                                                </div>
                                                <div class="text-left">
                                                    <h4 class="font-bold text-gray-900"><?php echo safeText($chapter['title']); ?></h4>
                                                    <p class="text-sm text-gray-500"><?php echo count($chapter['topics']); ?> topics</p>
                                                </div>
                                            </div>
                                            <svg class="w-5 h-5 text-gray-400 chapter-arrow transition-transform" data-chapter="<?php echo $index; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        
                                        <div id="chapter-<?php echo $index; ?>" class="chapter-content hidden border-t border-gray-200">
                                            <?php if (empty($chapter['topics'])): ?>
                                                <p class="p-4 text-gray-500 text-sm">No topics added yet</p>
                                            <?php else: ?>
                                                <ul class="divide-y divide-gray-100">
                                                    <?php foreach ($chapter['topics'] as $topic): ?>
                                                        <li class="p-4 hover:bg-gray-50 transition-colors">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                                                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                    </svg>
                                                                    <span class="text-gray-700 text-sm truncate"><?php echo safeText($topic['title']); ?></span>
                                                                </div>
                                                                <?php if (!$isEnrolled): ?>
                                                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                                    </svg>
                                                                <?php endif; ?>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Reviews Tab -->
                    <div id="reviews-tab" class="tab-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Student Reviews</h2>
                        
                        <!-- Add Review Form (Only for enrolled students) -->
                        <?php if ($isEnrolled && !$hasReviewed): ?>
                            <div class="bg-primary-50 border border-primary-200 rounded-lg p-6 mb-6">
                                <h3 class="font-bold text-gray-900 mb-4">Share Your Experience</h3>
                                <form id="reviewForm" onsubmit="submitReview(event)">
                                    <div class="mb-4">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Rating *</label>
                                        <div class="flex gap-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <button type="button" onclick="setRating(<?php echo $i; ?>)" class="rating-star text-3xl text-gray-300 hover:text-yellow-400 transition-colors" data-rating="<?php echo $i; ?>">
                                                    ★
                                                </button>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" id="rating" name="rating" required>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Your Review (Optional)</label>
                                        <textarea name="review_text" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Tell us about your experience..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                        Submit Review
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Reviews List -->
                        <?php if (empty($reviews)): ?>
                            <p class="text-gray-500 text-center py-8">No reviews yet. Be the first!</p>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="border-b border-gray-200 pb-6 last:border-0">
                                        <div class="flex items-start justify-between mb-2 gap-2">
                                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                                <div class="w-10 h-10 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">
                                                    <?php echo strtoupper(substr($review['user_name'], 0, 1)); ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-gray-900 truncate"><?php echo safeText($review['user_name']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center flex-shrink-0">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <svg class="w-4 h-4 <?php echo $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                    </svg>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($review['review_text'])): ?>
                                            <p class="text-gray-600 mt-2 text-sm"><?php echo nl2br(safeText($review['review_text'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar: Certificate Preview -->
       <!-- Sidebar Certificate Preview -->
<div class="lg:col-span-1">
    <div class="bg-white rounded-xl shadow border border-gray-200 p-6 sticky top-24">
        <h3 class="font-bold text-gray-900 mb-4 text-lg">Certificate of Completion</h3>
        
        <!-- Certificate Preview Card - NO SCROLL, FIXED SIZE -->
        <div class="relative bg-white rounded-xl overflow-hidden shadow-lg" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%); padding: 5px;">
            <!-- Inner White Card -->
            <div class="bg-white rounded-lg p-4 relative">
                <!-- Inner Purple Border -->
                <div class="absolute inset-2 border-2 rounded-md pointer-events-none" style="border-image: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%) 1;"></div>
                
                <!-- Decorative Inner Border -->
                <div class="absolute" style="top: 12px; left: 12px; right: 12px; bottom: 12px; border: 1px solid #c4b5fd; border-radius: 4px;"></div>
                
                <!-- Corner Decorations -->
                <div class="absolute" style="top: 14px; left: 14px; width: 16px; height: 16px; border-left: 2px solid #8b5cf6; border-top: 2px solid #8b5cf6;"></div>
                <div class="absolute" style="top: 14px; right: 14px; width: 16px; height: 16px; border-right: 2px solid #8b5cf6; border-top: 2px solid #8b5cf6;"></div>
                <div class="absolute" style="bottom: 14px; left: 14px; width: 16px; height: 16px; border-left: 2px solid #8b5cf6; border-bottom: 2px solid #8b5cf6;"></div>
                <div class="absolute" style="bottom: 14px; right: 14px; width: 16px; height: 16px; border-right: 2px solid #8b5cf6; border-bottom: 2px solid #8b5cf6;"></div>
                
                <!-- Content - Compact Spacing -->
                <div class="relative z-10 text-center py-2">
                    <!-- Logo -->
                    <div class="flex justify-center mb-2">
                        <img src="https://internshipadda.com/icon.png" alt="Internship Adda" class="h-6 w-auto" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="w-6 h-6 bg-gradient-to-br from-purple-500 to-purple-700 rounded flex items-center justify-center shadow-sm" style="display: none;">
                            <span class="text-white font-bold text-xs">I</span>
                        </div>
                    </div>
                    
                    <!-- Certificate Title -->
                    <h1 class="text-2xl font-bold mb-0.5 tracking-wider leading-tight" style="font-family: 'Playfair Display', serif; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        CERTIFICATE
                    </h1>
                    <p class="text-xs text-gray-500 mb-2">of Completion</p>
                    
                    <!-- Divider with Checkmark -->
                    <div class="flex items-center justify-center gap-1.5 mb-2">
                        <div class="w-12 h-0.5" style="background: linear-gradient(90deg, transparent, #6366f1, #8b5cf6);"></div>
                        <div class="w-5 h-5 rounded-full flex items-center justify-center shadow-sm" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="w-12 h-0.5" style="background: linear-gradient(90deg, #8b5cf6, #a855f7, transparent);"></div>
                    </div>
                    
                    <!-- Presented To -->
                    <p class="text-xs text-gray-500 mb-1.5" style="font-size: 10px;">This is to certify that</p>
                    
                    <!-- Name with Gradient Underline -->
                    <div class="mb-2 px-3">
                        <h2 class="text-xl font-bold text-gray-900 mb-0.5 leading-tight" style="font-family: 'Playfair Display', serif;">
                            Your Name
                        </h2>
                        <div class="h-0.5 mx-auto" style="width: 80%; background: linear-gradient(90deg, transparent 10%, #6366f1 30%, #8b5cf6 50%, #a855f7 70%, transparent 90%);"></div>
                    </div>
                    
                    <!-- Completion Text -->
                    <p class="text-xs text-gray-500 mb-1.5" style="font-size: 10px;">has successfully completed the course</p>
                    
                    <!-- Course Title -->
                    <div class="mb-2 px-3">
                        <h3 class="text-sm font-bold leading-tight" style="font-family: 'Playfair Display', serif; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </h3>
                    </div>
                    
                    <!-- Horizontal Divider -->
                    <div class="w-full h-px mb-2 mx-auto" style="width: 85%; background: linear-gradient(90deg, transparent 10%, #d1d5db 50%, transparent 90%);"></div>
                    
                    <!-- Collaboration Text -->
                    <div class="mb-3">
                        <p class="text-xs font-semibold leading-tight" style="font-size: 10px; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            Internship Adda × BlueCollar Connected Technologies Pvt. Ltd.
                        </p>
                    </div>
                    
                    <!-- Footer Section - Compact -->
                    <div class="grid grid-cols-3 gap-2 items-end pt-2 border-t border-gray-100">
                        <!-- Left: Signature -->
                        <div class="text-left">
                            <div class="text-gray-400 italic mb-0.5" style="font-family: 'Brush Script MT', cursive; font-size: 13px;">S - shr</div>
                            <div class="w-14 h-px bg-gray-300"></div>
                            <p class="text-gray-500 mt-0.5" style="font-size: 8px;">Authorized</p>
                        </div>
                        
                        <!-- Center: Date & Certificate ID -->
                        <div class="text-center">
                            <p class="text-gray-400 uppercase tracking-wide mb-0.5" style="font-size: 7px;">DATE</p>
                            <p class="font-semibold text-gray-700 mb-1.5" style="font-size: 10px;"><?php echo date('M Y'); ?></p>
                            <p class="text-gray-400 uppercase tracking-wide mb-0.5" style="font-size: 7px;">CERT ID</p>
                            <p class="font-mono text-gray-700" style="font-size: 7px;">CERT-2026</p>
                        </div>
                        
                        <!-- Right: QR Code -->
                        <div class="text-right flex flex-col items-end">
                            <div class="w-10 h-10 border border-gray-300 rounded flex items-center justify-center bg-gray-50">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                            </div>
                            <p class="text-gray-500 mt-0.5" style="font-size: 7px;">Scan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Description -->
        <p class="text-sm text-gray-600 leading-relaxed mt-4">
            Complete this course to earn your professional certificate and showcase your achievement!
        </p>
        
        <!-- Features List -->
        <div class="mt-4 space-y-2">
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-5 h-5 text-purple-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span>Verifiable certificate</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-5 h-5 text-purple-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span>Shareable on LinkedIn</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-5 h-5 text-purple-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span>Download as PDF</span>
            </div>
        </div>
    </div>
</div>

    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-white py-12 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center text-sm text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> Internship Adda. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
// Global variables
let appliedCoupon = null;
const originalPrice = <?php echo $course['price']; ?>;
const courseId = <?php echo $course['id']; ?>;
const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
const userId = <?php echo $userId ?? 'null'; ?>;

// Tab Switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'text-primary-600', 'border-primary-600');
    });
    
    document.getElementById(`${tabName}-tab`).classList.add('active');
    
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    activeBtn.classList.add('active', 'text-primary-600', 'border-primary-600');
}

// Toggle Chapter
function toggleChapter(index) {
    const content = document.getElementById(`chapter-${index}`);
    const arrow = document.querySelector(`[data-chapter="${index}"]`);
    
    content.classList.toggle('hidden');
    arrow.classList.toggle('rotate-180');
}

// Rating System
let selectedRating = 0;

function setRating(rating) {
    selectedRating = rating;
    document.getElementById('rating').value = rating;
    
    document.querySelectorAll('.rating-star').forEach((star, index) => {
        if (index < rating) {
            star.classList.remove('text-gray-300');
            star.classList.add('text-yellow-400');
        } else {
            star.classList.remove('text-yellow-400');
            star.classList.add('text-gray-300');
        }
    });
}

// Submit Review
async function submitReview(event) {
    event.preventDefault();
    
    if (selectedRating === 0) {
        alert('Please select a rating');
        return;
    }
    
    const formData = new FormData(event.target);
    formData.append('course_id', courseId);
    
    try {
        const response = await fetch('/api/reviews.php?action=submit', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Review submitted successfully!');
            location.reload();
        } else {
            alert(result.error || 'Failed to submit review');
        }
    } catch (error) {
        console.error('Review error:', error);
        alert('Network error. Please try again.');
    }
}

// Apply Coupon
async function applyCoupon() {
    const couponInput = document.getElementById('couponInput');
    const couponCode = couponInput.value.trim().toUpperCase();
    const messageDiv = document.getElementById('couponMessage');
    const applyBtn = document.getElementById('applyBtn');
    
    if (!couponCode) {
        showCouponMessage('Please enter a coupon code', 'error');
        return;
    }
    
    if (!isLoggedIn) {
        alert('Please login first to apply coupon');
        window.location.href = '/public/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    }
    
    applyBtn.disabled = true;
    applyBtn.textContent = 'Applying...';
    messageDiv.textContent = 'Validating coupon...';
    messageDiv.classList.remove('hidden');
    
    try {
        const response = await fetch('/api/validate-coupon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                coupon_code: couponCode,
                course_price: originalPrice
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            appliedCoupon = result;
            updatePriceDisplay(result);
            showCouponMessage('✅ Coupon applied!', 'success');
            couponInput.disabled = true;
            applyBtn.classList.add('hidden');
            document.getElementById('removeCouponBtn').classList.remove('hidden');
        } else {
            showCouponMessage(result.error || 'Invalid coupon', 'error');
            appliedCoupon = null;
        }
        
    } catch (error) {
        showCouponMessage('Failed to apply coupon', 'error');
    } finally {
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply';
    }
}

// Remove Coupon
function removeCoupon() {
    appliedCoupon = null;
    document.getElementById('originalPriceSection').classList.remove('hidden');
    document.getElementById('discountedPriceSection').classList.add('hidden');
    document.getElementById('couponInput').value = '';
    document.getElementById('couponInput').disabled = false;
    document.getElementById('applyBtn').classList.remove('hidden');
    document.getElementById('removeCouponBtn').classList.add('hidden');
    document.getElementById('couponMessage').classList.add('hidden');
}

// Update Price Display
function updatePriceDisplay(result) {
    document.getElementById('originalPriceSection').classList.add('hidden');
    document.getElementById('discountedPriceSection').classList.remove('hidden');
    
    const finalPrice = parseFloat(result.final_price || 0);
    const discountAmount = parseFloat(result.discount_amount || 0);
    const discountPercent = result.discount_percent || 0;
    
    document.getElementById('finalPrice').textContent = '₹' + finalPrice.toFixed(2);
    document.getElementById('savedAmount').textContent = discountAmount.toFixed(2);
    document.getElementById('discountBadge').textContent = `${discountPercent}% OFF`;
}

// Show Message
function showCouponMessage(message, type) {
    const div = document.getElementById('couponMessage');
    div.textContent = message;
    div.classList.remove('hidden', 'text-red-600', 'text-green-600');
    div.classList.add(type === 'error' ? 'text-red-600' : 'text-green-600');
}

// 🔥 MAIN ENROLLMENT FUNCTION
async function enrollCourse(courseId) {
    if (!isLoggedIn) {
        alert('Please login first');
        window.location.href = '/public/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    }
    
    const btn = document.getElementById('enrollBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Processing...';
    }
    
    try {
        let finalAmount = originalPrice;
        if (appliedCoupon && appliedCoupon.final_price) {
            finalAmount = parseFloat(appliedCoupon.final_price);
        }
        
        console.log('💰 Price:', originalPrice, 'Final:', finalAmount);
        
        if (finalAmount === 0) {
            await enrollFreeCourse(courseId);
        } else {
            await initRazorpayPayment(courseId, finalAmount);
        }
        
    } catch (error) {
        console.error('❌ Error:', error);
        alert('Error: ' + error.message);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Enroll Now';
        }
    }
}

// Free enrollment
async function enrollFreeCourse(courseId) {
    const data = { course_id: courseId };
    if (appliedCoupon) {
        data.coupon_id = appliedCoupon.coupon_id;
        data.coupon_code = appliedCoupon.coupon_code;
    }
    
    const res = await fetch('/api/enrollments.php?action=enroll', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    const result = await res.json();
    
   if (result.success) {
    alert('🎉 Enrolled successfully!');
    window.location.href = result.redirect || '/app/views/learner/my-courses.php'; // ✅ FIXED
} else {
        throw new Error(result.error || 'Enrollment failed');
    }
}

// Razorpay payment
async function initRazorpayPayment(courseId, amount) {
    const amountPaise = Math.round(amount * 100);
    
    const orderData = {
        course_id: courseId,
        amount: amountPaise
    };
    
    if (appliedCoupon) {
        orderData.coupon_id = appliedCoupon.coupon_id;
        orderData.coupon_code = appliedCoupon.coupon_code;
    }
    
    console.log('📦 Creating order:', orderData);
    
    const res = await fetch('/api/create-payments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
    });
    
    const result = await res.json();
    
    if (!result.success) {
        throw new Error(result.error || 'Order creation failed');
    }
    
    console.log('✅ Order created:', result.order_id);
    
    const options = {
        key: result.razorpay_key_id,
        amount: amountPaise,
        currency: 'INR',
        name: 'Internship Adda',
        description: '<?php echo addslashes($course['title'] ?? 'Course'); ?>',
        order_id: result.order_id,
        handler: function(response) {
            console.log('✅ Payment done:', response);
            verifyPaymentAndEnroll(response, courseId);
        },
        prefill: {
            name: result.user_name || '',
            email: result.user_email || '',
            contact: result.user_phone || ''
        },
        theme: { color: '#22c55e' },
        modal: {
            ondismiss: function() {
                const btn = document.getElementById('enrollBtn');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Enroll Now';
                }
            }
        }
    };
    
    const rzp = new Razorpay(options);
    
    rzp.on('payment.failed', function(response) {
        alert('Payment failed: ' + response.error.description);
        const btn = document.getElementById('enrollBtn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Enroll Now';
        }
    });
    
    rzp.open();
}

// Verify payment
async function verifyPaymentAndEnroll(payment, courseId) {
    const btn = document.getElementById('enrollBtn');
    if (btn) btn.textContent = 'Verifying...';
    
    const data = {
        razorpay_order_id: payment.razorpay_order_id,
        razorpay_payment_id: payment.razorpay_payment_id,
        razorpay_signature: payment.razorpay_signature,
        course_id: courseId
    };
    
    if (appliedCoupon) {
        data.coupon_id = appliedCoupon.coupon_id;
        data.coupon_code = appliedCoupon.coupon_code;
    }
    
    try {
        const res = await fetch('/api/verify-payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
    alert('🎉 Payment successful! You are now enrolled.');
    window.location.href = result.redirect || '/app/views/learner/my-courses.php'; // ✅ FIXED
} else {
            throw new Error(result.error || 'Verification failed');
        }
    } catch (error) {
        alert('Verification error: ' + error.message);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Enroll Now';
        }
    }
}

// Enter key support
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('couponInput');
    if (input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyCoupon();
            }
        });
    }
});
</script>


</body>
</html>
