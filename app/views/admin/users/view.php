<?php
// ✅ ENABLE ERROR DISPLAY
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    require_once __DIR__ . '/../../../config/database.php';

    $dbConfig = require __DIR__ . '/../../../config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
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

try {
    $auth = new Auth($db);
    $auth->requireAdmin();
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    header('Location: /app/views/admin/users/list.php');
    exit;
}

// ✅ FETCH USER DIRECTLY (NO MODEL DEPENDENCY)
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'learner'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: /app/views/admin/users/list.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error fetching user: " . $e->getMessage());
}

// ✅ GET ENROLLMENTS (WITHOUT MODEL)
try {
    $stmt = $db->prepare("
        SELECT 
            e.*, 
            c.title,
            c.id as course_id
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$userId]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching enrollments: " . $e->getMessage());
    $enrollments = [];
}

// ✅ GET ORDERS (WITHOUT MODEL)
try {
    $stmt = $db->prepare("
        SELECT 
            o.*,
            c.title as course_title
        FROM orders o
        LEFT JOIN courses c ON o.course_id = c.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
}

// ✅ GET CERTIFICATES
try {
    $stmt = $db->prepare("
        SELECT c.*, co.title as course_title 
        FROM certificates c 
        JOIN courses co ON c.course_id = co.id 
        WHERE c.user_id = ? 
        ORDER BY c.issued_at DESC
    ");
    $stmt->execute([$userId]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching certificates: " . $e->getMessage());
    $certificates = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - <?php echo htmlspecialchars($user['name']); ?></title>
    
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
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                        <a href="/app/views/admin/users/list.php" class="hover:text-primary-600">Users</a>
                        <span>/</span>
                        <span>View User</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></h1>
                </div>
                <a href="/app/views/admin/users/edit.php?id=<?php echo $user['id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
                    Edit User
                </a>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- User Info Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="text-center">
                        <div class="w-24 h-24 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-bold text-3xl mx-auto mb-4">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></h3>
                        <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($user['email']); ?></p>
                        
                        <div class="mt-4">
                            <span class="px-4 py-2 text-sm font-semibold rounded-full <?php 
                                echo $user['status'] === 'active' ? 'bg-green-100 text-green-700' : 
                                    ($user['status'] === 'suspended' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                            ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200 space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">User ID</span>
                            <span class="text-sm font-semibold text-gray-900">#<?php echo $user['id']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Joined</span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Last Login</span>
                            <span class="text-sm font-semibold text-gray-900">
                                <?php echo isset($user['last_login']) && $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Email Verified</span>
                            <span class="text-sm font-semibold <?php echo isset($user['email_verified']) && $user['email_verified'] ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo isset($user['email_verified']) && $user['email_verified'] ? 'Yes' : 'No'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200 space-y-2">
                        <button onclick="changeStatus('suspended')" class="w-full bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-4 py-2 rounded-lg font-semibold transition-colors">
                            Suspend User
                        </button>
                        <button onclick="changeStatus('banned')" class="w-full bg-red-100 hover:bg-red-200 text-red-700 px-4 py-2 rounded-lg font-semibold transition-colors">
                            Ban User
                        </button>
                        <button onclick="if(confirm('Delete this user permanently?')) deleteUser()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-semibold transition-colors">
                            Delete User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Activity Section -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Enrollments -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">Course Enrollments (<?php echo count($enrollments); ?>)</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($enrollments)): ?>
                            <p class="text-gray-500 text-center py-8">No enrollments yet</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-300 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($enrollment['title']); ?></h4>
                                                <p class="text-sm text-gray-600 mt-1">Enrolled: <?php echo date('M j, Y', strtotime($enrollment['enrolled_at'])); ?></p>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-2xl font-bold text-primary-600"><?php echo number_format($enrollment['progress_percent'] ?? 0, 1); ?>%</div>
                                                <p class="text-xs text-gray-500 mt-1">Progress</p>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-2 rounded-full" style="width: <?php echo $enrollment['progress_percent'] ?? 0; ?>%"></div>
                                            </div>
                                        </div>
                                        <?php if (isset($enrollment['completed_at']) && $enrollment['completed_at']): ?>
                                            <div class="mt-3 flex items-center gap-2 text-sm text-green-600">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span>Completed on <?php echo date('M j, Y', strtotime($enrollment['completed_at'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Orders -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">Purchase History (<?php echo count($orders); ?>)</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($orders)): ?>
                            <p class="text-gray-500 text-center py-8">No purchases yet</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($orders as $order): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($order['course_title'] ?? 'N/A'); ?></h4>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-gray-900">₹<?php echo number_format($order['amount'] ?? 0, 2); ?></div>
                                            <span class="text-xs font-semibold px-2 py-1 rounded-full <?php 
                                                echo $order['status'] === 'completed' ? 'bg-green-100 text-green-700' : 
                                                    ($order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Certificates -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">Certificates (<?php echo count($certificates); ?>)</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($certificates)): ?>
                            <p class="text-gray-500 text-center py-8">No certificates issued yet</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($certificates as $cert): ?>
                                    <div class="flex items-center justify-between p-4 border border-primary-200 bg-primary-50 rounded-lg">
                                        <div>
                                            <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($cert['course_title']); ?></h4>
                                            <p class="text-sm text-gray-600 mt-1">
                                                Code: <span class="font-mono font-semibold"><?php echo $cert['certificate_code']; ?></span>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Issued: <?php echo date('M j, Y', strtotime($cert['issued_at'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <?php if (isset($cert['revoked']) && $cert['revoked']): ?>
                                                <span class="px-3 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded-full">Revoked</span>
                                            <?php else: ?>
                                                <a href="/verify.php?code=<?php echo $cert['certificate_code']; ?>" target="_blank" class="text-primary-600 hover:text-primary-700 font-semibold text-sm">
                                                    View Certificate →
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function changeStatus(status) {
        if (!confirm(`Change user status to ${status}?`)) return;
        
        fetch('/api/users.php?action=update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: <?php echo $user['id']; ?>,
                status: status
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to update status');
            }
        });
    }

    function deleteUser() {
        fetch('/api/users.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: <?php echo $user['id']; ?> })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/app/views/admin/users/list.php';
            } else {
                alert(data.error || 'Failed to delete user');
            }
        });
    }
</script>

</body>
</html>
