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
        __DIR__ . '/../../controllers/' . $class . '.php',
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

if ($auth->isAdmin()) {
    header('Location: /app/views/admin/dashboard.php');
    exit;
}

$currentUser = $auth->user();
$userId = $auth->id();

// Stats for sidebar
$stats = [
    'total_enrollments' => 0,
    'total_certificates' => 0,
];
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_enrollments'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $stats['total_certificates'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // ignore
}

// Resource items shown on this page
$reportItems = [
    [
        'title'       => 'Internship Report Guidelines',
        'description' => 'Read the official guidelines explaining what your internship report must cover, formatting rules, page count and submission expectations.',
        'href'        => 'https://internshipadda.com/internship-report-guidelines.pdf',
        'cta'         => 'View',
        'icon'        => 'book',
        'is_new'      => false,
    ],
    [
        'title'       => 'Sample Internship Report',
        'description' => 'See a complete, real-world internship report. Use it as a reference for tone, structure and depth of content.',
        'href'        => 'https://internshipadda.com/sample-internship-report.pdf',
        'cta'         => 'View',
        'icon'        => 'document',
        'is_new'      => false,
    ],
    [
        'title'       => 'Internship Report Template',
        'description' => 'A ready-to-use Word/PDF template with all required sections — cover page, declaration, abstract, weekly logs and conclusion.',
        'href'        => 'https://internshipadda.com/internship-report-template.docx',
        'cta'         => 'View',
        'icon'        => 'template',
        'is_new'      => true,
    ],
    [
        'title'       => 'Tips to complete your Internship Report',
        'description' => 'Step-by-step tips, common mistakes to avoid, and a checklist to make sure your report passes admin review on the first attempt.',
        'href'        => 'https://internshipadda.com/internship-report-tips',
        'cta'         => 'Read',
        'icon'        => 'lightbulb',
        'is_new'      => true,
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Internship Report - Internship Adda</title>

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

        .mobile-navbar {
            position: fixed; top: 0; left: 0; right: 0;
            background: white; border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }
        .sidebar-menu {
            position: fixed; top: 0; right: -100%; width: 290px; height: 100vh;
            background: white; box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            z-index: 200; transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }
        .sidebar-menu.active { right: 0; }
        .menu-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6); z-index: 150;
            opacity: 0; pointer-events: none;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .menu-overlay.active { opacity: 1; pointer-events: all; }
        .hamburger span {
            display: block; width: 24px; height: 2px;
            background: #374151; transition: all 0.3s ease; border-radius: 2px;
        }
        .hamburger span:nth-child(2) { margin: 5px 0; }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(7px, 7px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(7px, -7px); }
        .mobile-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: white; border-top: 1px solid #e5e7eb;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 50;
        }
        .touch-btn { min-height: 44px; min-width: 44px; }
        .main-content { padding-top: 70px; }
        .desktop-sidebar { display: none; }

        /* NEW badge — playful gradient like screenshot */
        .new-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: white;
            background: linear-gradient(90deg, #ec4899 0%, #f59e0b 100%);
            border-radius: 6px;
            transform: rotate(-4deg) translateY(-2px);
            box-shadow: 0 2px 6px rgba(236, 72, 153, 0.35);
        }

        @media (min-width: 769px) {
            .mobile-navbar, .mobile-nav { display: none; }
            .desktop-sidebar { display: block; }
            .main-content { padding-top: 0; }
            body { padding-bottom: 0; }
        }
    </style>
</head>
<body>

<!-- Mobile Top Navbar -->
<nav class="mobile-navbar">
    <div class="flex items-center justify-between px-4 py-4">
        <div class="flex items-center gap-2">
            <a href="https://internshipadda.com">
                <img src="https://internshipadda.com/icon.png" alt="InternshipAdda Logo" class="w-auto max-h-12">
            </a>
        </div>
        <button onclick="toggleMenu()" class="touch-btn hamburger p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 transition-colors" id="hamburger">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

