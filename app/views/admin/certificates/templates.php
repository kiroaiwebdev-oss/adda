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

// ✅ Fetch templates directly
$stmt = $db->prepare("SELECT * FROM certificate_templates ORDER BY id ASC");
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Templates - Admin</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
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
        h1, h2, h3, h4 { font-family: 'Poppins', sans-serif; font-weight: 700; }
        
        /* ✅ Template Preview Scaling */
        .template-preview-container {
            width: 100%;
            height: 300px;
            overflow: hidden;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            background: #f9fafb;
            position: relative;
        }
        
        .template-preview-wrapper {
            transform: scale(0.267);
            transform-origin: top left;
            width: 1122px;
            height: 794px;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        /* Code Editor Styling */
        .code-editor {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .code-editor::-webkit-scrollbar {
            width: 8px;
        }
        
        .code-editor::-webkit-scrollbar-track {
            background: #0f172a;
        }
        
        .code-editor::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
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
                    <h1 class="text-3xl font-bold text-gray-900">📜 Certificate Templates</h1>
                    <p class="text-gray-600 mt-1">Manage certificate templates for internship completion</p>
                </div>
                <div class="bg-primary-50 px-6 py-3 rounded-lg border-2 border-primary-200">
                    <span class="text-sm text-gray-600 font-semibold">Total Templates:</span>
                    <span class="text-2xl font-bold text-primary-600 ml-2"><?php echo count($templates); ?></span>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <!-- Info Box -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-6 mb-8 shadow-md">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-blue-900 mb-3">📝 Template Variables Guide</h3>
                    <p class="text-sm text-blue-800 mb-4">Use these variables in your template HTML. They will be automatically replaced with actual certificate data:</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{student_name}}</code>
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{company_name}}</code>
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{internship_title}}</code>
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{start_date}}</code>
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{end_date}}</code>
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{duration_months}}</code>
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{certificate_code}}</code>
                        <code class="bg-white px-4 py-2 rounded-lg text-xs font-mono text-blue-700 shadow-sm border border-blue-200 text-center">{{issue_date}}</code>
                    </div>
                    <p class="text-xs text-blue-600 mt-3 font-semibold">💡 Tip: Templates are displayed in A4 Landscape format (1122px × 794px)</p>
                </div>
            </div>
        </div>

        <!-- Templates Grid -->
        <?php if (empty($templates)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <svg class="w-20 h-20 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-600 font-semibold text-lg">No templates found</p>
                <p class="text-gray-500 text-sm mt-2">Create your first certificate template</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                <?php foreach ($templates as $template): ?>
                    <div class="bg-white rounded-2xl border-2 <?php echo $template['is_active'] ? 'border-primary-300' : 'border-gray-200'; ?> overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-5 pb-5 border-b-2 border-gray-100">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center text-white font-bold text-2xl shadow-lg">
                                        <?php echo substr($template['company_name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($template['template_name']); ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">🏢 <?php echo htmlspecialchars($template['company_name']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <?php if ($template['is_active']): ?>
                                        <span class="px-4 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-bold">✅ ACTIVE</span>
                                    <?php else: ?>
                                        <span class="px-4 py-1.5 bg-gray-100 text-gray-600 rounded-full text-xs font-bold">⚫ INACTIVE</span>
                                    <?php endif; ?>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" <?php echo $template['is_active'] ? 'checked' : ''; ?> class="sr-only peer" onchange="toggleTemplate(<?php echo $template['id']; ?>)">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                    </label>
                                </div>
                            </div>

                            <!-- ✅ HORIZONTAL Template Preview -->
                            <div class="mb-5">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Template Preview (A4 Landscape)
                                </p>
                                <div class="template-preview-container">
                                    <div class="template-preview-wrapper">
                                        <?php 
                                        // Sample data for preview
                                        $previewHtml = str_replace(
                                            ['{{student_name}}', '{{company_name}}', '{{internship_title}}', '{{start_date}}', '{{end_date}}', '{{duration_months}}', '{{certificate_code}}', '{{certificate_number}}', '{{issue_date}}'],
                                            ['John Doe', htmlspecialchars($template['company_name']), 'Web Development', 'Jan 01, 2026', 'Feb 03, 2026', '1', 'CERT-PREVIEW', 'IA-2026-0000', 'Feb 03, 2026'],
                                            $template['template_html']
                                        );
                                        echo $previewHtml;
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Variables Display -->
                            <div class="mb-5 bg-gray-50 rounded-lg p-4">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">📋 Available Variables</p>
                                <div class="flex flex-wrap gap-2">
                                    <?php 
                                    $vars = explode(',', $template['variables']);
                                    foreach ($vars as $var): 
                                    ?>
                                        <span class="bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-xs font-mono font-semibold">{{<?php echo trim($var); ?>}}</span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="grid grid-cols-2 gap-3">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($template), ENT_QUOTES); ?>)" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-3 rounded-lg font-bold text-sm transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Edit Template
                                </button>
                                <button onclick="previewTemplate(<?php echo $template['id']; ?>)" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg font-bold text-sm transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Full Preview
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- ✅ IMPROVED Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-6xl w-full p-8 my-8 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Certificate Template
            </h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="editForm">
            <input type="hidden" id="edit_template_id" name="template_id">
            
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">📝 Template Name</label>
                        <input type="text" id="edit_template_name" name="template_name" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">🏢 Company Name</label>
                        <input type="text" id="edit_company_name" name="company_name" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" required>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">🖼️ Company Logo URL (Optional)</label>
                    <input type="text" id="edit_company_logo" name="company_logo" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="https://example.com/logo.png">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">💻 Template HTML (A4 Landscape: 1122px × 794px)</label>
                    <textarea id="edit_template_html" name="template_html" rows="14" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm transition-all bg-gray-50" required></textarea>
                    <p class="text-xs text-gray-500 mt-2">💡 Use variables like {{student_name}}, {{company_name}}, etc. in your HTML</p>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">🎨 Custom CSS (Optional)</label>
                    <textarea id="edit_template_css" name="template_css" rows="6" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm transition-all bg-gray-50"></textarea>
                </div>
            </div>
            
            <div class="flex gap-4 mt-8">
                <button type="submit" class="flex-1 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white px-8 py-4 rounded-xl font-bold text-lg transition-all shadow-lg hover:shadow-xl">
                    💾 Save Template
                </button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-8 py-4 rounded-xl font-bold text-lg transition-all">
                    ✖️ Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(template) {
        document.getElementById('edit_template_id').value = template.id;
        document.getElementById('edit_template_name').value = template.template_name;
        document.getElementById('edit_company_name').value = template.company_name;
        document.getElementById('edit_company_logo').value = template.company_logo || '';
        document.getElementById('edit_template_html').value = template.template_html;
        document.getElementById('edit_template_css').value = template.template_css || '';
        document.getElementById('editModal').classList.remove('hidden');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
    
    function toggleTemplate(templateId) {
        fetch('/api/certificate-templates.php?action=toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ template_id: templateId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to toggle template');
                location.reload();
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
            location.reload();
        });
    }
    
    function previewTemplate(templateId) {
        window.open('/app/views/admin/certificates/template-preview.php?id=' + templateId, '_blank', 'width=1200,height=900');
    }
    
    // Edit Form Submit
    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = '⏳ Saving...';
        
        try {
            const response = await fetch('/api/certificate-templates.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.fromEntries(formData))
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('✅ Template updated successfully!');
                location.reload();
            } else {
                alert('❌ Error: ' + (data.error || 'Failed to update template'));
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (err) {
            alert('❌ Error: ' + err.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Close modal on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });
</script>

</body>
</html>
