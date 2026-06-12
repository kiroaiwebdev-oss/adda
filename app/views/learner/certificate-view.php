<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
$dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

spl_autoload_register(function ($class) {
$paths = [
__DIR__ . '/../../core/' . $class . '.php',
__DIR__ . '/../../models/' . $class . '.php',
];
foreach ($paths as $path) {
if (file_exists($path)) {
require_once $path;
return;
}
}
});

$auth = new Auth($db);

if (!$auth->check()) {
header('Location: /public/login.php');
exit;
}

$certificateId = $_GET['id'] ?? 0;
if (!$certificateId) {
header('Location: /app/views/learner/internship-certificates.php');
exit;
}

// Get certificate with template
$stmt = $db->prepare("
SELECT
ic.*,
ct.template_html,
ct.template_css,
ct.template_name
FROM internship_certificates ic
LEFT JOIN certificate_templates ct ON ic.template_id = ct.id
WHERE ic.id = ? AND ic.user_id = ? AND ic.revoked = 0
");
$stmt->execute([$certificateId, $auth->id()]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
header('Location: /app/views/learner/internship-certificates.php');
exit;
}

// Replace template variables
$templateHtml = $certificate['template_html'] ?? '<p>Template not found</p>';
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

foreach ($replacements as $placeholder => $value) {
$templateHtml = str_replace($placeholder, $value, $templateHtml);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($certificate['student_name']) ?> - Certificate</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">

<style>
* {
margin: 0;
padding: 0;
box-sizing: border-box;
}

html, body {
width: 100%;
height: 100%;
margin: 0;
padding: 0;
}

body {
background: #f0f0f0;
display: flex;
justify-content: center;
align-items: center;
font-family: Arial, sans-serif;
}

/* Container for screen view */
#screen-container {
background: white;
box-shadow: 0 10px 50px rgba(0,0,0,0.2);
margin: 20px;
max-width: 95vw;
max-height: 90vh;
}

/* Actual certificate - EXACT LANDSCAPE SIZE */
#certificate {
width: 11.22in;
height: 7.94in;
position: relative;
overflow: hidden;
background: white;
}

/* Controls - only on screen */
.controls {
position: fixed;
top: 20px;
right: 20px;
z-index: 1000;
display: flex;
gap: 12px;
}

.btn {
background: #22c55e;
color: white;
padding: 14px 28px;
border: none;
border-radius: 10px;
font-weight: bold;
font-size: 15px;
cursor: pointer;
box-shadow: 0 4px 14px rgba(34,197,94,0.4);
transition: all 0.3s ease;
font-family: Arial, sans-serif;
display: flex;
align-items: center;
gap: 8px;
}

.btn:hover {
background: #16a34a;
transform: translateY(-2px);
box-shadow: 0 6px 20px rgba(34,197,94,0.5);
}

.btn:active {
transform: translateY(0);
}

.btn-secondary {
background: #6b7280;
}

.btn-secondary:hover {
background: #4b5563;
}

/* CRITICAL: Print styles for LANDSCAPE A4 */
@media print {
@page {
size: A4 landscape;
margin: 0;
}

html, body {
width: 297mm;
height: 210mm;
margin: 0;
padding: 0;
background: white;
display: block;
}

#screen-container {
box-shadow: none;
margin: 0;
max-width: none;
max-height: none;
width: 297mm;
height: 210mm;
}

#certificate {
width: 297mm;
height: 210mm;
page-break-after: avoid;
page-break-before: avoid;
page-break-inside: avoid;
margin: 0;
transform: none !important;
}

.controls {
display: none !important;
}
}

/* Responsive scaling for screen preview only */
@media screen and (max-width: 1200px) {
#screen-container {
transform: scale(0.8);
transform-origin: center;
}
}

@media screen and (max-width: 900px) {
#screen-container {
transform: scale(0.6);
transform-origin: center;
}
}

@media screen and (max-width: 600px) {
#screen-container {
transform: scale(0.4);
transform-origin: center;
}
}

/* Template styles */
<?= $certificate['template_css'] ?? '' ?>
</style>
</head>
<body>
<!-- Controls -->
<div class="controls">
<button onclick="printCertificate()" class="btn">
<span style="font-size: 18px;">🖨️</span>
<span>Print / Save PDF</span>
</button>
<button onclick="window.close()" class="btn btn-secondary">
<span style="font-size: 18px;">✖️</span>
<span>Close</span>
</button>
</div>

<!-- Certificate -->
<div id="screen-container">
<div id="certificate">
<?= $templateHtml ?>
</div>
</div>

<script>
function printCertificate() {
window.print();
}

// ✅ AUTO-PRINT if autoprint parameter is present
window.addEventListener('load', function() {
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('autoprint') === '1') {
// Auto-trigger print dialog after 500ms
setTimeout(function() {
window.print();
}, 500);
}
});

// Keyboard shortcut
document.addEventListener('keydown', function(e) {
if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
e.preventDefault();
printCertificate();
}
});
</script>
</body>
</html>