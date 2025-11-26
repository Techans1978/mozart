<?php
// public/modules/gestao_ativos/categorias-form.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
proteger_pagina();

// CSRF
if (empty($_SESSION['csrf_cat_form'])) $_SESSION['csrf_cat_form'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_cat_form'];

// Conexão
$dbc = $conn ?? null;
if (!$dbc) { die('Sem conexão com o banco.'); }

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$err  = '';
$ok   = '';

// POST: salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

    $id   = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $pai  = $_POST['pai_id'] === '' ? null : (int)$_POST['pai_id'];

    // validações
    if ($nome === '') $err = 'Informe o nome da categoria.';
    if (!$err && $id > 0 && $pai === $id) $err = 'A categoria pai não pode ser ela mesma.';

    // evita ciclo básico: não permitir mover para um filho
    if (!$err && $id > 0 && $pai !== null) {
        // sobe recursivamente a cadeia de pais para ver se $pai é descendente de $id
        $cur = $pai;
        while ($cur !== null) {
            $q = $dbc->prepare("SELECT pai_id FROM moz_cat_ativo WHERE id = ?");
            $q->bind_param('i', $cur);
            $q->execute();
            $q->bind_result($curPai);
            $found = $q->fetch();
            $q->close();
            if (!$found) break;
            if ((int)$cur === $id) { $err = 'Categoria pai não pode ser descendente (ciclo).'; break; }
            $cur = $curPai === null ? null : (int)$curPai;
        }
    }

    if (!$err) {
        if ($id > 0) {
            // update
            $stmt = $dbc->prepare("UPDATE moz_cat_ativo SET nome = ?, pai_id = ? WHERE id = ?");
            // bind nulo corretamente
            if ($pai === null) {
                $null = null;
                $stmt->bind_param('sii', $nome, $null, $id);
                // MySQLi não envia NULL assim em ints; alternativa:
                $stmt->close();
                $stmt = $dbc->prepare("UPDATE moz_cat_ativo SET nome = ?, pai_id = NULL WHERE id = ?");
                $stmt->bind_param('si', $nome, $id);
            } else {
                $stmt->bind_param('sii', $nome, $pai, $id);
            }
            $stmt->execute();
            $ok = $stmt->affected_rows >= 0 ? 'Categoria atualizada.' : '';
            $stmt->close();
        } else {
            // insert
            if ($pai === null) {
                $stmt = $dbc->prepare("INSERT INTO moz_cat_ativo (nome, pai_id) VALUES (?, NULL)");
                $stmt->bind_param('s', $nome);
            } else {
                $stmt = $dbc->prepare("INSERT INTO moz_cat_ativo (nome, pai_id) VALUES (?, ?)");
                $stmt->bind_param('si', $nome, $pai);
            }
            $stmt->execute();
            $ok = $stmt->insert_id ? 'Categoria criada.' : '';
            if ($stmt->errno === 1062) $err = 'Já existe uma categoria com este nome.';
            $id = (int)$stmt->insert_id;
            $stmt->close();
        }
        if (!$err && $ok) {
            $_SESSION['flash_ok'] = $ok;
            header('Location: '.BASE_URL.'/modules/gestao_ativos/categorias-listar.php');
            exit;
        }
    }
}

// Carrega registro (GET ou quando houve erro de POST)
$nome = '';
$pai_id = '';
if ($id > 0) {
    $st = $dbc->prepare("SELECT id, nome, pai_id FROM moz_cat_ativo WHERE id = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $st->bind_result($rid, $rnome, $rpai);
    if ($st->fetch()) { $nome = $rnome; $pai_id = $rpai; }
    $st->close();
}

// Pais para select
$parents = [];
$res = $dbc->query("SELECT id, nome FROM moz_cat_ativo ORDER BY nome");
while ($r = $res->fetch_assoc()) $parents[] = $r;

// Abre <html><head>...<body>
include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <h3 class="page-header"><?= $id>0 ? 'Editar' : 'Nova' ?> Categoria de Ativo</h3>

    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="panel panel-default">
      <div class="panel-heading">Dados</div>
      <div class="panel-body">
        <form method="post" class="form-horizontal" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$id ?>">

          <div class="form-group">
            <label class="col-sm-2 control-label" for="nome">Nome</label>
            <div class="col-sm-8">
              <input class="form-control" id="nome" name="nome" required maxlength="120"
                     value="<?= htmlspecialchars($nome) ?>" placeholder="Ex.: Notebook">
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-2 control-label" for="pai_id">Categoria Pai</label>
            <div class="col-sm-6">
              <select class="form-control" id="pai_id" name="pai_id">
                <option value="">— sem pai —</option>
                <?php foreach ($parents as $p): if ($id>0 && (int)$p['id']===$id) continue; ?>
                  <option value="<?= (int)$p['id'] ?>" <?= ($pai_id!=='' && (int)$pai_id === (int)$p['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($p['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <div class="col-sm-offset-2 col-sm-8">
              <a href="<?= BASE_URL ?>/modules/gestao_ativos/categorias-listar.php" class="btn btn-default">Voltar</a>
              <button class="btn btn-primary">Salvar</button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
