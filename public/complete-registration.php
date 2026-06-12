<?php
session_start();

// Check if email is verified and registration data exists
if (!isset($_SESSION['verified_email']) || !isset($_SESSION['pending_registration'])) {
    header('Location: /public/signup.php');
    exit;
}

$registrationData = $_SESSION['pending_registration'];
$verifiedEmail = $_SESSION['verified_email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completing Registration - Internship Adda</title>
    
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
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .animate-bounce {
            animation: bounce 1s infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-primary-50 to-blue-50 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <div id="statusCard" class="bg-white rounded-2xl shadow-xl p-8 text-center">
        <!-- Loading State -->
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center">
                <svg class="w-10 h-10 text-primary-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </div>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Completing Registration...</h1>
        <p class="text-gray-600 mb-6">Please wait while we set up your account</p>
        
        <div class="flex justify-center">
            <div class="flex gap-2">
                <div class="w-3 h-3 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0s"></div>
                <div class="w-3 h-3 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="w-3 h-3 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // ✅ FIXED: URL param se bhi redirect lo (fallback)
    const urlParams = new URLSearchParams(window.location.search);
    const urlRedirect = urlParams.get('redirect');

    (async function completeRegistration() {
        try {
            console.log('Starting registration completion...');
            
            const response = await fetch('/api/auth.php?action=register', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            console.log('Response status:', response.status);

            const responseText = await response.text();
            console.log('Raw response:', responseText);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                throw new Error('Invalid server response. Please try again.');
            }

            console.log('Parsed result:', result);

            if (result.success) {
                // ✅ FIXED: result.redirect_url use karo (auth.php session se deta hai)
                // Fallback: URL param → default dashboard
                const redirectTo = result.redirect_url 
                    || urlRedirect 
                    || '/app/views/learner/dashboard.php';

                console.log('✅ Redirecting to:', redirectTo);

                document.getElementById('statusCard').innerHTML = `
                    <div class="text-center">
                        <div class="flex justify-center mb-6">
                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">🎉 Registration Complete!</h1>
                        <p class="text-gray-600 mb-6">Your account has been successfully created</p>
                        <div class="bg-primary-50 border-2 border-primary-200 rounded-lg p-4 mb-4">
                            <p class="text-sm text-primary-800">
                                <strong>Welcome ${result.name}!</strong><br>
                                <span class="text-xs">Referral Code: <span class="font-mono font-bold">${result.referral_code}</span></span>
                            </p>
                        </div>
                        ${result.has_referral_bonus ? '<p class="text-green-600 text-sm mb-4">✅ Referral bonus applied!</p>' : ''}
                        <p class="text-gray-600 text-sm">Redirecting you back...</p>
                    </div>
                `;

                // ✅ FIXED: Dynamic redirect — internship page ya dashboard
                setTimeout(() => {
                    window.location.href = redirectTo;
                }, 2000);

            } else {
                throw new Error(result.message || result.error || 'Registration failed');
            }
        } catch (error) {
            console.error('Registration error:', error);
            
            document.getElementById('statusCard').innerHTML = `
                <div class="text-center">
                    <div class="flex justify-center mb-6">
                        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Registration Failed</h1>
                    <p class="text-gray-600 mb-6">${error.message}</p>
                    <a href="/public/signup.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Try Again
                    </a>
                </div>
            `;
        }
    })();
</script>

</body>
</html>