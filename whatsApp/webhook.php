<?php

define('WA_TOKEN',   getenv('WA_TOKEN')   ?: 'votre_token_whatsapp');
define('WA_PHONE_ID',getenv('WA_PHONE_ID')?:'votre_phone_id');
define('WA_VERIFY',  getenv('WA_VERIFY')  ?: 'visitci_verify_token');

// Vérification webhook Meta (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']          ?? '';
    $token     = $_GET['hub_verify_token']  ?? '';
    $challenge = $_GET['hub_challenge']     ?? '';
    if ($mode === 'subscribe' && $token === WA_VERIFY) {
        echo $challenge; exit;
    }
    http_response_code(403); exit;
}

// Réception message (POST)
$body = json_decode(file_get_contents('php://input'), true);
$entry = $body['entry'][0]['changes'][0]['value'] ?? null;
if (!$entry || !isset($entry['messages'])) exit;

$waMsg  = $entry['messages'][0];
$from   = $waMsg['from'];
$type   = $waMsg['type'];
$text   = '';

if ($type === 'text') {
    $text = trim($waMsg['text']['body'] ?? '');
} elseif ($type === 'interactive') {
    $text = $waMsg['interactive']['button_reply']['title']
          ?? $waMsg['interactive']['list_reply']['title'] ?? '';
}

if (!$text) exit;

$reply = callChatAPI($text, $from);
sendWhatsApp($from, $reply);

// ── FONCTIONS ────────────────────────────────────────────────
function callChatAPI(string $message, string $from): string {
    $payload = json_encode([
        'message'    => $message,
        'canal'      => 'whatsapp',
        'session_id' => 'wa_'.$from,
    ]);
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
    $ch = curl_init($baseUrl.'/api/chat.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res  = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['reply'] ?? "Désolé, une erreur s'est produite.";
}

function sendWhatsApp(string $to, string $text): void {
    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['body' => $text],
    ]);
    $ch = curl_init('https://graph.facebook.com/v18.0/'.WA_PHONE_ID.'/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer '.WA_TOKEN,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}