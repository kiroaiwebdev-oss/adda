<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';

// Get certificate ID
$certId = $_GET['id'] ?? 0;

if (!$certId) {
    die('Certificate ID required');
}

// Auth check
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

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

$auth = new Auth($db);

if (!$auth->check()) {
    die('Unauthorized');
}

// Fetch certificate
$stmt = $db->prepare("
    SELECT 
        ic.*,
        ct.template_html,
        ct.template_css
    FROM internship_certificates ic
    LEFT JOIN certificate_templates ct ON ic.template_id = ct.id
    WHERE ic.id = ? AND ic.user_id = ? AND ic.revoked = 0
");
$stmt->execute([$certId, $auth->id()]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    die('Certificate not found');
}

// Replace variables
$html = $certificate['template_html'];
$replacements = [
    '{{student_name}}' => htmlspecialchars($certificate['student_name']),
    '{{company_name}}' => htmlspecialchars($certificate['company_name']),
    '{{internship_title}}' => htmlspecialchars($certificate['internship_title']),
    '{{start_date}}' => date('F d, Y', strtotime($certificate['start_date'])),
    '{{end_date}}' => date('F d, Y', strtotime($certificate['end_date'])),
    '{{duration_months}}' => $certificate['duration_months'],
    '{{certificate_code}}' => htmlspecialchars($certificate['certificate_code']),
    '{{certificate_number}}' => htmlspecialchars($certificate['certificate_number']),
    '{{issue_date}}' => date('F d, Y', strtotime($certificate['issued_at']))
];

foreach ($replacements as $key => $val) {
    $html = str_replace($key, $val, $html);
}

// Generate PDF filename
$filename = 'Certificate_' . $certificate['certificate_number'] . '.pdf';

// Return HTML for print-to-PDF
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="' . $filename . '"');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: 1122px 794px; margin: 0; }
        body { margin: 0; padding: 0; width: 1122px; height: 794px; }
        <?= $certificate['template_css'] ?>
    </style>
</head>
<body>
    <?= $html ?>
    <script>window.print();</script>
</body>
</html>
