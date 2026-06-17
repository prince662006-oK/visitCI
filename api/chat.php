<?php
// ============================================================
//  api/chat.php — VisitCI — Avec fallback recherche web
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

// Clé optionnelle pour la recherche web (serper.dev a un plan gratuit).
// Si absente, le fallback web sera simplement désactivé sans planter.
define('SERPER_API_KEY', getenv('SERPER_API_KEY') ?: '');

$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('MYSQL_PRIVATE_URL') ?: '';
if ($dbUrl && strpos($dbUrl, 'mysql') !== false) {
    $p = parse_url($dbUrl);
    define('DB_HOST', $p['host'] ?? '');
    define('DB_PORT', $p['port'] ?? 3306);
    define('DB_NAME', ltrim($p['path'] ?? '', '/'));
    define('DB_USER', $p['user'] ?? '');
    define('DB_PASS', $p['pass'] ?? '');
} else {
    define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'mysql-oykn.railway.internal');
    define('DB_PORT', getenv('MYSQLPORT')     ?: getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway');
    define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: 'AqogZmpzTZZrcEYZzYGyGmbWdfPWArhM');
}

// ── MODE DIAGNOSTIC (GET) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $diag = ['php_version' => PHP_VERSION];
    $diag['ext_pdo_mysql'] = extension_loaded('pdo_mysql');
    $diag['db_host'] = DB_HOST;
    $diag['web_search_enabled'] = SERPER_API_KEY ? true : false;
    try {
        $pdo = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $diag['bdd'] = 'CONNECTÉE ✅';
        $diag['lieux_count'] = (int) $pdo->query('SELECT COUNT(*) FROM lieu')->fetchColumn();
    } catch (Throwable $e) {
        $diag['bdd'] = 'ERREUR ❌';
        $diag['bdd_message'] = $e->getMessage();
    }
    echo json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── LECTURE BODY (POST) ──────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!$body || empty($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message manquant', 'recu' => $raw]);
    exit;
}

$userMessage = trim(htmlspecialchars_decode($body['message']));
$history     = isset($body['history']) ? array_slice($body['history'], -10) : [];
$canal       = $body['canal'] ?? 'web';
$sessionId   = $body['session_id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

// ── CONNEXION BDD ────────────────────────────────────────────
function getDB(): ?PDO {
    try {
        return new PDO(
            'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false,
             PDO::ATTR_TIMEOUT => 5]
        );
    } catch (Throwable $e) {
        error_log('[VisitCI BDD] '.$e->getMessage());
        return null;
    }
}

// ── MOTS-CLÉS ────────────────────────────────────────────────
function extractKeywords(string $msg): array {
    $msg = mb_strtolower($msg);
    $types = [
        'restaurant' => ['restaurant','maquis','manger','dîner','déjeuner','bouffe','cuisine','plat','attiéké','alloco','foutou','nourriture','faim'],
        'hotel'      => ['hôtel','hotel','résidence','residence','hébergement','chambre','dormir','nuit','séjour','auberge','logement'],
        'activite'   => ['activité','loisir','sortir','boîte','club','bar','musée','concert','sport','gym','spa','visiter','divertissement'],
        'transport'  => ['transport','taxi','woro','gbaka','bus','moto','voiture','aller','trajet','bateau','lagune','ferry'],
        'plage'      => ['plage','mer','sable','bain','nager','surf','site','monument','patrimoine','nature'],
        'sante'      => ['pharmacie','médecin','hôpital','clinique','urgence','santé','docteur','médicament','soins','malade'],
        'banque'     => ['banque','atm','distributeur','argent','change','mobile money','orange money','wave','retrait'],
        'marche'     => ['marché','shopping','acheter','souvenir','artisanat','boutique','centre commercial','tissu'],
    ];
    $quartiers = ['cocody','plateau','marcory','yopougon','adjamé','treichville','abobo','koumassi','riviera','deux plateaux','angré','bingerville'];

    $found_types = [];
    $found_quartiers = [];
    foreach ($types as $type => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) { $found_types[] = $type; break; }
        }
    }
    foreach ($quartiers as $q) {
        if (str_contains($msg, $q)) $found_quartiers[] = $q;
    }
    $budget = null;
    if (preg_match('/moins de (\d[\d\s]*)\s*(fcfa|xof|f\b|francs?)?/iu', $msg, $m)) {
        $budget = (int) preg_replace('/\s+/', '', $m[1]);
    }
    return [
        'types'     => array_unique($found_types),
        'quartiers' => $found_quartiers,
        'budget'    => $budget,
        'open_now'  => str_contains($msg,'ouvert') || str_contains($msg,'nuit') || str_contains($msg,'24h'),
        'raw'       => $msg,
    ];
}

