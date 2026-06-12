<?php
session_start();

echo "<h1>Debug Info</h1>";

echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Server Variables:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'Not set') . "\n";
echo "</pre>";

echo "<h2>Authentication Status:</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User ID: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ User ID NOT SET<br>";
}

if (isset($_SESSION['role'])) {
    echo "✅ Role: " . $_SESSION['role'] . "<br>";
} else {
    echo "❌ Role NOT SET<br>";
}

echo "<hr>";
echo "<h3>Test Links:</h3>";
echo '<a href="/app/views/admin/dashboard.php">Dashboard</a><br>';
echo '<a href="/app/views/admin/banners/list.php">Banners List</a><br>';
echo '<a href="/app/views/admin/users/list.php">Users List</a><br>';
?>
