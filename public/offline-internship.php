<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline Internship Application - Internship Adda</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .header-scrolled {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid rgba(34, 197, 94, 0.1);
        }
        
        /* Nav Links */
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
        
        /* Mobile Menu */
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .mobile-menu.active {
            max-height: 600px;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 16px 40px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.35);
            display: inline-block;
            text-decoration: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(34, 197, 94, 0.45);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-link {
                padding: 12px 16px;
                font-size: 15px;
            }
            
            h1 {
                font-size: 2rem !important;
                line-height: 1.2 !important;
            }
            
            h2 {
                font-size: 1.75rem !important;
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
            <div class="flex items-center">
                <a href="https://internshipadda.com"> <img 
                        src="../../icon.png" 
                        alt="InternshipAdda Logo"
                        class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
                    ></a>
            </div>
            
            <!-- Desktop Menu -->
            <div class="hidden lg:flex items-center justify-center flex-1 mx-8">
                <div class="flex items-center space-x-2">
                    <a href="/public/about.html" class="nav-link">About</a>
                    <a href="/public/services.html" class="nav-link">Services</a>
                    <a href="/public/courses.php" class="nav-link">Courses</a>
                    <a href="/public/verify.html" class="nav-link">Verify Certificate</a>
                    <a href="/public/contact.html" class="nav-link">Contact</a>
                </div>
            </div>
            
            <!-- Desktop Right Menu -->
            <div class="hidden lg:flex items-center space-x-3">
                <a href="/public/login.php" class="text-primary-600 font-semibold px-4 py-2 rounded-lg hover:bg-primary-50 transition-colors">Login</a>
                <a href="/public/signup.php" class="btn-primary" style="padding: 12px 28px;">Sign Up</a>
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
                <a href="/public/about.html" class="nav-link block hover:bg-gray-50 rounded-lg">About</a>
                <a href="/public/services.html" class="nav-link block hover:bg-gray-50 rounded-lg">Services</a>
                <a href="/public/courses.php" class="nav-link block hover:bg-gray-50 rounded-lg">Courses</a>
                <a href="/public/verify.html" class="nav-link block hover:bg-gray-50 rounded-lg">Verify Certificate</a>
                <a href="/public/contact.html" class="nav-link block hover:bg-gray-50 rounded-lg">Contact</a>
            </div>
            <div class="pt-4 space-y-3">
                <a href="/public/login.php" class="block text-center bg-gray-100 text-gray-700 px-4 py-3 rounded-lg font-semibold">Login</a>
                <a href="/public/signup.php" class="block text-center bg-primary-600 text-white px-4 py-3 rounded-lg font-semibold">Sign Up</a>
            </div>
        </div>
    </nav>
</header>

<!-- Main Content with Top Padding -->
<div class="pt-20 md:pt-24">

<!-- Hero Section -->
<div class="bg-gradient-to-br from-primary-600 to-primary-800 text-white py-12 md:py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto text-center">
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4">🏢 Offline Internship Program</h1>
            <p class="text-lg md:text-xl text-primary-100 mb-6">Get hands-on experience with real projects at our office in Patna, Bihar</p>
            <div class="flex flex-wrap justify-center gap-3 md:gap-4 text-sm">
                <span class="bg-white/20 px-3 md:px-4 py-2 rounded-full">✅ Certificate Included</span>
                <span class="bg-white/20 px-3 md:px-4 py-2 rounded-full">✅ Stipend Available</span>
                <span class="bg-white/20 px-3 md:px-4 py-2 rounded-full">✅ Job Opportunity</span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container mx-auto px-4 py-8 md:py-12">
    <div class="max-w-4xl mx-auto">
        
        <!-- Alert Container -->
        <div id="alertContainer" class="mb-6"></div>
        
        <div class="grid md:grid-cols-2 gap-6 md:gap-8 mb-8">
            <!-- Benefits Card -->
            <div class="bg-white rounded-xl shadow-lg p-5 md:p-6">
                <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-4">🎯 What You'll Get</h2>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-primary-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm md:text-base">Real-world project experience</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-primary-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm md:text-base">Industry mentorship</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-primary-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm md:text-base">Verified internship certificate</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-primary-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm md:text-base">Stipend based on performance</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-primary-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm md:text-base">Job opportunity after completion</span>
                    </li>
                </ul>
            </div>
            
            <!-- Available Domains -->
            <div class="bg-white rounded-xl shadow-lg p-5 md:p-6">
                <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-4">💼 Available Domains</h2>
                <div class="space-y-2">
                    <div class="bg-primary-50 border border-primary-200 rounded-lg p-3">
                        <h3 class="font-semibold text-primary-700 text-sm md:text-base">Web Development</h3>
                        <p class="text-xs md:text-sm text-gray-600">Frontend, Backend, Full Stack</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <h3 class="font-semibold text-blue-700 text-sm md:text-base">Mobile App Development</h3>
                        <p class="text-xs md:text-sm text-gray-600">Android, iOS, Flutter</p>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                        <h3 class="font-semibold text-purple-700 text-sm md:text-base">Data Science & AI</h3>
                        <p class="text-xs md:text-sm text-gray-600">ML, Analytics, Python</p>
                    </div>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                        <h3 class="font-semibold text-orange-700 text-sm md:text-base">Digital Marketing</h3>
                        <p class="text-xs md:text-sm text-gray-600">SEO, Social Media, Ads</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
    <h3 class="font-semibold text-gray-800 text-sm md:text-base">Others </h3>
    <p class="text-xs md:text-sm text-gray-600">Cyber Security, UI/UX, Cloud, DevOps & more</p>
</div>

                </div>
            </div>
        </div>
        
        <!-- Application Form -->
        <div class="bg-white rounded-xl shadow-lg p-5 md:p-8">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">📋 Apply Now</h2>
            <p class="text-gray-600 mb-6 text-sm md:text-base">Fill in your details and our team will contact you soon</p>
            
            <form id="offlineInternshipForm" class="space-y-4 md:space-y-6">
                
                <!-- Personal Information -->
                <div class="grid md:grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                            placeholder="Enter your full name"
                            required
                        >
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                            placeholder="your.email@example.com"
                            required
                        >
                    </div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                            placeholder="+91 9876543210"
                            pattern="[0-9]{10}"
                            required
                        >
                    </div>
                    
                    <div>
                        <label for="college" class="block text-sm font-semibold text-gray-700 mb-2">College/University</label>
                        <input 
                            type="text" 
                            id="college" 
                            name="college" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                            placeholder="Your college name"
                        >
                    </div>
                </div>
                
                <!-- Academic Information -->
                <div class="grid md:grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label for="course" class="block text-sm font-semibold text-gray-700 mb-2">Course/Degree</label>
                        <input 
                            type="text" 
                            id="course" 
                            name="course" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                            placeholder="B.Tech, BCA, MCA, etc."
                        >
                    </div>
                    
                    <div>
                        <label for="year" class="block text-sm font-semibold text-gray-700 mb-2">Current Year</label>
                        <select 
                            id="year" 
                            name="year" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                        >
                            <option value="">Select year</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                            <option value="Final Year">Final Year</option>
                            <option value="Graduated">Graduated</option>
                        </select>
                    </div>
                </div>
                
                <!-- Internship Preferences -->
                <div class="grid md:grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label for="internship_type" class="block text-sm font-semibold text-gray-700 mb-2">Internship Domain *</label>
                        <select 
                            id="internship_type" 
                            name="internship_type" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                            required
                        >
                            <option value="">Select domain</option>
                            <option value="Web Development">Web Development</option>
                            <option value="Mobile App Development">Mobile App Development</option>
                            <option value="Data Science & AI">Data Science & AI</option>
                            <option value="Digital Marketing">Digital Marketing</option>
                            <option value="UI/UX Design">UI/UX Design</option>
                            <option value="Content Writing">Content Writing</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="duration" class="block text-sm font-semibold text-gray-700 mb-2">Preferred Duration</label>
                        <select 
                            id="duration" 
                            name="duration" 
                            class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                        >
                            <option value="">Select duration</option>
                            <option value="1 Month">1 Month</option>
                            <option value="2 Months">2 Months</option>
                            <option value="3 Months">3 Months</option>
                            <option value="6 Months">6 Months</option>
                        </select>
                    </div>
                </div>
                
                <!-- Additional Message -->
                <div>
                    <label for="message" class="block text-sm font-semibold text-gray-700 mb-2">Why do you want this internship?</label>
                    <textarea 
                        id="message" 
                        name="message" 
                        rows="4" 
                        class="w-full px-3 md:px-4 py-2.5 md:py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500 text-sm md:text-base"
                        placeholder="Tell us about your interest and expectations..."
                    ></textarea>
                </div>
                
                <!-- Submit Button -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-gray-500">* Required fields</p>
                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="w-full sm:w-auto bg-primary-600 hover:bg-primary-700 text-white px-6 md:px-8 py-3 rounded-lg font-semibold transition-all hover:shadow-lg flex items-center justify-center gap-2 text-sm md:text-base"
                    >
                        <span>Submit Application</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </button>
                </div>
                
            </form>
        </div>
        
        <!-- Contact Info -->
        <div class="mt-8 bg-primary-50 border border-primary-200 rounded-xl p-5 md:p-6 text-center">
            <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-2">Need Help?</h3>
            <p class="text-sm md:text-base text-gray-600 mb-4">Contact us for any queries</p>
            <div class="flex flex-wrap justify-center gap-3 md:gap-4">
                <a href="tel:+917209747479" class="flex items-center gap-2 text-primary-600 font-semibold text-sm md:text-base">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    +91 7209747479
                </a>
                <a href="mailto:info@internshipadda.com" class="flex items-center gap-2 text-primary-600 font-semibold text-sm md:text-base">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    info@internshipadda.com
                </a>
            </div>
        </div>
        
    </div>
</div>

</div>

<!-- Footer -->
<footer class="bg-gray-900 text-white py-12 md:py-16 mt-12">
    <div class="container mx-auto px-4">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12 mb-8 md:mb-12">
            <!-- Company Info -->
            <div>
                <div class="flex items-center space-x-3 mb-4 md:mb-6">
                    <a href="https://internshipadda.com"> <img 
                        src="https://internshipadda.com/icons.jpg" 
                        alt="InternshipAdda Logo"
                        class="w-auto max-h-12 sm:max-h-12 md:max-h-12 lg:max-h-16"
                    ></a>
                </div>
                <p class="text-sm md:text-base text-gray-400 leading-relaxed mb-4">
                    Empowering professionals worldwide with world-class internships and verified certifications.
                </p>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Quick Links</h4>
                <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                    <li><a href="/public/about.html" class="text-gray-400 hover:text-primary-500 transition-colors">About Us</a></li>
                    <li><a href="/public/services.html" class="text-gray-400 hover:text-primary-500 transition-colors">Services</a></li>
                    <li><a href="/public/courses.php" class="text-gray-400 hover:text-primary-500 transition-colors">Browse Programs</a></li>
                    <li><a href="/public/contact.html" class="text-gray-400 hover:text-primary-500 transition-colors">Contact</a></li>
                </ul>
            </div>
            
            <!-- Support -->
            <div>
                <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Support</h4>
                <ul class="space-y-2 md:space-y-3 text-sm md:text-base">
                    <li><a href="#" class="text-gray-400 hover:text-primary-500 transition-colors">Help Center</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-primary-500 transition-colors">Privacy Policy</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-primary-500 transition-colors">Terms of Service</a></li>
                </ul>
            </div>
            
            <!-- Contact -->
            <div>
                <h4 class="text-base md:text-lg font-bold mb-4 md:mb-6">Get in Touch</h4>
                <ul class="space-y-3 text-sm md:text-base">
                    <li class="flex items-center gap-2 text-gray-400">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                        </svg>
                        info@internshipadda.com
                    </li>
                    <li class="flex items-center gap-2 text-gray-400">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                        </svg>
                        +91 7209747479
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Bottom Footer -->
        <div class="border-t border-gray-800 pt-6 md:pt-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-gray-400 text-xs md:text-sm text-center md:text-left">
                    © 2026 Internship Adda. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top Button -->
<button id="scrollTop" class="fixed bottom-6 right-6 w-11 h-11 md:w-12 md:h-12 bg-gradient-to-br from-primary-500 to-primary-700 text-white rounded-full shadow-lg opacity-0 invisible transition-all duration-300 hover:scale-110 z-40 flex items-center justify-center">
    <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
    </svg>
</button>
<script>
// Header Scroll Effect
const header = document.getElementById('header');
window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        header.classList.add('header-scrolled');
    } else {
        header.classList.remove('header-scrolled');
    }
});

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