// ── RECHERCHE BDD ────────────────────────────────────────────
function searchPlaces(PDO $pdo, array $kw): array {
    $conditions = ['l.actif = 1'];
    $params = [];
    if (!empty($kw['types'])) {
        $ph = implode(',', array_fill(0, count($kw['types']), '?'));
        $conditions[] = "tl.slug IN ($ph)";
        $params = array_merge($params, $kw['types']);
    }
    if (!empty($kw['quartiers'])) {
        $qc = [];
        foreach ($kw['quartiers'] as $q) { $qc[] = 'l.quartier LIKE ?'; $params[] = "%$q%"; }
        $conditions[] = '('.implode(' OR ', $qc).')';
    }
    if ($kw['budget']) {
        $conditions[] = '(t.prix_min IS NULL OR t.prix_min <= ?)';
        $params[] = $kw['budget'];
    }
    if (empty($kw['types'])) {
        $words = array_filter(preg_split('/\s+/', trim($kw['raw'])), fn($w) => mb_strlen($w) > 2);
        if (!empty($words)) {
            $conditions[] = 'MATCH(l.nom, l.description, l.adresse, l.quartier) AGAINST(? IN BOOLEAN MODE)';
            $params[] = implode(' ', array_map(fn($w) => "+$w*", $words));
        }
    }
    $sql = "SELECT l.id, l.nom, l.quartier, l.ville, l.adresse, l.telephone,
                   l.description, l.note_moyenne, l.nb_avis, l.gamme_prix,
                   tl.libelle AS type,
                   GROUP_CONCAT(DISTINCT c.nom ORDER BY c.nom SEPARATOR ', ') AS categories,
                   MIN(t.prix_min) AS prix_min, MAX(t.prix_max) AS prix_max, t.devise
            FROM lieu l
            JOIN type_lieu tl ON tl.id = l.type_lieu_id
            LEFT JOIN lieu_categorie lc ON lc.lieu_id = l.id
            LEFT JOIN categorie c ON c.id = lc.categorie_id
            LEFT JOIN tarif t ON t.lieu_id = l.id
            WHERE ".implode(' AND ', $conditions)."
            GROUP BY l.id, tl.libelle, t.devise
            ORDER BY l.note_moyenne DESC, l.nb_avis DESC LIMIT 6";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[VisitCI SQL] '.$e->getMessage());
        try {
            return $pdo->query("SELECT l.*, tl.libelle AS type FROM lieu l JOIN type_lieu tl ON tl.id=l.type_lieu_id WHERE l.actif=1 ORDER BY l.note_moyenne DESC LIMIT 6")->fetchAll();
        } catch (Throwable $e2) { return []; }
    }
}

