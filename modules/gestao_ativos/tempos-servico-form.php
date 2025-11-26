<?php
// CRUD simples de tempos de serviço (SLA de execução)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

$dbc->query("CREATE TABLE IF NOT EXISTS moz_sla_servico (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  tempo_min INT UNSIGNED NOT NULL,          -- minutos (ex.: 120 = 2h)
  descricao VARCHAR(255) NULL,
  categoria VARCHAR(120) NULL,              -- opcional: Notebook, Impressora...
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id=(int)($_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)($_POST['id']??0);
  $nome=$_POST['nome']; $tempo=(int)$_POST['tempo_min']; $desc=$_POST['descricao']??null; $cat=$_POST['categoria']??null;
  if($id>0){
    $st=$dbc->prepare("UPDATE moz_sla_servico SET nome=?,tempo_min=?,descricao=?,categoria=? WHERE id=?");
    $st->bind_param('sissi',$nome,$tempo,$desc,$cat,$id); $st->execute(); $st->close();
  } else {
    $st=$dbc->prepare("INSERT INTO moz_sla_servico (nome,tempo_min,descricao,categoria,ativo) VALUES (?,?,?,?,1)");
    $st->bind_param('siss',$nome,$tempo,$desc,$cat); $st->execute(); $id=$st->insert_id; $st->close();
  }
  header("Location: tempos-servico-listar.php?ok=1"); exit;
}
$rec=['nome'=>'','tempo_min'=>120,'descricao'=>'','categoria'=>''];
if($id){ $rec=$dbc->query("SELECT * FROM moz_sla_servico WHERE id=".$id)->fetch_assoc() ?: $rec; }

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= $id?'Editar tempo':'Novo tempo de serviço' ?></h1></div></div>

  <form class="card" method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?=$id?>">
    <div class="grid cols-3">
      <div><label>Nome *</label><input name="nome" required value="<?=h($rec['nome'])?>" placeholder="Formatação Notebook"></div>
      <div><label>Tempo (min) *</label><input type="number" name="tempo_min" min="1" value="<?= (int)$rec['tempo_min'] ?>"></div>
      <div><label>Categoria</label><input name="categoria" value="<?=h($rec['categoria'])?>" placeholder="Notebook / Impressora"></div>
    </div>
    <div><label>Descrição</label><textarea name="descricao" placeholder="Detalhe do serviço"><?=h($rec['descricao'])?></textarea></div>
    <div class="row" style="justify-content:flex-end;gap:8px">
      <a class="btn" href="tempos-servico-listar.php">Cancelar</a>
      <button class="btn primary">Salvar</button>
    </div>
  </form>
</div></div>
<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
