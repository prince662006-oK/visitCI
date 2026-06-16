<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// ── CONFIG ──────────────────────────────────────────────────
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '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');
define('GROQ_MODEL',   'llama-3.3-70b-versatile');

// Railway injecte DATABASE_URL — on le parse en priorité
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('MYSQL_PRIVATE_URL') ?: '';
if ($dbUrl) {
    $p = parse_url($dbUrl);
    define('DB_HOST', $p['host'] ?? 'localhost');
    define('DB_PORT', $p['port'] ?? 3306);
    define('DB_NAME', ltrim($p['path'] ?? '/railway', '/'));
    define('DB_USER', $p['user'] ?? 'root');
    define('DB_PASS', $p['pass'] ?? '');
} else {
    define('DB_HOST', getenv('MYSQLHOST')  ?: getenv('DB_HOST')  ?: 'switchyard.proxy.rlwy.net');
    define('DB_PORT', getenv('MYSQLPORT')  ?: getenv('DB_PORT')  ?: '24576');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway');
    define('DB_USER', getenv('MYSQLUSER')  ?: getenv('DB_USER')  ?: 'root');
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: 'AqogZmpzTZZrcEYZzYGyGmbWdfPWArhM');
}

define('MAX_HISTORY', 10);
define('MAX_RESULTS', 6);

// ── ENTRÉE ──────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message manquant']);
    exit;
}

$userMessage = trim(htmlspecialchars_decode($body['message']));
$history     = isset($body['history']) ? array_slice($body['history'], -MAX_HISTORY) : [];
$canal       = $body['canal'] ?? 'web';
$sessionId   = $body['session_id'] ?? '';

// ── CONNEXION BDD ────────────────────────────────────────────
function getDB(): ?PDO {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
        // SSL Railway (pas toujours requis mais aide)
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log l'erreur réelle pour debug Railway
        error_log('[VisitCI BDD] ' . $e->getMessage());
        return null;
    }
}

// ── TEST DE CONNEXION (endpoint debug) ─────────────────────
// Appelle /api/chat.php?debug=1 pour tester la BDD sans IA
if (isset($_GET['debug'])) {
    $pdo = getDB();
    if (!$pdo) {
        echo json_encode([
            'bdd' => 'ERREUR',
            'host' => DB_HOST, 'port' => DB_PORT,
            'db'   => DB_NAME, 'user' => DB_USER,
        ]);
    } else {
        $count = $pdo->query('SELECT COUNT(*) FROM lieu')->fetchColumn();
        echo json_encode([
            'bdd'    => 'OK',
            'lieux'  => $count,
            'host'   => DB_HOST,
            'db'     => DB_NAME,
        ]);
    }
    exit;
}

