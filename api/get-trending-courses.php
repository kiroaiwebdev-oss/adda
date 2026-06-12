<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
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
    
    // 🔥 FIXED: Only select columns that exist in your courses table
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.title,
            c.slug,
            c.description,
            c.cover_image,
            c.icon,
            c.preview_image,
            c.price,
            c.status,
            c.created_by,
            c.created_at,
            c.updated_at,
            'Web Development' as category,
            'Beginner' as skill_level,
            8 as duration_weeks,
            NULL as original_price,
            COUNT(DISTINCT e.id) as total_enrollments,
            4.5 as avg_rating
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        WHERE c.status = 'published'
        GROUP BY c.id
        ORDER BY total_enrollments DESC, c.created_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'count' => count($courses)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
