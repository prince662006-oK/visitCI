<?php
define('TELEGRAM_TOKEN', getenv('TELEGRAM_TOKEN') ?: '8948292036:AAHCOShkRZBXRIWWaGAvZTkim5Cguay_BAQ');
define('GROQ_API_KEY',   getenv('GROQ_API_KEY')   ?: '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');
$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https'
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

define('APP_URL', getenv('APP_URL') ?: $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'visitci-production.up.railway.app'));
define('LOG_FILE', __DIR__ . '/webhook.log');

function logWebhook($message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    
    // Essayer d'écrire dans le fichier log
    $result = @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    
    // Si ça échoue, utiliser error_log comme fallback
    if ($result === false) {
        error_log($line);
    }
}

// ── LIRE php://input UNE SEULE FOIS ──
$rawInput = file_get_contents('php://input');
logWebhook('Webhook reçu - Method: '.$_SERVER['REQUEST_METHOD'].' - Size: '.strlen($rawInput).' bytes - APP_URL: '.APP_URL);

ignore_user_abort(true);

// ── Réponse immédiate à Telegram ──
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ── Traitement du message en arrière-plan ──
$update = json_decode($rawInput, true);
if (!$update) {
    logWebhook('Erreur JSON: ' . json_last_error_msg());
    exit;
}

logWebhook('Update reçu: ' . json_encode($update));

$msg = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? null;
if (!$msg && isset($update['callback_query']['message'])) {
    $msg = $update['callback_query']['message'];
}
if (!$msg) exit;
$chatId = $msg['chat']['id'];
$text   = trim($msg['text'] ?? $msg['caption'] ?? ($update['callback_query']['data'] ?? ''));
$from   = $msg['from']['first_name'] ?? 'Touriste';

if (!$text) exit;

// Appel à notre moteur IA central
$response = callChatAPI($text, $chatId);
sendTelegram($chatId, $response);

// ── FONCTIONS ────────────────────────────────────────────────
function callChatAPI(string $message, int $chatId): string {
    $payload = json_encode([
        'message'    => $message,
        'canal'      => 'telegram',
        'session_id' => 'tg_'.$chatId,
    ]);

    logWebhook("Appel API: $message (chatId: $chatId)");
    $baseUrl = APP_URL;
    $ch = curl_init($baseUrl.'/api/chat.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res  = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($res === false) {
        logWebhook('Chat API cURL error: '.curl_error($ch));
        curl_close($ch);
        return "Désolé, une erreur s'est produite. Réessayez.";
    }
    logWebhook("Chat API HTTP $httpCode: $res");
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['reply'] ?? "Désolé, une erreur s'est produite. Réessayez.";
}

function sendTelegram(int $chatId, string $text): void {
    logWebhook("Envoi message: $text (chatId: $chatId)");
    $payload = http_build_query([
        'chat_id'    => $chatId,
        'text'       => $text,
        'disable_web_page_preview' => true,
    ]);
    $ch = curl_init('https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($res === false) {
        logWebhook('Telegram sendMessage cURL error: '.curl_error($ch));
    } elseif ($httpCode !== 200) {
        logWebhook('Telegram sendMessage HTTP '.$httpCode.': '.$res);
    } else {
        logWebhook('Message envoyé avec succès');
    }
    curl_close($ch);
}

logWebhook('=== Fin traitement ===');
logWebhook('');

exit;
