<?php
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

$token = $_GET['token'] ?? '';
$error = '';
$expired = false;

// Validate token
if ($token) {
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        $error = 'Invalid or expired reset link';
        $expired = true;
    }
} else {
    $error = 'No reset token provided';
    $expired = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Internship Adda</title>
    
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
    <!-- Logo -->
    <div class="text-center mb-8">
        <a href="/" class="inline-flex items-center gap-2 mb-4">
            <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-2xl">I</span>
            </div>
            <span class="text-2xl font-bold text-gray-900">Internship Adda</span>
        </a>
        <h1 class="text-3xl font-bold text-gray-900 mt-4">Reset Password</h1>
        <p class="text-gray-600 mt-2">Enter your new password below</p>
    </div>

    <!-- Reset Password Form -->
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <!-- Alert Container -->
        <div id="alertContainer" class="mb-6"></div>

        <?php if ($expired): ?>
            <!-- Expired/Invalid Token -->
            <div class="bg-red-50 border-2 border-red-200 rounded-xl p-6 text-center">
                <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-bold text-red-900 mb-2">Link Expired or Invalid</h3>
                <p class="text-red-700 mb-4"><?php echo htmlspecialchars($error); ?></p>
                <a href="/public/forgot-password.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold">
                    Request New Link
                </a>
            </div>
        <?php else: ?>
            <!-- Reset Password Form -->
            <form id="resetPasswordForm" class="space-y-6">
                
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                    <input 
                        type="password" 
                        id="password"
                        name="password" 
                        required
                        minlength="8"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                        placeholder="Enter new password"
                    >
                    <p class="text-sm text-gray-500 mt-1">Minimum 8 characters</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password"
                        name="confirm_password" 
                        required
                        minlength="8"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 transition-colors"
                        placeholder="Confirm new password"
                    >
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                    Reset Password
                </button>

            </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="/public/login.php" class="text-primary-600 hover:text-primary-700 font-semibold inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Login
            </a>
        </div>
    </div>
</div>

<script>
document.getElementById('resetPasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password !== confirmPassword) {
        showAlert('error', 'Passwords do not match');
        return;
    }
    
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Resetting...';
    
    const data = {
        token: formData.get('token'),
        password: password
    };
    
    try {
        const response = await fetch('/api/password-reset.php?action=reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', '✅ Password reset successful! Redirecting to login...');
            setTimeout(() => {
                window.location.href = '/public/login.php?success=Password reset successful. Please login with your new password.';
            }, 2000);
        } else {
            showAlert('error', result.message || 'Failed to reset password');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        showAlert('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    const bgColor = type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800';
    
    container.innerHTML = `
        <div class="${bgColor} border-2 rounded-lg p-4">
            <p class="font-semibold">${message}</p>
        </div>
    `;
}
</script>

</body>
</html>
