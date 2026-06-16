<?php
$logFile = __DIR__ . '/telegram/webhook.log';

echo "<style>
    body { font-family: monospace; background: #0a0f1c; color: #00ff00; padding: 20px; }
    pre { background: #111; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .button { background: #F4A426; color: #000; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px; margin: 5px; }
    .info { color: #FFD07A; margin: 10px 0; }
    .error { color: #FF6B6B; }
</style>";

echo "<h1>📋 Logs Webhook Telegram</h1>";
echo "<div class='info'>";
echo "📁 Fichier: <code>$logFile</code><br>";
echo "✓ Existe: " . (file_exists($logFile) ? "OUI" : "<span class='error'>NON</span>") . "<br>";

if (file_exists($logFile)) {
    echo "✓ Readable: " . (is_readable($logFile) ? "OUI" : "<span class='error'>NON</span>") . "<br>";
    echo "✓ Writable: " . (is_writable($logFile) ? "OUI" : "<span class='error'>NON</span>") . "<br>";
    echo "✓ Size: " . filesize($logFile) . " bytes<br>";
}

echo "✓ Dir writable: " . (is_writable(__DIR__ . '/telegram') ? "OUI" : "<span class='error'>NON</span>") . "<br>";
echo "</div>";

echo "<button class='button' onclick='location.reload()'>🔄 Rafraîchir</button>";
echo "<button class='button' onclick='clearLogs()'>🗑️ Effacer</button>";

if (!file_exists($logFile)) {
    echo "<p class='error'>Aucun fichier log trouvé. Le webhook n'a pas encore été appelé ou les permissions d'écriture sont refusées.</p>";
} else {
    $logs = file_get_contents($logFile);
    $logs = htmlspecialchars($logs);
    echo "<pre>$logs</pre>";
}

?>

<script>
    function clearLogs() {
        if (confirm('Êtes-vous sûr?')) {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?clear=1')
                .then(() => location.reload());
        }
    }
</script>

<?php
if (isset($_GET['clear']) && file_exists($logFile)) {
    file_put_contents($logFile, '');
    echo "<p class='info'>✓ Logs effacés</p>";
}
