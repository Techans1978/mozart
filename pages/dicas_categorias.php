<?php
// pages/dicas_categorias.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tok(){ if(empty($_SESSION['csrf_dicas_cat'])) $_SESSION['csrf_dicas_cat']=bin2hex(random_bytes(16)); return $_SESSION['csrf_dicas_cat']; }
function check_tok($t){ return !empty($_SESSION['csrf_dicas_cat']) && hash_equals($_SESSION['csrf_dicas_cat'], (string)$t); }

$err=null; $ok=null;

/* POST: salvar/editar/excluir */
if($_SERVER['REQUEST_METHOD']==='POST'){
  $csrf = $_POST['csrf'] ?? '';
  if(!check_tok($csrf)){ $err='Token inválido.'; }
  else {
    $acao = $_POST['acao'] ?? '';
    if ($acao==='salvar'){
      $id        = (int)($_POST['id'] ?? 0);
      $titulo    = trim($_POST['titulo'] ?? '');
      $apelido   = trim($_POST['apelido'] ?? '');
      $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
      $published = isset($_POST['published']) ? 1 : 0;
      $ordering  = (int)($_POST['ordering'] ?? 0);
      $descricao = trim($_POST['descricao'] ?? '');

      if($titulo==='' || $apelido===''){ $err='Título e Apelido são obrigatórios.'; }
      else {
        if($id>0){
          $st=$conn->prepare("UPDATE dicas_categories SET titulo=?,apelido=?,parent_id=?,published=?,ordering=?,descricao=?,updated_at=NOW(),updated_by=? WHERE id=? LIMIT 1");
          $uid=(int)($_SESSION['user_id']??0);
          $st->bind_param('sssissii',$titulo,$apelido,$parent_id,$published,$ordering,$descricao,$uid,$id);
          $ok=$st->execute(); $st->close();
          if(!$ok) $err='Falha ao atualizar.';
        }else{
          $st=$conn->prepare("INSERT INTO dicas_categories(titulo,apelido,parent_id,published,ordering,descricao,created_at,created_by) VALUES(?,?,?,?,?,?,NOW(),?)");
          $uid=(int)($_SESSION['user_id']??0);
          $st->bind_param('sssissi',$titulo,$apelido,$parent_id,$published,$ordering,$descricao,$uid);
          $ok=$st->execute(); $st->close();
          if(!$ok) $err='Falha ao inserir.';
        }
      }
    }
    if($acao==='excluir'){
      $id=(int)($_POST['id']??0);
      if($id>0){
        $st=$conn->prepare("DELETE FROM dicas_categories WHERE id=? LIMIT 1");
        $st->bind_param('i',$id); $ok=$st->execute(); $st->close();
        if(!$ok) $err='Não foi possível excluir (possui filhos ou vínculos).';
      }
    }
  }
}

/* listas */
$cats=[]; if($r=$conn->query("SELECT id,titulo FROM dicas_categories ORDER BY titulo")) while($x=$r->fetch_assoc()) $cats[]=$x;

$rows=[]; $res=$conn->query("SELECT c.id,c.titulo,c.apelido,COALESCE(p.titulo,'-') AS pai,c.published,c.ordering
                             FROM dicas_categories c
                             LEFT JOIN dicas_categories p ON p.id=c.parent_id
                             ORDER BY c.ordering,c.titulo");
while($x=$res->fetch_assoc()) $rows[]=$x;

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Categorias — Dicas</h1></div></div>

  <?ph
