<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Internship Adda</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
    
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
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Poppins', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        h1, h2, h3 {
            font-family: 'Poppins', sans-serif;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .fade-in-delay-1 { animation-delay: 0.2s; opacity: 0; }
        .fade-in-delay-2 { animation-delay: 0.4s; opacity: 0; }
        .fade-in-delay-3 { animation-delay: 0.6s; opacity: 0; }
        
        .gradient-text {
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-hover {
            transition: all 0.3s ease;
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.3);
        }
        
        .btn-hover:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="text-center">
            <!-- Logo -->
            <div class="mb-8 fade-in">
                <a href="/public/index.html" class="inline-flex items-center gap-3 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow">
                        <span class="text-white font-bold text-2xl">IA</span>
                    </div>
                    <span class="text-2xl font-bold text-gray-900">Internship Adda</span>
                </a>
            </div>
            
            <!-- 404 Illustration -->
            <div class="mb-8 fade-in fade-in-delay-1">
                <div class="relative inline-block">
                    <!-- Main 404 Number -->
                    <div class="floating">
                        <h1 class="text-9xl md:text-[200px] font-black gradient-text leading-none">
                            404
                        </h1>
                    </div>
                    
                    <!-- Decorative elements -->
                    <div class="absolute -top-4 -right-4 w-20 h-20 bg-primary-200 rounded-full opacity-50 blur-xl"></div>
                    <div class="absolute -bottom-4 -left-4 w-24 h-24 bg-primary-300 rounded-full opacity-40 blur-xl"></div>
                </div>
            </div>
            
            <!-- Error Message -->
            <div class="mb-6 fade-in fade-in-delay-2">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Oops! Page Not Found
                </h2>
                <p class="text-lg md:text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
                    The page you're looking for doesn't exist or has been moved. 
                    <br class="hidden sm:block">
                    Don't worry, let's get you back on track! 🚀
                </p>
            </div>
            
            <!-- Suggested URL (if available) -->
            <?php if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/'): ?>
                <div class="mb-8 fade-in fade-in-delay-2">
                    <div class="bg-white border-2 border-gray-200 rounded-xl p-4 max-w-xl mx-auto">
                        <p class="text-sm text-gray-500 mb-2">You tried to access:</p>
                        <code class="text-sm font-mono text-red-600 break-all">
                            <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>
                        </code>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-12 fade-in fade-in-delay-3">
                <!-- Home Button -->
                <a href="/public/index.html" 
                   class="btn-hover w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-gradient-to-r from-primary-600 to-green-600 text-white px-8 py-4 rounded-xl font-bold text-lg shadow-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span>Go to Homepage</span>
                </a>
                
                <!-- Back Button -->
                <button onclick="history.back()" 
                        class="btn-hover w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-white border-2 border-primary-600 text-primary-700 px-8 py-4 rounded-xl font-bold text-lg shadow-lg hover:bg-primary-50">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    <span>Go Back</span>
                </button>
            </div>
            
            <!-- Quick Links -->
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8 max-w-3xl mx-auto fade-in fade-in-delay-3">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Popular Pages</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Courses -->
                    <a href="/public/courses.php" 
                       class="group p-4 bg-gradient-to-br from-blue-50 to-cyan-50 border-2 border-transparent hover:border-blue-300 rounded-xl transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-1">Courses</h4>
                        <p class="text-sm text-gray-600">Browse courses</p>
                    </a>
                    
                    <!-- Login -->
                    <a href="/public/login.php" 
                       class="group p-4 bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-transparent hover:border-green-300 rounded-xl transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-1">Login</h4>
                        <p class="text-sm text-gray-600">Access account</p>
                    </a>
                    
                    <!-- About -->
                    <a href="/public/about.html" 
                       class="group p-4 bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-transparent hover:border-purple-300 rounded-xl transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-1">About Us</h4>
                        <p class="text-sm text-gray-600">Learn more</p>
                    </a>
                    
                    <!-- Contact -->
                    <a href="/public/contact.html" 
                       class="group p-4 bg-gradient-to-br from-orange-50 to-red-50 border-2 border-transparent hover:border-orange-300 rounded-xl transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-orange-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-1">Contact</h4>
                        <p class="text-sm text-gray-600">Get in touch</p>
                    </a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="mt-12 text-center text-gray-500 text-sm fade-in fade-in-delay-3">
                <p>&copy; <?php echo date('Y'); ?> Internship Adda. All rights reserved.</p>
                <p class="mt-2">
                    <a href="/public/privacy-policy.html" class="text-primary-600 hover:text-primary-700 font-medium">Privacy Policy</a>
                    <span class="mx-2">•</span>
                    <a href="/public/toc.html" class="text-primary-600 hover:text-primary-700 font-medium">Terms of Service</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
