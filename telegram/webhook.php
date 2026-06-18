<?php
// ============================================================
//  telegram/webhook.php — VisitCI Bot Telegram — v3
//  Avec fallback recherche web (Groq tool calling + Serper.dev)
//  Appel direct en PHP (pas de self-call HTTP, évite les timeouts Railway)
// ============================================================

define('TELEGRAM_TOKEN', getenv('TELEGRAM_TOKEN') ?: '8948292036:AAHCOShkRZBXRIWWaGAvZTkim5Cguay_BAQ');

$LOG_PATH = sys_get_temp_dir() . '/visitci_tg.log';

function wlog(string $msg): void {
    global $LOG_PATH;
    @file_put_contents($LOG_PATH, '['.date('H:i:s').'] '.$msg."\n", FILE_APPEND | LOCK_EX);
}

// ── GET = logs ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Webhook v3 — avec recherche web fallback\n";
    echo "SERPER_API_KEY configurée: " . (getenv('SERPER_API_KEY') ? 'oui' : 'non') . "\n\n";
    echo "── Derniers logs ──\n";
    if (file_exists($LOG_PATH)) {
        echo implode('', array_slice(file($LOG_PATH), -60));
    } else {
        echo "(aucun log encore)\n";
    }
    exit;
}

// ── POST = traitement Telegram ────────────────────────────
$rawInput = file_get_contents('php://input');
wlog('=== Webhook reçu, taille='.strlen($rawInput));

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
flush();
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
ignore_user_abort(true);

$update = json_decode($rawInput, true);
if (!$update) { wlog('JSON invalide'); exit; }

$msg = $update['message'] ?? $update['edited_message'] ?? null;
if (!$msg && isset($update['callback_query']['message'])) $msg = $update['callback_query']['message'];
if (!$msg) { wlog('Pas de message'); exit; }

$chatId = (int)($msg['chat']['id'] ?? 0);
$text   = trim($update['callback_query']['data'] ?? $msg['text'] ?? $msg['caption'] ?? '');
$from   = $msg['from']['first_name'] ?? 'Touriste';

wlog("chatId=$chatId text=\"$text\" from=$from");
if (!$chatId || !$text) { wlog('chatId/text vide'); exit; }

if ($text === '/start') {
    $welcome = "Bonjour $from ! Je suis VisitCI, votre guide touristique IA pour la Côte d'Ivoire.\n\n"
             . "Je peux vous aider à trouver :\n"
             . "- Restaurants & maquis\n- Hôtels & résidences\n- Transports\n"
             . "- Plages & sites\n- Pharmacies & urgences\n- Banques & change\n\n"
             . "Posez-moi votre question !";
    sendTelegram($chatId, $welcome);
    wlog('/start envoyé');
    exit;
}

$reply = getChatReply($text, 'telegram', 'tg_'.$chatId, []);
sendTelegram($chatId, $reply);
wlog('=== Fin traitement ===');
exit;

