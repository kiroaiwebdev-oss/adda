<?php
// Enable error reporting temporarily
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Set JSON header immediately
header('Content-Type: application/json');

try {
    // Check if config exists
    $configPath = __DIR__ . '/../app/config/database.php';
    if (!file_exists($configPath)) {
        throw new Exception('Database config not found at: ' . $configPath);
    }
    
    require_once $configPath;
    
    $dbConfig = require $configPath;
    
    // Database connection
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'] ?? 'localhost',
        $dbConfig['port'] ?? '3306',
        $dbConfig['database'] ?? '',
        $dbConfig['charset'] ?? 'utf8mb4'
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
    
    // Autoload classes
    spl_autoload_register(function ($class) {
        $paths = [
            __DIR__ . '/../app/core/' . $class . '.php',
            __DIR__ . '/../app/models/' . $class . '.php',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return true;
            }
        }
        
        throw new Exception("Class not found: $class");
    });
    
    // Check Auth class
    if (!class_exists('Auth')) {
        throw new Exception('Auth class not found');
    }
    
    $auth = new Auth($db);
    
    // Check if user is authenticated
    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Please login first']);
        exit;
    }
    
    // Check if file uploaded
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No file uploaded',
            'debug' => [
                'method' => $_SERVER['REQUEST_METHOD'],
                'files' => array_keys($_FILES),
                'post' => array_keys($_POST)
            ]
        ]);
        exit;
    }
    
    $file = $_FILES['file'];
    $userId = $auth->user()['id'];
    
    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
        ];
        
        throw new Exception($errors[$file['error']] ?? 'Upload error: ' . $file['error']);
    }
    
    // Check file size
    $maxSize = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum 50MB allowed.');
    }
    
    // Check if tmp file exists
    if (!file_exists($file['tmp_name'])) {
        throw new Exception('Uploaded file not found on server');
    }
    
    // Detect file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Determine file category
    if (strpos($mimeType, 'image/') === 0) {
        $fileType = 'image';
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
    } elseif (strpos($mimeType, 'video/') === 0) {
        $fileType = 'video';
        $allowedMimes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/mpeg'];
    } else {
        throw new Exception('Invalid file type: ' . $mimeType . '. Only images and videos allowed.');
    }
    
    // Check mime type
    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('File type not allowed: ' . $mimeType);
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!$extension) {
        $extension = ($fileType === 'image') ? 'jpg' : 'mp4';
    }
    
    $uniqueName = 'media_' . uniqid() . '_' . time() . '.' . $extension;
    
    // Create directory
    $uploadDir = __DIR__ . '/../public/uploads/' . $fileType . 's/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory: ' . $uploadDir);
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory not writable: ' . $uploadDir);
    }
    
    $uploadPath = $uploadDir . $uniqueName;
    $webPath = '/public/uploads/' . $fileType . 's/' . $uniqueName;
    
    // Move file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file to: ' . $uploadPath);
    }
    
    // Set proper permissions
    chmod($uploadPath, 0644);
    
    // Save to database
    $stmt = $db->prepare("
        INSERT INTO media_files (user_id, file_name, file_path, file_type, file_size, mime_type, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $file['name'],
        $webPath,
        $fileType,
        $file['size'],
        $mimeType
    ]);
    
    $mediaId = $db->lastInsertId();
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'data' => [
            'id' => $mediaId,
            'file_name' => $file['name'],
            'file_path' => $webPath,
            'file_type' => $fileType,
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
            'url' => $webPath
        ]
    ]);
    
} catch (PDOException $e) {
    // Delete file if database failed
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    error_log('Upload DB Error: ' . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    error_log('Upload Error: ' . $e->getMessage());
}
