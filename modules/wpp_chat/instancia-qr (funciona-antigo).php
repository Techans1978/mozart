<?php
// public/modules/wpp_chat/instancia-qr.php

// DEBUG – pode desligar depois
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/includes/helpers.php';
// require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/wpp-client.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

proteger_pagina();

$dbc = $conn ?? null;
if (!$dbc instanceof mysqli) {
    die('Erro: conexão com o banco não encontrada.');
}

if (!function_exists('wpp_get_instance_config')) {
    die('Erro: função wpp_get_instance_config não encontrada.');
}
if (!function_exists('wpp_session_start_call')) {
    die('Erro: função wpp_session_start_call não encontrada.');
}
if (!function_exists('wpp_get_qr')) {
    die('Erro: função wpp_get_qr não encontrada.');
}
if (!function_exists('wpp_get_status')) {
    die('Erro: função wpp_get_status não encontrada.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Instância inválida');
}

$config = wpp_get_instance_config($dbc, $id);
if (!$config) {
    die('Instância não encontrada ou inativa');
}

$is_ajax = !empty($_GET['ajax']);

// MODO DEBUG BRUTO (acessa /instancia-qr.php?id=1&debug_raw=1)
if (!empty($_GET['debug_raw'])) {
    header('Content-Type: text/plain; charset=utf-8');

    echo "CONFIG:\n";
    print_r($config);

    echo "\n\n*** CHAMANDO wpp_session_start_call ***\n";
    try {
        $start = wpp_session_start_call($config);
        print_r($start);
    } catch (Throwable $e) {
        echo "ERRO start_call: " . $e->getMessage() . "\n";
    }

    echo "\n\n*** CHAMANDO wpp_get_qr ***\n";
    try {
        $qrRes = wpp_get_qr($config);
        print_r($qrRes);
    } catch (Throwable $e) {
        echo "ERRO get_qr: " . $e->getMessage() . "\n";
    }

    echo "\n\n*** CHAMANDO wpp_get_status ***\n";
    try {
        $statusR = wpp_get_status($config);
        print_r($statusR);
    } catch (Throwable $e) {
        echo "ERRO get_status: " . $e->getMessage() . "\n";
    }

    exit;
}

// Se for AJAX -> JSON
if ($is_ajax) {
    $payload = [
        'error'  => false,
        'qr'     => '',
        'status' => 'DESCONHECIDO',
        'raw'    => [] // pra debug no front
    ];

    try {
        $start = wpp_session_start_call($config);
        $qrRes   = wpp_get_qr($config);
        $statusR = wpp_get_status($config);

        // Guarda bruto pra debug
        $payload['raw'] = [
            'start'   => $start,
            'qrRes'   => $qrRes,
            'statusR' => $statusR
        ];

        // Normalizamos estrutura: assumindo que wpp_* retorna algo do tipo:
        // [ 'http' => 200, 'body' => '...', 'json' => [ ... ] ]
        $qrJson = isset($qrRes['json']) && is_array($qrRes['json'])
            ? $qrRes['json'] : (is_array($qrRes) ? $qrRes : []);

        $statusJson = isset($statusR['json']) && is_array($statusR['json'])
            ? $statusR['json'] : (is_array($statusR) ? $statusR : []);

        // Tenta vários campos possíveis para o QR
        $qrBase64 =
            ($qrJson['qrcode'] ?? '') ?:
            ($qrJson['base64'] ?? '') ?:
            ($qrJson['qr'] ?? '') ?:
            '';

        // Às vezes a resposta é uma string direta
        if (!$qrBase64 && is_string($qrRes)) {
            $qrBase64 = $qrRes;
        }

        // Tenta vários campos possíveis para status
        $status =
            $statusJson['status'] ??
            $statusJson['state'] ??
            $qrJson['status'] ??
            'DESCONHECIDO';

        $payload['qr']     = $qrBase64;
        $payload['status'] = $status;

    } catch (Throwable $e) {
        $payload['error'] = true;
        $payload['status'] = 'ERRO';
        $payload['raw'] = [
            'exception' => $e->getMessage()
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Página normal
require_once ROOT_PATH . '/system/includes/head.php';
?>
<link rel="stylesheet" href="assets/css/wpp_chat.css?ver=1">
<script src="assets/js/wpp_chat.js?ver=1"></script>

<div class="container-fluid mt-3">
    <h2>QR Code / Status — <?php echo wpp_h($config['nome']); ?></h2>
    <p>Use o QR Code abaixo para logar no WhatsApp Web desta instância.</p>

    <div class="row">
        <div class="col-md-5 text-center">
            <img id="qrImg" class="wpp-qrcode-img" src="" alt="QR Code">
            <p class="mt-2">
                Status: <span id="statusSession" class="font-weight-bold">...</span>
            </p>
            <button class="btn btn-sm btn-primary"
                    onclick="wppRefreshQr(<?php echo (int)$id; ?>, '#qrImg', '#statusSession', '#debugArea');">
                Atualizar QR / Status
            </button>
        </div>
        <div class="col-md-7">
            <p class="text-muted">
                Clique em "Atualizar" para buscar ou renovar o QR Code. Algumas instâncias podem demorar um pouco
                para iniciar.
            </p>
            <pre id="debugArea" style="display:block; max-height:300px; overflow:auto; background:#f7f7f7; border:1px solid #ddd; padding:10px; font-size:11px;"></pre>
        </div>
    </div>
</div>

<script>
    // Carrega automaticamente ao entrar
    wppRefreshQr(<?php echo (int)$id; ?>, '#qrImg', '#statusSession', '#debugArea');
</script>

<?php
require_once ROOT_PATH . '/system/includes/footer.php';