<!-- Mobile Sidebar -->
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
                <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-lg truncate"><?php echo htmlspecialchars($currentUser['name']); ?></h3>
                <p class="text-sm text-white/80 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
            </div>
        </div>
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

    <div class="p-4">
        <div class="space-y-1">
            <a href="/app/views/learner/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                Dashboard
            </a>

            <div class="px-4 pt-4 pb-2">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">📚 Courses</h4>
            </div>
            <a href="/app/views/learner/my-courses.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">My Courses</a>
            <a href="/public/courses.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Browse Courses</a>
            <a href="/app/views/learner/certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Course Certificates</a>

            <div class="px-4 pt-4 pb-2">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">💼 Internships</h4>
            </div>
            <a href="/app/views/learner/my-internships.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">My Internships</a>
            <a href="/public/internships.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Browse Internships</a>
            <a href="/app/views/learner/internship-certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Internship Certificates</a>
            <a href="/app/views/learner/internship-report.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 font-semibold">Internship Report</a>

            <div class="border-t border-gray-200 my-3"></div>

            <a href="/app/views/learner/reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Reports</a>
            <a href="/app/views/learner/referrals.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Referrals</a>
            <a href="/app/views/learner/profile.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Profile</a>
        </div>

        <div class="border-t border-gray-200 my-4"></div>
        <a href="/api/auth.php?action=logout" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors font-semibold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
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
        <!-- Desktop Header -->
        <header class="hidden md:block bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">📋 Internship Report</h1>
                        <p class="text-gray-600 mt-1">Resources to help you write and submit a professional internship report</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="px-4 sm:px-6 md:px-8 py-4 md:py-8">
            <!-- Mobile Header -->
            <div class="md:hidden mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-1">📋 Internship Report</h1>
                <p class="text-sm text-gray-600">Resources to help you write your report</p>
            </div>

            <!-- Info Banner -->
            <div class="bg-gradient-to-br from-primary-50 via-emerald-50 to-teal-50 border-2 border-primary-200 rounded-2xl p-5 sm:p-6 mb-6 sm:mb-8">
                <div class="flex flex-col sm:flex-row gap-4 items-start">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 bg-primary-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2">What goes into an Internship Report?</h3>
                        <p class="text-sm text-gray-700 mb-2">A complete internship report typically contains: cover page, declaration, acknowledgements, abstract, weekly progress logs, learnings, challenges faced, and a final conclusion with future scope.</p>
                        <p class="text-xs text-gray-600">📌 Use the resources below as your starting point — read the guidelines first, then fill the template using the sample as reference.</p>
                    </div>
                </div>
            </div>

            <!-- Report Resources Table -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-8 py-5 border-b border-gray-200 bg-white">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Internship Report</h2>
                </div>

                <!-- Table Header (desktop) -->
                <div class="hidden md:grid grid-cols-[1fr_140px] gap-6 px-8 py-4 bg-gray-50 border-b border-gray-200">
                    <div class="text-sm font-bold text-gray-900 uppercase tracking-wider">Topic</div>
                    <div class="text-sm font-bold text-gray-900 uppercase tracking-wider">View</div>
                </div>

                <!-- Rows -->
                <div class="divide-y divide-gray-100">
                    <?php foreach ($reportItems as $item): ?>
                        <div class="grid grid-cols-1 md:grid-cols-[1fr_140px] gap-3 md:gap-6 px-5 sm:px-8 py-5 hover:bg-gray-50 transition-colors items-start md:items-center">
                            <div class="flex items-start gap-3">
                                <div class="hidden sm:flex w-10 h-10 bg-primary-50 rounded-lg items-center justify-center flex-shrink-0">
                                    <?php if ($item['icon'] === 'book'): ?>
                                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                    <?php elseif ($item['icon'] === 'document'): ?>
                                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    <?php elseif ($item['icon'] === 'template'): ?>
                                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-base sm:text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <?php if ($item['is_new']): ?>
                                            <span class="new-badge">NEW</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs sm:text-sm text-gray-600 mt-1 leading-relaxed"><?php echo htmlspecialchars($item['description']); ?></p>
                                </div>
                            </div>
                            <div class="md:text-left">
                                <a href="<?php echo htmlspecialchars($item['href']); ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 font-semibold text-sm sm:text-base">
                                    <?php echo htmlspecialchars($item['cta']); ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Submit Hint -->
            <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-xl p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="text-2xl flex-shrink-0">📤</span>
                    <div>
                        <p class="text-sm font-bold text-gray-900 mb-1">Ready to submit your report?</p>
                        <p class="text-xs sm:text-sm text-gray-700">Once your internship report is complete, request your certificate from the
                            <a href="/app/views/learner/my-internships.php" class="text-primary-700 font-semibold underline">My Internships</a>
                            page. Admin will review your report along with the request.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav">
    <div class="grid grid-cols-5 h-full">
        <a href="/app/views/learner/dashboard.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span class="text-xs font-semibold">Home</span>
        </a>
        <a href="/app/views/learner/my-courses.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <span class="text-xs font-semibold">Courses</span>
        </a>
        <a href="/public/internships.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600">
            <div class="w-12 h-12 -mt-6 bg-primary-600 rounded-full flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <span class="text-xs font-semibold mt-1">Explore</span>
        </a>
        <a href="/app/views/learner/internship-report.php" class="flex flex-col items-center justify-center py-2 text-primary-600 bg-primary-50">
            <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="text-xs font-semibold">Report</span>
        </a>
        <a href="/app/views/learner/profile.php" class="flex flex-col items-center justify-center py-2 text-gray-600 hover:text-primary-600">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
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
</script>

</body>
</html>
