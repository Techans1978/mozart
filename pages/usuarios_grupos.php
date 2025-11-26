<?php
ini_set('display_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$usuario_id = (int)($_GET['usuario_id'] ?? 0);
if ($usuario_id <= 0) die("Usuário inválido.");

$u = $conn->query("SELECT id, nome_completo, email FROM usuarios WHERE id={$usuario_id}")->fetch_assoc();
if (!$u) die("Usuário não encontrado.");

// carregar lista de grupos
$grupos = [];
$res = $conn->query("SELECT id, COALESCE(path_cache,nome) AS label FROM grupos ORDER BY depth ASC,path_cache ASC,nome ASC");
while($r=$res->fetch_assoc()) $grupos[]=$r;

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <h1 class="page-header"><?= APP_NAME ?></h1>
    <h2>Grupos de <?= htmlspecialchars($u['nome_completo']) ?></h2>

    <form id="formAdd" class="form-inline">
      <input type="hidden" name="usuario_id" value="<?= $usuario_id ?>">
      <select name="grupo_id" class="form-control" required>
        <option value="">Selecione o grupo</option>
        <?php foreach($grupos as $g): ?>
          <option value="<?=$g['id']?>"><?=htmlspecialchars($g['label'])?></option>
        <?php endforeach; ?>
      </select>
      <label class="checkbox-inline"><input type="checkbox" name="is_primary" value="1"> Primário</label>
      <button type="submit" class="btn btn-success">Adicionar</button>
      <a href="<?= BASE_URL ?>/pages/usuarios_listar.php" class="btn btn-default">Voltar</a>
    </form>

    <table id="tabela" class="table table-bordered table-striped"></table>
  </div>
</div>
<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<script>
$(function(){
  const $t = $('#tabela').DataTable({
    ajax:{ url:'<?= BASE_URL ?>/services/usuarios_grupos_list.php', data:{usuario_id:<?= $usuario_id ?>}},
    columns:[
      {data:'id',title:'ID'},
      {data:'grupo_label',title:'Grupo'},
      {data:'is_primary',title:'Primário',render:v=>(v==1?'<span class="label label-info">Sim</span>':'Não')},
      {data:null,title:'Ações',orderable:false,render:row=>`
        <button class="btn btn-xs btn-default" onclick="toggle(${row.id},${row.is_primary})">${row.is_primary? 'Desmarcar':'Marcar'} primário</button>
        <button class="btn btn-xs btn-danger" onclick="remover(${row.id})">Remover</button>`}
    ]
  });

  $('#formAdd').on('submit',function(e){
    e.preventDefault();
    $.post('<?= BASE_URL ?>/services/usuarios_grupos_add.php',$(this).serialize(),r=>{
      if(r.ok) $t.ajax.reload(); else alert(r.mensagem);
    },'json');
  });

  window.remover=id=>{
    if(confirm("Remover vínculo?")) $.post('<?= BASE_URL ?>/services/usuarios_grupos_delete.php',{id},r=>{
      if(r.ok) $t.ajax.reload(); else alert(r.mensagem);
    },'json');
  };
  window.toggle=(id,v)=>{
    $.post('<?= BASE_URL ?>/services/usuarios_grupos_toggle_primary.php',{id,valor:(v?0:1)},r=>{
      if(r.ok) $t.ajax.reload(); else alert(r.mensagem);
    },'json');
  };
});
</script>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
