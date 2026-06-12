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

$auth = new Auth($db);

// Redirect if already logged in
if ($auth->check()) {
    // ✅ Check for redirect URL first
    $redirectUrl = $_GET['redirect'] ?? '';
    
    if (!empty($redirectUrl)) {
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    // Default redirect based on role
    if ($auth->isAdmin()) {
        header('Location: /app/views/admin/dashboard.php');
    } else {
        header('Location: /app/views/learner/dashboard.php');
    }
    exit;
}

// Capture referral code from URL
$referralCode = $_GET['ref'] ?? '';
$referralDiscount = 0;
$referrerExists = false;
$referrerName = '';

if ($referralCode) {
    $stmt = $db->prepare("SELECT id, name FROM users WHERE referral_code = ? AND status = 'active' AND role = 'learner'");
    $stmt->execute([$referralCode]);
    $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($referrer) {
        $referrerExists = true;
        $referrerName = $referrer['name'];
        
        $discountStmt = $db->prepare("SELECT setting_value FROM referral_settings WHERE setting_key = 'signup_discount_percentage'");
        $discountStmt->execute();
        $referralDiscount = (int)($discountStmt->fetchColumn() ?: 40);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Internship Adda</title>
    
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
        h1, h2, h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-primary-50 to-blue-50 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
      <a href="https://internshipadda.com" class="inline-flex flex-col items-center gap-3 mb-4">
        
        <img 
            src="../../icon.png" 
            alt="InternshipAdda Logo"
            class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
        >

      
    </a>
        <h1 class="text-3xl font-bold text-gray-900 mt-4">Create Account</h1>
        <p class="text-gray-600 mt-2">Start your learning journey today</p>
        
        <?php if ($referrerExists): ?>
        <div class="mt-4 inline-flex flex-col items-center gap-2 bg-gradient-to-r from-yellow-100 to-orange-100 border-2 border-yellow-400 rounded-xl px-6 py-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                </svg>
                <span class="font-bold text-yellow-800">🎉 Get <?php echo $referralDiscount; ?>% OFF!</span>
            </div>
            <p class="text-xs text-yellow-700">Referred by <span class="font-bold"><?php echo htmlspecialchars($referrerName); ?></span></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-8">
        <form id="registerForm" class="space-y-6">
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                    placeholder="John Doe"
                    required
                >
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                    placeholder="you@example.com"
                    required
                >
            </div>

            <div>
                <label for="mobile" class="block text-sm font-semibold text-gray-700 mb-2">Mobile Number</label>
                <input 
                    type="tel" 
                    id="mobile" 
                    name="mobile" 
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                    placeholder="10-digit mobile number"
                    pattern="[0-9]{10}"
                    maxlength="10"
                    required
                >
                <p class="text-xs text-gray-500 mt-1">Enter 10-digit mobile number without +91</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                        placeholder="Minimum 6 characters"
                        required
                        minlength="6"
                    >
                    <button 
                        type="button" 
                        onclick="togglePassword('password')"
                        class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 hover:text-gray-800"
                    >
                        <svg id="password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                        placeholder="Re-enter password"
                        required
                    >
                    <button 
                        type="button" 
                        onclick="togglePassword('confirm_password')"
                        class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 hover:text-gray-800"
                    >
                        <svg id="confirm_password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                Continue with Email Verification
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600">Already have an account? 
                <a href="/public/login.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" class="text-primary-600 hover:text-primary-700 font-semibold">Login here</a>
            </p>
        </div>

        <div class="mt-4 text-center">
            <a href="/" class="text-gray-500 hover:text-gray-700 text-sm">← Back to Home</a>
        </div>
    </div>
</div>

<script>
    const REFERRAL_CODE = <?php echo json_encode($referralCode); ?>;
    const HAS_REFERRAL = <?php echo $referrerExists ? 'true' : 'false'; ?>;
    
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const eyeIcon = document.getElementById(fieldId + '-eye');
        
        if (field.type === 'password') {
            field.type = 'text';
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
            `;
        } else {
            field.type = 'password';
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            `;
        }
    }
    
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending OTP to email...';
        
        const formData = new FormData(e.target);
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        const mobile = formData.get('mobile');
        const email = formData.get('email');
        const name = formData.get('name');

        if (password !== confirmPassword) {
            alert('❌ Passwords do not match!');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }

        if (!/^[0-9]{10}$/.test(mobile)) {
            alert('❌ Please enter a valid 10-digit mobile number!');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }

        try {
            // Step 1: Store registration data
            const storeResponse = await fetch('/api/auth.php?action=store-registration', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: name,
                    email: email,
                    mobile: mobile,
                    password: password,
                    referral_code: REFERRAL_CODE || null
                })
            });

            const storeResult = await storeResponse.json();

            if (!storeResult.success) {
                throw new Error(storeResult.error || 'Failed to store registration data');
            }

            // Step 2: Send OTP
            const otpResponse = await fetch('/api/verify-email.php?action=send-otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, name: name })
            });

            const otpResult = await otpResponse.json();

            if (otpResult.success) {
                // ✅ PRESERVE REDIRECT URL when going to verify-otp page
                const urlParams = new URLSearchParams(window.location.search);
                const redirectUrl = urlParams.get('redirect');
                
                let verifyUrl = '/public/verify-otp.php';
                if (redirectUrl) {
                    verifyUrl += '?redirect=' + encodeURIComponent(redirectUrl);
                }
                
                window.location.href = verifyUrl;
            } else {
                throw new Error(otpResult.error || 'Failed to send OTP');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ ' + error.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
</script>

</body>
</html>
