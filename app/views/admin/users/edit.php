<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$dbConfig = require __DIR__ . '/../../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

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

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    header('Location: /app/views/admin/users/list.php');
    exit;
}

$userModel = new User($db);
$user = $userModel->findById($userId);

if (!$user || $user['role'] !== 'learner') {
    header('Location: /app/views/admin/users/list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo htmlspecialchars($user['name']); ?></title>
    
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
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="/app/views/admin/users/list.php" class="hover:text-primary-600">Users</a>
                <span>/</span>
                <a href="/app/views/admin/users/view.php?id=<?php echo $user['id']; ?>" class="hover:text-primary-600"><?php echo htmlspecialchars($user['name']); ?></a>
                <span>/</span>
                <span>Edit</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Edit User</h1>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <div id="alertContainer" class="mb-6"></div>

                <form id="editUserForm" class="space-y-6">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-input" 
                            value="<?php echo htmlspecialchars($user['name']); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            value="<?php echo htmlspecialchars($user['email']); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Account Status</label>
                        <select id="status" name="status" class="form-input">
                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="banned" <?php echo $user['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                    </div>

                    <div class="pt-6 border-t border-gray-200 flex gap-4">
                        <button type="submit" id="saveBtn" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                            Save Changes
                        </button>
                        <a href="/app/views/admin/users/view.php?id=<?php echo $user['id']; ?>" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    const form = document.getElementById('editUserForm');
    const alertContainer = document.getElementById('alertContainer');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = {
            user_id: formData.get('user_id'),
            name: formData.get('name'),
            email: formData.get('email'),
            status: formData.get('status')
        };

        try {
            const response = await fetch('/api/users.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alertContainer.innerHTML = `<div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg border border-green-200">${result.message || 'User updated successfully'}</div>`;
                setTimeout(() => {
                    window.location.href = '/app/views/admin/users/view.php?id=' + data.user_id;
                }, 1500);
            } else {
                alertContainer.innerHTML = `<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200">${result.error || 'Failed to update user'}</div>`;
            }
        } catch (error) {
            alertContainer.innerHTML = '<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200">Network error</div>';
        }
    });
</script>

</body>
</html>
