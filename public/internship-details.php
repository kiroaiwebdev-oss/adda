<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

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

// Get internship slug from URL
$internshipSlug = $_GET['slug'] ?? null;
if (!$internshipSlug) {
    header('Location: /public/internships.php');
    exit;
}

// Get internship details
try {
    $stmt = $db->prepare("
        SELECT i.*,
               0 as total_applications,
               0 as avg_rating,
               0 as total_reviews
        FROM internships i
        WHERE i.slug = ? AND i.is_active = 1
    ");
    $stmt->execute([$internshipSlug]);
    $internship = $stmt->fetch();
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

if (!$internship) {
    header('Location: /public/internships.php');
    exit;
}

// Check if user has applied
$hasApplied = false;
if ($isLoggedIn) {
    try {
        $applyCheck = $db->prepare("SELECT id FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
        $applyCheck->execute([$userId, $internship['id']]);
        $hasApplied = (bool)$applyCheck->fetch();
    } catch (PDOException $e) {
        // Table doesn't exist yet, ignore
    }
}

// Get reviews
$reviews = [];
try {
    $reviewsStmt = $db->prepare("
        SELECT ir.*, u.name as user_name
        FROM internship_reviews ir
        JOIN users u ON ir.user_id = u.id
        WHERE ir.internship_id = ?
        ORDER BY ir.created_at DESC
        LIMIT 10
    ");
    $reviewsStmt->execute([$internship['id']]);
    $reviews = $reviewsStmt->fetchAll();
} catch (PDOException $e) {
    // Table doesn't exist yet, ignore
}

// Check if user has already reviewed
$hasReviewed = false;
if ($isLoggedIn) {
    try {
        $reviewCheck = $db->prepare("SELECT id FROM internship_reviews WHERE user_id = ? AND internship_id = ?");
        $reviewCheck->execute([$userId, $internship['id']]);
        $hasReviewed = (bool)$reviewCheck->fetch();
    } catch (PDOException $e) {
        // Table doesn't exist yet, ignore
    }
}

// Calculate price properly
$isFree = ($internship['price'] ?? 0) == 0;
$price = floatval($internship['price'] ?? 0);
$discountPrice = floatval($internship['discount_price'] ?? 0);

// Check if discount is set
$hasDiscount = $discountPrice > 0 && $discountPrice < $price;

// This is the CURRENT price user will pay (before coupon)
$currentPrice = $hasDiscount ? $discountPrice : $price;

// For JavaScript - send the CURRENT price (after admin discount)
$jsCurrentPrice = $currentPrice;

$avgRating = round($internship['avg_rating'] ?? 0, 1);

// ✅ ADDED: Current page URL for post-login/signup & post-enrollment redirect
$currentPageUrl = '/public/internship-details.php?slug=' . urlencode($internshipSlug);

function safeText($text) {
    return htmlspecialchars_decode($text ?? '', ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safeText($internship['title']); ?> - Internship Adda</title>
    
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
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; font-weight: 700; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
                <a href="/public/internships.php" class="text-sm sm:text-base text-primary-600 font-semibold">Internships</a>
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
                    <!-- ✅ FIXED: Login & Signup with redirect back to this internship page -->
                    <a href="/public/login.php?redirect=<?php echo urlencode($currentPageUrl); ?>" class="text-sm sm:text-base text-gray-600 hover:text-primary-600 font-semibold">Login</a>
                    <a href="/public/signup.php?redirect=<?php echo urlencode($currentPageUrl); ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 sm:px-6 sm:py-2 rounded-lg font-semibold transition-colors text-sm sm:text-base">
                        Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Hero Section -->
<section class="bg-gradient-to-br from-primary-50 to-blue-50 py-6 sm:py-8 lg:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Left: Internship Info -->
            <div class="lg:col-span-2">
                <div class="mb-4">
                    <a href="/public/internships.php" class="text-primary-600 hover:text-primary-700 font-semibold inline-flex items-center gap-2 text-sm sm:text-base">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Internships
                    </a>
                </div>
                
                <!-- Company & Category Tags -->
                <div class="flex flex-wrap gap-2 mb-3">
                    <?php if (!empty($internship['company_name'])): ?>
                        <span class="text-xs font-semibold text-primary-600 bg-primary-50 px-3 py-1 rounded-full">
                            <?php echo safeText($internship['company_name']); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($internship['category'])): ?>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                            <?php echo safeText($internship['category']); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($internship['mode'])): ?>
                        <span class="text-xs font-semibold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">
                            <?php echo safeText($internship['mode']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <h1 class="text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-bold text-gray-900 mb-3 sm:mb-4">
                    <?php echo safeText($internship['title']); ?>
                </h1>
                
                <p class="text-base sm:text-lg text-gray-600 mb-4 sm:mb-6">
                    <?php echo safeText($internship['description']); ?>
                </p>
                
                <!-- Rating & Stats -->
                <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-xs sm:text-sm">
                    <?php if ($internship['total_reviews'] > 0): ?>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 <?php echo $i <= $avgRating ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <span class="font-semibold text-gray-900"><?php echo $avgRating; ?></span>
                            <span class="text-gray-500">(<?php echo $internship['total_reviews']; ?>)</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-2 text-gray-600">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span><?php echo $internship['total_applications']; ?> applications</span>
                    </div>
                    
                    <?php if (!empty($internship['location'])): ?>
                        <div class="flex items-center gap-2 text-gray-600">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            </svg>
                            <span><?php echo safeText($internship['location']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($internship['duration'])): ?>
                        <div class="flex items-center gap-2 text-gray-600">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span><?php echo safeText($internship['duration']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right: Application Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-200 lg:sticky lg:top-24">
                    <!-- Cover Image -->
                    <div class="aspect-video rounded-lg overflow-hidden mb-4">
                        <?php if (!empty($internship['cover_image'])): ?>
                            <img src="<?php echo htmlspecialchars($internship['cover_image']); ?>" 
                                 alt="<?php echo safeText($internship['title']); ?>" 
                                 class="w-full h-full object-cover"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full h-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center\'><svg class=\'w-16 h-16 text-white opacity-80\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z\'></path></svg></div>';">
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
                                <svg class="w-16 h-16 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Price Section -->
                    <div class="mb-4" id="priceContainer">
                        <?php if ($isFree): ?>
                            <div class="text-2xl sm:text-3xl font-bold text-green-600 mb-2">FREE</div>
                            <p class="text-gray-600 text-xs sm:text-sm">Apply at no cost</p>
                        <?php else: ?>
                            <!-- Original Price Section (No Admin Discount) -->
                            <div id="originalPriceSection" <?php echo $hasDiscount ? 'class="hidden"' : ''; ?>>
                                <div class="text-3xl font-bold text-gray-900 mb-2">₹<?php echo number_format($price, 2); ?></div>
                                <p class="text-gray-600 text-sm">Application fee</p>
                            </div>
                            
                            <!-- Discounted Price Section (Admin Discount) -->
                            <?php if ($hasDiscount): ?>
                                <div id="discountedPriceSection">
                                    <div class="flex items-center gap-3 mb-2 flex-wrap">
                                        <div class="text-3xl font-bold text-green-600">₹<?php echo number_format($discountPrice, 2); ?></div>
                                        <div class="text-lg text-gray-400 line-through">₹<?php echo number_format($price, 2); ?></div>
                                    </div>
                                    <div class="mb-2">
                                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                                            <?php echo round((($price - $discountPrice) / $price) * 100); ?>% OFF
                                        </span>
                                    </div>
                                    <p class="text-green-600 text-sm font-semibold">
                                        🎉 You save ₹<?php echo number_format($price - $discountPrice, 2); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Coupon Input -->
                            <?php if (!$hasApplied && $isLoggedIn): ?>
                                <div class="mt-4 border-t pt-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Have a coupon / Referral Code?</label>
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
                                    <div id="couponMessage" class="mt-2 text-sm hidden"></div>
                                    
                                    <button 
                                        onclick="removeCoupon()" 
                                        id="removeCouponBtn" 
                                        class="hidden w-full mt-2 text-red-600 hover:text-red-700 text-sm font-semibold">
                                        ✕ Remove Coupon
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Stipend Info -->
                        <?php if (!empty($internship['stipend']) && $internship['stipend'] > 0): ?>
                            <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center gap-2 text-green-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <div class="font-bold">₹<?php echo number_format($internship['stipend']); ?>/month</div>
                                        <div class="text-xs">Stipend during internship</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Application Buttons -->
                    <?php if ($hasApplied): ?>
                        <a href="/app/views/learner/internship-player.php?id=<?php echo $internship['id']; ?>" 
                           class="block w-full bg-primary-600 hover:bg-primary-700 text-white text-center px-6 py-4 rounded-lg font-bold text-lg transition-colors mb-3">
                            Start Internship →
                        </a>
                        <p class="text-center text-sm text-green-600 font-semibold">✓ You're enrolled in this internship</p>
                    <?php elseif (!$isLoggedIn): ?>
                        <!-- ✅ FIXED: Login to Apply button with redirect back to this page -->
                        <a href="/public/login.php?redirect=<?php echo urlencode($currentPageUrl); ?>" 
                           class="block w-full bg-primary-600 hover:bg-primary-700 text-white text-center px-6 py-4 rounded-lg font-bold text-lg transition-colors mb-3">
                            Login to Apply
                        </a>
                        <!-- ✅ FIXED: New user? Signup button also with redirect -->
                        <a href="/public/signup.php?redirect=<?php echo urlencode($currentPageUrl); ?>" 
                           class="block w-full bg-white hover:bg-gray-50 text-primary-600 border-2 border-primary-600 text-center px-6 py-3 rounded-lg font-bold text-base transition-colors">
                            New User? Sign Up
                        </a>
                    <?php else: ?>
                        <button onclick="applyInternship(<?php echo $internship['id']; ?>)" 
                                id="applyInternshipBtn" 
                                class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-4 rounded-lg font-bold text-lg transition-colors mb-3">
                            Apply Now
                        </button>
                    <?php endif; ?>

                    <div class="border-t pt-4 mt-4 space-y-2 text-sm text-gray-600">
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
                            <span>Real-world experience</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Flexible schedule</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Tabs Section -->
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
                    <button onclick="switchTab('requirements')" class="tab-btn flex-1 px-6 py-4 font-semibold text-gray-600 hover:text-primary-600 border-b-2 border-transparent hover:border-primary-600 transition-colors" data-tab="requirements">
                        Requirements
                    </button>
                    <button onclick="switchTab('reviews')" class="tab-btn flex-1 px-6 py-4 font-semibold text-gray-600 hover:text-primary-600 border-b-2 border-transparent hover:border-primary-600 transition-colors" data-tab="reviews">
                        Reviews (<?php echo count($reviews); ?>)
                    </button>
                </div>
                
                <!-- Tab Content -->
                <div class="p-8">
                    <!-- Overview Tab -->
                    <div id="overview-tab" class="tab-content active">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">About This Internship</h2>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            <?php echo nl2br(safeText($internship['description'])); ?>
                        </p>
                        
                        <?php if (!empty($internship['responsibilities'])): ?>
                            <h3 class="text-xl font-bold text-gray-900 mb-3">Responsibilities</h3>
                            <div class="text-gray-600 mb-6 space-y-2">
                                <?php 
                                $responsibilities = explode("\n", $internship['responsibilities']);
                                foreach ($responsibilities as $resp):
                                    if (trim($resp)):
                                ?>
                                    <div class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span><?php echo safeText(trim($resp)); ?></span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($internship['benefits'])): ?>
                            <h3 class="text-xl font-bold text-gray-900 mb-3">Benefits</h3>
                            <div class="text-gray-600 space-y-2">
                                <?php 
                                $benefits = explode("\n", $internship['benefits']);
                                foreach ($benefits as $benefit):
                                    if (trim($benefit)):
                                ?>
                                    <div class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span><?php echo safeText(trim($benefit)); ?></span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Requirements Tab -->
                    <div id="requirements-tab" class="tab-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Requirements</h2>
                        <?php if (!empty($internship['requirements'])): ?>
                            <div class="space-y-2 text-gray-600">
                                <?php 
                                $requirements = explode("\n", $internship['requirements']);
                                foreach ($requirements as $req):
                                    if (trim($req)):
                                ?>
                                    <div class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span><?php echo safeText(trim($req)); ?></span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">No specific requirements listed.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Reviews Tab -->
                    <div id="reviews-tab" class="tab-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Student Reviews</h2>
                        
                        <!-- Add Review Form (Only for applied students) -->
                        <?php if ($hasApplied && !$hasReviewed): ?>
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
        
        <!-- Sidebar: Certificate -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow border border-gray-200 p-6 sticky top-24">
                <h3 class="font-bold text-gray-900 mb-4 text-lg">Internship Certificate</h3>
                <p class="text-sm text-gray-600 mb-4">Complete this internship to earn your professional certificate and showcase your achievement!</p>
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
// ✅ FINAL FIXED JavaScript Code
let appliedCoupon = null;
const regularPrice = <?php echo $price; ?>;
const currentPrice = <?php echo $jsCurrentPrice; ?>;
const internshipId = <?php echo $internship['id']; ?>;
const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
const userId = <?php echo $userId ?? 'null'; ?>;
const hasAdminDiscount = <?php echo $hasDiscount ? 'true' : 'false'; ?>;
const isFree = <?php echo $isFree ? 'true' : 'false'; ?>;

// ✅ ADDED: Current page URL for post-enrollment redirect
const currentPageUrl = '<?php echo addslashes($currentPageUrl); ?>';

console.log('🎯 Internship Details Loaded:', {internshipId, regularPrice, currentPrice, isFree, isLoggedIn});

// Tab Switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active', 'text-primary-600', 'border-primary-600'));
    document.getElementById(`${tabName}-tab`).classList.add('active');
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    activeBtn.classList.add('active', 'text-primary-600', 'border-primary-600');
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
    formData.append('internship_id', internshipId);
    try {
        const response = await fetch('/api/internship-reviews.php?action=submit', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            alert('✅ Review submitted successfully!');
            location.reload();
        } else {
            alert(result.error || 'Failed to submit review');
        }
    } catch (error) {
        console.error('Review error:', error);
        alert('Network error. Please try again.');
    }
}

// ✅ Apply Coupon
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
    
    try {
        const formData = new FormData();
        formData.append('code', couponCode);
        formData.append('order_type', 'internship');
        formData.append('order_id', internshipId);
        formData.append('amount', currentPrice);
        
        const response = await fetch('/api/coupons.php?action=validate', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('✅ Coupon validation result:', result);
        
        if (result.success) {
            appliedCoupon = {
                id: result.coupon.id,
                code: couponCode,
                discount: parseFloat(result.discount || 0),
                finalAmount: parseFloat(result.final_amount || result.finalAmount || currentPrice)
            };
            
            console.log('✅ Applied coupon:', appliedCoupon);
            
            updatePriceDisplay(result);
            showCouponMessage(result.message || '✅ Coupon applied successfully!', 'success');
            couponInput.disabled = true;
            applyBtn.classList.add('hidden');
            document.getElementById('removeCouponBtn').classList.remove('hidden');
        } else {
            showCouponMessage(result.error || 'Invalid coupon code', 'error');
            appliedCoupon = null;
        }
    } catch (error) {
        console.error('❌ Coupon error:', error);
        showCouponMessage('Failed to apply coupon. Please try again.', 'error');
        appliedCoupon = null;
    } finally {
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply';
    }
}

// Remove Coupon
function removeCoupon() {
    appliedCoupon = null;
    if (hasAdminDiscount) {
        document.getElementById('discountedPriceSection').classList.remove('hidden');
    } else {
        document.getElementById('originalPriceSection').classList.remove('hidden');
    }
    const couponDiscountSection = document.getElementById('couponDiscountSection');
    if (couponDiscountSection) {
        couponDiscountSection.classList.add('hidden');
    }
    document.getElementById('couponInput').value = '';
    document.getElementById('couponInput').disabled = false;
    document.getElementById('applyBtn').classList.remove('hidden');
    document.getElementById('removeCouponBtn').classList.add('hidden');
    document.getElementById('couponMessage').textContent = '';
    document.getElementById('couponMessage').classList.add('hidden');
}

// Update Price Display
function updatePriceDisplay(result) {
    try {
        const originalPriceSection = document.getElementById('originalPriceSection');
        const discountedPriceSection = document.getElementById('discountedPriceSection');
        
        if (originalPriceSection) originalPriceSection.classList.add('hidden');
        if (discountedPriceSection) discountedPriceSection.classList.add('hidden');
        
        let couponDiscountSection = document.getElementById('couponDiscountSection');
        
        if (!couponDiscountSection) {
            couponDiscountSection = document.createElement('div');
            couponDiscountSection.id = 'couponDiscountSection';
            couponDiscountSection.className = 'mb-4';
            
            const priceContainer = document.getElementById('priceContainer');
            if (priceContainer) {
                const couponDiv = priceContainer.querySelector('.border-t');
                if (couponDiv) {
                    priceContainer.insertBefore(couponDiscountSection, couponDiv);
                } else {
                    priceContainer.insertBefore(couponDiscountSection, priceContainer.firstChild);
                }
            }
        }
        
        const finalPrice = parseFloat(result.final_amount || result.finalAmount || 0);
        const discountAmount = parseFloat(result.discount || 0);
        const adminDiscountAmount = regularPrice - currentPrice;
        const totalSavings = adminDiscountAmount + discountAmount;
        const totalDiscountPercent = Math.round((totalSavings / regularPrice) * 100);
        
        couponDiscountSection.innerHTML = `
            <div class="flex items-center gap-3 mb-2 flex-wrap">
                <div class="text-3xl font-bold text-green-600">₹${finalPrice.toFixed(2)}</div>
                <div class="text-lg text-gray-400 line-through">₹${regularPrice.toFixed(2)}</div>
            </div>
            <div class="mb-2 flex gap-2 flex-wrap">
                ${adminDiscountAmount > 0 ? `<span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold">Admin: ${Math.round((adminDiscountAmount/regularPrice)*100)}% OFF</span>` : ''}
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">Coupon: ₹${discountAmount.toFixed(0)} OFF</span>
                <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-bold">Total: ${totalDiscountPercent}% OFF</span>
            </div>
            <p class="text-green-600 text-sm font-semibold">
                🎉 Total savings: ₹${totalSavings.toFixed(2)}
                ${adminDiscountAmount > 0 ? `<br/><span class="text-xs">(₹${adminDiscountAmount.toFixed(0)} admin + ₹${discountAmount.toFixed(0)} coupon)</span>` : ''}
            </p>
        `;
        
        couponDiscountSection.classList.remove('hidden');
    } catch (error) {
        console.error('❌ Price display error:', error);
    }
}

// Show Coupon Message
function showCouponMessage(message, type) {
    const messageDiv = document.getElementById('couponMessage');
    if (!messageDiv) return;
    messageDiv.textContent = message;
    messageDiv.classList.remove('hidden', 'text-red-600', 'text-green-600', 'text-gray-600');
    messageDiv.classList.add(type === 'error' ? 'text-red-600' : type === 'success' ? 'text-green-600' : 'text-gray-600');
}

// ✅ MAIN APPLY FUNCTION
async function applyInternship(id) {
    console.log('🎯 Apply button clicked for internship:', id);
    
    if (!isLoggedIn) {
        alert('Please login first to apply');
        window.location.href = '/public/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    }
    
    const applyBtn = document.getElementById('applyInternshipBtn');
    if (applyBtn) {
        applyBtn.disabled = true;
        applyBtn.textContent = '⏳ Processing...';
    }
    
    // ✅ FREE INTERNSHIP
    if (isFree) {
        console.log('✅ FREE Internship - Direct enrollment');
        
        try {
            const formData = new FormData();
            formData.append('internship_id', internshipId);
            if (appliedCoupon) {
                formData.append('coupon_id', appliedCoupon.id);
            }
            
            const response = await fetch('/api/internship-enrollment.php?action=enroll', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log('📦 Enrollment result:', result);
            
            if (result.success) {
                alert('✅ ' + result.message);
                // ✅ FIXED: Redirect back to this internship page (not my-internships)
                window.location.href = currentPageUrl;
            } else {
                if (result.redirect) {
                    console.log('🚀 Already enrolled - Redirecting to:', result.redirect);
                    alert(result.error || 'Already enrolled');
                    window.location.href = result.redirect;
                } else {
                    throw new Error(result.error || 'Enrollment failed');
                }
            }
        } catch (error) {
            console.error('❌ Enrollment error:', error);
            alert('Error: ' + error.message);
            if (applyBtn) {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Apply Now';
            }
        }
        
        return;
    }
    
    // ✅ PAID INTERNSHIP - Create Razorpay order
    console.log('💳 PAID Internship - Creating payment order');
    
    try {
        let finalAmount = currentPrice;
        if (appliedCoupon && appliedCoupon.finalAmount) {
            finalAmount = parseFloat(appliedCoupon.finalAmount);
        }
        
        console.log('💰 Final Amount:', finalAmount);
        
        const orderFormData = new FormData();
        orderFormData.append('internship_id', internshipId);
        orderFormData.append('amount', finalAmount);
        if (appliedCoupon) {
            orderFormData.append('coupon_id', appliedCoupon.id);
        }
        
        const orderResponse = await fetch('/api/create-razorpay-order.php', {
            method: 'POST',
            body: orderFormData
        });
        
        const orderResult = await orderResponse.json();
        console.log('📦 Razorpay order:', orderResult);
        
        if (!orderResult.success || !orderResult.order_id) {
            throw new Error(orderResult.error || 'Failed to create payment order');
        }
        
        console.log('🔑 Razorpay Key:', orderResult.key_id);
        
        // ✅ Initialize Razorpay payment
        const options = {
            key: orderResult.key_id,
            amount: orderResult.amount,
            currency: orderResult.currency || 'INR',
            name: 'Internship Adda',
            description: <?php echo json_encode($internship['title']); ?>,
            order_id: orderResult.order_id,
            handler: async function(response) {
                console.log('✅ Payment successful:', response);
                
                if (applyBtn) {
                    applyBtn.textContent = '⏳ Verifying payment...';
                }
                
                try {
                    const enrollFormData = new FormData();
                    enrollFormData.append('internship_id', internshipId);
                    enrollFormData.append('razorpay_payment_id', response.razorpay_payment_id);
                    enrollFormData.append('razorpay_order_id', response.razorpay_order_id);
                    enrollFormData.append('razorpay_signature', response.razorpay_signature);
                    enrollFormData.append('amount', finalAmount);
                    if (appliedCoupon) {
                        enrollFormData.append('coupon_id', appliedCoupon.id);
                    }
                    
                    const enrollResponse = await fetch('/api/internship-enrollment.php?action=enroll', {
                        method: 'POST',
                        body: enrollFormData
                    });
                    
                    const enrollResult = await enrollResponse.json();
                    console.log('📦 Enrollment result:', enrollResult);
                    
                    if (enrollResult.success) {
                        alert('🎉 ' + enrollResult.message);
                        // ✅ FIXED: Redirect back to this internship page (not my-internships)
                        window.location.href = currentPageUrl;
                    } else {
                        throw new Error(enrollResult.error || 'Enrollment failed');
                    }
                    
                } catch (error) {
                    console.error('❌ Verification error:', error);
                    alert('Payment successful but verification failed. Please contact support with Payment ID: ' + response.razorpay_payment_id);
                    // ✅ FIXED: Even on error, redirect to this page
                    window.location.href = currentPageUrl;
                }
            },
            modal: {
                ondismiss: function() {
                    console.log('⚠️ Payment cancelled by user');
                    if (applyBtn) {
                        applyBtn.disabled = false;
                        applyBtn.textContent = 'Apply Now';
                    }
                }
            },
            prefill: {
                name: orderResult.prefill?.name || '',
                email: orderResult.prefill?.email || '',
                contact: orderResult.prefill?.contact || ''
            },
            theme: {
                color: '#16a34a'
            }
        };
        
        console.log('🚀 Opening Razorpay modal...');
        const razorpay = new Razorpay(options);
        
        razorpay.on('payment.failed', function(response) {
            console.error('❌ Payment failed:', response.error);
            alert('Payment failed: ' + response.error.description);
            if (applyBtn) {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Apply Now';
            }
        });
        
        razorpay.open();
        
    } catch (error) {
        console.error('❌ Payment error:', error);
        alert('Error: ' + error.message);
        if (applyBtn) {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply Now';
        }
    }
}
</script>


</body>
</html>