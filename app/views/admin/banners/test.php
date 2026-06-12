<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Page</h1>";

echo "<h2>1. Session Check:</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>2. File Paths Check:</h2>";
$docRoot = $_SERVER['DOCUMENT_ROOT'];
echo "Document Root: $docRoot<br>";

$dbFile = $docRoot . '/app/config/database.php';
$modelFile = $docRoot . '/app/models/Banner.php';

echo "Database file exists: " . (file_exists($dbFile) ? '✅ YES' : '❌ NO') . " - $dbFile<br>";
echo "Banner model exists: " . (file_exists($modelFile) ? '✅ YES' : '❌ NO') . " - $modelFile<br>";

echo "<h2>3. Try Loading Database:</h2>";
try {
    require_once $dbFile;
    echo "✅ Database config loaded<br>";
    
    $database = new Database();
    echo "✅ Database object created<br>";
    
    $db = $database->connect();
    echo "✅ Database connected<br>";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>4. Try Loading Banner Model:</h2>";
try {
    require_once $modelFile;
    echo "✅ Banner model loaded<br>";
    
    $banner = new Banner($db);
    echo "✅ Banner object created<br>";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}

echo "<h2>5. All Good!</h2>";
echo "<a href='list.php'>Go to Banner List</a>";
?>
