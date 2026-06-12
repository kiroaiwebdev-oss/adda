<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    require_once __DIR__ . '/../../../config/database.php';
    $dbConfig = require __DIR__ . '/../../../config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
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

try {
    $auth = new Auth($db);
    $auth->requireAdmin();
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

$statusFilter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Fetch certificate requests
try {
    $baseQuery = "
        SELECT 
            icr.id,
            icr.user_id,
            icr.internship_id,
            icr.enrollment_id,
            icr.student_name,
            icr.email,
            icr.phone,
            icr.linkedin_url,
            icr.notes,
            icr.status,
            icr.request_date,
            icr.admin_notes,
            icr.processed_at,
            u.name as user_name,
            u.email as user_email,
            i.title as internship_title,
            i.company_name
        FROM internship_certificate_requests icr
        INNER JOIN users u ON icr.user_id = u.id
        INNER JOIN internships i ON icr.internship_id = i.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($statusFilter && $statusFilter !== 'all') {
        $baseQuery .= " AND icr.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($search) {
        $baseQuery .= " AND (u.name LIKE ? OR i.company_name LIKE ? OR i.title LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $baseQuery .= " ORDER BY icr.request_date DESC";
    
    $stmt = $db->prepare($baseQuery);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("❌ Database Error: " . $e->getMessage());
    $requests = [];
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM internship_certificate_requests WHERE status = 'pending'");
    $stmt->execute();
    $pendingCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pendingCount = 0;
}

// Fetch active templates for modal
try {
    $templatesStmt = $db->prepare("SELECT id, template_name, company_name FROM certificate_templates WHERE is_active = 1 ORDER BY template_name");
    $templatesStmt->execute();
    $templates = $templatesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Certificate Requests - Admin</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Internship Certificate Requests</h1>
                    <p class="text-gray-600 mt-1">Review and approve internship certificate requests</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-yellow-50 px-6 py-3 rounded-lg border-2 border-yellow-200">
                        <span class="text-sm text-gray-600 font-semibold">Pending:</span>
                        <span class="text-2xl font-bold text-yellow-600 ml-2"><?php echo $pendingCount; ?></span>
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
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Student name, company..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Requests Grid -->
        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-600 font-semibold text-lg">No requests found</p>
                <p class="text-gray-500 text-sm mt-2">Try changing the filters or check back later</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($requests as $req): ?>
                    <div class="bg-white rounded-xl border-2 <?php echo $req['status'] === 'pending' ? 'border-yellow-200' : ($req['status'] === 'approved' ? 'border-green-200' : 'border-red-200'); ?> overflow-hidden hover:shadow-xl transition-all"
                         data-request-id="<?php echo $req['id']; ?>"
                         data-student-name="<?php echo htmlspecialchars($req['student_name'] ?? $req['user_name'] ?? 'Unknown'); ?>"
                         data-email="<?php echo htmlspecialchars($req['email'] ?? $req['user_email'] ?? 'N/A'); ?>"
                         data-company="<?php echo htmlspecialchars($req['company_name'] ?? 'N/A'); ?>"
                         data-internship="<?php echo htmlspecialchars($req['internship_title'] ?? 'N/A'); ?>"
                         data-phone="<?php echo htmlspecialchars($req['phone'] ?? ''); ?>"
                         data-linkedin="<?php echo htmlspecialchars($req['linkedin_url'] ?? ''); ?>"
                         data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>">
                        <div class="p-6">
                            <!-- Status Badge -->
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-4 py-1.5 text-xs font-bold rounded-full <?php 
                                    echo $req['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                        ($req['status'] === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'); 
                                ?>">
                                    <?php echo $req['status'] === 'pending' ? '⏳ PENDING' : ($req['status'] === 'approved' ? '✅ APPROVED' : '❌ REJECTED'); ?>
                                </span>
                                <span class="text-xs text-gray-500 font-semibold">📅 <?php echo date('M d, Y', strtotime($req['request_date'])); ?></span>
                            </div>

                            <!-- Student Info -->
                            <div class="mb-4">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                        <?php echo strtoupper(substr($req['student_name'] ?? $req['user_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($req['student_name'] ?? $req['user_name'] ?? 'Unknown'); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($req['email'] ?? $req['user_email'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Internship Details -->
                            <div class="space-y-3 mb-4 pb-4 border-b border-gray-200">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <span class="text-xs font-bold text-gray-500 uppercase block mb-1">🏢 Company</span>
                                    <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($req['company_name'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <span class="text-xs font-bold text-gray-500 uppercase block mb-1">💼 Internship Title</span>
                                    <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($req['internship_title'] ?? 'N/A'); ?></p>
                                </div>
                                
                                <?php if (!empty($req['phone'])): ?>
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-gray-500">📱</span>
                                        <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($req['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($req['linkedin_url'])): ?>
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-blue-600">💼</span>
                                        <a href="<?php echo htmlspecialchars($req['linkedin_url']); ?>" target="_blank" class="text-blue-600 hover:underline truncate">
                                            LinkedIn Profile
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($req['notes'])): ?>
                                    <div class="bg-blue-50 rounded-lg p-3">
                                        <span class="text-xs font-bold text-blue-700 uppercase block mb-1">📝 Student Notes</span>
                                        <p class="text-sm text-blue-900"><?php echo nl2br(htmlspecialchars($req['notes'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Admin Notes -->
                            <?php if (!empty($req['admin_notes'])): ?>
                                <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-3 mb-4">
                                    <span class="text-xs font-bold text-purple-700 uppercase block mb-1">👨‍💼 Admin Notes</span>
                                    <p class="text-sm text-purple-900"><?php echo nl2br(htmlspecialchars($req['admin_notes'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <?php if ($req['status'] === 'pending'): ?>
                                <div class="flex gap-3">
                                    <button onclick="openApproveModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['student_name'] ?? $req['user_name'] ?? 'Student', ENT_QUOTES); ?>')" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-bold text-sm transition-all shadow-md hover:shadow-lg">
                                        ✅ Approve
                                    </button>
                                    <button onclick="openRejectModal(<?php echo $req['id']; ?>)" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-bold text-sm transition-all shadow-md hover:shadow-lg">
                                        ❌ Reject
                                    </button>
                                </div>
                            <?php elseif ($req['status'] === 'approved'): ?>
                                <div class="bg-green-50 border-2 border-green-200 rounded-lg text-center py-3 text-sm text-green-700 font-bold">
                                    ✅ Approved on <?php echo !empty($req['processed_at']) ? date('M d, Y', strtotime($req['processed_at'])) : 'N/A'; ?>
                                </div>
                            <?php else: ?>
                                <div class="bg-red-50 border-2 border-red-200 rounded-lg text-center py-3 text-sm text-red-700 font-bold">
                                    ❌ Rejected on <?php echo !empty($req['processed_at']) ? date('M d, Y', strtotime($req['processed_at'])) : 'N/A'; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-xl max-w-3xl w-full p-8 shadow-2xl max-h-[90vh] overflow-y-auto my-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">✅ Approve Certificate Request</h2>
        
        <!-- Request Details Preview -->
        <div id="requestDetailsPreview" class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="font-bold text-blue-900 mb-3">📋 Request Details:</h3>
            <div class="space-y-2 text-sm">
                <div><span class="font-semibold">Student:</span> <span id="preview_student"></span></div>
                <div><span class="font-semibold">Email:</span> <span id="preview_email"></span></div>
                <div><span class="font-semibold">Company:</span> <span id="preview_company"></span></div>
                <div><span class="font-semibold">Internship:</span> <span id="preview_internship"></span></div>
                <div><span class="font-semibold">Phone:</span> <span id="preview_phone"></span></div>
                <div id="preview_linkedin_div" class="hidden"><span class="font-semibold">LinkedIn:</span> <a id="preview_linkedin" href="#" target="_blank" class="text-blue-600 hover:underline"></a></div>
                <div id="preview_notes_div" class="hidden bg-white p-2 rounded mt-2">
                    <span class="font-semibold">Student Notes:</span>
                    <p id="preview_notes" class="text-gray-700 mt-1"></p>
                </div>
            </div>
        </div>
        
        <form id="approveForm">
            <input type="hidden" id="approve_request_id" name="request_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Student Name on Certificate *</label>
                    <input type="text" id="approve_student_name" name="student_name" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                </div>
                
                <!-- ✅ Template Selection -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Certificate Template *</label>
                    <select id="approve_template_id" name="template_id" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                        <option value="">-- Select Template --</option>
                        <?php foreach ($templates as $tmpl): ?>
                            <option value="<?php echo $tmpl['id']; ?>">
                                <?php echo htmlspecialchars($tmpl['template_name'] . ' - ' . $tmpl['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Choose the company template for this certificate</p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Admin Notes (Optional)</label>
                    <textarea name="admin_notes" rows="3" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Any notes for the student..."></textarea>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-bold transition-all shadow-lg">
                    ✅ Approve & Issue Certificate
                </button>
                <button type="button" onclick="closeApproveModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-bold transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full p-8 shadow-2xl">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">❌ Reject Request</h2>
        <form id="rejectForm">
            <input type="hidden" id="reject_request_id" name="request_id">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Reason for Rejection *</label>
                <textarea name="admin_notes" rows="4" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Explain why this request is being rejected..." required></textarea>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-bold transition-all shadow-lg">
                    ❌ Reject Request
                </button>
                <button type="button" onclick="closeRejectModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-bold transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Store request data globally
    let currentRequestData = {};
    
    function openApproveModal(requestId, studentName) {
        // Find request card by data attribute
        const requestCard = document.querySelector(`[data-request-id="${requestId}"]`);
        
        if (requestCard) {
            currentRequestData = {
                id: requestId,
                student_name: requestCard.dataset.studentName || studentName,
                email: requestCard.dataset.email || '',
                company: requestCard.dataset.company || '',
                internship: requestCard.dataset.internship || '',
                phone: requestCard.dataset.phone || '',
                linkedin: requestCard.dataset.linkedin || '',
                notes: requestCard.dataset.notes || ''
            };
            
            // Fill preview
            document.getElementById('preview_student').textContent = currentRequestData.student_name;
            document.getElementById('preview_email').textContent = currentRequestData.email;
            document.getElementById('preview_company').textContent = currentRequestData.company;
            document.getElementById('preview_internship').textContent = currentRequestData.internship;
            document.getElementById('preview_phone').textContent = currentRequestData.phone || 'N/A';
            
            if (currentRequestData.linkedin) {
                document.getElementById('preview_linkedin').href = currentRequestData.linkedin;
                document.getElementById('preview_linkedin').textContent = 'View Profile';
                document.getElementById('preview_linkedin_div').classList.remove('hidden');
            } else {
                document.getElementById('preview_linkedin_div').classList.add('hidden');
            }
            
            if (currentRequestData.notes) {
                document.getElementById('preview_notes').textContent = currentRequestData.notes;
                document.getElementById('preview_notes_div').classList.remove('hidden');
            } else {
                document.getElementById('preview_notes_div').classList.add('hidden');
            }
        }
        
        document.getElementById('approve_request_id').value = requestId;
        document.getElementById('approve_student_name').value = studentName;
        document.getElementById('approveModal').classList.remove('hidden');
    }
    
    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }
    
    function openRejectModal(requestId) {
        document.getElementById('reject_request_id').value = requestId;
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
    
    // Approve Form Submit
    document.getElementById('approveForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Validate template selection
        if (!data.template_id) {
            alert('⚠️ Please select a certificate template');
            return;
        }
        
        console.log('📤 Sending approve request:', data);
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = '⏳ Processing...';
        
        try {
            const response = await fetch('/api/internship-certificates.php?action=approve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            console.log('📥 Response:', result);
            
            if (result.success) {
                alert('✅ Certificate approved and issued successfully!\n\nCertificate Code: ' + result.certificate_code);
                location.reload();
            } else {
                alert('❌ Error: ' + (result.error || 'Failed to approve certificate'));
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (err) {
            console.error('❌ Error:', err);
            alert('❌ Error: ' + err.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Reject Form Submit
    document.getElementById('rejectForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        console.log('📤 Sending reject request:', data);
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = '⏳ Processing...';
        
        try {
            const response = await fetch('/api/internship-certificates.php?action=reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            console.log('📥 Response:', result);
            
            if (result.success) {
                alert('✅ Request rejected successfully');
                location.reload();
            } else {
                alert('❌ Error: ' + (result.error || 'Failed to reject request'));
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (err) {
            console.error('❌ Error:', err);
            alert('❌ Error: ' + err.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeApproveModal();
            closeRejectModal();
        }
    });
</script>

</body>
</html>
