<?php
session_name('ai_studio_session');
session_start();
header('Content-Type: text/html; charset=utf-8');

function ok($msg)   { echo '<div class="ok">✅ '   . htmlspecialchars($msg) . '</div>'; }
function err($msg)  { echo '<div class="err">❌ '  . htmlspecialchars($msg) . '</div>'; }
function warn($msg) { echo '<div class="warn">⚠️ ' . htmlspecialchars($msg) . '</div>'; }
function info($msg) { echo '<div class="info">ℹ️ ' . htmlspecialchars($msg) . '</div>'; }
function head($msg) { echo '<h3>' . htmlspecialchars($msg) . '</h3>'; }
function code_block($c) { echo '<pre>' . htmlspecialchars($c) . '</pre>'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI Studio — Debug</title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { background:#0f1117; font-family:'Courier New',monospace; color:#e2e8f0; padding:20px; font-size:13px; }
.wrap { max-width:900px; margin:0 auto; }
h1 { color:#fff; font-size:20px; margin-bottom:4px; }
h3 { color:#93c5fd; font-size:14px; margin:20px 0 8px; padding:6px 10px; background:rgba(147,197,253,0.08); border-left:3px solid #93c5fd; border-radius:0 6px 6px 0; }
.ok   { color:#4ade80; padding:3px 8px; margin:2px 0; }
.err  { color:#f87171; padding:3px 8px; margin:2px 0; font-weight:bold; }
.warn { color:#fbbf24; padding:3px 8px; margin:2px 0; }
.info { color:#94a3b8; padding:3px 8px; margin:2px 0; }
pre  { background:#1e293b; border:1px solid #334155; border-radius:6px; padding:12px; margin:8px 0; overflow-x:auto; color:#e2e8f0; font-size:12px; white-space:pre-wrap; word-break:break-all; }
.fix  { background:#052e16; border:1px solid #166534; border-radius:8px; padding:16px; margin:12px 0; }
.fix h4 { color:#4ade80; margin-bottom:8px; font-size:13px; }
.sec  { background:#1e293b; border:1px solid #334155; border-radius:10px; padding:16px; margin:12px 0; }
.issue{ background:#2d1515; border:1px solid #7f1d1d; border-radius:6px; padding:12px; margin:8px 0; }
</style>
</head>
<body>
<div class="wrap">
<h1>🔍 AI Studio — Complete Debug Tool</h1>
<p style="color:#64748b;margin-bottom:20px">Time: <?= date('Y-m-d H:i:s') ?> | Server: <?= $_SERVER['SERVER_NAME'] ?? 'unknown' ?></p>

<?php

// ════════════════════════════════════════
// SECTION 1: PHP & SERVER
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 1: PHP & Server');
info('PHP: ' . PHP_VERSION);
info('Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'));
info('File location: ' . __FILE__);
extension_loaded('pdo')       ? ok('PDO loaded')        : err('PDO NOT loaded');
extension_loaded('pdo_mysql') ? ok('PDO MySQL loaded')  : err('PDO MySQL NOT loaded');
extension_loaded('curl')      ? ok('cURL loaded')       : err('cURL NOT loaded');
extension_loaded('json')      ? ok('JSON loaded')       : err('JSON NOT loaded');
echo '</div>';

// ════════════════════════════════════════
// SECTION 2: SESSION
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 2: Session & Login');
info('Session name: ' . session_name());
info('Session ID: '   . session_id());
if (isset($_SESSION['studio_user_id'])) {
    ok('Logged in — user_id: ' . $_SESSION['studio_user_id']);
    info('Role: ' . ($_SESSION['studio_role'] ?? 'not set'));
    info('Name: ' . ($_SESSION['studio_name'] ?? 'not set'));
} else {
    err('NOT logged in — pehle login karo phir yeh page kholo');
}
echo '</div>';

// ════════════════════════════════════════
// SECTION 3: FILE STRUCTURE
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 3: File Structure Check');

$base = dirname(__FILE__);
info('Debug file at: ' . $base);

$check_files = [
    'database.php'     => ['../../ai-studio/config/database.php','../config/database.php','config/database.php','../../config/database.php'],
    'get-settings.php' => ['../api/ai/get-settings.php','../../api/ai/get-settings.php','api/ai/get-settings.php'],
    'settings.php(api)'=> ['../api/ai/settings.php','../../api/ai/settings.php'],
    'test-models.php'  => ['../api/ai/test-models.php','../../api/ai/test-models.php'],
];

$found = [];
foreach ($check_files as $label => $paths) {
    $f = false;
    foreach ($paths as $p) {
        $abs = realpath($base . '/' . $p);
        if ($abs && file_exists($abs)) {
            ok($label . ' → ' . $abs);
            $found[$label] = $abs;
            $f = true; break;
        }
    }
    if (!$f) err($label . ' — NOT FOUND (tried: ' . implode(', ', $paths) . ')');
}
echo '</div>';

// ════════════════════════════════════════
// SECTION 4: DATABASE
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 4: Database Connection');
$pdo = null;
$db_path = $found['database.php'] ?? null;

if ($db_path) {
    ok('database.php found: ' . $db_path);
    try {
        ob_start();
        require_once $db_path;
        $ob = ob_get_clean();
        if ($ob) warn('database.php output: ' . $ob);
        else ok('database.php included cleanly');

        if (function_exists('getAIDb')) {
            ok('getAIDb() function exists');
            try {
                $pdo = getAIDb();
                ok('DB Connection SUCCESS!');
            } catch(Exception $e) {
                err('getAIDb() FAILED: ' . $e->getMessage());
            }
        } else {
            err('getAIDb() function NOT found in database.php');
            $all_funcs = get_defined_functions()['user'];
            $db_funcs  = array_filter($all_funcs, fn($f)=>stripos($f,'db')!==false||stripos($f,'connect')!==false||stripos($f,'pdo')!==false);
            if ($db_funcs) info('Found functions: ' . implode(', ', $db_funcs));
        }
    } catch(Error $e) {
        err('database.php include ERROR: ' . $e->getMessage());
    }
} else {
    err('database.php NOT FOUND — cannot connect to DB');
}
echo '</div>';

// ════════════════════════════════════════
// SECTION 5: TABLE & SETTINGS DATA
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 5: Table & Settings Data');
$active_provider = '';
$saved_model     = '';
$saved_key       = '';

if ($pdo) {
    try {
        $tables = $pdo->query("SHOW TABLES LIKE 'ai_generator_settings'")->fetchAll();
        if ($tables) {
            ok('Table ai_generator_settings EXISTS');
            $cnt = $pdo->query("SELECT COUNT(*) FROM ai_generator_settings")->fetchColumn();
            info('Total rows: ' . $cnt);

            if ($cnt > 0) {
                $rows = $pdo->query("SELECT setting_key, setting_value FROM ai_generator_settings ORDER BY setting_key")->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($rows as $k => $v) {
                    if (strpos($k,'api_key') !== false) {
                        $disp = $v ? (substr($v,0,8).'...'.substr($v,-4).' ['.strlen($v).' chars]') : '❌ EMPTY';
                        info($k . ' = ' . $disp);
                    } else {
                        $v ? ok($k . ' = "' . $v . '"') : warn($k . ' = EMPTY');
                    }
                }
                // Critical checks
                $active_provider = $rows['active_ai_provider'] ?? '';
                if (!$active_provider) {
                    err('active_ai_provider NOT SET!');
                } else {
                    ok('Active provider: ' . $active_provider);
                    $saved_model = $rows[$active_provider . '_model'] ?? '';
                    $saved_key   = $rows[$active_provider . '_api_key'] ?? '';
                    $saved_model ? ok($active_provider . '_model = "' . $saved_model . '"') : err($active_provider . '_model NOT SAVED IN DB!');
                    $saved_key   ? ok($active_provider . '_api_key is set (' . strlen($saved_key) . ' chars)') : err($active_provider . '_api_key NOT SET!');
                }
            } else {
                err('TABLE IS EMPTY — settings kabhi save nahi hui!');
                echo '<div class="fix"><h4>🔧 Fix: Settings page se Save karo</h4></div>';
            }
        } else {
            err('Table ai_generator_settings DOES NOT EXIST!');
            echo '<div class="fix"><h4>🔧 Fix: phpMyAdmin mein yeh SQL chalao</h4>';
            echo '<pre>CREATE TABLE ai_generator_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);</pre></div>';
        }
    } catch(Exception $e) {
        err('Table check failed: ' . $e->getMessage());
    }
} else {
    warn('DB not connected — skip');
}
echo '</div>';

// ════════════════════════════════════════
// SECTION 6: get-settings.php SCAN
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 6: get-settings.php File Scan');
$gs_path = $found['get-settings.php'] ?? null;
if ($gs_path) {
    $c = file_get_contents($gs_path);
    ok('File readable');
    strpos($c,'<?php')                        !== false ? ok('Has PHP tag')              : err('MISSING <?php tag!');
    strpos($c,'json_encode')                  !== false ? ok('Has json_encode')          : err('NO json_encode — not returning JSON!');
    strpos($c,'Content-Type: application/json')!== false ? ok('Has Content-Type header') : warn('Missing Content-Type header');
    strpos($c,'ai_generator_settings')        !== false ? ok('Queries correct table')    : err('Wrong/missing table name!');
    strpos($c,'studio_user_id')               !== false ? ok('Has session check')        : warn('No session check');

    preg_match('/require[_once]*\s*\(?["\']([^"\']+)["\']/', $c, $rm);
    if ($rm) {
        info('require path in file: ' . $rm[1]);
        $resolved = realpath(dirname($gs_path) . '/' . $rm[1]);
        $resolved && file_exists($resolved) ? ok('Require resolves OK: ' . $resolved) : err('Require path BROKEN: ' . $rm[1]);
    }
    info('--- First 400 chars ---');
    code_block(substr($c, 0, 400));
} else {
    err('get-settings.php NOT FOUND');
}
echo '</div>';

// ════════════════════════════════════════
// SECTION 7: DB WRITE TEST
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 7: DB Write/Read Test');
if ($pdo) {
    try {
        $tk = 'debug_test_' . time();
        $st = $pdo->prepare("INSERT INTO ai_generator_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $st->execute([$tk, 'hello123']);
        ok('INSERT worked');
        $rb = $pdo->query("SELECT setting_value FROM ai_generator_settings WHERE setting_key='$tk'")->fetchColumn();
        $rb === 'hello123' ? ok('Read-back verified — DB saving correctly!') : err('Read-back FAILED — value: ' . $rb);
        $pdo->query("DELETE FROM ai_generator_settings WHERE setting_key='$tk'");
        ok('Cleanup done');
    } catch(Exception $e) {
        err('Write test FAILED: ' . $e->getMessage());
    }
} else { warn('DB not connected — skip'); }
echo '</div>';

// ════════════════════════════════════════
// SECTION 8: AUTO-FIXED FILES
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 8: Fixed Files (Copy-Paste Ready)');

// Compute correct relative path from api/ai/ to database.php
$gs_dir  = $found['get-settings.php'] ? dirname($found['get-settings.php']) : null;
$db_rel  = '__DIR__ . \'/../../ai-studio/config/database.php\''; // default
if ($gs_dir && $db_path) {
    $rel = str_replace($gs_dir . DIRECTORY_SEPARATOR, '', $db_path);
    if ($rel !== $db_path) {
        $db_rel = '__DIR__ . \'/' . str_replace('\\','/',$rel) . '\'';
    }
}
info('Detected require path: require_once ' . $db_rel);
?>

<h4 style="color:#fbbf24;margin:14px 0 6px">📄 FILE 1: api/ai/get-settings.php</h4>
<pre><?= htmlspecialchars('<?php
session_name(\'ai_studio_session\');
session_start();
header(\'Content-Type: application/json\');
header(\'Cache-Control: no-cache, no-store\');

if (!isset($_SESSION[\'studio_user_id\'])) {
    echo json_encode([\'success\' => false, \'message\' => \'Not logged in\']);
    exit;
}

require_once ' . $db_rel . ';

try {
    $db   = getAIDb();
    $rows = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode([\'success\' => true, \'settings\' => ($rows ?: new stdClass())]);
} catch (Exception $e) {
    echo json_encode([\'success\' => false, \'message\' => $e->getMessage()]);
}') ?></pre>

<h4 style="color:#fbbf24;margin:14px 0 6px">📄 FILE 2: api/ai/settings.php</h4>
<pre><?= htmlspecialchars('<?php
session_name(\'ai_studio_session\');
session_start();
header(\'Content-Type: application/json\');

if (!isset($_SESSION[\'studio_user_id\'])) {
    echo json_encode([\'success\' => false, \'message\' => \'Unauthorized\']);
    exit;
}

require_once ' . $db_rel . ';
$db     = getAIDb();
$action = $_GET[\'action\'] ?? \'\';

if ($action === \'get\') {
    $rows = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode([\'success\' => true, \'settings\' => ($rows ?: new stdClass())]);
    exit;
}

if ($action === \'save\') {
    if ($_SESSION[\'studio_role\'] !== \'admin\') {
        echo json_encode([\'success\' => false, \'message\' => \'Admin only\']);
        exit;
    }
    $input = json_decode(file_get_contents(\'php://input\'), true);
    if (!$input) { echo json_encode([\'success\' => false, \'message\' => \'Invalid JSON body\']); exit; }

    $allowed = [
        \'active_ai_provider\',
        \'gemini_api_key\',\'gemini_api_key_2\',\'gemini_api_key_3\',\'gemini_model\',
        \'groq_api_key\',  \'groq_api_key_2\',  \'groq_api_key_3\',  \'groq_model\',
        \'openai_api_key\',\'openai_api_key_2\', \'openai_api_key_3\',\'openai_model\',
        \'grok_api_key\',  \'grok_api_key_2\',   \'grok_api_key_3\',  \'grok_model\',
        \'default_language\',\'batch_size\',\'image_search_engine\',\'include_quiz\'
    ];
    $stmt = $db->prepare(
        "INSERT INTO ai_generator_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($allowed as $key) {
        if (array_key_exists($key, $input)) {
            $stmt->execute([$key, $input[$key]]);
        }
    }
    // Read back to verify
    $verified = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode([\'success\' => true, \'message\' => \'Settings saved!\', \'verified\' => $verified]);
    exit;
}

echo json_encode([\'success\' => false, \'message\' => \'Invalid action: \' . $action]);') ?></pre>

<?php
echo '</div>';

// ════════════════════════════════════════
// SECTION 9: FINAL DIAGNOSIS
// ════════════════════════════════════════
echo '<div class="sec">';
head('SECTION 9: Final Diagnosis');

$issues = [];
if (!isset($_SESSION['studio_user_id']))     $issues[] = ['Not logged in',                   'Login karo phir yeh page dobara kholo'];
if (!$db_path)                               $issues[] = ['database.php not found',           'File path check karo'];
if ($db_path && !$pdo)                       $issues[] = ['DB connection failed',             'database.php credentials check karo'];
if (!$gs_path)                               $issues[] = ['get-settings.php missing',         'Section 8 ka FILE 1 banao aur upload karo'];

if ($pdo) {
    try {
        $t = $pdo->query("SHOW TABLES LIKE 'ai_generator_settings'")->fetchAll();
        if (!$t)               $issues[] = ['Table missing',          'Section 5 ka SQL phpMyAdmin mein chalao'];
        else {
            $c2 = $pdo->query("SELECT COUNT(*) FROM ai_generator_settings")->fetchColumn();
            if (!$c2)          $issues[] = ['Table empty',            'Settings page se Save karo'];
            else {
                if (!$active_provider) $issues[] = ['active_ai_provider not set', 'Settings page → provider select → Save'];
                if ($active_provider && !$saved_model) $issues[] = [$active_provider.'_model not in DB', 'Settings → Test Models → model select → Save'];
                if ($active_provider && !$saved_key)   $issues[] = [$active_provider.'_api_key not set', 'Settings → API key daalo → Save'];
            }
        }
    } catch(Exception $e) {}
}

if (empty($issues)) {
    echo '<div style="background:#052e16;border:1px solid #166534;border-radius:8px;padding:16px;text-align:center">';
    echo '<p style="color:#4ade80;font-size:16px;font-weight:bold">🎉 Sab kuch theek hai — koi DB/file issue nahi!</p>';
    echo '<p style="color:#86efac;margin-top:8px">Section 8 ki fixed files use karo aur building.php update karo.</p>';
    echo '</div>';
} else {
    foreach ($issues as $i => $iss) {
        echo '<div class="issue">';
        echo '<p style="color:#f87171;font-weight:bold">'.($i+1).'. ❌ '.htmlspecialchars($iss[0]).'</p>';
        echo '<p style="color:#fca5a5;margin-top:4px">👉 Fix: '.htmlspecialchars($iss[1]).'</p>';
        echo '</div>';
    }
}
echo '</div>';
?>

<p style="color:#334155;margin-top:20px;font-size:11px">⚠️ Kaam ho jaye to debug.php DELETE kar dena — security risk hai</p>
</div>
</body>
</html>