<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$ok  = (isset($_GET['ok']) && $_GET['ok']=='1');
$msg = $_GET['msg'] ?? '';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$papel = null;
if ($id>0){
  $st = $conn->prepare("SELECT id, nome, codigo, parent_id, ativo FROM papeis WHERE id=?");
  $st->bind_param('i',$id); $st->execute();
  $papel = $st->get_result()->fetch_assoc(); $st->close();
  if (!$papel){ die('Papel não encontrado.'); }
}

// opções de pai com caminho
$opts = [];
$sqlOpts = "
SELECT p.id,
       COALESCE((
         SELECT GROUP_CONCAT(a.nome ORDER BY pp2.depth ASC SEPARATOR ' -> ')
         FROM papeis_paths pp2
         JOIN papeis a ON a.id=pp2.ancestor_id
         WHERE pp2.descendant_id=p.id
       ), p.nome) AS label
FROM papeis p
ORDER BY label ASC";
if ($r=$conn->query($sqlOpts)) while($x=$r->fetch_assoc()) $opts[]=$x;

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= htmlspecialchars(APP_NAME) ?></h1></div></div>

    <div class="row"><div class="col-lg-8">
      <h2><?= $id? 'Atualizar Papel':'Cadastrar Papel' ?></h2>

      <?php if ($msg!==''): ?>
        <div class="alert alert-<?= $ok?'success':'danger' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>/system/actions/papeis_save.php">
        <?php if($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

        <div class="form-group">
          <label>Nome</label>
          <input class="form-control" type="text" name="nome" required
                 value="<?= htmlspecialchars($papel['nome'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Código/Abreviação</label>
          <input class="form-control" type="text" name="codigo"
                 placeholder="Vazio será salvo como NULL (permite duplicar nulos)."
                 value="<?= htmlspecialchars($papel['codigo'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Papel Pai (opcional)</label>
          <select class="form-control" name="parent_id">
            <option value="">— nenhum —</option>
            <?php foreach($opts as $o):
              if ($id && (int)$o['id']===$id) continue; // evita pai = si mesmo
              $sel = (!empty($papel['parent_id']) && (int)$papel['parent_id']==(int)$o['id']) ? 'selected' : '';
            ?>
              <option value="<?= (int)$o['id'] ?>" <?= $sel ?>><?= htmlspecialchars($o['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Ativo</label>
          <select class="form-control" name="ativo">
            <?php $ativo = isset($papel['ativo']) ? (int)$papel['ativo'] : 1; ?>
            <option value="1" <?= $ativo===1?'selected':''; ?>>Sim</option>
            <option value="0" <?= $ativo===0?'selected':''; ?>>Não</option>
          </select>
        </div>

        <button class="btn btn-primary" type="submit"><?= $id?'Salvar':'Criar' ?></button>
        <a class="btn btn-default" href="<?= BASE_URL ?>/pages/papeis_listar.php">Voltar</a>
      </form>
    </div></div>
  </div>
</div>
<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
