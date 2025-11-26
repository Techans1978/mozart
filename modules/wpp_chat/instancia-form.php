<?php
// public/modules/wpp_chat/instancia-form.php

// Liga erros enquanto ajusta – depois pode comentar
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/includes/helpers.php';
// require_once __DIR__ . '/includes/guard.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

proteger_pagina(); // mesmo padrão do restante do sistema

// Garante conexão
$dbc = $conn ?? null;
if (!$dbc instanceof mysqli) {
    die('Erro: conexão com o banco não encontrada.');
}

$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing  = $id > 0;

$instancia = [
    'nome'         => '',
    'session_name' => '',
    'host'         => '127.0.0.1',
    'port'         => 21465,
    'secret'       => '',
    'ativo'        => 1
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wpp_csrf_check($_POST['_csrf'] ?? '')) {
        die('CSRF inválido');
    }

    $nome         = trim($_POST['nome'] ?? '');
    $session_name = trim($_POST['session_name'] ?? '');
    $host         = trim($_POST['host'] ?? '127.0.0.1');
    $port         = (int)($_POST['port'] ?? 21465);
    $secret       = trim($_POST['secret'] ?? '');
    $ativo        = isset($_POST['ativo']) ? 1 : 0;

    if ($editing) {
        $sql = "
            UPDATE moz_wpp_instance
               SET nome = ?, session_name = ?, host = ?, port = ?, secret = ?, ativo = ?, updated_at = NOW()
             WHERE id = ?
        ";
        $stmt = $dbc->prepare($sql);
        if (!$stmt) {
            die('Erro ao preparar UPDATE: ' . $dbc->error);
        }
        $ok = $stmt->bind_param('sssissi', $nome, $session_name, $host, $port, $secret, $ativo, $id);
        if (!$ok) {
            die('Erro ao bind_param UPDATE: ' . $stmt->error);
        }
        if (!$stmt->execute()) {
            die('Erro ao executar UPDATE: ' . $stmt->error);
        }

        wpp_flash('wpp_info', 'Instância atualizada com sucesso.');
    } else {
        $sql = "
            INSERT INTO moz_wpp_instance (nome, session_name, host, port, secret, ativo, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        $stmt = $dbc->prepare($sql);
        if (!$stmt) {
            die('Erro ao preparar INSERT: ' . $dbc->error);
        }
        $ok = $stmt->bind_param('sssisi', $nome, $session_name, $host, $port, $secret, $ativo);
        if (!$ok) {
            die('Erro ao bind_param INSERT: ' . $stmt->error);
        }
        if (!$stmt->execute()) {
            die('Erro ao executar INSERT: ' . $stmt->error);
        }

        $id = $stmt->insert_id;
        wpp_flash('wpp_info', 'Instância criada com sucesso.');
    }

    wpp_redirect('instancias.php');
    exit;
}

// Carrega dados se edição
if ($editing) {
    $stmt = $dbc->prepare("SELECT * FROM moz_wpp_instance WHERE id = ?");
    if (!$stmt) {
        die('Erro ao preparar SELECT: ' . $dbc->error);
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        die('Erro ao executar SELECT: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row) {
        die('Instância não encontrada');
    }

    $instancia = $row;
}

require_once ROOT_PATH . '/system/includes/head.php';
?>
<link rel="stylesheet" href="assets/css/wpp_chat.css?ver=1">

<div class="container-fluid mt-3">
    <h2><?php echo $editing ? 'Editar instância' : 'Nova instância'; ?></h2>

    <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo wpp_csrf_token(); ?>">

        <div class="form-group">
            <label>Nome</label>
            <input type="text" name="nome" class="form-control" required
                   value="<?php echo wpp_h($instancia['nome']); ?>">
        </div>

        <div class="form-group">
            <label>Session Name (WPPConnect)</label>
            <input type="text" name="session_name" class="form-control" required
                   value="<?php echo wpp_h($instancia['session_name']); ?>">
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Host</label>
                <input type="text" name="host" class="form-control"
                       value="<?php echo wpp_h($instancia['host']); ?>">
            </div>
            <div class="form-group col-md-2">
                <label>Porta</label>
                <input type="number" name="port" class="form-control"
                       value="<?php echo (int)$instancia['port']; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Secret / Token</label>
            <input type="text" name="secret" class="form-control"
                   value="<?php echo wpp_h($instancia['secret']); ?>">
        </div>

        <div class="form-group form-check">
            <input type="checkbox" name="ativo" class="form-check-input" id="ativo"
                   <?php echo !empty($instancia['ativo']) ? 'checked' : ''; ?>>
            <label for="ativo" class="form-check-label">Instância ativa</label>
        </div>

        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="instancias.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php
require_once ROOT_PATH . '/system/includes/footer.php';
