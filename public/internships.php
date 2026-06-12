<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); ini_set('log_errors', 1);

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

// ✅ FIXED: Get ONLY active & published internships
try {
    $stmt = $db->prepare("
        SELECT i.*,
               COALESCE((SELECT COUNT(*) FROM internship_enrollments WHERE internship_id = i.id), 0) as total_applications,
               COALESCE((SELECT AVG(rating) FROM internship_reviews WHERE internship_id = i.id), 0) as avg_rating,
               COALESCE((SELECT COUNT(*) FROM internship_reviews WHERE internship_id = i.id), 0) as total_reviews
        FROM internships i
        WHERE i.is_active = 1
        ORDER BY i.created_at DESC
    ");
    $stmt->execute();
    $internships = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Fallback: simple query
    try {
        $stmt = $db->prepare("SELECT * FROM internships WHERE is_active = 1 ORDER BY created_at DESC");
        $stmt->execute();
        $internships = $stmt->fetchAll();
        
        // Add default values
        foreach ($internships as &$int) {
            $int['total_applications'] = 0;
            $int['avg_rating'] = 0;
            $int['total_reviews'] = 0;
        }
    } catch (PDOException $e2) {
        die("Query failed: " . $e2->getMessage());
    }
}

function safeText($text) {
    return htmlspecialchars_decode($text ?? '', ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Internships - Internship Adda</title>
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
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; font-weight: 700; }
        .line-clamp-2 { 
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .line-clamp-3 { 
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Header Animation */
        .header-scrolled {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
        }
        
        .nav-link {
            position: relative;
            padding: 8px 16px;
            color: #4b5563;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: #22c55e;
        }
        
        .btn-login {
            color: #22c55e;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: #f0fdf4;
            color: #16a34a;
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.3);
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.4);
        }
        
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .mobile-menu.active {
            max-height: 500px;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Sticky Container (Topbar + Navbar) -->
<div class="sticky top-0 z-50">
    
    <!-- TOP BAR -->
    <div class="bg-primary-600 text-white py-2" style="background: linear-gradient(to right, #16a34a, #15803d);">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-center gap-2 text-sm font-semibold">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                </svg>
                <a href="tel:+917209747479" class="hover:underline" style="color: white;">
                    Customer Care / Enquiry +91 7209747479
                </a>
            </div>
        </div>
    </div>
    
    <!-- NAVBAR -->
    <header id="header" class="w-full bg-white shadow-md transition-all duration-300">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="https://internshipadda.com">
                        <img src="../../icon.png" alt="InternshipAdda Logo" class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16">
                    </a>
                </div>

                <!-- Desktop Center Menu -->
                <div class="hidden lg:flex items-center justify-center flex-1 mx-8">
                    <div class="flex items-center space-x-2">
                        <a href="about.html" class="nav-link">About</a>
                        <a href="services.html" class="nav-link">Services</a>
                        <a href="courses.php" class="nav-link">Courses</a>
                        <a href="internships.php" class="nav-link text-primary-600 font-semibold">Internship</a>
                        <a href="verify.html" class="nav-link">Verify Certificate</a>
                        <a href="contact.html" class="nav-link">Contact</a>
                    </div>
                </div>
                
                <!-- Desktop Right Menu -->
                <div class="hidden lg:flex items-center space-x-3">
                    <?php if ($isLoggedIn): ?>
                        <?php if ($auth->isAdmin()): ?>
                            <a href="/app/views/admin/dashboard.php" class="btn-login">Dashboard</a>
                        <?php else: ?>
                            <a href="/app/views/learner/dashboard.php" class="btn-login">My Dashboard</a>
                        <?php endif; ?>
                        <a href="/api/auth.php?action=logout" class="btn-signup">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn-login">Login</a>
                        <a href="signup.php" class="btn-signup">Sign Up</a>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="mobile-menu lg:hidden">
                <div class="pt-4 pb-3 space-y-1">
                    <a href="about.html" class="nav-link block hover:bg-gray-50 rounded-lg">About</a>
                    <a href="services.html" class="nav-link block hover:bg-gray-50 rounded-lg">Services</a>
                    <a href="courses.php" class="nav-link block hover:bg-gray-50 rounded-lg">Courses</a>
                    <a href="internships.php" class="nav-link block hover:bg-gray-50 rounded-lg text-primary-600 font-semibold">Internship</a>
                    <a href="verify.html" class="nav-link block hover:bg-gray-50 rounded-lg">Verify Certificate</a>
                    <a href="contact.html" class="nav-link block hover:bg-gray-50 rounded-lg">Contact</a>
                    <div class="pt-4 space-y-3">
                        <?php if ($isLoggedIn): ?>
                            <?php if ($auth->isAdmin()): ?>
                                <a href="/app/views/admin/dashboard.php" class="btn-login block text-center">Dashboard</a>
                            <?php else: ?>
                                <a href="/app/views/learner/dashboard.php" class="btn-login block text-center">My Dashboard</a>
                            <?php endif; ?>
                            <a href="/api/auth.php?action=logout" class="btn-signup block text-center">Logout</a>
                        <?php else: ?>
                            <a href="login.php" class="btn-login block text-center">Login</a>
                            <a href="signup.php" class="btn-signup block text-center">Sign Up</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
</div>

<!-- Hero Section -->
<section class="bg-gradient-to-br from-primary-50 to-blue-50 py-12 md:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-gray-900 mb-4">
            Explore <span class="text-primary-600">Internship</span> Opportunities
        </h1>
        <p class="text-lg sm:text-xl text-gray-600 max-w-3xl mx-auto mb-8">
            Real-world internships with top companies. Gain experience, earn certificates, and boost your career.
        </p>
    </div>
</section>

<!-- Internships Grid -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <?php if (empty($internships)): ?>
        <div class="text-center py-16">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            <h3 class="text-xl font-bold text-gray-900 mb-2">No Internships Available</h3>
            <p class="text-gray-600">Check back soon for new opportunities!</p>
        </div>
    <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($internships as $internship): ?>
                <?php
                    $avgRating = round(floatval($internship['avg_rating'] ?? 0), 1);
                    $price = floatval($internship['price'] ?? 0);
                    $discountPrice = floatval($internship['discount_price'] ?? 0);
                    $isFree = $price == 0;
                    $hasDiscount = $discountPrice > 0 && $discountPrice < $price;
                    $finalPrice = $hasDiscount ? $discountPrice : $price;
                    $mode = $internship['mode'] ?? 'Remote';
                    $coverImage = $internship['cover_image'] ?? null;
                ?>
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden hover:shadow-2xl transition-all duration-300 hover:scale-105 flex flex-col">
                    <!-- Cover Image or Default -->
                    <div class="aspect-video relative overflow-hidden">
                        <?php if (!empty($coverImage)): ?>
                            <img src="<?php echo htmlspecialchars($coverImage); ?>" 
                                 alt="<?php echo safeText($internship['title']); ?>" 
                                 class="w-full h-full object-cover"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full h-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center\'><svg class=\'w-20 h-20 text-white opacity-80\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z\'></path></svg></div>';">
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
                                <svg class="w-20 h-20 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Mode Badge -->
                        <div class="absolute top-4 right-4">
                            <span class="bg-white text-primary-700 px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                                <?php echo safeText($mode); ?>
                            </span>
                        </div>
                        
                        <!-- Price Badge -->
                        <div class="absolute top-4 left-4">
                            <?php if ($isFree): ?>
                                <span class="bg-green-500 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                                    FREE
                                </span>
                            <?php else: ?>
                                <?php if ($hasDiscount): ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="bg-red-500 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                                            ₹<?php echo number_format($finalPrice); ?>
                                        </span>
                                        <span class="bg-white text-gray-600 px-2 py-0.5 rounded-full text-xs line-through shadow">
                                            ₹<?php echo number_format($price); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                                        ₹<?php echo number_format($price); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Internship Content -->
                    <div class="p-6 flex-1 flex flex-col">
                        <!-- Company Name & Category -->
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <?php if (!empty($internship['company_name'])): ?>
                                <span class="text-xs font-semibold text-primary-600 bg-primary-50 px-2 py-1 rounded">
                                    <?php echo safeText($internship['company_name']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($internship['category'])): ?>
                                <span class="text-xs font-semibold text-gray-600 bg-gray-100 px-2 py-1 rounded">
                                    <?php echo safeText($internship['category']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Title -->
                        <h3 class="text-xl font-bold text-gray-900 mb-2 line-clamp-2">
                            <?php echo safeText($internship['title']); ?>
                        </h3>
                        
                        <!-- Description -->
                        <p class="text-sm text-gray-600 mb-4 line-clamp-3 flex-1">
                            <?php echo safeText($internship['description']); ?>
                        </p>
                        
                        <!-- Meta Info -->
                        <div class="space-y-2 mb-4 text-xs text-gray-600">
                            <?php if (!empty($internship['location'])): ?>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span><?php echo safeText($internship['location']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($internship['duration'])): ?>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span><?php echo safeText($internship['duration']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($internship['stipend']) && floatval($internship['stipend']) > 0): ?>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>₹<?php echo number_format(floatval($internship['stipend'])); ?>/month stipend</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                <span><?php echo intval($internship['total_applications']); ?> applications</span>
                            </div>
                        </div>
                        
                        <!-- Rating & Reviews -->
                        <?php if (intval($internship['total_reviews']) > 0): ?>
                            <div class="flex items-center gap-2 mb-4 pb-4 border-b border-gray-100">
                                <div class="flex items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg class="w-4 h-4 <?php echo $i <= $avgRating ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-sm font-semibold text-gray-900"><?php echo $avgRating; ?></span>
                                <span class="text-xs text-gray-500">(<?php echo intval($internship['total_reviews']); ?>)</span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <a href="/public/internship-details.php?slug=<?php echo urlencode($internship['slug']); ?>" class="flex-1 bg-white border-2 border-primary-600 text-primary-600 text-center px-4 py-2.5 rounded-lg font-semibold hover:bg-primary-50 transition-colors text-sm">
                                View Details
                            </a>
                            <a href="/public/internship-details.php?slug=<?php echo urlencode($internship['slug']); ?>" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white text-center px-4 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                                Apply Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-white py-12 md:py-16">
    <div class="container mx-auto px-4">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12 mb-8 md:mb-12">
            <!-- Company Info -->
            <div>
                <div class="flex items-center space-x-3 mb-4 md:mb-6">
                    <a href="https://internshipadda.com"> 
                        <img src="../../icons.jpg" alt="InternshipAdda Logo" class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16">
                    </a>
                </div>
                <p class="text-sm md:text-base text-gray-400 leading-relaxed mb-4">
                    Empowering professionals worldwide with world-class internships and verified certifications.
                </p>
                <div class="flex space-x-4">
                    <a href="https://www.facebook.com/share/1DJn9wodPA/" target="_blank" class="w-10 h-10 bg-gray-800 hover:bg-blue-600 rounded-lg flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="https://www.instagram.com/_internshipadda.com_" target="_blank" class="w-10 h-10 bg-gray-800 hover:bg-gradient-to-br hover:from-purple-600 hover:to-pink-500 rounded-lg flex items-center justify-center transition-all">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </a>
                    <a href="https://www.linkedin.com/company/internship-adda/" target="_blank" class="w-10 h-10 bg-gray-800 hover:bg-blue-700 rounded-lg flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </a>
                    <a href="https://wa.me/917209747479" target="_blank" class="w-10 h-10 bg-gray-800 hover:bg-gray-900 rounded-lg flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 32 32">
                            <path d="M19.11 17.33c-.27-.14-1.58-.78-1.82-.87-.24-.09-.41-.14-.59.14-.18.27-.68.87-.83 1.05-.15.18-.3.2-.57.07-.27-.14-1.12-.41-2.13-1.3-.79-.7-1.32-1.56-1.47-1.83-.15-.27-.02-.42.11-.56.12-.12.27-.3.41-.45.14-.15.18-.27.27-.45.09-.18.05-.34-.02-.48-.07-.14-.59-1.42-.81-1.94-.21-.51-.43-.44-.59-.45h-.5c-.18 0-.48.07-.73.34-.25.27-.96.94-.96 2.3s.99 2.67 1.13 2.85c.14.18 1.94 2.96 4.7 4.15.66.28 1.17.45 1.57.57.66.21 1.26.18 1.73.11.53-.08 1.58-.65 1.8-1.28.23-.63.23-1.17.16-1.28-.07-.11-.25-.18-.52-.32z"/>
                            <path d="M16.04 3C9.41 3 4 8.41 4 15.04c0 2.65.87 5.1 2.34 7.09L4 29l7.06-2.29a11.96 11.96 0 004.98 1.08c6.63 0 12.04-5.41 12.04-12.04C28.08 8.41 22.67 3 16.04 3zm0 21.9c-1.61 0-3.18-.43-4.55-1.24l-.33-.2-4.19 1.36 1.37-4.08-.21-.34a9.86 9.86 0 01-1.52-5.36c0-5.45 4.44-9.89 9.89-9.89 5.45 0 9.89 4.44 9.89 9.89 0 5.45-4.44 9.89-9.89 9.89z"/>
                        </svg>
                    </a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Quick Links</h4>
                <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                    <li><a href="about.html" class="text-gray-400 hover:text-primary-500 transition-colors">About Us</a></li>
                    <li><a href="services.html" class="text-gray-400 hover:text-primary-500 transition-colors">Services</a></li>
                    <li><a href="courses.php" class="text-gray-400 hover:text-primary-500 transition-colors">Browse Programs</a></li>
                    <li><a href="contact.html" class="text-gray-400 hover:text-primary-500 transition-colors">Become Mentor</a></li>
                    <li><a href="contact.html" class="text-gray-400 hover:text-primary-500 transition-colors">Contact</a></li>
                </ul>
            </div>
            
            <!-- Categories -->
            <div>
                <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Categories</h4>
                <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                    <li><a href="internships.php" class="text-gray-400 hover:text-primary-500 transition-colors">Web Development</a></li>
                    <li><a href="internships.php" class="text-gray-400 hover:text-primary-500 transition-colors">Data Science</a></li>
                    <li><a href="internships.php" class="text-gray-400 hover:text-primary-500 transition-colors">Digital Marketing</a></li>
                    <li><a href="internships.php" class="text-gray-400 hover:text-primary-500 transition-colors">Mobile Development</a></li>
                    <li><a href="internships.php" class="text-gray-400 hover:text-primary-500 transition-colors">UI/UX Design</a></li>
                    <li><a href="internships.php" class="text-gray-400 hover:text-primary-500 transition-colors">Cloud Computing</a></li>
                </ul>
            </div>
            
            <!-- Support -->
            <div>
                <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Support</h4>
                <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                    <li><a href="contact.html" class="text-gray-400 hover:text-primary-500 transition-colors">Help Center</a></li>
                    <li><a href="toc.html" class="text-gray-400 hover:text-primary-500 transition-colors">Terms of Service</a></li>
                    <li><a href="privacy-policy.html" class="text-gray-400 hover:text-primary-500 transition-colors">Privacy Policy</a></li>
                    <li><a href="cookie.html" class="text-gray-400 hover:text-primary-500 transition-colors">Cookie Policy</a></li>
                    <li><a href="refund.html" class="text-gray-400 hover:text-primary-500 transition-colors">Refund Policy</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Bottom Footer -->
        <div class="border-t border-gray-800 pt-6 md:pt-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-gray-400 text-xs md:text-sm text-center md:text-left">
                    © 2026 Internship Adda. All rights reserved. Built with ❤️ for learners worldwide. 
                    <span class="mx-1">•</span>
                    Built by 
                    <a href="https://internshipadda.com/" target="_blank" class="text-gray-300 hover:text-white underline-offset-2 hover:underline">
                        Internship Adda Team
                    </a>
                </p>
            </div>
        </div>
    </div>
</footer>

<script>
// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('active');
    });
}

// Header Scroll Effect
const header = document.getElementById('header');
window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        header.classList.add('header-scrolled');
    } else {
        header.classList.remove('header-scrolled');
    }
});
</script>

</body>
</html>