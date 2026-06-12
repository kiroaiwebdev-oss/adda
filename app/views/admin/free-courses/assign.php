<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Correct path to database config
require_once __DIR__ . '/../../../config/database.php';
$dbConfig = require __DIR__ . '/../../../config/database.php';

$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], 
    $dbConfig['port'], 
    $dbConfig['database'], 
    $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../../core/' . $class . '.php',
        __DIR__ . '/../../../models/' . $class . '.php',
        __DIR__ . '/../../../controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
$auth->requireAdmin();

// Get all active learners
$usersStmt = $db->prepare("SELECT id, name, email, mobile FROM users WHERE role = 'learner' AND status = 'active' ORDER BY name ASC");
$usersStmt->execute();
$users = $usersStmt->fetchAll();

// Get all published courses (WITHOUT thumbnail column)
$coursesStmt = $db->prepare("SELECT id, title, price FROM courses WHERE status = 'published' ORDER BY title ASC");
$coursesStmt->execute();
$courses = $coursesStmt->fetchAll();

// Get recent enrollments (fixed: enrolled_at instead of created_at, removed payment_status filter)
$recentStmt = $db->prepare("
    SELECT e.*, u.name as user_name, u.email, c.title as course_title 
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY e.enrolled_at DESC 
    LIMIT 15
");
$recentStmt->execute();
$recentEnrollments = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Free Course - Admin</title>
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
            background: #fafafa;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <h1 class="text-3xl font-bold text-gray-900">Offer Free Course</h1>
            <p class="text-gray-600 mt-1">Assign courses to users without payment</p>
        </div>
    </header>

    <main class="p-8">
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="font-semibold">Course successfully assigned for FREE! 🎉</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="font-semibold"><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Assign Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-200 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Assign Free Course</h2>
                            <p class="text-gray-500 text-sm">Give free access to any learner</p>
                        </div>
                    </div>
                    
                    <form action="process.php" method="POST" id="assignForm" onsubmit="return confirmAssignment()">
                        <!-- Select User -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Select Learner *
                                <span class="text-gray-400 font-normal">(Active users only)</span>
                            </label>
                            <select name="user_id" id="userSelect" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" onchange="updateUserInfo()">
                                <option value="">Choose a learner...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-mobile="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> - <?php echo htmlspecialchars($user['email']); ?>
                                        <?php if (!empty($user['mobile'])): ?>
                                            (<?php echo htmlspecialchars($user['mobile']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <!-- Selected User Info -->
                            <div id="userInfo" class="hidden mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm font-semibold text-blue-900" id="userName"></p>
                                <p class="text-xs text-blue-700" id="userEmail"></p>
                                <p class="text-xs text-blue-700" id="userMobile"></p>
                            </div>
                        </div>

                        <!-- Select Course -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Select Course *
                                <span class="text-gray-400 font-normal">(Published courses only)</span>
                            </label>
                            <select name="course_id" id="courseSelect" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" onchange="updateCourseInfo()">
                                <option value="">Choose a course...</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($course['title']); ?>"
                                            data-price="<?php echo htmlspecialchars($course['price']); ?>">
                                        <?php echo htmlspecialchars($course['title']); ?> - ₹<?php echo number_format($course['price'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <!-- Selected Course Info -->
                            <div id="courseInfo" class="hidden mt-3 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                                <p class="text-sm font-semibold text-purple-900" id="courseTitle"></p>
                                <p class="text-xs text-purple-700">Original Price: <span class="font-bold" id="coursePrice"></span></p>
                                <p class="text-xs text-green-700 font-bold">Free for this user! 🎁</p>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center gap-4">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white px-8 py-4 rounded-lg font-bold text-lg transition-all shadow-lg hover:shadow-xl">
                                <span class="flex items-center justify-center gap-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                                    </svg>
                                    Assign Course for FREE
                                </span>
                            </button>
                            <button type="reset" class="px-6 py-4 border border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition-colors" onclick="resetForm()">
                                Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stats & Recent Enrollments -->
            <div class="space-y-6">
                <!-- Stats Card -->
                <div class="bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl p-6 text-white">
                    <div class="flex items-center gap-3 mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                        <h3 class="text-lg font-bold">Quick Stats</h3>
                    </div>
                    <div class="space-y-2">
                        <p class="text-3xl font-bold"><?php echo count($users); ?></p>
                        <p class="text-primary-100">Active Learners</p>
                        <hr class="border-primary-400 my-3">
                        <p class="text-2xl font-bold"><?php echo count($courses); ?></p>
                        <p class="text-primary-100">Available Courses</p>
                        <hr class="border-primary-400 my-3">
                        <p class="text-2xl font-bold"><?php echo count($recentEnrollments); ?></p>
                        <p class="text-primary-100">Recent Enrollments</p>
                    </div>
                </div>

                <!-- Recent Enrollments -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Recent Enrollments</h3>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php if (empty($recentEnrollments)): ?>
                            <p class="text-center text-gray-400 text-sm py-8">No enrollments yet</p>
                        <?php else: ?>
                            <?php foreach ($recentEnrollments as $enrollment): ?>
                                <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-start gap-2">
                                        <div class="w-8 h-8 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">
                                            <?php echo strtoupper(substr($enrollment['user_name'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($enrollment['user_name']); ?></p>
                                            <p class="text-xs text-gray-600 truncate"><?php echo htmlspecialchars($enrollment['course_title']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo date('M j, Y', strtotime($enrollment['enrolled_at'])); ?></p>
                                        </div>
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-semibold">✓</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function updateUserInfo() {
    const select = document.getElementById('userSelect');
    const option = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('userInfo');
    
    if (option.value) {
        document.getElementById('userName').textContent = option.dataset.name;
        document.getElementById('userEmail').textContent = '📧 ' + option.dataset.email;
        document.getElementById('userMobile').textContent = option.dataset.mobile ? '📱 ' + option.dataset.mobile : '📱 Not provided';
        infoDiv.classList.remove('hidden');
    } else {
        infoDiv.classList.add('hidden');
    }
}

function updateCourseInfo() {
    const select = document.getElementById('courseSelect');
    const option = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('courseInfo');
    
    if (option.value) {
        document.getElementById('courseTitle').textContent = option.dataset.title;
        document.getElementById('coursePrice').textContent = '₹' + parseFloat(option.dataset.price).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        infoDiv.classList.remove('hidden');
    } else {
        infoDiv.classList.add('hidden');
    }
}

function confirmAssignment() {
    const userSelect = document.getElementById('userSelect');
    const courseSelect = document.getElementById('courseSelect');
    
    if (!userSelect.value || !courseSelect.value) {
        alert('Please select both user and course!');
        return false;
    }
    
    const userName = userSelect.options[userSelect.selectedIndex].dataset.name;
    const courseTitle = courseSelect.options[courseSelect.selectedIndex].dataset.title;
    
    return confirm(`Are you sure you want to assign "${courseTitle}" to "${userName}" for FREE?`);
}

function resetForm() {
    document.getElementById('userInfo').classList.add('hidden');
    document.getElementById('courseInfo').classList.add('hidden');
}
</script>

</body>
</html>
