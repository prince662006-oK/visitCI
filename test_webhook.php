<?php
// Simule un message depuis Telegram

$telegramUpdate = [
    'update_id' => 123456789,
    'message' => [
        'message_id' => 1,
        'date' => time(),
        'chat' => [
            'id' => 987654321,
            'first_name' => 'Test',
            'type' => 'private',
        ],
        'from' => [
            'id' => 987654321,
            'is_bot' => false,
            'first_name' => 'Testeur',
        ],
        'text' => 'restaurant abidjan',
    ]
];

$payload = json_encode($telegramUpdate);

echo "<pre>";
echo "=== TEST WEBHOOK TELEGRAM ===\n";
echo "Payload envoyé :\n";
echo json_encode($telegramUpdate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\nAppel au webhook...\n";

$ch = curl_init('https://visitci.free.je/telegram/webhook.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,  // Pour tests (ne pas utiliser en prod)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
if ($error) echo "Error: $error\n";

echo "\n=== FIN TEST ===\n";
echo "</pre>";
