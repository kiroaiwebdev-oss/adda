<?php
session_name('ai_studio_session');
session_start();
$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
header('Location: index.php');
exit;