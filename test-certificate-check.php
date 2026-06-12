<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/config/database.php';

$dbConfig = require __DIR__ . '/app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<style>
        body { font-family: monospace; background: #1a1a1a; color: #0f0; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #0f0; padding: 10px; text-align: left; }
        th { background: #0a0; color: #000; }
        h2 { color: #0ff; }
        .error { color: #f00; }
        .success { color: #0f0; }
    </style>";
    
    echo "<h1>🔍 Certificate Database Check</h1>";
    
    // 1. Check total certificates
    $stmt = $db->query("SELECT COUNT(*) as total FROM certificates WHERE status = 'active'");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h2>📊 Total Active Certificates: " . $total['total'] . "</h2>";
    
    // 2. Get all certificates
    $stmt = $db->query("
        SELECT 
            cert.id,
            cert.user_id,
            cert.course_id,
            cert.certificate_code,
            cert.certificate_number,
            cert.issued_date,
            u.name as user_name,
            c.title as course_title
        FROM certificates cert
        INNER JOIN users u ON cert.user_id = u.id
        INNER JOIN courses c ON cert.course_id = c.id
        WHERE cert.status = 'active'
        ORDER BY cert.issued_date DESC
    ");
    
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($certificates)) {
        echo "<p class='error'>❌ No certificates found in database!</p>";
    } else {
        echo "<h2>📜 All Certificates:</h2>";
        echo "<table>";
        echo "<tr><th>ID</th><th>User</th><th>Course</th><th>Certificate Code</th><th>Issued Date</th></tr>";
        foreach ($certificates as $cert) {
            echo "<tr>";
            echo "<td>" . $cert['id'] . "</td>";
            echo "<td>" . htmlspecialchars($cert['user_name']) . " (ID: " . $cert['user_id'] . ")</td>";
            echo "<td>" . htmlspecialchars($cert['course_title']) . "</td>";
            echo "<td>" . htmlspecialchars($cert['certificate_code']) . "</td>";
            echo "<td>" . $cert['issued_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Check course completion for user 1
    echo "<h2>📚 Course Completion Status (User ID: 1)</h2>";
    
    $stmt = $db->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            COUNT(DISTINCT t.id) as total_topics,
            COUNT(DISTINCT CASE WHEN t.is_mandatory = 1 THEN t.id END) as mandatory_topics,
            COUNT(DISTINCT tp.topic_id) as completed_topics,
            COUNT(DISTINCT CASE WHEN t.is_mandatory = 1 AND tp.is_complete = 1 THEN tp.topic_id END) as completed_mandatory,
            (SELECT COUNT(*) FROM certificates WHERE user_id = 1 AND course_id = c.id AND status = 'active') as has_certificate
        FROM courses c
        LEFT JOIN chapters ch ON ch.course_id = c.id
        LEFT JOIN topics t ON t.chapter_id = ch.id
        LEFT JOIN topic_progress tp ON tp.topic_id = t.id AND tp.user_id = 1
        WHERE c.id IN (SELECT course_id FROM enrollments WHERE user_id = 1)
        GROUP BY c.id, c.title
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($courses)) {
        echo "<p class='error'>❌ User 1 has no enrolled courses!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Course</th><th>Total Topics</th><th>Mandatory</th><th>Completed</th><th>Mandatory Done</th><th>Certificate</th></tr>";
        foreach ($courses as $course) {
            $isComplete = $course['mandatory_topics'] > 0 && $course['completed_mandatory'] >= $course['mandatory_topics'];
            $hasCert = $course['has_certificate'] > 0;
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($course['course_title']) . "</td>";
            echo "<td>" . $course['total_topics'] . "</td>";
            echo "<td>" . $course['mandatory_topics'] . "</td>";
            echo "<td>" . $course['completed_topics'] . "</td>";
            echo "<td>" . $course['completed_mandatory'] . "</td>";
            echo "<td>" . ($hasCert ? "✅ YES" : ($isComplete ? "⚠️ Eligible but not generated" : "❌ Not eligible")) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<p class='success'>✅ Database check complete!</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>
