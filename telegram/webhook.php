<?php
// ============================================================
//  telegram/webhook.php — Bot Telegram VisitCI (corrigé)
// ============================================================
define('TELEGRAM_TOKEN', getenv('TELEGRAM_TOKEN') ?: '8948292036:AAHCOShkRZBXRIWWaGAvZTkim5Cguay_BAQ');

// APP_URL : priorité à la variable d'env Railway
$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https'
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
define('APP_URL', rtrim(getenv('APP_URL') ?: $scheme.'://'.($_SERVER['HTTP_HOST'] ?? https://api.telegram.org/bot8948292036:AAHCOShkRZBXRIWWaGAvZTkim5Cguay_BAQ/getWebhookInfo'), '/'));
define('LOG_FILE', sys_get_temp_dir() . '/visitci_webhook.log');

function wlog(string $msg): void {
    $line = '['.date('Y-m-d H:i:s').'] '.$msg."\n";
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    error_log($line);
}

// ── LIRE INPUT ──────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
wlog('Webhook reçu — method:'.$_SERVER['REQUEST_METHOD'].' size:'.strlen($rawInput).' APP_URL:'.APP_URL);

// ── Réponse immédiate 200 à Telegram ──────────────────────
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
flush();
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

// ── Traitement en arrière-plan ─────────────────────────────
ignore_user_abort(true);

$update = json_decode($rawInput, true);
if (!$update) { wlog('JSON invalide: '.json_last_error_msg()); exit; }

wlog('Update: '.json_encode($update));

// Extraire le message (texte ordinaire, commandes, callback)
$msg = $update['message'] ?? $update['edited_message'] ?? null;
if (!$msg && isset($update['callback_query']['message'])) {
    $msg = $update['callback_query']['message'];
}
if (!$msg) { wlog('Pas de message'); exit; }

$chatId   = (int)($msg['chat']['id'] ?? 0);
$text     = trim(
    $update['callback_query']['data'] ??
    $msg['text'] ??
    $msg['caption'] ??
    ''
);
$from     = $msg['from']['first_name'] ?? 'Touriste';

if (!$chatId || !$text) { wlog('chatId ou texte vide'); exit; }

// Commande /start → message de bienvenue
if ($text === '/start') {
    $welcome = "Bonjour $from ! 🇨🇮 Je suis *VisitCI*, votre guide touristique IA pour la Côte d'Ivoire.\n\n"
             . "Je peux vous aider à trouver :\n"
             . "🍽️ Restaurants & maquis\n"
             . "🏨 Hôtels & résidences\n"
             . "🚖 Transports\n"
             . "🏖️ Plages & sites\n"
             . "🏥 Pharmacies & urgences\n"
             . "🏦 Banques & change\n\n"
             . "Posez-moi votre question en français ou en anglais !";
    sendTelegram($chatId, $welcome, true);
    exit;
}

// Appel IA
wlog("Traitement message: $text (chatId: $chatId)");
$reply = callChatAPI($text, $chatId);
sendTelegram($chatId, $reply);
wlog('=== Fin traitement ===');
exit;

// ── FONCTIONS ───────────────────────────────────────────────
function callChatAPI(string $message, int $chatId): string {
    $payload = json_encode([
        'message'    => $message,
        'canal'      => 'telegram',
        'session_id' => 'tg_'.$chatId,
    ]);

    // Appel interne à api/chat.php
    $url = APP_URL . 'https://visitci-production.up.railway.app/api/chat.php';
    wlog("→ Appel: $url");

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $res      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($res === false || $curlErr) {
        wlog("cURL error: $curlErr");
        return "⚠️ Désolé, je ne peux pas accéder à ma base de données en ce moment. Réessayez dans un instant.";
    }

    wlog("Réponse API HTTP $httpCode: $res");

    $data = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wlog("JSON parse error: ".json_last_error_msg()." — réponse: $res");
        return "⚠️ Erreur interne. Réessayez dans un instant.";
    }

    return $data['reply'] ?? "Désolé, je n'ai pas pu traiter votre demande.";
}

function sendTelegram(int $chatId, string $text, bool $markdown = false): void {
    wlog("→ Envoi Telegram à $chatId: ".mb_substr($text, 0, 80).'...');

    // Telegram limite à 4096 caractères par message
    $chunks = mb_str_split($text, 4000);
    foreach ($chunks as $chunk) {
        $params = [
            'chat_id'                  => $chatId,
            'text'                     => $chunk,
            'disable_web_page_preview' => true,
        ];
        if ($markdown) $params['parse_mode'] = 'Markdown';

        $ch = curl_init('https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $res      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            wlog("Telegram cURL error: $curlErr");
        } elseif ($httpCode !== 200) {
            // Retry sans parse_mode si erreur de parsing Markdown
            if ($markdown) {
                wlog("Telegram HTTP $httpCode (markdown), retry sans parse_mode");
                sendTelegram($chatId, $text, false);
                return;
            }
            wlog("Telegram HTTP $httpCode: $res");
        } else {
            wlog("Message Telegram envoyé OK");
        }
    }
}
