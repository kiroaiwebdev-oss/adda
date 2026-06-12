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

$templateId = $_GET['id'] ?? 0;
if (!$templateId) {
    die('Template ID required');
}

require_once __DIR__ . '/../../../models/CertificateTemplate.php';
$templateModel = new CertificateTemplate($db);

$template = $templateModel->getById($templateId);
if (!$template) {
    die('Template not found');
}

// Sample data for preview
$sampleData = [
    'student_name' => 'John Doe',
    'company_name' => $template['company_name'],
    'internship_title' => 'Full Stack Web Development',
    'start_date' => '2025-06-01',
    'end_date' => '2025-12-01',
    'duration_months' => '6',
    'certificate_code' => 'IC-2026-SAMPLE',
    'certificate_number' => 'IA/IC/2026/0001',
    'issue_date' => date('F d, Y')
];

// Replace variables in template
$html = $template['template_html'];
foreach ($sampleData as $key => $value) {
    $html = str_replace('{{' . $key . '}}', $value, $html);
}

$css = $template['template_css'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?php echo htmlspecialchars($template['template_name']); ?></title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .preview-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .preview-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .preview-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .preview-header p {
            color: #666;
            font-size: 14px;
        }
        
        .preview-badge {
            display: inline-block;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }
        
        .certificate-preview {
            margin: 30px 0;
            border: 3px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .preview-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #1a1a1a;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        <?php echo $css; ?>
    </style>
</head>
<body>

<div class="preview-container">
    <div class="preview-header">
        <h1>📋 Template Preview</h1>
        <p><?php echo htmlspecialchars($template['template_name']); ?> - <?php echo htmlspecialchars($template['company_name']); ?></p>
        <span class="preview-badge">Sample Data Preview</span>
    </div>
    
    <div class="certificate-preview">
        <?php echo $html; ?>
    </div>
    
    <div class="preview-actions">
        <button onclick="window.print()" class="btn btn-primary">
            🖨️ Print Preview
        </button>
        <a href="/app/views/admin/certificates/templates.php" class="btn btn-secondary">
            ← Back to Templates
        </a>
        <button onclick="window.close()" class="btn btn-secondary">
            ✕ Close
        </button>
    </div>
</div>

<script>
    // Auto-adjust preview to fit screen
    window.addEventListener('load', function() {
        const preview = document.querySelector('.certificate-preview');
        if (preview) {
            const maxWidth = window.innerWidth - 200;
            if (preview.scrollWidth > maxWidth) {
                preview.style.transform = `scale(${maxWidth / preview.scrollWidth})`;
                preview.style.transformOrigin = 'top center';
            }
        }
    });
</script>

</body>
</html>
