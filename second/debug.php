<?php
// ════════════════════════════════════════
// debug.php v2 — No shell_exec, Hostinger safe
// Path: /second/debug.php
// DELETE after use!
// ════════════════════════════════════════
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

function pass($l,$d=''){echo"<div style='background:#d4edda;color:#155724;padding:10px 14px;margin:4px 0;border-radius:6px;font-family:monospace;font-size:13px'><strong>✅ $l</strong>".($d?"<br><small style='opacity:.8'>$d</small>":'')."</div>";}
function fail($l,$d=''){echo"<div style='background:#f8d7da;color:#721c24;padding:10px 14px;margin:4px 0;border-radius:6px;font-family:monospace;font-size:13px'><strong>❌ $l</strong>".($d?"<br><small style='opacity:.8'>".htmlspecialchars($d)."</small>":'')."</div>";}
function warn($l,$d=''){echo"<div style='background:#fff3cd;color:#856404;padding:10px 14px;margin:4px 0;border-radius:6px;font-family:monospace;font-size:13px'><strong>⚠️ $l</strong>".($d?"<br><small style='opacity:.8'>".htmlspecialchars($d)."</small>":'')."</div>";}
function info($l,$d=''){echo"<div style='background:#d1ecf1;color:#0c5460;padding:10px 14px;margin:4px 0;border-radius:6px;font-family:monospace;font-size:13px'><strong>ℹ️ $l</strong>".($d?"<br><small style='opacity:.8'>".htmlspecialchars($d)."</small>":'')."</div>";}
function sec($t){echo"<h2 style='margin:22px 0 8px;font:700 15px sans-serif;color:#333;border-bottom:2px solid #dee2e6;padding-bottom:5px'>$t</h2>";}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Debug v2</title>
<style>
body{font-family:sans-serif;background:#f5f5f5;padding:20px;max-width:860px;margin:0 auto}
h1{font-size:18px;margin-bottom:4px}
.note{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:16px}
pre{background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;overflow-x:auto;font-size:11px;margin:6px 0;white-space:pre-wrap;word-break:break-all}
</style>
</head>
<body>
<h1>🔍 Debug Report v2 — <?= date('d M Y H:i:s') ?></h1>
<div class="note">⚠️ <strong>DELETE this file after use!</strong> Path: <?= __FILE__ ?></div>

<?php

// ══════════════════════════════════════
sec('1. PHP Version');
// ══════════════════════════════════════
pass('PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
info('PHP ini', 'display_errors=' . ini_get('display_errors') . ' | error_reporting=' . ini_get('error_reporting'));

// ══════════════════════════════════════
sec('2. db.php — Include + PDO Connection');
// ══════════════════════════════════════

$dbFile = __DIR__ . '/db.php';
if (!file_exists($dbFile)) {
    fail('db.php NOT FOUND', $dbFile);
} else {
    try {
        ob_start();
        require_once $dbFile;
        $ob = ob_get_clean();
        if ($ob) warn('db.php has output (should have none)', $ob);
        else     pass('db.php included — no output');
    } catch (Throwable $e) {
        ob_end_clean();
        fail('db.php include FAILED', $e->getMessage() . ' | Line: ' . $e->getLine() . ' | File: ' . $e->getFile());
    }

    if (function_exists('getDB')) {
        pass('getDB() function exists');
        try {
            $pdo = getDB();
            pass('PDO DB Connection SUCCESS');

            // MySQL version
            $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
            pass('MySQL Version: ' . $ver);

            // Table checks
            $tables = [
                'users', 'internships', 'courses',
                'internshipenrollments', 'offlineinternshipapplications',
                'manager_permissions', 'manager_activity_log'
            ];
            foreach ($tables as $tbl) {
                try {
                    $cnt = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
                    pass("Table: $tbl", "Rows: $cnt");
                } catch (PDOException $e) {
                    fail("Table: $tbl MISSING or ERROR", $e->getMessage());
                }
            }

            // Check users with manager role
            try {
                $managers = $pdo->query(
                    "SELECT id, name, email, role FROM users WHERE role IN ('admin','manager') LIMIT 10"
                )->fetchAll(PDO::FETCH_ASSOC);
                if ($managers) {
                    pass('Manager/Admin users found: ' . count($managers));
                    foreach ($managers as $m) {
                        info("User: [{$m['role']}] {$m['name']}", "ID: {$m['id']} | Email: {$m['email']}");
                    }
                } else {
                    fail('NO manager/admin users found in DB!', "INSERT a user with role='manager' first.");
                }
            } catch (PDOException $e) {
                fail('Cannot query users', $e->getMessage());
            }

        } catch (PDOException $e) {
            fail('PDO Connection FAILED', $e->getMessage());
        }
    } else {
        fail('getDB() NOT FOUND in db.php', 'Check function name in db.php');
    }
}

// ══════════════════════════════════════
sec('3. manager_auth.php — Include Test');
// ══════════════════════════════════════

$authFile = __DIR__ . '/manager_auth.php';
if (!file_exists($authFile)) {
    fail('manager_auth.php NOT FOUND');
} else {
    // Read and check for session_start conflicts
    $authCode = file_get_contents($authFile);

    if (strpos($authCode, 'session_start()') !== false) {
        warn('session_start() found in manager_auth.php',
             'Make sure it is not called twice. Wrap with: if(session_status()===PHP_SESSION_NONE) session_start();');
    } else {
        pass('No bare session_start() in manager_auth.php');
    }

    try {
        // Start session before including auth
        if (session_status() === PHP_SESSION_NONE) session_start();
        ob_start();
        require_once $authFile;
        $ob = ob_get_clean();
        if ($ob) warn('manager_auth.php has unexpected output', $ob);
        else     pass('manager_auth.php included OK');
    } catch (Throwable $e) {
        ob_end_clean();
        fail('manager_auth.php FAILED', $e->getMessage() . ' | Line: ' . $e->getLine());
    }

    $funcs = ['requireManagerAccess','getManagerPermissions','checkPermission','can','logAction'];
    foreach ($funcs as $fn) {
        if (function_exists($fn)) pass("Function: $fn()");
        else                      fail("Function MISSING: $fn()");
    }
}

// ══════════════════════════════════════
sec('4. Session State');
// ══════════════════════════════════════

if (session_status() === PHP_SESSION_ACTIVE) {
    pass('Session active | ID: ' . session_id());
    if (!empty($_SESSION['user_id'])) {
        pass('Logged in as: ' . ($_SESSION['user_name'] ?? '?'));
        info('user_id', (string)$_SESSION['user_id']);
        info('user_role', $_SESSION['user_role'] ?? 'not set');
    } else {
        warn('Not logged in', 'No session user_id. Login at manager_login.php first, then re-run debug.');
    }
} else {
    fail('Session NOT active');
}

// ══════════════════════════════════════
sec('5. internships.php — Code Scan');
// ══════════════════════════════════════

$intFile = __DIR__ . '/internships.php';
if (!file_exists($intFile)) {
    fail('internships.php NOT FOUND');
} else {
    $code = file_get_contents($intFile);
    $size = round(strlen($code)/1024, 1);
    pass("internships.php found", "Size: {$size} KB");

    // Bug: PHP in script tag
    if (preg_match('/<script[^>]*>.*?<\?php/si', $code))
        fail('BUG: PHP code inside <script> tag found!');
    else
        pass('No PHP inside <script> tags');

    // Bug: match() on PHP < 8 (but we already have 8.3 so just note)
    if (strpos($code, 'match(') !== false)
        info('match() used', 'OK — PHP 8.3 detected');
    else
        pass('No match() — compatible');

    // Bug: oklch
    $oklchCount = substr_count($code, 'oklch(');
    if ($oklchCount > 0)
        warn("oklch() used $oklchCount times", 'Modern browsers OK, but add rgba fallback for older ones');
    else
        pass('No oklch() — maximum browser compatibility');

    // Bug: logout in wrong place
    if (preg_match('/<script[^>]*>[\s\S]*?\$_GET\[.logout.\]/i', $code))
        fail('BUG: Logout ($_GET logout) inside <script> tag!');
    else
        pass('Logout handler not inside <script>');

    // Check logout is at top (before HTML)
    $htmlStart = strpos($code, '<!DOCTYPE');
    $logoutPos = strpos($code, "logout");
    if ($htmlStart !== false && $logoutPos !== false && $logoutPos < $htmlStart)
        pass('Logout handled before HTML output — correct');
    else
        warn('Logout position unclear', 'Ensure logout redirect is before any HTML/echo output');

    // Check session_start position
    $sessionPos = strpos($code, 'session_start');
    if ($sessionPos !== false && $htmlStart !== false && $sessionPos > $htmlStart)
        fail('BUG: session_start() called AFTER HTML output!');
    elseif ($sessionPos !== false)
        pass('session_start() before HTML — OK');

    // PHP parse via tokenizer (no shell needed)
    $tokens = @token_get_all($code);
    $parseErrors = error_get_last();
    if ($parseErrors && $parseErrors['type'] === E_PARSE)
        fail('PHP Parse Error in internships.php', $parseErrors['message'] . ' line ' . $parseErrors['line']);
    else
        pass('PHP tokenizer — no parse errors detected');
}

// ══════════════════════════════════════
sec('6. All Files — Quick Code Scan');
// ══════════════════════════════════════

$scanFiles = ['manager_dashboard.php','courses.php','offline_apps.php','activity_log.php','manager_login.php'];
foreach ($scanFiles as $sf) {
    $fp = __DIR__ . '/' . $sf;
    if (!file_exists($fp)) { warn("$sf not found"); continue; }
    $c = file_get_contents($fp);

    $bugs = [];
    if (preg_match('/<script[^>]*>.*?<\?php/si', $c))   $bugs[] = 'PHP inside <script>';
    if (preg_match('/header\(.*Location/i', $c)) {
        // Check if header is after output
        $htmlP = strpos($c, '<!DOCTYPE');
        $hdrP  = strpos($c, 'header(');
        if ($htmlP !== false && $hdrP > $htmlP) $bugs[] = 'header() called after HTML output';
    }

    if (empty($bugs)) pass("$sf — no obvious bugs");
    else              fail("$sf — bugs found", implode(' | ', $bugs));
}

// ══════════════════════════════════════
sec('7. PHP Error Log');
// ══════════════════════════════════════

$logPaths = [
    ini_get('error_log'),
    __DIR__ . '/error_log',
    dirname(__DIR__) . '/error_log',
    dirname(__DIR__) . '/logs/error_log',
    '/home/u761369285/logs/error_log',
    '/home/u761369285/domains/internshipadda.com/logs/error_log',
];

$found = false;
foreach ($logPaths as $lp) {
    if ($lp && file_exists($lp) && is_readable($lp)) {
        $lines = file($lp);
        $last  = array_slice($lines, -25);
        pass("Error log found: $lp");
        echo "<pre>" . htmlspecialchars(implode('', $last)) . "</pre>";
        $found = true;
        break;
    }
}
if (!$found) warn('Error log not found / not readable', 'Check Hostinger error logs in hPanel → Logs');

// ══════════════════════════════════════
sec('8. Server Environment');
// ══════════════════════════════════════
info('Server Software', $_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
info('Script directory', __DIR__);
info('Memory Limit', ini_get('memory_limit'));
info('Max Exec Time', ini_get('max_execution_time') . 's');
info('Disabled Functions', ini_get('disable_functions') ?: 'none');
?>

<div style="margin-top:20px;background:#fff3cd;border:1px solid #ffc107;padding:12px;border-radius:6px;font-size:13px">
  <strong>⚠️</strong> Debug complete. <strong>DELETE debug.php now!</strong><br>
  <code>Delete via File Manager or FTP: <?= __FILE__ ?></code>
</div>

</body>
</html>