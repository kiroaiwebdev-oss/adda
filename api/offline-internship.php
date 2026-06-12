<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Database connection
    $configPath = __DIR__ . '/../app/config/database.php';
    
    if (!file_exists($configPath)) {
        throw new Exception("Config not found");
    }
    
    $dbConfig = require $configPath;
    
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], 
        $dbConfig['port'], 
        $dbConfig['database'], 
        $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? 'create';
    
    // ========================================
    // ACTION: CREATE APPLICATION (Public)
    // ========================================
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception("Invalid input data");
        }
        
        // Validate required fields
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $internship_type = trim($input['internship_type'] ?? '');
        
        if (empty($name) || empty($email) || empty($phone) || empty($internship_type)) {
            throw new Exception("Name, email, phone, and internship type are required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        // Optional fields
        $college = trim($input['college'] ?? '');
        $course = trim($input['course'] ?? '');
        $year = trim($input['year'] ?? '');
        $duration = trim($input['duration'] ?? '');
        $message = trim($input['message'] ?? '');
        
        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO offline_internship_applications 
            (name, email, phone, college, course, year, internship_type, duration, message, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $name, 
            $email, 
            $phone, 
            $college, 
            $course, 
            $year, 
            $internship_type, 
            $duration, 
            $message
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Application submitted successfully',
            'application_id' => $db->lastInsertId()
        ]);
        exit;
    }
    
    // ========================================
    // ADMIN-ONLY ACTIONS BELOW
    // ========================================
    
    // Check admin authentication
    session_start();
    
    // Load Auth class
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
    
    if (!$auth->check() || !$auth->isAdmin()) {
        http_response_code(403);
        throw new Exception("Admin access required");
    }
    
    // ========================================
    // ACTION: GET SINGLE APPLICATION
    // ========================================
    if ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $id = intval($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception("Invalid application ID");
        }
        
        $stmt = $db->prepare("SELECT * FROM offline_internship_applications WHERE id = ?");
        $stmt->execute([$id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            throw new Exception("Application not found");
        }
        
        echo json_encode([
            'success' => true,
            'data' => $application
        ]);
        exit;
    }
    
    // ========================================
    // ACTION: UPDATE APPLICATION STATUS/FIELDS
    // ========================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception("Invalid input data");
        }
        
        $id = intval($input['id'] ?? 0);
        $field = $input['field'] ?? '';
        $value = $input['value'] ?? '';
        
        if ($id <= 0) {
            throw new Exception("Invalid application ID");
        }
        
        // Validate field
        $allowedFields = ['is_contacted', 'is_enrolled', 'status', 'notes'];
        
        if (!in_array($field, $allowedFields)) {
            throw new Exception("Invalid field");
        }
        
        // Handle boolean fields
        if ($field === 'is_contacted' || $field === 'is_enrolled') {
            $value = $value ? 1 : 0;
            
            // Set timestamp
            if ($value === 1) {
                $timestampField = $field === 'is_contacted' ? 'contacted_at' : 'enrolled_at';
                $stmt = $db->prepare("UPDATE offline_internship_applications SET $field = ?, $timestampField = NOW() WHERE id = ?");
            } else {
                $stmt = $db->prepare("UPDATE offline_internship_applications SET $field = ? WHERE id = ?");
            }
            $stmt->execute([$value, $id]);
        } 
        // Handle status field
        elseif ($field === 'status') {
            $validStatuses = ['pending', 'contacted', 'enrolled', 'rejected'];
            if (!in_array($value, $validStatuses)) {
                throw new Exception("Invalid status value");
            }
            
            $stmt = $db->prepare("UPDATE offline_internship_applications SET status = ? WHERE id = ?");
            $stmt->execute([$value, $id]);
            
            // Auto-update checkboxes based on status
            if ($value === 'contacted') {
                $db->prepare("UPDATE offline_internship_applications SET is_contacted = 1, contacted_at = NOW() WHERE id = ?")->execute([$id]);
            } elseif ($value === 'enrolled') {
                $db->prepare("UPDATE offline_internship_applications SET is_contacted = 1, is_enrolled = 1, contacted_at = COALESCE(contacted_at, NOW()), enrolled_at = NOW() WHERE id = ?")->execute([$id]);
            }
        } 
        // Handle notes field
        elseif ($field === 'notes') {
            $stmt = $db->prepare("UPDATE offline_internship_applications SET notes = ? WHERE id = ?");
            $stmt->execute([$value, $id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Updated successfully'
        ]);
        exit;
    }
    
    // ========================================
    // ACTION: DELETE APPLICATION
    // ========================================
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception("Invalid input data");
        }
        
        $id = intval($input['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception("Invalid application ID");
        }
        
        // Check if application exists
        $stmt = $db->prepare("SELECT id FROM offline_internship_applications WHERE id = ?");
        $stmt->execute([$id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Application not found");
        }
        
        // Delete application
        $stmt = $db->prepare("DELETE FROM offline_internship_applications WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Application deleted successfully'
        ]);
        exit;
    }
    
    // ========================================
    // ACTION: GET ALL APPLICATIONS (ADMIN)
    // ========================================
    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $status = $_GET['status'] ?? 'all';
        $search = $_GET['search'] ?? '';
        
        $query = "SELECT * FROM offline_internship_applications WHERE 1=1";
        $params = [];
        
        if ($status !== 'all') {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        
        if (!empty($search)) {
            $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $applications,
            'count' => count($applications)
        ]);
        exit;
    }
    
    // ========================================
    // ACTION: GET STATISTICS (ADMIN)
    // ========================================
    if ($action === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted,
                SUM(CASE WHEN status = 'enrolled' THEN 1 ELSE 0 END) as enrolled,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN is_contacted = 1 THEN 1 ELSE 0 END) as total_contacted,
                SUM(CASE WHEN is_enrolled = 1 THEN 1 ELSE 0 END) as total_enrolled
            FROM offline_internship_applications
        ");
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        exit;
    }
    
    // Invalid action
    throw new Exception("Invalid action");
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
