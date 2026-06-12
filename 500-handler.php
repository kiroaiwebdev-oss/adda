<?php
// 500 Error Handler
http_response_code(500);
header('HTTP/1.1 500 Internal Server Error');
require __DIR__ . '/public/errors/500.php';
exit;
?>
