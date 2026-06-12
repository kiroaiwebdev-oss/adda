<?php
require_once __DIR__ . '/../../config/app.php';
$pageTitle = 'Sign Up - Internship Adda';
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
        
        .form-input.error {
            border-color: #ef4444;
        }
        
        .error-message {
            color: #ef4444;
            font-size: 14px;
            margin-top: 6px;
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
        
        .password-strength {
            height: 4px;
            background: #e5e7eb;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 4px;
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
                <a href="/login.php" class="text-primary-600 hover:text-primary-700 font-semibold text-sm md:text-base transition-colors">Sign In</a>
            </div>
        </div>
    </header>

    <!-- Signup Form -->
    <div class="container mx-auto px-4 py-8 md:py-12">
        <div class="max-w-md mx-auto">
            <div class="auth-card p-8 md:p-10">
                <div class="text-center mb-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">Create Account</h1>
                    <p class="text-gray-600">Join thousands of learners worldwide</p>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Signup Form -->
                <form id="signupForm" class="space-y-5">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-input" 
                            placeholder="John Doe"
                            required
                        >
                        <div id="nameError" class="error-message hidden"></div>
                    </div>

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
                        <div id="emailError" class="error-message hidden"></div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Minimum 8 characters"
                            required
                        >
                        <div class="password-strength">
                            <div id="passwordStrengthBar" class="password-strength-bar" style="width: 0%"></div>
                        </div>
                        <div id="passwordStrengthText" class="text-xs text-gray-500 mt-1"></div>
                        <div id="passwordError" class="error-message hidden"></div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                        <input 
                            type="password" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            class="form-input" 
                            placeholder="Re-enter your password"
                            required
                        >
                        <div id="password_confirmationError" class="error-message hidden"></div>
                    </div>

                    <div class="flex items-start">
                        <input type="checkbox" id="terms" name="terms" class="w-4 h-4 mt-1 text-primary-600 border-gray-300 rounded focus:ring-primary-500" required>
                        <label for="terms" class="ml-2 text-sm text-gray-600">
                            I agree to the <a href="/terms.php" class="text-primary-600 hover:text-primary-700 font-semibold">Terms of Service</a> and <a href="/privacy.php" class="text-primary-600 hover:text-primary-700 font-semibold">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" id="signupBtn" class="btn-primary">
                        <span id="signupBtnText">Create Account</span>
                        <span id="signupBtnLoader" class="hidden"><span class="loading-spinner"></span> Creating account...</span>
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="/login.php" class="text-primary-600 hover:text-primary-700 font-semibold">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const signupForm = document.getElementById('signupForm');
        const signupBtn = document.getElementById('signupBtn');
        const signupBtnText = document.getElementById('signupBtnText');
        const signupBtnLoader = document.getElementById('signupBtnLoader');
        const alertContainer = document.getElementById('alertContainer');
        const passwordInput = document.getElementById('password');
        const passwordStrengthBar = document.getElementById('passwordStrengthBar');
        const passwordStrengthText = document.getElementById('passwordStrengthText');

        // Password strength checker
        passwordInput.addEventListener('input', () => {
            const password = passwordInput.value;
            const strength = calculatePasswordStrength(password);
            
            let width = 0;
            let color = '#ef4444';
            let text = '';
            
            if (strength === 0) {
                width = 0;
                text = '';
            } else if (strength <= 2) {
                width = 33;
                color = '#ef4444';
                text = 'Weak password';
            } else if (strength <= 3) {
                width = 66;
                color = '#f59e0b';
                text = 'Medium password';
            } else {
                width = 100;
                color = '#22c55e';
                text = 'Strong password';
            }
            
            passwordStrengthBar.style.width = width + '%';
            passwordStrengthBar.style.backgroundColor = color;
            passwordStrengthText.textContent = text;
            passwordStrengthText.style.color = color;
        });

        function calculatePasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            return strength;
        }

        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Clear previous errors
            clearErrors();
            
            // Get form data
            const formData = new FormData(signupForm);
            const data = {
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password'),
                password_confirmation: formData.get('password_confirmation'),
                terms: formData.get('terms') ? true : false
            };
            
            // Validate terms
            if (!data.terms) {
                showAlert('error', 'Please accept the Terms of Service and Privacy Policy');
                return;
            }
            
            // Disable button
            signupBtn.disabled = true;
            signupBtnText.classList.add('hidden');
            signupBtnLoader.classList.remove('hidden');
            
            try {
                const response = await fetch('/api/auth.php?action=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message || 'Account created successfully! Redirecting...');
                    
                    // Redirect to login or dashboard
                    setTimeout(() => {
                        window.location.href = '/login.php';
                    }, 1500);
                } else {
                    showAlert('error', result.error || 'Registration failed. Please try again.');
                    signupBtn.disabled = false;
                    signupBtnText.classList.remove('hidden');
                    signupBtnLoader.classList.add('hidden');
                    
                    // Show field-specific errors
                    if (result.errors) {
                        Object.keys(result.errors).forEach(field => {
                            showFieldError(field, result.errors[field][0]);
                        });
                    }
                }
            } catch (error) {
                showAlert('error', 'Network error. Please check your connection.');
                signupBtn.disabled = false;
                signupBtnText.classList.remove('hidden');
                signupBtnLoader.classList.add('hidden');
            }
        });

        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showFieldError(field, message) {
            const errorDiv = document.getElementById(`${field}Error`);
            const input = document.getElementById(field);
            
            if (errorDiv && input) {
                errorDiv.textContent = message;
                errorDiv.classList.remove('hidden');
                input.classList.add('error');
            }
        }

        function clearErrors() {
            alertContainer.innerHTML = '';
            document.querySelectorAll('.error-message').forEach(el => {
                el.classList.add('hidden');
                el.textContent = '';
            });
            document.querySelectorAll('.form-input').forEach(el => {
                el.classList.remove('error');
            });
        }

        // Clear error on input
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('error');
                const errorDiv = document.getElementById(`${input.id}Error`);
                if (errorDiv) {
                    errorDiv.classList.add('hidden');
                }
            });
        });
    </script>

</body>
</html>
