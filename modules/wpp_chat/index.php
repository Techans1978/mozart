<?php
// public/modules/wpp_chat/index.php
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once __DIR__.'/includes/helpers.php';
// require_once __DIR__.'/includes/guard.php';

require_once ROOT_PATH.'/system/includes/header.php';

global $conn;

// Contagens simples (ajuste nomes de tabela se preciso)
$instances_total = $conn->query("SELECT COUNT(*) AS c FROM moz_wpp_instance")->fetch_assoc()['c'];
$campaigns_total = $conn->query("SELECT COUNT(*) AS c FROM moz_wpp_campanha")->fetch_assoc()['c'];
$conversas_total = $conn->query("SELECT COUNT(*) AS c FROM moz_wpp_conversa")->fetch_assoc()['c'];

?>
<link rel="stylesheet" href="assets/css/wpp_chat.css?ver=1">

<div class="container-fluid mt-3">
    <h1>WPP Chat — Dashboard</h1>
    <p class="text-muted">Visão geral das instâncias, campanhas e conversas.</p>

    <div class="row">
        <div class="col-md-4">
            <div class="wpp-card">
                <strong>Instâncias</strong>
                <div class="display-4"><?php echo (int)$instances_total; ?></div>
                <a href="instancias.php" class="btn btn-sm btn-primary mt-2">Gerenciar instâncias</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="wpp-card">
                <strong>Campanhas</strong>
                <div class="display-4"><?php echo (int)$campaigns_total; ?></div>
                <a href="campanhas.php" class="btn btn-sm btn-primary mt-2">Ver campanhas</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="wpp-card">
                <strong>Conversas</strong>
                <div class="display-4"><?php echo (int)$conversas_total; ?></div>
                <a href="conversas.php" class="btn btn-sm btn-primary mt-2">Ver conversas</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH.'/system/includes/footer.php';
