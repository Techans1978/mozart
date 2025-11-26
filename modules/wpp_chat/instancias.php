<?php
// public/modules/wpp_chat/instancias.php

// (OPCIONAL) Liga erros enquanto ajusta – depois pode comentar
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

proteger_pagina(); // se já usa isso nas outras telas

// Garante que temos conexão
$dbc = $conn ?? null;
if (!$dbc instanceof mysqli) {
    die('Erro: conexão com o banco não encontrada.');
}

$sql = "
    SELECT id, nome, session_name, host, port, ativo,
           last_qr_at,
           status_atual
      FROM moz_wpp_instance
    ORDER BY id DESC
";

$res = $dbc->query($sql);
$erro_db = null;
$instancias = [];

if ($res === false) {
    // Guarda o erro para mostrar na tela (ou só logar, se preferir)
    $erro_db = 'Erro ao buscar instâncias WPP: ' . $dbc->error;
} else {
    while ($row = $res->fetch_assoc()) {
        $instancias[] = $row;
    }
}

require_once ROOT_PATH . '/system/includes/head.php';
?>
<link rel="stylesheet" href="assets/css/wpp_chat.css?ver=1">

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Instâncias WPP</h2>
        <a href="instancia-form.php" class="btn btn-sm btn-success">Nova instância</a>
    </div>

    <?php if ($msg = wpp_flash('wpp_info')): ?>
        <div class="alert alert-success"><?php echo wpp_h($msg); ?></div>
    <?php endif; ?>

    <?php if ($erro_db): ?>
        <div class="alert alert-danger">
            <?php echo wpp_h($erro_db); ?>
        </div>
    <?php endif; ?>

    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Session</th>
                <th>Host/Porta</th>
                <th>Ativo</th>
                <th>Status</th>
                <th>Último QR</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($instancias)): ?>
            <tr>
                <td colspan="8" class="text-center">
                    <?php echo $erro_db ? 'Não foi possível listar as instâncias.' : 'Nenhuma instância cadastrada.'; ?>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($instancias as $row): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><?php echo wpp_h($row['nome']); ?></td>
                    <td><?php echo wpp_h($row['session_name']); ?></td>
                    <td><?php echo wpp_h($row['host'] . ':' . $row['port']); ?></td>
                    <td><?php echo !empty($row['ativo']) ? 'Sim' : 'Não'; ?></td>
                    <td>
                        <?php if ($row['status_atual'] === 'CONNECTED'): ?>
                            <span class="wpp-badge wpp-status-online">ONLINE</span>
                        <?php else: ?>
                            <span class="wpp-badge wpp-status-offline">
                                <?php echo wpp_h($row['status_atual'] ?: 'DESCONHECIDO'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo wpp_h($row['last_qr_at']); ?></td>
                    <td>
                        <a href="instancia-form.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                        <a href="instancia-qr.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-secondary">QR / Status</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
require_once ROOT_PATH . '/system/includes/footer.php';
