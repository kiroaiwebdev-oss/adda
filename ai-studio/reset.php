<?php
/**
 * TEMP FILE — Password Reset
 * USE KARO AUR TURANT DELETE KARO!
 */
require_once __DIR__ . '/config/database.php';
$db = getAIDb();

// ── YAHAN APNA NAYA PASSWORD LIKHO ──
$newPassword = 'Admin@1234';
$email       = 'admin@internshipadda.com';
// ─────────────────────────────────────

$hashed = password_hash($newPassword, PASSWORD_BCRYPT);
$stmt   = $db->prepare("UPDATE ai_generator_users SET password = ? WHERE email = ?");
$done   = $stmt->execute([$hashed, $email]);

if ($done && $stmt->rowCount() > 0) {
    echo "<h2 style='color:green'>✅ Password update ho gaya!</h2>";
    echo "<p>Email: <strong>{$email}</strong></p>";
    echo "<p>New Password: <strong>{$newPassword}</strong></p>";
    echo "<hr>";
    echo "<p style='color:red'><strong>⚠️ AB IS FILE KO TURANT DELETE KARO SERVER SE!</strong></p>";
    echo "<p><a href='index.php'>Login Page pe jao →</a></p>";
} else {
    echo "<h2 style='color:red'>❌ Update fail hua</h2>";
    echo "<p>Email check karo: {$email}</p>";

    // User exist karta hai check karo
    $check = $db->prepare("SELECT id, email FROM ai_generator_users WHERE email = ?");
    $check->execute([$email]);
    $user = $check->fetch();

    if ($user) {
        echo "<p style='color:orange'>User found: ID = {$user['id']}</p>";
    } else {
        echo "<p style='color:red'>User NOT found — pehle insert karo</p>";

        // Auto insert karo agar user nahi hai
        $insert = $db->prepare("INSERT INTO ai_generator_users (name, email, password, role, is_active) VALUES (?,?,?,?,?)");
        $insert->execute(['Studio Admin', $email, $hashed, 'admin', 1]);
        echo "<p style='color:green'>✅ New admin user create kar diya! Ab login karo.</p>";
    }
}
?>