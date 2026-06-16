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
define('DB_HOST',      getenv('MYSQLHOST')     ?: 'mysql-oykn.railway.internal');
define('DB_NAME',      getenv('MYSQLDATABASE') ?: 'railway');
define('DB_USER',      getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',      getenv('MYSQLPASSWORD') ?: 'AqogZmpzTZZrcEYZzYGyGmbWdfPWArhM');
define('DB_PORT',      getenv('MYSQLPORT')     ?: '3306'); // Optionnel mais conseillé sur Railway

define('MAX_HISTORY',  10);   // nb de messages gardés en mémoire
define('MAX_RESULTS',  6);    // nb max de lieux retournés par la BDD

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
$sessionId   = $body['session_id'] ?? session_id();

// ── CONNEXION BDD ────────────────────────────────────────────
function getDB(): ?PDO {
    try {
      $pdo = new PDO(
    'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
    DB_USER, DB_PASS,
          [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        return $pdo;
    }catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
}

function extractKeywords(string $msg): array {
    $msg = mb_strtolower($msg);

    $types = [
        'restaurant' => ['restaurant','maquis','manger','dîner','déjeuner','petit-déj','bouffe','cuisine','plat','attiéké','alloco','foutou','thiéboudienne','kédjénou'],
        'hotel'      => ['hôtel','hotel','résidence','hébergement','chambre','dormir','nuit','séjour','auberge'],
        'activite'   => ['activité','loisir','sortir','boîte','club','bar','musée','concert','sport','gym','spa','détente','visiter'],
        'transport'  => ['transport','taxi','woro','gbaka','bus','moto','voiture','aller','trajet','déplacement','bateau','lagune','ferry'],
        'plage'      => ['plage','mer','plage','sable','bain','nager','surf','site','monument','patrimoine'],
        'sante'      => ['pharmacie','médecin','hôpital','clinique','urgence','santé','docteur','médicament','soins'],
        'banque'     => ['banque','atm','distributeur','argent','change','mobile money','orange money','mtn','wave'],
        'marche'     => ['marché','shopping','acheter','souvenir','artisanat','boutique','centre commercial'],
    ];

    $quartiers = ['cocody','plateau','marcory','yopougon','adjamé','treichville','abobo','koumassi','riviera','deux plateaux','angré','bingerville'];

    $found_types    = [];
    $found_quartiers = [];

    foreach ($types as $type => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) { $found_types[] = $type; break; }
        }
    }
    foreach ($quartiers as $q) {
        if (str_contains($msg, $q)) $found_quartiers[] = $q;
    }

    // Détection budget
    $budget = null;
    if (preg_match('/moins de (\d[\d\s]*)\s*(fcfa|xof|f|francs?)?/iu', $msg, $m)) {
        $budget = (int) preg_replace('/\s+/', '', $m[1]);
    }

    // Détection "ouvert" / "nuit"
    $openNow = str_contains($msg, 'ouvert') || str_contains($msg, 'nuit') || str_contains($msg, '24h');

    return [
        'types'    => array_unique($found_types),
        'quartiers'=> $found_quartiers,
        'budget'   => $budget,
        'open_now' => $openNow,
        'raw'      => $msg,
    ];
}

function searchPlaces(PDO $pdo, array $kw): array {
    $conditions = ['l.actif = 1'];
    $params     = [];

    // Filtre par type
    if (!empty($kw['types'])) {
        $placeholders = implode(',', array_fill(0, count($kw['types']), '?'));
        $conditions[] = "tl.slug IN ($placeholders)";
        $params = array_merge($params, $kw['types']);
    }

    // Filtre par quartier
    if (!empty($kw['quartiers'])) {
        $qCond = [];
        foreach ($kw['quartiers'] as $q) {
            $qCond[] = 'l.quartier LIKE ?';
            $params[] = "%$q%";
        }
        $conditions[] = '(' . implode(' OR ', $qCond) . ')';
    }

    // Filtre budget
    if ($kw['budget']) {
        $conditions[] = '(t.prix_min IS NULL OR t.prix_min <= ?)';
        $params[] = $kw['budget'];
    }

    // Recherche fulltext si pas de type trouvé
    if (empty($kw['types']) && !empty($kw['raw'])) {
        $conditions[] = 'MATCH(l.nom, l.description, l.adresse, l.quartier) AGAINST(? IN BOOLEAN MODE)';
        $words = preg_split('/\s+/', trim($kw['raw']));
        $params[] = implode(' ', array_map(fn($w) => "+$w*", $words));
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
        JOIN type_lieu tl ON tl.id = l.type_lieu_id
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
        return [];
    }
}

// ── FORMATAGE CONTEXTE POUR L'IA ────────────────────────────
function formatContext(array $places): string {
    if (empty($places)) return "Aucun lieu trouvé dans la base de données pour cette recherche.";

    $lines = ["Voici les lieux disponibles dans la base de données :\n"];
    foreach ($places as $i => $p) {
        $num  = $i + 1;
        $note = $p['note_moyenne'] > 0 ? "Note: {$p['note_moyenne']}/5 ({$p['nb_avis']} avis)" : "Pas encore noté";
        $prix = '';
        if ($p['prix_min'] || $p['prix_max']) {
            $min = $p['prix_min'] ? number_format($p['prix_min'], 0, ',', ' ').' '.$p['devise'] : '';
            $max = $p['prix_max'] ? number_format($p['prix_max'], 0, ',', ' ').' '.$p['devise'] : '';
            $prix = $min && $max ? "Prix: $min – $max" : "Prix: ".($min ?: $max);
        }
        $gamme = $p['gamme_prix'] ? " | Gamme: {$p['gamme_prix']}" : '';
        $desc  = $p['description'] ? substr($p['description'], 0, 120).'...' : '';
        $tel   = $p['telephone'] ? "Tél: {$p['telephone']}" : '';
        $adresse = $p['adresse'] ? "{$p['adresse']}, " : '';

        $lines[] = "$num. {$p['nom']} ({$p['type']})";
        $lines[] = "   Localisation: {$adresse}{$p['quartier']}, {$p['ville']}";
        if ($p['categories']) $lines[] = "   Spécialités: {$p['categories']}";
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
        // Upsert conversation
        $stmt = $pdo->prepare("
            INSERT INTO conversation (canal, user_id_externe, langue)
            VALUES (?, ?, 'fr')
            ON DUPLICATE KEY UPDATE derniere_activite = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$canal, $userId]);
        $convId = $pdo->lastInsertId() ?: (int) $pdo->query("
            SELECT id FROM conversation WHERE canal=".
            $pdo->quote($canal)." AND user_id_externe=".$pdo->quote($userId)
        )->fetchColumn();

        // Sauvegarde messages
        $stmt = $pdo->prepare("INSERT INTO message (conversation_id, role, contenu) VALUES (?,?,?)");
        $stmt->execute([$convId, 'user',      $userMsg]);
        $stmt->execute([$convId, 'assistant', $botReply]);
    } catch (PDOException $e) {
        // Silencieux — ne bloque pas la réponse
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
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode !== 200) {
        return "Je rencontre une difficulté technique. Veuillez réessayer dans un instant.";
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "Je n'ai pas pu générer une réponse.";
}

// ── SYSTÈME PROMPT ───────────────────────────────────────────
function buildSystemPrompt(string $context): string {
    return <<<PROMPT
Tu es VisitCI, un assistant touristique IA expert de la Côte d'Ivoire, en particulier d'Abidjan.
Tu es chaleureux, enthousiaste, précis et utile. Tu parles en français sauf si l'utilisateur parle une autre langue.

TES MISSIONS :
- Recommander des restaurants, hôtels, activités, transports, plages, pharmacies, banques, marchés
- Donner des infos pratiques : horaires, tarifs, comment y aller, contacts
- Répondre aux urgences (pharmacie de nuit, hôpital, taxi urgent)
- Partager des conseils culturels sur la Côte d'Ivoire

RÈGLES IMPORTANTES :
- Utilise UNIQUEMENT les données ci-dessous pour tes recommandations de lieux
- Si aucun lieu n'est disponible, dis-le honnêtement et propose d'autres options
- Cite les noms des lieux, quartiers, prix et téléphones quand disponibles
- Sois concis mais complet (max 4-5 phrases par réponse)
- Utilise des emojis avec modération pour rendre la réponse lisible
- Ne fabrique JAMAIS de lieux ou d'informations qui ne sont pas dans les données

DONNÉES DISPONIBLES :
$context
PROMPT;
}


$pdo     = getDB();
$kw      = extractKeywords($userMessage);
$places  = $pdo ? searchPlaces($pdo, $kw) : [];
$context = formatContext($places);

// Construction des messages pour Groq
$systemPrompt = buildSystemPrompt($context);
$groqMessages = [['role' => 'system', 'content' => $systemPrompt]];

// Ajout de l'historique (sans le dernier message user qui sera ajouté ci-dessous)
foreach ($history as $h) {
    if (in_array($h['role'] ?? '', ['user','assistant'])) {
        $groqMessages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}
$groqMessages[] = ['role' => 'user', 'content' => $userMessage];

// Appel Groq
$reply = callGroq($groqMessages);

// Sauvegarde en BDD (non bloquante)
if ($pdo) {
    $userId = $sessionId ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    saveConversation($pdo, $canal, $userId, $userMessage, $reply);
}

// ── RÉPONSE ──────────────────────────────────────────────────
echo json_encode([
    'reply'        => $reply,
    'places_found' => count($places),
    'debug'        => defined('DEBUG') && DEBUG ? [
        'keywords' => $kw,
        'context'  => $context,
    ] : null,
], JSON_UNESCAPED_UNICODE);
