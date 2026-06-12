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

// Handle success/error messages
$successMsg = isset($_GET['success']) ? '✅ Coupon created successfully!' : '';
$errorMsg = isset($_GET['error']) ? '❌ ' . urldecode($_GET['error']) : '';

// Get all coupons with usage count
$stmt = $db->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = c.id) as actual_used_count
    FROM coupons c 
    ORDER BY c.created_at DESC
");
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

function safeText($text) {
    return htmlspecialchars_decode($text, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupon Management</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
        body { font-family: 'Inter', sans-serif; background: #fafafa; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; font-weight: 700; }
        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
        }
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        .modal { display: none; }
        .modal.active { display: flex; }
        .dropdown-menu { display: none; }
        .dropdown-menu.active { display: block; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../../views/components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">🎟️ Coupon Management</h1>
                    <p class="text-gray-600 mt-1">Create and manage discount coupons</p>
                </div>
                <div class="flex gap-3">
                    <a href="tracking.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Track Usage
                    </a>
                    <button onclick="openCreateModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        + Create Coupon
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <?php if ($successMsg): ?>
            <div class="bg-green-50 text-green-700 px-6 py-4 rounded-lg border-2 border-green-200 font-semibold mb-6">
                <?php echo $successMsg; ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="bg-red-50 text-red-700 px-6 py-4 rounded-lg border-2 border-red-200 font-semibold mb-6">
                <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($coupons)): ?>
            <div class="bg-white rounded-xl border-2 border-dashed border-gray-300 p-12 text-center">
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Coupons Yet</h3>
                <p class="text-gray-600 mb-6">Create your first coupon to offer discounts</p>
                <button onclick="openCreateModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold">
                    Create First Coupon
                </button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($coupons as $coupon): ?>
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6 hover:border-primary-500 transition-all relative">
                        <!-- Three Dot Menu -->
                        <div class="absolute top-4 right-4">
                            <button onclick="toggleDropdown(<?php echo $coupon['id']; ?>)" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="dropdown-<?php echo $coupon['id']; ?>" class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-50">
                                <div class="py-1">
                                    <button onclick="viewCoupon(<?php echo $coupon['id']; ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        View Details
                                    </button>
                                    <button onclick="editCoupon(<?php echo $coupon['id']; ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit Coupon
                                    </button>
                                    <a href="tracking.php?coupon_id=<?php echo $coupon['id']; ?>" class="w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        Track Usage
                                    </a>
                                    <hr class="my-1">
                                    <form method="POST" action="delete.php" onsubmit="return confirm('Delete coupon <?php echo $coupon['code']; ?>?')" class="w-full">
                                        <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Delete Coupon
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-4 pr-8">
                            <span class="text-2xl font-bold text-primary-600"><?php echo safeText($coupon['code']); ?></span>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $coupon['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                                <?php echo $coupon['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <div class="space-y-2 text-sm mb-4">
                            <p class="text-gray-600">
                                <span class="font-semibold">Discount:</span> 
                                <?php 
                                if ($coupon['discount_type'] === 'percentage') {
                                    echo $coupon['discount_value'] . '%';
                                } else {
                                    echo '₹' . number_format($coupon['discount_value'], 2);
                                }
                                ?>
                            </p>
                            <?php if ($coupon['min_purchase'] > 0): ?>
                                <p class="text-gray-600">
                                    <span class="font-semibold">Min Purchase:</span> ₹<?php echo number_format($coupon['min_purchase'], 2); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($coupon['max_discount']): ?>
                                <p class="text-gray-600">
                                    <span class="font-semibold">Max Discount:</span> ₹<?php echo number_format($coupon['max_discount'], 2); ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-gray-600">
                                <span class="font-semibold">Used:</span> <?php echo $coupon['actual_used_count']; ?>
                                <?php if ($coupon['usage_limit']): ?>
                                    / <?php echo $coupon['usage_limit']; ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($coupon['valid_until']): ?>
                                <p class="text-gray-600">
                                    <span class="font-semibold">Valid Until:</span> <?php echo date('M j, Y', strtotime($coupon['valid_until'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="flex gap-2">
                            <form method="POST" action="toggle-status.php" class="flex-1">
                                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo $coupon['is_active'] ? 0 : 1; ?>">
                                <button type="submit" class="w-full text-sm px-4 py-2 rounded-lg <?php echo $coupon['is_active'] ? 'bg-gray-100 hover:bg-gray-200 text-gray-700' : 'bg-primary-100 hover:bg-primary-200 text-primary-700'; ?> font-semibold">
                                    <?php echo $coupon['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Hidden Data for Modals -->
                        <div id="coupon-data-<?php echo $coupon['id']; ?>" style="display:none;">
                            <?php echo json_encode($coupon); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Create Coupon Modal -->
<div id="createModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-8 m-4">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Create New Coupon</h2>

        <form method="POST" action="create.php" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Coupon Code *</label>
                <input type="text" name="code" class="form-input uppercase" placeholder="e.g., SAVE20" required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Type *</label>
                    <select name="discount_type" class="form-select" onchange="toggleDiscountType(this.value)">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₹)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Value *</label>
                    <input type="number" name="discount_value" class="form-input" min="0" step="0.01" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Min Purchase (₹)</label>
                    <input type="number" name="min_purchase" class="form-input" min="0" step="0.01" placeholder="0">
                </div>
                <div id="maxDiscountField">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Max Discount (₹)</label>
                    <input type="number" name="max_discount" class="form-input" min="0" step="0.01" placeholder="No limit">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Usage Limit</label>
                    <input type="number" name="usage_limit" class="form-input" min="0" placeholder="Unlimited">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Valid Until</label>
                    <input type="datetime-local" name="valid_until" class="form-input">
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold">
                    Create Coupon
                </button>
                <button type="button" onclick="closeCreateModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Coupon Modal -->
<div id="viewModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
    <div class="bg-white rounded-xl max-w-lg w-full p-8 m-4">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Coupon Details</h2>
        <div id="viewModalContent" class="space-y-3"></div>
        <button onclick="closeViewModal()" class="w-full mt-6 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold">
            Close
        </button>
    </div>
</div>

<!-- Edit Coupon Modal -->
<div id="editModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-8 m-4">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Edit Coupon</h2>
        <form method="POST" action="edit.php" class="space-y-4" id="editForm">
            <input type="hidden" name="coupon_id" id="edit_coupon_id">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Coupon Code *</label>
                <input type="text" name="code" id="edit_code" class="form-input uppercase" required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Type *</label>
                    <select name="discount_type" id="edit_discount_type" class="form-select" onchange="toggleEditDiscountType(this.value)">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₹)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Value *</label>
                    <input type="number" name="discount_value" id="edit_discount_value" class="form-input" min="0" step="0.01" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Min Purchase (₹)</label>
                    <input type="number" name="min_purchase" id="edit_min_purchase" class="form-input" min="0" step="0.01">
                </div>
                <div id="editMaxDiscountField">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Max Discount (₹)</label>
                    <input type="number" name="max_discount" id="edit_max_discount" class="form-input" min="0" step="0.01">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Usage Limit</label>
                    <input type="number" name="usage_limit" id="edit_usage_limit" class="form-input" min="0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Valid Until</label>
                    <input type="datetime-local" name="valid_until" id="edit_valid_until" class="form-input">
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold">
                    Update Coupon
                </button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Create Modal
    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
    }

    function toggleDiscountType(type) {
        const maxField = document.getElementById('maxDiscountField');
        maxField.style.display = type === 'percentage' ? 'block' : 'none';
    }

    // Dropdown Menu
    function toggleDropdown(id) {
        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu.id !== `dropdown-${id}`) {
                menu.classList.remove('active');
            }
        });
        
        const dropdown = document.getElementById(`dropdown-${id}`);
        dropdown.classList.toggle('active');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('button') && !event.target.closest('.dropdown-menu')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('active');
            });
        }
    });

    // View Modal
    function viewCoupon(id) {
        const data = JSON.parse(document.getElementById(`coupon-data-${id}`).textContent);
        const content = document.getElementById('viewModalContent');
        
        content.innerHTML = `
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Coupon Code</p>
                <p class="text-xl font-bold text-primary-600">${data.code}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-sm text-gray-600">Discount Type</p>
                    <p class="font-semibold">${data.discount_type === 'percentage' ? 'Percentage' : 'Fixed Amount'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Discount Value</p>
                    <p class="font-semibold">${data.discount_type === 'percentage' ? data.discount_value + '%' : '₹' + parseFloat(data.discount_value).toFixed(2)}</p>
                </div>
            </div>
            ${data.min_purchase > 0 ? `
            <div>
                <p class="text-sm text-gray-600">Minimum Purchase</p>
                <p class="font-semibold">₹${parseFloat(data.min_purchase).toFixed(2)}</p>
            </div>` : ''}
            ${data.max_discount ? `
            <div>
                <p class="text-sm text-gray-600">Maximum Discount</p>
                <p class="font-semibold">₹${parseFloat(data.max_discount).toFixed(2)}</p>
            </div>` : ''}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-sm text-gray-600">Usage Limit</p>
                    <p class="font-semibold">${data.usage_limit || 'Unlimited'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Times Used</p>
                    <p class="font-semibold">${data.actual_used_count || 0}</p>
                </div>
            </div>
            ${data.valid_until ? `
            <div>
                <p class="text-sm text-gray-600">Valid Until</p>
                <p class="font-semibold">${new Date(data.valid_until).toLocaleString()}</p>
            </div>` : ''}
            <div>
                <p class="text-sm text-gray-600">Status</p>
                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold ${data.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}">
                    ${data.is_active ? 'Active' : 'Inactive'}
                </span>
            </div>
        `;
        
        document.getElementById('viewModal').classList.add('active');
        toggleDropdown(id); // Close dropdown
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
    }

    // Edit Modal
    function editCoupon(id) {
        const data = JSON.parse(document.getElementById(`coupon-data-${id}`).textContent);
        
        document.getElementById('edit_coupon_id').value = data.id;
        document.getElementById('edit_code').value = data.code;
        document.getElementById('edit_discount_type').value = data.discount_type;
        document.getElementById('edit_discount_value').value = data.discount_value;
        document.getElementById('edit_min_purchase').value = data.min_purchase || '';
        document.getElementById('edit_max_discount').value = data.max_discount || '';
        document.getElementById('edit_usage_limit').value = data.usage_limit || '';
        
        if (data.valid_until) {
            const date = new Date(data.valid_until);
            const formatted = date.toISOString().slice(0, 16);
            document.getElementById('edit_valid_until').value = formatted;
        } else {
            document.getElementById('edit_valid_until').value = '';
        }
        
        toggleEditDiscountType(data.discount_type);
        document.getElementById('editModal').classList.add('active');
        toggleDropdown(id); // Close dropdown
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    function toggleEditDiscountType(type) {
        const maxField = document.getElementById('editMaxDiscountField');
        maxField.style.display = type === 'percentage' ? 'block' : 'none';
    }
</script>

</body>
</html>
