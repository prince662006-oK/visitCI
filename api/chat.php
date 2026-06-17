<?php
// ============================================================
// api/chat.php — VisitCI — Version avec recherche web fallback
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', '0');
function jsonFatal(string $msg, int $code = 500): void {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['fatal_error' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    jsonFatal("$errstr in ".basename($errfile)." line $errline");
});
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        jsonFatal($e['message']." in ".basename($e['file'])." line ".$e['line']);
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── CONFIGS ─────────────────────────────────────────────────
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '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');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');

// ... (ta config BDD reste identique) ...
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

// Mode diagnostic GET (inchangé)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ... ton code diagnostic existant ...
    exit;
}

// ── LECTURE BODY ────────────────────────────────────────────
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!$body || empty($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message manquant']);
    exit;
}

$userMessage = trim(htmlspecialchars_decode($body['message']));
$history = isset($body['history']) ? array_slice($body['history'], -10) : [];
$canal = $body['canal'] ?? 'web';
$sessionId = $body['session_id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

// ── CONNEXION BDD ────────────────────────────────────────────
function getDB(): ?PDO {
    try {
        return new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5
            ]);
    } catch (Throwable $e) {
        error_log('[VisitCI BDD] '.$e->getMessage());
        return null;
    }
}

// ── KEYWORDS (inchangé) ─────────────────────────────────────
function extractKeywords(string $msg): array {
    // ... ton code extractKeywords existant ...
    // (je ne le recopie pas pour brevité, garde le tien)
    $msg = mb_strtolower($msg);
    // ... reste du code ...
    return [ /* ... */ ];
}

// ── RECHERCHE BDD ────────────────────────────────────────────
function searchPlaces(PDO $pdo, array $kw): array {
    // ... ton code searchPlaces existant ...
    // (garde-le tel quel)
}

// ── RECHERCHE WEB (NOUVEAU) ──────────────────────────────────
function webSearch(string $query): string {
    $query = urlencode($query . " Côte d'Ivoire Abidjan");
    $url = "https://html.duckduckgo.com/html/?q=" . $query;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; VisitCI-Bot)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return "Aucune information trouvée en ligne.";

    // Extraction simple des résultats
    preg_match_all('/<a class="result__a"[^>]*>(.*?)<\/a>.*?<a class="result__snippet"[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

    $results = [];
    foreach (array_slice($matches, 0, 5) as $m) {
        $title = strip_tags($m[1]);
        $snippet = strip_tags($m[2]);
        if (strlen($snippet) > 80) {
            $results[] = "• $title : " . mb_substr($snippet, 0, 180) . "...";
        }
    }

    return empty($results) 
        ? "Aucune information pertinente trouvée en ligne pour cette requête." 
        : "Informations trouvées en ligne :\n" . implode("\n", $results);
}

// ── FORMATAGE CONTEXTE ───────────────────────────────────────
function formatContext(array $places, string $webInfo = ''): string {
    if (empty($places) && empty($webInfo)) {
        return "Aucun lieu trouvé dans la base de données ni en ligne.";
    }

    $lines = ["=== Données disponibles ===\n"];

    if (!empty($places)) {
        $lines[] = "📍 **Lieux de la base de données :**";
        foreach ($places as $i => $p) {
            $lines[] = ($i+1).". {$p['nom']} ({$p['type']}) — {$p['quartier']??''}, {$p['ville']??'Abidjan'}";
            if (!empty($p['categories'])) $lines[] = "   Catégories: {$p['categories']}";
            if ($p['note_moyenne'] > 0) $lines[] = "   Note: {$p['note_moyenne']}/5";
            if (!empty($p['prix_min'])) $lines[] = "   Prix: ".number_format($p['prix_min'],0,',',' ')." XOF";
            if (!empty($p['telephone'])) $lines[] = "   Tél: {$p['telephone']}";
            if (!empty($p['description'])) $lines[] = "   ".mb_substr($p['description'],0,140)."...";
            $lines[] = '';
        }
    }

    if (!empty($webInfo)) {
        $lines[] = "🌐 **Informations trouvées en ligne :**";
        $lines[] = $webInfo;
    }

    return implode("\n", $lines);
}

// ── GROQ ─────────────────────────────────────────────────────
function callGroq(array $messages): string {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => GROQ_MODEL,
            'max_tokens' => 900,
            'temperature' => 0.7,
            'messages' => $messages
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer '.GROQ_API_KEY
        ],
        CURLOPT_TIMEOUT => 25,
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("[GROQ] Erreur $code");
        return "Désolé, je rencontre un problème technique. Réessayez dans un instant.";
    }

    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? "Je n'ai pas pu générer de réponse.";
}

// ── SAUVEGARDE CONVERSATION (inchangé) ───────────────────────
function saveConv(PDO $pdo, string $canal, string $uid, string $q, string $a): void {
    // ... ton code existant ...
}

// ── ORCHESTRATION PRINCIPALE ─────────────────────────────────
$pdo = getDB();
$kw = extractKeywords($userMessage);
$places = $pdo ? searchPlaces($pdo, $kw) : [];

// Recherche web si pas de résultat en BDD
$webInfo = '';
if (empty($places) || count($places) <= 1) {
    $webInfo = webSearch($userMessage);
}

$ctx = formatContext($places, $webInfo);

$systemPrompt = "Tu es VisitCI, l'assistant touristique expert de la Côte d'Ivoire. Tu es chaleureux, précis et utile.

RÈGLES IMPORTANTES :
- Priorise toujours les données de la base de données quand elles existent.
- Si aucune donnée pertinente n'est trouvée dans la base, utilise les informations trouvées en ligne.
- Cite les sources quand tu utilises les données web.
- Sois concis (3 à 6 phrases maximum).
- Ne jamais inventer d'informations.
- Réponds toujours en français sauf demande contraire.";

$msgs = [
    ['role' => 'system', 'content' => $systemPrompt . "\n\nDONNÉES ACTUELLES :\n" . $ctx]
];

foreach ($history as $h) {
    if (in_array($h['role'] ?? '', ['user','assistant'])) {
        $msgs[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}
$msgs[] = ['role' => 'user', 'content' => $userMessage];

$reply = callGroq($msgs);

if ($pdo) saveConv($pdo, $canal, $sessionId, $userMessage, $reply);

echo json_encode([
    'reply' => $reply,
    'places_found' => count($places),
    'web_search_used' => !empty($webInfo),
    'bdd' => $pdo ? 'ok' : 'erreur'
], JSON_UNESCAPED_UNICODE);
