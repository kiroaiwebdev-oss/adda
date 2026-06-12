<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed');
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/core/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
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

// ✅ Get Parameters
$certId = (int)($_GET['cert_id'] ?? 0);
$companyName = $_GET['company_name'] ?? '';
$mode = $_GET['mode'] ?? 'print'; // 'view' or 'print'

if (!$certId) {
    die('Invalid certificate ID');
}

// ✅ Fetch External Student Certificate
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               c.issued_date,
               co.title as course_title,
               co.description as course_description,
               COALESCE(u.name, c.student_name) as user_name,
               COALESCE(u.email, c.student_email) as user_email,
               CASE WHEN c.user_id IS NULL THEN 'External' ELSE 'Registered' END as student_type
        FROM certificates c
        JOIN courses co ON c.course_id = co.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$certId]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        die('Certificate not found');
    }
    
    // Only allow export for external students
    if ($certificate['student_type'] !== 'External') {
        die('Only external student certificates can be exported');
    }
    
    $displayNumber = $certificate['certificate_number'] ?? $certificate['certificate_code'];
    $issuedDate = date('F j, Y', strtotime($certificate['issued_date']));
    
    // Collaboration text
    $collaborationText = 'Internship Adda';
    if (!empty($companyName)) {
        $collaborationText .= ' × ' . htmlspecialchars($companyName);
    }
    
} catch (Exception $e) {
    die('Error loading certificate');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?php echo htmlspecialchars($certificate['user_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Poppins', sans-serif;
        background: #f5f5f5;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }
    
    /* Certificate Container - A4 Landscape */
    #certificate {
        width: 297mm;
        height: 210mm;
        background: white;
        position: relative;
        box-shadow: 0 10px 50px rgba(0,0,0,0.15);
        overflow: hidden;
    }
    
    /* Purple-Blue Gradient Border */
    .cert-border {
        position: absolute;
        top: 12mm;
        left: 12mm;
        right: 12mm;
        bottom: 12mm;
        border: 4px solid;
        border-image: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%) 1;
        border-radius: 8px;
    }
    
    /* Inner Border - Light Purple */
    .cert-inner-border {
        position: absolute;
        top: 15mm;
        left: 15mm;
        right: 15mm;
        bottom: 15mm;
        border: 2px solid #c4b5fd;
        border-radius: 6px;
    }
    
    /* Corner Decorations - Purple */
    .corner-decoration {
        position: absolute;
        width: 50px;
        height: 50px;
        border: 3px solid #8b5cf6;
    }
    .corner-top-left { top: 17mm; left: 17mm; border-right: none; border-bottom: none; }
    .corner-top-right { top: 17mm; right: 17mm; border-left: none; border-bottom: none; }
    .corner-bottom-left { bottom: 17mm; left: 17mm; border-right: none; border-top: none; }
    .corner-bottom-right { bottom: 17mm; right: 17mm; border-left: none; border-top: none; }
    
    /* Content Area */
    .cert-content {
        position: relative;
        z-index: 10;
        padding: 20mm 25mm 18mm 25mm;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    /* Logo at Top Center */
    .cert-logo {
        text-align: center;
        margin-bottom: 6mm;
    }
    .cert-logo img {
        height: 40px;
        width: auto;
    }
    
    /* Main Content */
    .cert-main {
        text-align: center;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        margin-top: -8mm;
    }
    
    /* Title - Purple Gradient */
    .cert-title {
        font-family: 'Playfair Display', serif;
        font-size: 48px;
        font-weight: 700;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 6px;
        letter-spacing: 2px;
    }
    
    .cert-subtitle {
        font-size: 22px;
        color: #6b7280;
        margin-bottom: 18px;
        font-weight: 400;
    }
    
    .cert-divider {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 16px 0;
    }
    .cert-divider-line {
        width: 70px;
        height: 2px;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
    }
    .cert-divider-icon {
        width: 28px;
        height: 28px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-radius: 50%;
        margin: 0 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
    }
    
    .cert-presented-to {
        font-size: 16px;
        color: #6b7280;
        margin-bottom: 10px;
    }
    
    .cert-name {
        font-family: 'Playfair Display', serif;
        font-size: 44px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 18px;
        border-bottom: 3px solid;
        border-image: linear-gradient(90deg, #6366f1, #8b5cf6, #a855f7) 1;
        display: inline-block;
        padding-bottom: 6px;
    }
    
    .cert-completion-text {
        font-size: 15px;
        color: #6b7280;
        margin-bottom: 12px;
    }
    
    /* Course Title - Purple Gradient Pill */
    .cert-course-title-container {
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto 12px;
        max-width: 90%;
    }
    
    .cert-course-title {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        font-weight: 700;
        color: white;
        background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
        padding: 14px 40px;
        border-radius: 50px;
        display: inline-block;
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        line-height: 1.5;
        text-align: center;
        max-width: 100%;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
    }
    
    /* Collaboration Text - Purple */
    .cert-collaboration {
        text-align: center;
        margin-top: 12px;
        font-size: 12px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 600;
    }
    
    /* Footer Section */
    .cert-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-top: auto;
        padding-top: 8mm;
    }
    
    .cert-footer-left {
        text-align: left;
        flex: 1;
    }
    
    .cert-footer-center {
        text-align: center;
        flex: 1;
    }
    
    .cert-footer-right {
        text-align: right;
        flex: 1;
    }
    
    /* Signature */
    .cert-signature img {
        height: 50px;
        width: auto;
        margin-bottom: 3px;
    }
    .cert-signature-line {
        width: 180px;
        height: 2px;
        background: #d1d5db;
        margin: 3px 0;
    }
    .cert-signature-label {
        font-size: 11px;
        color: #6b7280;
        margin-top: 3px;
    }
    
    /* QR Code */
    .cert-qr img {
        height: 70px;
        width: 70px;
    }
    .cert-qr-label {
        font-size: 9px;
        color: #6b7280;
        margin-top: 3px;
    }
    
    /* Date & Certificate Number */
    .cert-info-label {
        font-size: 10px;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }
    .cert-info-value {
        font-size: 12px;
        font-weight: 600;
        color: #1f2937;
    }
    
    /* Print Button */
    .print-btn {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        z-index: 1000;
    }
    .print-btn:hover {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
    }
    
    /* Print Styles */
    @media print {
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            background: white;
            padding: 0;
        }
        #certificate {
            box-shadow: none;
            page-break-after: avoid;
        }
        .print-btn {
            display: none;
        }
    }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Download PDF</button>
    
    <div id="certificate">
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
                
                <!-- Course Title with Purple Gradient Background -->
                <div class="cert-course-title-container">
                    <div class="cert-course-title">
                        <?php echo htmlspecialchars($certificate['course_title']); ?>
                    </div>
                </div>
                
                <!-- Collaboration Text -->
                <div class="cert-collaboration">
                    <?php echo $collaborationText; ?>
                </div>
            </div>
            
            <!-- Footer Section -->
            <div class="cert-footer">
                <!-- Left: Signature + Stamp -->
                <div class="cert-footer-left">
                    <div class="cert-signature" style="position: relative;">
                        
                        <!-- Stamp -->
                        <img 
                            src="https://i.ibb.co/cSWbL5Qk/muh.png" 
                            alt="Official Stamp"
                            style="
                                position: absolute;
                                left: 30px;
                                top: 5px;
                                width: 60px;
                                opacity: 0.35;
                                transform: rotate(-12deg);
                                z-index: 1;
                            "
                        >

                        <!-- Signature -->
                        <img 
                            src="https://i.ibb.co/8DjXhf2X/image-removebg-preview.png" 
                            alt="Signature"
                            style="position: relative; z-index: 2;"
                        >

                        <div class="cert-signature-line"></div>
                        <div class="cert-signature-label">Authorized Signature</div>
                    </div>
                </div>
                
                <!-- Center: Date & Certificate Number -->
                <div class="cert-footer-center">
                    <div style="margin-bottom: 12px;">
                        <div class="cert-info-label">Issue Date</div>
                        <div class="cert-info-value"><?php echo $issuedDate; ?></div>
                    </div>
                    <div>
                        <div class="cert-info-label">Certificate ID</div>
                        <div class="cert-info-value" style="font-size: 10px;">
                            <?php echo htmlspecialchars($displayNumber); ?>
                        </div>
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

    <script>
        <?php if ($mode === 'print'): ?>
        // ✅ Auto-print for Export mode
        window.onload = function() {
            window.print();
        };
        <?php endif; ?>
    </script>
</body>
</html>
