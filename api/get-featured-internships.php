<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../app/config/database.php';

try {
    $dbConfig = require __DIR__ . '/../app/config/database.php';
    
    // ✅ Create PDO connection
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // ✅ Query with all needed fields + enrollment count
    $stmt = $db->prepare("
        SELECT 
            i.*,
            COALESCE((SELECT COUNT(*) FROM internship_enrollments WHERE internship_id = i.id), 0) as total_enrollments,
            COALESCE((SELECT AVG(rating) FROM internship_reviews WHERE internship_id = i.id), 4.5) as avg_rating
        FROM internships i
        WHERE i.is_active = 1
        ORDER BY i.created_at DESC
        LIMIT 6
    ");
    
    $stmt->execute();
    $internships = $stmt->fetchAll();
    
    // ✅ Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'internships' => $internships,
        'count' => count($internships)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    // ✅ Database error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    // ✅ General error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
