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

// Filters
$revokedFilter = $_GET['revoked'] ?? '';
$search = $_GET['search'] ?? '';

// ✅ FIXED QUERY
$baseQuery = "
    SELECT 
        ic.*,
        u.email as user_email,
        u.name as user_name,
        ct.template_name
    FROM internship_certificates ic
    LEFT JOIN users u ON ic.user_id = u.id
    LEFT JOIN certificate_templates ct ON ic.template_id = ct.id
    WHERE 1=1
";

$params = [];

if ($revokedFilter === 'no') {
    $baseQuery .= " AND ic.revoked = 0";
} elseif ($revokedFilter === 'yes') {
    $baseQuery .= " AND ic.revoked = 1";
}

if ($search) {
    $baseQuery .= " AND (ic.student_name LIKE ? OR ic.company_name LIKE ? OR ic.certificate_code LIKE ? OR ic.internship_title LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$baseQuery .= " ORDER BY ic.issued_at DESC";

$stmt = $db->prepare($baseQuery);
$stmt->execute($params);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issued Internship Certificates - Admin</title>
    
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
                    <h1 class="text-3xl font-bold text-gray-900">Issued Internship Certificates</h1>
                    <p class="text-gray-600 mt-1">View and manage all issued internship certificates</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-primary-50 px-6 py-3 rounded-lg border-2 border-primary-200">
                        <span class="text-sm text-gray-600 font-semibold">Total Issued:</span>
                        <span class="text-2xl font-bold text-primary-600 ml-2"><?php echo count($certificates); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Student name, company, certificate code..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="revoked" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">All Certificates</option>
                        <option value="no" <?php echo $revokedFilter === 'no' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="yes" <?php echo $revokedFilter === 'yes' ? 'selected' : ''; ?>>Revoked Only</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Certificates Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Certificate Code</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Student</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Company</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Internship Title</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Duration</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Issued Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($certificates)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                    </svg>
                                    <p class="text-gray-600 font-semibold text-lg">No certificates issued yet</p>
                                    <p class="text-gray-500 text-sm mt-2">Approved certificate requests will appear here</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-semibold text-primary-600"><?php echo htmlspecialchars($cert['certificate_code']); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($cert['student_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($cert['user_email'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($cert['company_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($cert['internship_title']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php echo $cert['duration_months']; ?> months
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php echo date('M d, Y', strtotime($cert['issued_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($cert['revoked']): ?>
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700">Revoked</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2 flex-wrap">
                                            <a href="/app/views/admin/certificates/internship-view.php?id=<?php echo $cert['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-700 font-semibold text-sm whitespace-nowrap">View</a>
                                            
                                            <!-- ✅ Verify Button -->
                                            <button onclick="verifyCertificate('<?php echo htmlspecialchars($cert['certificate_code']); ?>')" 
                                                    class="text-purple-600 hover:text-purple-700 font-semibold text-sm whitespace-nowrap">Verify</button>
                                            
                                            <?php if (!$cert['revoked']): ?>
                                                <button onclick="revokeCertificate(<?php echo $cert['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-700 font-semibold text-sm whitespace-nowrap">Revoke</button>
                                            <?php else: ?>
                                                <button onclick="restoreCertificate(<?php echo $cert['id']; ?>)" 
                                                        class="text-green-600 hover:text-green-700 font-semibold text-sm whitespace-nowrap">Restore</button>
                                            <?php endif; ?>
                                        </div>
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

<!-- Verify Modal -->
<div id="verifyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full p-8 shadow-2xl">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">🔍 Certificate Verification Result</h2>
        <div id="verifyResult"></div>
        <button onclick="closeVerifyModal()" class="w-full mt-6 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-bold transition-colors">
            Close
        </button>
    </div>
</div>

<script>
    function verifyCertificate(certificateCode) {
        if (!certificateCode) {
            alert('Invalid certificate code');
            return;
        }
        
        fetch('/api/internship-certificates.php?action=verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ certificate_code: certificateCode })
        })
        .then(res => res.json())
        .then(data => {
            let resultHTML = '';
            
            if (data.success && data.valid) {
                const cert = data.certificate;
                resultHTML = `
                    <div class="bg-green-50 border-2 border-green-200 rounded-lg p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-green-900">✅ Certificate Verified</h3>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-semibold">Certificate Code:</span> ${cert.certificate_code}</div>
                            <div><span class="font-semibold">Certificate Number:</span> ${cert.certificate_number}</div>
                            <div><span class="font-semibold">Student Name:</span> ${cert.student_name}</div>
                            <div><span class="font-semibold">Company:</span> ${cert.company_name}</div>
                            <div><span class="font-semibold">Internship:</span> ${cert.internship_title}</div>
                            <div><span class="font-semibold">Duration:</span> ${cert.duration_months} months</div>
                            <div><span class="font-semibold">Issued Date:</span> ${new Date(cert.issued_at).toLocaleDateString()}</div>
                            <div class="pt-3 border-t border-green-300">
                                <span class="px-3 py-1 bg-green-600 text-white rounded-full text-xs font-bold">✓ ACTIVE</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                resultHTML = `
                    <div class="bg-red-50 border-2 border-red-200 rounded-lg p-6 text-center">
                        <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-red-900 mb-2">❌ ${data.message || 'Certificate Invalid'}</h3>
                        <p class="text-sm text-red-700">This certificate could not be verified in our system.</p>
                    </div>
                `;
            }
            
            document.getElementById('verifyResult').innerHTML = resultHTML;
            document.getElementById('verifyModal').classList.remove('hidden');
        })
        .catch(err => {
            alert('Error verifying certificate: ' + err.message);
        });
    }
    
    function closeVerifyModal() {
        document.getElementById('verifyModal').classList.add('hidden');
    }
    
    function revokeCertificate(id) {
        if (!confirm('⚠️ Revoke this certificate?\n\nThe student will NO LONGER be able to access or share this certificate.')) return;
        
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
                alert('❌ Error: ' + (data.error || 'Failed to revoke certificate'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }
    
    function restoreCertificate(id) {
        if (!confirm('Restore this certificate?\n\nIt will become active and accessible again.')) return;
        
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
                alert('❌ Error: ' + (data.error || 'Failed to restore certificate'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeVerifyModal();
        }
    });
</script>

</body>
</html>