mobileMenuBtn.addEventListener('click', () => {
    mobileMenu.classList.toggle('active');
});

// Close mobile menu when clicking on links
const mobileMenuLinks = mobileMenu.querySelectorAll('a');
mobileMenuLinks.forEach(link => {
    link.addEventListener('click', () => {
        mobileMenu.classList.remove('active');
    });
});

// SUCCESS POPUP MODAL (Modern Design)
function showSuccessPopup() {
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    overlay.style.animation = 'fadeIn 0.3s ease-in-out';
    
    // Create popup
    const popup = document.createElement('div');
    popup.className = 'bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 md:p-8 relative';
    popup.style.animation = 'slideUp 0.4s ease-out';
    popup.innerHTML = `
        <div class="text-center">
            <!-- Success Icon -->
            <div class="w-16 h-16 md:w-20 md:h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6">
                <svg class="w-8 h-8 md:w-10 md:h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            
            <!-- Title -->
            <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-3">Application Submitted! 🎉</h3>
            
            <!-- Message -->
            <p class="text-base md:text-lg text-gray-600 mb-6">
                Your application has been successfully submitted. Our team will review your application and contact you within <strong class="text-primary-600">24-48 hours</strong>.
            </p>
            
            <!-- Next Steps -->
            <div class="bg-primary-50 rounded-xl p-4 mb-6 text-left">
                <h4 class="font-bold text-gray-900 mb-2 text-sm md:text-base">📋 What's Next?</h4>
                <ul class="space-y-2 text-xs md:text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <span class="text-primary-600 font-bold">1.</span>
                        <span>Check your email for confirmation</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-primary-600 font-bold">2.</span>
                        <span>Our team will call you for interview</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-primary-600 font-bold">3.</span>
                        <span>Join orientation & start internship</span>
                    </li>
                </ul>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3">
                <button onclick="closePopup()" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-all">
                    Got It!
                </button>
                <a href="/public/courses.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-all text-center">
                    Browse Courses
                </a>
            </div>
        </div>
    `;
    
    overlay.appendChild(popup);
    document.body.appendChild(overlay);
    
    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closePopup();
        }
    });
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closePopup() {
    const overlay = document.querySelector('.fixed.inset-0.bg-black');
    if (overlay) {
        overlay.style.animation = 'fadeOut 0.3s ease-in-out';
        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
        }, 300);
    }
}

