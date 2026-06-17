<?php
// ============================================================
//  telegram/webhook.php — VisitCI Bot Telegram — Version finale
//  GET (navigateur)  → affiche le dernier diagnostic en JSON
//  POST (Telegram)   → traite le message et répond
// ============================================================

define('TELEGRAM_TOKEN', getenv('TELEGRAM_TOKEN') ?: '8948292036:AAHCOShkRZBXRIWWaGAvZTkim5Cguay_BAQ');

$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https'
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
define('APP_URL', rtrim(getenv('APP_URL') ?: $scheme.'://'.($_SERVER['HTTP_HOST'] ?? ''), '/'));

$LOG_PATH = sys_get_temp_dir() . '/visitci_tg.log';

function wlog(string $msg): void {
    global $LOG_PATH;
    @file_put_contents($LOG_PATH, '['.date('H:i:s').'] '.$msg."\n", FILE_APPEND | LOCK_EX);
}

// ── GET = affiche les derniers logs directement (pas besoin d'un autre fichier) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "APP_URL = " . APP_URL . "\n";
    echo "Token configuré : " . (TELEGRAM_TOKEN ? 'oui' : 'non') . "\n\n";
    echo "── Derniers logs ──\n";
    if (file_exists($LOG_PATH)) {
        $lines = file($LOG_PATH);
        echo implode('', array_slice($lines, -60));
    } else {
        echo "(aucun log encore — envoie un message au bot puis recharge cette page)\n";
    }
    exit;
}

// ── POST = traitement Telegram ──────────────────────────────
$rawInput = file_get_contents('php://input');
wlog('=== Nouveau webhook reçu, taille='.strlen($rawInput));

// Réponse immédiate à Telegram pour éviter les retries
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
flush();
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
ignore_user_abort(true);

$update = json_decode($rawInput, true);
if (!$update) { wlog('JSON invalide: '.json_last_error_msg().' raw='.substr($rawInput,0,200)); exit; }

$msg = $update['message'] ?? $update['edited_message'] ?? null;
if (!$msg && isset($update['callback_query']['message'])) $msg = $update['callback_query']['message'];
if (!$msg) { wlog('Pas de champ message dans update: '.json_encode($update)); exit; }

$chatId = (int)($msg['chat']['id'] ?? 0);
$text   = trim($update['callback_query']['data'] ?? $msg['text'] ?? $msg['caption'] ?? '');
$from   = $msg['from']['first_name'] ?? 'Touriste';

wlog("chatId=$chatId text=\"$text\" from=$from");

if (!$chatId || !$text) { wlog('chatId ou text vide, abandon'); exit; }

if ($text === '/start') {
    $welcome = "Bonjour $from ! Je suis VisitCI, votre guide touristique IA pour la Côte d'Ivoire.\n\n"
             . "Je peux vous aider à trouver :\n"
             . "- Restaurants & maquis\n- Hôtels & résidences\n- Transports\n"
             . "- Plages & sites\n- Pharmacies & urgences\n- Banques & change\n\n"
             . "Posez-moi votre question !";
    sendTelegram($chatId, $welcome);
    wlog('Message /start envoyé');
    exit;
}

$reply = callChatAPI($text, $chatId);
sendTelegram($chatId, $reply);
wlog('=== Fin traitement ===');
exit;

// ── FONCTIONS ────────────────────────────────────────────────
function callChatAPI(string $message, int $chatId): string {
    $url = APP_URL . '/api/chat.php';
    wlog("Appel API: $url");

    $payload = json_encode([
        'message'    => $message,
        'canal'      => 'telegram',
        'session_id' => 'tg_'.$chatId,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $res      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    wlog("Résultat: httpCode=$httpCode curlErr=\"$curlErr\" res=".substr((string)$res, 0, 300));

    if ($res === false || $curlErr) {
        return "⚠️ Erreur de connexion interne (curl): $curlErr";
    }
    if ($httpCode !== 200) {
        return "⚠️ L'API a répondu avec le code $httpCode. Réponse: ".substr((string)$res, 0, 200);
    }

    $data = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "⚠️ Réponse API non-JSON: ".substr((string)$res, 0, 200);
    }

    return $data['reply'] ?? "Désolé, je n'ai pas pu traiter votre demande.";
}

function sendTelegram(int $chatId, string $text): void {
    $chunks = mb_str_split($text, 4000);
    foreach ($chunks as $chunk) {
        $ch = curl_init('https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'chat_id' => $chatId,
                'text'    => $chunk,
                'disable_web_page_preview' => true,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        wlog("sendTelegram httpCode=$httpCode curlErr=\"$curlErr\"");
    }
}
