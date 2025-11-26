<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!($conn instanceof mysqli)) { die('Conexão MySQLi $conn não encontrada.'); }
session_start();

$id = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$codigo = trim($_POST['codigo'] ?? '');
$parent_id = ($_POST['parent_id'] === '' ? null : (int)$_POST['parent_id']);
$sort_order = (int)($_POST['sort_order'] ?? 0);
$ativo = isset($_POST['ativo']) ? 1 : 0;

if ($nome===''){ $_SESSION['__flash']=['m'=>'Nome é obrigatório.']; header('Location: ../categorias_bpm_form.php'.($id?('?id='.$id):'')); exit; }

try{
  $conn->begin_transaction();

  if ($id<=0){
    // INSERT
    $st = $conn->prepare("INSERT INTO bpm_categorias (nome,codigo,parent_id,sort_order,ativo) VALUES (?,?,?,?,?)");
    $st->bind_param('ssiii',$nome,$codigo,$parent_id,$sort_order,$ativo);
    $st->execute(); $id = $st->insert_id; $st->close();

    // closure: caminhos — herda ancestrais do pai + self
    if ($parent_id){
      $conn->query("INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth)
                    SELECT ancestor_id, $id, depth+1 FROM bpm_categorias_paths WHERE descendant_id = $parent_id");
    }
    $conn->query("INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth) VALUES ($id,$id,0)");

  } else {
    // UPDATE (sem mover com filhos no v1)
    // Verifica se mudou o pai
    $old = $conn->query("SELECT parent_id FROM bpm_categorias WHERE id=$id")->fetch_assoc();
    $old_parent = $old ? ($old['parent_id']===null?null:(int)$old['parent_id']) : null;
    $moving = ($old_parent !== $parent_id);

    // se estiver mudando o pai e tiver filhos, bloquear no v1
    if ($moving){
      $rs = $conn->query("SELECT 1 FROM bpm_categorias_paths WHERE ancestor_id=$id AND depth>0 LIMIT 1");
      if ($rs && $rs->num_rows>0){
        $_SESSION['__flash']=['m'=>'Não é possível mover categoria que possui subcategorias (v1). Remova/mova os filhos primeiro.'];
        $conn->rollback();
        header('Location: ../categorias_bpm_form.php?id='.$id);
        exit;
      }
    }

    $st = $conn->prepare("UPDATE bpm_categorias SET nome=?, codigo=?, parent_id=?, sort_order=?, ativo=? WHERE id=?");
    $st->bind_param('ssiiii',$nome,$codigo,$parent_id,$sort_order,$ativo,$id);
    $st->execute(); $st->close();

    if ($moving){
      // remove caminhos antigos envolvendo este nó (exceto outros nós)
      $conn->query("DELETE FROM bpm_categorias_paths WHERE descendant_id=$id");
      // re-insere caminhos herdando ancestrais do novo pai
      if ($parent_id){
        $conn->query("INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth)
                      SELECT ancestor_id, $id, depth+1 FROM bpm_categorias_paths WHERE descendant_id = $parent_id");
      }
      $conn->query("INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth) VALUES ($id,$id,0)");
    }
  }

  $conn->commit();
  $_SESSION['__flash']=['m'=>'Categoria salva com sucesso.'];
  header('Location: ../categorias_bpm_listar.php');
} catch(Exception $e){
  $conn->rollback();
  $_SESSION['__flash']=['m'=>'Erro ao salvar: '.$e->getMessage()];
  header('Location: ../categorias_bpm_form.php'.($id?('?id='.$id):''));
}
