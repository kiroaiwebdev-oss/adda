<?php
/**
 * Advanced File Tree with Auto-Description & Copy
 */

// Files ka description nikalne ka function
function getFileDescription($filename, $path) {
    $content = is_file($path) ? file_get_contents($path, false, null, 0, 500) : ''; // Pehli 500 lines read karein
    
    // Logic based descriptions
    if (strpos($filename, 'Controller') !== false) return "Handles business logic & routing";
    if (strpos($filename, 'Model') !== false)      return "Handles database queries & data";
    if (strpos($filename, 'Config') !== false || strpos($path, 'config/') !== false) return "System configurations";
    if (strpos($filename, '.sql') !== false)       return "Database Schema/Data";
    if (strpos($filename, 'Auth') !== false)       return "Authentication & Security";
    if (strpos($filename, 'Route') !== false)      return "URL mapping & API endpoints";
    if (strpos($path, 'views/') !== false)         return "Frontend UI (HTML/PHP)";
    if (strpos($filename, '.css') !== false)       return "Styling & Design";
    if (strpos($filename, '.js') !== false)        return "Client-side scripting";
    
    // Agar PHP file hai to uska pehla comment dhundne ki koshish karein
    if (preg_match('/\/\*\*([\s\S]*?)\*\//', $content, $matches)) {
        return trim(str_replace(['*', '/'], '', $matches[1]));
    }

    return "Project File"; 
}

function generateTree($dir, $prefix = '') {
    $tree = "";
    $files = array_diff(scandir($dir), array('.', '..', '.git', 'node_modules', 'vendor'));
    $files = array_values($files);

    $count = count($files);
    for ($i = 0; $i < $count; $i++) {
        $file = $files[$i];
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $isLast = ($i === $count - 1);
        $connector = $isLast ? '└── ' : '├── ';
        
        $description = is_dir($path) ? "" : "  # " . getFileDescription($file, $path);
        $tree .= $prefix . $connector . $file . (is_dir($path) ? '/' : '') . $description . PHP_EOL;

        if (is_dir($path)) {
            $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $tree .= generateTree($path, $newPrefix);
        }
    }
    return $tree;
}

$rootName = basename(realpath('./'));
$fullTree = "/$rootName\n" . generateTree(realpath('./'));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Smart File Tree</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; background: #121212; color: #00ff00; padding: 20px; }
        .container { background: #1e1e1e; padding: 20px; border-radius: 10px; border: 1px solid #333; position: relative; }
        pre { white-space: pre-wrap; word-wrap: break-word; font-size: 14px; line-height: 1.5; }
        .copy-btn {
            position: absolute; top: 10px; right: 10px;
            background: #007bff; color: white; border: none;
            padding: 8px 15px; border-radius: 5px; cursor: pointer;
            font-family: sans-serif; font-weight: bold;
        }
        .copy-btn:hover { background: #0056b3; }
        .header { color: #fff; margin-bottom: 15px; font-family: sans-serif; }
    </style>
</head>
<body>

<div class="header">
    <h2>Smart File Tree Explorer</h2>
    <p>PHP is scanning your files and identifying their purpose...</p>
</div>

<div class="container">
    <button class="copy-btn" onclick="copyTree()">Copy Structure</button>
    <pre id="tree-content"><?php echo htmlspecialchars($fullTree); ?></pre>
</div>

<script>
function copyTree() {
    const text = document.getElementById('tree-content').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerText = 'Copied!';
        btn.style.background = '#28a745';
        setTimeout(() => {
            btn.innerText = 'Copy Structure';
            btn.style.background = '#007bff';
        }, 2000);
    });
}
</script>

</body>
</html>