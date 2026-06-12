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

// ✅ Get certificate WITH template HTML
$stmt = $db->prepare("
    SELECT 
        ic.*,
        ct.template_html,
        ct.template_css,
        ct.template_name,
        ct.company_name as template_company,
        u.email as user_email,
        u.name as user_name
    FROM internship_certificates ic
    LEFT JOIN certificate_templates ct ON ic.template_id = ct.id
    LEFT JOIN users u ON ic.user_id = u.id
    WHERE ic.id = ?
");
$stmt->execute([$certificateId]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    header('Location: /app/views/admin/certificates/internship-issued.php');
    exit;
}

// ✅ IMPORTANT: Replace template variables with actual data
$templateHtml = $certificate['template_html'] ?? '<div style="padding:50px;text-align:center;color:#999">Template not found or empty</div>';
$templateCss = $certificate['template_css'] ?? '';

// Replace all variables
$replacements = [
    '{{student_name}}' => htmlspecialchars($certificate['student_name'] ?? 'Student Name'),
    '{{company_name}}' => htmlspecialchars($certificate['company_name'] ?? 'Company Name'),
    '{{internship_title}}' => htmlspecialchars($certificate['internship_title'] ?? 'Internship'),
    '{{start_date}}' => date('F d, Y', strtotime($certificate['start_date'] ?? 'now')),
    '{{end_date}}' => date('F d, Y', strtotime($certificate['end_date'] ?? 'now')),
    '{{duration_months}}' => $certificate['duration_months'] ?? '0',
    '{{certificate_code}}' => htmlspecialchars($certificate['certificate_code'] ?? 'CERT-XXXX'),
    '{{certificate_number}}' => htmlspecialchars($certificate['certificate_number'] ?? 'IA-0000'),
    '{{issue_date}}' => date('F d, Y', strtotime($certificate['issued_at'] ?? 'now'))
];

foreach ($replacements as $placeholder => $value) {
    $templateHtml = str_replace($placeholder, $value, $templateHtml);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Certificate - <?php echo htmlspecialchars($certificate['student_name']); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&family=Playfair+Display:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        
        /* ✅ Certificate Print Styles */
        @media print {
            body * { visibility: hidden; }
            #certificate-preview, #certificate-preview * { visibility: visible; }
            #certificate-preview { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 1122px;
                height: 794px;
            }
            .no-print { display: none !important; }
        }
        
        /* ✅ Custom Template Styles */
        <?php echo $templateCss; ?>
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30 no-print">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <a href="/app/views/admin/certificates/internship-issued.php" class="text-primary-600 hover:text-primary-700 text-sm font-semibold mb-2 inline-block">
                        ← Back to Issued Certificates
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900">Internship Certificate Details</h1>
                    <p class="text-gray-600 mt-1">Awarded to <?php echo htmlspecialchars($certificate['student_name']); ?></p>
                </div>
                <div>
                    <?php if ($certificate['revoked']): ?>
                        <span class="px-6 py-3 text-sm font-bold rounded-lg bg-red-100 text-red-700">🚫 REVOKED</span>
                    <?php else: ?>
                        <span class="px-6 py-3 text-sm font-bold rounded-lg bg-green-100 text-green-700">✅ ACTIVE</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Certificate Info -->
            <div class="lg:col-span-1 no-print">
                <div class="bg-white rounded-xl border border-gray-200 p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Certificate Information</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Certificate Number</span>
                            <p class="text-lg font-mono font-bold text-gray-900">
                                <?php echo htmlspecialchars($certificate['certificate_number']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Certificate Code</span>
                            <p class="text-lg font-mono font-bold text-primary-600">
                                <?php echo htmlspecialchars($certificate['certificate_code']); ?>
                            </p>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Student Name</span>
                            <p class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($certificate['student_name']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Company Name</span>
                            <p class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($certificate['company_name']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Internship Title</span>
                            <p class="text-sm text-gray-700">
                                <?php echo htmlspecialchars($certificate['internship_title']); ?>
                            </p>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200 grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Start Date</span>
                                <p class="text-sm text-gray-700">
                                    <?php echo date('M d, Y', strtotime($certificate['start_date'])); ?>
                                </p>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">End Date</span>
                                <p class="text-sm text-gray-700">
                                    <?php echo date('M d, Y', strtotime($certificate['end_date'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Duration</span>
                            <p class="text-sm text-gray-700">
                                <?php echo $certificate['duration_months']; ?> months
                            </p>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Template</span>
                            <p class="text-sm text-gray-700">
                                <?php echo htmlspecialchars($certificate['template_name'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Issued Date</span>
                            <p class="text-sm text-gray-700">
                                <?php echo date('F d, Y h:i A', strtotime($certificate['issued_at'])); ?>
                            </p>
                        </div>
                        
                        <?php if ($certificate['revoked']): ?>
                            <div class="pt-4 border-t border-gray-200">
                                <span class="text-xs font-semibold text-red-500 uppercase block mb-1">Revoked Date</span>
                                <p class="text-sm text-red-700">
                                    <?php echo $certificate['revoked_at'] ? date('F d, Y', strtotime($certificate['revoked_at'])) : 'N/A'; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-6 pt-6 border-t border-gray-200 space-y-3">
                        <button onclick="window.print()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Certificate
                        </button>
                        
                        <?php if (!$certificate['revoked']): ?>
                            <button onclick="revokeCertificate(<?php echo $certificate['id']; ?>)" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                🚫 Revoke Certificate
                            </button>
                        <?php else: ?>
                            <button onclick="restoreCertificate(<?php echo $certificate['id']; ?>)" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                ✅ Restore Certificate
                            </button>
                        <?php endif; ?>
                        
                        <a href="/app/views/verify-certificate.php?code=<?php echo urlencode($certificate['certificate_code']); ?>" target="_blank" class="block w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors text-center">
                            🔍 Verify Certificate
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- ✅ Certificate Preview - HORIZONTAL LAYOUT -->
                       <!-- ✅ Certificate Preview - AUTO-FIT RESPONSIVE -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6 no-print">
                        <h2 class="text-xl font-bold text-gray-900">Certificate Preview</h2>
                        <span class="text-sm text-gray-500 bg-gray-100 px-4 py-2 rounded-full">
                            Template: <?php echo htmlspecialchars($certificate['template_name'] ?? 'Default'); ?>
                        </span>
                    </div>
                    
                    <!-- ✅ AUTO-FIT RESPONSIVE CONTAINER -->
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-gray-300 rounded-xl overflow-hidden shadow-inner p-4">
                        <div style="width: 100%; max-width: 1122px; margin: 0 auto;">
                            <div id="certificate-preview" style="width: 100%; aspect-ratio: 1122/794; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 8px; overflow: hidden;">
                                <div style="transform: scale(var(--scale, 0.8)); transform-origin: top left; width: 1122px; height: 794px;">
                                    <?php echo $templateHtml; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 text-center text-sm text-gray-500 no-print">
                        <p class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Certificate in A4 Landscape (1122×794px) • Auto-scaled to fit
                        </p>
                    </div>
                </div>
            </div>
            
            <script>
                // ✅ Auto-calculate perfect scale
                function adjustCertificateScale() {
                    const container = document.getElementById('certificate-preview');
                    if (container) {
                        const containerWidth = container.offsetWidth;
                        const scale = containerWidth / 1122;
                        container.style.setProperty('--scale', scale);
                        container.querySelector('div').style.transform = `scale(${scale})`;
                    }
                }
                
                window.addEventListener('load', adjustCertificateScale);
                window.addEventListener('resize', adjustCertificateScale);
            </script>

        </div>
    </main>
</div>

<script>
    function revokeCertificate(id) {
        if (!confirm('⚠️ Revoke this certificate?\n\nThe student will no longer be able to view or download it.')) return;
        
        fetch('/api/internship-certificates.php?action=revoke', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ certificate_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ Certificate revoked successfully');
                location.reload();
            } else {
                alert('❌ ' + (data.error || 'Failed to revoke certificate'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }
    
    function restoreCertificate(id) {
        if (!confirm('Restore this certificate?')) return;
        
        fetch('/api/internship-certificates.php?action=restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ certificate_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ Certificate restored successfully');
                location.reload();
            } else {
                alert('❌ ' + (data.error || 'Failed to restore certificate'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }
</script>

</body>
</html>
