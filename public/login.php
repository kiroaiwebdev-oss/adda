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

$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Internship Adda</title>
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
        }
        h1, h2, h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        
        /* Custom Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-primary-50 to-blue-50 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">
   <!-- Logo -->
<div class="text-center mb-8">

    <a href="https://internshipadda.com" class="inline-flex flex-col items-center gap-3 mb-4">
        
        <img 
            src="../../icon.png" 
            alt="InternshipAdda Logo"
            class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
        >

       

    </a>

    <h1 class="text-3xl font-bold text-gray-900 mt-4">
        Welcome Back!
    </h1>

    <p class="text-gray-600 mt-2">
        Login to continue your learning journey
    </p>

</div>


    <!-- Login Form -->
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-800 font-semibold"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 border-2 border-green-200 rounded-lg p-4 mb-6">
                <p class="text-green-800 font-semibold"><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <form id="loginForm" class="space-y-6">
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
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                        placeholder="Enter your password"
                        required
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

            <!-- Forgot Password Link -->
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 text-primary-600 rounded">
                    <span class="text-sm text-gray-600">Remember me</span>
                </label>
                <a href="/public/forgot-password.php" class="text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    Forgot Password?
                </a>
            </div>

            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                Login
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600">Don't have an account? 
                <a href="/public/signup.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" class="text-primary-600 hover:text-primary-700 font-semibold">Register here</a>
            </p>
        </div>

        <div class="mt-4 text-center">
            <a href="/" class="text-gray-500 hover:text-gray-700 text-sm">← Back to Home</a>
        </div>
    </div>
</div>

<!-- Custom Modal for Admin Block -->
<div id="adminBlockModal" class="modal-overlay">
    <div class="modal-content">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-3">🔒 Admin Access Blocked</h2>
        <p class="text-gray-600 mb-6">This page is for learners only. Please use <strong>/public/admin-login.php</strong> to access the admin panel.</p>
        <button onclick="closeAdminModal()" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
            Got it
        </button>
    </div>
</div>

<script>
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

    function closeAdminModal() {
        // Step 1: Logout the admin session
        fetch('/api/auth.php?action=logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        }).then(() => {
            // Step 2: Close modal
            document.getElementById('adminBlockModal').classList.remove('active');
            
            // Step 3: Clear form fields
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
            
            // Step 4: Reset password field type to password
            document.getElementById('password').type = 'password';
            
            // Step 5: Focus on email field for fresh login
            document.getElementById('email').focus();
            
        }).catch(() => {
            // Even if logout fails, still clear form
            document.getElementById('adminBlockModal').classList.remove('active');
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password').type = 'password';
            document.getElementById('email').focus();
        });
    }

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            email: formData.get('email'),
            password: formData.get('password')
        };

        try {
            const response = await fetch('/api/auth.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                // 🔒 BLOCK ADMIN LOGIN - Show Custom Modal
                if (result.data.role === 'admin') {
                    // Show custom modal
                    document.getElementById('adminBlockModal').classList.add('active');
                } else {
                    // ✅ LEARNER LOGIN - Check for redirect URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const redirectUrl = urlParams.get('redirect');
                    
                    if (redirectUrl) {
                        console.log('🚀 Redirecting to saved URL:', redirectUrl);
                        window.location.href = redirectUrl;
                    } else {
                        console.log('🚀 No redirect parameter, going to dashboard');
                        window.location.href = '/app/views/learner/dashboard.php';
                    }
                }
            } else {
                alert(result.error || 'Login failed. Please check your credentials.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        }
    });
</script>

</body>
</html>