// ── EXTRACTION MOTS-CLÉS ────────────────────────────────────
function extractKeywords(string $msg): array {
    $msg = mb_strtolower($msg);

    $types = [
        'restaurant' => ['restaurant','maquis','manger','dîner','déjeuner','petit-déj','bouffe','cuisine','plat','attiéké','alloco','foutou','thiéboudienne','kédjénou','nourriture','faim'],
        'hotel'      => ['hôtel','hotel','résidence','residence','hébergement','chambre','dormir','nuit','séjour','auberge','logement','appartement'],
        'activite'   => ['activité','loisir','sortir','boîte','club','bar','musée','concert','sport','gym','spa','détente','visiter','divertissement','animation'],
        'transport'  => ['transport','taxi','woro','gbaka','bus','moto','voiture','aller','trajet','déplacement','bateau','lagune','ferry','conduire','route'],
        'plage'      => ['plage','mer','sable','bain','nager','surf','site','monument','patrimoine','nature','touriste'],
        'sante'      => ['pharmacie','médecin','hôpital','clinique','urgence','santé','docteur','médicament','soins','blessé','malade'],
        'banque'     => ['banque','atm','distributeur','argent','change','mobile money','orange money','mtn','wave','retrait','dépôt'],
        'marche'     => ['marché','shopping','acheter','souvenir','artisanat','boutique','centre commercial','tissu','wax'],
    ];

    $quartiers = ['cocody','plateau','marcory','yopougon','adjamé','treichville','abobo','koumassi','riviera','deux plateaux','angré','bingerville','port bouët','zone 4','zone4'];

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

    $openNow = str_contains($msg, 'ouvert') || str_contains($msg, 'nuit') || str_contains($msg, '24h');

    return [
        'types'     => array_unique($found_types),
        'quartiers' => $found_quartiers,
        'budget'    => $budget,
        'open_now'  => $openNow,
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
        foreach ($kw['quartiers'] as $q) {
            $qc[] = 'l.quartier LIKE ?';
            $params[] = "%$q%";
        }
        $conditions[] = '(' . implode(' OR ', $qc) . ')';
    }

    if ($kw['budget']) {
        $conditions[] = '(t.prix_min IS NULL OR t.prix_min <= ?)';
        $params[] = $kw['budget'];
    }

    // FULLTEXT seulement si aucun type trouvé ET message assez long
    if (empty($kw['types']) && mb_strlen($kw['raw']) > 3) {
        $words = array_filter(preg_split('/\s+/', trim($kw['raw'])), fn($w) => mb_strlen($w) > 2);
        if (!empty($words)) {
            $conditions[] = 'MATCH(l.nom, l.description, l.adresse, l.quartier) AGAINST(? IN BOOLEAN MODE)';
            $params[] = implode(' ', array_map(fn($w) => "+$w*", $words));
        }
    }

    $where = implode(' AND ', $conditions);
    $sql = "
        SELECT
            l.id, l.nom, l.quartier, l.ville, l.adresse,
            l.telephone, l.description, l.note_moyenne, l.nb_avis,
            l.gamme_prix, l.latitude, l.longitude,
            tl.libelle AS type,
            GROUP_CONCAT(DISTINCT c.nom ORDER BY c.nom SEPARATOR ', ') AS categories,
            MIN(t.prix_min) AS prix_min,
            MAX(t.prix_max) AS prix_max,
            t.devise
        FROM lieu l
        JOIN type_lieu tl        ON tl.id = l.type_lieu_id
        LEFT JOIN lieu_categorie lc ON lc.lieu_id = l.id
        LEFT JOIN categorie c       ON c.id = lc.categorie_id
        LEFT JOIN tarif t           ON t.lieu_id = l.id
        WHERE $where
        GROUP BY l.id, tl.libelle, t.devise
        ORDER BY l.note_moyenne DESC, l.nb_avis DESC
        LIMIT " . MAX_RESULTS;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[VisitCI SQL] ' . $e->getMessage() . ' | SQL: ' . $sql);
        // Fallback : retourner tous les lieux si la requête échoue
        try {
            return $pdo->query("SELECT l.*, tl.libelle AS type FROM lieu l JOIN type_lieu tl ON tl.id = l.type_lieu_id WHERE l.actif=1 ORDER BY l.note_moyenne DESC LIMIT " . MAX_RESULTS)->fetchAll();
        } catch (PDOException $e2) {
            return [];
        }
    }
}

// ── FORMATAGE CONTEXTE ───────────────────────────────────────
function formatContext(array $places): string {
    if (empty($places)) return "Aucun lieu trouvé dans la base de données pour cette recherche.";

    $lines = ["Voici les lieux disponibles :\n"];
    foreach ($places as $i => $p) {
        $note    = $p['note_moyenne'] > 0 ? "Note: {$p['note_moyenne']}/5 ({$p['nb_avis']} avis)" : "Pas encore noté";
        $prix    = '';
        if (!empty($p['prix_min']) || !empty($p['prix_max'])) {
            $min  = !empty($p['prix_min']) ? number_format($p['prix_min'], 0, ',', ' ').' '.($p['devise']??'XOF') : '';
            $max  = !empty($p['prix_max']) ? number_format($p['prix_max'], 0, ',', ' ').' '.($p['devise']??'XOF') : '';
            $prix = ($min && $max) ? "Prix: $min – $max" : "Prix: ".($min ?: $max);
        }
        $gamme   = !empty($p['gamme_prix']) ? " | Gamme: {$p['gamme_prix']}" : '';
        $desc    = !empty($p['description']) ? mb_substr($p['description'], 0, 150).'...' : '';
        $tel     = !empty($p['telephone'])   ? "Tél: {$p['telephone']}" : '';
        $adresse = !empty($p['adresse'])     ? "{$p['adresse']}, " : '';
        $quartier= $p['quartier'] ?? '';
        $ville   = $p['ville'] ?? 'Abidjan';

        $lines[] = ($i+1).". {$p['nom']} ({$p['type']})";
        $lines[] = "   Localisation: {$adresse}{$quartier}, {$ville}";
        if (!empty($p['categories'])) $lines[] = "   Spécialités: {$p['categories']}";
        $lines[] = "   $note$gamme";
        if ($prix) $lines[] = "   $prix";
        if ($tel)  $lines[] = "   $tel";
        if ($desc) $lines[] = "   $desc";
        $lines[] = '';
    }
    return implode("\n", $lines);
}

