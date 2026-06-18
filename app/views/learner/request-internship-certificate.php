<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../core/' . $class . '.php',
        __DIR__ . '/../../models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
$auth->requireAuth();

if ($auth->isAdmin()) {
    header('Location: /app/views/admin/dashboard.php');
    exit;
}

$userId = $auth->id();
$currentUser = $auth->user();

$internshipId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$internshipId) {
    header('Location: /app/views/learner/my-internships.php');
    exit;
}

// Check if enrolled
$enrollStmt = $db->prepare("SELECT * FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
$enrollStmt->execute([$userId, $internshipId]);
$enrollment = $enrollStmt->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) {
    header('Location: /app/views/learner/my-internships.php?error=not_enrolled');
    exit;
}

// Get internship details
$internshipStmt = $db->prepare("SELECT * FROM internships WHERE id = ?");
$internshipStmt->execute([$internshipId]);
$internship = $internshipStmt->fetch(PDO::FETCH_ASSOC);

if (!$internship) {
    header('Location: /app/views/learner/my-internships.php?error=not_found');
    exit;
}

// Check if already requested
$checkStmt = $db->prepare("SELECT * FROM internship_certificate_requests WHERE user_id = ? AND internship_id = ?");
$checkStmt->execute([$userId, $internshipId]);
$existingRequest = $checkStmt->fetch(PDO::FETCH_ASSOC);

// Check completion percentage
$totalLessonsStmt = $db->prepare("
    SELECT COUNT(DISTINCT il.id) as total
    FROM internship_lessons il
    INNER JOIN internship_modules im ON il.module_id = im.id
    WHERE im.internship_id = ?
");
$totalLessonsStmt->execute([$internshipId]);
$totalLessons = $totalLessonsStmt->fetch(PDO::FETCH_ASSOC)['total'];

$completedLessonsStmt = $db->prepare("
    SELECT COUNT(DISTINCT ilp.lesson_id) as completed
    FROM internship_lesson_progress ilp
    INNER JOIN internship_lessons il ON ilp.lesson_id = il.id
    INNER JOIN internship_modules im ON il.module_id = im.id
    WHERE im.internship_id = ? AND ilp.student_id = ? AND ilp.is_completed = 1
");
$completedLessonsStmt->execute([$internshipId, $userId]);
$completedLessons = $completedLessonsStmt->fetch(PDO::FETCH_ASSOC)['completed'];

$completionPercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingRequest) {
    $studentName = trim($_POST['student_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $collegeName = trim($_POST['college_name'] ?? '');
    $degree = trim($_POST['degree'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($studentName) || empty($email)) {
        $error = "Name and Email are required!";
    } else {
        try {
            $insertStmt = $db->prepare("
                INSERT INTO internship_certificate_requests 
                (user_id, internship_id, enrollment_id, student_name, email, phone, linkedin_url, address, dob, gender, college_name, degree, notes, status, request_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $insertStmt->execute([
                $userId, 
                $internshipId, 
                $enrollment['id'],
                $studentName, 
                $email, 
                $phone ?: null, 
                $linkedinUrl ?: null, 
                $address ?: null, 
                $dob ?: null, 
                $gender ?: null, 
                $collegeName ?: null, 
                $degree ?: null, 
                $notes ?: null
            ]);
            
            header('Location: /app/views/learner/my-internships.php?success=certificate_requested');
            exit;
        } catch (Exception $e) {
            $error = "Failed to submit request: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Certificate - <?php echo htmlspecialchars($internship['title']); ?></title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
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
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">

<div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
        
        <!-- Header -->
        <div class="mb-8">
            <a href="/app/views/learner/my-internships.php" class="inline-flex items-center text-sm text-gray-600 hover:text-primary-600 mb-4">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to My Internships
            </a>
            
            <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 border border-gray-200">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Request Certificate</h1>
                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($internship['title']); ?></p>
                    </div>
                </div>
                
                <!-- Completion Progress -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Course Completion</span>
                        <span class="text-sm font-bold text-primary-600"><?php echo $completionPercentage; ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-3 rounded-full transition-all" style="width: <?php echo $completionPercentage; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2"><?php echo $completedLessons; ?> of <?php echo $totalLessons; ?> lessons completed</p>
                </div>
            </div>
        </div>
        
        <?php if ($existingRequest): ?>
            <!-- Already Requested -->
            <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-200 text-center">
                <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Certificate Request Submitted</h2>
                <p class="text-gray-600 mb-4">Your certificate request is currently <strong class="text-yellow-600"><?php echo $existingRequest['status']; ?></strong></p>
                <div class="bg-gray-50 rounded-xl p-4 text-left max-w-md mx-auto">
                    <p class="text-sm text-gray-600"><strong>Submitted on:</strong> <?php echo date('F j, Y', strtotime($existingRequest['request_date'])); ?></p>
                    <?php if ($existingRequest['status'] === 'approved'): ?>
                        <p class="text-sm text-green-600 font-semibold mt-2">✅ Your certificate has been approved!</p>
                    <?php elseif ($existingRequest['status'] === 'rejected'): ?>
                        <p class="text-sm text-red-600 font-semibold mt-2">❌ Request rejected</p>
                    <?php else: ?>
                        <p class="text-sm text-yellow-600 font-semibold mt-2">⏳ Awaiting admin review</p>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($completionPercentage < 100): ?>
            <!-- Not Completed -->
            <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-200 text-center">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Complete the Internship First</h2>
                <p class="text-gray-600 mb-6">You need to complete 100% of the internship before requesting a certificate.</p>
                <a href="/app/views/learner/internship-player.php?id=<?php echo $internshipId; ?>" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-semibold">
                    Continue Learning
                </a>
            </div>
            
        <?php else: ?>
            <!-- Certificate Request Form -->
            <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 border border-gray-200">
                
                <?php if (isset($error)): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Certificate Request Form</h2>
                    <p class="text-gray-600 text-sm">Fill in your details to request your internship certificate</p>
                </div>
                
                <form method="POST" class="space-y-6">
                    
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="student_name" required 
                               value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required 
                               value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    
                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number <span class="text-gray-400">(Optional)</span></label>
                        <input type="tel" name="phone" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    
                    <!-- LinkedIn -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">LinkedIn Profile URL <span class="text-gray-400">(Optional)</span></label>
                        <input type="url" name="linkedin_url" placeholder="https://www.linkedin.com/in/yourprofile"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    
                    <!-- Address -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Current Address <span class="text-gray-400">(Optional)</span></label>
                        <textarea name="address" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    
                    <!-- DOB & Gender -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Date of Birth <span class="text-gray-400">(Optional)</span></label>
                            <input type="date" name="dob"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Gender <span class="text-gray-400">(Optional)</span></label>
                            <select name="gender" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- College & Degree -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">College/University <span class="text-gray-400">(Optional)</span></label>
                            <input type="text" name="college_name"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Degree/Course <span class="text-gray-400">(Optional)</span></label>
                            <input type="text" name="degree" placeholder="e.g., B.Tech CSE"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Notes <span class="text-gray-400">(Optional)</span></label>
                        <textarea name="notes" rows="4" placeholder="Any special requests or information..."
                                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex gap-4 pt-4">
                        <a href="/app/views/learner/my-internships.php" 
                           class="flex-1 text-center px-6 py-4 border-2 border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="flex-1 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white px-6 py-4 rounded-xl font-bold shadow-lg transition-all">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
    </div>
</div>

</body>
</html>
