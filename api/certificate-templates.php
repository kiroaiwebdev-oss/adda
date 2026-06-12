<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
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

$action = $_GET['action'] ?? '';

require_once __DIR__ . '/../app/models/CertificateTemplate.php';
$templateModel = new CertificateTemplate($db);

try {
    switch ($action) {
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $templateId = $data['template_id'] ?? null;
            
            if (!$templateId) {
                throw new Exception('Template ID required');
            }
            
            $result = $templateModel->update($templateId, $data);
            
            if (!$result) {
                throw new Exception('Failed to update template');
            }
            
            echo json_encode(['success' => true, 'message' => 'Template updated']);
            break;
            
        case 'toggle':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $templateId = $data['template_id'] ?? null;
            
            if (!$templateId) {
                throw new Exception('Template ID required');
            }
            
            $result = $templateModel->toggleActive($templateId);
            
            if (!$result) {
                throw new Exception('Failed to toggle template');
            }
            
            echo json_encode(['success' => true, 'message' => 'Template status updated']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
