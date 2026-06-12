<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Database connection
$dbConfig = require __DIR__ . '/../../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Autoloader
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

// Authentication
$auth = new Auth($db);
$auth->requireAdmin();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM offline_internship_applications WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR internship_type LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats - FIXED: Count only applications that don't have is_contacted OR is_enrolled set
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_contacted = 0 AND is_enrolled = 0 THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN is_contacted = 1 THEN 1 ELSE 0 END) as contacted,
        SUM(CASE WHEN is_enrolled = 1 THEN 1 ELSE 0 END) as enrolled
    FROM offline_internship_applications
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline Internship Applications - Admin Panel</title>
    
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
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Sidebar -->
<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<!-- Main Content -->
<div class="ml-64 min-h-screen">
    
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">📋 Offline Internship Applications</h1>
                    <p class="text-gray-600 mt-1">Manage and track offline internship applications</p>
                </div>
                <a href="/public/offline-internship.php" target="_blank" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    View Application Form
                </a>
            </div>
        </div>
    </header>

    <main class="p-8">
        
        <!-- Alert Container -->
        <div id="alertContainer" class="mb-6"></div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></h3>
                <p class="text-sm text-gray-600">Total Applications</p>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo $stats['pending']; ?></h3>
                <p class="text-sm text-gray-600">Pending Review</p>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo $stats['contacted']; ?></h3>
                <p class="text-sm text-gray-600">Contacted</p>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo $stats['enrolled']; ?></h3>
                <p class="text-sm text-gray-600">Enrolled</p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search by name, email, phone..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                    >
                </div>
                
                <select 
                    name="status" 
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                >
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="contacted" <?php echo $status_filter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="enrolled" <?php echo $status_filter === 'enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-all">
                    Filter
                </button>
                
                <a href="?" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg font-semibold transition-all">
                    Reset
                </a>
            </form>
        </div>
        
        <!-- Applications Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Applicant</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Education</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Internship</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-lg font-semibold">No applications found</p>
                                    <p class="text-sm">Applications will appear here once students apply</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center text-primary-600 font-bold">
                                                <?php echo strtoupper(substr($app['name'], 0, 1)); ?>
                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($app['name']); ?></p>
                                                <p class="text-sm text-gray-500">Applied: <?php echo date('d M Y', strtotime($app['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($app['email']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($app['phone']); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($app['college'] ?: 'N/A'); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($app['course'] ?: 'N/A'); ?> - <?php echo htmlspecialchars($app['year'] ?: 'N/A'); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($app['internship_type']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($app['duration'] ?: 'Not specified'); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <?php if ($app['is_contacted']): ?>
                                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-700">
                                                    ✓ Contacted
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($app['is_enrolled']): ?>
                                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                                    ✓ Enrolled
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!$app['is_contacted'] && !$app['is_enrolled']): ?>
                                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-700">
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button 
                                            onclick="viewDetails(<?php echo $app['id']; ?>)" 
                                            class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all"
                                        >
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </main>
</div>

<!-- View Details Modal -->
<div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Application Details</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div id="modalContent" class="p-6">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<script>
let currentApplicationId = null;

async function viewDetails(id) {
    currentApplicationId = id;
    
    try {
        const response = await fetch(`/api/offline-internship.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const app = result.data;
            
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <!-- Personal Info -->
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3">Personal Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Full Name</label>
                                <p class="text-gray-900">${app.name}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Email</label>
                                <p class="text-gray-900">${app.email}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Phone</label>
                                <p class="text-gray-900">${app.phone}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">College</label>
                                <p class="text-gray-900">${app.college || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Course</label>
                                <p class="text-gray-900">${app.course || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Year</label>
                                <p class="text-gray-900">${app.year || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Internship Details -->
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3">Internship Details</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Domain</label>
                                <p class="text-gray-900">${app.internship_type}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Duration</label>
                                <p class="text-gray-900">${app.duration || 'Not specified'}</p>
                            </div>
                        </div>
                        ${app.message ? `
                            <div class="mt-4">
                                <label class="text-sm font-semibold text-gray-600">Message</label>
                                <p class="text-gray-900 mt-1">${app.message}</p>
                            </div>
                        ` : ''}
                    </div>
                    
                    <!-- Status Management -->
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3">Status Management</h3>
                        
                        <div class="space-y-4">
                            <!-- Contacted Checkbox - ENGLISH LABEL -->
                            <label class="flex items-center gap-3 cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition">
                                <input 
                                    type="checkbox" 
                                    id="isContacted"
                                    ${app.is_contacted ? 'checked' : ''}
                                    onchange="updateStatus('is_contacted', this.checked)"
                                    class="w-5 h-5 text-primary-600 rounded focus:ring-primary-500"
                                >
                                <div>
                                    <span class="text-gray-900 font-semibold">✓ Contacted</span>
                                    <p class="text-sm text-gray-500">I have reached out to this applicant</p>
                                </div>
                            </label>
                            
                            <!-- Enrolled Checkbox - ENGLISH LABEL -->
                            <label class="flex items-center gap-3 cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition">
                                <input 
                                    type="checkbox" 
                                    id="isEnrolled"
                                    ${app.is_enrolled ? 'checked' : ''}
                                    onchange="updateStatus('is_enrolled', this.checked)"
                                    class="w-5 h-5 text-primary-600 rounded focus:ring-primary-500"
                                >
                                <div>
                                    <span class="text-gray-900 font-semibold">✓ Enrolled</span>
                                    <p class="text-sm text-gray-500">Student has joined the internship</p>
                                </div>
                            </label>
                            
                            <!-- Admin Notes -->
                            <div>
                                <label class="text-sm font-semibold text-gray-600 block mb-2">Admin Notes</label>
                                <textarea 
                                    id="adminNotes"
                                    rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                                    placeholder="Add notes about this application..."
                                >${app.notes || ''}</textarea>
                                <button 
                                    onclick="saveNotes()"
                                    class="mt-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-semibold"
                                >
                                    Save Notes
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timestamps -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-bold text-gray-600 mb-2">Timeline</h3>
                        <div class="space-y-1 text-sm">
                            <p><strong>Applied:</strong> ${new Date(app.created_at).toLocaleString()}</p>
                            ${app.contacted_at ? `<p><strong>Contacted:</strong> ${new Date(app.contacted_at).toLocaleString()}</p>` : ''}
                            ${app.enrolled_at ? `<p><strong>Enrolled:</strong> ${new Date(app.enrolled_at).toLocaleString()}</p>` : ''}
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex gap-3 pt-4 border-t">
                        <a href="mailto:${app.email}" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-semibold text-center">
                            Send Email
                        </a>
                        <a href="tel:${app.phone}" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-semibold text-center">
                            Call Now
                        </a>
                        <button 
                            onclick="deleteApplication(${app.id})"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-semibold"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('detailsModal').classList.remove('hidden');
        } else {
            showAlert('error', 'Failed to load application details');
        }
    } catch (error) {
        showAlert('error', 'Network error');
    }
}

function closeModal() {
    document.getElementById('detailsModal').classList.add('hidden');
    currentApplicationId = null;
}

async function updateStatus(field, value) {
    if (!currentApplicationId) return;
    
    try {
        const response = await fetch('/api/offline-internship.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentApplicationId,
                field: field,
                value: value
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', '✅ Updated successfully');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', '❌ Update failed');
        }
    } catch (error) {
        showAlert('error', '❌ Network error');
    }
}

async function saveNotes() {
    if (!currentApplicationId) return;
    
    const notes = document.getElementById('adminNotes').value;
    
    try {
        const response = await fetch('/api/offline-internship.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentApplicationId,
                field: 'notes',
                value: notes
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', '✅ Notes saved');
        } else {
            showAlert('error', '❌ Failed to save notes');
        }
    } catch (error) {
        showAlert('error', '❌ Network error');
    }
}

async function deleteApplication(id) {
    if (!confirm('⚠️ Are you sure you want to delete this application? This cannot be undone!')) return;
    
    try {
        const response = await fetch('/api/offline-internship.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', '✅ Application deleted');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', '❌ Delete failed');
        }
    } catch (error) {
        showAlert('error', '❌ Network error');
    }
}

function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const bgColor = type === 'success' ? 'bg-green-100 border-green-500 text-green-800' : 'bg-red-100 border-red-500 text-red-800';
    
    alertContainer.innerHTML = `
        <div class="${bgColor} border-l-4 p-4 rounded-lg shadow-md">
            <p class="font-semibold">${message}</p>
        </div>
    `;
    
    setTimeout(() => {
        alertContainer.innerHTML = '';
    }, 3000);
}

// Close modal on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>
