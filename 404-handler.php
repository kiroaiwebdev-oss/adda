<?php
// 404 Handler - Redirect to custom page
http_response_code(404);
header('HTTP/1.1 404 Not Found');
require __DIR__ . '/public/errors/404.php';
exit;
?>
