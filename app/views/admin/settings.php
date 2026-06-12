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
$auth->requireAdmin();

// 🔥 FIXED: Get settings properly
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// 🔥 FIXED: Checkbox helper function
function isChecked($settings, $key, $default = 'false') {
    $value = $settings[$key] ?? $default;
    return ($value === 'true' || $value === '1' || $value === 1 || $value === true);
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Internship Adda Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; }
        .tab-button { position: relative; transition: all 0.3s ease; }
        .tab-button.active { color: #16a34a; font-weight: 600; }
        .tab-button.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; background: #16a34a; border-radius: 3px 3px 0 0; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .setting-card { background: white; border: 2px solid #e5e7eb; border-radius: 16px; overflow: hidden; transition: all 0.3s ease; }
        .setting-card:hover { border-color: #22c55e; box-shadow: 0 4px 20px rgba(34, 197, 94, 0.1); }
    </style>
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
</head>
<body class="bg-gray-50">

<?php include __DIR__ . '/../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <h1 class="text-3xl font-bold text-gray-900">⚙️ Platform Settings</h1>
            <p class="text-gray-600 mt-1">Configure your LMS platform</p>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-6xl mx-auto">
            <?php if ($success): ?>
                <div class="bg-green-50 border-2 border-green-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    <p class="text-green-800 font-semibold"><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border-2 border-red-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    <p class="text-red-800 font-semibold"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="bg-white rounded-xl border border-gray-200 mb-6 overflow-hidden">
                <div class="flex overflow-x-auto">
                    <button onclick="switchTab('general')" class="tab-button active flex-1 px-6 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <i class="fas fa-cog mr-2"></i>General
                    </button>
                    <button onclick="switchTab('auth')" class="tab-button flex-1 px-6 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <i class="fas fa-user-shield mr-2"></i>Authentication
                    </button>
                    <button onclick="switchTab('payment')" class="tab-button flex-1 px-6 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <i class="fas fa-credit-card mr-2"></i>Payment
                    </button>
                    <button onclick="switchTab('email')" class="tab-button flex-1 px-6 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <i class="fas fa-envelope mr-2"></i>Email
                    </button>
                    <button onclick="switchTab('course')" class="tab-button flex-1 px-6 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <i class="fas fa-graduation-cap mr-2"></i>Course
                    </button>
                    <button onclick="switchTab('live')" class="tab-button flex-1 px-6 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <i class="fas fa-video mr-2"></i>Live Class
                    </button>
                </div>
            </div>

            <form id="settingsForm" class="space-y-6">
                
                <!-- 1. GENERAL SETTINGS TAB -->
                <div id="general-tab" class="tab-content active">
                    <div class="setting-card">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-primary-50 to-green-50">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-building text-primary-600"></i>
                                Site Identity
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Basic information about your platform</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-tag text-gray-400 mr-2"></i>Platform Name *
                                    </label>
                                    <input 
                                        type="text" 
                                        name="platform_name"
                                        value="<?php echo htmlspecialchars($settings['platform_name'] ?? 'Internship Adda'); ?>"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                        placeholder="Internship Adda"
                                        required
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-heading text-gray-400 mr-2"></i>Site Tagline
                                    </label>
                                    <input 
                                        type="text" 
                                        name="site_tagline"
                                        value="<?php echo htmlspecialchars($settings['site_tagline'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                        placeholder="Learn, Grow, Succeed"
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-image text-gray-400 mr-2"></i>Header Logo URL
                                </label>
                                <input 
                                    type="url" 
                                    name="header_logo"
                                    value="<?php echo htmlspecialchars($settings['header_logo'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    placeholder="https://example.com/logo.png"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-image text-gray-400 mr-2"></i>Footer Logo URL
                                </label>
                                <input 
                                    type="url" 
                                    name="footer_logo"
                                    value="<?php echo htmlspecialchars($settings['footer_logo'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    placeholder="https://example.com/footer-logo.png"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-star text-gray-400 mr-2"></i>Favicon URL
                                </label>
                                <input 
                                    type="url" 
                                    name="favicon"
                                    value="<?php echo htmlspecialchars($settings['favicon'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    placeholder="https://example.com/favicon.ico"
                                >
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-envelope text-gray-400 mr-2"></i>Admin Email
                                    </label>
                                    <input 
                                        type="email" 
                                        name="admin_email"
                                        value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                        placeholder="admin@internshipadda.com"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-phone text-gray-400 mr-2"></i>Contact Phone
                                    </label>
                                    <input 
                                        type="tel" 
                                        name="contact_phone"
                                        value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                        placeholder="+91 98765 43210"
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i>Address
                                </label>
                                <textarea 
                                    name="contact_address"
                                    rows="3"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    placeholder="123 Business Street, City, State - 123456"
                                ><?php echo htmlspecialchars($settings['contact_address'] ?? ''); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-copyright text-gray-400 mr-2"></i>Copyright Text
                                </label>
                                <input 
                                    type="text" 
                                    name="copyright_text"
                                    value="<?php echo htmlspecialchars($settings['copyright_text'] ?? '© 2026 Internship Adda. All rights reserved.'); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    placeholder="© 2026 Internship Adda. All rights reserved."
                                >
                            </div>

                            <div class="border-t border-gray-200 pt-6">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="maintenance_mode"
                                        value="1"
                                        <?php echo isChecked($settings, 'maintenance_mode') ? 'checked' : ''; ?>
                                        class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                    >
                                    <div>
                                        <span class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                            <i class="fas fa-tools text-orange-500"></i>
                                            Maintenance Mode
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1">Enable to show maintenance page to all users (except admins)</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. AUTHENTICATION SETTINGS TAB -->
                <div id="auth-tab" class="tab-content">
                    <div class="setting-card">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-user-lock text-blue-600"></i>
                                User Authentication & Registration
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Control user signup and login methods</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="flex items-start gap-3">
                                <input 
                                    type="checkbox" 
                                    name="enable_registration"
                                    value="1"
                                    <?php echo isChecked($settings, 'enable_registration', 'true') ? 'checked' : ''; ?>
                                    class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                >
                                <div>
                                    <span class="text-sm font-semibold text-gray-700">Enable User Registration</span>
                                    <p class="text-xs text-gray-500 mt-1">Allow new users to create accounts</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <input 
                                    type="checkbox" 
                                    name="email_verification"
                                    value="1"
                                    <?php echo isChecked($settings, 'email_verification', 'true') ? 'checked' : ''; ?>
                                    class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                >
                                <div>
                                    <span class="text-sm font-semibold text-gray-700">Require Email Verification</span>
                                    <p class="text-xs text-gray-500 mt-1">Users must verify email before accessing courses</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user-tag text-gray-400 mr-2"></i>Default User Role
                                </label>
                                <select 
                                    name="default_role"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                >
                                    <option value="student" <?php echo ($settings['default_role'] ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="instructor" <?php echo ($settings['default_role'] ?? 'student') === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                </select>
                            </div>

                            <div class="border-t border-gray-200 pt-6 mt-6">
                                <h4 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fab fa-google text-red-500"></i>
                                    Google OAuth Login
                                </h4>
                                
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="google_login_enabled"
                                            value="1"
                                            <?php echo isChecked($settings, 'google_login_enabled') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Enable Google Login</span>
                                            <p class="text-xs text-gray-500 mt-1">Allow users to login with Google</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Google Client ID</label>
                                        <input 
                                            type="text" 
                                            name="google_client_id"
                                            value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="123456789-abcdefg.apps.googleusercontent.com"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Google Client Secret</label>
                                        <input 
                                            type="password" 
                                            name="google_client_secret"
                                            value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="••••••••••••••••"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fab fa-facebook text-blue-600"></i>
                                    Facebook OAuth Login
                                </h4>
                                
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="facebook_login_enabled"
                                            value="1"
                                            <?php echo isChecked($settings, 'facebook_login_enabled') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Enable Facebook Login</span>
                                            <p class="text-xs text-gray-500 mt-1">Allow users to login with Facebook</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Facebook App ID</label>
                                        <input 
                                            type="text" 
                                            name="facebook_app_id"
                                            value="<?php echo htmlspecialchars($settings['facebook_app_id'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="1234567890123456"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Facebook App Secret</label>
                                        <input 
                                            type="password" 
                                            name="facebook_app_secret"
                                            value="<?php echo htmlspecialchars($settings['facebook_app_secret'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="••••••••••••••••"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. PAYMENT SETTINGS TAB -->
                <div id="payment-tab" class="tab-content">
                    <div class="setting-card">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-rupee-sign text-green-600"></i>
                                Payment Gateway & Currency
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Configure payment methods and currency</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-coins text-gray-400 mr-2"></i>Currency
                                    </label>
                                    <select 
                                        name="currency"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    >
                                        <option value="INR" <?php echo ($settings['currency'] ?? 'INR') === 'INR' ? 'selected' : ''; ?>>INR - Indian Rupee</option>
                                        <option value="USD" <?php echo ($settings['currency'] ?? 'INR') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                        <option value="EUR" <?php echo ($settings['currency'] ?? 'INR') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                        <option value="GBP" <?php echo ($settings['currency'] ?? 'INR') === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-align-left text-gray-400 mr-2"></i>Currency Symbol Position
                                    </label>
                                    <select 
                                        name="currency_position"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    >
                                        <option value="left" <?php echo ($settings['currency_position'] ?? 'left') === 'left' ? 'selected' : ''; ?>>Left (₹500)</option>
                                        <option value="right" <?php echo ($settings['currency_position'] ?? 'left') === 'right' ? 'selected' : ''; ?>>Right (500₹)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 pt-6 mt-6">
                                <h4 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fas fa-credit-card text-blue-600"></i>
                                    Razorpay Payment Gateway
                                </h4>
                                
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="razorpay_enabled"
                                            value="1"
                                            <?php echo isChecked($settings, 'razorpay_enabled', 'true') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Enable Razorpay</span>
                                            <p class="text-xs text-gray-500 mt-1">Accept payments via Razorpay</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Razorpay Key ID</label>
                                        <input 
                                            type="text" 
                                            name="razorpay_key_id"
                                            value="<?php echo htmlspecialchars($settings['razorpay_key_id'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="rzp_test_xxxxxxxxxxxxx"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Razorpay Key Secret</label>
                                        <input 
                                            type="password" 
                                            name="razorpay_key_secret"
                                            value="<?php echo htmlspecialchars($settings['razorpay_key_secret'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="••••••••••••••••"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Mode</label>
                                        <select 
                                            name="razorpay_mode"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                        >
                                            <option value="test" <?php echo ($settings['razorpay_mode'] ?? 'test') === 'test' ? 'selected' : ''; ?>>Test Mode (Sandbox)</option>
                                            <option value="live" <?php echo ($settings['razorpay_mode'] ?? 'test') === 'live' ? 'selected' : ''; ?>>Live Mode (Production)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fas fa-file-invoice text-purple-600"></i>
                                    Invoice Details
                                </h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Business Name</label>
                                        <input 
                                            type="text" 
                                            name="invoice_business_name"
                                            value="<?php echo htmlspecialchars($settings['invoice_business_name'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                            placeholder="Internship Adda Pvt Ltd"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">GST Number (Optional)</label>
                                        <input 
                                            type="text" 
                                            name="invoice_gst_number"
                                            value="<?php echo htmlspecialchars($settings['invoice_gst_number'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                            placeholder="22AAAAA0000A1Z5"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 4. EMAIL SETTINGS TAB -->
                <div id="email-tab" class="tab-content">
                    <div class="setting-card">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-red-50 to-pink-50">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-paper-plane text-red-600"></i>
                                Email & SMTP Configuration
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Configure email sending for notifications</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-server text-gray-400 mr-2"></i>Mail Driver
                                </label>
                                <select 
                                    name="mail_driver"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                >
                                    <option value="smtp" <?php echo ($settings['mail_driver'] ?? 'smtp') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                                    <option value="mailgun" <?php echo ($settings['mail_driver'] ?? 'smtp') === 'mailgun' ? 'selected' : ''; ?>>Mailgun</option>
                                    <option value="ses" <?php echo ($settings['mail_driver'] ?? 'smtp') === 'ses' ? 'selected' : ''; ?>>Amazon SES</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Host</label>
                                    <input 
                                        type="text" 
                                        name="smtp_host"
                                        value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                        placeholder="smtp.gmail.com"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Port</label>
                                    <input 
                                        type="number" 
                                        name="smtp_port"
                                        value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                        placeholder="587"
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Username</label>
                                <input 
                                    type="text" 
                                    name="smtp_username"
                                    value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                    placeholder="your-email@gmail.com"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Password</label>
                                <input 
                                    type="password" 
                                    name="smtp_password"
                                    value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                    placeholder="••••••••••••••••"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Encryption</label>
                                <select 
                                    name="smtp_encryption"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                >
                                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>

                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-base font-bold text-gray-900 mb-4">From Details</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Name</label>
                                        <input 
                                            type="text" 
                                            name="mail_from_name"
                                            value="<?php echo htmlspecialchars($settings['mail_from_name'] ?? 'Internship Adda'); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                            placeholder="Internship Adda"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Email</label>
                                        <input 
                                            type="email" 
                                            name="mail_from_email"
                                            value="<?php echo htmlspecialchars($settings['mail_from_email'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                            placeholder="noreply@internshipadda.com"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. COURSE SETTINGS TAB -->
                <div id="course-tab" class="tab-content">
                    <div class="setting-card">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-book-open text-purple-600"></i>
                                Course Player & Settings
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Configure how courses behave</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-video text-gray-400 mr-2"></i>Video Storage Provider
                                </label>
                                <select 
                                    name="video_storage"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                >
                                    <option value="local" <?php echo ($settings['video_storage'] ?? 'local') === 'local' ? 'selected' : ''; ?>>Local Server</option>
                                    <option value="youtube" <?php echo ($settings['video_storage'] ?? 'local') === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                                    <option value="vimeo" <?php echo ($settings['video_storage'] ?? 'local') === 'vimeo' ? 'selected' : ''; ?>>Vimeo</option>
                                    <option value="aws" <?php echo ($settings['video_storage'] ?? 'local') === 'aws' ? 'selected' : ''; ?>>AWS S3</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">AWS S3 Access Key (if selected)</label>
                                <input 
                                    type="text" 
                                    name="aws_access_key"
                                    value="<?php echo htmlspecialchars($settings['aws_access_key'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                    placeholder="AKIAIOSFODNN7EXAMPLE"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">AWS S3 Secret Key</label>
                                <input 
                                    type="password" 
                                    name="aws_secret_key"
                                    value="<?php echo htmlspecialchars($settings['aws_secret_key'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                    placeholder="••••••••••••••••"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Vimeo API Token (if selected)</label>
                                <input 
                                    type="text" 
                                    name="vimeo_api_token"
                                    value="<?php echo htmlspecialchars($settings['vimeo_api_token'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                    placeholder="1234567890abcdef1234567890abcdef"
                                >
                            </div>

                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-base font-bold text-gray-900 mb-4">Course Features</h4>
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="enable_comments"
                                            value="1"
                                            <?php echo isChecked($settings, 'enable_comments', 'true') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Enable Comments</span>
                                            <p class="text-xs text-gray-500 mt-1">Allow students to comment on course content</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="enable_reviews"
                                            value="1"
                                            <?php echo isChecked($settings, 'enable_reviews', 'true') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Enable Course Reviews</span>
                                            <p class="text-xs text-gray-500 mt-1">Allow students to rate and review courses</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="auto_generate_certificate"
                                            value="1"
                                            <?php echo isChecked($settings, 'auto_generate_certificate', 'true') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Auto-Generate Certificate</span>
                                            <p class="text-xs text-gray-500 mt-1">Automatically create certificate when student completes course</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. LIVE CLASS SETTINGS TAB -->
                <div id="live-tab" class="tab-content">
                    <div class="setting-card">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-blue-50">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-broadcast-tower text-indigo-600"></i>
                                Live Class Integration
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Configure live class platforms</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="border-b border-gray-200 pb-6">
                                <h4 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fab fa-zoom text-blue-600"></i>
                                    Zoom Integration
                                </h4>
                                
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="zoom_enabled"
                                            value="1"
                                            <?php echo isChecked($settings, 'zoom_enabled') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Enable Zoom</span>
                                            <p class="text-xs text-gray-500 mt-1">Integrate Zoom for live classes</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Zoom API Key</label>
                                        <input 
                                            type="text" 
                                            name="zoom_api_key"
                                            value="<?php echo htmlspecialchars($settings['zoom_api_key'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="abc123def456ghi789"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Zoom API Secret</label>
                                        <input 
                                            type="password" 
                                            name="zoom_api_secret"
                                            value="<?php echo htmlspecialchars($settings['zoom_api_secret'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="••••••••••••••••"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fab fa-google text-red-600"></i>
                                    Google Meet Integration
                                </h4>
                                
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="checkbox" 
                                            name="google_meet_enabled"
                                            value="1"
                                            <?php echo isChecked($settings, 'google_meet_enabled') ? 'checked' : ''; ?>
                                            class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 mt-1"
                                        >
                                        <div>
                                            <span class="text-sm font-semibold text-gray-700">Enable Google Meet</span>
                                            <p class="text-xs text-gray-500 mt-1">Integrate Google Meet for live classes</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Google Meet API Key</label>
                                        <input 
                                            type="text" 
                                            name="google_meet_api_key"
                                            value="<?php echo htmlspecialchars($settings['google_meet_api_key'] ?? ''); ?>"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 font-mono text-sm"
                                            placeholder="AIzaSyB1234567890abcdefg"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button - Visible on All Tabs -->
                <div class="flex justify-end gap-4 sticky bottom-0 bg-gray-50 py-4 border-t border-gray-200">
                    <button 
                        type="button"
                        onclick="window.location.href='/app/views/admin/dashboard.php'"
                        class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-100 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-8 py-3 bg-gradient-to-r from-primary-600 to-green-600 hover:from-primary-700 hover:to-green-700 text-white rounded-lg font-semibold transition-all shadow-lg hover:shadow-xl flex items-center gap-2"
                    >
                        <i class="fas fa-save"></i>
                        Save All Settings
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.closest('.tab-button').classList.add('active');
}

document.getElementById('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    submitBtn.disabled = true;
    
    const formData = new FormData(e.target);
    const data = {};
    
    const checkboxFields = [
        'maintenance_mode', 'enable_registration', 'email_verification',
        'google_login_enabled', 'facebook_login_enabled', 'razorpay_enabled',
        'enable_comments', 'enable_reviews', 'auto_generate_certificate',
        'zoom_enabled', 'google_meet_enabled'
    ];
    
    // 🔥 FIXED: Process form data properly
    for (let [key, value] of formData.entries()) {
        if (checkboxFields.includes(key)) {
            data[key] = 'true';
        } else {
            // 🔥 Keep empty strings empty - don't convert to 'false'
            const trimmedValue = value.trim();
            data[key] = trimmedValue === '' ? '' : trimmedValue;
        }
    }
    
    // 🔥 Only checkboxes get 'false' when unchecked
    checkboxFields.forEach(field => {
        if (!data.hasOwnProperty(field)) {
            data[field] = 'false';
        }
    });
    
    console.log('💾 Saving settings:', Object.keys(data).length, 'items');
    
    try {
        const response = await fetch('/api/settings.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('✅ Response:', result);
        
        if (result.success) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-2xl z-50 flex items-center gap-3';
            notification.innerHTML = '<i class="fas fa-check-circle text-2xl"></i><span class="font-semibold">Settings Saved!</span>';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                window.location.href = window.location.pathname + '?success=Settings saved successfully';
            }, 1000);
        } else {
            alert('Error: ' + result.error);
            submitBtn.innerHTML = originalHTML;
            submitBtn.disabled = false;
        }
    } catch (error) {
        console.error('❌ Error:', error);
        alert('Network error: ' + error.message);
        submitBtn.innerHTML = originalHTML;
        submitBtn.disabled = false;
    }
});

window.addEventListener('DOMContentLoaded', () => {
    console.log('✅ Settings page loaded');
});
</script>


</body>
</html>
