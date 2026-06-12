<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Internship Adda</title>
    
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
                            '50': '#f0fdf4',
                            '100': '#dcfce7',
                            '200': '#bbf7d0',
                            '300': '#86efac',
                            '400': '#4ade80',
                            '500': '#22c55e',
                            '600': '#16a34a',
                            '700': '#15803d',
                            '800': '#166534',
                            '900': '#14532d',
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
            background: #ffffff;
            color: #1f2937;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        
        /* Premium Header Design */
        header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .header-scrolled {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid rgba(34, 197, 94, 0.1);
        }
        
        .nav-link {
            position: relative;
            padding: 8px 16px;
            color: #4b5563;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .nav-link:before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover {
            color: #22c55e;
        }
        
        .nav-link:hover:before {
            width: 80%;
        }
        
        .btn-login {
            color: #22c55e;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 15px;
        }
        
        .btn-login:hover {
            background: #f0fdf4;
            color: #16a34a;
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-signup:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.4);
        }
        
        .btn-signup:hover:before {
            left: 100%;
        }
        
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .mobile-menu.active {
            max-height: 700px;
        }
        
        /* 404 Page Specific Styles */
        .error-container {
            min-height: calc(100vh - 80px);
            padding-top: 80px;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        .error-number {
            font-size: 10rem;
            font-weight: 900;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        @media (max-width: 768px) {
            .error-number {
                font-size: 6rem;
            }
            
            .error-container {
                padding-top: 80px;
                min-height: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Premium Navigation Header -->
    <header id="header" class="fixed w-full top-0 z-50 transition-all duration-300">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="logo-icon w-11 h-11 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span class="text-xl md:text-2xl font-bold text-gray-900">Internship<span class="text-primary-600">Adda</span></span>
                </div>
                
                <!-- Desktop Center Menu -->
                <div class="hidden lg:flex items-center justify-center flex-1 mx-8">
                    <div class="flex items-center space-x-2">
                        <a href="/about.html" class="nav-link">About</a>
                        <a href="/services.html" class="nav-link">Services</a>
                        <a href="/courses.html" class="nav-link">Courses</a>
                        <a href="/verify.html" class="nav-link">Verify Certificate</a>
                        <a href="/contact.html" class="nav-link">Contact</a>
                    </div>
                </div>
                
                <!-- Desktop Right Menu - Login & Sign Up -->
                <div class="hidden lg:flex items-center space-x-3">
                    <a href="/login.php" class="btn-login">Login</a>
                    <a href="/signup.php" class="btn-signup">Sign Up</a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="mobile-menu lg:hidden">
                <div class="pt-4 pb-3 space-y-1">
                    <a href="/about.html" class="nav-link block hover:bg-gray-50 rounded-lg">About</a>
                    <a href="/services.html" class="nav-link block hover:bg-gray-50 rounded-lg">Services</a>
                    <a href="/courses.html" class="nav-link block hover:bg-gray-50 rounded-lg">Courses</a>
                    <a href="/verify.html" class="nav-link block hover:bg-gray-50 rounded-lg">Verify Certificate</a>
                    <a href="/contact.html" class="nav-link block hover:bg-gray-50 rounded-lg">Contact</a>
                    <div class="pt-4 space-y-3">
                        <a href="/login.php" class="btn-login block text-center">Login</a>
                        <a href="/signup.php" class="btn-signup block text-center">Sign Up</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- 404 Error Content -->
    <div class="error-container flex items-center justify-center bg-gradient-to-br from-primary-50 via-white to-primary-50">
        <div class="container mx-auto px-4 py-16 md:py-20">
            <div class="max-w-4xl mx-auto text-center">
                <!-- 404 Number -->
                <div class="float-animation mb-8">
                    <h1 class="error-number">404</h1>
                </div>
                
                <!-- Error Message -->
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 mb-6">
                    Oops! Page Not Found
                </h2>
                
                <p class="text-lg md:text-xl text-gray-600 mb-12 max-w-2xl mx-auto">
                    The page you're looking for doesn't exist or has been moved. Let's get you back on track!
                </p>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16">
                    <a href="/" class="inline-flex items-center justify-center px-8 py-4 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-bold rounded-xl hover:shadow-2xl transition-all duration-300 hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Back to Home
                    </a>
                    
                    <a href="/courses.html" class="inline-flex items-center justify-center px-8 py-4 bg-white text-primary-600 font-bold rounded-xl border-2 border-primary-200 hover:border-primary-400 hover:bg-primary-50 transition-all duration-300">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Browse Courses
                    </a>
                </div>
                
                <!-- Quick Links -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-3xl mx-auto">
                    <a href="/" class="p-6 bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 border border-gray-100">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                        </div>
                        <h3 class="font-bold text-gray-900">Home</h3>
                    </a>
                    
                    <a href="/about.html" class="p-6 bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 border border-gray-100">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="font-bold text-gray-900">About</h3>
                    </a>
                    
                    <a href="/verify.html" class="p-6 bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 border border-gray-100">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="font-bold text-gray-900">Verify</h3>
                    </a>
                    
                    <a href="/contact.html" class="p-6 bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 border border-gray-100">
                        <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="font-bold text-gray-900">Contact</h3>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 md:py-16">
        <div class="container mx-auto px-4">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12 mb-8 md:mb-12">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-3 mb-4 md:mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <span class="text-xl md:text-2xl font-bold">Internship<span class="text-primary-500">Adda</span></span>
                    </div>
                    <p class="text-sm md:text-base text-gray-400 leading-relaxed mb-4">
                        Empowering professionals worldwide with world-class internships and verified certifications.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-primary-600 rounded-lg flex items-center justify-center transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-primary-600 rounded-lg flex items-center justify-center transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-primary-600 rounded-lg flex items-center justify-center transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-primary-600 rounded-lg flex items-center justify-center transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Quick Links</h4>
                    <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                        <li><a href="/about.html" class="text-gray-400 hover:text-primary-500 transition-colors">About Us</a></li>
                        <li><a href="/services.html" class="text-gray-400 hover:text-primary-500 transition-colors">Services</a></li>
                        <li><a href="/courses.html" class="text-gray-400 hover:text-primary-500 transition-colors">Browse Programs</a></li>
                        <li><a href="/contact.html" class="text-gray-400 hover:text-primary-500 transition-colors">Become Mentor</a></li>
                        <li><a href="/contact.html" class="text-gray-400 hover:text-primary-500 transition-colors">Contact</a></li>
                    </ul>
                </div>
                
                <!-- Categories -->
                <div>
                    <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Categories</h4>
                    <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                        <li><a href="/courses.html" class="text-gray-400 hover:text-primary-500 transition-colors">Web Development</a></li>
                        <li><a href="/courses.html" class="text-gray-400 hover:text-primary-500 transition-colors">Data Science</a></li>
                        <li><a href="/courses.html" class="text-gray-400 hover:text-primary-500 transition-colors">Digital Marketing</a></li>
                        <li><a href="/courses.html" class="text-gray-400 hover:text-primary-500 transition-colors">Mobile Development</a></li>
                        <li><a href="/courses.html" class="text-gray-400 hover:text-primary-500 transition-colors">UI/UX Design</a></li>
                        <li><a href="/courses.html" class="text-gray-400 hover:text-primary-500 transition-colors">Cloud Computing</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div>
                    <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Support</h4>
                    <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                        <li><a href="/contact.html" class="text-gray-400 hover:text-primary-500 transition-colors">Help Center</a></li>
                        <li><a href="/toc.html" class="text-gray-400 hover:text-primary-500 transition-colors">Terms of Service</a></li>
                        <li><a href="/privacy-policy.html" class="text-gray-400 hover:text-primary-500 transition-colors">Privacy Policy</a></li>
                        <li><a href="/cookie.html" class="text-gray-400 hover:text-primary-500 transition-colors">Cookie Policy</a></li>
                        <li><a href="/refund.html" class="text-gray-400 hover:text-primary-500 transition-colors">Refund Policy</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Bottom Footer -->
            <div class="border-t border-gray-800 pt-6 md:pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <p class="text-gray-400 text-xs md:text-sm text-center md:text-left">
                        © 2026 Internship Adda. All rights reserved. Built with ❤️ for learners worldwide. 
                        <span class="mx-1">•</span>
                        Built by 
                        <a href="https://devsarun.io/" target="_blank" class="text-gray-300 hover:text-white underline-offset-2 hover:underline">
                            DevsArun
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!mobileMenuBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }
        });

        // Header scroll effect
        const header = document.getElementById('header');
        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 50) {
                header.classList.add('header-scrolled');
            } else {
                header.classList.remove('header-scrolled');
            }
            
            lastScroll = currentScroll;
        });
    </script>
</body>
</html>
