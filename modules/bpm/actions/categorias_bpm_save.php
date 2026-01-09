<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!($conn instanceof mysqli)) { die('Conexão MySQLi $conn não encontrada.'); }

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
proteger_pagina();

function flash_and_redirect($msg, $to){
  $_SESSION['__flash'] = ['m'=>$msg];
  header('Location: '.$to);
  exit;
}

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nome = trim((string)($_POST['nome'] ?? ''));
$codigo = trim((string)($_POST['codigo'] ?? ''));

$parent_id = null;
if (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') {
  $parent_id = (int)$_POST['parent_id'];
  if ($parent_id <= 0) $parent_id = null;
}

$sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
$ativo = isset($_POST['ativo']) ? 1 : 0;

if ($nome === '') {
  flash_and_redirect('Nome é obrigatório.', BASE_URL.'/modules/bpm/categorias_bpm_form.php'.($id?('?id='.$id):''));
}

try {
  $conn->begin_transaction();

  if ($id <= 0) {
    // INSERT
    $st = $conn->prepare("INSERT INTO bpm_categorias (nome,codigo,parent_id,sort_order,ativo) VALUES (?,?,?,?,?)");
    if (!$st) throw new Exception('prepare insert: '.$conn->error);

    // parent_id NULL é OK no bind_param (mysqlnd) — mantém NULL
    $st->bind_param('ssiii', $nome, $codigo, $parent_id, $sort_order, $ativo);

    if (!$st->execute()) throw new Exception('execute insert: '.$st->error);
    $st->close();

    $id = (int)$conn->insert_id;
    if ($id <= 0) throw new Exception('Falha ao obter insert_id.');

    // closure: caminhos — herda ancestrais do pai + self
    if ($parent_id) {
      $sql = "INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth)
              SELECT ancestor_id, ?, depth+1
              FROM bpm_categorias_paths
              WHERE descendant_id = ?";
      $st = $conn->prepare($sql);
      if (!$st) throw new Exception('prepare closure inherit: '.$conn->error);
      $st->bind_param('ii', $id, $parent_id);
      if (!$st->execute()) throw new Exception('execute closure inherit: '.$st->error);
      $st->close();
    }

    $st = $conn->prepare("INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth) VALUES (?,?,0)");
    if (!$st) throw new Exception('prepare closure self: '.$conn->error);
    $st->bind_param('ii', $id, $id);
    if (!$st->execute()) throw new Exception('execute closure self: '.$st->error);
    $st->close();

  } else {
    // UPDATE (sem mover com filhos no v1)

    // Busca parent antigo
    $st = $conn->prepare("SELECT parent_id FROM bpm_categorias WHERE id=? LIMIT 1");
    if (!$st) throw new Exception('prepare select old parent: '.$conn->error);
    $st->bind_param('i', $id);
    if (!$st->execute()) throw new Exception('execute select old parent: '.$st->error);
    $res = $st->get_result();
    $old = $res ? $res->fetch_assoc() : null;
    $st->close();

    if (!$old) throw new Exception('Categoria não encontrada para update.');

    $old_parent = ($old['parent_id'] === null ? null : (int)$old['parent_id']);
    $moving = ($old_parent !== $parent_id);

    // se estiver mudando o pai e tiver filhos, bloquear no v1
    if ($moving) {
      $st = $conn->prepare("SELECT 1 FROM bpm_categorias_paths WHERE ancestor_id=? AND depth>0 LIMIT 1");
      if (!$st) throw new Exception('prepare check filhos: '.$conn->error);
      $st->bind_param('i', $id);
      if (!$st->execute()) throw new Exception('execute check filhos: '.$st->error);
      $res = $st->get_result();
      $hasChildren = ($res && $res->num_rows > 0);
      $st->close();

      if ($hasChildren) {
        $conn->rollback();
        flash_and_redirect(
          'Não é possível mover categoria que possui subcategorias (v1). Remova/mova os filhos primeiro.',
          BASE_URL.'/modules/bpm/categorias_bpm_form.php?id='.$id
        );
      }
    }

    $st = $conn->prepare("UPDATE bpm_categorias SET nome=?, codigo=?, parent_id=?, sort_order=?, ativo=? WHERE id=?");
    if (!$st) throw new Exception('prepare update: '.$conn->error);
    $st->bind_param('ssiiii', $nome, $codigo, $parent_id, $sort_order, $ativo, $id);
    if (!$st->execute()) throw new Exception('execute update: '.$st->error);
    $st->close();

    if ($moving) {
      // remove caminhos antigos envolvendo este nó
      $st = $conn->prepare("DELETE FROM bpm_categorias_paths WHERE descendant_id=?");
      if (!$st) throw new Exception('prepare delete paths: '.$conn->error);
      $st->bind_param('i', $id);
      if (!$st->execute()) throw new Exception('execute delete paths: '.$st->error);
      $st->close();

      // re-insere caminhos herdando ancestrais do novo pai
      if ($parent_id) {
        $sql = "INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth)
                SELECT ancestor_id, ?, depth+1
                FROM bpm_categorias_paths
                WHERE descendant_id = ?";
        $st = $conn->prepare($sql);
        if (!$st) throw new Exception('prepare reinherit: '.$conn->error);
        $st->bind_param('ii', $id, $parent_id);
        if (!$st->execute()) throw new Exception('execute reinherit: '.$st->error);
        $st->close();
      }

      $st = $conn->prepare("INSERT INTO bpm_categorias_paths (ancestor_id, descendant_id, depth) VALUES (?,?,0)");
      if (!$st) throw new Exception('prepare self path: '.$conn->error);
      $st->bind_param('ii', $id, $id);
      if (!$st->execute()) throw new Exception('execute self path: '.$st->error);
      $st->close();
    }
  }

  $conn->commit();
  $_SESSION['__flash'] = ['m'=>'Categoria salva com sucesso.'];
  header('Location: '.BASE_URL.'/modules/bpm/categorias_bpm_listar.php');
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  $_SESSION['__flash'] = ['m'=>'Erro ao salvar: '.$e->getMessage()];
  header('Location: '.BASE_URL.'/modules/bpm/categorias_bpm_form.php'.($id?('?id='.$id):''));
  exit;
}
