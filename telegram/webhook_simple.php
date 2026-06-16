<?php
// /telegram/webhook.php - SIMPLIFIÉ
define('TELEGRAM_TOKEN', getenv('TELEGRAM_TOKEN') ?: '8948292036:AAHCOShkRZBXRIWWaGAvZTkim5Cguay_BAQ');
define('APP_URL', getenv('APP_URL') ?: 'https://visitci.free.je');

// Réponse immédiate
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
flush();

// Lire une seule fois
$rawInput = file_get_contents('php://input');
$update = json_decode($rawInput, true);

if (!$update) exit;

$msg = $update['message'] ?? null;
if (!$msg) exit;

$chatId = $msg['chat']['id'] ?? null;
$text = trim($msg['text'] ?? '');

if (!$chatId || !$text) exit;

// ── Stocker en BD au lieu d'appeler directement ──
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=n8n;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("
        INSERT INTO telegram_queue (chat_id, user_message, status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$chatId, $text]);
    
} catch (Exception $e) {
    // Log en fichier si BD échoue
    error_log('Queue error: ' . $e->getMessage());
}

exit;
