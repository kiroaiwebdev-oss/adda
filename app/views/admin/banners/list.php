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

// Load Banner model
require_once __DIR__ . '/../../../models/Banner.php';
$bannerModel = new Banner($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Banners - Admin</title>
    
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
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #16a34a; border-radius: 4px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">🎨 Promotional Banners</h1>
                <p class="text-gray-600 mt-1">Manage homepage banners and offers</p>
            </div>
            <button onclick="openModal()" class="bg-gradient-to-r from-primary-600 to-primary-700 text-white px-6 py-3 rounded-lg hover:shadow-lg transition-all font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Banner
            </button>
        </div>
    </header>

    <main class="p-8">
        <!-- Banners Grid -->
        <div id="banners-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Loaded via JavaScript -->
        </div>

        <!-- Loading State -->
        <div id="loading-state" class="text-center py-16">
            <div class="inline-block w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-4 text-gray-600 font-medium">Loading banners...</p>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden text-center py-16 bg-white rounded-xl border border-gray-200">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">No Banners Found</h3>
            <p class="text-gray-600 mb-6">Create your first promotional banner</p>
            <button onclick="openModal()" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 font-semibold inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add First Banner
            </button>
        </div>
    </main>
</div>

<!-- Modal -->
<div id="banner-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white z-10">
            <h2 id="modal-title" class="text-2xl font-bold text-gray-900">Add New Banner</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="banner-form" class="p-6 space-y-6">
            <input type="hidden" id="banner-id">

            <!-- Image Upload -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Banner Image <span class="text-red-500">*</span>
                </label>
                <div id="preview-container" class="hidden mb-4 relative">
                    <img id="image-preview" class="w-full h-64 object-cover rounded-lg border-2 border-gray-200">
                    <button type="button" onclick="removeImage()" class="absolute top-3 right-3 bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 shadow-lg font-semibold">
                        Remove
                    </button>
                </div>
                <div id="upload-area" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary-500 hover:bg-primary-50/50 transition-all cursor-pointer">
                    <input type="file" id="banner-image" accept="image/*" class="hidden" onchange="previewImage(event)">
                    <label for="banner-image" class="cursor-pointer block">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="text-gray-700 font-semibold text-base mb-2">Click to upload banner image</p>
                        <p class="text-sm text-gray-500">PNG, JPG, WEBP (Max 5MB)</p>
                        <p class="text-xs text-gray-400 mt-1">Recommended: 1920x600px</p>
                    </label>
                </div>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Banner Title</label>
                <input type="text" id="banner-title" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g., Get 50% OFF - Use Code SAVE50">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea id="banner-description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none" placeholder="Limited time offer on all courses"></textarea>
            </div>

            <!-- Link URL -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Link URL (Optional)</label>
                <input type="url" id="banner-link" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="https://internshipadda.com/courses">
            </div>

            <!-- Display Order -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Display Order</label>
                <input type="number" id="banner-order" value="0" min="0" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <p class="text-xs text-gray-500 mt-1">Lower numbers appear first in slider</p>
            </div>

            <!-- Active Status -->
            <div class="flex items-center gap-3 bg-gray-50 p-4 rounded-lg">
                <input type="checkbox" id="banner-active" checked class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                <label for="banner-active" class="text-sm font-medium text-gray-700">Active (Show on homepage)</label>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="submit" class="flex-1 bg-gradient-to-r from-primary-600 to-primary-700 text-white px-6 py-3 rounded-lg hover:shadow-lg transition-all font-semibold">
                    Save Banner
                </button>
                <button type="button" onclick="closeModal()" class="px-6 py-3 border-2 border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="hidden fixed top-4 right-4 z-[60] bg-white rounded-lg shadow-2xl p-4 min-w-[300px] border-l-4"></div>

<script>
let banners = [];
let editId = null;

// Load banners
async function loadBanners() {
    try {
        const res = await fetch('/api/banners.php?admin=1');
        const data = await res.json();

        if (data.success && Array.isArray(data.banners)) {
            banners = data.banners;
            renderBanners();
        } else {
            showToast('Failed to load banners', 'error');
        }
    } catch (e) {
        console.error('Load error:', e);
        showToast('Error loading banners', 'error');
    } finally {
        document.getElementById('loading-state').style.display = 'none';
    }
}

// Render banners
function renderBanners() {
    const grid = document.getElementById('banners-grid');
    const empty = document.getElementById('empty-state');

    if (banners.length === 0) {
        grid.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }

    empty.classList.add('hidden');
    grid.innerHTML = banners.map(b => `
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
            <div class="relative h-48 bg-gray-100">
                <img src="/${b.image_path}" alt="${escHtml(b.title || 'Banner')}" class="w-full h-full object-cover" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 200%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22200%22/%3E%3Ctext fill=%22%23999%22 font-size=%2220%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22%3EImage Not Found%3C/text%3E%3C/svg%3E'">
                <span class="absolute top-3 left-3 px-3 py-1 rounded-full text-xs font-bold shadow-lg ${b.is_active == 1 ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'}">
                    ${b.is_active == 1 ? '✓ Active' : '✕ Inactive'}
                </span>
                <span class="absolute top-3 right-3 bg-black/70 text-white px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm">
                    Order: ${b.display_order}
                </span>
            </div>
            <div class="p-4">
                <h3 class="font-bold text-lg text-gray-900 mb-1 truncate">${escHtml(b.title || 'Untitled Banner')}</h3>
                <p class="text-sm text-gray-600 mb-3 line-clamp-2 min-h-[40px]">${escHtml(b.description || 'No description')}</p>
                
                <div class="flex gap-2">
                    <button onclick="editBanner(${b.id})" class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 transition-colors text-sm font-semibold">
                        Edit
                    </button>
                    <button onclick="toggleStatus(${b.id})" class="flex-1 ${b.is_active == 1 ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-500 hover:bg-green-600'} text-white px-3 py-2 rounded-lg transition-colors text-sm font-semibold">
                        ${b.is_active == 1 ? 'Hide' : 'Show'}
                    </button>
                    <button onclick="deleteBanner(${b.id})" class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 transition-colors text-sm font-semibold">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function escHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Modal functions
function openModal() {
    editId = null;
    document.getElementById('banner-form').reset();
    document.getElementById('preview-container').classList.add('hidden');
    document.getElementById('upload-area').classList.remove('hidden');
    document.getElementById('modal-title').textContent = 'Add New Banner';
    document.getElementById('banner-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('banner-modal').classList.add('hidden');
}

function previewImage(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            showToast('Image must be less than 5MB', 'error');
            e.target.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            document.getElementById('image-preview').src = ev.target.result;
            document.getElementById('preview-container').classList.remove('hidden');
            document.getElementById('upload-area').classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }
}

function removeImage() {
    document.getElementById('banner-image').value = '';
    document.getElementById('preview-container').classList.add('hidden');
    document.getElementById('upload-area').classList.remove('hidden');
}

function editBanner(id) {
    const b = banners.find(x => x.id === id);
    if (!b) return;

    editId = id;
    document.getElementById('banner-id').value = id;
    document.getElementById('banner-title').value = b.title || '';
    document.getElementById('banner-description').value = b.description || '';
    document.getElementById('banner-link').value = b.link_url || '';
    document.getElementById('banner-order').value = b.display_order;
    document.getElementById('banner-active').checked = b.is_active == 1;

    document.getElementById('image-preview').src = '/' + b.image_path;
    document.getElementById('preview-container').classList.remove('hidden');
    document.getElementById('upload-area').classList.add('hidden');

    document.getElementById('modal-title').textContent = 'Edit Banner';
    document.getElementById('banner-modal').classList.remove('hidden');
}

// Form submit
document.getElementById('banner-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData();
    const file = document.getElementById('banner-image').files[0];

    if (file) {
        fd.append('banner_image', file);
    } else if (!editId) {
        showToast('Please select an image', 'error');
        return;
    }

    fd.append('title', document.getElementById('banner-title').value);
    fd.append('description', document.getElementById('banner-description').value);
    fd.append('link_url', document.getElementById('banner-link').value);
    fd.append('display_order', document.getElementById('banner-order').value);
    fd.append('is_active', document.getElementById('banner-active').checked ? 1 : 0);
    fd.append('action', editId ? 'update' : 'create');
    if (editId) fd.append('id', editId);

    try {
        const res = await fetch('/api/banners.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Banner saved successfully!', 'success');
            closeModal();
            loadBanners();
        } else {
            showToast(data.message || 'Failed to save banner', 'error');
        }
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
});

async function toggleStatus(id) {
    try {
        const res = await fetch('/api/banners.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'toggle', id})
        });
        const data = await res.json();
        if (data.success) {
            showToast('Status updated', 'success');
            loadBanners();
        }
    } catch (e) {
        showToast('Failed to update', 'error');
    }
}

async function deleteBanner(id) {
    if (!confirm('Are you sure you want to delete this banner?')) return;

    try {
        const res = await fetch('/api/banners.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete', id})
        });
        const data = await res.json();
        if (data.success) {
            showToast('Banner deleted', 'success');
            loadBanners();
        }
    } catch (e) {
        showToast('Failed to delete', 'error');
    }
}

function showToast(msg, type = 'info') {
    const toast = document.getElementById('toast');
    const colors = {
        success: 'border-green-500 text-green-800',
        error: 'border-red-500 text-red-800',
        info: 'border-blue-500 text-blue-800'
    };
    
    toast.className = `fixed top-4 right-4 z-[60] bg-white rounded-lg shadow-2xl p-4 min-w-[300px] border-l-4 ${colors[type]}`;
    toast.innerHTML = `<div class="font-semibold">${msg}</div>`;
    toast.classList.remove('hidden');
    
    setTimeout(() => toast.classList.add('hidden'), 3000);
}

// Init
document.addEventListener('DOMContentLoaded', loadBanners);
</script>

</body>
</html>
