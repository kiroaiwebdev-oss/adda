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
    header('Location: /app/views/admin/certificates/course-list.php');
    exit;
}

require_once __DIR__ . '/../../../models/Certificate.php';
$certificateModel = new Certificate($db);

$certificate = $certificateModel->findByCode('');
// Get by ID instead
$stmt = $db->prepare("
    SELECT c.*, 
           c.issued_date,
           co.title as course_title,
           co.description as course_description,
           u.name as user_name,
           u.email as user_email
    FROM certificates c
    JOIN courses co ON c.course_id = co.id
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$certificateId]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    header('Location: /app/views/admin/certificates/course-list.php');
    exit;
}

$displayNumber = $certificate['certificate_number'] ?? $certificate['certificate_code'];
$issuedDate = date('F j, Y', strtotime($certificate['issued_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Certificate - <?php echo htmlspecialchars($certificate['user_name']); ?></title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
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
        
        /* Certificate Preview Container - FIXED SCALING */
        #certificate-preview-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        #certificate-preview {
            width: 100%;
            aspect-ratio: 297 / 210;
            background: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .cert-border {
            position: absolute;
            top: 4%;
            left: 4%;
            right: 4%;
            bottom: 4%;
            border: 3px solid #22c55e;
            border-radius: 6px;
        }
        
        .cert-inner-border {
            position: absolute;
            top: 5%;
            left: 5%;
            right: 5%;
            bottom: 5%;
            border: 1.5px solid #86efac;
            border-radius: 4px;
        }
        
        .corner-decoration {
            position: absolute;
            width: 30px;
            height: 30px;
            border: 2px solid #22c55e;
        }
        .corner-top-left { top: 5.5%; left: 5.5%; border-right: none; border-bottom: none; }
        .corner-top-right { top: 5.5%; right: 5.5%; border-left: none; border-bottom: none; }
        .corner-bottom-left { bottom: 5.5%; left: 5.5%; border-right: none; border-top: none; }
        .corner-bottom-right { bottom: 5.5%; right: 5.5%; border-left: none; border-top: none; }
        
        .cert-content {
            position: relative;
            z-index: 10;
            padding: 6% 8% 5% 8%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .cert-logo {
            text-align: center;
            margin-bottom: 2%;
        }
        .cert-logo img {
            height: 24px;
            width: auto;
        }
        
        .cert-main {
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            margin-top: -3%;
        }
        
        .cert-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5em;
            font-weight: 700;
            color: #22c55e;
            margin-bottom: 0.15em;
            letter-spacing: 1px;
        }
        
        .cert-subtitle {
            font-size: 1.15em;
            color: #6b7280;
            margin-bottom: 0.6em;
        }
        
        .cert-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0.6em 0;
        }
        .cert-divider-line {
            width: 40px;
            height: 2px;
            background: #22c55e;
        }
        .cert-divider-icon {
            width: 18px;
            height: 18px;
            background: #22c55e;
            border-radius: 50%;
            margin: 0 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
        }
        
        .cert-presented-to {
            font-size: 0.85em;
            color: #6b7280;
            margin-bottom: 0.4em;
        }
        
        .cert-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.3em;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5em;
            border-bottom: 2px solid #22c55e;
            display: inline-block;
            padding-bottom: 0.15em;
        }
        
        .cert-completion-text {
            font-size: 0.8em;
            color: #6b7280;
            margin-bottom: 0.3em;
        }
        
        .cert-course-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.6em;
            font-weight: 700;
            color: #22c55e;
            margin-bottom: 0.4em;
        }
        
        .cert-collaboration {
            text-align: center;
            margin-top: 0.5em;
            font-size: 0.65em;
            color: #22c55e;
            font-weight: 600;
        }
        
        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
            padding-top: 3%;
        }
        
        .cert-footer-left, .cert-footer-center, .cert-footer-right {
            flex: 1;
        }
        
        .cert-footer-left {
            text-align: left;
        }
        
        .cert-footer-center {
            text-align: center;
        }
        
               .cert-footer-right {
            text-align: right;
            flex: 1;
            display: flex;
            justify-content: flex-end;
        }
        
        .cert-signature img {
            height: 32px;
            width: auto;
            margin-bottom: 2px;
        }
        .cert-signature-line {
            width: 120px;
            height: 1.5px;
            background: #d1d5db;
            margin: 2px 0;
        }
        .cert-signature-label {
            font-size: 0.55em;
            color: #6b7280;
            margin-top: 2px;
        }
        
        .cert-qr {
            display: inline-block;
        }
        
        .cert-qr img {
            height: 45px;
            width: 45px;
            display: block;
        }
        .cert-qr-label {
            font-size: 0.5em;
            color: #6b7280;
            margin-top: 2px;
        }

        .cert-info-label {
            font-size: 0.55em;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 1px;
        }
        .cert-info-value {
            font-size: 0.65em;
            font-weight: 600;
            color: #1f2937;
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
                    <a href="/app/views/admin/certificates/course-list.php" class="text-primary-600 hover:text-primary-700 text-sm font-semibold mb-2 inline-block">
                        ← Back to Certificates
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900">Course Certificate Details</h1>
                    <p class="text-gray-600 mt-1">Awarded to <?php echo htmlspecialchars($certificate['user_name']); ?></p>
                </div>
                <div>
                    <?php if (isset($certificate['revoked']) && $certificate['revoked']): ?>
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
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-200 p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Certificate Information</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Certificate Number</span>
                            <p class="text-lg font-mono font-bold text-gray-900">
                                <?php echo htmlspecialchars($certificate['certificate_number'] ?? 'N/A'); ?>
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
                                <?php echo htmlspecialchars($certificate['user_name']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Email</span>
                            <p class="text-sm text-gray-700">
                                <?php echo htmlspecialchars($certificate['user_email']); ?>
                            </p>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Course Title</span>
                            <p class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($certificate['course_title']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Issued Date</span>
                            <p class="text-sm text-gray-700">
                                <?php echo isset($certificate['issued_date']) ? date('F d, Y', strtotime($certificate['issued_date'])) : 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo isset($certificate['issued_date']) ? date('h:i A', strtotime($certificate['issued_date'])) : ''; ?>
                            </p>
                        </div>
                        
                        <?php if (isset($certificate['revoked']) && $certificate['revoked']): ?>
                            <div class="pt-4 border-t border-gray-200">
                                <span class="text-xs font-semibold text-red-500 uppercase block mb-1">Revoked Date</span>
                                <p class="text-sm text-red-700">
                                    <?php echo isset($certificate['revoked_at']) ? date('F d, Y h:i A', strtotime($certificate['revoked_at'])) : 'N/A'; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-6 pt-6 border-t border-gray-200 space-y-3">
                        <?php if (!isset($certificate['revoked']) || !$certificate['revoked']): ?>
                            <button onclick="revokeCertificate(<?php echo $certificate['id']; ?>)" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                🚫 Revoke Certificate
                            </button>
                        <?php else: ?>
                            <button onclick="restoreCertificate(<?php echo $certificate['id']; ?>)" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                ✅ Restore Certificate
                            </button>
                        <?php endif; ?>
                        
                        <a href="/app/views/verify-certificate.php?code=<?php echo urlencode($certificate['certificate_code']); ?>" target="_blank" class="block w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors text-center">
                            🔍 Verify Certificate
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Certificate Preview -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-200 p-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Certificate Preview</h2>
                    
                    <div id="certificate-preview-container">
                        <div id="certificate-preview">
                            <!-- Borders -->
                            <div class="cert-border"></div>
                            <div class="cert-inner-border"></div>
                            
                            <!-- Corner Decorations -->
                            <div class="corner-decoration corner-top-left"></div>
                            <div class="corner-decoration corner-top-right"></div>
                            <div class="corner-decoration corner-bottom-left"></div>
                            <div class="corner-decoration corner-bottom-right"></div>
                            
                            <!-- Content -->
                            <div class="cert-content">
                                <!-- Logo at Top Center -->
                                <div class="cert-logo">
                                    <img src="https://internshipadda.com/icon.png" alt="Internship Adda">
                                </div>
                                
                                <!-- Main Content -->
                                <div class="cert-main">
                                    <h1 class="cert-title">CERTIFICATE</h1>
                                    <p class="cert-subtitle">of Completion</p>
                                    
                                    <div class="cert-divider">
                                        <div class="cert-divider-line"></div>
                                        <div class="cert-divider-icon">✓</div>
                                        <div class="cert-divider-line"></div>
                                    </div>
                                    
                                    <p class="cert-presented-to">This is to certify that</p>
                                    <h2 class="cert-name"><?php echo htmlspecialchars($certificate['user_name']); ?></h2>
                                    
                                    <p class="cert-completion-text">has successfully completed the course</p>
                                    <h3 class="cert-course-title"><?php echo htmlspecialchars($certificate['course_title']); ?></h3>
                                    
                                    <!-- Collaboration Text -->
                                    <div class="cert-collaboration">
                                        Internship Adda × BLUECOLLAR Connected Tech
                                    </div>
                                </div>
                                
                                <!-- Footer Section -->
                                <div class="cert-footer">
                                    <!-- Left: Signature -->
                                    <div class="cert-footer-left">
                                        <div class="cert-signature">
                                            <img src="https://i.ibb.co/8DjXhf2X/image-removebg-preview.png" alt="Signature">
                                            <div class="cert-signature-line"></div>
                                            <div class="cert-signature-label">Authorized Signature</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Center: Date & Certificate Number -->
                                    <div class="cert-footer-center">
                                        <div style="margin-bottom: 8px;">
                                            <div class="cert-info-label">Issue Date</div>
                                            <div class="cert-info-value"><?php echo $issuedDate; ?></div>
                                        </div>
                                        <div>
                                            <div class="cert-info-label">Certificate ID</div>
                                            <div class="cert-info-value" style="font-size: 0.55em;"><?php echo htmlspecialchars($displayNumber); ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Right: QR Code -->
                                    <div class="cert-footer-right">
                                        <div class="cert-qr">
                                            <img src="https://i.ibb.co/VcvwKT1m/qr-code.png" alt="QR Code">
                                            <div class="cert-qr-label">Scan to Verify</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function revokeCertificate(id) {
        if (!confirm('⚠️ Revoke this certificate?\n\nThe student will no longer be able to view or download it.')) return;
        
        fetch('/api/certificates.php?action=revoke', {
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
            alert('❌ Network error: ' + err.message);
        });
    }
    
    function restoreCertificate(id) {
        if (!confirm('Restore this certificate?')) return;
        
        fetch('/api/certificates.php?action=restore', {
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
            alert('❌ Network error: ' + err.message);
        });
    }
</script>

</body>
</html>