// Form Submission
document.getElementById('offlineInternshipForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <svg class="animate-spin h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Submitting...</span>
    `;
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('/api/offline-internship.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success popup
            showSuccessPopup();
            
            // Reset form
            e.target.reset();
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            showAlert('error', '❌ ' + (result.error || 'Failed to submit application. Please try again.'));
        }
    } catch (error) {
        showAlert('error', '❌ Network error. Please check your connection and try again.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const bgColor = type === 'success' ? 'bg-green-100 border-green-500 text-green-800' : 'bg-red-100 border-red-500 text-red-800';
    
    alertContainer.innerHTML = `
        <div class="${bgColor} border-l-4 p-4 rounded-lg shadow-md animate-slide-down">
            <p class="font-semibold">${message}</p>
        </div>
    `;
    
    setTimeout(() => {
        alertContainer.innerHTML = '';
    }, 8000);
}

// Scroll to Top
const scrollTopBtn = document.getElementById('scrollTop');

window.addEventListener('scroll', () => {
    if (window.pageYOffset > 500) {
        scrollTopBtn.style.opacity = '1';
        scrollTopBtn.style.visibility = 'visible';
    } else {
        scrollTopBtn.style.opacity = '0';
        scrollTopBtn.style.visibility = 'hidden';
    }
});

scrollTopBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Animation CSS (Add to style tag)
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes slideUp {
        from { 
            transform: translateY(30px);
            opacity: 0;
        }
        to { 
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes slide-down {
        from { 
            transform: translateY(-20px);
            opacity: 0;
        }
        to { 
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .animate-slide-down {
        animation: slide-down 0.4s ease-out;
    }
`;
document.head.appendChild(style);
</script>


</body>
</html>