// ============================================================
//  LOGIQUE CHAT INTÉGRÉE (identique à api/chat.php, sans HTTP layer)
// ============================================================
function getChatReply(string $userMessage, string $canal, string $sessionId, array $history): string {
    wlog('getChatReply démarré');

    $groqKey   = getenv('GROQ_API_KEY') ?: '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';
    $groqModel = 'llama-3.3-70b-versatile';
    $serperKey = getenv('SERPER_API_KEY') ?: '2f20d47a2462fb7c5c3a6f43c0f3356cfcd63164';

    $dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('MYSQL_PRIVATE_URL') ?: '';
    if ($dbUrl && strpos($dbUrl, 'mysql') !== false) {
        $p = parse_url($dbUrl);
        $dbHost = $p['host'] ?? ''; $dbPort = $p['port'] ?? 3306;
        $dbName = ltrim($p['path'] ?? '', '/'); $dbUser = $p['user'] ?? ''; $dbPass = $p['pass'] ?? '';
    } else {
        $dbHost = getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'mysql-oykn.railway.internal';
        $dbPort = getenv('MYSQLPORT')     ?: getenv('DB_PORT') ?: '3306';
        $dbName = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway';
        $dbUser = getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root';
        $dbPass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: 'AqogZmpzTZZrcEYZzYGyGmbWdfPWArhM';
    }

    wlog("BDD config: host=$dbHost port=$dbPort (MYSQLHOST env=".(getenv('MYSQLHOST')?:'absent').")");

    $pdo = null;
    try {
        $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5]);
        wlog('BDD connectée OK');
    } catch (Throwable $e) {
        wlog('BDD erreur: '.$e->getMessage());
    }

    // ── Mots-clés ──
    $msgLower = mb_strtolower($userMessage);
    $types = [
        'restaurant' => ['restaurant','maquis','manger','dîner','déjeuner','bouffe','cuisine','plat','attiéké','alloco','foutou','nourriture','faim'],
        'hotel'      => ['hôtel','hotel','résidence','residence','hébergement','chambre','dormir','nuit','séjour','auberge','logement'],
        'activite'   => ['activité','loisir','sortir','boîte','club','bar','musée','concert','sport','gym','spa','visiter'],
        'transport'  => ['transport','taxi','woro','gbaka','bus','moto','voiture','aller','trajet','bateau','lagune','ferry'],
        'plage'      => ['plage','mer','sable','bain','nager','surf','site','monument','patrimoine','nature'],
        'sante'      => ['pharmacie','médecin','hôpital','clinique','urgence','santé','docteur','médicament','soins','malade'],
        'banque'     => ['banque','atm','distributeur','argent','change','mobile money','orange money','wave','retrait'],
        'marche'     => ['marché','shopping','acheter','souvenir','artisanat','boutique','centre commercial','tissu'],
    ];
    $foundTypes = [];
    foreach ($types as $type => $kws) {
        foreach ($kws as $kw) { if (str_contains($msgLower, $kw)) { $foundTypes[] = $type; break; } }
    }
    $foundTypes = array_unique($foundTypes);

    // ── Recherche BDD ──
    $places = [];
    if ($pdo) {
        try {
            $conditions = ['l.actif = 1'];
            $params = [];
            if (!empty($foundTypes)) {
                $ph = implode(',', array_fill(0, count($foundTypes), '?'));
                $conditions[] = "tl.slug IN ($ph)";
                $params = array_merge($params, $foundTypes);
            } else {
                $words = array_filter(preg_split('/\s+/', trim($msgLower)), fn($w) => mb_strlen($w) > 2);
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
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $places = $stmt->fetchAll();
            wlog('Lieux trouvés: '.count($places));
        } catch (Throwable $e) {
            wlog('Erreur recherche: '.$e->getMessage());
        }
    }

    // ── Contexte ──
    $ctx = "Aucun lieu trouvé dans la base de données vérifiée pour cette recherche.";
    if (!empty($places)) {
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
        $ctx = implode("\n", $lines);
    }

    // ── Prompt système ──
    $webNote = $serperKey
        ? "Si aucun lieu pertinent n'est trouvé dans les données VisitCI ci-dessous, tu PEUX utiliser l'outil search_web pour chercher en ligne. Dans ce cas, précise clairement au touriste que cette information vient d'une recherche internet et n'est pas vérifiée par VisitCI, et invite-le à vérifier les détails avant de s'y rendre."
        : "Utilise UNIQUEMENT les données ci-dessous. Si rien n'est trouvé, dis-le honnêtement sans inventer de lieu.";

    $sysPrompt = "Tu es VisitCI, assistant touristique IA de la Côte d'Ivoire. Tu es chaleureux et précis. Tu parles en français sauf si l'utilisateur parle une autre langue.\n\nRÈGLES:\n- Cite toujours nom, quartier, téléphone et prix si disponibles\n- Sois concis (3-5 phrases max)\n- Ne fabrique JAMAIS d'informations — ni depuis les données VisitCI, ni depuis le web\n- $webNote\n\nDONNÉES VISITCI:\n$ctx";

    $msgs = [['role'=>'system','content'=>$sysPrompt]];
    foreach ($history as $h) {
        if (in_array($h['role']??'', ['user','assistant'])) $msgs[] = ['role'=>$h['role'],'content'=>$h['content']];
    }
    $msgs[] = ['role'=>'user','content'=>$userMessage];

    $reply = callGroqWithTools($msgs, $groqKey, $groqModel, $serperKey);

    // ── Sauvegarde conversation (best effort) ──
    if ($pdo) {
        try {
            $pdo->prepare("INSERT INTO conversation (canal,user_id_externe,langue) VALUES (?,?,'fr') ON DUPLICATE KEY UPDATE derniere_activite=CURRENT_TIMESTAMP")->execute([$canal,$sessionId]);
            $id = (int)$pdo->lastInsertId() ?: (int)$pdo->query("SELECT id FROM conversation WHERE canal=".$pdo->quote($canal)." AND user_id_externe=".$pdo->quote($sessionId))->fetchColumn();
            if ($id) {
                $s = $pdo->prepare("INSERT INTO message (conversation_id,role,contenu) VALUES (?,?,?)");
                $s->execute([$id,'user',$userMessage]);
                $s->execute([$id,'assistant',$reply]);
            }
        } catch (Throwable $e) { wlog('Conv save erreur: '.$e->getMessage()); }
    }

    return $reply;
}