// ── CONTEXTE BDD ─────────────────────────────────────────────
function formatContext(array $places): string {
    if (empty($places)) return "Aucun lieu trouvé dans la base de données vérifiée pour cette recherche.";
    $lines = ["Lieux disponibles (base de données vérifiée VisitCI) :\n"];
    foreach ($places as $i => $p) {
        $lines[] = ($i+1).". {$p['nom']} ({$p['type']}) — ".($p['quartier']??'').", ".($p['ville']??'Abidjan');
        if (!empty($p['categories']))  $lines[] = "   Catégories: {$p['categories']}";
        if ($p['note_moyenne'] > 0)    $lines[] = "   Note: {$p['note_moyenne']}/5 ({$p['nb_avis']} avis)";
        if (!empty($p['prix_min']))    $lines[] = "   Prix: ".number_format($p['prix_min'],0,',',' ')." – ".number_format($p['prix_max']??$p['prix_min'],0,',',' ')." ".($p['devise']??'XOF');
        if (!empty($p['telephone']))   $lines[] = "   Tél: {$p['telephone']}";
        if (!empty($p['description'])) $lines[] = "   ".mb_substr($p['description'],0,120)."...";
        $lines[] = '';
    }
    return implode("\n", $lines);
}

// ── RECHERCHE WEB (fallback, via Serper.dev) ─────────────────
function searchWeb(string $query): string {
    if (!SERPER_API_KEY) {
        return "Recherche web non configurée (clé API absente).";
    }
    $ch = curl_init('https://google.serper.dev/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['q' => $query.' Abidjan Côte d\'Ivoire', 'num' => 5]),
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: '.SERPER_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$res) {
        error_log('[VisitCI WEBSEARCH] code='.$code);
        return "Recherche web indisponible pour le moment.";
    }

    $data = json_decode($res, true);
    $results = $data['organic'] ?? [];
    if (empty($results)) return "Aucun résultat web trouvé.";

    $lines = ["Résultats de recherche web (source externe, à vérifier) :\n"];
    foreach (array_slice($results, 0, 5) as $i => $r) {
        $lines[] = ($i+1).". ".($r['title'] ?? '')."";
        if (!empty($r['snippet'])) $lines[] = "   ".$r['snippet'];
        if (!empty($r['link']))    $lines[] = "   Source: ".$r['link'];
        $lines[] = '';
    }
    return implode("\n", $lines);
}

// ── DÉFINITION DE L'OUTIL POUR GROQ (tool calling) ───────────
function getToolsDefinition(): array {
    return [[
        'type' => 'function',
        'function' => [
            'name' => 'search_web',
            'description' => 'Recherche des informations sur internet quand la base de données vérifiée VisitCI ne contient pas le lieu demandé. À utiliser UNIQUEMENT si aucun résultat pertinent n\'est trouvé dans les données fournies.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'La requête de recherche, ex: "restaurant libanais Cocody Abidjan"',
                    ],
                ],
                'required' => ['query'],
            ],
        ],
    ]];
}

// ── SAUVEGARDE ───────────────────────────────────────────────
function saveConv(PDO $pdo, string $canal, string $uid, string $q, string $a): void {
    try {
        $pdo->prepare("INSERT INTO conversation (canal,user_id_externe,langue) VALUES (?,?,'fr') ON DUPLICATE KEY UPDATE derniere_activite=CURRENT_TIMESTAMP")->execute([$canal,$uid]);
        $id = (int)$pdo->lastInsertId() ?: (int)$pdo->query("SELECT id FROM conversation WHERE canal=".$pdo->quote($canal)." AND user_id_externe=".$pdo->quote($uid))->fetchColumn();
        if ($id) {
            $s = $pdo->prepare("INSERT INTO message (conversation_id,role,contenu) VALUES (?,?,?)");
            $s->execute([$id,'user',$q]);
            $s->execute([$id,'assistant',$a]);
        }
    } catch (Throwable $e) { error_log('[VisitCI CONV] '.$e->getMessage()); }
}

