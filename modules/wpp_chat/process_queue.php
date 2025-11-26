<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/includes/wpp_webhook.php';

$processados = 0;

while (wpp_webhook_process_one($conn)) {
    $processados++;
    if ($processados > 100) break; // Segurança
}

echo "Processados: {$processados}";
