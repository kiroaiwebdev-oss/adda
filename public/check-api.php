<?php
echo "<h1>API Check</h1>";

$apiFile = $_SERVER['DOCUMENT_ROOT'] . '/api/banners.php';
echo "API file: $apiFile<br>";
echo "Exists: " . (file_exists($apiFile) ? '✅ YES' : '❌ NO') . "<br>";

if (file_exists($apiFile)) {
    echo "<br><h2>Try including API:</h2>";
    try {
        include $apiFile;
        echo "✅ API file loaded successfully";
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage();
    }
} else {
    echo "<br>❌ API file NOT FOUND at: $apiFile";
    echo "<br><br>Expected location: <code>/var/www/html/api/banners.php</code>";
    echo "<br>or <code>/home/yourusername/public_html/api/banners.php</code>";
}
?>
