<?php
session_start();

require_once __DIR__ . '/../../../config/database.php';

$dbConfig = require __DIR__ . '/../../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

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

// ============================================
// CSV IMPORT PROCESSING
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['csv_import_message'] = '❌ CSV upload failed. Please select a valid file.';
        $_SESSION['csv_import_success'] = false;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $tmpName = $_FILES['csv_file']['tmp_name'];
    $fileName = $_FILES['csv_file']['name'];
    
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        $_SESSION['csv_import_message'] = '❌ Only CSV files are allowed.';
        $_SESSION['csv_import_success'] = false;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (($handle = fopen($tmpName, 'r')) === false) {
        $_SESSION['csv_import_message'] = '❌ Unable to open uploaded file.';
        $_SESSION['csv_import_success'] = false;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $header = fgetcsv($handle, 0, ',');
    if (!$header) {
        fclose($handle);
        $_SESSION['csv_import_message'] = '❌ Empty CSV file.';
        $_SESSION['csv_import_success'] = false;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $expected = ['student_name', 'student_email', 'company_name', 'internship_title', 'certificate_number', 'start_date', 'end_date', 'duration_months', 'issued_date'];
    $normalizedHeader = array_map('trim', array_map('strtolower', $header));
    
    if ($normalizedHeader !== $expected) {
        fclose($handle);
        $_SESSION['csv_import_message'] = '❌ Invalid CSV header. Expected: ' . implode(', ', $expected);
        $_SESSION['csv_import_success'] = false;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $rowNumber = 1;
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $rowNumber++;

        if (empty(array_filter($row))) {
            continue;
        }

        $studentName = trim($row[0] ?? '');
        $studentEmail = trim($row[1] ?? '');
        $companyName = trim($row[2] ?? '');
        $internshipTitle = trim($row[3] ?? '');
        $certificateNumber = trim($row[4] ?? '');
        $startDate = trim($row[5] ?? '');
        $endDate = trim($row[6] ?? '');
        $durationMonths = trim($row[7] ?? '');
        $issuedDate = trim($row[8] ?? '');

        // Validate required fields
        if ($studentName === '' || $studentEmail === '' || $companyName === '' || $internshipTitle === '' || $certificateNumber === '' || $startDate === '' || $endDate === '' || $durationMonths === '' || $issuedDate === '') {
            $errorCount++;
            $errors[] = "Row {$rowNumber}: Missing required fields.";
            continue;
        }

        // Validate email
        if (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $errorCount++;
            $errors[] = "Row {$rowNumber}: Invalid email format ({$studentEmail}).";
            continue;
        }

        // Validate dates
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $issuedTimestamp = strtotime($issuedDate);
        
        if ($startTimestamp === false || $endTimestamp === false || $issuedTimestamp === false) {
            $errorCount++;
            $errors[] = "Row {$rowNumber}: Invalid date format. Use YYYY-MM-DD";
            continue;
        }
        
        $formattedStartDate = date('Y-m-d', $startTimestamp);
        $formattedEndDate = date('Y-m-d', $endTimestamp);
        $formattedIssuedDate = date('Y-m-d H:i:s', $issuedTimestamp);

        // Check if certificate number already exists
        $stmt = $db->prepare("SELECT id FROM internship_certificates WHERE certificate_number = ?");
        $stmt->execute([$certificateNumber]);
        if ($stmt->fetch()) {
            $errorCount++;
            $errors[] = "Row {$rowNumber}: Certificate number already exists ({$certificateNumber}).";
            continue;
        }

        // Generate unique codes
        $certificateCode = 'INT-CERT-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $verificationCode = bin2hex(random_bytes(16));

        // Insert certificate (user_id = NULL for external students)
        try {
            $stmt = $db->prepare("
                INSERT INTO internship_certificates 
                (user_id, student_name, student_email, company_name, internship_title, certificate_code, certificate_number, start_date, end_date, duration_months, issued_at, revoked, revoked_at) 
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)
            ");
            
            $stmt->execute([
                $studentName,
                $studentEmail,
                $companyName,
                $internshipTitle,
                $certificateCode,
                $certificateNumber,
                $formattedStartDate,
                $formattedEndDate,
                (int)$durationMonths,
                $formattedIssuedDate
            ]);
            
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Row {$rowNumber}: Database error - " . $e->getMessage();
        }
    }

    fclose($handle);

    $message = "✅ {$successCount} certificate(s) imported successfully.";
    if ($errorCount > 0) {
        $message .= " ❌ {$errorCount} row(s) failed.";
        if (count($errors) > 0) {
            $message .= " Errors: " . implode(' | ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= " ... and " . (count($errors) - 5) . " more errors.";
            }
        }
    }

    $_SESSION['csv_import_message'] = $message;
    $_SESSION['csv_import_success'] = $successCount > 0 && $errorCount === 0;

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// ============================================
// GENERATE SAMPLE CSV DOWNLOAD
// ============================================
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample-internship-certificates.csv"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['student_name', 'student_email', 'company_name', 'internship_title', 'certificate_number', 'start_date', 'end_date', 'duration_months', 'issued_date']);
    
    fputcsv($output, ['Rahul Kumar', 'rahul@external.com', 'Global Business Corp', 'Digital Marketing', 'INT-CERT-2026-001', '2025-12-01', '2026-02-01', '2', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Priya Singh', 'priya@external.com', 'Digital Marketing Pro', 'Mobile App Development', 'INT-CERT-2026-002', '2025-11-15', '2026-01-15', '2', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Vikram Rao', 'vikram@external.com', 'Tech Solutions', 'Web Development', 'INT-CERT-2026-003', '2025-10-01', '2025-12-01', '2', date('Y-m-d H:i:s')]);
    
    fclose($output);
    exit;
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

try {
    $stmt = $db->query("
        SELECT ic.*, 
               COALESCE(u.name, ic.student_name) as display_name,
               COALESCE(u.email, ic.student_email) as display_email,
               CASE WHEN ic.user_id IS NULL THEN 'External' ELSE 'Registered' END as student_type
        FROM internship_certificates ic
        LEFT JOIN users u ON ic.user_id = u.id
        ORDER BY ic.id DESC
    ");
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error loading certificates: " . $e->getMessage());
}

// Apply filters
if ($statusFilter === 'revoked') {
    $certificates = array_filter($certificates, fn($c) => isset($c['revoked']) && $c['revoked'] == 1);
} elseif ($statusFilter === 'active') {
    $certificates = array_filter($certificates, fn($c) => !isset($c['revoked']) || $c['revoked'] == 0);
}

if ($search) {
    $certificates = array_filter($certificates, function($c) use ($search) {
        return (isset($c['display_name']) && stripos($c['display_name'], $search) !== false) || 
               (isset($c['company_name']) && stripos($c['company_name'], $search) !== false) ||
               (isset($c['internship_title']) && stripos($c['internship_title'], $search) !== false) ||
               (isset($c['certificate_code']) && stripos($c['certificate_code'], $search) !== false) ||
               (isset($c['certificate_number']) && stripos($c['certificate_number'], $search) !== false);
    });
}

$totalCertificates = count($certificates);
$registeredCount = count(array_filter($certificates, fn($c) => $c['student_type'] === 'Registered'));
$externalCount = count(array_filter($certificates, fn($c) => $c['student_type'] === 'External'));
$revokedCount = count(array_filter($certificates, fn($c) => isset($c['revoked']) && $c['revoked']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Certificates - Admin</title>
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
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Internship Certificates</h1>
                    <p class="text-gray-600 mt-1">Manage certificates for registered users & external students</p>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Total Certificates</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalCertificates; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
                        <span class="text-2xl">🎓</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Registered Users</p>
                        <p class="text-3xl font-bold text-purple-600 mt-1"><?php echo $registeredCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <span class="text-2xl">👤</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">External Students</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1"><?php echo $externalCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <span class="text-2xl">🌐</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Revoked</p>
                        <p class="text-3xl font-bold text-red-600 mt-1"><?php echo $revokedCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <span class="text-2xl">🚫</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV Import Card -->
        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border-2 border-indigo-200 p-6 mb-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="bg-indigo-600 text-white p-3 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Bulk Import Internship Certificates</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            Add certificates for students who completed internships externally.
                        </p>
                        <p class="text-xs text-indigo-700 font-semibold mt-2">
                            ✨ No user account required - works with student name & email only!
                        </p>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <a href="?download_sample=1"
                       class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg border-2 border-indigo-600 bg-white text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download Sample CSV
                    </a>

                    <form action="" method="POST" enctype="multipart/form-data" class="flex gap-2 items-center">
                        <input type="hidden" name="import_csv" value="1">
                        <label class="block cursor-pointer">
                            <input type="file" name="csv_file" accept=".csv" required
                                   class="block w-full text-sm text-gray-700
                                          file:mr-4 file:py-2.5 file:px-5
                                          file:rounded-lg file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-indigo-100 file:text-indigo-700
                                          hover:file:bg-indigo-200 file:cursor-pointer
                                          cursor-pointer">
                        </label>
                        <button type="submit"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 transition-colors shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Upload CSV
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($_SESSION['csv_import_message'])): ?>
                <div class="mt-4 text-sm font-medium
                    <?php echo $_SESSION['csv_import_success'] ? 'text-green-800 bg-green-100 border-green-300' : 'text-red-800 bg-red-100 border-red-300'; ?>
                    px-4 py-3 rounded-lg border-2">
                    <?php
                        echo htmlspecialchars($_SESSION['csv_import_message']);
                        unset($_SESSION['csv_import_message'], $_SESSION['csv_import_success']);
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, company, internship, cert number..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="revoked" <?php echo $statusFilter === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
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
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Cert. No.</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Student</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Company</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Internship</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Duration</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($certificates)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="font-semibold">No certificates found</p>
                                    <p class="text-sm mt-1">Upload CSV to add certificates</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-bold text-gray-900">
                                            <?php echo htmlspecialchars($cert['certificate_number'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($cert['display_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($cert['display_email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($cert['student_type'] === 'External'): ?>
                                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-700">🌐 External</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-purple-100 text-purple-700">👤 Registered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['company_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-700"><?php echo htmlspecialchars($cert['internship_title']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo $cert['duration_months']; ?> months</div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('M Y', strtotime($cert['start_date'])); ?> - <?php echo date('M Y', strtotime($cert['end_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (isset($cert['revoked']) && $cert['revoked']): ?>
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700">🚫 Revoked</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">✅ Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2 flex-wrap">
                                            
                                            <?php if ($cert['student_type'] === 'External'): ?>
                                                <!-- ✅ External Student: View opens modal -->
                                                <button onclick="openViewModal(<?php echo $cert['id']; ?>, '<?php echo htmlspecialchars($cert['display_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cert['internship_title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cert['certificate_number'] ?? $cert['certificate_code'], ENT_QUOTES); ?>')" 
                                                        class="text-blue-600 hover:text-blue-700 font-semibold text-sm whitespace-nowrap">
                                                    👁️ View
                                                </button>
                                                
                                                <!-- ✅ Export with auto-print -->
                                                <button onclick="openExportModal(<?php echo $cert['id']; ?>, '<?php echo htmlspecialchars($cert['display_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cert['internship_title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cert['certificate_number'] ?? $cert['certificate_code'], ENT_QUOTES); ?>')" 
                                                        class="text-green-600 hover:text-green-700 font-semibold text-sm whitespace-nowrap">
                                                    📥 Export
                                                </button>
                                                
                                            <?php else: ?>
                                                <!-- ✅ Registered User: Normal view -->
                                                <a href="/app/views/admin/certificates/internship-view.php?id=<?php echo $cert['id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-700 font-semibold text-sm whitespace-nowrap">
                                                    👁️ View
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="/app/views/verify-certificate.php?code=<?php echo urlencode($cert['certificate_code']); ?>" 
                                               target="_blank"
                                               class="text-purple-600 hover:text-purple-700 font-semibold text-sm whitespace-nowrap">
                                                🔍 Verify
                                            </a>
                                            
                                            <?php if (!isset($cert['revoked']) || !$cert['revoked']): ?>
                                                <button onclick="revokeCertificate(<?php echo $cert['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-700 font-semibold text-sm whitespace-nowrap">
                                                    🚫 Revoke
                                                </button>
                                            <?php else: ?>
                                                <button onclick="restoreCertificate(<?php echo $cert['id']; ?>)" 
                                                        class="text-green-600 hover:text-green-700 font-semibold text-sm whitespace-nowrap">
                                                    ✅ Restore
                                                </button>
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

<!-- ✅ VIEW MODAL (No Auto-Print) -->
<div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-8 relative">
        <button onclick="closeViewModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        
        <h2 class="text-2xl font-bold text-gray-900 mb-2">👁️ View Certificate</h2>
        <p class="text-sm text-gray-600 mb-6">Choose additional company name (optional)</p>
        
        <form id="viewForm" onsubmit="viewCertificate(event)">
            <input type="hidden" id="viewCertId" name="cert_id">
            
            <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">External Student</p>
                <p id="viewStudentName" class="text-base font-bold text-gray-900"></p>
                <p id="viewInternshipTitle" class="text-sm text-gray-600 mt-1"></p>
                <p id="viewCertNumber" class="text-xs font-mono text-indigo-600 mt-1"></p>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Additional Company (Optional)
                </label>
                <input 
                    type="text" 
                    id="viewCompanyName" 
                    name="company_name"
                    placeholder="e.g., Tech Corp, Startup XYZ"
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                <p class="text-xs text-gray-500 mt-2">
                    ℹ️ Leave empty for default (Internship Adda × BlueCollar)
                </p>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeViewModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-colors">
                    👁️ View Certificate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ✅ EXPORT MODAL (Auto-Print) -->
<div id="exportModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-8 relative">
        <button onclick="closeExportModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        
        <h2 class="text-2xl font-bold text-gray-900 mb-2">📥 Export Certificate</h2>
        <p class="text-sm text-gray-600 mb-6">Print with additional company name (optional)</p>
        
        <form id="exportForm" onsubmit="exportCertificate(event)">
            <input type="hidden" id="exportCertId" name="cert_id">
            
            <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">External Student</p>
                <p id="exportStudentName" class="text-base font-bold text-gray-900"></p>
                <p id="exportInternshipTitle" class="text-sm text-gray-600 mt-1"></p>
                <p id="exportCertNumber" class="text-xs font-mono text-indigo-600 mt-1"></p>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Additional Company (Optional)
                </label>
                <input 
                    type="text" 
                    id="exportCompanyName" 
                    name="company_name"
                    placeholder="e.g., Tech Corp, Startup XYZ"
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                <p class="text-xs text-gray-500 mt-2">
                    ℹ️ Leave empty for default (Internship Adda × BlueCollar)
                </p>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeExportModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-colors">
                    📥 Print Certificate
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ========================================
// VIEW MODAL (No Auto-Print)
// ========================================
function openViewModal(certId, studentName, internshipTitle, certNumber) {
    document.getElementById('viewCertId').value = certId;
    document.getElementById('viewStudentName').textContent = studentName;
    document.getElementById('viewInternshipTitle').textContent = internshipTitle;
    document.getElementById('viewCertNumber').textContent = 'Certificate: ' + certNumber;
    document.getElementById('viewCompanyName').value = '';
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
    document.getElementById('viewForm').reset();
}

function viewCertificate(event) {
    event.preventDefault();
    
    const certId = document.getElementById('viewCertId').value;
    const companyName = document.getElementById('viewCompanyName').value.trim();
    
    const url = `/api/export-internship-certificate.php?cert_id=${certId}&company_name=${encodeURIComponent(companyName)}&mode=view`;
    window.open(url, '_blank');
    
    closeViewModal();
}

// ========================================
// EXPORT MODAL (Auto-Print)
// ========================================
function openExportModal(certId, studentName, internshipTitle, certNumber) {
    document.getElementById('exportCertId').value = certId;
    document.getElementById('exportStudentName').textContent = studentName;
    document.getElementById('exportInternshipTitle').textContent = internshipTitle;
    document.getElementById('exportCertNumber').textContent = 'Certificate: ' + certNumber;
    document.getElementById('exportCompanyName').value = '';
    document.getElementById('exportModal').classList.remove('hidden');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
    document.getElementById('exportForm').reset();
}

function exportCertificate(event) {
    event.preventDefault();
    
    const certId = document.getElementById('exportCertId').value;
    const companyName = document.getElementById('exportCompanyName').value.trim();
    
    const url = `/api/export-internship-certificate.php?cert_id=${certId}&company_name=${encodeURIComponent(companyName)}&mode=print`;
    window.open(url, '_blank');
    
    closeExportModal();
}

// ========================================
// Revoke/Restore
// ========================================
function revokeCertificate(id) {
    if (!confirm('⚠️ Revoke this certificate?\n\nThe certificate will no longer be valid for verification.')) return;
    
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
        alert('❌ Network error: ' + err.message);
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
        alert('❌ Network error: ' + err.message);
    });
}
</script>

</body>
</html>