// ── Recherche web (Serper.dev) ──
function searchWeb(string $query, string $serperKey): string {
    if (!$serperKey) return "Recherche web non configurée.";

    $ch = curl_init('https://google.serper.dev/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['q' => $query.' Abidjan Côte d\'Ivoire', 'num' => 5]),
        CURLOPT_HTTPHEADER     => ['X-API-KEY: '.$serperKey, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    wlog("searchWeb query=\"$query\" code=$code");

    if ($code !== 200 || !$res) return "Recherche web indisponible.";

    $data = json_decode($res, true);
    $results = $data['organic'] ?? [];
    if (empty($results)) return "Aucun résultat web trouvé.";

    $lines = ["Résultats de recherche web (source externe, à vérifier) :\n"];
    foreach (array_slice($results, 0, 5) as $i => $r) {
        $lines[] = ($i+1).". ".($r['title'] ?? '');
        if (!empty($r['snippet'])) $lines[] = "   ".$r['snippet'];
        if (!empty($r['link']))    $lines[] = "   Source: ".$r['link'];
        $lines[] = '';
    }
    return implode("\n", $lines);
}

function getToolsDefinition(): array {
    return [[
        'type' => 'function',
        'function' => [
            'name' => 'search_web',
            'description' => 'Recherche des informations sur internet quand la base de données vérifiée VisitCI ne contient pas le lieu demandé. À utiliser UNIQUEMENT si aucun résultat pertinent n\'est trouvé dans les données fournies.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'La requête de recherche, ex: "restaurant libanais Cocody Abidjan"'],
                ],
                'required' => ['query'],
            ],
        ],
    ]];
}

function callGroqWithTools(array $messages, string $groqKey, string $groqModel, string $serperKey): string {
    $tools = $serperKey ? getToolsDefinition() : null;

    $payload = ['model' => $groqModel, 'max_tokens' => 800, 'temperature' => 0.7, 'messages' => $messages];
    if ($tools) { $payload['tools'] = $tools; $payload['tool_choice'] = 'auto'; }

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer '.$groqKey],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    wlog("Groq httpCode=$code err=\"$err\"");

    if ($err || $code !== 200) return "Je rencontre une difficulté technique (Groq $code). Réessayez.";

    $data = json_decode($res, true);
    $choice = $data['choices'][0] ?? null;
    if (!$choice) return "Pas de réponse générée.";

    $message = $choice['message'] ?? [];

    if (!empty($message['tool_calls'])) {
        $toolCall = $message['tool_calls'][0];
        $args = json_decode($toolCall['function']['arguments'] ?? '{}', true);
        $query = $args['query'] ?? '';

        wlog('IA demande recherche web: '.$query);
        $webResult = searchWeb($query, $serperKey);

        $messages[] = $message;
        $messages[] = ['role' => 'tool', 'tool_call_id' => $toolCall['id'], 'content' => $webResult];

        return callGroqFinal($messages, $groqKey, $groqModel);
    }

    return $message['content'] ?? "Pas de réponse générée.";
}

function callGroqFinal(array $messages, string $groqKey, string $groqModel): string {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['model' => $groqModel, 'max_tokens' => 800, 'temperature' => 0.7, 'messages' => $messages]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer '.$groqKey],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return "Je rencontre une difficulté technique. Réessayez.";
    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? "Pas de réponse générée.";
}

function sendTelegram(int $chatId, string $text): void {
    $chunks = mb_str_split($text, 4000);
    foreach ($chunks as $chunk) {
        $ch = curl_init('https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['chat_id'=>$chatId,'text'=>$chunk,'disable_web_page_preview'=>true]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        wlog("sendTelegram httpCode=$code err=\"$err\"");
    }
}
