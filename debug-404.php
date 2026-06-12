<?php
/**
 * 404 Debug Tool - Identifies exact problem
 * Upload to ROOT folder and visit: https://lms.devsarun.io/debug-404.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Debug - Problem Identifier</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #2d2d2d;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        }
        h1 {
            color: #00ffff;
            border-bottom: 2px solid #00ff00;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 28px;
        }
        h2 {
            color: #ffff00;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .success {
            background: #004400;
            color: #00ff00;
            border-left: 4px solid #00ff00;
        }
        .error {
            background: #440000;
            color: #ff4444;
            border-left: 4px solid #ff4444;
        }
        .warning {
            background: #444400;
            color: #ffff00;
            border-left: 4px solid #ffff00;
        }
        .info {
            background: #004444;
            color: #00ffff;
            border-left: 4px solid #00ffff;
        }
        code {
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 3px;
            color: #ff00ff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #444;
        }
        th {
            background: #1e1e1e;
            color: #00ffff;
            font-weight: bold;
        }
        td {
            background: #2d2d2d;
        }
        .test-link {
            display: inline-block;
            background: #0066cc;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            font-weight: bold;
        }
        .test-link:hover {
            background: #0088ff;
        }
        .solution {
            background: #002200;
            border: 2px solid #00ff00;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
        }
        .solution h3 {
            color: #00ff00;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 404 Error Debug Tool - Internship Adda</h1>
        <p style="color: #aaa; margin-bottom: 20px;">Identifying exact problem with custom 404 page...</p>

        <?php
        // Initialize problem counter
        $problems = [];
        $solutions = [];

        // TEST 1: Check if .htaccess exists
        echo "<h2>📋 TEST 1: .htaccess File Check</h2>";
        $htaccessPath = __DIR__ . '/.htaccess';
        if (file_exists($htaccessPath)) {
            echo '<div class="status success">✅ .htaccess file EXISTS</div>';
            echo '<div class="info">Location: ' . $htaccessPath . '</div>';
            
            // Read .htaccess content
            $htaccessContent = file_get_contents($htaccessPath);
            echo '<div class="info">File size: ' . strlen($htaccessContent) . ' bytes</div>';
            
            // Check for ErrorDocument directive
            if (strpos($htaccessContent, 'ErrorDocument 404') !== false) {
                preg_match('/ErrorDocument\s+404\s+(.+)/i', $htaccessContent, $matches);
                $errorDocPath = isset($matches[1]) ? trim($matches[1]) : 'Not found';
                echo '<div class="status success">✅ ErrorDocument 404 directive FOUND</div>';
                echo '<div class="info">Points to: <code>' . htmlspecialchars($errorDocPath) . '</code></div>';
                
                // Check if error page exists
                $errorPagePath = __DIR__ . $errorDocPath;
                if (file_exists($errorPagePath)) {
                    echo '<div class="status success">✅ Error page file EXISTS at specified path</div>';
                } else {
                    echo '<div class="status error">❌ Error page file NOT FOUND at: ' . $errorPagePath . '</div>';
                    $problems[] = "Error page file doesn't exist at specified path";
                    $solutions[] = "Create the file at: " . $errorPagePath;
                }
            } else {
                echo '<div class="status error">❌ ErrorDocument 404 directive NOT FOUND</div>';
                $problems[] = ".htaccess missing ErrorDocument directive";
                $solutions[] = "Add this line to .htaccess: ErrorDocument 404 /public/errors/404.php";
            }
        } else {
            echo '<div class="status error">❌ .htaccess file NOT FOUND</div>';
            $problems[] = "No .htaccess file in root directory";
            $solutions[] = "Create .htaccess file in root folder";
        }

        // TEST 2: Check Apache mod_rewrite
        echo "<h2>🔧 TEST 2: Apache Module Check</h2>";
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            $hasRewrite = in_array('mod_rewrite', $modules);
            $hasHeaders = in_array('mod_headers', $modules);
            
            echo '<div class="status ' . ($hasRewrite ? 'success' : 'error') . '">';
            echo $hasRewrite ? '✅' : '❌';
            echo ' mod_rewrite: ' . ($hasRewrite ? 'ENABLED' : 'DISABLED') . '</div>';
            
            echo '<div class="status ' . ($hasHeaders ? 'success' : 'error') . '">';
            echo $hasHeaders ? '✅' : '❌';
            echo ' mod_headers: ' . ($hasHeaders ? 'ENABLED' : 'DISABLED') . '</div>';
            
            if (!$hasRewrite) {
                $problems[] = "mod_rewrite is disabled";
                $solutions[] = "Contact Hostinger support to enable mod_rewrite";
            }
        } else {
            echo '<div class="status warning">⚠️ Cannot detect Apache modules (function not available)</div>';
            echo '<div class="info">This is normal on some shared hosting (like Hostinger)</div>';
        }

        // TEST 3: Check error page files
        echo "<h2>📄 TEST 3: Error Page Files Check</h2>";
        $errorPages = [
            '/public/errors/404.php',
            '/public/errors/500.php',
            '/app/views/errors/404.php',
            '/app/views/errors/500.php',
            '/404-handler.php',
            '/500-handler.php'
        ];

        echo '<table>';
        echo '<tr><th>File Path</th><th>Status</th><th>Size</th><th>Permissions</th></tr>';
        
        $foundPages = [];
        foreach ($errorPages as $page) {
            $fullPath = __DIR__ . $page;
            $exists = file_exists($fullPath);
            $size = $exists ? filesize($fullPath) : 0;
            $perms = $exists ? substr(sprintf('%o', fileperms($fullPath)), -4) : 'N/A';
            
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($page) . '</code></td>';
            echo '<td class="' . ($exists ? 'success' : 'error') . '">';
            echo $exists ? '✅ EXISTS' : '❌ NOT FOUND';
            echo '</td>';
            echo '<td>' . ($exists ? number_format($size) . ' bytes' : '-') . '</td>';
            echo '<td>' . $perms . '</td>';
            echo '</tr>';
            
            if ($exists) {
                $foundPages[] = $page;
            }
        }
        echo '</table>';

        if (empty($foundPages)) {
            $problems[] = "No error page files found";
            $solutions[] = "Create 404.php file in /public/errors/ folder";
        }

        // TEST 4: Server environment
        echo "<h2>🖥️ TEST 4: Server Environment</h2>";
        echo '<table>';
        echo '<tr><th>Variable</th><th>Value</th></tr>';
        
        $serverVars = [
            'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'PHP Version' => PHP_VERSION,
            'PHP SAPI' => php_sapi_name(),
            'Current Directory' => __DIR__,
            'Script Filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
            'Request URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'HTTP Host' => $_SERVER['HTTP_HOST'] ?? 'N/A'
        ];

        foreach ($serverVars as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
            echo '<td><code>' . htmlspecialchars($value) . '</code></td>';
            echo '</tr>';
        }
        echo '</table>';

        // TEST 5: File permissions
        echo "<h2>🔒 TEST 5: Directory Permissions</h2>";
        $dirs = [
            '/' => __DIR__,
            '/public' => __DIR__ . '/public',
            '/public/errors' => __DIR__ . '/public/errors',
            '/app' => __DIR__ . '/app',
            '/app/views' => __DIR__ . '/app/views',
            '/app/views/errors' => __DIR__ . '/app/views/errors'
        ];

        echo '<table>';
        echo '<tr><th>Directory</th><th>Exists</th><th>Writable</th><th>Permissions</th></tr>';
        
        foreach ($dirs as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
            
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($name) . '</code></td>';
            echo '<td class="' . ($exists ? 'success' : 'error') . '">';
            echo $exists ? '✅ YES' : '❌ NO';
            echo '</td>';
            echo '<td class="' . ($writable ? 'success' : 'warning') . '">';
            echo $writable ? '✅ YES' : '⚠️ NO';
            echo '</td>';
            echo '<td>' . $perms . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        // TEST 6: Test direct access
        echo "<h2>🔗 TEST 6: Direct Access Tests</h2>";
        echo '<p>Click these links to test direct access:</p>';
        
        $testUrls = [
            'Custom 404 (public/errors)' => '/public/errors/404.php',
            'Custom 404 (app/views)' => '/app/views/errors/404.php',
            '404 Handler' => '/404-handler.php',
            'Non-existent page' => '/test-random-page-' . time()
        ];

        foreach ($testUrls as $label => $url) {
            echo '<a href="' . htmlspecialchars($url) . '" target="_blank" class="test-link">';
            echo '🔗 Test: ' . htmlspecialchars($label);
            echo '</a>';
        }

        // TEST 7: Check .htaccess syntax
        echo "<h2>📝 TEST 7: .htaccess Content Preview</h2>";
        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            $lines = explode("\n", $content);
            
            echo '<div style="background: #1e1e1e; padding: 20px; border-radius: 5px; overflow-x: auto; max-height: 400px;">';
            echo '<pre style="margin: 0; color: #00ff00; font-size: 12px;">';
            
            $lineNum = 1;
            foreach ($lines as $line) {
                $highlight = '';
                if (stripos($line, 'ErrorDocument') !== false) {
                    $highlight = ' style="background: #004400; color: #00ffff; font-weight: bold;"';
                }
                printf('<span%s>%3d: %s</span>' . "\n", $highlight, $lineNum++, htmlspecialchars($line));
            }
            
            echo '</pre>';
            echo '</div>';
        }

        // PROBLEM SUMMARY
        if (!empty($problems)) {
            echo '<div class="solution">';
            echo '<h3>🚨 IDENTIFIED PROBLEMS (' . count($problems) . '):</h3>';
            echo '<ol>';
            foreach ($problems as $problem) {
                echo '<li style="color: #ff4444; margin: 10px 0;">' . htmlspecialchars($problem) . '</li>';
            }
            echo '</ol>';
            echo '</div>';

            echo '<div class="solution">';
            echo '<h3>💡 RECOMMENDED SOLUTIONS:</h3>';
            echo '<ol>';
            foreach ($solutions as $solution) {
                echo '<li style="color: #00ff00; margin: 10px 0;">' . htmlspecialchars($solution) . '</li>';
            }
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div class="solution">';
            echo '<h3>✅ NO MAJOR PROBLEMS DETECTED!</h3>';
            echo '<p>All basic checks passed. The issue might be:</p>';
            echo '<ul>';
            echo '<li>Server-side caching (clear Hostinger cache)</li>';
            echo '<li>Browser cache (hard refresh with Ctrl+F5)</li>';
            echo '<li>Apache configuration override by hosting provider</li>';
            echo '<li>ErrorDocument directive being ignored by server</li>';
            echo '</ul>';
            echo '</div>';
        }

        // FINAL RECOMMENDATION
        echo '<div class="solution">';
        echo '<h3>🎯 FINAL RECOMMENDATION:</h3>';
        echo '<p style="color: #ffff00; font-size: 16px; margin-bottom: 15px;">';
        echo 'Based on the analysis, here\'s what you should do:';
        echo '</p>';
        
        if (in_array('/public/errors/404.php', $foundPages)) {
            echo '<p style="color: #00ff00;">✅ Your 404 page exists. Problem is likely:</p>';
            echo '<ol style="margin-left: 20px;">';
            echo '<li><strong>Hostinger is overriding .htaccess</strong> - Use PHP-based handler instead</li>';
            echo '<li><strong>Cache issue</strong> - Clear all caches and wait 5 minutes</li>';
            echo '<li><strong>Apache not reading ErrorDocument</strong> - Try RewriteRule method</li>';
            echo '</ol>';
            
            echo '<h4 style="color: #00ffff; margin-top: 20px;">IMMEDIATE FIX:</h4>';
            echo '<p>Create <code>404-handler.php</code> in ROOT with this code:</p>';
            echo '<pre style="background: #1e1e1e; padding: 15px; border-radius: 5px; color: #00ff00; overflow-x: auto;">';
            echo htmlspecialchars('<?php
http_response_code(404);
require __DIR__ . \'/public/errors/404.php\';
exit;
?>');
            echo '</pre>';
            
            echo '<p>Then update .htaccess ErrorDocument line to:</p>';
            echo '<pre style="background: #1e1e1e; padding: 15px; border-radius: 5px; color: #00ff00;">ErrorDocument 404 /404-handler.php</pre>';
        } else {
            echo '<p style="color: #ff4444;">❌ Your 404 page is missing. Create it first!</p>';
        }
        echo '</div>';

        // Footer
        echo '<hr style="border: 1px solid #444; margin: 30px 0;">';
        echo '<p style="text-align: center; color: #888; font-size: 12px;">';
        echo 'Debug completed at ' . date('Y-m-d H:i:s') . ' | ';
        echo '<a href="' . $_SERVER['PHP_SELF'] . '" style="color: #00ffff;">Refresh</a>';
        echo '</p>';
        ?>
    </div>
</body>
</html>
