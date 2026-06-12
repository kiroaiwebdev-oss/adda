<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Step 1: Session check
session_name('ai_studio_session');
session_start();

$debug = [];
$debug['session_name']    = session_name();
$debug['session_id']      = session_id();
$debug['session_data']    = $_SESSION;
$debug['session_user_id'] = $_SESSION['studio_user_id'] ?? 'NOT SET';
$debug['logged_in']       = isset($_SESSION['studio_user_id']) ? 'YES' : 'NO';

// Step 2: DB config check
$configFile = __DIR__ . '/../../ai-studio/config/database.php';
$debug['config_file_exists'] = file_exists($configFile) ? 'YES' : 'NO';

if (file_exists($configFile)) {
    require_once $configFile;
    $debug['config_loaded'] = 'YES';

    // Step 3: AI DB check
    try {
        $aiDb = getAIDb();
        $aiDb->query("SELECT 1");
        $debug['ai_db'] = 'CONNECTED ✅';
    } catch (Exception $e) {
        $debug['ai_db'] = 'FAILED ❌: ' . $e->getMessage();
    }

    // Step 4: LMS DB check
    try {
        $lmsDb = getLMSDb();
        $lmsDb->query("SELECT 1");
        $debug['lms_db'] = 'CONNECTED ✅';
    } catch (Exception $e) {
        $debug['lms_db'] = 'FAILED ❌: ' . $e->getMessage();
    }

    // Step 5: Tables check
    if (isset($lmsDb)) {
        $tables = ['courses', 'chapters', 'topics', 'contentblocks', 'quizzes',
                   'quizquestions', 'internships', 'internshipmodules',
                   'internshiplessons', 'internshipcontentblocks', 'internshipquizquestions'];
        foreach ($tables as $t) {
            try {
                $lmsDb->query("SELECT 1 FROM `{$t}` LIMIT 1");
                $debug['table_' . $t] = '✅ exists';
            } catch (Exception $e) {
                $debug['table_' . $t] = '❌ MISSING';
            }
        }
    }
}

// Step 6: POST data check
$input = json_decode(file_get_contents('php://input'), true);
$debug['post_data_received'] = $input ? 'YES' : 'NO (empty body)';
$debug['post_keys']          = $input ? array_keys($input) : [];

echo json_encode($debug, JSON_PRETTY_PRINT);