<?php
// ============================================================
// api/chat.php — VisitCI — Version anti-erreurs HTML
// ============================================================

// === BLINDAGE TOTAL AU DÉBUT ===
ob_start(); // Capture tout output accidentel

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

function jsonFatal(string $msg, int $code = 500): void {
    ob_clean(); // Vide tout output parasite
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'fatal_error' => true, 
        'message' => $msg,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Gestionnaire d'erreurs
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) return false; // @ supprimé
    jsonFatal("Erreur PHP: $errstr dans $errfile ligne $errline");
});

// Shutdown
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        jsonFatal($error['message'] . " in " . basename($error['file']) . " line " . $error['line']);
    }
    $output = ob_get_clean();
    if ($output && trim($output) !== '' && !json_decode($output)) {
        error_log("[OUTPUT LEAK] " . substr($output, 0, 500));
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === CONFIGS ===
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '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');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');

// Configuration BDD (gardée telle quelle)
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('MYSQL_PRIVATE_URL') ?: '';
if ($dbUrl && strpos($dbUrl, 'mysql') !== false) {
    $p = parse_url($dbUrl);
    define('DB_HOST', $p['host'] ?? '');
    define('DB_PORT', $p['port'] ?? 3306);
    define('DB_NAME', ltrim($p['path'] ?? '', '/'));
    define('DB_USER', $p['user'] ?? '');
    define('DB_PASS', $p['pass'] ?? '');
} else {
    define('DB_HOST', getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'switchyard.proxy.rlwy.net');
    define('DB_PORT', getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway');
    define('DB_USER', getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: 'AqogZmpzTZZrcEYZzYGyGmbWdfPWArhM');
}

// Mode diagnostic
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ... ton code diagnostic (avec ob_clean() au début si besoin) ...
    ob_clean();
    // ton code diagnostic ici
    exit;
}

// === LECTURE INPUT ===
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || empty($body['message'])) {
    jsonFatal('Message manquant', 400);
}

$userMessage = trim(strip_tags($body['message'] ?? ''));
$history = $body['history'] ?? [];
$canal = $body['canal'] ?? 'web';
$sessionId = $body['session_id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

// Le reste de tes fonctions (getDB, extractKeywords, searchPlaces, webSearch, etc.)

// === ORCHESTRATION ===
try {
    $pdo = getDB();
    $kw = extractKeywords($userMessage);
    $places = $pdo ? searchPlaces($pdo, $kw) : [];

    $webInfo = '';
    if (empty($places)) {
        $webInfo = webSearch($userMessage);
    }

    $ctx = formatContext($places, $webInfo);

    $systemPrompt = "Tu es VisitCI..."; // ton prompt

    $msgs = [['role' => 'system', 'content' => $systemPrompt . "\n\nDONNÉES :\n" . $ctx]];
    
    foreach ($history as $h) {
        if (isset($h['role'], $h['content']) && in_array($h['role'], ['user','assistant'])) {
            $msgs[] = $h;
        }
    }
    $msgs[] = ['role' => 'user', 'content' => $userMessage];

    $reply = callGroq($msgs);

    if ($pdo) {
        saveConv($pdo, $canal, $sessionId, $userMessage, $reply);
    }

    ob_clean(); // Sécurité finale
    echo json_encode([
        'reply' => $reply,
        'places_found' => count($places),
        'web_search_used' => !empty($webInfo),
        'success' => true
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    jsonFatal('Erreur interne : ' . $e->getMessage());
}
