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
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

require_once __DIR__ . '/../app/models/Certificate.php';
require_once __DIR__ . '/../app/models/InternshipCertificate.php';

$certificateModel = new Certificate($db);
$internshipCertModel = new InternshipCertificate($db);

$verificationCode = trim($_GET['code'] ?? '');
$verificationResult = null;
$certificateType = null;

if ($verificationCode) {
    // ✅ Try internship certificate first (supports both certificate_code AND certificate_number)
    $cert = $internshipCertModel->getByCode($verificationCode);
    
    if ($cert) {
        // ✅ Found internship certificate
        if (isset($cert['revoked']) && $cert['revoked']) {
            $verificationResult = [
                'valid' => false,
                'message' => 'Certificate has been revoked',
                'certificate' => $cert
            ];
        } else {
            $verificationResult = [
                'valid' => true,
                'message' => 'Certificate is valid',
                'certificate' => $cert
            ];
        }
        $certificateType = 'internship';
    } else {
        // ✅ Try course certificate
        $result = $certificateModel->verify($verificationCode);
        
        if ($result['valid']) {
            $verificationResult = $result;
            $certificateType = 'course';
        } else {
            // ✅ Not found in either table
            $verificationResult = [
                'valid' => false,
                'message' => 'Certificate not found'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - Internship Adda</title>

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
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        #header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
        }

        .nav-link {
            position: relative;
            padding: 0.75rem 1rem;
            color: #374151;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: #22c55e;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: linear-gradient(to right, #22c55e, #16a34a);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .btn-login {
            padding: 0.625rem 1.5rem;
            color: #22c55e;
            font-weight: 600;
            border: 2px solid #22c55e;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #22c55e;
            color: white;
        }

        .btn-signup {
            padding: 0.625rem 1.5rem;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            font-weight: 600;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
            transition: all 0.3s ease;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
        }

        .mobile-menu {
            display: none;
            animation: slideDown 0.3s ease;
        }

        .mobile-menu.active {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 via-emerald-50 to-teal-50 min-h-screen">

    <!-- Navigation Header -->
    <header id="header" class="fixed w-full top-0 z-50 transition-all duration-300">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="https://internshipadda.com"> <img 
                        src="../../icon.png" 
                        alt="InternshipAdda Logo"
                        class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
                    ></a>
                    
                </div>

                <div class="hidden lg:flex items-center justify-center flex-1 mx-8">
                    <div class="flex items-center space-x-2">
                        <a href="/public/about.html" class="nav-link">About</a>
                        <a href="/public/services.html" class="nav-link">Services</a>
                        <a href="/public/courses.php" class="nav-link">Courses</a>
                        <a href="/public/internships.php" class="nav-link">Internship</a>
                        <a href="/public/verify.php" class="nav-link">Verify Certificate</a>
                        <a href="/public/contact.html" class="nav-link">Contact</a>
                    </div>
                </div>

                <div class="hidden lg:flex items-center space-x-3">
                    <a href="/public/login.php" class="btn-login">Login</a>
                    <a href="/public/signup.php" class="btn-signup">Sign Up</a>
                </div>

                <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>

            <div id="mobile-menu" class="mobile-menu lg:hidden">
                <div class="pt-4 pb-3 space-y-1">
                    <a href="/public/about.html" class="nav-link block hover:bg-gray-50 rounded-lg">About</a>
                    <a href="/public/services.html" class="nav-link block hover:bg-gray-50 rounded-lg">Services</a>
                    <a href="/public/courses.php" class="nav-link block hover:bg-gray-50 rounded-lg">Courses</a>
                    <a href="/public/internships.php" class="nav-link block hover:bg-gray-50 rounded-lg">Internship</a>
                    <a href="/public/verify.php" class="nav-link block hover:bg-gray-50 rounded-lg">Verify Certificate</a>
                    <a href="/public/contact.html" class="nav-link block hover:bg-gray-50 rounded-lg">Contact</a>
                    <div class="pt-4 space-y-3">
                        <a href="/public/login.php" class="btn-login block text-center">Login</a>
                        <a href="/public/signup.php" class="btn-signup block text-center">Sign Up</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <div class="pt-24 md:pt-32 pb-16 px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-2">🎓 Verify Certificate</h1>
                <p class="text-lg text-gray-600">Enter certificate number to verify authenticity</p>
            </div>

            <!-- Search Form -->
            <div class="bg-white rounded-2xl border-2 border-primary-200 p-6 md:p-8 mb-6 shadow-xl">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Verify Certificate</h2>
                <form method="GET" class="flex flex-col md:flex-row gap-3">
                    <input 
                        type="text" 
                        name="code" 
                        value="<?php echo htmlspecialchars($verificationCode); ?>" 
                        placeholder="Enter Certificate Number (e.g., INT-CERT-2026-003 or IA-2026-7458)" 
                        class="flex-1 px-4 md:px-6 py-3 md:py-4 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-base md:text-lg"
                        required
                    >
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 md:px-8 py-3 md:py-4 rounded-xl font-bold text-base md:text-lg transition-colors whitespace-nowrap">
                        🔍 Verify
                    </button>
                </form>
            </div>

            <!-- Verification Result -->
            <?php if ($verificationResult): ?>
                <div class="bg-white rounded-2xl border-2 <?php echo $verificationResult['valid'] ? 'border-green-200' : 'border-red-200'; ?> p-6 md:p-8 shadow-xl">
                    <?php if ($verificationResult['valid']): ?>
                        <!-- Valid Certificate -->
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 bg-green-100 rounded-full mb-4">
                                <svg class="w-10 h-10 md:w-12 md:h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl md:text-3xl font-bold text-green-600 mb-2">✅ Valid Certificate</h3>
                            <p class="text-gray-600"><?php echo htmlspecialchars($verificationResult['message']); ?></p>
                        </div>

                        <?php $cert = $verificationResult['certificate']; ?>
                        
                        <div class="bg-gray-50 rounded-xl p-4 md:p-6 space-y-4">
                            <!-- ✅ Certificate Type Badge -->
                            <div class="text-center pb-4 border-b border-gray-200">
                                <?php if ($certificateType === 'internship'): ?>
                                    <span class="px-4 py-2 text-sm font-bold rounded-full bg-purple-100 text-purple-700">
                                        💼 Internship Certificate
                                    </span>
                                    <?php if (isset($cert['student_type'])): ?>
                                        <?php if ($cert['student_type'] === 'External'): ?>
                                            <span class="ml-2 px-3 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-700">
                                                🌐 External Student
                                            </span>
                                        <?php else: ?>
                                            <span class="ml-2 px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">
                                                🎓 Registered User
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="px-4 py-2 text-sm font-bold rounded-full bg-blue-100 text-blue-700">
                                        📚 Course Certificate
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">Certificate Number</span>
                                    <p class="text-base md:text-lg font-bold text-gray-900 font-mono break-all">
                                        <?php echo htmlspecialchars($cert['certificate_number'] ?? $cert['certificate_code']); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">Certificate Code</span>
                                    <p class="text-base md:text-lg font-bold text-primary-600 font-mono break-all">
                                        <?php echo htmlspecialchars($cert['certificate_code']); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 pt-4">
                                <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">Awarded To</span>
                                <p class="text-lg md:text-xl font-bold text-gray-900">
                                    <?php echo htmlspecialchars($cert['user_name'] ?? $cert['student_name']); ?>
                                </p>
                                <?php if (!empty($cert['user_email'])): ?>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <?php echo htmlspecialchars($cert['user_email']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">
                                    <?php echo $certificateType === 'course' ? 'Course Title' : 'Internship Title'; ?>
                                </span>
                                <p class="text-base md:text-lg font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($cert['course_title'] ?? $cert['internship_title'] ?? 'N/A'); ?>
                                </p>
                            </div>

                            <?php if ($certificateType === 'internship' && isset($cert['company_name'])): ?>
                                <div>
                                    <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">Company Name</span>
                                    <p class="text-base md:text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($cert['company_name']); ?>
                                    </p>
                                </div>
                                <?php if (!empty($cert['start_date']) && !empty($cert['end_date'])): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">Start Date</span>
                                            <p class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($cert['start_date'])); ?></p>
                                        </div>
                                        <div>
                                            <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">End Date</span>
                                            <p class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($cert['end_date'])); ?></p>
                                        </div>
                                        <div>
                                            <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">Duration</span>
                                            <p class="text-sm text-gray-900"><?php echo $cert['duration_months'] ?? 'N/A'; ?> months</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="border-t border-gray-200 pt-4">
                                <span class="text-xs md:text-sm font-semibold text-gray-500 uppercase">Issued On</span>
                                <p class="text-base md:text-lg text-gray-900">
                                    <?php echo date('F d, Y', strtotime($cert['issued_date'] ?? $cert['issued_at'] ?? 'now')); ?>
                                </p>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Invalid Certificate -->
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 bg-red-100 rounded-full mb-4">
                                <svg class="w-10 h-10 md:w-12 md:h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl md:text-3xl font-bold text-red-600 mb-2">❌ Invalid Certificate</h3>
                            <p class="text-base md:text-lg text-gray-700"><?php echo htmlspecialchars($verificationResult['message']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- How to Verify Section (same as before) -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 md:p-8 shadow-xl">
                    <div class="bg-blue-50 rounded-xl p-4 md:p-6 border border-blue-100 mb-6">
                        <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2 text-base md:text-lg">
                            <span class="text-xl">ℹ️</span>
                            How to Verify Your Certificate
                        </h3>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <div class="w-7 h-7 md:w-8 md:h-8 bg-primary-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0 text-sm">1</div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 text-sm md:text-base">Enter Certificate Number</h4>
                                    <p class="text-xs md:text-sm text-gray-600">Type your complete certificate number in the search box above</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-7 h-7 md:w-8 md:h-8 bg-primary-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0 text-sm">2</div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 text-sm md:text-base">Click Verify Button</h4>
                                    <p class="text-xs md:text-sm text-gray-600">Press the "Verify" button to search our database</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-7 h-7 md:w-8 md:h-8 bg-primary-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0 text-sm">3</div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 text-sm md:text-base">View Certificate Details</h4>
                                    <p class="text-xs md:text-sm text-gray-600">If valid, certificate details will be displayed (works for both courses & internships)</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-green-50 rounded-xl p-4 md:p-6 border border-green-100">
                        <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2 text-base md:text-lg">
                            <span class="text-xl">💡</span>
                            Need Help?
                        </h3>
                        <p class="text-xs md:text-sm text-gray-600 mb-3">If you can't find your certificate, contact us at:</p>
                        <div class="space-y-2 text-xs md:text-sm">
                            <p class="flex items-center gap-2">
                                <span class="text-base md:text-lg">📧</span>
                                <span class="font-semibold">Email:</span>
                                <a href="mailto:support@internshipadda.com" class="text-primary-600 hover:text-primary-700 font-semibold">support@internshipadda.com</a>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-6">
                <a href="/" class="text-gray-600 hover:text-primary-600 font-semibold text-sm md:text-base">
                    ← Back to Home
                </a>
            </div>
        </div>
    </div>

    <footer class="bg-gray-900 text-white py-8 md:py-12">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400 text-xs md:text-sm">
                © 2026 Internship Adda. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
        });
    </script>

</body>
</html>