// ── APPEL GROQ AVEC SUPPORT TOOL CALLING ─────────────────────
function callGroqWithTools(array $messages): string {
    $tools = SERPER_API_KEY ? getToolsDefinition() : null;

    $payload = [
        'model'       => GROQ_MODEL,
        'max_tokens'  => 800,
        'temperature' => 0.7,
        'messages'    => $messages,
    ];
    if ($tools) {
        $payload['tools'] = $tools;
        $payload['tool_choice'] = 'auto';
    }

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer '.GROQ_API_KEY],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $code !== 200) {
        error_log('[VisitCI GROQ] code='.$code.' err='.$err.' res='.$res);
        return "Je rencontre une difficulté technique (Groq HTTP $code). Veuillez réessayer.";
    }

    $data = json_decode($res, true);
    $choice = $data['choices'][0] ?? null;
    if (!$choice) return "Pas de réponse générée.";

    $message = $choice['message'] ?? [];

    // L'IA veut utiliser l'outil de recherche web
    if (!empty($message['tool_calls'])) {
        $toolCall = $message['tool_calls'][0];
        $args = json_decode($toolCall['function']['arguments'] ?? '{}', true);
        $query = $args['query'] ?? '';

        error_log('[VisitCI] IA demande recherche web: '.$query);
        $webResult = searchWeb($query);

        // On renvoie le résultat de l'outil à Groq pour qu'il formule la réponse finale
        $messages[] = $message; // le message assistant avec tool_calls
        $messages[] = [
            'role'         => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content'      => $webResult,
        ];

        return callGroqFinal($messages);
    }

    return $message['content'] ?? "Pas de réponse générée.";
}

// Second appel sans tools, pour obtenir la réponse finale après résultat d'outil
function callGroqFinal(array $messages): string {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model' => GROQ_MODEL, 'max_tokens' => 800, 'temperature' => 0.7, 'messages' => $messages,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer '.GROQ_API_KEY],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return "Je rencontre une difficulté technique. Réessayez.";
    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? "Pas de réponse générée.";
}

// ── PROMPT SYSTÈME ───────────────────────────────────────────
function buildSystemPrompt(string $ctx): string {
    $webNote = SERPER_API_KEY
        ? "Si aucun lieu pertinent n'est trouvé dans les données VisitCI ci-dessous, tu PEUX utiliser l'outil search_web pour chercher en ligne. Dans ce cas, précise clairement au touriste que cette information vient d'une recherche internet et n'est pas vérifiée par VisitCI, et invite-le à vérifier les détails (horaires, prix) avant de s'y rendre."
        : "Utilise UNIQUEMENT les données ci-dessous. Si rien n'est trouvé, dis-le honnêtement sans inventer de lieu.";

    return <<<PROMPT
Tu es VisitCI, assistant touristique IA de la Côte d'Ivoire. Tu es chaleureux et précis. Tu parles en français sauf si l'utilisateur parle une autre langue.

RÈGLES:
- Cite toujours nom, quartier, téléphone et prix si disponibles
- Sois concis (3-5 phrases max)
- Ne fabrique JAMAIS d'informations — ni depuis les données VisitCI, ni depuis le web
- $webNote

DONNÉES VISITCI:
$ctx
PROMPT;
}

// ── ORCHESTRATION ────────────────────────────────────────────
$pdo    = getDB();
$kw     = extractKeywords($userMessage);
$places = $pdo ? searchPlaces($pdo, $kw) : [];
$ctx    = formatContext($places);

$msgs = [['role' => 'system', 'content' => buildSystemPrompt($ctx)]];
foreach ($history as $h) {
    if (in_array($h['role'] ?? '', ['user', 'assistant'])) {
        $msgs[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}
$msgs[] = ['role' => 'user', 'content' => $userMessage];

$reply = callGroqWithTools($msgs);

if ($pdo) saveConv($pdo, $canal, $sessionId, $userMessage, $reply);

echo json_encode([
    'reply'        => $reply,
    'places_found' => count($places),
    'bdd'          => $pdo ? 'ok' : 'erreur',
    'web_search'   => SERPER_API_KEY ? 'activée' : 'désactivée',
], JSON_UNESCAPED_UNICODE);
