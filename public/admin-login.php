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
    if ($auth->isAdmin()) {
        header('Location: /app/views/admin/dashboard.php');
    } else {
        header('Location: /public/login.php?error=Access denied. Admins only.');
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
    <title>Admin Login - Internship Adda</title>
    
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
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        h1, h2, h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
        <a href="/" class="inline-flex items-center gap-2 mb-4">
            <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-2xl">I</span>
            </div>
            <span class="text-2xl font-bold text-white">Internship Adda</span>
        </a>
        <div class="mt-4 inline-block bg-red-500 text-white px-4 py-2 rounded-full text-sm font-bold">
            🔒 ADMIN ACCESS ONLY
        </div>
        <h1 class="text-3xl font-bold text-white mt-4">Admin Portal</h1>
        <p class="text-gray-300 mt-2">Secure login for administrators</p>
    </div>

    <!-- Login Form -->
    <div class="bg-white rounded-2xl shadow-2xl p-8">
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

        <form id="adminLoginForm" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Admin Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                    placeholder=""
                    required
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Admin Password</label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                        placeholder="Enter admin password"
                        required
                    >
                    <button 
                        type="button" 
                        onclick="togglePassword()"
                        class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 hover:text-gray-800"
                    >
                        <svg id="password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <button 
                type="submit" 
                id="loginBtn"
                class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2"
            >
                <span id="loginBtnText">Login as Admin</span>
                <svg id="loginSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600 text-sm">Not an admin? 
                <a href="/public/login.php" class="text-primary-600 hover:text-primary-700 font-semibold">Login as Learner</a>
            </p>
        </div>

        <div class="mt-4 text-center">
            <a href="/" class="text-gray-500 hover:text-gray-700 text-sm">← Back to Home</a>
        </div>
    </div>

    <!-- Security Notice -->
    <div class="mt-6 bg-yellow-500/20 border border-yellow-500 rounded-lg p-4 text-center">
        <p class="text-yellow-200 text-sm font-semibold">⚠️ Unauthorized access attempts are logged and monitored</p>
    </div>
</div>

<script>
    function togglePassword() {
        const field = document.getElementById('password');
        const eyeIcon = document.getElementById('password-eye');
        
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

    document.getElementById('adminLoginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const loginBtn = document.getElementById('loginBtn');
        const loginBtnText = document.getElementById('loginBtnText');
        const loginSpinner = document.getElementById('loginSpinner');
        
        // Show loading state
        loginBtn.disabled = true;
        loginBtnText.textContent = 'Logging in...';
        loginSpinner.classList.remove('hidden');
        
        const formData = new FormData(e.target);
        const data = {
            email: formData.get('email'),
            password: formData.get('password')
        };

        try {
            // Use the same login endpoint but check for admin role
            const response = await fetch('/api/auth.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                // Check if user is admin
                if (result.data.role === 'admin') {
                    window.location.href = '/app/views/admin/dashboard.php';
                } else {
                    // Not an admin
                    loginBtn.disabled = false;
                    loginBtnText.textContent = 'Login as Admin';
                    loginSpinner.classList.add('hidden');
                    
                    alert('❌ Access Denied: Admin credentials required. You are logged in as a learner.');
                }
            } else {
                // Login failed
                loginBtn.disabled = false;
                loginBtnText.textContent = 'Login as Admin';
                loginSpinner.classList.add('hidden');
                
                alert(result.error || '❌ Login failed. Please check your admin credentials.');
            }
        } catch (error) {
            console.error('Error:', error);
            
            loginBtn.disabled = false;
            loginBtnText.textContent = 'Login as Admin';
            loginSpinner.classList.add('hidden');
            
            alert('❌ Network error. Please try again.');
        }
    });
</script>

</body>
</html>
