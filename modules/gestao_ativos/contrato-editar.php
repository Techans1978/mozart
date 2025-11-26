<?php
// public/modules/gestao_ativos/contrato-editar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function migrate(mysqli $db){
  $db->query("CREATE TABLE IF NOT EXISTS moz_contrato_template (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    conteudo LONGTEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
migrate($dbc);

$id = (int)($_GET['id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $id = (int)($_POST['id'] ?? 0);
  $nome = trim($_POST['nome'] ?? '');
  $slug = trim($_POST['slug'] ?? '');
  $conteudo = $_POST['conteudo'] ?? '';
  if($nome==='' || $slug===''){ die('Nome/slug obrigatórios.'); }

  if($id>0){
    $st=$dbc->prepare("UPDATE moz_contrato_template SET nome=?, slug=?, conteudo=? WHERE id=?");
    $st->bind_param('sssi',$nome,$slug,$conteudo,$id); $st->execute(); $st->close();
  } else {
    $st=$dbc->prepare("INSERT INTO moz_contrato_template (nome,slug,conteudo,ativo) VALUES (?,?,?,1)");
    $st->bind_param('sss',$nome,$slug,$conteudo); $st->execute(); $id=$st->insert_id; $st->close();
  }
  header("Location: contratos-modelos-listar.php"); exit;
}

$modelo = ['id'=>'','nome'=>'','slug'=>'','conteudo'=>''];
if($id>0){
  $st=$dbc->prepare("SELECT * FROM moz_contrato_template WHERE id=?");
  $st->bind_param('i',$id); $st->execute();
  $modelo=$st->get_result()->fetch_assoc(); $st->close();
}

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= $id?'Editar modelo':'Novo modelo' ?></h1></div></div>

  <form method="post" class="card" autocomplete="off">
    <input type="hidden" name="id" value="<?= htmlspecialchars($modelo['id'] ?? '') ?>">
    <div class="grid cols-3">
      <div><label>Nome *</label><input class="form-control" name="nome" value="<?= htmlspecialchars($modelo['nome']) ?>" required></div>
      <div><label>Slug *</label><input class="form-control" name="slug" value="<?= htmlspecialchars($modelo['slug']) ?>" required></div>
      <div><label>Dicas</label><div class="hint">Use <code>{chaves}</code> para placeholders (ex.: <code>{nome_colaborador}</code>, <code>{empresa}</code>, <code>{ativo_id}</code>).</div></div>
    </div>
    <div class="stack">
      <label>Conteúdo do modelo (texto contrato)</label>
      <textarea name="conteudo" rows="22" class="form-control" style="font-family:monospace"><?= htmlspecialchars($modelo['conteudo']) ?></textarea>
      <div class="hint">Aceita quebras de linha; será renderizado como parágrafos. Para negrito/itálico, você pode usar Markdown simples **texto** ou *texto* se preferir — trataremos isso futuramente.</div>
    </div>
    <div class="row" style="justify-content:flex-end;gap:8px">
      <a class="btn btn-default" href="contratos-modelos-listar.php">Cancelar</a>
      <button class="btn btn-primary">Salvar modelo</button>
    </div>
  </form>
</div></div>
<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
