<?php
require_once __DIR__ . '/../../config/app.php';
$pageTitle = 'Forgot Password - Internship Adda';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
            color: #1f2937;
            min-height: 100vh;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        
        .auth-card {
            background: white;
            border: 1px solid rgba(34, 197, 94, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border-radius: 24px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.35);
            border: none;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(34, 197, 94, 0.45);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .loading-spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #22c55e;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="py-6">
        <div class="container mx-auto px-4 md:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <a href="/" class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-700 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-xl">I</span>
                    </div>
                    <span class="text-xl md:text-2xl font-bold text-gray-900">Internship Adda</span>
                </a>
                <a href="/login.php" class="text-primary-600 hover:text-primary-700 font-semibold text-sm md:text-base transition-colors">Back to Login</a>
            </div>
        </div>
    </header>

    <!-- Forgot Password Form -->
    <div class="container mx-auto px-4 py-8 md:py-12">
        <div class="max-w-md mx-auto">
            <div class="auth-card p-8 md:p-10">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Forgot Password?</h1>
                    <p class="text-gray-600">Enter your email and we'll send you reset instructions</p>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Forgot Password Form -->
                <form id="forgotForm" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="your.email@example.com"
                            required
                        >
                    </div>

                    <button type="submit" id="resetBtn" class="btn-primary">
                        <span id="resetBtnText">Send Reset Link</span>
                        <span id="resetBtnLoader" class="hidden"><span class="loading-spinner"></span> Sending...</span>
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        Remember your password? 
                        <a href="/login.php" class="text-primary-600 hover:text-primary-700 font-semibold">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const forgotForm = document.getElementById('forgotForm');
        const resetBtn = document.getElementById('resetBtn');
        const resetBtnText = document.getElementById('resetBtnText');
        const resetBtnLoader = document.getElementById('resetBtnLoader');
        const alertContainer = document.getElementById('alertContainer');

        forgotForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            alertContainer.innerHTML = '';
            
            const formData = new FormData(forgotForm);
            const data = { email: formData.get('email') };
            
            resetBtn.disabled = true;
            resetBtnText.classList.add('hidden');
            resetBtnLoader.classList.remove('hidden');
            
            try {
                const response = await fetch('/api/auth.php?action=forgot-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alertContainer.innerHTML = `<div class="alert alert-success">${result.message || 'Reset link sent! Check your email.'}</div>`;
                    forgotForm.reset();
                } else {
                    alertContainer.innerHTML = `<div class="alert alert-error">${result.error || 'Failed to send reset link.'}</div>`;
                }
            } catch (error) {
                alertContainer.innerHTML = '<div class="alert alert-error">Network error. Please try again.</div>';
            } finally {
                resetBtn.disabled = false;
                resetBtnText.classList.remove('hidden');
                resetBtnLoader.classList.add('hidden');
            }
        });
    </script>

</body>
</html>
