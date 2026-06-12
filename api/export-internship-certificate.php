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

$certId = (int)($_GET['cert_id'] ?? 0);
$additionalCompany = $_GET['company_name'] ?? '';
$mode = $_GET['mode'] ?? 'print';

if (!$certId) {
    die('Invalid certificate ID');
}

try {
    $stmt = $db->prepare("
        SELECT ic.*, 
               COALESCE(u.name, ic.student_name) as display_name,
               COALESCE(u.email, ic.student_email) as display_email,
               CASE WHEN ic.user_id IS NULL THEN 'External' ELSE 'Registered' END as student_type
        FROM internship_certificates ic
        LEFT JOIN users u ON ic.user_id = u.id
        WHERE ic.id = ?
    ");
    $stmt->execute([$certId]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        die('Certificate not found');
    }
    
    if ($certificate['student_type'] !== 'External') {
        die('Only external student certificates can be exported');
    }
    
    $studentName = htmlspecialchars($certificate['display_name']);
    $companyName = htmlspecialchars($certificate['company_name']);
    $internshipTitle = htmlspecialchars($certificate['internship_title']);
    $certificateNumber = htmlspecialchars($certificate['certificate_number']);
    $certificateCode = htmlspecialchars($certificate['certificate_code']);
    $startDate = date('F d, Y', strtotime($certificate['start_date']));
    $endDate = date('F d, Y', strtotime($certificate['end_date']));
    $durationMonths = $certificate['duration_months'];
    $issueDate = date('F d, Y', strtotime($certificate['issued_at']));
    
    $companyBrandingText = 'Internship Adda × BlueCollar Connected Technologies Pvt Ltd';
    if (!empty($additionalCompany)) {
        $companyBrandingText = 'Internship Adda × BlueCollar Connected Technologies × ' . htmlspecialchars($additionalCompany);
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
    <title>Internship Certificate - <?php echo $studentName; ?></title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        #certificate {
            width: 1122px;
            height: 794px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px;
            box-sizing: border-box;
            box-shadow: 0 10px 50px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .cert-inner {
            background: #fff;
            width: 100%;
            height: 100%;
            border-radius: 20px;
            padding: 35px 45px;
            box-sizing: border-box;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        /* Corner Decorations */
        .corner-decoration {
            position: absolute;
            width: 80px;
            height: 80px;
        }
        .corner-top-left {
            top: 20px;
            left: 20px;
            border-top: 5px solid #667eea;
            border-left: 5px solid #667eea;
        }
        .corner-top-right {
            top: 20px;
            right: 20px;
            border-top: 5px solid #667eea;
            border-right: 5px solid #667eea;
        }
        .corner-bottom-left {
            bottom: 20px;
            left: 20px;
            border-bottom: 5px solid #667eea;
            border-left: 5px solid #667eea;
        }
        .corner-bottom-right {
            bottom: 20px;
            right: 20px;
            border-bottom: 5px solid #667eea;
            border-right: 5px solid #667eea;
        }
        
        .cert-content {
            position: relative;
            z-index: 10;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        /* Top Section */
        .cert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .cert-header-left, .cert-header-center, .cert-header-right {
            flex: 1;
        }
        
        .cert-header-left {
            text-align: left;
        }
        
        .cert-header-center {
            text-align: center;
        }
        
        .cert-header-right {
            text-align: right;
        }
        
        .cert-header img {
            height: 42px;
            width: auto;
        }
        
        .cert-header-center img {
            height: 48px;
        }
        
        .msme-badge {
            display: inline-block;
            background: #fff;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 6px 10px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }
        
        .msme-badge img {
            height: 35px;
            width: auto;
            display: block;
            margin-bottom: 2px;
        }
        
        .msme-badge-text {
            font-size: 7px;
            color: #667eea;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Title */
        .cert-title-section {
            margin-bottom: 12px;
        }
        
        .cert-title {
            font-size: 42px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 6px;
            letter-spacing: 2px;
        }
        
        .cert-subtitle {
            font-size: 14px;
            color: #666;
            font-style: italic;
        }
        
        /* Main Content */
        .cert-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 10px 0;
        }
        
        .cert-presented-to {
            font-size: 17px;
            color: #555;
            margin-bottom: 14px;
        }
        
        .cert-student-name {
            font-size: 48px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 14px;
            font-family: Georgia, serif;
            text-transform: uppercase;
            line-height: 1.1;
        }
        
        .cert-completion-text {
            font-size: 16px;
            color: #555;
            margin-bottom: 14px;
        }
        
        .cert-internship-title {
            background: linear-gradient(90deg, #667eea, #764ba2);
            color: #fff;
            font-size: 24px;
            font-weight: 600;
            padding: 12px 35px;
            display: inline-block;
            border-radius: 50px;
            margin: 0 auto 10px;
        }
        
        .cert-duration {
            font-size: 14px;
            color: #333;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .cert-company-branding {
            font-size: 13px;
            color: #667eea;
            font-weight: 700;
            margin-top: 14px;
            line-height: 1.4;
        }
        
        /* Footer */
        .cert-footer {
            border-top: 2px solid #667eea;
            padding-top: 12px;
            margin-top: auto;
        }
        
        .cert-footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            align-items: flex-end;
        }
        
        /* ✅ FIXED: Signatures with Seals BEHIND */
        .cert-signature {
            position: relative;
            text-align: center;
        }
        
        /* ✅ Seal positioned EXACTLY behind signature - centered */
        .cert-seal {
            position: absolute;
            width: 85px;
            height: 85px;
            opacity: 0.35;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
        }
        
        /* ✅ Signature wrapper with relative positioning */
        .signature-content {
            position: relative;
            z-index: 1;
        }
        
        .cert-signature img.signature-img {
            height: 45px;
            width: auto;
            margin: 0 auto 4px;
            display: block;
        }
        
        .cert-signature-line {
            width: 165px;
            height: 1.5px;
            background: #333;
            margin: 0 auto 5px;
        }
        
        .cert-signature-label {
            font-size: 10px;
            color: #667eea;
            font-weight: 600;
            line-height: 1.3;
        }
        
        /* QR Code */
        .cert-qr {
            text-align: center;
        }
        
        .cert-qr img {
            width: 70px;
            height: 70px;
            margin: 0 auto 3px;
            display: block;
        }
        
        .cert-qr-label {
            font-size: 8px;
            color: #999;
            margin-bottom: 5px;
        }
        
        .cert-info-text {
            font-size: 9px;
            color: #666;
            font-weight: 600;
        }
        
        .cert-info-small {
            font-size: 8px;
            color: #999;
            margin-top: 2px;
        }
        
        .cert-info-date {
            font-size: 8px;
            color: #999;
            margin-top: 3px;
        }
        
        /* Print Button */
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            z-index: 1000;
            font-size: 14px;
        }
        
        .print-btn:hover {
            background: linear-gradient(135deg, #5568d3, #6a3d91);
            transform: translateY(-2px);
        }
        
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
        <div class="cert-inner">
            <div class="corner-decoration corner-top-left"></div>
            <div class="corner-decoration corner-top-right"></div>
            <div class="corner-decoration corner-bottom-left"></div>
            <div class="corner-decoration corner-bottom-right"></div>
            
            <div class="cert-content">
                <!-- Top Section -->
                <div class="cert-header">
                    <div class="cert-header-left">
                        <img src="https://internshipadda.com/icon.png" alt="Internship Adda">
                    </div>
                    <div class="cert-header-center">
                        <img src="https://i.ibb.co/j9C8b7Tb/bl.png" alt="BlueCollar">
                    </div>
                    <div class="cert-header-right">
                        <div class="msme-badge">
                            <img src="https://etimg.etb2bimg.com/photo/93064603.cms" alt="MSME">
                            <div class="msme-badge-text">MSME Registered</div>
                        </div>
                    </div>
                </div>
                
                <!-- Title -->
                <div class="cert-title-section">
                    <div class="cert-title">INTERNSHIP CERTIFICATE</div>
                    <div class="cert-subtitle">Excellence in Professional Development</div>
                </div>
                
                <!-- Main Content -->
                <div class="cert-main">
                    <div class="cert-presented-to">This certifies that</div>
                    <div class="cert-student-name"><?php echo $studentName; ?></div>
                    <div class="cert-completion-text">has successfully completed the internship in</div>
                    <div class="cert-internship-title"><?php echo $internshipTitle; ?></div>
                    <div class="cert-duration"><?php echo $startDate; ?> — <?php echo $endDate; ?> (<?php echo $durationMonths; ?> months)</div>
                    <div class="cert-company-branding"><?php echo $companyBrandingText; ?></div>
                </div>
                
                <!-- Footer with Signatures -->
                <div class="cert-footer">
                    <div class="cert-footer-grid">
                        
                        <!-- Left Signature -->
                        <div class="cert-signature">
                            <!-- ✅ Seal behind signature -->
                            <img src="https://i.ibb.co/cSWbL5Qk/muh.png" alt="Seal" class="cert-seal">
                            
                            <div class="signature-content">
                                <img src="https://i.ibb.co/8DjXhf2X/image-removebg-preview.png" alt="Signature" class="signature-img">
                                <div class="cert-signature-line"></div>
                                <div class="cert-signature-label">Internship Adda<br>Founder/Co-Founder</div>
                            </div>
                        </div>
                        
                        <!-- Center QR -->
                        <div class="cert-qr">
                            <img src="https://i.ibb.co/DDP3HtYw/qr-code.png" alt="QR">
                            <div class="cert-qr-label">Scan to Verify</div>
                            <div class="cert-info-text">Cert. No: <?php echo $certificateNumber; ?></div>
                            <div class="cert-info-small"><?php echo $certificateCode; ?></div>
                            <div class="cert-info-date">Issue Date: <?php echo $issueDate; ?></div>
                        </div>
                        
                        <!-- Right Signature -->
                        <div class="cert-signature">
                            <!-- ✅ Seal behind signature -->
                            <img src="https://i.ibb.co/yFRbfYzs/image.png" alt="Seal" class="cert-seal">
                            
                            <div class="signature-content">
                                <img src="https://i.ibb.co/WN0c95wW/Md-Tauseef-Alam-removebg-preview.png" alt="Signature" class="signature-img">
                                <div class="cert-signature-line"></div>
                                <div class="cert-signature-label">BlueCollar Connected<br>Technologies Pvt Ltd<br>Director</div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        <?php if ($mode === 'print'): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        <?php endif; ?>
    </script>
</body>
</html>
