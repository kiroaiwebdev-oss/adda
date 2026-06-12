<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$dbConfig = require __DIR__ . '/../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../core/' . $class . '.php',
        __DIR__ . '/../models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

require_once __DIR__ . '/../models/Certificate.php';
require_once __DIR__ . '/../models/InternshipCertificate.php';

$certificateModel = new Certificate($db);
$internshipCertModel = new InternshipCertificate($db);

$verificationCode = $_GET['code'] ?? '';
$verificationResult = null;
$certificateType = null;

if ($verificationCode) {
    // Try course certificate first
    $result = $certificateModel->verify($verificationCode);
    
    if ($result['valid']) {
        $verificationResult = $result;
        $certificateType = 'course';
    } else {
        // Try internship certificate
        $cert = $internshipCertModel->getByCode($verificationCode);
        if ($cert) {
            if ($cert['revoked']) {
                $verificationResult = [
                    'valid' => false,
                    'message' => 'Certificate has been revoked',
                    'certificate' => $cert
                ];
            } else {
                $verificationResult = [
                    'valid' => true,
                    'message' => 'Certificate is valid',
                    'certificate' => $cert
                ];
            }
            $certificateType = 'internship';
        } else {
            $verificationResult = [
                'valid' => false,
                'message' => 'Certificate not found'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - Internship Adda</title>
    
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
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); }
        h1, h2, h3, h4 { font-family: 'Poppins', sans-serif; font-weight: 700; }
    </style>
</head>
<body class="min-h-screen py-12 px-4">

<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-4">
            <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl flex items-center justify-center">
                <span class="text-white font-bold text-3xl">I</span>
            </div>
            <h1 class="text-4xl font-bold text-gray-900">Internship Adda</h1>
        </div>
        <p class="text-xl text-gray-600">Certificate Verification</p>
    </div>

    <!-- Search Form -->
    <div class="bg-white rounded-2xl border-2 border-primary-200 p-8 mb-6 shadow-xl">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Verify Certificate</h2>
        <form method="GET" class="flex gap-3">
            <input 
                type="text" 
                name="code" 
                value="<?php echo htmlspecialchars($verificationCode); ?>" 
                placeholder="Enter Certificate Number or Code (e.g., IA/CC/2026/0001)" 
                class="flex-1 px-6 py-4 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-lg"
                required
            >
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-4 rounded-xl font-bold text-lg transition-colors">
                🔍 Verify
            </button>
        </form>
    </div>

    <!-- Verification Result -->
    <?php if ($verificationResult): ?>
        <div class="bg-white rounded-2xl border-2 <?php echo $verificationResult['valid'] ? 'border-green-200' : 'border-red-200'; ?> p-8 shadow-xl">
            <?php if ($verificationResult['valid']): ?>
                <!-- Valid Certificate -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-3xl font-bold text-green-600 mb-2">✅ Valid Certificate</h3>
                    <p class="text-gray-600"><?php echo htmlspecialchars($verificationResult['message']); ?></p>
                </div>

                <?php $cert = $verificationResult['certificate']; ?>
                
                <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-sm font-semibold text-gray-500 uppercase">Certificate Number</span>
                            <p class="text-lg font-bold text-gray-900 font-mono">
                                <?php echo htmlspecialchars($cert['certificate_number'] ?? $cert['certificate_code']); ?>
                            </p>
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-gray-500 uppercase">Certificate Code</span>
                            <p class="text-lg font-bold text-primary-600 font-mono">
                                <?php echo htmlspecialchars($cert['certificate_code']); ?>
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <span class="text-sm font-semibold text-gray-500 uppercase">Awarded To</span>
                        <p class="text-xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($cert['user_name'] ?? $cert['student_name']); ?>
                        </p>
                    </div>

                    <div>
                        <span class="text-sm font-semibold text-gray-500 uppercase">
                            <?php echo $certificateType === 'course' ? 'Course Title' : 'Internship Title'; ?>
                        </span>
                        <p class="text-lg font-semibold text-gray-900">
                            <?php echo htmlspecialchars($cert['course_title'] ?? $cert['internship_title']); ?>
                        </p>
                    </div>

                    <?php if ($certificateType === 'internship'): ?>
                        <div>
                            <span class="text-sm font-semibold text-gray-500 uppercase">Company Name</span>
                            <p class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($cert['company_name']); ?>
                            </p>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <span class="text-sm font-semibold text-gray-500 uppercase">Start Date</span>
                                <p class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($cert['start_date'])); ?></p>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-gray-500 uppercase">End Date</span>
                                <p class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($cert['end_date'])); ?></p>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-gray-500 uppercase">Duration</span>
                                <p class="text-sm text-gray-900"><?php echo $cert['duration_months']; ?> months</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="border-t border-gray-200 pt-4">
                        <span class="text-sm font-semibold text-gray-500 uppercase">Issued On</span>
                        <p class="text-lg text-gray-900">
                            <?php echo date('F d, Y', strtotime($cert['issued_at'])); ?>
                        </p>
                    </div>
                </div>

            <?php else: ?>
                <!-- Invalid Certificate -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-4">
                        <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-3xl font-bold text-red-600 mb-2">❌ Invalid Certificate</h3>
                    <p class="text-lg text-gray-700"><?php echo htmlspecialchars($verificationResult['message']); ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Info -->
    <div class="mt-8 text-center text-sm text-gray-600">
        <p>For any queries, contact us at <a href="mailto:support@internshipadda.com" class="text-primary-600 font-semibold">support@internshipadda.com</a></p>
    </div>
</div>

</body>
</html>
