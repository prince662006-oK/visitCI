<?php
// ============================================================
//  telegram/log.php — Visualiseur de logs webhook
//  Visite cette URL pour voir les derniers logs Telegram
// ============================================================
header('Content-Type: text/plain; charset=utf-8');

$logFile = sys_get_temp_dir() . '/visitci_webhook.log';

if (!file_exists($logFile)) {
    echo "Aucun log trouvé encore à : $logFile\n";
    echo "Envoie un message à ton bot Telegram, puis recharge cette page.\n";
    exit;
}

$lines = file($logFile);
$lastLines = array_slice($lines, -80); // 80 dernières lignes
echo implode('', $lastLines);