// ── SAUVEGARDE CONVERSATION ──────────────────────────────────
function saveConversation(PDO $pdo, string $canal, string $userId, string $userMsg, string $botReply): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO conversation (canal, user_id_externe, langue)
            VALUES (?, ?, 'fr')
            ON DUPLICATE KEY UPDATE derniere_activite = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$canal, $userId]);
        $convId = (int)$pdo->lastInsertId();
        if (!$convId) {
            $convId = (int)$pdo->query(
                "SELECT id FROM conversation WHERE canal=".$pdo->quote($canal)." AND user_id_externe=".$pdo->quote($userId)
            )->fetchColumn();
        }
        if ($convId) {
            $stmt = $pdo->prepare("INSERT INTO message (conversation_id, role, contenu) VALUES (?,?,?)");
            $stmt->execute([$convId, 'user', $userMsg]);
            $stmt->execute([$convId, 'assistant', $botReply]);
        }
    } catch (PDOException $e) {
        error_log('[VisitCI CONV] ' . $e->getMessage());
    }
}

// ── APPEL GROQ ───────────────────────────────────────────────
function callGroq(array $messages): string {
    $payload = json_encode([
        'model'       => GROQ_MODEL,
        'max_tokens'  => 800,
        'temperature' => 0.7,
        'messages'    => $messages,
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[VisitCI GROQ cURL] ' . $curlErr);
        return "Je rencontre une difficulté de connexion. Veuillez réessayer.";
    }
    if ($httpCode !== 200) {
        error_log('[VisitCI GROQ HTTP] ' . $httpCode . ' — ' . $response);
        return "Je rencontre une difficulté technique (HTTP $httpCode). Veuillez réessayer.";
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "Je n'ai pas pu générer une réponse.";
}

// ── PROMPT SYSTÈME ───────────────────────────────────────────
function buildSystemPrompt(string $context): string {
    $date = date('d/m/Y H:i', time() + 0); // UTC, ajuste si besoin
    return <<<PROMPT
Tu es VisitCI, un assistant touristique IA expert de la Côte d'Ivoire, en particulier d'Abidjan.
Tu es chaleureux, enthousiaste, précis et utile. Tu parles en français sauf si l'utilisateur parle une autre langue.
Date et heure actuelles (UTC) : $date

TES MISSIONS :
- Recommander des restaurants, hôtels, activités, transports, plages, pharmacies, banques, marchés
- Donner des infos pratiques : horaires, tarifs, comment y aller, contacts téléphoniques
- Répondre aux urgences (pharmacie de nuit, hôpital, taxi urgent)
- Partager des conseils culturels sur la Côte d'Ivoire

RÈGLES IMPORTANTES :
- Utilise UNIQUEMENT les données ci-dessous pour tes recommandations de lieux
- Si aucun lieu n'est disponible dans les données, dis-le honnêtement mais reste utile
- Cite toujours le nom du lieu, le quartier, le téléphone et les prix quand disponibles
- Sois concis (3-5 phrases max) mais complet
- Utilise des emojis avec modération
- Ne fabrique JAMAIS de lieux ou d'informations absents des données

DONNÉES DISPONIBLES :
$context
PROMPT;
}

// ── ORCHESTRATION ────────────────────────────────────────────
$pdo     = getDB();
$kw      = extractKeywords($userMessage);
$places  = $pdo ? searchPlaces($pdo, $kw) : [];
$context = formatContext($places);

$groqMessages   = [['role' => 'system', 'content' => buildSystemPrompt($context)]];
foreach ($history as $h) {
    if (in_array($h['role'] ?? '', ['user', 'assistant'])) {
        $groqMessages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}
$groqMessages[] = ['role' => 'user', 'content' => $userMessage];

$reply = callGroq($groqMessages);

if ($pdo) {
    $userId = $sessionId ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    saveConversation($pdo, $canal, $userId, $userMessage, $reply);
}

echo json_encode([
    'reply'        => $reply,
    'places_found' => count($places),
], JSON_UNESCAPED_UNICODE);
