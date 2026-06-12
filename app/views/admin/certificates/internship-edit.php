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

$certificateId = $_GET['id'] ?? 0;
if (!$certificateId) {
    header('Location: /app/views/admin/certificates/internship-issued.php');
    exit;
}

require_once __DIR__ . '/../../../models/InternshipCertificate.php';
require_once __DIR__ . '/../../../models/CertificateTemplate.php';

$certificateModel = new InternshipCertificate($db);
$templateModel = new CertificateTemplate($db);

$certificate = $certificateModel->getById($certificateId);
if (!$certificate) {
    header('Location: /app/views/admin/certificates/internship-issued.php');
    exit;
}

$templates = $templateModel->getActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Internship Certificate</title>
    
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
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <a href="/app/views/admin/certificates/internship-issued.php" class="text-primary-600 hover:text-primary-700 text-sm font-semibold mb-2 inline-block">
                        ← Back to Issued Certificates
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Internship Certificate</h1>
                    <p class="text-gray-600 mt-1">Certificate Code: <?php echo htmlspecialchars($certificate['certificate_code']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <div id="alertContainer" class="mb-6"></div>

                <form id="editForm">
                    <input type="hidden" name="certificate_id" value="<?php echo $certificate['id']; ?>">

                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Student Name *</label>
                                <input type="text" name="student_name" value="<?php echo htmlspecialchars($certificate['student_name']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Company Name *</label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($certificate['company_name']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Internship Title *</label>
                            <input type="text" name="internship_title" value="<?php echo htmlspecialchars($certificate['internship_title']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                        </div>

                        <div class="grid grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date *</label>
                                <input type="date" name="start_date" value="<?php echo $certificate['start_date']; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date *</label>
                                <input type="date" name="end_date" value="<?php echo $certificate['end_date']; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Duration (Months) *</label>
                                <input type="number" name="duration_months" value="<?php echo $certificate['duration_months']; ?>" min="1" max="24" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Certificate Template *</label>
                            <select name="template_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>" <?php echo $certificate['template_id'] == $template['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($template['template_name']); ?> - <?php echo htmlspecialchars($template['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pt-6 border-t border-gray-200 flex gap-4">
                            <button type="submit" id="saveBtn" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                💾 Save Changes
                            </button>
                            <a href="/app/views/admin/certificates/internship-issued.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold text-center transition-colors">
                                ❌ Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    const form = document.getElementById('editForm');
    const alertContainer = document.getElementById('alertContainer');
    const saveBtn = document.getElementById('saveBtn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        saveBtn.disabled = true;
        saveBtn.innerHTML = '⏳ Saving...';
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        try {
            const response = await fetch('/api/internship-certificates.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alertContainer.innerHTML = '<div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg border border-green-200 font-semibold">✅ Certificate updated successfully</div>';
                
                setTimeout(() => {
                    window.location.href = '/app/views/admin/certificates/internship-issued.php';
                }, 1000);
            } else {
                alertContainer.innerHTML = `<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200 font-semibold">❌ ${result.error || 'Failed to update certificate'}</div>`;
                saveBtn.disabled = false;
                saveBtn.innerHTML = '💾 Save Changes';
            }
        } catch (error) {
            console.error('Error:', error);
            alertContainer.innerHTML = '<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200 font-semibold">❌ Network error. Please try again.</div>';
            saveBtn.disabled = false;
            saveBtn.innerHTML = '💾 Save Changes';
        }
    });
</script>

</body>
</html>
